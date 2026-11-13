<?php
require_once "../conexion/conexion.php";
$usuario    = isset($_POST['usuario'])  ? trim($_POST['usuario']) : '';
$contrasena = isset($_POST['password']) ? $_POST['password']      : '';
$sisgedo_https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

// Cookie de sesión endurecida: no accesible por JS (HttpOnly) y no se envía
// entre sitios (SameSite=Lax) -> mitiga robo de sesión por XSS y CSRF.
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $sisgedo_https,
        'samesite' => 'Lax',
    ]);
} else {
    session_set_cookie_params(0, '/; samesite=Lax', '', $sisgedo_https, true);
}
session_start();

/* =====================================================================
 *  Límite de intentos por IP (anti fuerza bruta)
 * ===================================================================== */
$ip_cliente      = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'desconocida';
// La llave combina IP + usuario: así el bloqueo protege cada cuenta sin afectar
// a otros usuarios que compartan la misma IP (red de oficina / NAT).
$clave_intento   = $ip_cliente . '|' . strtolower($usuario);
$archivo_intentos = __DIR__ . '/../logs/login_intentos.json';
$MAX_INTENTOS    = 6;    // intentos permitidos
$VENTANA_SEG     = 900;  // en 15 minutos

function login_leer_intentos($archivo)
{
    if (!is_file($archivo)) {
        return array();
    }
    $j = json_decode(@file_get_contents($archivo), true);
    return is_array($j) ? $j : array();
}
function login_guardar_intentos($archivo, $data)
{
    @file_put_contents($archivo, json_encode($data), LOCK_EX);
}

$ahora    = time();
$intentos = login_leer_intentos($archivo_intentos);
// Limpiar registros cuya ventana ya expiró
foreach ($intentos as $k => $v) {
    if (!isset($v['ts']) || ($ahora - $v['ts']) > $VENTANA_SEG) {
        unset($intentos[$k]);
    }
}
$reg = isset($intentos[$clave_intento]) ? $intentos[$clave_intento] : array('n' => 0, 'ts' => $ahora);
if (($ahora - $reg['ts']) > $VENTANA_SEG) {
    $reg = array('n' => 0, 'ts' => $ahora);
}

// ¿IP bloqueada por demasiados intentos?
if ($reg['n'] >= $MAX_INTENTOS) {
    $intentos[$clave_intento] = $reg;
    login_guardar_intentos($archivo_intentos, $intentos);
    header("Location: ../login.php?e=2");
    exit;
}

/**
 * Verifica la clave contra el valor guardado.
 *  - Si esta guardada como hash bcrypt: usa password_verify.
 *  - Si esta en texto plano (dato antiguo): compara y, si coincide,
 *    la convierte a hash automaticamente (migracion al vuelo).
 */
function sisgedo_verificar_clave($plano, $guardada, $idpersonal, $pdo)
{
    if ($guardada === null) {
        return false;
    }

    if (preg_match('/^\$2y\$/', $guardada)) {
        return password_verify($plano, $guardada);
    }

    if (hash_equals((string)$guardada, (string)$plano) && $plano !== '') {
        $nuevo = password_hash($plano, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE personal SET clave = ? WHERE idpersonal = ?");
        $up->execute([$nuevo, $idpersonal]);
        return true;
    }
    return false;
}

try {
    $conexion  = new Conexion();
    $conectado = $conexion->conectar();

    $sql = "select personal.idpersonal, personal.usuario, personal.clave,
                   responsables.idoficina, responsables.idresponsables
            from personal
            left join responsables on personal.idpersonal = responsables.idpersonal
            where personal.usuario = ?
            -- Si la persona tiene varios cargos (responsables), usar el ACTIVO.
            -- Antes se tomaba cualquiera y algunos usuarios entraban con un cargo
            -- inactivo de otra oficina. Entre varios activos, el más antiguo (igual que antes).
            order by (responsables.estado = 1) desc, responsables.idresponsables asc
            limit 1";
    $result = $conectado->prepare($sql);
    $result->execute([$usuario]);
    $fila = $result->fetch(PDO::FETCH_ASSOC);

    if ($fila && sisgedo_verificar_clave($contrasena, $fila['clave'], $fila['idpersonal'], $conectado)) {
        // Éxito: limpiar intentos de esta IP
        unset($intentos[$clave_intento]);
        login_guardar_intentos($archivo_intentos, $intentos);

        // Renovar el ID de sesión (anti session fixation)
        session_regenerate_id(true);

        $_SESSION['usuario']       = $fila['idpersonal'];
        $_SESSION['alias']         = $fila['usuario'];
        $_SESSION['idoficina']     = $fila['idoficina'];
        $_SESSION['idresponsable'] = $fila['idresponsables'];
        unset($_SESSION['foto_perfil']); // fuerza a recargar la foto del avatar

        // Último acceso: guarda en sesión el acceso ANTERIOR (para mostrarlo en el
        // perfil) y registra el actual. Tolerante: si la columna ultimo_acceso aún
        // no existe (falta ejecutar ultimo_acceso.sql), el login no se ve afectado.
        try {
            $qa = $conectado->prepare("SELECT ultimo_acceso FROM personal WHERE idpersonal = ?");
            if ($qa !== false && @$qa->execute([$fila['idpersonal']])) {
                $_SESSION['ultimo_acceso_anterior'] = $qa->fetchColumn();
                $qu = $conectado->prepare("UPDATE personal SET ultimo_acceso = NOW() WHERE idpersonal = ?");
                if ($qu !== false) {
                    @$qu->execute([$fila['idpersonal']]);
                }
            }
        } catch (Exception $e) {
            error_log('ultimo_acceso: ' . $e->getMessage());
        }

        header("Location: ../index.php");
    } else {
        // Fallo: contar el intento
        $reg['n']  = $reg['n'] + 1;
        $intentos[$clave_intento] = $reg;
        login_guardar_intentos($archivo_intentos, $intentos);
        header("Location: ../login.php?e=1");
    }
} catch (Exception $exc) {
    error_log('Error en login: ' . $exc->getMessage());
    header("Location: ../login.php?e=3");
}
