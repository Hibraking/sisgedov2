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
            // Serializa la generación del número de expediente para evitar colisiones
            // cuando dos personas envían al mismo tiempo (bloqueo con nombre, misma conexión).
            $conectado->query("SELECT GET_LOCK('sisgedo_nro_exp', 10)");
            try {
                $sqlNe = "select if(max(nro_exp) is null,concat(year(now()),'0001'),max(nro_exp)+1)
                            from registro_documentos where year(fecha_registro)=year(now())";
                $rNe = $conectado->query($sqlNe);
                $num_exp = $rNe ? $rNe->fetchColumn() : (date('Y') . '0001');

                $sql = "INSERT INTO registro_documentos(iddocumento,idremitente,idtipo_tramite,numero_docu,asunto,folios,adjunto,nro_exp,recepcion_web)
                                                        VALUES(?,?,?,?,?,?,?,?,1);";
                $result = $conectado->prepare($sql);
                $result->execute([$iddocumento, $idremitente, $idtipo_tramite, $numero_docu, $asunto, $folios, $adjunto, $num_exp]);
            } finally {
                $conectado->query("SELECT RELEASE_LOCK('sisgedo_nro_exp')");
            }
            return $num_exp;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return "-1";
        }
    }
    public function nro_expediente()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select if(max(nro_exp) is null,concat(year(now()),'0001'),max(nro_exp)+1)
                        from registro_documentos
                        where year(fecha_registro)=year(now())";
            $result = $conectado->prepare($sql);
            $result->execute();
            $max_exp = "";
            foreach ($result as $filas) {
                $max_exp = $filas[0];
            }
            return $max_exp;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            die();
        }
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
    public function envio_correo($destino, $numexp)
    {

        function limpiarAsunto($asunto)
        {
            $cadena = "Subject";
            $longitud = strlen($cadena) + 2;
            return substr(
                iconv_mime_encode(
                    $cadena,
                    $asunto,
                    [
                        "input-charset" => "UTF-8",
                        "output-charset" => "UTF-8",
                    ]
                ),
                $longitud
            );
        }

        $asunto = limpiarAsunto("Recepción del Trámite Documentario");
        $destinatario = $destino;

        $encabezados = "MIME-Version: 1.0" . "\r\n";

        # ojo, es una concatenación:
        $encabezados .= "Content-type:text/html; charset=UTF-8" . "\r\n";
        $encabezados .= 'From: Municipalidad de Huaytará<mesadepartes@munihuaytara.gob.pe>' . "\r\n";

        $mensaje = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
                        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml">
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                    <meta name="viewport" content="width=device-width, initial-scale=1"/>
                    <title>Este es un mensaje</title>
                    <style type="text/css">
                        h1{
                            color: #8bc34a;
                        }
                        p{
                            font-size: 1rem;
                        }
                        .detail{
                        font-size: 10px;
                            }
                        img{
                            width: 200px;
                            height: 80px;
                        }
                    </style>
                </head>
                <body>
                <h1>MUNICIPALIDAD PROVINCIAL DE HUAYTARA - MESA DE PARTES VIRTUAL</h1>
                <p>Sus documentos, fueron recepcionados correctamente en la mesa de partes virutal de la Municipalidad provincial de Huaytar&aacute; a continuaci&oacute;n Ud. 
                podr&aacute; verificar los detalles.</p>
                <p>
                Número de Expediente: <strong>' . $numexp . '</strong><br>
                Tambi&eacute;n se puede realizar el seguimiento del tr&aacute;mite desde  <a href="' . sisgedo_base_url('consulta_publico.php?num_exp=' . rawurlencode((string)$numexp)) . '">aqu&iacute;</a>,o en el siguiente enlace:
                <a href="' . sisgedo_base_url('consulta_publico.php?num_exp=' . rawurlencode((string)$numexp)) . '">' . sisgedo_base_url('consulta_publico.php?num_exp=' . rawurlencode((string)$numexp)) . '</a>
                </p>
                <p>Wilmer Cabrera - Alcalde</p>
                <p>Gestión Edil 2023 - 2026</p>
                <img src="' . sisgedo_base_url('images/LOGO.png') . '" alt="Municipalidad Provincial de Huaytará">
                <p class="detail">Sistema de trámite Documentario - Municipalidad Provincial de Huaytará</p>
                </body>';
        $mensaje = wordwrap($mensaje, 70, "\r\n");
        $resultado = mail($destinatario, $asunto, $mensaje, $encabezados); #Mandar al final los encabezados
        if ($resultado) {
            echo "Correo enviado";
        } else {
            echo "Correo NO enviado";
        }
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
        if (isset($_POST['email'])) {
            $email = $_POST['email'];
        } else {
            $email = $mpvirtual->get_correo($_POST['idremitente']);
        }
        $mpvirtual->envio_correo($email, $numexp);
    } else {
        echo "no se registro";
    }
}
