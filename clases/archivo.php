<?php
/* =====================================================================
   SISGEDO — Entrega controlada de adjuntos y fotos de perfil
   ---------------------------------------------------------------------
   Antes los archivos se descargaban directamente de las carpetas
   ("docs adjs reg_doc", "docs adjs docs_emitidos", "images/perfiles")
   con solo conocer su dirección, incluso sin iniciar sesión.

   Ahora el .htaccess de la raíz envía esas MISMAS direcciones a este
   archivo (los enlaces existentes no cambian) y aquí se comprueba:
     1) que haya sesión;
     2) que el usuario pueda ver ESE documento: el administrador, la
        oficina destino/emisora, las oficinas por las que pasó el trámite,
        mesa de partes (Registro) y la Mesa de Partes Virtual (sus docs);
     3) la foto de perfil, solo la propia (o el administrador).
   Los tipos que un navegador podría ejecutar (HTML, SVG, EXE...) se
   entregan SIEMPRE como descarga, nunca se abren en la página.
   ===================================================================== */
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';

function archivo_negar($codigo, $mensaje)
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $mensaje;
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$idusuario = isset($_SESSION['usuario']) ? intval($_SESSION['usuario']) : 0;
if ($idusuario <= 0) {
    archivo_negar(403, 'Inicie sesión en el sistema para ver este archivo.');
}

$carpetas = array('reg' => 'docs adjs reg_doc', 'emi' => 'docs adjs docs_emitidos', 'perfil' => 'images/perfiles');
$c = isset($_GET['c']) ? (string)$_GET['c'] : '';
$f = isset($_GET['f']) ? basename(str_replace('\\', '/', (string)$_GET['f'])) : '';
if (!isset($carpetas[$c]) || $f === '' || $f[0] === '.' || preg_match('/\.(php\d?|phtml|phar|htaccess)$/i', $f)) {
    archivo_negar(404, 'Archivo no encontrado.');
}
$ruta = __DIR__ . '/../' . $carpetas[$c] . '/' . $f;
if (!is_file($ruta)) {
    archivo_negar(404, 'Archivo no encontrado.');
}

/** ¿El usuario puede ver este archivo? */
function archivo_permitido($c, $f, $idusuario)
{
    if ($idusuario === 1) {
        return true;                                 // administrador
    }
    $db = (new Conexion())->conectar();
    if ($c === 'perfil') {
        $st = $db->prepare("SELECT COUNT(*) FROM personal WHERE idpersonal = ? AND imagen = ?");
        $st->execute(array($idusuario, $f));
        return (int)$st->fetchColumn() > 0;
    }
    // Oficinas del usuario: la de la sesión y las de sus cargos activos
    $ofis = array(isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0);
    $st = $db->prepare("SELECT idoficina FROM responsables WHERE idpersonal = ? AND estado = 1");
    $st->execute(array($idusuario));
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $o) {
        $ofis[] = intval($o);
    }
    $ofis = array_values(array_unique(array_filter($ofis)));
    if (!$ofis) {
        return false;
    }
    $mo = implode(',', array_fill(0, count($ofis), '?'));

    if ($c === 'reg') {
        // Registros con este adjunto (o cuyo archivo anterior quedó en el historial de la MPV)
        $st = $db->prepare("SELECT idregistro_documentos, COALESCE(recepcion_web,0), idoficina FROM registro_documentos WHERE adjunto = ?");
        $st->execute(array($f));
        $regs = $st->fetchAll(PDO::FETCH_NUM);
        try {
            $st = $db->prepare("SELECT rd.idregistro_documentos, COALESCE(rd.recepcion_web,0), rd.idoficina
                                  FROM sgd_mpv_evento e INNER JOIN registro_documentos rd ON rd.idregistro_documentos = e.idregistro_documentos
                                 WHERE e.archivo = ?");
            $st->execute(array($f));
            $regs = array_merge($regs, $st->fetchAll(PDO::FETCH_NUM));
        } catch (Exception $e) {
            // sin el módulo de Mesa de Partes Virtual
        }
        if (!$regs) {
            return false;                            // archivo que no pertenece a ningún registro
        }
        $ids = array();
        $web = false;
        foreach ($regs as $r) {
            if (in_array(intval($r[2]), $ofis, true)) {
                return true;                         // oficina destino
            }
            $ids[] = intval($r[0]);
            $web = $web || intval($r[1]) === 1;
        }
        // Mesa de partes (Registro) y, si llegó por internet, la Mesa de Partes Virtual
        if (acceso_explicito('vistas/o_reg_documentos.php')) {
            return true;
        }
        if ($web && acceso_explicito('vistas/v_mesa_partes_virtual.php')) {
            return true;
        }
        $mi = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("SELECT COUNT(*) FROM movimientos m LEFT JOIN responsables r ON m.idresponsable = r.idresponsables
                             WHERE m.idregdocs IN ($mi) AND (m.idoficina_derivar IN ($mo) OR r.idoficina IN ($mo))");
        $st->execute(array_merge($ids, $ofis, $ofis));
        return (int)$st->fetchColumn() > 0;          // oficinas por las que pasó el trámite
    }

    // c === 'emi': documentos emitidos con este adjunto (o el original antes de la firma digital)
    $st = $db->prepare("SELECT iddocs_emitidos, idoficina_registro, idoficina FROM docs_emitidos WHERE adjunto = ?");
    $st->execute(array($f));
    $docs = $st->fetchAll(PDO::FETCH_NUM);
    try {
        $st = $db->prepare("SELECT de.iddocs_emitidos, de.idoficina_registro, de.idoficina
                              FROM sgd_firma fi INNER JOIN docs_emitidos de ON de.iddocs_emitidos = fi.iddocs_emitidos
                             WHERE fi.archivo_origen = ? OR fi.archivo_firmado = ?");
        $st->execute(array($f, $f));
        $docs = array_merge($docs, $st->fetchAll(PDO::FETCH_NUM));
    } catch (Exception $e) {
        // sin la tabla de firmas
    }
    if (!$docs) {
        return false;
    }
    $ids = array();
    foreach ($docs as $d) {
        if (in_array(intval($d[1]), $ofis, true) || in_array(intval($d[2]), $ofis, true)) {
            return true;                             // oficina emisora o destino
        }
        $ids[] = intval($d[0]);
    }
    $mi = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT COUNT(*) FROM movimientos m LEFT JOIN responsables r ON m.idresponsable = r.idresponsables
                         WHERE (m.iddocs_emitidos IN ($mi) OR m.iddocrespuesta IN ($mi))
                           AND (m.idoficina_derivar IN ($mo) OR r.idoficina IN ($mo))");
    $st->execute(array_merge($ids, $ids, $ofis, $ofis));
    return (int)$st->fetchColumn() > 0;
}

/** Acceso concedido de verdad al menú (no por ser una página fuera del menú). */
function acceso_explicito($direccion)
{
    try {
        $db = (new Conexion())->conectar();
        $st = $db->prepare("SELECT COUNT(*) FROM accesos_usuario a INNER JOIN menu m ON a.idmenu = m.idmenu
                             WHERE a.idusuario = ? AND a.access = 1 AND m.direccion = ?");
        $st->execute(array(intval($_SESSION['usuario']), $direccion));
        return (int)$st->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log('archivo acceso_explicito: ' . $e->getMessage());
        return false;
    }
}

try {
    $permitido = archivo_permitido($c, $f, $idusuario);
} catch (Exception $e) {
    error_log('archivo.php: ' . $e->getMessage());
    $permitido = false;                              // ante un error, NO se entrega
}
if (!$permitido) {
    archivo_negar(403, 'No tiene permiso para ver este archivo.');
}
session_write_close();                               // no bloquear otras peticiones del usuario

// Solo estos tipos se abren en el navegador; todo lo demás se descarga
$enLinea = array('pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jfif' => 'image/jpeg',
                 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain; charset=utf-8');
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$inline = isset($enLinea[$ext]);
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . ($inline ? $enLinea[$ext] : 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
if ($ext !== 'pdf') {
    // Nunca ejecutar nada del archivo (el visor de PDF de Chrome no funciona con
    // "sandbox", por eso el PDF queda con la política general del .htaccess)
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f));
header('Cache-Control: private, max-age=600');
readfile($ruta);
exit;
