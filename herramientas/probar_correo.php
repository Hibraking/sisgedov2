<?php
/* =====================================================================
   SISGEDO — Prueba del correo saliente
   ---------------------------------------------------------------------
   Envía un correo de prueba con la configuración de
   conexion/correo_config.php y muestra el diálogo completo con el
   servidor SMTP, para diagnosticar problemas (contraseña, puerto, SSL).

   USO
   - Por navegador (solo el administrador, usuario ID 1, con sesión):
       https://<dominio>/sisgedov2/herramientas/probar_correo.php
     muestra un formulario; el envío se hace con POST y el token CSRF
     (antes bastaba abrir un enlace con ?a=... para que se enviara).
   - Por consola:
       php herramientas/probar_correo.php correo@destino.com [dominio]
     (con [dominio] distinto de localhost usa la configuración de PRODUCCIÓN)
   ===================================================================== */

$esCli = (PHP_SAPI === 'cli');
if ($esCli) {
    $destino = isset($argv[1]) ? $argv[1] : '';
    $_SERVER['HTTP_HOST'] = isset($argv[2]) ? $argv[2] : 'localhost';
} else {
    require_once __DIR__ . '/../clases/seguridad.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['usuario']) || intval($_SESSION['usuario']) !== 1) {
        http_response_code(403);
        exit('Acceso restringido al administrador.');
    }
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        // GET: solo el formulario, no se envía nada
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Prueba de correo</title></head>'
            . '<body style="font-family:Arial,sans-serif;max-width:480px;margin:40px auto">'
            . '<h2>SISGEDO — Prueba de correo</h2>'
            . '<form method="post"><input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'
            . '<label>Correo destino<br><input type="email" name="a" required style="width:100%;padding:6px"></label>'
            . '<p><button type="submit">Enviar correo de prueba</button></p></form></body></html>';
        exit;
    }
    csrf_require();
    header('Content-Type: text/plain; charset=utf-8');
    $destino = isset($_POST['a']) ? trim((string)$_POST['a']) : '';
}

require_once __DIR__ . '/../clases/correo.php';

echo "SISGEDO — Prueba de correo\n";
echo "Entorno      : " . (SISGEDO_ES_LOCAL ? 'LOCAL (Mailpit: http://localhost:8025)' : 'PRODUCCIÓN') . "\n";
echo "Servidor     : " . SGD_CORREO_HOST . ':' . SGD_CORREO_PUERTO . ' ' . (SGD_CORREO_SEGURIDAD ?: 'sin cifrado') . "\n";
echo "Remitente    : " . SGD_CORREO_NOMBRE . ' <' . SGD_CORREO_DESDE . ">\n";
echo "Autenticación: " . (SGD_CORREO_AUTH ? 'sí, usuario ' . SGD_CORREO_USUARIO . ', contraseña ' . (SGD_CORREO_CLAVE !== '' ? 'configurada' : 'FALTA (conexion/correo_clave.php)') : 'no') . "\n";
echo "Bitácora     : " . (sgd_correo_bitacora_ok() ? 'tabla sgd_correo OK' : 'FALTA migraciones/002_registro_correos.sql') . "\n\n";

if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
    exit($esCli ? "Indique el correo destino: php herramientas/probar_correo.php correo@destino.com\n"
                : "Indique un correo destino válido.\n");
}

// Mostrar el diálogo SMTP mientras dura la prueba
ob_implicit_flush(true);
$GLOBALS['sgd_correo_debug'] = true;

$html = sgd_correo_plantilla('Correo de prueba', '<p>Si está leyendo este mensaje, el correo saliente de SISGEDO funciona correctamente.</p><p>Fecha: ' . date('d/m/Y H:i:s') . '</p>');
$ok = sgd_correo_enviar($destino, 'Prueba de correo SISGEDO', $html, array('tipo' => 'PRUEBA', 'referencia' => 'PRUEBA'));

echo "\n" . ($ok ? "RESULTADO: ENVIADO a $destino" : "RESULTADO: ERROR — " . sgd_correo_ultimo_error()) . "\n";
