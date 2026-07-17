<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Accion
{
    public function lista_datos()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT aciones_documento.idaciones_documento,aciones_documento.nombre_accion,aciones_documento.descripcion
                FROM aciones_documento;";
            $result = $conectado->prepare($sql);
            $result->execute();
            $lista_cat = "";
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
                echo '<a href="#" class="tooltip-success" data-rel="tooltip" title="Editar">';
                echo '<span class="green">';
                echo '<i class="ace-icon fa fa-pencil-square-o bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
                echo '<li>';
                echo '<a href="#" class="tooltip-error" data-rel="tooltip" title="Eliminar">';
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
            return substr($lista_cat, 0, strlen($lista_cat) - 5);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function insertar_datos($nombre, $descripcion)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO aciones_documento(nombre_accion,descripcion)
                VALUES(?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$nombre, $descripcion]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function modificar_datos($codigo, $nombre, $descripcion)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "UPDATE aciones_documento
                    SET
                    nombre_accion = ?,
                    descripcion = ?
                    WHERE idaciones_documento =?;";
            $result = $conectado->prepare($sql);
            $result->execute([$nombre, $descripcion, $codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM aciones_documento WHERE idaciones_documento=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
}
//cuerpo de las ejecuciones             
$dml_accion = new Accion();
if (isset($_POST["Insertar"]) || isset($_POST["insertar"]) || isset($_POST["Modificar"]) || isset($_POST["Codigo"]) || isset($_POST["Emilinar"]) || isset($_POST["Eliminar"]) || isset($_POST["Estado"])) {
    csrf_require();
    exigir_acceso_endpoint();
}
if (isset($_POST['Codigo'])) {
    if ($_POST['Codigo'] == '' & $_POST['Nombre'] != '') {
        $dml_accion->insertar_datos($_POST['Nombre'], $_POST['Descripcion']);
        header("location: ../vistas/v_accion.php");
    } elseif ($_POST['Codigo'] != '') {
        $dml_accion->modificar_datos($_POST['Codigo'], $_POST['Nombre'], $_POST['Descripcion']);
        header("location: ../vistas/v_accion.php");
    }
}

if (isset($_POST['Emilinar'])) {
    $dml_accion->elimnar_datos($_POST['Codigo']);
}
