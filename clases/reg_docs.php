<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Reg_docs
{
    public function lista_datos($fecha = "")
    {
        $fecha_fin = date("Y-m-d", strtotime($fecha . "+ 15 day"));

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select registro_documentos.idregistro_documentos,documentos.nombre_documento,concat(remitente.apellidos,', ',remitente.nombres) as ape_nom,
                      tipo_tram.nombre,oficinas.nombre,registro_documentos.nro_exp,registro_documentos.numero_docu,registro_documentos.asunto,registro_documentos.parasu,
                      registro_documentos.observacion,registro_documentos.folios,date_format(date_add(registro_documentos.fecha_registro, INTERVAL 0 hour),'%d/%m/%y %h:%i %p') as fecha_registro ,
                      registro_documentos.estado_recepcion,registro_documentos.adjunto
                      from registro_documentos
                      left join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                      inner join remitente on registro_documentos.idremitente=remitente.idremitente
                      inner join tipo_tram on registro_documentos.idtipo_tramite=tipo_tram.idtipo_tram
                      left join oficinas on registro_documentos.idoficina=oficinas.idoficinas
                      where registro_documentos.fecha_registro >= ? and registro_documentos.fecha_registro < date_add(?, interval 1 day)
                      order by registro_documentos.fecha_registro desc";
            //echo $sql;
            $result = $conectado->prepare($sql);
            $result->execute([$fecha, $fecha_fin]);
            $adjunto = '';
            foreach ($result as $filas) {
                echo '<tr>';
                echo '<td class="center">';
                echo '<label class="pos-rel">';
                echo '<input type="checkbox" class="ace" />';
                echo '<span class="lbl"></span>';
                echo '</label>';
                echo '</td>';
                echo '<td>' . h($filas[0]) . '</td>';
                echo '<td>' . h($filas[1]) . '</td>';
                echo '<td >' . h($filas[2]) . '</td>';
                echo '<td >' . h($filas[3]) . '</td>';
                echo '<td >' . h($filas[4]) . '</td>';
                echo '<td >' . h($filas[5]) . '</td>';
                echo '<td >' . h($filas[6]) . '</td>';
                echo '<td >' . h($filas[7]) . '</td>';
                echo '<td >' . h($filas[8]) . '</td>';
                echo '<td >' . h($filas[9]) . '</td>';
                echo '<td >' . h($filas[10]) . '</td>';
                echo '<td >' . h($filas[11]) . '</td>';
                echo '<td >';
                if ($filas[12] === "0") {
                    echo '<span class="label label-sm label-warning">Por Recepcionar</span>';
                } else {
                    echo '<span class="label label-sm label-success">Recepcionado</span>';
                }
                echo '</td >';
                echo '<td>';
                echo '<div class="hidden-sm hidden-xs action-buttons">';
                $tiene_adj = (!is_null($filas[13]) && trim($filas[13]) !== '');
                if ($tiene_adj) {
                    $adjunto = '../docs adjs reg_doc/' . $filas[13];
                    echo '<a class="tooltip-info" data-rel="tooltip" title="Ver Adjunto" href="' . h($adjunto) . '" target="_blank">';
                    echo '<i class="ace-icon fa fa-file bigger-130" style="color:#002b5c;"></i>';
                    echo '</a>';
                } else {
                    $adjunto = '#';
                    echo '<span class="tooltip-info" data-rel="tooltip" title="Sin adjunto" style="cursor:default;">';
                    echo '<i class="ace-icon fa fa-file-o bigger-130" style="color:#adadad;"></i>';
                    echo '</span>';
                }
                echo '<a class="green editar tooltip-succes" data-rel="tooltip" title="Editar" href="#">';
                echo '<i class="ace-icon fa fa-pencil bigger-130"></i>';
                echo '</a>';
                echo '<a class="red eliminar tooltip-error" data-rel="tooltip" title="Eliminar" href="#">';
                echo '<i class="ace-icon fa fa-trash-o bigger-130"></i>';
                echo '</a>';
                echo '<a class="tooltip-info" data-rel="tooltip" title="Cargo" href="../tcpdf/examples/cargo_externo.php?exp=' . h($filas[5]) . '" target="_blank">';
                echo '<i class="ace-icon fa fa-print bigger-130"></i>';
                echo '</a>';
                echo '</div>';
                echo '<div class="hidden-md hidden-lg">';
                echo '<div class="inline pos-rel">';
                echo '<button class="btn btn-minier btn-yellow dropdown-toggle" data-toggle="dropdown" data-position="auto">';
                echo '<i class="ace-icon fa fa-caret-down icon-only bigger-120"></i>';
                echo '</button>';
                echo '<ul class="dropdown-menu dropdown-only-icon dropdown-yellow dropdown-menu-right dropdown-caret dropdown-close">';
                echo '<li>';
                if ($tiene_adj) {
                    echo '<a href="' . h($adjunto) . '" target="_blank" class="tooltip-info" data-rel="tooltip" title="Ver Adjunto">';
                    echo '<span style="color:#002b5c;"><i class="ace-icon fa fa-search-plus bigger-120"></i></span>';
                    echo '</a>';
                } else {
                    echo '<a href="#" onclick="return false;" class="tooltip-info" data-rel="tooltip" title="Sin adjunto" style="opacity:.35;cursor:default;">';
                    echo '<span class="grey"><i class="ace-icon fa fa-search-plus bigger-120"></i></span>';
                    echo '</a>';
                }
                echo '</li>';
                echo '<li>';
                echo '<a href="#" class="tooltip-success editar" data-rel="tooltip" title="Editar">';
                echo '<span class="green">';
                echo '<i class="ace-icon fa fa-pencil-square-o bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';

                /*echo '<li>';
                                            echo '<a href="#" class="tooltip-error eliminar" data-rel="tooltip" title="Eliminar">';
                                                echo '<span class="red">';
                                                    echo '<i class="ace-icon fa fa-trash-o bigger-120"></i>';
                                                echo '</span>';
                                            echo '</a>';
                                        //echo '</li>';*/
                echo '<li>';
                echo '<a href="../tcpdf/examples/cargo_externo.php?exp=' . $filas[5] . '" target="_blank" class="tooltip-error" data-rel="tooltip" title="Cargo">';
                echo '<span class="blue">';
                echo '<i class="ace-icon fa fa-print bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
                echo '</ul>';
                echo '</div>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
        // ================= NUEVO (solo lectura, para la vista rediseñada) =================

    /** Documentos registrados en mesa de partes en un rango.
     *  Columnas: 0 id, 1 tipo doc, 2 remitente, 3 tramite, 4 oficina destino, 5 nro_exp,
     *  6 numero_docu, 7 asunto, 8 parasu, 9 observacion, 10 folios, 11 fecha display,
     *  12 fecha raw, 13 estado_recepcion, 14 adjunto, 15 iddocumento, 16 idremitente,
     *  17 idtipo_tramite, 18 idoficina, 19 procedencia */
    public function datos_registrados($desde, $hasta)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select rd.idregistro_documentos,documentos.nombre_documento,concat(remitente.apellidos,', ',remitente.nombres),
                        tipo_tram.nombre,coalesce(o.nombre,''),rd.nro_exp,coalesce(rd.numero_docu,''),rd.asunto,
                        coalesce(rd.parasu,''),coalesce(rd.observacion,''),coalesce(rd.folios,0),
                        date_format(date_add(rd.fecha_registro,interval 0 hour),'%d/%m/%Y %h:%i %p'),rd.fecha_registro,
                        rd.estado_recepcion,
                        if(rd.adjunto is null,'#',concat('../docs adjs reg_doc/',rd.adjunto)),
                        rd.iddocumento,rd.idremitente,rd.idtipo_tramite,coalesce(rd.idoficina,0),coalesce(rd.lugar_procedencia,''),
                        coalesce(rd.recepcion_web,0),
                        coalesce((select o2.nombre from responsables rr inner join oficinas o2 on rr.idoficina=o2.idoficinas where rr.idpersonal=rd.idpersonal limit 1),'')
                      from registro_documentos rd
                      inner join documentos on rd.iddocumento=documentos.iddocumentos
                      inner join remitente on rd.idremitente=remitente.idremitente
                      inner join tipo_tram on rd.idtipo_tramite=tipo_tram.idtipo_tram
                      left join oficinas o on rd.idoficina=o.idoficinas ";
            require_once __DIR__ . '/mpv_util.php';
            if (sgd_mpv_ok($conectado)) {
                // Con el módulo "Mesa de Partes Virtual" (migraciones 007/008), Registro es
                // solo la mesa de partes PRESENCIAL. Los documentos virtuales se atienden
                // (recepcionar, observar, derivar) y consultan (Historial) en su módulo;
                // aquí podían volver a derivarse, editarse o eliminarse por error.
                $sql .= " where rd.fecha_registro >= ? and rd.fecha_registro < date_add(?, interval 1 day)
                            and coalesce(rd.recepcion_web,0)=0
                          order by rd.fecha_registro desc";
                $params = [$desde, $hasta];
            } else {
                $sql .= " where rd.fecha_registro >= ? and rd.fecha_registro < date_add(?, interval 1 day)
                             or (coalesce(rd.recepcion_web,0)=1 and rd.idoficina is null and rd.estado_recepcion=0
                                 and rd.fecha_registro >= date_sub(now(), interval ? day))
                          order by rd.fecha_registro desc";
                $params = [$desde, $hasta, intval(SISGEDO_DIAS_NUEVO)];
            }
            $st = $conectado->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return array();
        }
    }

    /** Catálogos en array para los combos del formulario. */
    public function datos_documentos_reg()
    {
        try {
            $c = (new Conexion())->conectar();
            $st = $c->prepare("SELECT iddocumentos,nombre_documento FROM documentos WHERE TRIM(COALESCE(nombre_documento,'')) <> '' ORDER BY nombre_documento ASC");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return array();
        }
    }
    public function datos_tramites()
    {
        try {
            $c = (new Conexion())->conectar();
            $st = $c->prepare("SELECT idtipo_tram,nombre FROM tipo_tram ORDER BY nombre ASC");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return array();
        }
    }
    public function datos_oficinas_reg()
    {
        try {
            $c = (new Conexion())->conectar();
            $st = $c->prepare("SELECT idoficinas,nombre FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-') ORDER BY nombre ASC");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return array();
        }
    }

    /** Contadores para las tarjetas KPI del registro. */
    public function contadores_registro()
    {
        $out = array('hoy' => 0, 'semana' => 0, 'pendientes' => 0, 'anio' => 0);
        try {
            $c = (new Conexion())->conectar();
            // Con el módulo Mesa de Partes Virtual, Registro cuenta solo lo presencial
            require_once __DIR__ . '/mpv_util.php';
            $pres = sgd_mpv_ok($c) ? " and recepcion_web=0" : "";  // forma directa: usa idx_rd_web_estado
            $q = $c->prepare("select count(*) from registro_documentos where fecha_registro >= curdate() and fecha_registro < curdate() + interval 1 day" . $pres);
            $q->execute();
            $out['hoy'] = intval($q->fetchColumn());
            $q = $c->prepare("select count(*) from registro_documentos where fecha_registro>=date_sub(now(),interval 7 day)" . $pres);
            $q->execute();
            $out['semana'] = intval($q->fetchColumn());
            $q = $c->prepare("select count(*) from registro_documentos where estado_recepcion=0" . $pres);
            $q->execute();
            $out['pendientes'] = intval($q->fetchColumn());
            $q = $c->prepare("select count(*) from registro_documentos where fecha_registro >= makedate(year(now()),1) and fecha_registro < makedate(year(now())+1,1)" . $pres);
            $q->execute();
            $out['anio'] = intval($q->fetchColumn());
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
        return $out;
    }

    /** ¿El registro puede modificarse/eliminarse? Solo si nadie lo recepcionó ni tiene movimientos. */
    public function es_editable_registro($codigo)
    {
        try {
            $c = (new Conexion())->conectar();
            $q = $c->prepare("select estado_recepcion from registro_documentos where idregistro_documentos=?");
            $q->execute([$codigo]);
            $er = $q->fetchColumn();
            if ($er === false || intval($er) === 1) {
                return false;
            }
            $q = $c->prepare("select count(*) from movimientos where idregdocs=?");
            $q->execute([$codigo]);
            return intval($q->fetchColumn()) === 0;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function insertar_datos($iddocumento, $idremitente, $idtipo_tramite, $idoficina, $nro_exp, $numero_docu, $asunto, $parasu, $observacion, $folios, $adjunto, $idpersonal, $procedencia)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO registro_documentos
(iddocumento,
idremitente,
idtipo_tramite,
idoficina,
nro_exp,
numero_docu,
asunto,
parasu,
observacion,
folios,
adjunto,
idpersonal,
lugar_procedencia)
VALUES
(
?,
?,
?,
?,
?,
?,
?,
?,
?,
?,
?,
?,
?);";
            $result = $conectado->prepare($sql);
            $result->execute([$iddocumento, $idremitente, $idtipo_tramite, $idoficina, $nro_exp, $numero_docu, $asunto, $parasu, $observacion, $folios, $adjunto, $idpersonal, $procedencia]);
            echo $iddocumento, $idremitente, $idtipo_tramite, $idoficina, $nro_exp, $numero_docu, $asunto, $parasu, $observacion, $folios, $adjunto, $idpersonal, $procedencia;
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }
    public function modificar_datos($iddocumento, $idremitente, $idtipo_tramite, $idoficina, $numero_docu, $asunto, $parasu, $observacion, $folios, $idpersonal, $procedencia, $codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "UPDATE registro_documentos
                        SET
                        iddocumento = ?,
                        idremitente = ?,
                        idtipo_tramite = ?,
                        idoficina = ?,
                        numero_docu = ?,
                        asunto = ?,
                        parasu = ?,
                        observacion = ?,
                        folios = ?,
                        idpersonal = ?,
                        lugar_procedencia = ?
                        WHERE idregistro_documentos = ?";
            $result = $conectado->prepare($sql);
            $result->execute([$iddocumento, $idremitente, $idtipo_tramite, $idoficina, $numero_docu, $asunto, $parasu, $observacion, $folios, $idpersonal, $procedencia, $codigo]);
            //                echo $iddocumento,$idremitente,$idtipo_tramite,$idoficina,$numero_docu,$asunto,$parasu,$observacion,$folios,$idpersonal,$procedencia,$codigo;
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM registro_documentos WHERE idregistro_documentos=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
            return $result->rowCount() > 0 ? true : 'No se encontró el registro (puede que ya se haya eliminado).';
        } catch (Exception $exc) {
            error_log('elimnar_datos(' . $codigo . '): ' . $exc->getMessage());
            // Antes se cortaba con die() sin respuesta y la vista recargaba
            // como si se hubiera eliminado: ahora se informa el motivo real.
            return 'No se pudo eliminar en la base de datos: ' . $exc->getMessage();
        }
    }
    public function lista_oficinas()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT oficinas.idoficinas,
                        oficinas.nombre
                    FROM oficinas;";
            $result = $conectado->prepare($sql);
            $result->execute();
            foreach ($result as $filas) {
                echo '<option value="' . $filas[0] . '">' . $filas[1] . '</option>';
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_remitentes()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT remitente.idremitente,concat(remitente.apellidos,', ',remitente.nombres) as ape_nom
                        FROM remitente;";
            $result = $conectado->prepare($sql);
            $result->execute();
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
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
                       ORDER BY tipo_tram.nombre ASC;";
            $result = $conectado->prepare($sql);
            $result->execute();
            echo json_encode($result->fetchAll());
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
                        ORDER BY documentos.nombre_documento ASC;";
            $result = $conectado->prepare($sql);
            $result->execute();
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function nro_expediente()
    {
        // Número atómico (sin duplicados en registros simultáneos) — MGD.
        require_once __DIR__ . '/gestion_documental.php';
        return sgd_nuevo_expediente();
    }
    /** Reemplaza el adjunto de un registro (antes el archivo se subía al editar pero no se guardaba). */
    public function actualizar_adjunto($codigo, $adjunto)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $st = $conectado->prepare("UPDATE registro_documentos SET adjunto = ? WHERE idregistro_documentos = ?");
            $st->execute([$adjunto, $codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
}
// //cuerpo de las ejecuciones$
$dml_reg_docs = new Reg_docs();

// Protección CSRF: valida el token solo en acciones de escritura
if (isset($_POST["Insertar"]) || isset($_POST["Modificar"]) || isset($_POST['Emilinar'])) {
    csrf_require();
    exigir_acceso_endpoint();
}

if (isset($_POST["Insertar"])) {
    // (Seguridad) El personal que registra sale de la SESIÓN, no del POST.
    $idpersonal = !empty($_SESSION['usuario']) ? intval($_SESSION['usuario']) : (isset($_POST["idpersonal"]) ? intval($_POST["idpersonal"]) : 0);
    $documento = intval($_POST["Documento"]);
    $remitente = intval($_POST["Remitente"]);
    $tipotram = intval($_POST["TipoTram"]);
    $oficina = isset($_POST["OficinaRecep"]) ? $_POST["OficinaRecep"] : '';
    $numdoc = isset($_POST["NumDoc"]) ? trim($_POST["NumDoc"]) : '';
    $folios = intval($_POST["Folios"]);
    $asunto = isset($_POST["Asunto"]) ? trim($_POST["Asunto"]) : '';
    $parasu = isset($_POST["Parasu"]) ? $_POST["Parasu"] : '';
    $observacion = isset($_POST["observacion"]) ? $_POST["observacion"] : '';
    $Procedencia = isset($_POST["Procedencia"]) ? $_POST["Procedencia"] : '';
    $cadena = isset($_POST['lista_oficinas']) ? trim($_POST['lista_oficinas']) : '';
    // Validación de servidor (no confiar solo en el JavaScript)
    $errores = array();
    if ($documento < 1) {
        $errores[] = 'Seleccione el tipo de documento.';
    }
    if ($remitente < 1) {
        $errores[] = 'Seleccione o registre el remitente.';
    }
    if ($tipotram < 1) {
        $errores[] = 'Seleccione el tipo de trámite.';
    }
    if ($asunto === '') {
        $errores[] = 'Ingrese el asunto.';
    }
    if ($folios < 1) {
        $errores[] = 'Ingrese un número de folios válido.';
    }
    if ($cadena === '') {
        $errores[] = 'Seleccione al menos una oficina destino.';
    }
    if (!empty($errores)) {
        echo 'ERROR_VALIDACION: ' . implode(' ', $errores);
        exit;
    }
    $nombre_fichero = null;
    if (isset($_FILES['fichero'])) {
        if (is_uploaded_file($_FILES['fichero']['tmp_name'])) {
            require_once __DIR__ . '/util_subida.php';
            if (!sisgedo_validar_archivo($_FILES['fichero'], $err_subida)) {
                echo $err_subida;
                return;
            }
            $nombre_fichero = sisgedo_nombre_seguro($_FILES['fichero']);
            $ruta = "../docs adjs reg_doc/";
            $upload = $ruta . $nombre_fichero;
            if (!move_uploaded_file($_FILES['fichero']['tmp_name'], $upload)) { //movemos el archivo a su ubicacion
                echo "ERROR AL SUBIR EL ARCHIVO";
                return;
            }
            // Integridad (MGD): huella SHA-256 y metadatos del archivo recibido
            require_once __DIR__ . '/gestion_documental.php';
            sgd_registrar_archivo('REGISTRO', $nombre_fichero, $upload, $_FILES['fichero']['name'], 'PRESENCIAL');
        }
    }
    $nro_exp = $dml_reg_docs->nro_expediente();
    $oficinas = explode(",", $cadena);
    foreach ($oficinas as $idoficina) {
        $dml_reg_docs->insertar_datos($documento, $remitente, $tipotram, intval($idoficina), $nro_exp, $numdoc, $asunto, $parasu, $observacion, $folios, $nombre_fichero, $idpersonal, $Procedencia);
    }
    // Código de verificación digital del expediente (aparece en el cargo con QR)
    sgd_obtener_cvd('EXPEDIENTE', $nro_exp);
    //                echo $documento," :: ", $remitente, " :: ",$tipotram," :: ", $idoficina," :: ", $nro_exp," :: ", $numdoc," :: ", $asunto," :: ", $parasu," :: ", $observacion," :: ", $folios," :: ", $nombre_fichero," :: ",$idpersonal," :: ",$Procedencia;
}
if (isset($_POST["Modificar"])) {

    $codigo = intval($_POST["Codigo"]);
    // Mejora de integridad: no modificar registros ya recepcionados o con movimientos.
    if (!$dml_reg_docs->es_editable_registro($codigo)) {
        echo 'ERROR_VALIDACION: El documento ya fue recepcionado o tiene movimientos; no puede modificarse.';
        exit;
    }
    // (Seguridad) El personal sale de la SESIÓN, no del POST.
    $idpersonal = !empty($_SESSION['usuario']) ? intval($_SESSION['usuario']) : (isset($_POST["idpersonal"]) ? intval($_POST["idpersonal"]) : 0);
    $documento = intval($_POST["Documento"]);
    $remitente = intval($_POST["Remitente"]);
    $tipotram = intval($_POST["TipoTram"]);
    $oficina = $_POST["OficinaRecep"];
    $numdoc = $_POST["NumDoc"];
    $folios = intval($_POST["Folios"]);
    $asunto = $_POST["Asunto"];
    $parasu = $_POST["Parasu"];
    $observacion = $_POST["observacion"];
    $Procedencia = $_POST["Procedencia"];
    $cadena = $_POST['lista_oficinas'];
    $nombre_fichero = null;
    if (isset($_FILES['fichero'])) {
        if (is_uploaded_file($_FILES['fichero']['tmp_name'])) {
            require_once __DIR__ . '/util_subida.php';
            if (!sisgedo_validar_archivo($_FILES['fichero'], $err_subida)) {
                echo $err_subida;
                return;
            }
            $nombre_fichero = sisgedo_nombre_seguro($_FILES['fichero']);
            $ruta = "../docs adjs reg_doc/";
            $upload = $ruta . $nombre_fichero;
            if (!move_uploaded_file($_FILES['fichero']['tmp_name'], $upload)) { //movemos el archivo a su ubicacion
                echo "ERROR AL SUBIR EL ARCHIVO";
                return;
            }
            // Integridad (MGD): huella SHA-256 y metadatos del archivo recibido
            require_once __DIR__ . '/gestion_documental.php';
            sgd_registrar_archivo('REGISTRO', $nombre_fichero, $upload, $_FILES['fichero']['name'], 'PRESENCIAL');
        }
    }

    $dml_reg_docs->modificar_datos($documento, $remitente, $tipotram, intval($cadena), $numdoc, $asunto, $parasu, $observacion, $folios, $idpersonal, $Procedencia, $codigo);
    if ($nombre_fichero !== null) {
        $dml_reg_docs->actualizar_adjunto($codigo, $nombre_fichero);
    }
}

if (isset($_POST['Emilinar'])) {
    // Mejora de integridad: no eliminar registros ya recepcionados o con movimientos
    // (borrarlos rompería el historial y el número de expediente del ciudadano).
    if (!$dml_reg_docs->es_editable_registro(intval($_POST['Codigo']))) {
        echo 'ERROR_VALIDACION: El documento ya fue recepcionado o tiene movimientos; no puede eliminarse.';
        exit;
    }
    $resElim = $dml_reg_docs->elimnar_datos(intval($_POST['Codigo']));
    echo ($resElim === true) ? 'OK' : 'ERROR_ELIMINAR: ' . $resElim;
    exit;
}
if (isset($_POST['carga_tramite'])) {
    $dml_reg_docs->lista_tipo_tram();
}
if (isset($_POST['carga_documentos'])) {
    $dml_reg_docs->lista_documentos();
}
if (isset($_POST['carga_remitente'])) {
    $dml_reg_docs->lista_remitentes();
}
if (isset($_POST['carga_oficinas'])) {
    $dml_reg_docs->lista_oficinas();
}
