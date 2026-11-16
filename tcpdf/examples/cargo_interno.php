<?php
// Dirección antigua. El cargo ahora está en pdf/cargo_interno.php, con control de acceso
// (personal con sesión o, para el ciudadano, el código de verificación).
$q = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
header('Location: ../../pdf/cargo_interno.php' . ($q !== '' ? '?' . $q : ''), true, 301);
exit;
