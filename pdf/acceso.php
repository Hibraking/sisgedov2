<?php
/* =====================================================================
   SISGEDO — PDFs propios del sistema (cargos): carga de la librería y
   CONTROL DE ACCESO.
   ---------------------------------------------------------------------
   Antes los cargos se abrían con solo cambiar el N° de expediente en la
   dirección (los números son correlativos): cualquiera podía descargar
   los cargos de otras personas. Ahora:
     - Personal de la municipalidad (con sesión): por N° (?exp=...).
     - Ciudadano (sin sesión): solo con el CÓDIGO DE VERIFICACIÓN impreso
       en su cargo (?c=XXXX-XXXX), que no se puede adivinar.
   ===================================================================== */

require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../clases/gestion_documental.php';

// Un PDF es salida binaria: cualquier aviso impreso antes lo rompe. Se apagan
// ANTES de cargar TCPDF, porque sus avisos "Deprecated" aparecen al compilarse
// (la primera petición tras reiniciar el servidor) y salían antes del PDF.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/../tcpdf/examples/config/tcpdf_config_alt.php';   // rutas de imágenes/fuentes
require_once __DIR__ . '/../tcpdf/tcpdf.php';

/** ¿Hay un usuario del sistema con sesión iniciada? */
function pdf_hay_sesion()
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $ok = !empty($_SESSION['usuario']);
    session_write_close();                 // el PDF no modifica la sesión
    return $ok;
}

/** Corta con una página clara (no un PDF vacío) cuando no hay permiso. */
function pdf_denegar($mensaje)
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $verificar = sisgedo_base_url('verificar.php');
    $login = sisgedo_base_url('login.php');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Cargo no disponible</title><style>body{font-family:Segoe UI,Arial,sans-serif;background:#f1f5f9;color:#1e293b;margin:0;padding:40px 16px}'
        . '.c{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:26px}h1{font-size:19px;color:#123a7a;margin:0 0 10px}'
        . 'p{font-size:14px;line-height:1.55;color:#475569}a{color:#2563eb;font-weight:600}</style></head><body><div class="c">'
        . '<h1>Cargo no disponible</h1><p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Si usted presentó el documento, use el <b>código de verificación</b> de su cargo en <a href="' . htmlspecialchars($verificar, ENT_QUOTES, 'UTF-8') . '">Verificar documento</a>.</p>'
        . '<p>Si es personal de la municipalidad, <a href="' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '">inicie sesión</a> y vuelva a abrir el cargo.</p>'
        . '</div></body></html>';
    exit;
}

/** Solo personal con sesión (cargos internos). */
function pdf_exigir_sesion()
{
    if (!pdf_hay_sesion()) {
        pdf_denegar('Este cargo es de uso interno de la municipalidad.');
    }
}

/**
 * N° de expediente que se puede mostrar:
 *  - con ?c=CÓDIGO: el expediente al que pertenece ese código (sin sesión);
 *  - con ?exp=N°: solo si hay sesión.
 */
function pdf_expediente_autorizado()
{
    $codigo = isset($_GET['c']) ? trim((string)$_GET['c']) : '';
    if ($codigo !== '') {
        $cvd = sgd_normalizar_cvd($codigo);
        $exp = null;
        if (strlen($cvd) === 8 && sgd_migracion_ok()) {
            $st = sgd_db()->prepare("SELECT referencia FROM sgd_verificacion WHERE cvd = ? AND tipo = 'EXPEDIENTE' LIMIT 1");
            $st->execute(array($cvd));
            $exp = $st->fetchColumn();
        }
        if (!$exp) {
            pdf_denegar('El código de verificación no es válido o no corresponde a un cargo de mesa de partes.');
        }
        return (string)$exp;
    }
    if (!pdf_hay_sesion()) {
        pdf_denegar('Para ver un cargo se necesita el código de verificación impreso en él.');
    }
    return isset($_GET['exp']) ? trim((string)$_GET['exp']) : '';
}
