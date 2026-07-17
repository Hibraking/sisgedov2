<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}
class Profile
{
    public function obtiene_datos($idpersonal)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT idpersonal,apellidos,nombres,direccion,email,telefono,imagen,clave,usuario
                    FROM personal
                    where personal.idpersonal=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idpersonal]);
            $datos_obtenidos = $result->fetch(PDO::FETCH_BOTH);
            return $datos_obtenidos;
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
}
//cuerpo de las ejecuciones

// Actualización del PROPIO perfil (contacto y contraseña). Solo afecta al usuario
// en sesión: el id sale de $_SESSION, nunca del POST.
if (isset($_POST['actualizar_perfil'])) {
    csrf_require();
    $id = isset($_SESSION['usuario']) ? intval($_SESSION['usuario']) : 0;
    if ($id < 1) {
        echo 'ERROR_SESION: sesión no válida.';
        exit;
    }

    $direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : '';
    $email     = isset($_POST['email']) ? trim($_POST['email']) : '';
    $telefono  = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    $claveNueva  = isset($_POST['clave_nueva']) ? $_POST['clave_nueva'] : '';
    $claveActual = isset($_POST['clave_actual']) ? $_POST['clave_actual'] : '';

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'ERROR_VALIDACION: El email no tiene un formato válido.';
        exit;
    }

    try {
        $pdo = (new Conexion())->conectar();

        if ($claveNueva !== '') {
            if (strlen($claveNueva) < 6) {
                echo 'ERROR_VALIDACION: La nueva contraseña debe tener al menos 6 caracteres.';
                exit;
            }
            $q = $pdo->prepare("SELECT clave FROM personal WHERE idpersonal=?");
            $q->execute(array($id));
            $guardada = $q->fetchColumn();
            $ok = false;
            if ($guardada !== false && $guardada !== null) {
                if (preg_match('/^\$2y\$/', $guardada)) {
                    $ok = password_verify($claveActual, $guardada);
                } else {
                    $ok = hash_equals((string)$guardada, (string)$claveActual) && $claveActual !== '';
                }
            }
            if (!$ok) {
                echo 'ERROR_VALIDACION: La contraseña actual no es correcta.';
                exit;
            }
            $q = $pdo->prepare("UPDATE personal SET clave=? WHERE idpersonal=?");
            $q->execute(array(password_hash($claveNueva, PASSWORD_DEFAULT), $id));
        }

        // Foto de perfil (opcional): imagen real, máx. 3 MB, nombre aleatorio,
        // se guarda en images/perfiles/ y se elimina la anterior.
        if (isset($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
            $permitidas = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $permitidas, true)) {
                echo 'ERROR_VALIDACION: La foto debe ser JPG, PNG, GIF o WEBP.';
                exit;
            }
            if ($_FILES['foto']['size'] <= 0 || $_FILES['foto']['size'] > 3 * 1024 * 1024) {
                echo 'ERROR_VALIDACION: La foto no debe superar los 3 MB.';
                exit;
            }
            if (@getimagesize($_FILES['foto']['tmp_name']) === false) {
                echo 'ERROR_VALIDACION: El archivo no es una imagen válida.';
                exit;
            }
            $dir = __DIR__ . '/../images/perfiles/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $nombreFoto = 'perfil_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nombreFoto)) {
                echo 'ERROR_ARCHIVO: No se pudo guardar la foto.';
                exit;
            }
            try {
                $q = $pdo->prepare("SELECT imagen FROM personal WHERE idpersonal=?");
                $q->execute(array($id));
                $anterior = $q->fetchColumn();
                if ($anterior && strpos($anterior, 'perfil_') === 0 && is_file($dir . $anterior)) {
                    @unlink($dir . $anterior);
                }
            } catch (Exception $e) { /* sin bloqueo */
            }
            $q = $pdo->prepare("UPDATE personal SET imagen=? WHERE idpersonal=?");
            $q->execute(array($nombreFoto, $id));
            $_SESSION['foto_perfil'] = $nombreFoto; // refresca la caché del avatar de la cabecera
        }

        $q = $pdo->prepare("UPDATE personal SET direccion=?, email=?, telefono=? WHERE idpersonal=?");
        $q->execute(array($direccion, $email, $telefono, $id));
        echo 'OK';
    } catch (Exception $exc) {
        error_log('actualizar_perfil: ' . $exc->getMessage());
        echo 'ERROR_BD: no se pudo actualizar el perfil.';
    }
    exit;
}

// $dml_menu=new Menu();
//                if(isset($_POST['Codigo'])){
//                    $Modulo=$_POST['Modulo'];
//                    $Nombre=$_POST['Nombre'];
//                    $Ruta=$_POST['Ruta'];
//                    $Clase=$_POST['Clase'];
//                    $Dependencia=$_POST['Dependencia'];
//                    $font_icon=$_POST['Clase'];
//                if($_POST['Codigo']=='' & $_POST['Nombre']!=''){
//                    $dml_menu->insertar_datos($Modulo,$Nombre,$Ruta,$Clase,$Dependencia,$font_icon); 
//                    header("location: ../vistas/v_menu.php");
//                }elseif ($_POST['Codigo']!='') {
//                    $Codigo=$_POST['Codigo'];
//                   $dml_menu->modificar_datos($Modulo,$Nombre,$Ruta,$Clase,$Dependencia,$font_icon,$Codigo); 
//                    header("location: ../vistas/v_menu.php");
//                }
//                
//                }
//            
//if(isset($_POST['Emilinar'])){
//    $dml_menu->elimnar_datos($_POST['Codigo']);
//}