<?php
/* =====================================================================
   SISGEDO — Envío de correos (PHPMailer + SMTP autenticado)
   ---------------------------------------------------------------------
   Librería (NO es un endpoint). Se incluye con:
       require_once __DIR__ . '/correo.php';

   Uso:
       $ok = sgd_correo_enviar('ciudadano@x.com', 'Asunto', $html, array(
           'tipo'       => 'MPV_RECEPCION',   // motivo (queda en la bitácora)
           'referencia' => $nro_exp,          // expediente relacionado
       ));
       if (!$ok) { $error = sgd_correo_ultimo_error(); }

   Cada intento queda registrado en la tabla sgd_correo
   (migraciones/002_registro_correos.sql) con su estado y error.
   Configuración: conexion/correo_config.php
   ===================================================================== */

require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/correo_config.php';
require_once __DIR__ . '/../librerias/PHPMailer/Exception.php';
require_once __DIR__ . '/../librerias/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../librerias/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/** Último error de envío (texto legible) o '' si el último envío salió bien. */
function sgd_correo_ultimo_error($nuevo = null)
{
    static $error = '';
    if ($nuevo !== null) {
        $error = (string)$nuevo;
    }
    return $error;
}

/** Conexión PDO propia (modo excepción) para la bitácora. */
function sgd_correo_db()
{
    static $db = false;
    if ($db === false) {
        $db = null;
        try {
            $c = new Conexion();
            $db = $c->conectar();
            if ($db) {
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } catch (Exception $e) {
            $db = null;
        }
    }
    return $db;
}

/** ¿Existe la tabla sgd_correo? (migración 002) */
function sgd_correo_bitacora_ok()
{
    static $ok = null;
    if ($ok === null) {
        $ok = false;
        try {
            $db = sgd_correo_db();
            if ($db) {
                $st = $db->query("SELECT COUNT(*) FROM information_schema.tables
                                   WHERE table_schema = DATABASE() AND table_name = 'sgd_correo'");
                $ok = ((int)$st->fetchColumn()) > 0;
            }
        } catch (Exception $e) {
            $ok = false;
        }
        if (!$ok) {
            error_log('SISGEDO: falta ejecutar migraciones/002_registro_correos.sql (los correos no quedan en bitácora).');
        }
    }
    return $ok;
}

/** Guarda el intento de envío en la bitácora (o en el log de PHP si no hay tabla). */
function sgd_correo_registrar($tipo, $referencia, $destinatario, $asunto, $estado, $detalle = null, $message_id = null)
{
    $cortar = function ($s, $n) {
        if ($s === null) return null;
        $s = (string)$s;
        return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
    };
    $idpersonal = (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['usuario'])) ? (int)$_SESSION['usuario'] : null;
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

    if (sgd_correo_bitacora_ok()) {
        try {
            $st = sgd_correo_db()->prepare(
                "INSERT INTO sgd_correo (tipo, referencia, destinatario, asunto, estado, detalle, message_id, servidor, idpersonal, ip)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute(array(
                $cortar($tipo, 40), $cortar($referencia, 45), $cortar($destinatario, 255), $cortar($asunto, 255),
                $estado, $cortar($detalle, 1000), $cortar($message_id, 255),
                $cortar(SGD_CORREO_HOST . ':' . SGD_CORREO_PUERTO, 120), $idpersonal, $ip,
            ));
            return;
        } catch (Exception $e) {
            error_log('SISGEDO correo: no se pudo guardar en bitácora: ' . $e->getMessage());
        }
    }
    error_log("SISGEDO correo [$estado] $tipo ref=$referencia a=$destinatario" . ($detalle ? " — $detalle" : ''));
}

/**
 * Plantilla HTML institucional para todos los correos del sistema.
 * $cuerpo_html ya debe venir escapado. El logo se incrusta como cid:logo.
 */
function sgd_correo_plantilla($titulo, $cuerpo_html)
{
    $e = function ($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    };
    $firma = '';
    if (defined('SISGEDO_MUNI_FIRMA') && SISGEDO_MUNI_FIRMA !== '') {
        $firma .= $e(SISGEDO_MUNI_FIRMA) . '<br>';
    }
    if (defined('SISGEDO_MUNI_GESTION') && SISGEDO_MUNI_GESTION !== '') {
        $firma .= $e(SISGEDO_MUNI_GESTION);
    }
    return '<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $e($titulo) . '</title></head>
<body style="margin:0;padding:0;background:#f2f4f8;font-family:Arial,Helvetica,sans-serif;color:#1f2933">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f8;padding:24px 0">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden">
  <tr><td style="background:#123a7a;padding:18px 24px;color:#ffffff">
    <img src="cid:logo" alt="" height="48" style="height:48px;vertical-align:middle;margin-right:12px;border:0">
    <span style="font-size:16px;font-weight:bold;vertical-align:middle">' . $e(SISGEDO_ENTIDAD_MAYUS) . '</span>
  </td></tr>
  <tr><td style="padding:24px">
    <h1 style="font-size:20px;color:#123a7a;margin:0 0 16px">' . $e($titulo) . '</h1>
    ' . $cuerpo_html . '
    ' . ($firma !== '' ? '<p style="font-size:14px;color:#52606d;margin-top:24px">' . $firma . '</p>' : '') . '
  </td></tr>
  <tr><td style="background:#f7f9fc;padding:14px 24px;font-size:11px;color:#7b8794">
    Sistema de Trámite Documentario - ' . $e(SISGEDO_ENTIDAD) . '.<br>
    Este es un mensaje automático; no es necesario responderlo.
  </td></tr>
</table>
</td></tr></table>
</body></html>';
}

/**
 * Envía un correo HTML. Devuelve true si el servidor SMTP lo aceptó.
 * $opc: tipo, referencia, texto (versión texto plano), responder_a
 */
function sgd_correo_enviar($destino, $asunto, $html, array $opc = array())
{
    $tipo       = isset($opc['tipo']) ? $opc['tipo'] : 'GENERAL';
    $referencia = isset($opc['referencia']) ? (string)$opc['referencia'] : null;
    $destino    = trim((string)$destino);
    sgd_correo_ultimo_error('');

    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        sgd_correo_ultimo_error('Dirección de correo no válida.');
        sgd_correo_registrar($tipo, $referencia, $destino === '' ? '(vacío)' : $destino, $asunto, 'ERROR', 'Dirección de correo no válida.');
        return false;
    }
    if (!SGD_CORREO_ACTIVO) {
        sgd_correo_ultimo_error('El envío de correos está desactivado (SGD_CORREO_ACTIVO).');
        sgd_correo_registrar($tipo, $referencia, $destino, $asunto, 'DESACTIVADO');
        return false;
    }
    if (SGD_CORREO_AUTH && SGD_CORREO_CLAVE === '') {
        $msg = 'Falta la contraseña SMTP (crear conexion/correo_clave.php).';
        sgd_correo_ultimo_error($msg);
        sgd_correo_registrar($tipo, $referencia, $destino, $asunto, 'ERROR', $msg);
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SGD_CORREO_HOST;
        $mail->Port       = (int)SGD_CORREO_PUERTO;
        $mail->SMTPAuth   = (bool)SGD_CORREO_AUTH;
        $mail->Timeout    = (int)SGD_CORREO_TIMEOUT;
        if (!empty($GLOBALS['sgd_correo_debug'])) {
            // Solo herramientas/probar_correo.php: muestra el diálogo SMTP.
            // En el nivel 3 PHPMailer ya oculta usuario y contraseña ("[credentials hidden]").
            $mail->SMTPDebug   = 3;
            $enDatos = false;
            $mail->Debugoutput = function ($linea) use (&$enDatos) {
                $linea = rtrim($linea);
                if ($enDatos) {
                    if ($linea === 'CLIENT -> SERVER: .') {
                        $enDatos = false;
                        echo "CLIENT -> SERVER: [contenido del mensaje omitido]\n";
                    }
                    return;
                }
                if (strpos($linea, 'SERVER -> CLIENT: 354') === 0) {
                    $enDatos = true;
                }
                echo $linea . "\n";
            };
        }
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;
        $mail->Encoding   = PHPMailer::ENCODING_BASE64;
        if (SGD_CORREO_AUTH) {
            $mail->Username = SGD_CORREO_USUARIO;
            $mail->Password = SGD_CORREO_CLAVE;
        }
        if (SGD_CORREO_SEGURIDAD === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SGD_CORREO_SEGURIDAD === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure  = '';
            $mail->SMTPAutoTLS = false;
        }
        if (!SGD_CORREO_VERIFICAR_SSL) {
            $mail->SMTPOptions = array('ssl' => array(
                'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
            ));
        }

        $mail->setFrom(SGD_CORREO_DESDE, SGD_CORREO_NOMBRE);
        $responder = isset($opc['responder_a']) ? $opc['responder_a'] : SGD_CORREO_RESPONDER_A;
        if ($responder !== '' && filter_var($responder, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($responder);
        }
        $mail->addAddress($destino);

        $logo = __DIR__ . '/../' . SISGEDO_IMG_LOGO;
        if (is_file($logo)) {
            $mail->addEmbeddedImage($logo, 'logo', basename($logo));
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        $mail->AltBody = isset($opc['texto']) ? $opc['texto']
            : trim(html_entity_decode(strip_tags(preg_replace('~<(br|/p|/h1|/tr)\s*/?>~i', "\n", $html)), ENT_QUOTES, 'UTF-8'));

        $mail->send();
        sgd_correo_registrar($tipo, $referencia, $destino, $asunto, 'ENVIADO', null, $mail->getLastMessageID());
        return true;
    } catch (PHPMailerException $e) {
        $detalle = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
    } catch (Exception $e) {
        $detalle = $e->getMessage();
    }
    sgd_correo_ultimo_error($detalle);
    sgd_correo_registrar($tipo, $referencia, $destino, $asunto, 'ERROR', $detalle);
    return false;
}
