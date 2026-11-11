<?php
/* =====================================================================
   SISGEDO — Registro de huellas SHA-256 de adjuntos EXISTENTES (MGD)
   ---------------------------------------------------------------------
   Los archivos subidos desde ahora registran su huella automáticamente.
   Este script calcula la huella de los adjuntos que ya estaban en el
   servidor antes de la migración 001, para que también puedan
   verificarse. Procesa por lotes y se puede ejecutar varias veces: solo
   procesa los archivos que aún no tienen huella.

   USO
   - Por consola (recomendado; Laragon > Terminal o SSH del hosting):
       php herramientas/sgd_registrar_hashes.php
   - Por navegador (solo el administrador, usuario ID 1, con sesión):
       https://<dominio>/sisgedo/herramientas/sgd_registrar_hashes.php
     Procesa 300 archivos por carga; recargue hasta que diga "Terminado".
   ===================================================================== */

$esCli = (PHP_SAPI === 'cli');
if ($esCli) {
    $_SERVER['HTTP_HOST'] = isset($argv[1]) ? $argv[1] : 'localhost';
} else {
    session_start();
    if (empty($_SESSION['usuario']) || intval($_SESSION['usuario']) !== 1) {
        http_response_code(403);
        exit('Acceso restringido al administrador.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
@set_time_limit(0);

require_once __DIR__ . '/../clases/gestion_documental.php';

$lote = $esCli ? 1000000 : 300;
$db = sgd_db();
$raiz = realpath(__DIR__ . '/..');
$fuentes = array(
    // origen, carpeta, consulta de adjuntos sin huella (con su canal)
    array('REGISTRO', 'docs adjs reg_doc',
        "SELECT rd.adjunto, MAX(COALESCE(rd.recepcion_web,0))
           FROM registro_documentos rd
           LEFT JOIN sgd_archivo a ON a.origen = 'REGISTRO' AND a.nombre_almacenado = rd.adjunto
          WHERE rd.adjunto IS NOT NULL AND rd.adjunto <> '' AND a.idarchivo IS NULL
          GROUP BY rd.adjunto LIMIT " . (int)$lote),
    array('EMITIDO', 'docs adjs docs_emitidos',
        "SELECT de.adjunto, 0
           FROM docs_emitidos de
           LEFT JOIN sgd_archivo a ON a.origen = 'EMITIDO' AND a.nombre_almacenado = de.adjunto
          WHERE de.adjunto IS NOT NULL AND de.adjunto <> '' AND a.idarchivo IS NULL
          GROUP BY de.adjunto LIMIT " . (int)$lote),
);

$total = 0;
$faltantes = 0;
foreach ($fuentes as $f) {
    list($origen, $carpeta, $sql) = $f;
    $filas = $db->query($sql)->fetchAll(PDO::FETCH_NUM);
    foreach ($filas as $r) {
        $nombre = $r[0];
        $ruta = $raiz . '/' . $carpeta . '/' . $nombre;
        if (!is_file($ruta)) {
            $faltantes++;
            continue;
        }
        $canal = $origen === 'EMITIDO' ? 'INTERNO' : (intval($r[1]) === 1 ? 'VIRTUAL' : 'PRESENCIAL');
        if (sgd_registrar_archivo($origen, $nombre, $ruta, null, $canal)) {
            $total++;
            if ($esCli && $total % 200 === 0) {
                echo "  ... $total archivos\n";
            }
        }
    }
    echo "$origen: " . count($filas) . " pendientes revisados.\n";
}

echo "Huellas registradas en esta ejecución: $total\n";
echo "Adjuntos referenciados que no existen en disco: $faltantes\n";
if (!$esCli && $total > 0) {
    echo "Recargue la página para procesar el siguiente lote.\n";
} else {
    echo "Terminado.\n";
}
