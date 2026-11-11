<?php
/* =====================================================================
   SISGEDO — Verificación pública de documentos (MGD)
   Permite a cualquier persona comprobar, con el Código de Verificación
   Digital (CVD) impreso en el cargo o documento (o leyendo su QR), que el
   documento fue registrado en el sistema, y comparar la huella SHA-256
   de su archivo con la registrada (integridad).
   ===================================================================== */
require_once './conexion/conexion.php';
require_once './clases/gestion_documental.php';

function hv($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function tam_legible($b)
{
  $b = (float)$b;
  if ($b >= 1048576) return number_format($b / 1048576, 2) . ' MB';
  if ($b >= 1024) return number_format($b / 1024, 1) . ' KB';
  return number_format($b) . ' bytes';
}

$cvdIn = isset($_GET['c']) ? trim((string)$_GET['c']) : '';
$cvd = sgd_normalizar_cvd($cvdIn);
$busco = ($cvdIn !== '');
$valido = false;
$tipo = null;
$doc = null;       // datos del documento
$archivo = null;   // metadatos del adjunto
$integro = null;   // true/false/null (sin huella)
$destinos = array();
$firmas = array();

if ($busco && strlen($cvd) === 8) {
  try {
    $db = sgd_db();
    $st = $db->prepare("SELECT tipo, referencia FROM sgd_verificacion WHERE cvd = ?");
    $st->execute(array($cvd));
    $ref = $st->fetch(PDO::FETCH_ASSOC);
    if ($ref) {
      $tipo = $ref['tipo'];
      if ($tipo === 'EXPEDIENTE') {
        $st = $db->prepare("SELECT d.nombre_documento, COALESCE(rd.numero_docu,''), rd.nro_exp,
                                   date_format(rd.fecha_registro, '%d/%m/%Y %h:%i %p'), rd.asunto, rd.folios,
                                   COALESCE(rd.recepcion_web,0), COALESCE(tt.nombre,''), rd.adjunto
                              FROM registro_documentos rd
                              LEFT JOIN documentos d ON rd.iddocumento = d.iddocumentos
                              LEFT JOIN tipo_tram tt ON rd.idtipo_tramite = tt.idtipo_tram
                             WHERE rd.nro_exp = ? ORDER BY rd.idregistro_documentos ASC LIMIT 1");
        $st->execute(array($ref['referencia']));
        $doc = $st->fetch(PDO::FETCH_NUM);
        if ($doc && !empty($doc[8])) {
          $archivo = sgd_obtener_archivo('REGISTRO', $doc[8]);
          $integro = sgd_verificar_integridad('REGISTRO', $doc[8]);
        }
      } else {
        $st = $db->prepare("SELECT d.nombre_documento, COALESCE(de.numero,''), de.iddocs_emitidos,
                                   date_format(de.fecha, '%d/%m/%Y %h:%i %p'), de.asunto,
                                   COALESCE(o.nombre,''), de.adjunto, COALESCE(od.nombre,'')
                              FROM docs_emitidos de
                              LEFT JOIN documentos d ON de.iddocumento = d.iddocumentos
                              LEFT JOIN oficinas o ON de.idoficina_registro = o.idoficinas
                              LEFT JOIN oficinas od ON de.idoficina = od.idoficinas
                             WHERE de.iddocs_emitidos = ?");
        $st->execute(array((int)$ref['referencia']));
        $doc = $st->fetch(PDO::FETCH_NUM);
        if ($doc) {
          if ($doc[7] !== '') $destinos[] = $doc[7];
          // Firmas digitales registradas (FIRMA PERÚ) del mismo envío
          if (!empty($doc[6])) {
            $stF = $db->prepare("SELECT f.tipo_firma, date_format(f.fecha_firma, '%d/%m/%Y %h:%i %p'),
                                        TRIM(CONCAT(COALESCE(p.nombres,''), ' ', COALESCE(p.apellidos,'')))
                                   FROM sgd_firma f
                                   INNER JOIN docs_emitidos g ON g.iddocs_emitidos = f.iddocs_emitidos
                                   LEFT JOIN personal p ON p.idpersonal = f.idpersonal
                                  WHERE f.estado = 'FIRMADO' AND g.adjunto = ? ORDER BY f.fecha_firma");
            $stF->execute(array($doc[6]));
            $firmas = $stF->fetchAll(PDO::FETCH_NUM);
          }
          if (!empty($doc[6])) {
            $archivo = sgd_obtener_archivo('EMITIDO', $doc[6]);
            $integro = sgd_verificar_integridad('EMITIDO', $doc[6]);
          }
        }
      }
      $valido = (bool)$doc;
    }
  } catch (Exception $e) {
    error_log('verificar.php: ' . $e->getMessage());
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title>Verificación de documentos — <?= hv(SISGEDO_ENTIDAD) ?></title>
  <link rel="icon" type="image/png" href="<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="css/publico.css" />
  <style>
    .cvd-in{text-transform:uppercase;letter-spacing:2px;font-family:Consolas,"Courier New",monospace;font-weight:700}
    .ok-box{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:10px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-size:13px;line-height:1.5}
    .ok-box i{font-size:22px;margin-top:1px}
    .hash{font-family:Consolas,"Courier New",monospace;font-size:11.5px;word-break:break-all;color:#334155;background:#f1f5f9;border-radius:6px;padding:6px 8px;display:block}
    .cmp{margin-top:12px;padding:12px;border:1px dashed #cbd5e1;border-radius:10px;font-size:13px;color:#475569}
    .cmp input[type=file]{margin-top:8px;max-width:100%}
    .res-ok{color:#047857;font-weight:700}
    .res-bad{color:#b91c1c;font-weight:700}
  </style>
</head>

<body>
  <div class="pub-hdr">
    <img src="<?= SISGEDO_IMG_LOGO ?>" alt="<?= hv(SISGEDO_ENTIDAD) ?>" />
    <div class="th">
      <div class="t1"><?= hv(SISGEDO_ENTIDAD_MAYUS) ?></div>
      <div class="t2">Verificación de Documentos</div>
    </div>
    <a class="enl" href="consulta_publico.php"><i class="fa fa-search"></i> Consulta tu expediente</a>
  </div>

  <div class="pub-main">
    <div class="pub-hero">
      <h1>Verifica un documento</h1>
      <p>Ingresa el código de verificación impreso en el cargo o documento (o escanea su código QR) para confirmar que
        fue registrado en el Sistema de Gestión Documental de la <?= hv(SISGEDO_ENTIDAD) ?>.</p>
    </div>

    <div class="pub-card">
      <form method="get" action="verificar.php" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1;min-width:220px">
          <label>Código de verificación</label>
          <input class="cvd-in" type="text" name="c" value="<?= hv($cvdIn) ?>" placeholder="XXXX-XXXX" maxlength="12"
            autocomplete="off" <?= $busco ? '' : 'autofocus' ?> />
        </div>
        <button class="btn btn-p" type="submit"><i class="fa fa-check-circle"></i> Verificar</button>
      </form>
    </div>

    <?php if ($busco && !$valido) { ?>
      <div class="pub-card">
        <div class="aviso">
          <i class="fa fa-exclamation-circle"></i>
          El código <b><?= hv($cvdIn) ?></b> no corresponde a ningún documento registrado.<br />
          Verifique que lo haya escrito correctamente (8 caracteres). Si el problema continúa, el documento podría no ser
          auténtico: comuníquese con la municipalidad.
        </div>
      </div>
    <?php } ?>

    <?php if ($valido) { ?>
      <div class="pub-card">
        <div class="ok-box">
          <i class="fa fa-shield"></i>
          <div><b>Documento válido.</b> El código <b><?= hv(sgd_formato_cvd($cvd)) ?></b> corresponde a un
            <?= $tipo === 'EXPEDIENTE' ? 'expediente recibido' : 'documento emitido' ?> por la <?= hv(SISGEDO_ENTIDAD) ?>
            y registrado en su Sistema de Gestión Documental.</div>
        </div>

        <dl class="exp-datos" style="margin-top:14px">
          <?php if ($tipo === 'EXPEDIENTE') { ?>
            <div><dt>Expediente N°</dt><dd><b><?= hv($doc[2]) ?></b></dd></div>
            <div><dt>Fecha y hora de recepción</dt><dd><?= hv($doc[3]) ?></dd></div>
            <div><dt>Documento</dt><dd><?= hv($doc[0]) ?><?= $doc[1] !== '' ? ' N° ' . hv($doc[1]) : '' ?></dd></div>
            <div><dt>Canal de recepción</dt><dd><?= intval($doc[6]) === 1 ? 'Mesa de Partes Virtual' : 'Mesa de Partes presencial' ?></dd></div>
            <div><dt>Tipo de trámite</dt><dd><?= hv($doc[7]) ?></dd></div>
            <div><dt>Folios</dt><dd><?= hv($doc[5]) ?></dd></div>
            <div style="grid-column:1/-1"><dt>Asunto</dt><dd><?= hv($doc[4]) ?></dd></div>
          <?php } else { ?>
            <div><dt>Documento</dt><dd><b><?= hv($doc[0]) ?><?= $doc[1] !== '' ? ' N° ' . hv($doc[1]) : '' ?></b></dd></div>
            <div><dt>Fecha de emisión</dt><dd><?= hv($doc[3]) ?></dd></div>
            <div><dt>Oficina emisora</dt><dd><?= hv($doc[5]) ?></dd></div>
            <div><dt>Destino</dt><dd><?= count($destinos) ? hv(implode(' / ', $destinos)) : '—' ?></dd></div>
            <div style="grid-column:1/-1"><dt>Asunto</dt><dd><?= hv($doc[4]) ?></dd></div>
            <div style="grid-column:1/-1"><dt>Firma digital</dt><dd>
              <?php if (count($firmas) === 0) { ?>Sin firma digital registrada
              <?php } else { foreach ($firmas as $fr) { ?>
                <div><span class="res-ok"><i class="fa fa-certificate"></i> <?= $fr[0] === 'FIRMA' ? 'Firmado' : 'Visto bueno' ?></span>
                  <?= $fr[2] !== '' ? ' por ' . hv($fr[2]) : '' ?> — <?= hv($fr[1]) ?></div>
              <?php } } ?>
            </dd></div>
          <?php } ?>
        </dl>
        <?php if ($tipo === 'EXPEDIENTE') { ?>
          <p style="margin-top:12px;font-size:13px"><a href="consulta_publico.php?num_exp=<?= rawurlencode($doc[2]) ?>" style="color:#2563eb"><i class="fa fa-map-o"></i> Ver el recorrido de este expediente</a></p>
        <?php } ?>
      </div>

      <div class="pub-card">
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin-bottom:10px"><i class="fa fa-file-text-o" style="margin-right:8px"></i>Archivo registrado</div>
        <?php if ($archivo) { ?>
          <dl class="exp-datos">
            <div><dt>Formato</dt><dd><?= hv(strtoupper($archivo['extension'])) ?><?= $archivo['mime'] ? ' (' . hv($archivo['mime']) . ')' : '' ?></dd></div>
            <div><dt>Tamaño</dt><dd><?= hv(tam_legible($archivo['tamano_bytes'])) ?></dd></div>
            <div><dt>Fecha de carga</dt><dd><?= hv(date('d/m/Y h:i A', strtotime($archivo['fecha_carga']))) ?></dd></div>
            <div><dt>Integridad en el servidor</dt><dd><?= $integro === true ? '<span class="res-ok"><i class="fa fa-check"></i> Sin alteraciones</span>' : ($integro === false ? '<span class="res-bad"><i class="fa fa-times"></i> No coincide</span>' : '—') ?></dd></div>
            <div style="grid-column:1/-1"><dt>Huella digital SHA-256</dt><dd><span class="hash" id="hashReg"><?= hv($archivo['hash_sha256']) ?></span></dd></div>
          </dl>
          <div class="cmp">
            <b>¿Tiene una copia del archivo?</b> Selecciónela para comprobar que es idéntica a la registrada. El archivo
            <b>no se envía</b> a ningún servidor: la comparación se hace en su propio equipo.
            <br /><input type="file" id="archivoCmp" />
            <div id="resCmp" style="margin-top:8px"></div>
          </div>
        <?php } else { ?>
          <p style="font-size:13px;color:#64748b">Este registro no tiene huella digital de archivo (documento sin adjunto o
            registrado antes de la implementación del control de integridad).</p>
        <?php } ?>
      </div>
    <?php } ?>

    <?php if (!$busco) { ?>
      <div class="pub-card">
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin-bottom:12px"><i class="fa fa-question-circle" style="margin-right:8px"></i>¿Qué es el código de verificación?</div>
        <p style="font-size:13px;color:#475569;line-height:1.6">Es un código único de 8 caracteres que el sistema asigna a cada
          expediente y documento. Aparece en el cargo de recepción junto a un código QR. Con él puede comprobar que el
          documento es auténtico y, si tiene una copia del archivo, que no fue modificado.</p>
      </div>
    <?php } ?>
  </div>

  <div class="pub-footer">
    <b><?= hv(SISGEDO_MUNI_SIGLAS) ?> — <?= hv(SISGEDO_ENTIDAD) ?></b> · Verificación de documentos © <?= date('Y') ?>
  </div>

  <script>
    (function () {
      var inp = document.getElementById('archivoCmp');
      if (!inp) return;
      var out = document.getElementById('resCmp');
      var esperado = (document.getElementById('hashReg').textContent || '').trim().toLowerCase();
      inp.addEventListener('change', function () {
        var f = inp.files && inp.files[0];
        if (!f) { out.innerHTML = ''; return; }
        if (!window.crypto || !window.crypto.subtle) {
          out.innerHTML = '<span class="res-bad">Su navegador no permite calcular la huella en esta conexión (se requiere HTTPS).</span>';
          return;
        }
        out.textContent = 'Calculando...';
        f.arrayBuffer().then(function (buf) {
          return window.crypto.subtle.digest('SHA-256', buf);
        }).then(function (h) {
          var hex = Array.prototype.map.call(new Uint8Array(h), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
          out.innerHTML = hex === esperado
            ? '<span class="res-ok"><i class="fa fa-check-circle"></i> El archivo es idéntico al registrado.</span>'
            : '<span class="res-bad"><i class="fa fa-times-circle"></i> El archivo NO coincide con el registrado (fue modificado o es otro archivo).</span><span class="hash" style="margin-top:6px">' + hex + '</span>';
        }).catch(function () {
          out.innerHTML = '<span class="res-bad">No se pudo leer el archivo.</span>';
        });
      });
    })();
  </script>
</body>

</html>
