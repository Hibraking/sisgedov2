<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Archivador
{
    public function lista_datos($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT archivador.idarchivador,archivador.nombre,archivador.detalles,archivador.anio
                        FROM archivador
                        where idoficina=?
                        order by anio desc;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina]);
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
                echo '<td>';
                echo '<div class="hidden-sm hidden-xs action-buttons">';
                //                                echo '<a class="blue" href="#">';
                //                                    echo'<i class="ace-icon fa fa-search-plus bigger-130"></i>';
                //                                echo '</a>';
                echo '<a class="green editar" href="#">';
                echo '<i class="ace-icon fa fa-pencil bigger-130"></i>';
                echo '</a>';
                echo '<a class="red eliminar" href="#">';
                echo '<i class="ace-icon fa fa-trash-o bigger-130"></i>';
                echo '</a>';
                echo '</div>';
                echo '<div class="hidden-md hidden-lg">';
                echo '<div class="inline pos-rel">';
                echo '<button class="btn btn-minier btn-yellow dropdown-toggle" data-toggle="dropdown" data-position="auto">';
                echo '<i class="ace-icon fa fa-caret-down icon-only bigger-120"></i>';
                echo '</button>';
                echo '<ul class="dropdown-menu dropdown-only-icon dropdown-yellow dropdown-menu-right dropdown-caret dropdown-close">';
                //                                        echo '<li>';
                //                                            echo '<a href="#" class="tooltip-info" data-rel="tooltip" title="View">';
                //                                                echo '<span class="blue">';
                //                                                    echo '<i class="ace-icon fa fa-search-plus bigger-120"></i>';
                //                                                echo '</span>';
                //                                            echo '</a>';
                //                                        echo '</li>';
                echo '<li>';
                echo '<a href="#" class="tooltip-success editar" data-rel="tooltip" title="Editar">';
                echo '<span class="green">';
                echo '<i class="ace-icon fa fa-pencil-square-o bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
                echo '<li>';
                echo '<a href="#" class="tooltip-error eliminar" data-rel="tooltip" title="Eliminar">';
                echo '<span class="red">';
                echo '<i class="ace-icon fa fa-trash-o bigger-120"></i>';
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
    public function insertar_datos($idoficina, $nombre, $detalles, $anio)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO archivador(idoficina,nombre,detalles,anio)
                    VALUES(?,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $nombre, $detalles, $anio]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function modificar_datos($nombre, $detalles, $anio, $codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "UPDATE archivador
                        SET
                        nombre =?,
                        detalles = ?,
                        anio = ?
                        WHERE idarchivador = ?;";
            $result = $conectado->prepare($sql);
            $result->execute([$nombre, $detalles, $anio, $codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM archivador WHERE idarchivador=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo 'ERROR_EN_USO: No se puede eliminar porque está siendo usado en otros registros del sistema.';
        }
    }
}
//cuerpo de las ejecuciones     

$dml_Archivador = new Archivador();
if (isset($_POST["Insertar"]) || isset($_POST["insertar"]) || isset($_POST["Modificar"]) || isset($_POST["Codigo"]) || isset($_POST["Emilinar"]) || isset($_POST["Eliminar"]) || isset($_POST["Estado"])) {
    csrf_require();
    exigir_acceso_endpoint();
}

// Al pulsar Eliminar también llega 'Codigo': este bloque (insertar/modificar)
// NO debe ejecutarse, o dejaba el registro con nombre vacío si no se podía borrar.
if (isset($_POST['Codigo']) && !isset($_POST['Emilinar'])) {
    if ($_POST['Codigo'] == '' & $_POST['Nombre'] != '') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $dml_Archivador->insertar_datos($_SESSION['idoficina'], $_POST['Nombre'], $_POST['Detalles'], $_POST['anio']);
        header("location: ../vistas/v_archivador.php");
    } elseif ($_POST['Codigo'] != '') {
        $dml_Archivador->modificar_datos($_POST['Nombre'], $_POST['Detalles'], $_POST['anio'], $_POST['Codigo']);
        header("location: ../vistas/v_archivador.php");
    }
}

if (isset($_POST['Emilinar'])) {
    $dml_Archivador->elimnar_datos($_POST['Codigo']);
}
