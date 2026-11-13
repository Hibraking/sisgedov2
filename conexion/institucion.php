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
if (!defined('SISGEDO_IMG_LOGO'))     define('SISGEDO_IMG_LOGO', 'images/logo_web.png'); // versión web (120 px de alto) del original images/LOGO.png
// Ilustración del edificio en la portada del login.
// Versión ligera (PNG 1200x800); si existe un .webp con el mismo nombre, el login
// lo usa primero (137 KB frente a los 5,8 MB del SVG original edificio_svg_trans.svg).
if (!defined('SISGEDO_IMG_EDIFICIO')) define('SISGEDO_IMG_EDIFICIO', 'images/edificio_login.png');
// Ícono de pestaña del navegador (favicon) en todas las vistas
if (!defined('SISGEDO_IMG_FAVICON'))  define('SISGEDO_IMG_FAVICON', 'images/icono sistema.jpg');
// Logo de la cabecera de los PDF (archivo DENTRO de tcpdf/examples/images/)
if (!defined('SISGEDO_PDF_LOGO'))     define('SISGEDO_PDF_LOGO', 'logo huaytara.png');

/* ---- Etiqueta "NEW" de la Mesa de Partes Virtual ---- */
// Un documento virtual sin derivar se marca como NEW solo si llegó en los
// últimos N días (evita marcar registros antiguos que nunca se derivaron).
if (!defined('SISGEDO_DIAS_NUEVO'))   define('SISGEDO_DIAS_NUEVO', 30);

/* ---- Horario de atención de la Mesa de Partes ---- */
// Lo que llega por la Mesa de Partes Virtual FUERA de este horario (o en fin de
// semana / feriado) se considera presentado el siguiente día hábil a la hora de
// inicio. Formato: día de la semana (1 = lunes ... 7 = domingo) => [inicio, fin].
// Los días que no figuran aquí no son hábiles.
if (!defined('SISGEDO_HORARIO')) define('SISGEDO_HORARIO', array(
    1 => array('08:00', '18:00'),   // lunes
    2 => array('08:00', '18:00'),   // martes
    3 => array('08:00', '18:00'),   // miércoles
    4 => array('08:00', '18:00'),   // jueves
    5 => array('08:00', '17:30'),   // viernes
));
// Texto del horario para mostrar al ciudadano
if (!defined('SISGEDO_HORARIO_TEXTO')) define('SISGEDO_HORARIO_TEXTO', 'lunes a jueves de 8:00 a. m. a 6:00 p. m. y viernes de 8:00 a. m. a 5:30 p. m.');

// Feriados nacionales de fecha fija (mes-día). Jueves y Viernes Santo se
// calculan solos cada año. Días no laborables o feriados locales (aniversario,
// días declarados por el Gobierno): agréguelos en la tabla sgd_dia_no_laborable.
if (!defined('SISGEDO_FERIADOS_FIJOS')) define('SISGEDO_FERIADOS_FIJOS', array(
    '01-01' => 'Año Nuevo',
    '05-01' => 'Día del Trabajo',
    '06-07' => 'Batalla de Arica y Día de la Bandera',
    '06-29' => 'San Pedro y San Pablo',
    '07-23' => 'Día de la Fuerza Aérea del Perú',
    '07-28' => 'Fiestas Patrias',
    '07-29' => 'Fiestas Patrias',
    '08-06' => 'Batalla de Junín',
    '08-30' => 'Santa Rosa de Lima',
    '10-08' => 'Combate de Angamos',
    '11-01' => 'Día de Todos los Santos',
    '12-08' => 'Inmaculada Concepción',
    '12-09' => 'Batalla de Ayacucho',
    '12-25' => 'Navidad',
));

// Plazo (días hábiles) que tiene el ciudadano para subsanar un documento
// observado por la Mesa de Partes (Ley 27444, art. 136).
if (!defined('SISGEDO_PLAZO_SUBSANAR')) define('SISGEDO_PLAZO_SUBSANAR', 2);

/* ---- Derivados automáticos (NO editar) ---- */
if (!defined('SISGEDO_ENTIDAD')) {
    define('SISGEDO_ENTIDAD', 'Municipalidad ' . SISGEDO_MUNI_TIPO . ' de ' . SISGEDO_MUNI_LUGAR);
    define('SISGEDO_ENTIDAD_MAYUS', function_exists('mb_strtoupper')
        ? mb_strtoupper(SISGEDO_ENTIDAD, 'UTF-8')
        : strtoupper(SISGEDO_ENTIDAD));
}
