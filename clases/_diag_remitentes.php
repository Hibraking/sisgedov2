<?php
// DIAGNOSTICO TEMPORAL. Borrar despues.
error_reporting(E_ALL); ini_set('display_errors','1');
header('Content-Type: text/plain; charset=utf-8');

// Simula una peticion DataTables (pagina 1, 5 filas, orden por apellidos, busqueda opcional)
$buscar = isset($_GET['q']) ? $_GET['q'] : '';
$hold_post = array(
    'draw'   => 1,
    'start'  => 0,
    'length' => 5,
    'order'  => array(0 => array('column' => 2, 'dir' => 'asc')),
    'search' => array('value' => $buscar),
);

// Incluimos la clase SIN disparar el despacho serverSide (que exige sesion)
require __DIR__ . '/remitentes.php';   // define la clase e instancia $dml_remitente
$_POST = $hold_post;

ob_start();
$dml_remitente->datatable();
$out = ob_get_clean();

echo "Busqueda: '".$buscar."'\n";
echo "Longitud respuesta: ".strlen($out)."\n";
echo "----- primeros 900 -----\n".substr($out,0,900)."\n----- fin -----\n\n";
$j = json_decode($out, true);
if ($j === null) {
    echo "RESULTADO: NO es JSON. ".json_last_error_msg()."\n";
} else {
    echo "RESULTADO: JSON OK.\n";
    echo "  recordsTotal   = ".$j['recordsTotal']."\n";
    echo "  recordsFiltered= ".$j['recordsFiltered']."\n";
    echo "  filas en pagina= ".count($j['data'])."\n";
    if (count($j['data'])>0) echo "  ejemplo fila   = ".json_encode($j['data'][0], JSON_UNESCAPED_UNICODE)."\n";
}
