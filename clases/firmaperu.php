<?php
/* =====================================================================
   SISGEDO — Integración con FIRMA PERÚ (Plataforma Nacional de Firma Digital)
   Basado en: "Documento de integración entre un sistema de información y
   el componente web de FIRMA PERÚ" (PCM, 2025).

   Acciones:
     POST accion=iniciar   (usuario con sesión)  -> parámetros iniciales en Base64
     POST accion=param     (lo llama el firmador) -> parámetros de firma en Base64
     GET  accion=documento (lo llama el firmador) -> PDF a firmar
     POST accion=subir     (lo llama el firmador) -> recibe el PDF firmado (signed_file)
     POST accion=estado    (usuario con sesión)  -> estado de la firma

   Las acciones del firmador NO usan la sesión del navegador: se autorizan
   con un token de un solo uso (param_token) que vence en pocos minutos.
   ===================================================================== */

require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/firmaperu_config.php';
require_once __DIR__ . '/gestion_documental.php';

$accion = isset($_REQUEST['accion']) ? (string)$_REQUEST['accion'] : '';
$CARPETA_EMI = __DIR__ . '/../docs adjs docs_emitidos/';

function fp_json($arr, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}
function fp_error($msg, $code = 400)
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

/** Busca una solicitud de firma PENDIENTE y vigente por su token. */
function fp_solicitud($token)
{
    if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) return null;
    $st = sgd_db()->prepare("SELECT f.*, de.adjunto, de.idoficina_registro
                               FROM sgd_firma f
                               INNER JOIN docs_emitidos de ON de.iddocs_emitidos = f.iddocs_emitidos
                              WHERE f.param_token = ? AND f.estado = 'PENDIENTE' AND f.token_expira > NOW()");
    $st->execute(array($token));
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? $r : null;
}

/** Token JWT de FIRMA PERÚ (se reutiliza hasta que vence, como recomienda la PCM). */
function fp_token_acceso()
{
    $cache = __DIR__ . '/../logs/firmaperu_token.json';
    if (is_file($cache)) {
        $c = json_decode((string)@file_get_contents($cache), true);
        if (is_array($c) && !empty($c['token']) && isset($c['exp']) && $c['exp'] > time() + 60) {
            return $c['token'];
        }
    }
    $cred = json_decode((string)file_get_contents(SGD_FIRMAPERU_CREDENCIALES), true);
    if (!is_array($cred) || empty($cred['client_id']) || empty($cred['client_secret']) || empty($cred['token_url'])) {
        throw new Exception('fwAuthorization.json incompleto (client_id, client_secret, token_url).');
    }
    $cuerpo = http_build_query(array('client_id' => $cred['client_id'], 'client_secret' => $cred['client_secret']));
    if (function_exists('curl_init')) {
        $ch = curl_init($cred['token_url']);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        ));
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(array('http' => array(
            'method' => 'POST', 'timeout' => 20, 'content' => $cuerpo,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        )));
        $resp = @file_get_contents($cred['token_url'], false, $ctx);
        $http = $resp === false ? 0 : 200;
    }
    $token = trim((string)$resp);
    if ($http !== 200 || $token === '') {
        throw new Exception('No se pudo obtener el token de FIRMA PERÚ (HTTP ' . $http . ').');
    }
    // Vencimiento leído del propio JWT (claim "exp"); si no se puede, 10 minutos.
    $exp = time() + 600;
    $partes = explode('.', $token);
    if (count($partes) === 3) {
        $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);
        if (is_array($payload) && !empty($payload['exp'])) {
            $exp = (int)$payload['exp'];
        }
    }
    @file_put_contents($cache, json_encode(array('token' => $token, 'exp' => $exp)), LOCK_EX);
    return $token;
}

/* ---------------------------------------------------------------------
   1) INICIAR (desde la vista de Emisión, con sesión y CSRF)
   --------------------------------------------------------------------- */
if ($accion === 'iniciar' || $accion === 'estado') {
    require_once __DIR__ . '/seguridad.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
    exigir_sesion_endpoint();
    csrf_require();
    $iddoc = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $idoficina = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
    $idusuario = isset($_SESSION['usuario']) ? intval($_SESSION['usuario']) : 0;

    $st = sgd_db()->prepare("SELECT iddocs_emitidos, adjunto, idoficina_registro, estado FROM docs_emitidos WHERE iddocs_emitidos = ?");
    $st->execute(array($iddoc));
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc) fp_json(array('ok' => false, 'mensaje' => 'Documento no encontrado.'));
    if ($idusuario !== 1 && intval($doc['idoficina_registro']) !== $idoficina) {
        fp_json(array('ok' => false, 'mensaje' => 'Solo la oficina emisora puede firmar este documento.'), 403);
    }

    if ($accion === 'estado') {
        $st = sgd_db()->prepare("SELECT estado, tipo_firma, fecha_firma FROM sgd_firma WHERE iddocs_emitidos = ? ORDER BY idfirma DESC LIMIT 1");
        $st->execute(array($iddoc));
        $f = $st->fetch(PDO::FETCH_ASSOC);
        fp_json(array('ok' => true, 'firma' => $f ? $f : null));
    }

    if (!sgd_firmaperu_activo()) {
        fp_json(array('ok' => false, 'mensaje' => 'La firma digital aún no está configurada (falta el archivo de credenciales de FIRMA PERÚ).'));
    }
    if (empty($doc['adjunto']) || strtolower(pathinfo($doc['adjunto'], PATHINFO_EXTENSION)) !== 'pdf') {
        fp_json(array('ok' => false, 'mensaje' => 'Para firmar digitalmente, el adjunto del documento debe ser un PDF.'));
    }
    if (!is_file($CARPETA_EMI . $doc['adjunto'])) {
        fp_json(array('ok' => false, 'mensaje' => 'No se encontró el archivo adjunto en el servidor.'));
    }
    if (intval($doc['estado']) === 1) {
        fp_json(array('ok' => false, 'mensaje' => 'El documento ya fue recepcionado por su destino; no puede modificarse.'));
    }
    $tipo = (isset($_POST['tipo']) && $_POST['tipo'] === 'VISTO_BUENO') ? 'VISTO_BUENO' : 'FIRMA';
    $token = bin2hex(random_bytes(32));
    $ins = sgd_db()->prepare("INSERT INTO sgd_firma (iddocs_emitidos, idpersonal, tipo_firma, param_token, token_expira, archivo_origen)
                              VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)");
    $ins->execute(array($iddoc, $idusuario ?: null, $tipo, $token, SGD_FIRMAPERU_MINUTOS, $doc['adjunto']));

    $inicial = array(
        'param_url' => sisgedo_base_url('clases/firmaperu.php') . '?accion=param',
        'param_token' => $token,
        'document_extension' => 'pdf',
    );
    fp_json(array('ok' => true, 'puerto' => SGD_FIRMAPERU_PUERTO, 'param' => base64_encode(json_encode($inicial))));
}

/* ---------------------------------------------------------------------
   2) PARÁMETROS DE FIRMA (POST x-www-form-urlencoded con param_token)
   --------------------------------------------------------------------- */
if ($accion === 'param') {
    $sol = fp_solicitud(isset($_POST['param_token']) ? $_POST['param_token'] : '');
    if (!$sol) fp_error('Solicitud de firma inválida o vencida.', 403);
    try {
        $jwt = fp_token_acceso();
    } catch (Exception $e) {
        error_log('firmaperu param: ' . $e->getMessage());
        fp_error('No se pudo autenticar con FIRMA PERÚ.', 502);
    }
    $base = sisgedo_base_url('clases/firmaperu.php');
    $esVB = $sol['tipo_firma'] === 'VISTO_BUENO';
    $param = array(
        'signatureFormat' => 'PAdES',
        'signatureLevel' => SGD_FIRMAPERU_NIVEL,
        'signaturePackaging' => 'enveloped',
        'documentToSign' => $base . '?accion=documento&t=' . $sol['param_token'],
        'certificateFilter' => SGD_FIRMAPERU_FILTRO_CERT,
        'webTsa' => SGD_FIRMAPERU_TSA_URL,
        'userTsa' => SGD_FIRMAPERU_TSA_USER,
        'passwordTsa' => SGD_FIRMAPERU_TSA_PASS,
        'theme' => 'claro',
        'visiblePosition' => true,
        'contactInfo' => '',
        'signatureReason' => $esVB ? 'Doy V° B°' : 'Soy el autor del documento',
        'bachtOperation' => false,   // nombre de campo tal como lo define FIRMA PERÚ
        'oneByOne' => true,
        'signatureStyle' => 1,
        'imageToStamp' => sisgedo_base_url(implode('/', array_map('rawurlencode', explode('/', SGD_FIRMAPERU_IMAGEN)))),
        'stampTextSize' => 14,
        'stampWordWrap' => 37,
        'role' => $esVB ? 'Visto bueno' : '',
        'stampPage' => 1,
        'positionx' => 20,
        'positiony' => 20,
        'uploadDocumentSigned' => $base . '?accion=subir&t=' . $sol['param_token'],
        'certificationSignature' => false,
        'token' => $jwt,
    );
    header('Content-Type: text/plain; charset=utf-8');
    echo base64_encode(json_encode($param));
    exit;
}

/* ---------------------------------------------------------------------
   3) DOCUMENTO A FIRMAR (descarga por el firmador)
   --------------------------------------------------------------------- */
if ($accion === 'documento') {
    $sol = fp_solicitud(isset($_GET['t']) ? $_GET['t'] : '');
    if (!$sol) fp_error('Solicitud de firma inválida o vencida.', 403);
    $ruta = $CARPETA_EMI . basename($sol['adjunto']);
    if (!is_file($ruta)) fp_error('Archivo no encontrado.', 404);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($ruta));
    header('Content-Disposition: inline; filename="documento.pdf"');
    readfile($ruta);
    exit;
}

/* ---------------------------------------------------------------------
   4) SUBIDA DEL DOCUMENTO FIRMADO (campo "signed_file")
   --------------------------------------------------------------------- */
if ($accion === 'subir') {
    $sol = fp_solicitud(isset($_GET['t']) ? $_GET['t'] : '');
    if (!$sol) fp_error('Solicitud de firma inválida o vencida.', 403);
    if (!isset($_FILES['signed_file']) || !is_uploaded_file($_FILES['signed_file']['tmp_name'])) {
        fp_error('No se recibió el documento firmado.', 400);
    }
    $tmp = $_FILES['signed_file']['tmp_name'];
    $cabecera = (string)@file_get_contents($tmp, false, null, 0, 5);
    if ($cabecera !== '%PDF-' || filesize($tmp) > 30 * 1024 * 1024) {
        sgd_db()->prepare("UPDATE sgd_firma SET estado='ERROR', detalle='Archivo recibido no es un PDF válido' WHERE idfirma=?")->execute(array($sol['idfirma']));
        fp_error('El archivo firmado no es un PDF válido.', 400);
    }
    // Se guarda como archivo NUEVO; el original se conserva para trazabilidad.
    $nuevo = bin2hex(random_bytes(8)) . '_' . time() . '_firmado.pdf';
    if (!move_uploaded_file($tmp, $CARPETA_EMI . $nuevo)) {
        fp_error('No se pudo guardar el documento firmado.', 500);
    }
    $hash = sgd_registrar_archivo('EMITIDO', $nuevo, $CARPETA_EMI . $nuevo, 'firmado.pdf', 'INTERNO');
    $db = sgd_db();
    $db->beginTransaction();
    // Todas las filas del mismo envío (un destino por fila) comparten el adjunto.
    $db->prepare("UPDATE docs_emitidos SET adjunto = ? WHERE adjunto = ? AND idoficina_registro = ?")
        ->execute(array($nuevo, $sol['adjunto'], $sol['idoficina_registro']));
    $db->prepare("UPDATE sgd_firma SET estado='FIRMADO', archivo_firmado=?, hash_sha256=?, fecha_firma=NOW() WHERE idfirma=?")
        ->execute(array($nuevo, $hash, $sol['idfirma']));
    $db->commit();
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
    exit;
}

fp_error('Acción no válida.', 400);
