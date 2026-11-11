<?php
/* =====================================================================
   SISGEDO — Configuración de FIRMA PERÚ (firma digital, Ley N° 27269)
   ---------------------------------------------------------------------
   La firma digital se ACTIVA sola cuando existe el archivo de
   credenciales fwAuthorization.json que entrega la Secretaría de
   Gobierno y Transformación Digital (PCM) al aprobar la solicitud de
   credenciales del "Firmador web" de FIRMA PERÚ.

   PASOS PARA ACTIVAR
   1. Solicitar las credenciales a la PCM (formato "Solicitud de
      credenciales Firma Perú - Firmador web", firmado por el titular).
   2. Copiar el archivo fwAuthorization.json recibido en la ruta indicada
      abajo (FUERA de la carpeta pública del sistema).
   3. Cada usuario que firme instala en su PC el cliente de FIRMA PERÚ y
      usa su DNIe o certificado digital (RENIEC / EREP).
   ===================================================================== */

// Ruta del archivo de credenciales (por defecto, una carpeta "firmaperu"
// al lado de la carpeta del sistema, no accesible desde la web).
if (!defined('SGD_FIRMAPERU_CREDENCIALES')) {
    define('SGD_FIRMAPERU_CREDENCIALES', dirname(__DIR__, 2) . '/firmaperu/fwAuthorization.json');
}

// Puerto local del firmador en la PC del usuario (recomendado por PCM: 48596)
if (!defined('SGD_FIRMAPERU_PUERTO'))      define('SGD_FIRMAPERU_PUERTO', 48596);

// Script del componente web (no cambiar salvo que la PCM lo indique)
if (!defined('SGD_FIRMAPERU_JS'))          define('SGD_FIRMAPERU_JS', 'https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js');

// Filtro de certificados (expresión regular sobre el CN). '.*' = todos
if (!defined('SGD_FIRMAPERU_FILTRO_CERT')) define('SGD_FIRMAPERU_FILTRO_CERT', '.*');

// Nivel de firma: 'B' básica, 'T' con sello de tiempo (requiere TSA), 'LTA' largo plazo
if (!defined('SGD_FIRMAPERU_NIVEL'))       define('SGD_FIRMAPERU_NIVEL', 'B');
// Sello de tiempo (TSA). Dejar vacío si no se cuenta con uno.
if (!defined('SGD_FIRMAPERU_TSA_URL'))     define('SGD_FIRMAPERU_TSA_URL', '');
if (!defined('SGD_FIRMAPERU_TSA_USER'))    define('SGD_FIRMAPERU_TSA_USER', '');
if (!defined('SGD_FIRMAPERU_TSA_PASS'))    define('SGD_FIRMAPERU_TSA_PASS', '');

// Imagen PNG del estampado de la firma (ruta desde la raíz del sistema)
if (!defined('SGD_FIRMAPERU_IMAGEN'))      define('SGD_FIRMAPERU_IMAGEN', 'tcpdf/examples/images/' . (defined('SISGEDO_PDF_LOGO') ? SISGEDO_PDF_LOGO : 'logo huaytara.png'));

// Minutos de validez del enlace de firma de un solo uso
if (!defined('SGD_FIRMAPERU_MINUTOS'))     define('SGD_FIRMAPERU_MINUTOS', 15);

/** ¿Está configurada la firma digital? */
function sgd_firmaperu_activo()
{
    return is_file(SGD_FIRMAPERU_CREDENCIALES) && is_readable(SGD_FIRMAPERU_CREDENCIALES);
}
