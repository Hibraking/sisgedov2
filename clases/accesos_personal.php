<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Accesos
{
    public function lista_datos($idusuario)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select menu.idmenu,accesos_usuario.idaccesos_usuario,menu.dependencia as dependecias,
                        (select menu.nombre_menu from menu where menu.idmenu=dependecias) as depend, 
                        menu.nombre_menu,accesos_usuario.access
                        from accesos_usuario
                        RIGHT join menu on accesos_usuario.idmenu=menu.idmenu and accesos_usuario.idusuario=?
                        order by dependencia asc";
            $result = $conectado->prepare($sql);
            $result->execute([$idusuario]);
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
                echo '<td >' . h($filas[3]) . '</td>';
                echo '<td >' . h($filas[4]) . '</td>';
                echo '<td >';
                if ($filas[5] === "1") {
                    echo  '<p class="green" href="#">';
                    echo '<i class="ace-icon fa fa-check-circle bigger-130"> --Acceso</i>';
                    echo '</p>';
                } else {
                    echo  '<p class="red" href="#">';
                    echo '<i class="ace-icon fa fa-remove bigger-130">  --Denegado</i>';
                    echo '</p>';
                }

                echo '</td>';
                echo '<td>';
                echo '<div class="hidden-sm hidden-xs action-buttons">';
                //                                echo '<a class="blue" href="#">';
                //                                    echo'<i class="ace-icon fa fa-search-plus bigger-130"></i>';
                //                                echo '</a>';
                echo '<a class="green activar" href="#">';
                echo '<i class="ace-icon fa fa-check-circle bigger-130"></i>';
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
                echo '<a href="#" class="tooltip-success" data-rel="tooltip" title="Activar">';
                echo '<span class="green">';
                echo '<i class="ace-icon fa fa-check-circle bigger-120"></i>';
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
    public function insertar_datos($idmenu, $idusuario)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO accesos_usuario(idmenu,idusuario,access)
                        VALUES(?,?,true);";
            $result = $conectado->prepare($sql);
            $result->execute([$idmenu, $idusuario]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM accesos_usuario WHERE idaccesos_usuario=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_usuarios()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT personal.idpersonal,concat(personal.apellidos,', ',personal.nombres,' - ',personal.usuario) as details
                FROM personal;";
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
    public function datos_usuario($idusuario)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT concat(personal.apellidos,', ',personal.nombres,' - ',personal.usuario) as details
                FROM personal
                where idpersonal=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idusuario]);
            $lista_cat = "";
            foreach ($result as $filas) {
                $_SESSION['nombres_usuario'] = $filas[0];
            }
            return substr($lista_cat, 0, strlen($lista_cat) - 5);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
}
//cuerpo de las ejecuciones  

$dml_menu = new Accesos();
if (isset($_POST['Usuarios'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_setting'] = $_POST['Usuarios'];
    $dml_menu->datos_usuario($_POST['Usuarios']);
    header("location: ../vistas/v_accesos_personal.php");
}
if (isset($_POST['otorgar'])) {
    // Protección CSRF + permiso del módulo (antes esta acción no estaba cubierta)
    csrf_require();
    exigir_acceso_endpoint();
    csrf_require();
    exigir_acceso_endpoint();
    $dml_menu->insertar_datos($_POST['Idmenu'], $_POST['Idusuario']);
}
if (isset($_POST['Emilinar'])) {
    csrf_require();
    exigir_acceso_endpoint();
    $dml_menu->elimnar_datos($_POST['Codigo']);
}
