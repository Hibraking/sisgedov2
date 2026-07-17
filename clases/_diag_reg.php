<?php
// DIAGNÓSTICO TEMPORAL. Borrar después.
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "=== Probando carga_tramite (lista_tipo_tram) de reg_docs.php ===\n\n";

// Simula la petición AJAX
$_POST['carga_tramite'] = '-';

ob_start();
require __DIR__ . '/reg_docs.php';
$salida = ob_get_clean();

echo "Longitud de la respuesta: " . strlen($salida) . " caracteres\n";
echo "----- PRIMEROS 800 CARACTERES DE LA RESPUESTA -----\n";
echo substr($salida, 0, 800) . "\n";
echo "----- FIN -----\n\n";

$json = json_decode($salida, true);
if ($json === null) {
    echo "RESULTADO: La respuesta NO es JSON válido.\n";
    echo "Motivo json: " . json_last_error_msg() . "\n";
    echo "(Si arriba ves texto/advertencias antes o después del JSON, eso es lo que rompe el desplegable.)\n";
} else {
    echo "RESULTADO: JSON válido con " . count($json) . " filas.\n";
    if (count($json) > 0) {
        echo "Primera fila: " . json_encode($json[0]) . "\n";
    } else {
        echo "(La consulta devolvió 0 filas: revisar la tabla tipo_tram o el filtro.)\n";
    }
}
