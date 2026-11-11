<?php
/* =====================================================================
   SISGEDO — DATOS DE LA INSTITUCIÓN
   =====================================================================
   Este es el ÚNICO archivo que debe editar para adaptar el sistema a
   otra municipalidad. Todo el proyecto (vistas, páginas públicas,
   cargos PDF, correos) toma el nombre desde aquí.

   Para cambiar los logos tiene dos opciones:
   a) Reemplazar el CONTENIDO de los archivos de imagen actuales, o
   b) Subir imágenes nuevas y cambiar las rutas en las constantes
      SISGEDO_IMG_* de más abajo.
   ===================================================================== */

// Tipo de municipalidad: 'Provincial' o 'Distrital'
if (!defined('SISGEDO_MUNI_TIPO'))   define('SISGEDO_MUNI_TIPO', 'Provincial');

// Nombre del lugar (con sus tildes)
if (!defined('SISGEDO_MUNI_LUGAR'))  define('SISGEDO_MUNI_LUGAR', 'Huaytará');

// Siglas cortas (aparecen en el pie de las páginas públicas)
if (!defined('SISGEDO_MUNI_SIGLAS')) define('SISGEDO_MUNI_SIGLAS', 'MPH');

// Correo remitente de la Mesa de Partes Virtual
if (!defined('SISGEDO_MUNI_EMAIL'))  define('SISGEDO_MUNI_EMAIL', 'mesadepartes@munihuaytara.gob.pe');

// Firma del correo de la Mesa de Partes Virtual (dejar '' para omitir)
if (!defined('SISGEDO_MUNI_FIRMA'))   define('SISGEDO_MUNI_FIRMA', 'Wilmer Cabrera - Alcalde');
if (!defined('SISGEDO_MUNI_GESTION')) define('SISGEDO_MUNI_GESTION', 'Gestión Edil 2023 - 2026');

/* ---- Imágenes institucionales (rutas desde la raíz del proyecto) ---- */
// Logo principal (cabecera de páginas públicas y correo al ciudadano)
if (!defined('SISGEDO_IMG_LOGO'))     define('SISGEDO_IMG_LOGO', 'images/LOGO.png');
// Ilustración del edificio en la portada del login
if (!defined('SISGEDO_IMG_EDIFICIO')) define('SISGEDO_IMG_EDIFICIO', 'images/edificio_svg_trans.svg');
// Ícono de pestaña del navegador (favicon) en todas las vistas
if (!defined('SISGEDO_IMG_FAVICON'))  define('SISGEDO_IMG_FAVICON', 'images/icono sistema.jpg');
// Logo de la cabecera de los PDF (archivo DENTRO de tcpdf/examples/images/)
if (!defined('SISGEDO_PDF_LOGO'))     define('SISGEDO_PDF_LOGO', 'logo huaytara.png');

/* ---- Derivados automáticos (NO editar) ---- */
if (!defined('SISGEDO_ENTIDAD')) {
    define('SISGEDO_ENTIDAD', 'Municipalidad ' . SISGEDO_MUNI_TIPO . ' de ' . SISGEDO_MUNI_LUGAR);
    define('SISGEDO_ENTIDAD_MAYUS', function_exists('mb_strtoupper')
        ? mb_strtoupper(SISGEDO_ENTIDAD, 'UTF-8')
        : strtoupper(SISGEDO_ENTIDAD));
}
