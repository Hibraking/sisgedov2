<?php
session_start();
require_once '../clases/guardia.php';
$_SESSION["modulo"] = "1";
if (!isset($_SESSION['alias'])) {
  header("Location: ../login.php");
  exit;
}
require_once '../conexion/conexion.php';
require_once '../clases/seguridad.php';
require_once '../clases/mesa_partes_virtual.php';

$alias = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';

// ---- Bandeja y filtros ----
$bandejas = array(
  'pendientes'  => array('Por recepcionar', 'fa-inbox'),
  'observados'  => array('Observados', 'fa-exclamation-circle'),
  'por_derivar' => array('Por derivar', 'fa-share'),
  'derivados'   => array('Historial', 'fa-history'),
);
$bandeja = isset($_GET['b']) && isset($bandejas[$_GET['b']]) ? $_GET['b'] : 'pendientes';
$qFiltro = isset($_GET['q']) ? trim($_GET['q']) : '';
$desdeFiltro = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hastaFiltro = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$hoy = date('Y-m-d');

$mpv = new MesaPartesVirtual();
$migracionOk = sgd_mpv_ok();
$datos = $mpv->lista($bandeja, $qFiltro, $desdeFiltro, $hastaFiltro);
$cont = $mpv->contadores();
$catOficinas = $mpv->oficinas();
$tok = csrf_token();

/** "hace 3 h", "hace 2 días"... */
function mpv_hace($fecha)
{
  $s = time() - strtotime((string)$fecha);
  if ($s < 3600) return 'hace ' . max(1, (int)floor($s / 60)) . ' min';
  if ($s < 86400) return 'hace ' . (int)floor($s / 3600) . ' h';
  $d = (int)floor($s / 86400);
  return 'hace ' . $d . ($d === 1 ? ' día' : ' días');
}
function mpv_url($b, $extra = array())
{
  $p = array_merge(array('b' => $b), $extra);
  foreach ($p as $k => $v) {
    if ($v === '' || ($k === 'b' && $v === 'pendientes')) unset($p[$k]);
  }
  $qs = http_build_query($p);
  return 'v_mesa_partes_virtual.php' . ($qs !== '' ? '?' . $qs : '');
}
function mpv_fh($f)
{
  return $f ? date('d/m/Y h:i a', strtotime($f)) : '';
}
/** Datos derivados de cada fila para la vista. */
function mpv_fila($r)
{
  $presOficial = sgd_cal_fecha_presentacion($r[11]);
  $fuera = strtotime($presOficial) !== strtotime($r[11]);
  $plazo = $r[19];
  $cierre = $plazo ? sgd_cal_cierre($plazo) : null;
  $vencido = $cierre && time() > strtotime($cierre);
  $porVencer = $cierre && !$vencido && date('Y-m-d') === $plazo;
  return array(
    'adj' => $r[12] !== '' ? '../docs adjs reg_doc/' . $r[12] : '',
    'presentado' => mpv_fh($r[11]),
    'oficial' => $fuera ? sgd_cal_texto($presOficial) : '',
    'recep' => mpv_fh($r[13]),
    'estado' => $r[18],
    'plazo' => $cierre ? sgd_cal_texto($cierre) : '',
    'vencido' => $vencido,
    'por_vencer' => $porVencer,
  );
}
$first = isset($datos[0]) ? $datos[0] : null;
$firstF = $first ? mpv_fila($first) : null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mesa de Partes Virtual — SISGEDO</title>
  <link rel="icon" type="image/png" href="../<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/v_mesa_partes_virtual.css?v=<?= @filemtime(__DIR__ . '/../css/v_mesa_partes_virtual.css') ?>" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body data-csrf="<?= h($tok) ?>" data-bandeja="<?= h($bandeja) ?>" data-plazo-dias="<?= (int)SISGEDO_PLAZO_SUBSANAR ?>"
  data-plazo-obs="<?= h(sgd_cal_texto(sgd_cal_cierre(sgd_cal_sumar_habiles(date('Y-m-d'), (int)SISGEDO_PLAZO_SUBSANAR)))) ?>">
  <div class="app">
    <div class="hdr">
      <div class="l">
        <button class="ib"><i class="fa fa-bars"></i></button>
        <div class="brand"><i class="fa fa-leaf"></i> SISGEDO</div>
        <span class="sub">Sistema de Gestión Documentario</span>
      </div>
      <div class="r">
        <div class="usr">
          <span class="av"><i class="fa fa-user"></i></span>
          <div>
            <div class="wl">Bienvenido,</div>
            <div class="nm"><?= h($alias) ?></div>
          </div>
          <a href="../clases/cerrarsesion.php" class="ib" title="Salir"><i class="fa fa-sign-out"></i></a>
        </div>
      </div>
    </div>

    <div class="body">
      <?php $NAV_ACTIVO = 'vistas/v_mesa_partes_virtual.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main">
        <h1 class="t">Mesa de Partes Virtual</h1>
        <div class="subt">Documentos que los ciudadanos presentan por internet. Horario de atención: <?= h(SISGEDO_HORARIO_TEXTO) ?> — lo enviado fuera de ese horario se considera presentado el siguiente día hábil.</div>

        <?php if (!$migracionOk) { ?>
          <div class="mpv-aviso mpv-aviso-err"><i class="fa fa-exclamation-triangle"></i> Falta ejecutar en la base de datos las migraciones <strong>007_mesa_partes_virtual.sql</strong> y <strong>008_mpv_observar_horario.sql</strong>. Hasta entonces este módulo no muestra documentos.</div>
        <?php } ?>

        <!-- Cómo funciona (ayuda breve) -->
        <div class="mpv-pasos">
          <div class="paso"><span class="n">1</span>
            <div><strong>Revise</strong><br>Abra el documento. Si está conforme, pulse <em>Recepcionar</em>. Si tiene un problema (ilegible, sin firma, incompleto), pulse <em>Observar</em> y explique qué corregir.</div>
          </div>
          <i class="fa fa-angle-right flecha"></i>
          <div class="paso"><span class="n">2</span>
            <div><strong>Observados</strong><br>El ciudadano tiene <?= (int)SISGEDO_PLAZO_SUBSANAR ?> días hábiles para corregirlo desde el enlace de su correo; al hacerlo vuelve a <em>Por recepcionar</em>.</div>
          </div>
          <i class="fa fa-angle-right flecha"></i>
          <div class="paso"><span class="n">3</span>
            <div><strong>Derive</strong><br>En <em>Por derivar</em>, elija la oficina que debe atenderlo. Al ciudadano se le avisa por correo en cada paso.</div>
          </div>
        </div>

        <div class="kpis">
          <?php
          $cards = array(
            array('Por recepcionar', $cont['pendientes'], 'Esperan revisión', 'fa-inbox', 't-orange', '#f97316', 'lab-orange'),
            array('Observados', $cont['observados'], $cont['vencidos'] > 0 ? $cont['vencidos'] . ' con plazo vencido' : 'Esperan subsanación', 'fa-exclamation-circle', 't-orange', $cont['vencidos'] > 0 ? '#dc2626' : '#b45309', 'lab-orange'),
            array('Por derivar', $cont['por_derivar'], 'Recepcionados sin oficina', 'fa-share', 't-purple', '#6d28d9', 'lab-purple'),
            array('Este mes', $cont['mes'], 'Llegaron hoy: ' . $cont['hoy'], 'fa-globe', 't-green', '#16a34a', 'lab-green'),
          );
          foreach ($cards as $c) { ?>
            <div class="kpi">
              <div class="ic <?= $c[4] ?>"><i class="fa <?= $c[3] ?>"></i></div>
              <div>
                <div class="lab <?= $c[6] ?>"><?= $c[0] ?></div>
                <div class="num" style="color:<?= $c[5] ?>"><?= number_format($c[1]) ?></div>
                <div class="ss"><?= h($c[2]) ?></div>
              </div>
            </div>
          <?php } ?>
        </div>

        <!-- Pestañas -->
        <div class="mpv-tabs">
          <?php foreach ($bandejas as $k => $b) {
            $n = isset(array('pendientes' => 1, 'observados' => 1, 'por_derivar' => 1)[$k]) ? $cont[$k] : null;
            $alerta = ($k === 'observados') ? $cont['vencidos'] > 0 : ($n > 0); ?>
            <a class="mpv-tab <?= $k === $bandeja ? 'on' : '' ?>" href="<?= h(mpv_url($k)) ?>"><i class="fa <?= $b[1] ?>"></i> <?= h($b[0]) ?><?php if ($n !== null) { ?> <span class="cnt <?= $alerta ? ($k === 'observados' ? 'cnt-rojo' : 'cnt-on') : '' ?>"><?= (int)$n ?></span><?php } ?></a>
          <?php } ?>
        </div>

        <!-- Filtros -->
        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" value="<?= h($qFiltro) ?>" placeholder="Buscar por expediente, remitente, DNI o asunto..." /><i class="fa fa-search"></i></div>
            </div>
            <?php if ($bandeja === 'derivados') { ?>
              <div class="fld"><label>Desde</label>
                <div class="inp"><input id="desde" type="date" max="<?= h($hoy) ?>" value="<?= h($desdeFiltro) ?>" /></div>
              </div>
              <div class="fld"><label>Hasta</label>
                <div class="inp"><input id="hasta" type="date" max="<?= h($hoy) ?>" value="<?= h($hastaFiltro) ?>" /></div>
              </div>
            <?php } ?>
            <div class="field-actions">
              <button type="button" class="btn" onclick="location.href='<?= h(mpv_url($bandeja)) ?>'"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <!-- Lista -->
        <div class="card table-card">
          <div class="rowg thead">
            <span>Expediente</span><span>Documento</span><span>Remitente</span><span>Asunto</span>
            <span><?= array('pendientes' => 'Presentado', 'observados' => 'Plazo para subsanar', 'por_derivar' => 'Recepcionado', 'derivados' => 'Resultado')[$bandeja] ?></span><span>Acciones</span>
          </div>
          <div id="tbody">
            <?php if (count($datos) === 0) { ?>
              <div class="mpv-vacio">
                <?php if ($bandeja === 'pendientes') { ?><i class="fa fa-check-circle"></i><br>No hay documentos por recepcionar. ¡Todo al día!
                <?php } elseif ($bandeja === 'observados') { ?><i class="fa fa-check-circle"></i><br>No hay documentos observados esperando subsanación.
                <?php } elseif ($bandeja === 'por_derivar') { ?><i class="fa fa-check-circle"></i><br>No hay documentos recepcionados pendientes de derivar.
                <?php } else { ?>No hay documentos en el rango seleccionado (por defecto, últimos 30 días).<?php } ?>
              </div>
            <?php }
            foreach ($datos as $i => $r) {
              $f = mpv_fila($r);
              $espera = $bandeja === 'pendientes' && (time() - strtotime($r[11])) > 86400;
              $clase = $espera ? ' row-espera' : '';
              if ($bandeja === 'observados') $clase = $f['vencido'] ? ' row-vencido' : ($f['por_vencer'] ? ' row-espera' : '');
            ?>
              <div class="row rowg <?= $i === 0 ? 'sel' : '' ?><?= $clase ?>"
                data-id="<?= h($r[0]) ?>" data-exp="<?= h($r[1]) ?>" data-doc="<?= h(trim($r[2] . ' ' . $r[3])) ?>"
                data-remit="<?= h($r[4]) ?>" data-dni="<?= h($r[5]) ?>" data-email="<?= h($r[6]) ?>" data-tel="<?= h($r[7]) ?>"
                data-dir="<?= h($r[17]) ?>" data-tram="<?= h($r[8]) ?>" data-asunto="<?= h($r[9]) ?>" data-folios="<?= h($r[10]) ?>"
                data-presentado="<?= h($f['presentado']) ?>" data-oficial="<?= h($f['oficial']) ?>" data-recep="<?= h($f['recep']) ?>"
                data-recepquien="<?= h($r[14]) ?>" data-destino="<?= h($r[15]) ?>" data-adj="<?= h($f['adj']) ?>"
                data-estado="<?= h($f['estado']) ?>" data-plazo="<?= h($f['plazo']) ?>" data-vencido="<?= $f['vencido'] ? '1' : '0' ?>" data-motivo="<?= h($r[20]) ?>">
                <span class="exp"><?= h($r[1]) ?></span>
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa fa-globe" style="color:#a855f7;margin-top:2px"></i><span class="nm"><?= h(trim($r[2] . ' ' . $r[3])) ?></span></div>
                  <span class="badge b-blue"><?= h(strtoupper($r[8])) ?></span>
                  <?php if ($f['estado'] === 'SUBSANADO') { ?><span class="badge b-subsanado" title="El ciudadano corrigió el documento observado"><i class="fa fa-refresh"></i> SUBSANADO</span><?php } ?>
                  <?php if ($f['estado'] === 'NO_PRESENTADO') { ?><span class="badge b-nopres">NO PRESENTADO</span><?php } ?>
                </div>
                <div class="dcell"><span class="cell-txt"><?= h($r[4]) ?></span><?php if ($r[5] !== '') { ?><div class="muted" style="font-size:11px">DNI/RUC <?= h($r[5]) ?></div><?php } ?></div>
                <div class="dcell">
                  <p class="asun"><?= h($r[9]) ?></p>
                  <?php if ($bandeja === 'observados' && $r[20] !== '') { ?><div class="motivo" title="<?= h($r[20]) ?>"><i class="fa fa-comment-o"></i> <?= h($r[20]) ?></div><?php } ?>
                </div>
                <div class="dcell">
                  <?php if ($bandeja === 'pendientes') { ?>
                    <span class="cell-txt"><?= h($f['presentado']) ?></span>
                    <?php if ($f['oficial'] !== '') { ?><div class="fuera" title="Enviado fuera del horario de atención"><i class="fa fa-moon-o"></i> Se considera: <?= h($f['oficial']) ?></div><?php } ?>
                    <div class="<?= $espera ? 'espera' : 'muted' ?>" style="font-size:11px"><?= h(mpv_hace($r[11])) ?></div>
                  <?php } elseif ($bandeja === 'observados') { ?>
                    <span class="cell-txt <?= $f['vencido'] ? 'vencido' : '' ?>"><?= h($f['plazo']) ?></span>
                    <div style="font-size:11px" class="<?= $f['vencido'] ? 'vencido' : ($f['por_vencer'] ? 'espera' : 'muted') ?>"><?= $f['vencido'] ? 'Plazo vencido' : ($f['por_vencer'] ? 'Vence hoy' : 'En plazo') ?></div>
                  <?php } elseif ($bandeja === 'por_derivar') { ?>
                    <span class="cell-txt"><?= h($f['recep']) ?></span>
                    <div class="muted" style="font-size:11px"><?= h($r[14]) ?></div>
                  <?php } else { ?>
                    <?php if ($f['estado'] === 'NO_PRESENTADO') { ?>
                      <span class="cell-txt vencido">No presentado</span>
                      <div class="muted" style="font-size:11px">No subsanó a tiempo</div>
                    <?php } else { ?>
                      <span class="cell-txt"><?= h($r[15]) ?></span>
                      <div class="muted" style="font-size:11px"><?= intval($r[16]) === 1 ? 'Recibido por la oficina' : 'Pendiente en la oficina' ?></div>
                    <?php } ?>
                  <?php } ?>
                </div>
                <div class="act">
                  <?php if ($f['adj'] !== '') { ?><a class="ab ab-b" title="Ver documento adjunto" target="_blank" href="<?= h($f['adj']) ?>" onclick="event.stopPropagation()"><i class="fa fa-paperclip"></i></a><?php } else { ?><span class="ab ab-d" title="Sin adjunto"><i class="fa fa-paperclip"></i></span><?php } ?>
                  <a class="ab ab-b" title="Seguimiento" target="_blank" href="../consulta_publico.php?num_exp=<?= h(rawurlencode((string)$r[1])) ?>" onclick="event.stopPropagation()"><i class="fa fa-eye"></i></a>
                  <?php if ($bandeja === 'pendientes') { ?>
                    <button class="btn btn-sm btn-obs" title="Observar: pedir al ciudadano que corrija el documento" onclick="event.stopPropagation();abrirObservar(this)"><i class="fa fa-exclamation-circle"></i> Observar</button>
                    <button class="btn btn-p btn-sm" onclick="event.stopPropagation();abrirRecepcionar(this)"><i class="fa fa-check"></i> Recepcionar</button>
                  <?php } elseif ($bandeja === 'observados') { ?>
                    <?php if ($f['vencido']) { ?>
                      <button class="btn btn-sm btn-nopres" onclick="event.stopPropagation();abrirNoPresentado(this)"><i class="fa fa-ban"></i> No presentado</button>
                    <?php } else { ?>
                      <span class="muted" style="font-size:11px">Esperando al ciudadano</span>
                    <?php } ?>
                  <?php } elseif ($bandeja === 'por_derivar') { ?>
                    <button class="btn btn-p btn-sm" onclick="event.stopPropagation();abrirDerivar(this)"><i class="fa fa-share"></i> Derivar</button>
                  <?php } elseif ($f['estado'] !== 'NO_PRESENTADO') { ?>
                    <a class="ab ab-b" title="Imprimir cargo" target="_blank" href="../tcpdf/examples/cargo_externo.php?exp=<?= h(rawurlencode((string)$r[1])) ?>" onclick="event.stopPropagation()"><i class="fa fa-print"></i></a>
                  <?php } ?>
                </div>
              </div>
            <?php } ?>
          </div>
          <div class="pag"><span class="muted"><?= number_format(count($datos)) ?> documento(s)<?= count($datos) >= 500 ? ' (se muestran los primeros 500; use el buscador)' : '' ?></span></div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalle del documento</h2>
        </div>
        <div class="bd">
          <?php if ($first) { ?>
            <div class="docbox">
              <div class="ic"><i class="fa fa-globe"></i></div>
              <div style="min-width:0">
                <div id="d-doc" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h(trim($first[2] . ' ' . $first[3])) ?></div><span id="d-badge" class="badge b-blue"><?= h(strtoupper($first[8])) ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Expediente</div>
              <dl>
                <div><dt>N° Expediente</dt><dd id="d-exp"><?= h($first[1]) ?></dd></div>
                <div><dt>Enviado</dt><dd id="d-presentado"><?= h($firstF['presentado']) ?></dd></div>
                <div id="d-oficial-w" <?= $firstF['oficial'] === '' ? 'style="display:none"' : '' ?>><dt>Se considera presentado</dt><dd id="d-oficial" class="fuera"><?= h($firstF['oficial']) ?></dd></div>
                <div><dt>Recepcionado</dt><dd id="d-recep"><?= $firstF['recep'] !== '' ? h($firstF['recep'] . ($first[14] !== '' ? ' — ' . $first[14] : '')) : 'Pendiente' ?></dd></div>
                <div><dt>Destino</dt><dd id="d-destino"><?= $first[15] !== '' ? h($first[15]) : 'Sin derivar' ?></dd></div>
                <div><dt>Folios</dt><dd id="d-folios"><?= h($first[10]) ?></dd></div>
              </dl>
              <div class="sect">Ciudadano</div>
              <dl>
                <div><dt>Nombre</dt><dd id="d-remit"><?= h($first[4]) ?></dd></div>
                <div><dt>DNI / RUC</dt><dd id="d-dni"><?= h($first[5] !== '' ? $first[5] : '—') ?></dd></div>
                <div><dt>Correo</dt><dd id="d-email"><?= h($first[6] !== '' ? $first[6] : '—') ?></dd></div>
                <div><dt>Teléfono</dt><dd id="d-tel"><?= h($first[7] !== '' ? $first[7] : '—') ?></dd></div>
                <div><dt>Dirección</dt><dd id="d-dir"><?= h($first[17] !== '' ? $first[17] : '—') ?></dd></div>
              </dl>
              <div class="sect">Asunto</div>
              <p id="d-asunto" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[9]) ?></p>
              <div class="sect">Historial</div>
              <div id="d-historial" class="mpv-hist" data-id="<?= h($first[0]) ?>"><span class="muted">Cargando…</span></div>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Seleccione un documento para ver sus detalles.</div><?php } ?>
        </div>
        <?php if ($first && ($bandeja === 'pendientes' || $bandeja === 'por_derivar')) { ?>
          <div class="ftr">
            <a id="f-adj" class="btn" style="flex:1;justify-content:center" target="_blank" href="<?= h($firstF['adj'] !== '' ? $firstF['adj'] : '#') ?>"><i class="fa fa-paperclip"></i> Ver documento</a>
            <?php if ($bandeja === 'pendientes') { ?>
              <button class="btn btn-p" style="flex:1;justify-content:center" onclick="abrirRecepcionar(document.querySelector('#tbody .row.sel'))"><i class="fa fa-check"></i> Recepcionar</button>
            <?php } else { ?>
              <button class="btn btn-p" style="flex:1;justify-content:center" onclick="abrirDerivar(document.querySelector('#tbody .row.sel'))"><i class="fa fa-share"></i> Derivar</button>
            <?php } ?>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>

  <!-- MODAL RECEPCIONAR -->
  <div class="modal" id="modalRecep">
    <div class="box">
      <div class="mh">
        <h3>Recepcionar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarModales()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div id="rc_info" class="mpv-resumen"></div>
        <div class="mpv-nota" id="rc_correo"></div>
        <div class="muted" style="font-size:11.5px"><i class="fa fa-info-circle"></i> Antes de recepcionar, abra el documento adjunto y verifique que esté conforme. Si tiene algún problema, use <strong>Observar</strong>. Después podrá derivarlo en <strong>Por derivar</strong>.</div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarModales()">Cancelar</button><button class="btn btn-p" id="rc_ok"><i class="fa fa-check"></i> Recepcionar</button></div>
    </div>
  </div>

  <!-- MODAL OBSERVAR -->
  <div class="modal" id="modalObs">
    <div class="box">
      <div class="mh">
        <h3>Observar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarModales()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div id="ob_info" class="mpv-resumen"></div>
        <div><label>¿Qué debe corregir el ciudadano? <span class="req">*</span></label>
          <textarea id="ob_motivo" rows="4" maxlength="1000" placeholder="Ej.: El archivo no se puede leer (está borroso). Vuelva a escanearlo y adjúntelo en PDF."></textarea>
          <div class="mpv-sugerencias">
            <span class="muted">Motivos frecuentes:</span>
            <button type="button" class="chip" data-txt="El archivo adjunto no se puede leer (está borroso o incompleto). Vuelva a escanearlo y adjúntelo en PDF.">Ilegible</button>
            <button type="button" class="chip" data-txt="El documento no tiene firma. Adjunte el documento firmado.">Sin firma</button>
            <button type="button" class="chip" data-txt="Faltan páginas: el número de folios indicado no coincide con el documento adjunto. Adjunte el documento completo.">Faltan folios</button>
            <button type="button" class="chip" data-txt="Faltan los anexos mencionados en el documento. Adjúntelos en un solo archivo PDF junto con el documento.">Faltan anexos</button>
          </div>
        </div>
        <div class="mpv-nota warn" id="ob_plazo"></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarModales()">Cancelar</button><button class="btn btn-obs" id="ob_ok"><i class="fa fa-exclamation-circle"></i> Observar y avisar al ciudadano</button></div>
    </div>
  </div>

  <!-- MODAL NO PRESENTADO -->
  <div class="modal" id="modalNoPres">
    <div class="box">
      <div class="mh">
        <h3>Marcar como no presentado</h3><button class="ib" style="color:#94a3b8" onclick="cerrarModales()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div id="np_info" class="mpv-resumen"></div>
        <div class="mpv-nota warn"><i class="fa fa-exclamation-triangle"></i> El ciudadano no subsanó la observación dentro del plazo (venció el <strong id="np_plazo"></strong>). El documento se considerará <strong>no presentado</strong> (Ley 27444, art. 136) y se le avisará por correo. Esta acción no se puede deshacer.</div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarModales()">Cancelar</button><button class="btn btn-nopres" id="np_ok"><i class="fa fa-ban"></i> Confirmar: no presentado</button></div>
    </div>
  </div>

  <!-- MODAL DERIVAR -->
  <div class="modal" id="modalDeriv">
    <div class="box">
      <div class="mh">
        <h3>Derivar a una oficina</h3><button class="ib" style="color:#94a3b8" onclick="cerrarModales()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div id="dv_info" class="mpv-resumen"></div>
        <div><label>Oficina destino <span class="req">*</span></label><select id="dv_oficina">
            <option value="">Seleccione la oficina...</option><?php foreach ($catOficinas as $o) { ?><option value="<?= h($o[0]) ?>"><?= h($o[1]) ?></option><?php } ?>
          </select></div>
        <label class="mpv-check"><input type="checkbox" id="dv_cargo" checked> Abrir el cargo para imprimir</label>
        <div class="muted" style="font-size:11.5px"><i class="fa fa-info-circle"></i> El documento aparecerá en la bandeja <strong>Recepción &gt; Por recepcionar</strong> de la oficina elegida.</div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarModales()">Cancelar</button><button class="btn btn-p" id="dv_ok"><i class="fa fa-share"></i> Derivar</button></div>
    </div>
  </div>

  <script src="../js/v_mesa_partes_virtual.js?v=<?= @filemtime(__DIR__ . '/../js/v_mesa_partes_virtual.js') ?>"></script>
</body>

</html>
