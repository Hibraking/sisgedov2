<?php
// Dirección antigua. El cargo ahora está en pdf/cargo.php, con control de acceso
// (personal con sesión o, para el ciudadano, el código de verificación).
$q = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
header('Location: ../../pdf/cargo.php' . ($q !== '' ? '?' . $q : ''), true, 301);
exit;
