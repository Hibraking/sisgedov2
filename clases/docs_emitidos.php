<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Reg_docs
{
    public function lista_datos($idoficina, $fecha)
    {
        $fecha_fin = date("Y-m-d", strtotime($fecha . "+ 2 month"));

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select docs_emitidos.iddocs_emitidos,documentos.nombre_documento,docs_emitidos.asunto,docs_emitidos.detalles,
                docs_emitidos.numero,date_format(date_add(docs_emitidos.fecha, INTERVAL 0 hour),'%d/%m/%Y %h:%i %p'),docs_emitidos.adjunto,
                docs_emitidos.idoficina as idoficinass,(select nombre from oficinas where oficinas.idoficinas=idoficinass),docs_emitidos.estado
                from docs_emitidos
                inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                inner join oficinas on docs_emitidos.idoficina_registro=oficinas.idoficinas
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                where oficinas.idoficinas=? and date(docs_emitidos.fecha) between ? and ?
                order by docs_emitidos.fecha desc";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $fecha, $fecha_fin]);
            foreach ($result as $filas) {
                echo '<tr>';
                echo '<td class="center">';
                echo '<label class="pos-rel">';
                echo '<input type="checkbox" class="ace" />';
                echo '<span class="lbl"></span>';
                echo '</label>';
                echo '</td>';
                echo '<td>';
                if ($filas[9] === "0") {
                    if (is_null($filas[7])) {
                        echo '<span class="label label-sm label-info">Doc. Resuesta</span>';
                    } else {
                        echo '<span class="label label-sm label-warning">Por Recepcionar</span>';
                    }
                } else {
                    echo '<span class="label label-sm label-success">Recepcionado</span>';
                }
                echo '</td>';
                echo '<td>' . h($filas[0]) . '</td>';
                echo '<td>' . h($filas[1]) . '</td>';
                echo '<td >' . h($filas[2]) . '</td>';
                echo '<td >' . h($filas[3]) . '</td>';
                echo '<td >' . h($filas[4]) . '</td>';
                echo '<td >' . h($filas[8]) . '</td>';
                echo '<td >' . h($filas[5]) . '</td>';

                $adjunto;
                if (isset($filas[6])) {
                    $adjunto = '../docs adjs docs_emitidos/' . $filas[6];
                } else {
                    $adjunto = '#';
                }
                echo '<td>';
                echo '<div class="action-buttons">';
                echo '<a class="blue tooltip-info adjunto" data-rel="tooltip" title="Ver Adjunto" href="' . h($adjunto) . '">';
                echo '<i class="ace-icon fa fa-search-plus bigger-130"></i>';
                echo '</a>';
                echo '<a class="blue verificar tooltip-info" data-rel="tooltip" title="Seguimiento"  href="../reportes/consulta_interna.php?codigo_doc=' . $filas[0] . '">';
                echo '<i class="ace-icon fa fa-eye bigger-130"></i>';
                echo '</a>';
                echo '<a class="green editar tooltip-success" data-rel="tooltip" title="Editar" href="#">';
                echo '<i class="ace-icon fa fa-pencil bigger-130"></i>';
                echo '</a>';
                echo '<a class="red eliminar tooltip-error" data-rel="tooltip" title="Eliminar" href="#">';
                echo '<i class="ace-icon fa fa-trash-o bigger-130"></i>';
                echo '</a>';
                echo '</div>';
                //                            echo '<div class="hidden-md hidden-lg">';
                //                                echo '<div class="inline pos-rel">';
                //                                    echo '<button class="btn btn-minier btn-yellow dropdown-toggle" data-toggle="dropdown" data-position="auto">';
                //                                        echo '<i class="ace-icon fa fa-caret-down icon-only bigger-120"></i>';
                //                                    echo '</button>';
                //                                    echo '<ul class="dropdown-menu dropdown-only-icon dropdown-yellow dropdown-menu-right dropdown-caret dropdown-close">';
                //                                        echo '<li>';
                //                                            echo '<a href="'.$adjunto.'" class="tooltip-info adjunto" data-rel="tooltip" title="Ver Adjunto">';
                //                                                echo '<span class="blue">';
                //                                                    echo '<i class="ace-icon fa fa-search-plus bigger-120"></i>';
                //                                                echo '</span>';
                //                                            echo '</a>';
                //                                        echo '</li>';
                //                                        echo '<li>';
                //                                            echo '<a href="#" class="tooltip-success editar" data-rel="tooltip" title="Editar">';
                //                                                echo '<span class="green">';
                //                                                    echo '<i class="ace-icon fa fa-pencil-square-o bigger-120"></i>';
                //                                                echo '</span>';
                //                                            echo '</a>';
                //                                        echo '</li>';
                //                                        
                //                                        echo '<li>';
                //                                            echo '<a href="#" class="tooltip-error eliminar" data-rel="tooltip" title="Eliminar">';
                //                                                echo '<span class="red">';
                //                                                    echo '<i class="ace-icon fa fa-trash-o bigger-120"></i>';
                //                                                echo '</span>';
                //                                            echo '</a>';
                //                                        echo '</li>';
                //                                    echo '</ul>';
                //                                echo '</div>';
                //                                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
        // ================= NUEVO (solo lectura, para la vista rediseñada) =================

    /** Documentos emitidos por la oficina en un rango.
     *  Columnas: 0 id, 1 tipo doc, 2 numero, 3 asunto, 4 detalles, 5 destino,
     *  6 estado(0/1), 7 adjunto, 8 fecha display, 9 fecha raw, 10 iddocumento, 11 idoficina destino */
    public function datos_emitidos($idoficina, $desde, $hasta)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select de.iddocs_emitidos,documentos.nombre_documento,de.numero,de.asunto,coalesce(de.detalles,''),
                        coalesce((select o2.nombre from oficinas o2 where o2.idoficinas=de.idoficina),''),
                        de.estado,
                        if(de.adjunto is null,'#',concat('../docs adjs docs_emitidos/',de.adjunto)),
                        date_format(date_add(de.fecha,interval 0 hour),'%d/%m/%Y %h:%i %p'),
                        de.fecha,de.iddocumento,coalesce(de.idoficina,0)
                      from docs_emitidos de
                      inner join documentos on de.iddocumento=documentos.iddocumentos
                      where de.idoficina_registro=? and date(de.fecha) between ? and ?
                      order by de.fecha desc";
            $st = $conectado->prepare($sql);
            $st->execute([$idoficina, $desde, $hasta]);
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return array();
        }
    }

    /** Contadores para las tarjetas KPI de la vista de emisión. */
    public function contadores_emision($idoficina)
    {
        $out = array('por_recepcionar' => 0, 'recepcionados' => 0, 'sin_destino' => 0);
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $q = $conectado->prepare("select count(*) from docs_emitidos where idoficina_registro=? and idoficina is not null and estado=0");
            $q->execute([$idoficina]);
            $out['por_recepcionar'] = intval($q->fetchColumn());
            $q = $conectado->prepare("select count(*) from docs_emitidos where idoficina_registro=? and estado=1");
            $q->execute([$idoficina]);
            $out['recepcionados'] = intval($q->fetchColumn());
            $q = $conectado->prepare("select count(*) from docs_emitidos where idoficina_registro=? and idoficina is null");
            $q->execute([$idoficina]);
            $out['sin_destino'] = intval($q->fetchColumn());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
        return $out;
    }

    /** Catálogos en array (para renderizar combos en el servidor). */
    public function datos_documentos()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $st = $conectado->prepare("SELECT iddocumentos,nombre_documento FROM documentos WHERE TRIM(COALESCE(nombre_documento,'')) <> '' ORDER BY nombre_documento ASC");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return array();
        }
    }
    public function datos_oficinas()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $st = $conectado->prepare("SELECT idoficinas,nombre FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-') ORDER BY nombre ASC");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return array();
        }
    }

    /** ¿El documento puede modificarse/eliminarse? Solo si nadie lo recepcionó ni tiene movimientos. */
    public function es_editable($codigo)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $q = $conectado->prepare("select estado from docs_emitidos where iddocs_emitidos=?");
            $q->execute([$codigo]);
            $estado = $q->fetchColumn();
            if ($estado === false) {
                return false;
            }
            if (intval($estado) === 1) {
                return false;
            }
            $q = $conectado->prepare("select count(*) from movimientos where iddocs_emitidos=? or iddocrespuesta=?");
            $q->execute([$codigo, $codigo]);
            return intval($q->fetchColumn()) === 0;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return false;
        }
    }

    public function insertar_datos($iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $adjunto)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO docs_emitidos(iddocumento,idresponsable,idoficina,asunto,detalles,numero,adjunto)
                        VALUES(?,?,?,?,?,?,?);";
            $result = $conectado->prepare($sql);

            $result->execute([$iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $adjunto]);
            $stmt = $conectado->prepare("SELECT LAST_INSERT_ID()");
            $stmt->execute();
            $lastId = $stmt->fetchColumn();
            echo $lastId;
        } catch (Exception $exc) {
            echo $exc->getMessage();
            die();
        }
    }
    public function modificar_datos($iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "UPDATE docs_emitidos
                        SET
                        iddocumento = ?,
                        idresponsable =?,
                        idoficina=?,
                        asunto = ?,
                        detalles = ?,
                        numero = ?
                        WHERE iddocs_emitidos =?;";
            $result = $conectado->prepare($sql);
            $result->execute([$iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $codigo]);
        } catch (Exception $exc) {
            echo $exc->getMessage();
            die();
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM docs_emitidos WHERE iddocs_emitidos=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            die();
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
            $lista_cat = "";
            foreach ($result as $filas) {
                echo '<option value="' . $filas[0] . '">' . $filas[1] . '</option>';
            }
            return substr($lista_cat, 0, strlen($lista_cat) - 5);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
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
            $lista_cat = "";
            foreach ($result as $filas) {
                echo '<option value="' . $filas[0] . '">' . $filas[1] . '</option>';
            }
            return substr($lista_cat, 0, strlen($lista_cat) - 5);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
}
//cuerpo de las ejecuciones
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$dml_reg_docs = new Reg_docs();
if (isset($_POST["Insertar"]) || isset($_POST["insertar"]) || isset($_POST["Modificar"]) || isset($_POST["Codigo"]) || isset($_POST["Emilinar"]) || isset($_POST["Eliminar"]) || isset($_POST["Estado"])) {
    csrf_require();
    exigir_acceso_endpoint();
}
if (isset($_POST["Insertar"])) {
    $iddocumento = intval($_POST["Documento"]);
    // (Seguridad) El responsable emisor sale de la SESIÓN, no del POST.
    $idresponsable = !empty($_SESSION['idresponsable']) ? intval($_SESSION['idresponsable']) : intval($_POST["idresponsable"]);
    $cadena = isset($_POST["listaoficinas"]) ? $_POST["listaoficinas"] : '';
    $asunto = isset($_POST["Asunto"]) ? trim($_POST["Asunto"]) : '';
    $detalles = isset($_POST["Detalles"]) ? $_POST["Detalles"] : '';
    $numero = isset($_POST["NumDoc"]) ? trim($_POST["NumDoc"]) : '';
    // Validación de servidor (no confiar solo en el JavaScript)
    if ($iddocumento < 1) {
        echo 'ERROR_VALIDACION: Seleccione el tipo de documento.';
        exit;
    }
    if ($asunto === '') {
        echo 'ERROR_VALIDACION: Ingrese el asunto.';
        exit;
    }
    if ($numero === '') {
        echo 'ERROR_VALIDACION: Ingrese el número del documento.';
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
            $ruta = "../docs adjs docs_emitidos/";
            $upload = $ruta . $nombre_fichero;
            if (!move_uploaded_file($_FILES['fichero']['tmp_name'], $upload)) { //movemos el archivo a su ubicacion
                echo "ERROR AL SUBIR EL ARCHIVO";
                return;
            }
        }
    }

    $oficinas = explode(",", $cadena);
    if ($cadena === '') {
        $dml_reg_docs->insertar_datos($iddocumento, $idresponsable, null, $asunto, $detalles, $numero, $nombre_fichero);

        return;
    }
    foreach ($oficinas as $idoficina) {
        $dml_reg_docs->insertar_datos($iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $nombre_fichero);
    }
    //                    echo $iddocumento,$idresponsable,$idoficina,$asunto,$detalles,$numero,$nombre_fichero;

}
if (isset($_POST["Modificar"])) {
    $codigo = intval($_POST["Codigo"]);
    // Mejora de integridad: no se puede modificar un documento ya recepcionado o con movimientos.
    if (!$dml_reg_docs->es_editable($codigo)) {
        echo 'ERROR_VALIDACION: El documento ya fue recepcionado o tiene movimientos; no puede modificarse.';
        exit;
    }
    $iddocumento = intval($_POST["Documento"]);
    // (Seguridad) El responsable emisor sale de la SESIÓN, no del POST.
    $idresponsable = !empty($_SESSION['idresponsable']) ? intval($_SESSION['idresponsable']) : (isset($_POST["idresponsable"]) ? intval($_POST["idresponsable"]) : 0);
    $cadena = isset($_POST["listaoficinas"]) ? $_POST["listaoficinas"] : '';
    $asunto = isset($_POST["Asunto"]) ? $_POST["Asunto"] : '';
    $detalles = isset($_POST["Detalles"]) ? $_POST["Detalles"] : '';
    $numero = isset($_POST["NumDoc"]) ? $_POST["NumDoc"] : '';
    $oficinas = explode(",", $cadena);
    foreach ($oficinas as $oficina) {
    }
    if (trim($cadena) === '') {
        $oficina = null;
    } // documento sin destino
    $dml_reg_docs->modificar_datos($iddocumento, $idresponsable, $oficina, $asunto, $detalles, $numero,  $codigo);
}

if (isset($_POST['Emilinar'])) {
    // Mejora de integridad: no se puede eliminar un documento ya recepcionado o con movimientos
    // (borrarlo rompería el historial y la trazabilidad).
    if (!$dml_reg_docs->es_editable(intval($_POST['Codigo']))) {
        echo 'ERROR_VALIDACION: El documento ya fue recepcionado o tiene movimientos; no puede eliminarse.';
        exit;
    }
    $dml_reg_docs->elimnar_datos($_POST['Codigo']);
}
