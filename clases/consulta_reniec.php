<?php
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	exigir_sesion_endpoint();
}
require './simple_html_dom.php';
error_reporting(E_ALL ^ E_NOTICE);

$dni = isset($_POST['dni']) ? trim((string)$_POST['dni']) : '';

// El DNI va dentro de la dirección que se consulta: solo 8 dígitos (antes se
// enviaba sin validar). Si no es válido, se responde vacío sin consultar.
if (!preg_match('/^\d{8}$/', $dni)) {
	echo json_encode(array($dni, '', '', ''));
	exit;
}

//OBTENEMOS EL VALOR
$html = @file_get_html('http://aplicaciones007.jne.gob.pe/srop_publico/Consulta/Afiliado/GetNombresCiudadano?DNI=' . $dni);
$consulta = $html ? $html->plaintext : '';

//LA LOGICA DE LA PAGINAS ES APELLIDO PATERNO | APELLIDO MATERNO | NOMBRES

$partes = explode("|", $consulta);


$datos = array(
	0 => $dni,
	1 => isset($partes[0]) ? trim($partes[0]) : '',   // sin respuesta: vacío (antes daba avisos de índice)
	2 => isset($partes[1]) ? trim($partes[1]) : '',
	3 => isset($partes[2]) ? trim($partes[2]) : '',
);

echo json_encode($datos);
