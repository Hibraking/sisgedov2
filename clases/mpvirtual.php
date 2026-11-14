<?php

class mpvirtual
{
    public function insertar_datos_remitente($d_identidad, $apellidos, $nombres, $direccion, $email, $telefono)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO remitente(idremitente,d_identidad,apellidos,nombres,direccion,email,telefono)
				VALUES(null,?,?,?,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$d_identidad, $apellidos, $nombres, $direccion, $email, $telefono]);
            $stmt = $conectado->prepare("SELECT LAST_INSERT_ID()");
            $stmt->execute();
            $lastId = $stmt->fetchColumn();
            return $lastId;
        } catch (Exception $exc) {
            // Antes devolvía 1 y el documento quedaba a nombre del remitente N° 1
            // (otra persona) sin avisar. Ahora 0 = error: el registro se detiene.
            error_log('MPV insertar_datos_remitente: ' . $exc->getMessage());
            return 0;
        }
    }
    public function insertar_datos_reg_docs($iddocumento, $idremitente, $idtipo_tramite, $numero_docu, $asunto, $folios, $adjunto)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            // Número de expediente atómico (sin duplicados aunque dos ciudadanos
            // envíen al mismo tiempo) — ver clases/gestion_documental.php
            require_once __DIR__ . '/gestion_documental.php';
            $num_exp = sgd_nuevo_expediente();

            $sql = "INSERT INTO registro_documentos(iddocumento,idremitente,idtipo_tramite,numero_docu,asunto,folios,adjunto,nro_exp,recepcion_web)
                                                    VALUES(?,?,?,?,?,?,?,?,1);";
            $result = $conectado->prepare($sql);
            $result->execute([$iddocumento, $idremitente, $idtipo_tramite, $numero_docu, $asunto, $folios, $adjunto, $num_exp]);
            // Código de verificación digital del expediente (MGD)
            sgd_obtener_cvd('EXPEDIENTE', $num_exp);
            return $num_exp;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return "-1";
        }
    }
    public function nro_expediente()
    {
        // Se mantiene por compatibilidad; ahora usa la secuencia atómica.
        require_once __DIR__ . '/gestion_documental.php';
        return sgd_nuevo_expediente();
    }
    public function lista_tipo_tram()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT tipo_tram.idtipo_tram,tipo_tram.nombre
                       FROM tipo_tram
                       WHERE TRIM(COALESCE(tipo_tram.nombre,'')) <> ''
                       order by tipo_tram.nombre asc;";
            $result = $conectado->prepare($sql);
            $result->execute();
            foreach ($result as $filas) {
                echo '<option value="' . (int)$filas[0] . '">' . htmlspecialchars(trim((string)$filas[1]), ENT_QUOTES, 'UTF-8') . '</option>';
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_documentos()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT documentos.iddocumentos,documentos.nombre_documento
                        FROM documentos
                        WHERE TRIM(COALESCE(documentos.nombre_documento,'')) <> ''
                        order by documentos.nombre_documento asc;";
            $result = $conectado->prepare($sql);
            $result->execute();
            foreach ($result as $filas) {
                echo '<option value="' . (int)$filas[0] . '">' . htmlspecialchars(trim((string)$filas[1]), ENT_QUOTES, 'UTF-8') . '</option>';
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function get_remitente($email)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select * from remitente where email=?";
            $result = $conectado->prepare($sql);
            $result->execute([$email]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    /**
     * Envía al ciudadano la constancia de recepción de la Mesa de Partes Virtual.
     * Devuelve true si el servidor de correo la aceptó. El intento (éxito o
     * error) queda registrado en la tabla sgd_correo.
     */
    public function envio_correo($destino, $numexp, $asunto_doc = '')
    {
        require_once __DIR__ . '/correo.php';
        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $url = sisgedo_base_url('consulta_publico.php?num_exp=' . rawurlencode((string)$numexp));

        $cvd = '';
        if (function_exists('sgd_obtener_cvd') && ($c = sgd_obtener_cvd('EXPEDIENTE', $numexp))) {
            $urlv = sgd_url_verificacion($c);
            $cvd = '<tr><td style="padding:6px 0;color:#52606d">Código de verificación</td>'
                 . '<td style="padding:6px 0"><strong>' . $e(sgd_formato_cvd($c)) . '</strong><br>'
                 . '<a href="' . $e($urlv) . '" style="font-size:12px;color:#1f5bbf">Verificar autenticidad</a></td></tr>';
        }

        // Horario de atención: lo enviado fuera de horario cuenta desde el siguiente día hábil
        require_once __DIR__ . '/calendario.php';
        $ahora = date('Y-m-d H:i:s');
        $oficial = sgd_cal_fecha_presentacion($ahora);
        $fueraHorario = strtotime($oficial) !== strtotime($ahora);

        $cuerpo = '<p style="font-size:15px;line-height:1.5">Su documento fue <strong>recibido correctamente</strong> en la Mesa de Partes Virtual de la '
            . $e(SISGEDO_ENTIDAD) . '. Guarde este correo como constancia de presentación. '
            . 'Le enviaremos otro correo cuando mesa de partes lo recepcione.</p>'
            . ($fueraHorario
                ? '<p style="font-size:14px;line-height:1.5;background:#f5f3ff;border-left:4px solid #7c3aed;padding:10px 14px;color:#4c1d95">'
                  . 'Lo envió <strong>fuera del horario de atención</strong> (' . $e(SISGEDO_HORARIO_TEXTO) . '). '
                  . 'Para el cómputo de plazos se considera presentado el <strong>' . $e(sgd_cal_texto($oficial)) . '</strong>.</p>'
                : '')
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0;width:100%">'
            . '<tr><td style="padding:6px 0;color:#52606d;width:45%">Número de expediente</td>'
            . '<td style="padding:6px 0;font-size:18px"><strong>' . $e($numexp) . '</strong></td></tr>'
            . ($asunto_doc !== '' ? '<tr><td style="padding:6px 0;color:#52606d">Asunto</td><td style="padding:6px 0">' . $e($asunto_doc) . '</td></tr>' : '')
            . '<tr><td style="padding:6px 0;color:#52606d">Fecha y hora de envío</td><td style="padding:6px 0">' . $e(date('d/m/Y H:i', strtotime($ahora))) . '</td></tr>'
            . ($fueraHorario ? '<tr><td style="padding:6px 0;color:#52606d">Se considera presentado</td><td style="padding:6px 0"><strong>' . $e(sgd_cal_texto($oficial)) . '</strong></td></tr>' : '')
            . $cvd
            . '</table>'
            . '<p style="text-align:center;margin:24px 0">'
            . '<a href="' . $e($url) . '" style="background:#1f5bbf;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;display:inline-block">Ver el estado de mi trámite</a></p>'
            . '<p style="font-size:12px;color:#7b8794">Si el botón no funciona, copie este enlace en su navegador:<br>' . $e($url) . '</p>';

        return sgd_correo_enviar(
            $destino,
            'Constancia de recepción - Expediente N° ' . $numexp,
            sgd_correo_plantilla('Mesa de Partes Virtual', $cuerpo),
            array('tipo' => 'MPV_RECEPCION', 'referencia' => $numexp)
        );
    }
    /**
     * Aviso a la Mesa de Partes (bandeja de Entrada de SGD_CORREO_AVISO_MPV):
     * datos del expediente recién registrado por la MPV + el documento adjunto.
     * "Responder" en el webmail contesta directamente al ciudadano.
     */
    public function aviso_mesa_partes($numexp)
    {
        require_once __DIR__ . '/correo.php';
        require_once __DIR__ . '/calendario.php';
        $destino = defined('SGD_CORREO_AVISO_MPV') ? trim((string)SGD_CORREO_AVISO_MPV) : '';
        if ($destino === '') {
            return false;
        }
        try {
            $conectado = (new Conexion())->conectar();
            $st = $conectado->prepare(
                "SELECT rd.nro_exp, rd.numero_docu, rd.asunto, rd.folios, rd.adjunto, rd.fecha_registro,
                        d.nombre_documento, t.nombre AS tramite,
                        r.d_identidad, r.apellidos, r.nombres, r.email, r.telefono, r.direccion,
                        (SELECT a.nombre_original FROM sgd_archivo a
                          WHERE a.origen = 'REGISTRO' AND a.nombre_almacenado = rd.adjunto LIMIT 1) AS nombre_original
                   FROM registro_documentos rd
                   LEFT JOIN documentos d ON d.iddocumentos = rd.iddocumento
                   LEFT JOIN tipo_tram t  ON t.idtipo_tram = rd.idtipo_tramite
                   LEFT JOIN remitente r  ON r.idremitente = rd.idremitente
                  WHERE rd.nro_exp = ? ORDER BY rd.idregistro_documentos DESC LIMIT 1");
            $st->execute(array((string)$numexp));
            $x = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return false;
        }
        if (!$x) {
            return false;
        }

        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $fila = function ($etq, $val, $negrita = false) use ($e) {
            if (trim((string)$val) === '') return '';
            return '<tr><td style="padding:6px 8px;color:#52606d;width:38%;border-bottom:1px solid #eef1f5">' . $e($etq) . '</td>'
                 . '<td style="padding:6px 8px;border-bottom:1px solid #eef1f5">' . ($negrita ? '<strong>' . $e($val) . '</strong>' : $e($val)) . '</td></tr>';
        };
        $ciudadano = trim($x['apellidos'] . ' ' . $x['nombres']);

        // Adjunto: el documento presentado (si no supera el límite)
        $adjuntos = array();
        $notaAdjunto = 'El ciudadano no adjuntó archivo.';
        if (!empty($x['adjunto'])) {
            $ruta = __DIR__ . '/../docs adjs reg_doc/' . $x['adjunto'];
            $maxBytes = (defined('SGD_CORREO_AVISO_ADJUNTO_MAX_MB') ? (int)SGD_CORREO_AVISO_ADJUNTO_MAX_MB : 15) * 1024 * 1024;
            if (is_file($ruta) && filesize($ruta) <= $maxBytes) {
                $nombre = trim((string)$x['nombre_original']) !== '' ? $x['nombre_original'] : $x['adjunto'];
                $adjuntos[] = array('ruta' => $ruta, 'nombre' => $nombre);
                $notaAdjunto = 'Se adjunta el documento presentado (' . $nombre . ').';
            } elseif (is_file($ruta)) {
                $notaAdjunto = 'El documento pesa más de ' . (int)($maxBytes / 1048576) . ' MB: ábralo desde el sistema.';
            }
        }

        $url = sisgedo_base_url('consulta_publico.php?num_exp=' . rawurlencode((string)$x['nro_exp']));
        $cuerpo = '<p style="font-size:15px;line-height:1.5">Ingresó un nuevo documento por la <strong>Mesa de Partes Virtual</strong>.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0;width:100%;border-collapse:collapse">'
            . $fila('Expediente N°', $x['nro_exp'], true)
            . $fila('Fecha y hora', $x['fecha_registro'] ? date('d/m/Y H:i', strtotime($x['fecha_registro'])) : date('d/m/Y H:i'))
            . ($x['fecha_registro'] && sgd_cal_fuera_de_horario($x['fecha_registro'])
                ? $fila('Fuera de horario: se considera presentado', sgd_cal_texto(sgd_cal_fecha_presentacion($x['fecha_registro'])), true) : '')
            . $fila('Tipo de documento', trim($x['nombre_documento'] . ' ' . $x['numero_docu']))
            . $fila('Tipo de trámite', $x['tramite'])
            . $fila('Asunto', $x['asunto'], true)
            . $fila('Folios', $x['folios'])
            . $fila('Remitente', $ciudadano)
            . $fila('DNI / RUC', $x['d_identidad'])
            . $fila('Correo', $x['email'])
            . $fila('Teléfono', $x['telefono'])
            . $fila('Dirección', $x['direccion'])
            . '</table>'
            . '<p style="font-size:13px;color:#52606d">' . $e($notaAdjunto) . ' Para responder al ciudadano use "Responder": la respuesta irá a su correo.</p>'
            . '<p style="text-align:center;margin:20px 0"><a href="' . $e($url) . '" style="background:#1f5bbf;color:#ffffff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:bold;display:inline-block">Ver el expediente</a></p>'
            . '<p style="font-size:12px;color:#7b8794">Para recepcionarlo y derivarlo ingrese al SISGEDO &gt; Operaciones &gt; Mesa de Partes Virtual.</p>';

        $asuntoCorreo = 'MPV - Exp. N° ' . $x['nro_exp'] . ' - ' . ($ciudadano !== '' ? $ciudadano . ': ' : '') . $x['asunto'];
        if (function_exists('mb_strimwidth')) {
            $asuntoCorreo = mb_strimwidth($asuntoCorreo, 0, 150, '…', 'UTF-8');
        }
        $responder = filter_var(trim((string)$x['email']), FILTER_VALIDATE_EMAIL) ? trim($x['email']) : '';

        return sgd_correo_enviar(
            $destino,
            $asuntoCorreo,
            sgd_correo_plantilla('Nuevo documento por Mesa de Partes Virtual', $cuerpo),
            array(
                'tipo' => 'MPV_AVISO_INTERNO',
                'referencia' => $x['nro_exp'],
                'responder_a' => $responder,
                'adjuntos' => $adjuntos,
                'copia_enviados' => false,   // va al propio buzón: no duplicar en Enviados
            )
        );
    }
    public function get_correo($idremitente)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select email
                        from remitente
                        where idremitente=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idremitente]);
            $correo = "";
            foreach ($result as $filas) {
                $correo = $filas[0];
            }
            return $correo;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return "";
        }
    }
}

$mpvirtual = new mpvirtual();
if (isset($_POST['gettemit'])) {
    require_once '../conexion/conexion.php';
    $mpvirtual->get_remitente($_POST['email']);
}
if (isset($_POST['Insertar'])) {
    // ---- Validación de servidor (no confiar solo en el JavaScript) ----
    $g = function ($k) {
        return isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
    };
    $errores = array();
    if ((int)$g('iddocumento') <= 0) {
        $errores[] = 'Seleccione el tipo de documento.';
    }
    if ((int)$g('idtipo_tramite') <= 0) {
        $errores[] = 'Seleccione el tipo de trámite.';
    }
    if ($g('numero_docu') === '') {
        $errores[] = 'Ingrese el número de documento.';
    }
    if ($g('asunto') === '') {
        $errores[] = 'Ingrese el asunto.';
    }
    if (!is_numeric($g('folios')) || (int)$g('folios') < 1) {
        $errores[] = 'Ingrese un número de folios válido.';
    }
    if ($g('email') === '' || !filter_var($g('email'), FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingrese un correo electrónico válido.';
    }
    if ($g('d_identidad') === '') {
        $errores[] = 'Ingrese el DNI o RUC.';
    }
    if (!empty($errores)) {
        echo 'ERROR_VALIDACION: ' . implode(' ', $errores);
        return;
    }

    $nombre_fichero = null;
    //cargando archivo adjunto
    if (isset($_FILES['fichero'])) {
        if (is_uploaded_file($_FILES['fichero']['tmp_name'])) {
            require_once __DIR__ . '/util_subida.php';
            if (!sisgedo_validar_archivo($_FILES['fichero'], $err_subida)) {
                echo "ERROR_ARCHIVO: " . $err_subida;
                return;
            }
            $nombre_fichero = sisgedo_nombre_seguro($_FILES['fichero']);
            $ruta = "../docs adjs reg_doc/";
            $upload = $ruta . $nombre_fichero;
            if (!move_uploaded_file($_FILES['fichero']['tmp_name'], $upload)) { //movemos el archivo a su ubicacion
                echo "ERROR_ARCHIVO: No se pudo guardar el archivo.";
                return;
            }
            // Integridad (MGD): huella SHA-256 y metadatos del archivo recibido
            require_once __DIR__ . '/gestion_documental.php';
            sgd_registrar_archivo('REGISTRO', $nombre_fichero, $upload, $_FILES['fichero']['name'], 'VIRTUAL');
        }
    }
    require_once '../conexion/conexion.php';
    //si el remitente no existe ingresa
    if (isset($_POST['idremitente'])) {
        $numexp = $mpvirtual->insertar_datos_reg_docs($_POST['iddocumento'], $_POST['idremitente'], $_POST['idtipo_tramite'], $_POST['numero_docu'], $_POST['asunto'], $_POST['folios'], $nombre_fichero);
    } else {
        $idremit = $mpvirtual->insertar_datos_remitente($_POST['d_identidad'], $_POST['apellidos'], $_POST['nombres'], $_POST['direccion'], $_POST['email'], $_POST['telefono']);
        if ((int)$idremit <= 0) {
            echo 'ERROR_VALIDACION: No se pudieron guardar sus datos. Revise que no haya caracteres especiales e intente nuevamente.';
            return;
        }
        $numexp =$mpvirtual->insertar_datos_reg_docs($_POST['iddocumento'], $idremit, $_POST['idtipo_tramite'], $_POST['numero_docu'], $_POST['asunto'], $_POST['folios'], $nombre_fichero);
    }
    echo $numexp;
    if ($numexp !== "-1") {
        $email = $g('email');
        if ($email === '' && isset($_POST['idremitente'])) {
            $email = $mpvirtual->get_correo($_POST['idremitente']);
        }
        // Respuesta para js/mpvirtual.js: el número va primero, luego el estado del correo
        echo $mpvirtual->envio_correo($email, $numexp, $g('asunto')) ? ' CORREO_OK' : ' CORREO_FALLO';

        // Aviso interno a la Mesa de Partes (con el documento adjunto). Se envía
        // DESPUÉS de entregar la respuesta al ciudadano para no hacerlo esperar.
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        $mpvirtual->aviso_mesa_partes($numexp);
    } else {
        echo "no se registro";
    }
}
