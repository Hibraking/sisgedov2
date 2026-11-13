<?php
require_once './conexion/conexion.php';

if (!function_exists('hpub')) {
  function hpub($v)
  {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
  }
}

// Búsqueda por número de expediente (misma información que la consulta pública anterior)
$numExp = isset($_GET['num_exp']) ? trim($_GET['num_exp']) : '';
$busco = ($numExp !== '');

$cab = null;
$eventos = array();
$mpvEstado = null;      // Mesa de Partes Virtual: 'pendiente' | 'observado' | 'recepcionado' | 'derivado' | 'no_presentado'
$mpvEventos = array();  // historial MPV (observado, subsanado, recepcionado...)
$mpvObs = null;         // array(motivo, plazo) si está observado
$mpvOficial = null;     // fecha en que se considera presentado (si se envió fuera de horario)
if ($busco) {
  try {
    $pdo = (new Conexion())->conectar();

    // Cabecera del expediente (equivalente a datos_registro)
    $st = $pdo->prepare("select documentos.nombre_documento, COALESCE(rd.numero_docu,''),
                                        concat(remitente.apellidos, ' ', remitente.nombres),
                                        tipo_tram.nombre, rd.asunto,
                                        date_format(adddate(rd.fecha_registro, interval 0 hour), '%d/%m/%Y %h:%i %p'),
                                        rd.idregistro_documentos, COALESCE(rd.recepcion_web, 0)
                                 from registro_documentos rd
                                 inner join documentos on rd.iddocumento=documentos.iddocumentos
                                 inner join remitente on rd.idremitente=remitente.idremitente
                                 inner join tipo_tram on rd.idtipo_tramite=tipo_tram.idtipo_tram
                                 where rd.nro_exp=? order by rd.idregistro_documentos asc");
    $st->execute(array($numExp));
    $regs = $st->fetchAll(PDO::FETCH_NUM);

    if (count($regs) > 0) {
      $cab = $regs[0];
      // Mesa de Partes Virtual: ¿ya lo recepcionó la mesa de partes? ¿ya se derivó?
      require_once __DIR__ . '/clases/mpv_util.php';
      if (intval($cab[7]) === 1 && sgd_mpv_ok($pdo)) {
        require_once __DIR__ . '/clases/calendario.php';
        $stm = $pdo->prepare("select rd.mpv_recepcion, rd.idoficina, rd.mpv_estado, rd.mpv_plazo, rd.fecha_registro
                                from registro_documentos rd where rd.idregistro_documentos=?");
        $stm->execute(array(intval($cab[6])));
        $mpvInfo = $stm->fetch(PDO::FETCH_NUM);
        if ($mpvInfo) {
          if ($mpvInfo[2] === 'NO_PRESENTADO') {
            $mpvEstado = 'no_presentado';
          } elseif ($mpvInfo[2] === 'OBSERVADO') {
            $mpvEstado = 'observado';
          } else {
            $mpvEstado = $mpvInfo[0] === null ? 'pendiente' : ($mpvInfo[1] === null ? 'recepcionado' : 'derivado');
          }
          if (sgd_cal_fuera_de_horario($mpvInfo[4])) {
            $mpvOficial = sgd_cal_fecha_presentacion($mpvInfo[4]);
          }
          $ste = $pdo->prepare("select tipo, coalesce(detalle,''), plazo, fecha from sgd_mpv_evento
                                 where idregistro_documentos=? order by fecha asc, idevento asc");
          $ste->execute(array(intval($cab[6])));
          $mpvEventos = $ste->fetchAll(PDO::FETCH_NUM);
          if ($mpvEstado === 'observado') {
            $motivo = '';
            foreach ($mpvEventos as $ev) {
              if ($ev[0] === 'OBSERVADO') $motivo = $ev[1];
            }
            $mpvObs = array($motivo, $mpvInfo[3]);
          }
        }
      }
      $ids = array();
      foreach ($regs as $rg) {
        $ids[] = intval($rg[6]);
      }
      $marcas = implode(',', array_fill(0, count($ids), '?'));

      // Recorrido (equivalente a seguimiento): oficina, acción, estado y fecha de cada movimiento
      $st = $pdo->prepare("select date_format(adddate(m.fecha_hora, interval 0 hour), '%d/%m/%Y'),
                                            date_format(adddate(m.fecha_hora, interval 0 hour), '%h:%i %p'),
                                            coalesce(ofi.nombre, ofid.nombre, ''),
                                            coalesce(acc.nombre_accion, '--'),
                                            coalesce(ed.nombre, '--'),
                                            case when m.estado = 0 then 1 else 0 end
                                     from movimientos m
                                     left join responsables r on m.idresponsable=r.idresponsables
                                     left join oficinas ofi on r.idoficina=ofi.idoficinas
                                     left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                                     left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                                     left join estado_documento ed on m.idestadodocs=ed.idestado_documento
                                     where m.idregdocs in ($marcas)
                                     order by m.fecha_hora asc, m.idMovimientos asc");
      $st->execute($ids);
      $eventos = $st->fetchAll(PDO::FETCH_NUM);
    }
  } catch (Exception $e) {
    error_log('consulta_publico: ' . $e->getMessage());
  }
}

// Estado y ubicación actual (último evento)
$estadoActual = 'En mesa de partes';
$oficinaActual = 'Mesa de Partes';
$enTramite = true;
$mpvEstadosTexto = array(
  'pendiente' => 'En revisión de mesa de partes',
  'observado' => 'Observado: debe subsanar',
  'recepcionado' => 'Recepcionado por mesa de partes',
  'no_presentado' => 'No presentado',
);
if (count($eventos) === 0 && $mpvEstado !== null && isset($mpvEstadosTexto[$mpvEstado])) {
  $estadoActual = $mpvEstadosTexto[$mpvEstado];
}
if (count($eventos) > 0) {
  $ult = $eventos[count($eventos) - 1];
  $estadoActual = $ult[4] !== '--' ? $ult[4] : 'En trámite';
  $oficinaActual = $ult[2] !== '' ? $ult[2] : $oficinaActual;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Consulta tu Expediente — <?= hpub(SISGEDO_ENTIDAD) ?></title>
  <link rel="icon" type="image/png" href="<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="css/publico.css?v=<?= @filemtime(__DIR__ . '/css/publico.css') ?>" />
</head>

<body>
  <!-- CABECERA MUNICIPAL -->
  <div class="pub-hdr">
    <img src="<?= SISGEDO_IMG_LOGO ?>" alt="<?= hpub(SISGEDO_ENTIDAD) ?>" />
    <div class="th">
      <div class="t1"><?= hpub(SISGEDO_ENTIDAD_MAYUS) ?></div>
      <div class="t2">Consulta de Trámite Documentario</div>
    </div>
    <a class="enl" href="mpvirtual.php"><i class="fa fa-send-o"></i> Mesa de Partes Virtual</a>
  </div>

  <div class="pub-main">
    <div class="pub-hero">
      <h1>Consulta tu Expediente</h1>
      <p>Ingresa el número de expediente que recibiste al presentar tu documento y conoce en qué oficina se encuentra tu
        trámite.</p>
    </div>

    <!-- BUSCADOR -->
    <div class="pub-card">
      <form method="get" action="consulta_publico.php"
        style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1;min-width:220px">
          <label>Número de expediente</label>
          <input type="text" name="num_exp" value="<?= hpub($numExp) ?>" placeholder="Ej. 20263304" inputmode="numeric"
            autocomplete="off" autofocus />
        </div>
        <button class="btn btn-p" type="submit"><i class="fa fa-search"></i> Consultar</button>
      </form>
    </div>

    <?php if ($busco && $cab === null) { ?>
      <!-- NO ENCONTRADO -->
      <div class="pub-card">
        <div class="aviso">
          <i class="fa fa-exclamation-circle"></i>
          No encontramos ningún expediente con el número <b><?= hpub($numExp) ?></b>.<br />
          Verifica el número en tu constancia. Si el problema continúa, acércate a mesa de partes o llama a la
          municipalidad.
        </div>
      </div>
    <?php } ?>

    <?php if ($cab !== null) { ?>
      <!-- CABECERA DEL EXPEDIENTE -->
      <div class="pub-card">
        <div class="exp-head">
          <span class="di"><i class="fa fa-folder-open-o"></i></span>
          <div style="flex:1;min-width:0">
            <div class="dt">Expediente N° <?= hpub($numExp) ?></div>
            <div class="ds"><?= hpub($cab[0]) ?><?= $cab[1] !== '' ? ' N° ' . hpub($cab[1]) : '' ?> — presentado el
              <?= hpub($cab[5]) ?></div>
          </div>
          <span class="badge <?= count($eventos) > 0 ? 'azul' : 'ambar' ?>"><?= hpub(strtoupper($estadoActual)) ?></span>
        </div>
        <dl class="exp-datos">
          <div>
            <dt>Remitente</dt>
            <dd><?= hpub($cab[2]) ?></dd>
          </div>
          <div>
            <dt>Tipo de trámite</dt>
            <dd><?= hpub($cab[3]) ?></dd>
          </div>
          <div>
            <dt>Se encuentra en</dt>
            <dd><b><?= hpub($oficinaActual) ?></b></dd>
          </div>
          <div>
            <dt>Movimientos</dt>
            <dd><?= number_format(count($eventos) + 1) ?></dd>
          </div>
          <div style="grid-column:1/-1">
            <dt>Asunto</dt>
            <dd><?= hpub($cab[4]) ?></dd>
          </div>
        </dl>
      </div>

      <!-- RECORRIDO -->
      <div class="pub-card">
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin-bottom:4px"><i class="fa fa-map-o"
            style="margin-right:8px"></i>Recorrido de tu trámite</div>
        <div class="linea">
          <div class="evt">
            <div class="ehead">
              <span class="eofi"><?= intval($cab[7]) === 1 ? 'Mesa de Partes Virtual' : 'Mesa de Partes' ?></span>
              <span class="efecha"><i class="fa fa-calendar"></i> <?= hpub($cab[5]) ?></span>
            </div>
            <div class="edet"><span><i class="fa fa-flag-o"></i>Documento
                <?= intval($cab[7]) === 1 ? 'presentado por internet' : 'registrado en ventanilla' ?></span>
              <?php if ($mpvOficial) { ?><span><i class="fa fa-moon-o"></i>Enviado fuera del horario de atención: se considera presentado el <?= hpub(sgd_cal_texto($mpvOficial)) ?></span><?php } ?>
            </div>
          </div>
          <?php
          // Historial de la Mesa de Partes Virtual (observaciones, subsanación, recepción...)
          $mpvTitulos = array(
            'RECEPCIONADO' => array('Recepcionado por mesa de partes', 'fa-check'),
            'OBSERVADO' => array('Observado por mesa de partes', 'fa-exclamation-circle'),
            'SUBSANADO' => array('Documento corregido enviado', 'fa-refresh'),
            'NO_PRESENTADO' => array('Documento no presentado', 'fa-ban'),
            'DERIVADO' => array('Derivado', 'fa-share'),
          );
          $mpvUltimo = count($mpvEventos) - 1;
          foreach ($mpvEventos as $j => $me) {
            $tt = isset($mpvTitulos[$me[0]]) ? $mpvTitulos[$me[0]] : array($me[0], 'fa-circle');
            $esActualMpv = count($eventos) === 0 && $j === $mpvUltimo && in_array($mpvEstado, array('observado', 'no_presentado'), true); ?>
            <div class="evt <?= $esActualMpv ? 'actual' : '' ?>">
              <div class="ehead">
                <span class="eofi"><?= $me[0] === 'SUBSANADO' ? 'Ciudadano' : 'Mesa de Partes' ?></span>
                <span class="efecha"><i class="fa fa-calendar"></i> <?= hpub(date('d/m/Y · h:i A', strtotime($me[3]))) ?></span>
              </div>
              <div class="edet">
                <span><i class="fa <?= $tt[1] ?>"></i><?= hpub($tt[0]) ?><?= $me[0] === 'DERIVADO' && $me[1] !== '' ? ' a ' . hpub($me[1]) : '' ?></span>
                <?php if ($me[0] === 'OBSERVADO' && $me[1] !== '') { ?><span>Motivo: <?= hpub($me[1]) ?></span><?php } ?>
              </div>
            </div>
          <?php } ?>
          <?php foreach ($eventos as $i => $ev) {
            $esUltimo = ($i === count($eventos) - 1); ?>
            <div class="evt <?= $esUltimo ? 'actual' : '' ?>">
              <div class="ehead">
                <span class="eofi"><?= $ev[2] !== '' ? hpub($ev[2]) : 'Oficina de la entidad' ?></span>
                <span class="efecha"><i class="fa fa-calendar"></i> <?= hpub($ev[0]) ?> · <?= hpub($ev[1]) ?></span>
              </div>
              <div class="edet">
                <span><i class="fa fa-paper-plane"></i>Acción: <?= hpub($ev[3]) ?></span>
                <span><i class="fa fa-flag-o"></i>Estado: <?= hpub($ev[4]) ?></span>
                <?php if ($esUltimo) { ?><span class="badge verde">UBICACIÓN ACTUAL</span><?php } ?>
              </div>
            </div>
          <?php } ?>
          <?php if (count($eventos) === 0 && $mpvEstado === 'observado' && $mpvObs) { ?>
            <div class="evt actual">
              <div class="ehead"><span class="eofi">Debes subsanar tu documento</span></div>
              <div class="edet"><span><i class="fa fa-envelope-o"></i>Te enviamos un correo con la observación y un enlace para enviar el documento
                  corregido. Plazo: hasta el <b><?= hpub(sgd_cal_texto(sgd_cal_cierre($mpvObs[1]))) ?></b>. Si no lo subsanas a tiempo,
                  se considerará no presentado.</span></div>
            </div>
          <?php } elseif (count($eventos) === 0 && $mpvEstado === 'no_presentado') { ?>
            <div class="evt actual">
              <div class="ehead"><span class="eofi">Trámite concluido: no presentado</span></div>
              <div class="edet"><span><i class="fa fa-info-circle"></i>La observación no se subsanó dentro del plazo. Si aún deseas realizar el trámite,
                  preséntalo nuevamente en la <a href="mpvirtual.php">Mesa de Partes Virtual</a>.</span></div>
            </div>
          <?php } elseif (count($eventos) === 0) { ?>
            <div class="evt actual">
              <?php if ($mpvEstado === 'pendiente') { ?>
                <div class="ehead"><span class="eofi">En revisión de mesa de partes</span></div>
                <div class="edet"><span><i class="fa fa-clock-o"></i>Tu documento llegó correctamente. Mesa de partes lo revisará,
                    lo recepcionará y lo derivará al área competente. Te avisaremos por correo.</span></div>
              <?php } elseif ($mpvEstado === 'recepcionado') { ?>
                <div class="ehead"><span class="eofi">Por derivar al área competente</span></div>
                <div class="edet"><span><i class="fa fa-clock-o"></i>Mesa de partes recepcionó tu documento y lo derivará
                    al área que debe atenderlo.</span></div>
              <?php } else { ?>
                <div class="ehead"><span class="eofi">En espera de recepción</span></div>
                <div class="edet"><span><i class="fa fa-clock-o"></i>Tu documento fue registrado y está pendiente de ser
                    recepcionado por la oficina destino.</span></div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </div>
    <?php } ?>

    <?php if (!$busco) { ?>
      <!-- AYUDA -->
      <div class="pub-card">
        <div style="font-size:14px;font-weight:700;color:#123a7a;margin-bottom:12px"><i class="fa fa-question-circle"
            style="margin-right:8px"></i>¿Cómo funciona?</div>
        <div class="fgrid">
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">1.</span><span
              style="font-size:13px;color:#475569">Presenta tu documento en la <a href="mpvirtual.php"
                style="color:#2563eb">Mesa de Partes Virtual</a> o de forma presencial.</span></div>
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">2.</span><span
              style="font-size:13px;color:#475569">Recibirás un <b>número de expediente</b> como constancia (también llega
              a tu correo).</span></div>
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">3.</span><span
              style="font-size:13px;color:#475569">Ingresa ese número aquí y verás cada oficina por la que pasa tu
              trámite.</span></div>
          <div style="display:flex;gap:10px"><span style="color:#2563eb;font-weight:700">4.</span><span
              style="font-size:13px;color:#475569">La etiqueta <b>Ubicación actual</b> te indica dónde está tu documento
              hoy.</span></div>
        </div>
      </div>
    <?php } ?>
  </div>

  <div class="pub-footer">
    <b><?= hpub(SISGEDO_MUNI_SIGLAS) ?> — <?= hpub(SISGEDO_ENTIDAD) ?></b> · Consulta de Trámite Documentario © <?= date('Y') ?>
  </div>
</body>

</html>