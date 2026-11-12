<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Menu
{
    public function lista_datos()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select idmenu,modulo.nombre,menu.nombre_menu,menu.direccion,menu.clase_llamar,menu.dependencia as dependencias,
                    (select menu.nombre_menu from menu where idmenu=dependencias) as menu_padre,menu.font_icon
                        from menu
                        left join modulo on menu.idmodulo=modulo.idmodulo
                        where modulo.idmodulo=1";
            $result = $conectado->prepare($sql);
            $result->execute();
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
                echo '<td >' . h($filas[6]) . '</td>';
                echo '<td >' . h($filas[7]) . '</td>';
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
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function insertar_datos($idmodulo, $nombre_menu, $direccion, $clase_llamar, $dependencia, $font_icon)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO menu(idmodulo,nombre_menu,direccion,clase_llamar,dependencia,font_icon)
                        VALUES(?,?,?,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$idmodulo, $nombre_menu, $direccion, $clase_llamar, $dependencia, $font_icon]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function modificar_datos($idmodulo, $nombre_menu, $direccion, $clase_llamar, $dependencia, $font_icon, $codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "UPDATE menu
                        SET
                        idmodulo = ?,
                        nombre_menu = ?,
                        direccion = ?,
                        clase_llamar = ?,
                        dependencia = ?,
                        font_icon = ?
                        WHERE idmenu = ?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idmodulo, $nombre_menu, $direccion, $clase_llamar, $dependencia, $font_icon, $codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM menu WHERE idmenu=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo 'ERROR_EN_USO: No se puede eliminar porque está siendo usado en otros registros del sistema.';
        }
    }
    public function lista_menu()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT idmenu,nombre_menu FROM menu;";
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

$dml_menu = new Menu();
if (isset($_POST["Insertar"]) || isset($_POST["insertar"]) || isset($_POST["Modificar"]) || isset($_POST["Codigo"]) || isset($_POST["Emilinar"]) || isset($_POST["Eliminar"]) || isset($_POST["Estado"])) {
    csrf_require();
    exigir_acceso_endpoint();
}
// Al pulsar Eliminar también llega 'Codigo': este bloque (insertar/modificar)
// NO debe ejecutarse, o dejaba el registro con nombre vacío si no se podía borrar.
if (isset($_POST['Codigo']) && !isset($_POST['Emilinar'])) {
    $Modulo = $_POST['Modulo'];
    $Nombre = $_POST['Nombre'];
    $Ruta = $_POST['Ruta'];
    $Clase = $_POST['Clase'];
    $Dependencia = $_POST['Dependencia'];
    $font_icon = $_POST['Clase'];
    if ($_POST['Codigo'] == '' & $_POST['Nombre'] != '') {
        $dml_menu->insertar_datos($Modulo, $Nombre, $Ruta, $Clase, $Dependencia, $font_icon);
        header("location: ../vistas/v_menu.php");
    } elseif ($_POST['Codigo'] != '') {
        $Codigo = $_POST['Codigo'];
        $dml_menu->modificar_datos($Modulo, $Nombre, $Ruta, $Clase, $Dependencia, $font_icon, $Codigo);
        header("location: ../vistas/v_menu.php");
    }
}

if (isset($_POST['Emilinar'])) {
    $dml_menu->elimnar_datos($_POST['Codigo']);
}
