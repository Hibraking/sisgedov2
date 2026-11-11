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
            error_log($exc->getMessage());
            return 1;
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
                echo '<option value="' . $filas[0] . '">' . $filas[1] . '</option>';
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
                echo '<option value="' . $filas[0] . '">' . $filas[1] . '</option>';
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

        $cuerpo = '<p style="font-size:15px;line-height:1.5">Su documento fue <strong>recibido correctamente</strong> en la Mesa de Partes Virtual de la '
            . $e(SISGEDO_ENTIDAD) . '. Guarde este correo como constancia de presentación.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0;width:100%">'
            . '<tr><td style="padding:6px 0;color:#52606d;width:45%">Número de expediente</td>'
            . '<td style="padding:6px 0;font-size:18px"><strong>' . $e($numexp) . '</strong></td></tr>'
            . ($asunto_doc !== '' ? '<tr><td style="padding:6px 0;color:#52606d">Asunto</td><td style="padding:6px 0">' . $e($asunto_doc) . '</td></tr>' : '')
            . '<tr><td style="padding:6px 0;color:#52606d">Fecha y hora de recepción</td><td style="padding:6px 0">' . $e(date('d/m/Y H:i')) . '</td></tr>'
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
        $numexp = $mpvirtual->insertar_datos_reg_docs($_POST['iddocumento'], $idremit, $_POST['idtipo_tramite'], $_POST['numero_docu'], $_POST['asunto'], $_POST['folios'], $nombre_fichero);
    }
    echo $numexp;
    if ($numexp !== "-1") {
        $email = $g('email');
        if ($email === '' && isset($_POST['idremitente'])) {
            $email = $mpvirtual->get_correo($_POST['idremitente']);
        }
        // Respuesta para js/mpvirtual.js: el número va primero, luego el estado del correo
        echo $mpvirtual->envio_correo($email, $numexp, $g('asunto')) ? ' CORREO_OK' : ' CORREO_FALLO';
    } else {
        echo "no se registro";
    }
}
