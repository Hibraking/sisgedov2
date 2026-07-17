<?php

/**
 * Validación centralizada de archivos subidos (adjuntos de trámite).
 * Uso:
 *   require_once __DIR__ . '/util_subida.php';
 *   if (!sisgedo_validar_archivo($_FILES['fichero'], $err)) { echo $err; return; }
 *   $nombre_fichero = sisgedo_nombre_seguro($_FILES['fichero']);
 */

function sisgedo_validar_archivo($file, &$error = null)
{
    $error = null;

    // Extensiones permitidas para documentos de mesa de partes.
    $permitidas = array(
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'txt',
        'csv',
        'zip',
        'rar'
    );
    $max_bytes = 15 * 1024 * 1024; // 15 MB

    if (!isset($file) || !is_array($file)) {
        $error = 'No se recibió ningún archivo.';
        return false;
    }
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ocurrió un error al subir el archivo.';
        return false;
    }
    if ($file['size'] <= 0 || $file['size'] > $max_bytes) {
        $error = 'El archivo está vacío o supera el tamaño máximo permitido (15 MB).';
        return false;
    }

    // Rechazar cualquier fragmento de nombre que sea una extensión ejecutable
    // (evita trucos tipo "documento.php.pdf").
    $peligrosas = array(
        'php',
        'phtml',
        'php3',
        'php4',
        'php5',
        'php7',
        'phar',
        'cgi',
        'pl',
        'py',
        'asp',
        'aspx',
        'jsp',
        'sh',
        'exe',
        'bat',
        'htaccess'
    );
    $partes = explode('.', strtolower($file['name']));
    foreach ($partes as $p) {
        if (in_array($p, $peligrosas, true)) {
            $error = 'Nombre de archivo no permitido por seguridad.';
            return false;
        }
    }

    // La extensión final debe estar en la lista blanca.
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidas, true)) {
        $error = 'Tipo de archivo no permitido. Solo se aceptan: ' . implode(', ', $permitidas) . '.';
        return false;
    }

    return true;
}

/**
 * Genera un nombre de archivo seguro y único, conservando la extensión validada.
 * No usa el nombre original (controlado por el usuario) para el archivo en disco.
 */
function sisgedo_nombre_seguro($file)
{
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (function_exists('random_bytes')) {
        $aleatorio = bin2hex(random_bytes(8));
    } else {
        $aleatorio = md5(uniqid((string)mt_rand(), true));
    }
    return $aleatorio . '_' . time() . '.' . $ext;
}
