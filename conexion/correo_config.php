<?php
/* =====================================================================
   SISGEDO — Configuración del CORREO SALIENTE (avisos al ciudadano)
   ---------------------------------------------------------------------
   El sistema envía los correos por SMTP AUTENTICADO con la cuenta de la
   institución (SISGEDO_MUNI_EMAIL, definida en conexion/institucion.php),
   usando PHPMailer (librerias/PHPMailer). Así el correo sale realmente
   desde el servidor de correo de la municipalidad y no cae en spam.

   ENTORNOS
   - LOCAL (Laragon): los correos van a Mailpit (no salen a internet).
     Véalos en http://localhost:8025
   - PRODUCCIÓN: SMTP de la cuenta de correo institucional.

   PASOS PARA ACTIVAR EN PRODUCCIÓN (cPanel)
   1. cPanel > Cuentas de correo: confirme que existe la cuenta
      SISGEDO_MUNI_EMAIL y anote su contraseña.
   2. cPanel > Cuentas de correo > "Connect Devices" (Conectar dispositivos):
      anote el "Servidor saliente" y el puerto SMTP (normalmente 465 SSL).
      Ajuste SGD_CORREO_HOST / SGD_CORREO_PUERTO / SGD_CORREO_SEGURIDAD abajo.
   3. Copie conexion/correo_clave.ejemplo.php como conexion/correo_clave.php
      y escriba la contraseña. Ese archivo NO se sube a Git.
   4. cPanel > Email Deliverability: verifique que SPF y DKIM estén en
      "Valid" para el dominio (si no, los correos pueden ir a spam).
   5. Pruebe con herramientas/probar_correo.php
   ===================================================================== */

require_once __DIR__ . '/institucion.php';

// Interruptor general: false = no se envía ningún correo (se registra como DESACTIVADO)
if (!defined('SGD_CORREO_ACTIVO')) define('SGD_CORREO_ACTIVO', true);

// Nombre que verá el ciudadano como remitente
if (!defined('SGD_CORREO_NOMBRE')) define('SGD_CORREO_NOMBRE', SISGEDO_ENTIDAD . ' - Mesa de Partes');

// Dirección remitente. DEBE ser la misma cuenta con la que se autentica.
if (!defined('SGD_CORREO_DESDE')) define('SGD_CORREO_DESDE', SISGEDO_MUNI_EMAIL);

// Dirección a la que el ciudadano responde si contesta el correo ('' = la misma)
if (!defined('SGD_CORREO_RESPONDER_A')) define('SGD_CORREO_RESPONDER_A', '');

$sgd_es_local = defined('SISGEDO_ES_LOCAL') ? SISGEDO_ES_LOCAL : (
    isset($_SERVER['HTTP_HOST']) && preg_match('~localhost|127\.0\.0\.1|sisgedo\.test~', $_SERVER['HTTP_HOST'])
);

if ($sgd_es_local) {
    // ---- LOCAL: Mailpit de Laragon (sin autenticación, sin cifrado) ----
    if (!defined('SGD_CORREO_HOST'))      define('SGD_CORREO_HOST', '127.0.0.1');
    if (!defined('SGD_CORREO_PUERTO'))    define('SGD_CORREO_PUERTO', 1025);
    if (!defined('SGD_CORREO_SEGURIDAD')) define('SGD_CORREO_SEGURIDAD', '');   // '', 'ssl' o 'tls'
    if (!defined('SGD_CORREO_AUTH'))      define('SGD_CORREO_AUTH', false);
} else {
    // ---- PRODUCCIÓN: servidor de correo de la institución ----
    // Datos de cPanel > Cuentas de correo > Connect Devices (Outgoing Server).
    // OJO: es "munihuaytara.gob.pe", NO "mail.munihuaytara.gob.pe" (ese nombre no existe en el DNS).
    if (!defined('SGD_CORREO_HOST'))      define('SGD_CORREO_HOST', 'munihuaytara.gob.pe');
    if (!defined('SGD_CORREO_PUERTO'))    define('SGD_CORREO_PUERTO', 465);
    if (!defined('SGD_CORREO_SEGURIDAD')) define('SGD_CORREO_SEGURIDAD', 'ssl'); // 465 = 'ssl', 587 = 'tls'
    if (!defined('SGD_CORREO_AUTH'))      define('SGD_CORREO_AUTH', true);
}
unset($sgd_es_local);

// Usuario SMTP (en cPanel es la dirección de correo completa)
if (!defined('SGD_CORREO_USUARIO')) define('SGD_CORREO_USUARIO', SGD_CORREO_DESDE);

// Contraseña: se lee de conexion/correo_clave.php (fuera de Git)
if (!defined('SGD_CORREO_CLAVE')) {
    $sgd_archivo_clave = __DIR__ . '/correo_clave.php';
    $sgd_clave = is_file($sgd_archivo_clave) ? include $sgd_archivo_clave : '';
    define('SGD_CORREO_CLAVE', is_string($sgd_clave) ? $sgd_clave : '');
    unset($sgd_archivo_clave, $sgd_clave);
}

// Verificar el certificado SSL del servidor. Póngalo en false SOLO si el
// certificado del servidor de correo no coincide con SGD_CORREO_HOST
// (error "certificate verify failed") y no puede corregirse en cPanel.
if (!defined('SGD_CORREO_VERIFICAR_SSL')) define('SGD_CORREO_VERIFICAR_SSL', true);

// Segundos máximos de espera al servidor SMTP (el ciudadano espera mientras tanto)
if (!defined('SGD_CORREO_TIMEOUT')) define('SGD_CORREO_TIMEOUT', 15);

/* ---------------------------------------------------------------------
   COPIA EN "ENVIADOS"
   SMTP solo entrega el correo; no lo guarda en la carpeta Enviados de la
   cuenta. Para que cada constancia se vea en Roundcube (webmail) > Enviados,
   después de enviarla se sube una copia por IMAP (como hace Outlook).
   Si la copia falla, el correo YA fue enviado: solo se anota en el log.
   Datos: cPanel > Cuentas de correo > Connect Devices (Incoming Server, IMAP).
   --------------------------------------------------------------------- */
if (!defined('SGD_CORREO_GUARDAR_ENVIADOS')) define('SGD_CORREO_GUARDAR_ENVIADOS', (bool)SGD_CORREO_AUTH); // en local (Mailpit) no hay IMAP
if (!defined('SGD_CORREO_IMAP_HOST'))        define('SGD_CORREO_IMAP_HOST', SGD_CORREO_HOST);
if (!defined('SGD_CORREO_IMAP_PUERTO'))      define('SGD_CORREO_IMAP_PUERTO', 993);
if (!defined('SGD_CORREO_IMAP_SEGURIDAD'))   define('SGD_CORREO_IMAP_SEGURIDAD', 'ssl'); // 993 = 'ssl'
// Nombre de la carpeta en el servidor (Roundcube la muestra en la URL: _mbox=INBOX.Sent)
if (!defined('SGD_CORREO_CARPETA_ENVIADOS')) define('SGD_CORREO_CARPETA_ENVIADOS', 'INBOX.Sent');
