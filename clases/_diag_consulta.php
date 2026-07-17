<?php
// DIAGNÓSTICO TEMPORAL. Borrar después.
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

$num = isset($_GET['n']) ? $_GET['n'] : '20263304';
echo "=== Probando datos_registro (RESULTADOS) para: $num ===\n\n";

// Simula la petición AJAX de la ficha del documento
$_POST = array('datos_registro' => '-', 'codigo' => $num);

ob_start();
require __DIR__ . '/consulta_interna.php';
$salida = ob_get_clean();

echo "Longitud de la respuesta: " . strlen($salida) . " caracteres\n";
echo "----- RESPUESTA (primeros 1500) -----\n";
echo substr($salida, 0, 1500) . "\n";
echo "----- FIN -----\n\n";

$json = json_decode($salida, true);
if ($json === null) {
    echo "RESULTADO: NO es JSON valido.  Motivo: " . json_last_error_msg() . "\n";
    echo "(Si arriba hay texto/advertencia antes o despues del JSON, eso rompe la ficha.)\n";
} else {
    echo "RESULTADO: JSON valido con " . count($json) . " fila(s).\n";
    if (count($json) > 0) {
        echo "Primera fila: " . json_encode($json[0], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "(Devolvio 0 filas: la consulta de la ficha no encontro el registro.)\n";
    }
}
