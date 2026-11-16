<?php
// Algunos cargos se imprimieron con el QR / enlace apuntando a pdf/verificar.php
// (falla corregida en conexion.php). Se redirige a la página correcta para
// que esos cargos en papel sigan funcionando.
$q = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
header('Location: ../verificar.php' . ($q !== '' ? '?' . $q : ''), true, 301);
exit;
