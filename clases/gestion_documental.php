<?php
/* =====================================================================
   SISGEDO — Funciones del Modelo de Gestión Documental (MGD)
   RSGD N° 001-2017-PCM/SEGDI
   ---------------------------------------------------------------------
   Librería (NO es un endpoint). Se incluye con:
       require_once __DIR__ . '/gestion_documental.php';

   Contiene:
     - Numeración atómica: expediente, registro interno y documentos
       emitidos (correlativo por oficina / tipo de documento / año).
     - Integridad: huella SHA-256 y metadatos de archivos adjuntos.
     - Código de Verificación Digital (CVD) para representaciones impresas.

   Requiere la migración migraciones/001_mgd_numeracion_integridad.sql.
   Compatible con PHP 7.3+.
   ===================================================================== */

require_once __DIR__ . '/../conexion/conexion.php';

/* Formato del número de documento emitido. Marcadores:
     {NUM}    correlativo con ceros a la izquierda (SGD_DIGITOS_NUMERO)
     {ANIO}   año en curso
     {SIGLAS} nomenclatura de la oficina (oficinas.siglas)
   Resultado por defecto: 045-2026-MPH/GM                                  */
if (!defined('SGD_FORMATO_NUMERO')) define('SGD_FORMATO_NUMERO', '{NUM}-{ANIO}-{SIGLAS}');
if (!defined('SGD_DIGITOS_NUMERO')) define('SGD_DIGITOS_NUMERO', 3);

/** Conexión compartida en modo excepción (independiente de la config de PDO del hosting). */
function sgd_db()
{
    static $db = null;
    if ($db === null) {
        $c = new Conexion();
        $db = $c->conectar();
        if ($db) {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }
    return $db;
}

/* ===================================================================
   1. SECUENCIAS ATÓMICAS
   =================================================================== */

/**
 * ¿Se ejecutó la migración 001? Si aún no, el sistema sigue funcionando con
 * la numeración anterior (compatibilidad), sin CVD ni huellas.
 */
function sgd_migracion_ok()
{
    static $ok = null;
    if ($ok === null) {
        try {
            $st = sgd_db()->query("SELECT COUNT(*) FROM information_schema.tables
                                    WHERE table_schema = DATABASE() AND table_name = 'sgd_secuencia'");
            $ok = ((int)$st->fetchColumn()) > 0;
        } catch (Exception $e) {
            $ok = false;
        }
        if (!$ok) {
            error_log('SISGEDO: falta ejecutar migraciones/001_mgd_numeracion_integridad.sql (modo compatibilidad).');
        }
    }
    return $ok;
}

/**
 * Devuelve el siguiente valor de la secuencia $clave de forma atómica
 * (sin duplicados aunque dos usuarios registren en el mismo instante).
 * Si la secuencia aún no existe, arranca desde $semilla (último valor ya usado).
 */
function sgd_secuencia_siguiente($clave, $semilla = 0)
{
    $db = sgd_db();
    // a) Caso normal: la secuencia existe -> incrementa en una sola sentencia.
    $up = $db->prepare("UPDATE sgd_secuencia SET ultimo = LAST_INSERT_ID(ultimo + 1) WHERE clave = ?");
    $up->execute(array($clave));
    if ($up->rowCount() > 0) {
        return (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
    }
    // b) Primera vez (p. ej. inicio de año): crea la fila desde la semilla.
    //    Si otro usuario la creó un instante antes, ON DUPLICATE incrementa.
    $ins = $db->prepare("INSERT INTO sgd_secuencia (clave, ultimo) VALUES (?, LAST_INSERT_ID(?))
                         ON DUPLICATE KEY UPDATE ultimo = LAST_INSERT_ID(ultimo + 1)");
    $ins->execute(array($clave, (int)$semilla + 1));
    return (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
}

/** Valor actual de una secuencia (sin incrementarla). null si no existe. */
function sgd_secuencia_actual($clave)
{
    $st = sgd_db()->prepare("SELECT ultimo FROM sgd_secuencia WHERE clave = ?");
    $st->execute(array($clave));
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}

/** Garantiza que la secuencia sea al menos $valor (cuando se registra un número manual mayor). */
function sgd_secuencia_sincronizar($clave, $valor)
{
    $valor = (int)$valor;
    if ($valor <= 0) return;
    $st = sgd_db()->prepare("INSERT INTO sgd_secuencia (clave, ultimo) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, ?)");
    $st->execute(array($clave, $valor, $valor));
}

/* ===================================================================
   2. NÚMERO DE EXPEDIENTE (mesa de partes presencial y virtual)
      Formato histórico del sistema: <año><correlativo de 4+ dígitos>
      Ej.: 20260001 ... 20269999, 202610000 ...
   =================================================================== */

function sgd_nuevo_expediente()
{
    $anio = (int)date('Y');
    if (!sgd_migracion_ok()) {
        // Compatibilidad (sin migración): máximo NUMÉRICO + 1
        $st = sgd_db()->prepare("SELECT MAX(CAST(SUBSTRING(nro_exp, 5) AS UNSIGNED)) FROM registro_documentos
                                  WHERE nro_exp LIKE ? AND fecha_registro >= ?");
        $st->execute(array($anio . '%', $anio . '-01-01'));
        return $anio . str_pad((string)((int)$st->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
    }
    $clave = 'EXP-' . $anio;
    $semilla = 0;
    if (sgd_secuencia_actual($clave) === null) {
        // Semilla = mayor correlativo ya usado este año (comparación NUMÉRICA,
        // no de texto: evita el error de MAX() sobre varchar al pasar de 9999).
        $st = sgd_db()->prepare("SELECT MAX(CAST(SUBSTRING(nro_exp, 5) AS UNSIGNED))
                                   FROM registro_documentos
                                  WHERE nro_exp LIKE ? AND fecha_registro >= ?");
        $st->execute(array($anio . '%', $anio . '-01-01'));
        $semilla = (int)$st->fetchColumn();
    }
    $n = sgd_secuencia_siguiente($clave, $semilla);
    return $anio . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/* ===================================================================
   3. NÚMERO DE REGISTRO INTERNO (recepción/derivación por oficina)
      Formato histórico: <año><correlativo>, por oficina.
   =================================================================== */

function sgd_nuevo_registro_interno($idoficina)
{
    $idoficina = (int)$idoficina;
    $anio = (int)date('Y');
    $clave = 'MOV-' . $idoficina . '-' . $anio;
    $semilla = 0;
    $sinMigracion = !sgd_migracion_ok();
    if ($sinMigracion || sgd_secuencia_actual($clave) === null) {
        $st = sgd_db()->prepare("SELECT MAX(CAST(SUBSTRING(m.num_exp, 5) AS UNSIGNED))
                                   FROM movimientos m
                                   INNER JOIN responsables r ON m.idresponsable = r.idresponsables
                                  WHERE r.idoficina = ? AND m.num_exp LIKE ? AND m.fecha_hora >= ?");
        $st->execute(array($idoficina, $anio . '%', $anio . '-01-01'));
        $semilla = (int)$st->fetchColumn();
    }
    if ($sinMigracion) {
        return $anio . str_pad((string)($semilla + 1), 4, '0', STR_PAD_LEFT);
    }
    $n = sgd_secuencia_siguiente($clave, $semilla);
    return $anio . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/* ===================================================================
   4. NUMERACIÓN DE DOCUMENTOS EMITIDOS
      Correlativo por oficina emisora + tipo de documento + año.
   =================================================================== */

/** Siglas (nomenclatura) de una oficina. '' si no se configuraron. */
function sgd_siglas_oficina($idoficina)
{
    if (!sgd_migracion_ok()) return '';
    $st = sgd_db()->prepare("SELECT COALESCE(TRIM(siglas), '') FROM oficinas WHERE idoficinas = ?");
    $st->execute(array((int)$idoficina));
    $v = $st->fetchColumn();
    return $v === false ? '' : (string)$v;
}

/**
 * Extrae el correlativo de un número escrito a mano, solo si corresponde
 * al año indicado. Ej.: "INFORME N° 027-2026-MPH/GM" -> 27 ; "2542-2025-..." (otro año) -> 0
 */
function sgd_extraer_correlativo($numero, $anio)
{
    if (preg_match('/(\d{1,6})\s*-\s*(\d{4})/u', (string)$numero, $m)) {
        return ((int)$m[2] === (int)$anio) ? (int)$m[1] : 0;
    }
    return 0;
}

/** Mayor correlativo ya emitido este año por la oficina para ese tipo de documento. */
function sgd_semilla_emitidos($idoficina, $iddocumento, $anio)
{
    $st = sgd_db()->prepare("SELECT numero FROM docs_emitidos
                              WHERE idoficina_registro = ? AND iddocumento = ? AND fecha >= ?");
    $st->execute(array((int)$idoficina, (int)$iddocumento, $anio . '-01-01'));
    $max = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $num) {
        $n = sgd_extraer_correlativo($num, $anio);
        if ($n > $max) $max = $n;
    }
    return $max;
}

function sgd_clave_emitidos($idoficina, $iddocumento, $anio)
{
    return 'EMI-' . (int)$idoficina . '-' . (int)$iddocumento . '-' . (int)$anio;
}

function sgd_formatear_numero($n, $anio, $siglas)
{
    $num = str_pad((string)(int)$n, SGD_DIGITOS_NUMERO, '0', STR_PAD_LEFT);
    $txt = str_replace(array('{NUM}', '{ANIO}', '{SIGLAS}'), array($num, (string)$anio, $siglas), SGD_FORMATO_NUMERO);
    return rtrim($txt, '-/ ');
}

/**
 * Vista previa del próximo número (NO lo reserva).
 * @return array('ok'=>bool, 'numero'=>string, 'correlativo'=>int, 'siglas'=>string, 'mensaje'=>string)
 */
function sgd_previsualizar_numero_emitido($idoficina, $iddocumento)
{
    $anio = (int)date('Y');
    $siglas = sgd_siglas_oficina($idoficina);
    if ($siglas === '') {
        return array('ok' => false, 'numero' => '', 'correlativo' => 0, 'siglas' => '',
            'mensaje' => sgd_migracion_ok()
                ? 'Su oficina no tiene siglas configuradas (Mantenimiento > Oficinas). Ingrese el número manualmente.'
                : 'La numeración automática aún no está habilitada (falta la migración 001). Ingrese el número manualmente.');
    }
    $clave = sgd_clave_emitidos($idoficina, $iddocumento, $anio);
    $actual = sgd_secuencia_actual($clave);
    if ($actual === null) {
        $actual = sgd_semilla_emitidos($idoficina, $iddocumento, $anio);
    }
    $n = $actual + 1;
    return array('ok' => true, 'numero' => sgd_formatear_numero($n, $anio, $siglas), 'correlativo' => $n,
        'siglas' => $siglas, 'mensaje' => '');
}

/**
 * Reserva y devuelve el siguiente número del documento emitido.
 * @return string|null  null si la oficina no tiene siglas configuradas.
 */
function sgd_reservar_numero_emitido($idoficina, $iddocumento)
{
    $anio = (int)date('Y');
    $siglas = sgd_siglas_oficina($idoficina);
    if ($siglas === '') return null;
    $clave = sgd_clave_emitidos($idoficina, $iddocumento, $anio);
    $semilla = 0;
    if (sgd_secuencia_actual($clave) === null) {
        $semilla = sgd_semilla_emitidos($idoficina, $iddocumento, $anio);
    }
    $n = sgd_secuencia_siguiente($clave, $semilla);
    return sgd_formatear_numero($n, $anio, $siglas);
}

/** Si se registró un número manual, adelanta el contador para no repetirlo después. */
function sgd_registrar_numero_manual($idoficina, $iddocumento, $numero)
{
    if (!sgd_migracion_ok()) return;
    $anio = (int)date('Y');
    $n = sgd_extraer_correlativo($numero, $anio);
    if ($n > 0) {
        sgd_secuencia_sincronizar(sgd_clave_emitidos($idoficina, $iddocumento, $anio), $n);
    }
}

/** ¿Ya existe ese número para la oficina y tipo de documento este año? */
function sgd_numero_emitido_existe($idoficina, $iddocumento, $numero)
{
    $st = sgd_db()->prepare("SELECT COUNT(*) FROM docs_emitidos
                              WHERE idoficina_registro = ? AND iddocumento = ? AND fecha >= ? AND TRIM(numero) = TRIM(?)");
    $st->execute(array((int)$idoficina, (int)$iddocumento, date('Y') . '-01-01', (string)$numero));
    return (int)$st->fetchColumn() > 0;
}

/* ===================================================================
   5. INTEGRIDAD Y METADATOS DE ARCHIVOS
   =================================================================== */

/** MIME real del archivo (por contenido, no por extensión). */
function sgd_mime_real($ruta)
{
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $m = finfo_file($f, $ruta);
            // finfo_close() está obsoleta desde PHP 8.5 (el objeto se libera solo)
            // y su aviso se colaba en la respuesta de la Mesa de Partes Virtual.
            if (PHP_VERSION_ID < 80500) {
                finfo_close($f);
            }
            if ($m) return $m;
        }
    }
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($ruta);
        if ($m) return $m;
    }
    return null;
}

/**
 * Registra metadatos y huella SHA-256 de un archivo ya guardado en disco.
 * Nunca interrumpe el trámite: ante un error solo se registra en el log.
 *
 * @param string $origen  'REGISTRO' (docs adjs reg_doc) | 'EMITIDO' (docs adjs docs_emitidos)
 * @param string $nombre_almacenado  nombre en disco (= columna adjunto)
 * @param string $ruta    ruta completa del archivo en disco
 * @param string $nombre_original nombre con el que lo subió el usuario
 * @param string $canal   'PRESENCIAL' | 'VIRTUAL' | 'INTERNO'
 * @return string|null hash SHA-256
 */
function sgd_registrar_archivo($origen, $nombre_almacenado, $ruta, $nombre_original = null, $canal = 'INTERNO')
{
    try {
        if (!is_file($ruta) || !sgd_migracion_ok()) return null;
        $hash = hash_file('sha256', $ruta);
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['usuario'])) {
            $idpersonal = (int)$_SESSION['usuario'];
        } else {
            $idpersonal = null;
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : null;
        $ext = strtolower(pathinfo($nombre_almacenado, PATHINFO_EXTENSION));
        $orig = $nombre_original !== null ? substr(basename((string)$nombre_original), 0, 255) : null;
        $st = sgd_db()->prepare("INSERT INTO sgd_archivo
                (origen, nombre_almacenado, nombre_original, extension, mime, tamano_bytes, hash_sha256, canal, idpersonal, ip)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE hash_sha256 = VALUES(hash_sha256), tamano_bytes = VALUES(tamano_bytes), mime = VALUES(mime)");
        $st->execute(array($origen, $nombre_almacenado, $orig, $ext, sgd_mime_real($ruta), filesize($ruta), $hash, $canal, $idpersonal, $ip));
        return $hash;
    } catch (Exception $e) {
        error_log('sgd_registrar_archivo: ' . $e->getMessage());
        return null;
    }
}

/** Metadatos registrados de un adjunto (o null). */
function sgd_obtener_archivo($origen, $nombre_almacenado)
{
    if ($nombre_almacenado === null || $nombre_almacenado === '' || !sgd_migracion_ok()) return null;
    $st = sgd_db()->prepare("SELECT * FROM sgd_archivo WHERE origen = ? AND nombre_almacenado = ?");
    $st->execute(array($origen, $nombre_almacenado));
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? $r : null;
}

/** Comprueba que el archivo en disco no haya sido alterado desde su registro. */
function sgd_verificar_integridad($origen, $nombre_almacenado)
{
    $meta = sgd_obtener_archivo($origen, $nombre_almacenado);
    if (!$meta) return null; // sin huella registrada (documento anterior a la migración)
    $carpeta = $origen === 'REGISTRO' ? 'docs adjs reg_doc' : 'docs adjs docs_emitidos';
    $ruta = __DIR__ . '/../' . $carpeta . '/' . $nombre_almacenado;
    if (!is_file($ruta)) return false;
    return hash_equals($meta['hash_sha256'], hash_file('sha256', $ruta));
}

/* ===================================================================
   6. CÓDIGO DE VERIFICACIÓN DIGITAL (CVD)
      8 caracteres sin letras ambiguas (sin 0/O, 1/I/L). Se muestra como XXXX-XXXX.
   =================================================================== */

function sgd_generar_cvd()
{
    $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max = strlen($alfabeto) - 1;
    $c = '';
    for ($i = 0; $i < 8; $i++) {
        $c .= $alfabeto[random_int(0, $max)];
    }
    return $c;
}

/** Normaliza lo que escribe el ciudadano: quita guiones/espacios y pasa a mayúsculas. */
function sgd_normalizar_cvd($cvd)
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$cvd));
}

function sgd_formato_cvd($cvd)
{
    return strlen($cvd) === 8 ? substr($cvd, 0, 4) . '-' . substr($cvd, 4) : $cvd;
}

/**
 * Devuelve el CVD de un expediente/documento; lo crea si aún no tiene.
 * @param string $tipo 'EXPEDIENTE' (referencia = nro_exp) | 'EMITIDO' (referencia = iddocs_emitidos)
 */
function sgd_obtener_cvd($tipo, $referencia)
{
    try {
        if (!sgd_migracion_ok()) return null;
        $db = sgd_db();
        $st = $db->prepare("SELECT cvd FROM sgd_verificacion WHERE tipo = ? AND referencia = ?");
        $st->execute(array($tipo, (string)$referencia));
        $c = $st->fetchColumn();
        if ($c) return $c;
        for ($intento = 0; $intento < 5; $intento++) {
            $nuevo = sgd_generar_cvd();
            try {
                $ins = $db->prepare("INSERT INTO sgd_verificacion (cvd, tipo, referencia) VALUES (?,?,?)");
                $ins->execute(array($nuevo, $tipo, (string)$referencia));
                return $nuevo;
            } catch (PDOException $e) {
                // Colisión de código o la referencia se registró en paralelo: reintenta / relee.
                $st->execute(array($tipo, (string)$referencia));
                $c = $st->fetchColumn();
                if ($c) return $c;
            }
        }
    } catch (Exception $e) {
        error_log('sgd_obtener_cvd: ' . $e->getMessage());
    }
    return null;
}

/** URL pública de verificación para un CVD (se usa en el QR). */
function sgd_url_verificacion($cvd)
{
    return sisgedo_base_url('verificar.php') . '?c=' . rawurlencode(sgd_formato_cvd($cvd));
}

/* ===================================================================
   7. REPRESENTACIÓN IMPRESA: bloque QR + CVD en PDFs generados con TCPDF
   =================================================================== */

/**
 * Dibuja el código QR de verificación a la derecha de un cuadro HTML
 * que ocupa el 80% del ancho (ver cargos). $y0 = Y donde empezó el cuadro.
 */
function sgd_pdf_qr($pdf, $y0, $cvd)
{
    if (!$cvd) return;
    $m = $pdf->getMargins();
    $ancho = $pdf->getPageWidth() - $m['left'] - $m['right'];
    $tam = 25;
    $x = $m['left'] + $ancho * 0.8 + ($ancho * 0.2 - $tam) / 2;
    $estilo = array('border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false);
    $yAntes = $pdf->GetY();
    $pdf->write2DBarcode(sgd_url_verificacion($cvd), 'QRCODE,M', $x, $y0 + 1, $tam, $tam, $estilo, 'N');
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetXY($m['left'] + $ancho * 0.8, $y0 + $tam + 1.5);
    $pdf->Cell($ancho * 0.2, 3, sgd_formato_cvd($cvd), 0, 2, 'C');
    $pdf->SetFont('helvetica', '', 6);
    $pdf->Cell($ancho * 0.2, 3, 'Escanee para verificar', 0, 0, 'C');
    $pdf->SetFont('times', '', 9);
    $pdf->SetXY($m['left'], max($yAntes, $y0 + $tam + 8));
}

/** Fila HTML (para el pie del cuadro) con el CVD y la URL de verificación. */
function sgd_html_fila_verificacion($cvd, $colspan = 4)
{
    if (!$cvd) return '';
    $url = sgd_url_verificacion($cvd);
    $base = sisgedo_base_url('verificar.php');
    return '<tr><td colspan="' . (int)$colspan . '" style="font-size:7.5px;">'
        . 'Código de verificación: <b>' . htmlspecialchars(sgd_formato_cvd($cvd), ENT_QUOTES, 'UTF-8') . '</b> — '
        . 'Verifique la autenticidad de este cargo en <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '</a></td></tr>';
}
