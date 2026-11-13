<?php
/* =====================================================================
   SISGEDO — Subsanar un documento OBSERVADO (página pública)
   ---------------------------------------------------------------------
   El ciudadano llega desde el botón "Subsanar mi documento" del correo de
   observación (subsanar.php?t=<clave>). Sube el documento corregido y una
   nota; el expediente vuelve a la bandeja "Por recepcionar" de la Mesa de
   Partes Virtual como SUBSANADO. El enlace se puede usar UNA sola vez y
   solo dentro del plazo (Ley 27444, art. 136).
   ===================================================================== */
require_once './conexion/conexion.php';   // configura la sesión; csrf_*() la inicia
require_once './clases/seguridad.php';
require_once './clases/mesa_partes_virtual.php';

function hs($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$token = isset($_REQUEST['t']) ? trim((string)$_REQUEST['t']) : '';
$mpv = new MesaPartesVirtual();
$doc = $mpv->buscar_por_token($token);
$error = '';
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validar()) {
    $error = 'La página caducó. Vuelva a abrir el enlace del correo e intente de nuevo.';
  } else {
    $r = $mpv->subsanar($token, isset($_FILES['archivo']) ? $_FILES['archivo'] : null, isset($_POST['nota']) ? $_POST['nota'] : '');
    if ($r['ok']) {
      $exito = $r;
      $doc = null;
    } else {
      $error = $r['msg'];
      $doc = $mpv->buscar_por_token($token);
    }
  }
}
$vigente = $doc ? MesaPartesVirtual::plazo_vigente($doc[19]) : false;
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>Subsanar documento — <?= hs(SISGEDO_ENTIDAD) ?></title>
  <link rel="icon" type="image/png" href="<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="css/publico.css" />
  <style>
    .obs { background: #fff7ed; border-left: 4px solid #f97316; border-radius: 6px; padding: 12px 14px; color: #7c2d12; line-height: 1.55; }
    .ok-box { text-align: center; padding: 10px 0; }
    .ok-box .fa { font-size: 44px; color: #16a34a; }
    .datos { display: grid; grid-template-columns: 160px 1fr; gap: 6px 12px; font-size: 14px; margin: 12px 0; }
    .datos dt { color: #64748b; }
    .datos dd { margin: 0; color: #1e293b; }
    .plazo { font-weight: 700; color: #c2410c; }
    .err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; }
    @media (max-width: 600px) { .datos { grid-template-columns: 1fr; } }
  </style>
</head>

<body>
  <div class="pub-hdr">
    <img src="<?= SISGEDO_IMG_LOGO ?>" alt="<?= hs(SISGEDO_ENTIDAD) ?>" />
    <div class="th">
      <div class="t1"><?= hs(SISGEDO_ENTIDAD_MAYUS) ?></div>
      <div class="t2">Mesa de Partes Virtual — Subsanación</div>
    </div>
    <a class="enl" href="consulta_publico.php"><i class="fa fa-search"></i> Consultar expediente</a>
  </div>

  <div class="pub-main">
    <div class="pub-hero">
      <h1>Subsanar documento observado</h1>
      <p>Adjunte el documento corregido. Mantendrá el mismo número de expediente y la fecha de presentación original.</p>
    </div>

    <?php if ($exito) { ?>
      <div class="pub-card ok-box">
        <i class="fa fa-check-circle"></i>
        <h2 style="margin:10px 0 6px">¡Documento corregido enviado!</h2>
        <p>Expediente N° <b><?= hs($exito['exp']) ?></b>. Mesa de partes lo revisará nuevamente y le avisaremos por correo cuando sea recepcionado.</p>
        <p><a class="btn btn-p" href="consulta_publico.php?num_exp=<?= hs(rawurlencode($exito['exp'])) ?>"><i class="fa fa-eye"></i> Ver el estado de mi trámite</a></p>
      </div>
    <?php } elseif (!$doc) { ?>
      <div class="pub-card">
        <div class="aviso"><i class="fa fa-exclamation-circle"></i>
          <?= $error !== '' ? hs($error) . '<br>' : '' ?>
          Este enlace no es válido o ya fue usado. Si ya envió su documento corregido, puede ver el estado de su trámite en
          <a href="consulta_publico.php">Consultar expediente</a>.
        </div>
      </div>
    <?php } else { ?>
      <div class="pub-card">
        <dl class="datos">
          <dt>Expediente N°</dt><dd><b><?= hs($doc[1]) ?></b></dd>
          <dt>Documento</dt><dd><?= hs(trim($doc[2] . ' ' . $doc[3])) ?></dd>
          <dt>Asunto</dt><dd><?= hs($doc[9]) ?></dd>
          <dt>Plazo para subsanar</dt><dd class="plazo">hasta el <?= hs(sgd_cal_texto(sgd_cal_cierre($doc[19]))) ?></dd>
        </dl>
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin:14px 0 6px">Observación de mesa de partes</div>
        <div class="obs"><?= nl2br(hs($doc[20])) ?></div>
      </div>

      <?php if (!$vigente) { ?>
        <div class="pub-card">
          <div class="aviso"><i class="fa fa-clock-o"></i>
            El plazo para subsanar venció el <b><?= hs(sgd_cal_texto(sgd_cal_cierre($doc[19]))) ?></b>.
            El documento se considerará no presentado. Si aún desea realizar el trámite, preséntelo nuevamente por la
            <a href="mpvirtual.php">Mesa de Partes Virtual</a>.
          </div>
        </div>
      <?php } else { ?>
        <div class="pub-card">
          <?php if ($error !== '') { ?><div class="err"><i class="fa fa-exclamation-triangle"></i> <?= hs($error) ?></div><?php } ?>
          <form method="post" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<i class=\'fa fa-spinner fa-spin\'></i> Enviando...';">
            <input type="hidden" name="t" value="<?= hs($token) ?>" />
            <input type="hidden" name="_csrf" value="<?= hs(csrf_token()) ?>" />
            <div style="margin-bottom:12px">
              <label>Documento corregido <span style="color:#dc2626">*</span></label>
              <input type="file" name="archivo" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar" />
              <div style="font-size:12px;color:#64748b;margin-top:4px">PDF de preferencia. Máximo 15 MB. Si son varias hojas o anexos, júntelos en un solo PDF.</div>
            </div>
            <div style="margin-bottom:14px">
              <label>Nota para mesa de partes (opcional)</label>
              <textarea name="nota" rows="3" maxlength="1000" placeholder="Ej.: Adjunto el documento firmado y con los 5 folios completos."><?= hs(isset($_POST['nota']) ? $_POST['nota'] : '') ?></textarea>
            </div>
            <button class="btn btn-p" type="submit"><i class="fa fa-paper-plane"></i> Enviar documento corregido</button>
          </form>
        </div>
      <?php } ?>
    <?php } ?>
  </div>

  <div class="pub-footer">
    <b><?= hs(SISGEDO_MUNI_SIGLAS) ?> — <?= hs(SISGEDO_ENTIDAD) ?></b> · Mesa de Partes Virtual © <?= date('Y') ?>
  </div>
</body>

</html>
<?php
// Aviso a la bandeja de mesa de partes (con el archivo corregido), DESPUÉS de
// entregar la página al ciudadano para no hacerlo esperar.
if ($exito) {
  ignore_user_abort(true);
  if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
  } elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
  }
  $mpv->aviso_subsanacion($exito['id'], $exito['archivo'], $exito['nombre_original'], $exito['nota']);
}
