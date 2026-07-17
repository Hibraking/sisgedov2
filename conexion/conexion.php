<?php
//include "./config.php";

/*
 * Deteccion de entorno (local Laragon vs produccion). Se ejecuta una sola vez
 * porque este archivo se incluye con require_once.
 */
if (!defined('SISGEDO_ES_LOCAL')) {
    // Hora oficial del Perú para TODO el sistema:
    // - PHP: date(), strtotime(), etc.
    // - MySQL: la sesión ya se fija a -05:00 al conectar (ver conectar()).
    // Así local y producción registran y muestran la misma hora, sin parches.
    date_default_timezone_set('America/Lima');

    $sisgedo_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n');
    $sisgedo_local = (strpos($sisgedo_host, 'localhost') !== false)
        || (strpos($sisgedo_host, '127.0.0.1') !== false)
        || (strpos($sisgedo_host, 'sisgedo.test') !== false);
    define('SISGEDO_ES_LOCAL', $sisgedo_local);
    $sisgedo_https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    // Manejo de errores segun entorno:
    //  - LOCAL: se muestran en pantalla (util para desarrollar).
    //  - PRODUCCION: se ocultan al usuario y se guardan en un log privado.
    if (SISGEDO_ES_LOCAL) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
    }

    // Endurecimiento de la cookie de sesión (aplica si se llama ANTES de
    // session_start). Funciona en cualquier SAPI, a diferencia de .user.ini.
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $sisgedo_https ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
    }

    if (!function_exists('sisgedo_base_url')) {
        function sisgedo_base_url($path = '') {
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n');
            $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
            $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
            $basePath = preg_replace('~/(?:clases|vistas|reportes|tcpdf(?:/examples)?)$~', '', $basePath);
            if ($basePath === '/' || $basePath === '.' || $basePath === '\\') {
                $basePath = '';
            }
            $base = rtrim($scheme . '://' . $host . $basePath, '/');
            return $path === '' ? $base : ($base . '/' . ltrim($path, '/'));
        }
    }
}

class Conexion
{
    public function conectar()
    {

        if (SISGEDO_ES_LOCAL) {
            // ---- Configuracion LOCAL (Laragon) ----
            $db_host = 'localhost';
            $db_name = 'sisgedov2';
            $db_user = 'root';
            $db_pass = '';            // Laragon usa contrasena vacia por defecto
        } else {
            // ---- Configuracion PRODUCCION ----
            // NOTA: en cPanel el nombre lleva el prefijo de la cuenta.
            // Si creas la BD con otro nombre, ajusta SOLO esta linea.
            $db_host = 'localhost';
            $db_name = 'xyltrylx_sisgedov2';
            $db_user = 'xyltrylx_sisgedov2';
            $db_pass = '@@Munixd26';
        }

        $conexion = null;
        try {
            $conexion = new PDO('mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4', $db_user, $db_pass);
            $conexion->exec("SET NAMES utf8mb4; SET time_zone = '-05:00'");
        } catch (PDOException $e) {
            // Nunca exponer detalles internos al usuario.
            error_log('Error de conexion BD: ' . $e->getMessage());
            if (SISGEDO_ES_LOCAL) {
                echo 'Error de conexion a la base de datos: ' . $e->getMessage();
            } else {
                http_response_code(500);
                echo 'No se pudo conectar con el sistema. Intente mas tarde.';
            }
        }
        return $conexion;
    }
}
