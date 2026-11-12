<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Personal
{
    public function lista_datos()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT personal.idpersonal,
                        personal.apellidos,
                        personal.nombres,
                        personal.direccion,
                        personal.email,
                        personal.telefono
                        FROM personal;";
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
                echo '<td class="hidden">' . h($filas[0]) . '</td>';
                echo '<td>' . h($filas[1]) . '</td>';
                echo '<td >' . h($filas[2]) . '</td>';
                echo '<td >' . h($filas[3]) . '</td>';
                echo '<td >' . h($filas[4]) . '</td>';
                echo '<td >' . h($filas[5]) . '</td>';
                echo '<td>';
                echo '<div class="hidden-sm hidden-xs action-buttons">';
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
    public function insertar_datos($apellidos, $nombre, $direccion, $email, $telefono, $usuario, $clave)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            // Guardar la clave siempre hasheada
            if ($clave !== null && $clave !== '') {
                $clave = password_hash($clave, PASSWORD_DEFAULT);
            }
            $sql = "INSERT INTO personal(apellidos,nombres,direccion,email,telefono,usuario,clave)
                        VALUES(?,?,?,?,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$apellidos, $nombre, $direccion, $email, $telefono, $usuario, $clave]);
            return null;
        } catch (Exception $exc) {
            return $exc->getMessage();
        }
    }
    public function modificar_datos($apellidos, $nombre, $direccion, $email, $telefono, $usuario, $clave, $codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            if ($clave === null || $clave === '') {
                // No se cambia la contrasena: se conserva la actual
                $sql = "UPDATE personal
                            SET apellidos = ?, nombres = ?, direccion = ?,
                                email = ?, telefono = ?, usuario = ?
                            WHERE idpersonal = ?;";
                $result = $conectado->prepare($sql);
                $result->execute([$apellidos, $nombre, $direccion, $email, $telefono, $usuario, $codigo]);
            } else {
                // Se cambia la contrasena: se guarda hasheada
                $clave = password_hash($clave, PASSWORD_DEFAULT);
                $sql = "UPDATE personal
                            SET apellidos = ?, nombres = ?, direccion = ?,
                                email = ?, telefono = ?, usuario = ?, clave = ?
                            WHERE idpersonal = ?;";
                $result = $conectado->prepare($sql);
                $result->execute([$apellidos, $nombre, $direccion, $email, $telefono, $usuario, $clave, $codigo]);
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM personal WHERE idpersonal=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo 'ERROR_EN_USO: No se puede eliminar porque está siendo usado en otros registros del sistema.';
            die();
        }
    }
}
//cuerpo de las ejecuciones

// Protección CSRF: valida el token solo cuando se solicita una acción real
if (isset($_POST['Codigo']) || isset($_POST['Insertar']) || isset($_POST['Emilinar'])) {
    csrf_require();
    exigir_acceso_endpoint();
}

$dml_personal = new Personal();
// Al pulsar Eliminar también llega 'Codigo': este bloque (insertar/modificar)
// NO debe ejecutarse, o dejaba el registro con nombre vacío si no se podía borrar.
if (isset($_POST['Codigo']) && !isset($_POST['Emilinar'])) {

    if ($_POST['Codigo'] == '' & $_POST['Nombres'] != '') {

        $dml_personal->insertar_datos($_POST['Apellidos'], $_POST['Nombres'], $_POST['Direccion'], $_POST['Email'], $_POST['Telefono'], $_POST['nusu'], $_POST['Clave']);
        header("location: ../vistas/v_personal.php");
    } elseif ($_POST['Codigo'] != '') {
        $dml_personal->modificar_datos($_POST['Apellidos'], $_POST['Nombres'], $_POST['Direccion'], $_POST['Email'], $_POST['Telefono'], $_POST['nusu'], $_POST['Clave'], $_POST['Codigo']);

        header("location: ../vistas/v_personal.php");
    }
}

if (isset($_POST['Emilinar'])) {
    $dml_personal->elimnar_datos($_POST['Codigo']);
}
if (isset($_POST['Insertar'])) {
    $dml_personal->insertar_datos($_POST['apellidos'], $_POST['nombres'], $_POST['direccion'], $_POST['email'], $_POST['telefono'], $_POST['nusu'], $_POST['clave']);
}
