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
require_once '../clases/reg_docs.php';

$idoficina = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
$alias     = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';

if (!function_exists('norm_txt_reg')) {
  function norm_txt_reg($text)
  {
    $text = trim((string)$text);
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
  }
}
function page_url_reg($page, $params)
{
  if (intval($page) > 1) {
    $params['page'] = intval($page);
  } else {
    unset($params['page']);
  }
  $defaults = array('q' => '', 'desde' => '', 'hasta' => '', 'estado' => 'Todos', 'sort' => 'recentes', 'per_page' => '25');
  foreach ($defaults as $k => $v) {
    if (array_key_exists($k, $params) && (string)$params[$k] === (string)$v) {
      unset($params[$k]);
    }
  }
  $qs = http_build_query($params);
  return $qs === '' ? 'o_reg_documentos.php' : '?' . $qs;
}

// ---- Filtros ----
$qFiltro = isset($_GET['q']) ? trim($_GET['q']) : '';
$desdeFiltro = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hastaFiltro = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$estadoFiltro = isset($_GET['estado']) ? trim($_GET['estado']) : 'Todos';
$sortFiltro = isset($_GET['sort']) ? trim($_GET['sort']) : 'recentes';
$porPagina = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;

$hoy = date('Y-m-d');
$desdeSql = ($desdeFiltro !== '') ? $desdeFiltro : date('Y-m-d', strtotime('-7 days'));
$hastaSql = ($hastaFiltro !== '') ? $hastaFiltro : $hoy;

$dml = new Reg_docs(); // clase de clases/reg_docs.php
$datos = $dml->datos_registrados($desdeSql, $hastaSql);
$cont = $dml->contadores_registro();
$catDocumentos = $dml->datos_documentos_reg();
$catTramites = $dml->datos_tramites();
$catOficinas = $dml->datos_oficinas_reg();

$datosFiltrados = array_values(array_filter($datos, function ($r) use ($qFiltro, $estadoFiltro) {
  if ($estadoFiltro !== 'Todos') {
    $est = intval($r[13]) === 1 ? 'Recepcionado' : 'Pendiente';
    if (norm_txt_reg($est) !== norm_txt_reg($estadoFiltro)) return false;
  }
  if ($qFiltro !== '') {
    $texto = norm_txt_reg(implode(' ', array_map(function ($v) {
      return is_scalar($v) ? (string)$v : '';
    }, $r)));
    if (strpos($texto, norm_txt_reg($qFiltro)) === false) return false;
  }
  return true;
}));

if ($sortFiltro === 'antiguos') {
  usort($datosFiltrados, function ($a, $b) {
    return strcmp((string)$a[12], (string)$b[12]);
  });
}

// ---- Exportaciones (CSV / Excel / PDF), mismas opciones que Recepción ----
$exportFmt = isset($_GET['export']) ? $_GET['export'] : '';

if ($exportFmt === 'csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="registro_mesa_partes_' . date('Ymd_His') . '.csv"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, array('N Expediente', 'Documento', 'N Documento', 'Remitente', 'Tramite', 'Oficina destino', 'Asunto', 'Folios', 'Estado', 'Fecha'), ';', '"', '\\');
  foreach ($datosFiltrados as $rc) {
    fputcsv($out, array($rc[5], $rc[1], $rc[6], $rc[2], $rc[3], $rc[4], $rc[7], $rc[10], (intval($rc[13]) === 1 ? 'Recepcionado' : 'Pendiente'), $rc[11]), ';', '"', '\\');
  }
  fclose($out);
  exit;
}
if ($exportFmt === 'xls') {
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="registro_mesa_partes_' . date('Ymd_His') . '.xls"');
  echo "\xEF\xBB\xBF";
  echo '<table border="1"><tr style="background:#e2e8f0;font-weight:bold"><th>N Expediente</th><th>Documento</th><th>N Documento</th><th>Remitente</th><th>Tramite</th><th>Oficina destino</th><th>Asunto</th><th>Folios</th><th>Estado</th><th>Fecha</th></tr>';
  foreach ($datosFiltrados as $rx) {
    echo '<tr><td>' . h($rx[5]) . '</td><td>' . h($rx[1]) . '</td><td>' . h($rx[6]) . '</td><td>' . h($rx[2]) . '</td><td>' . h($rx[3]) . '</td><td>' . h($rx[4]) . '</td><td>' . h($rx[7]) . '</td><td>' . h($rx[10]) . '</td><td>' . (intval($rx[13]) === 1 ? 'Recepcionado' : 'Pendiente') . '</td><td>' . h($rx[11]) . '</td></tr>';
  }
  echo '</table>';
  exit;
}
if ($exportFmt === 'pdf') {
  $cwd_prev = getcwd();
  chdir(__DIR__ . '/../tcpdf/examples');
  // TCPDF es una versión antigua: sus avisos 'Deprecated' (PHP 8.4+) rompían el PDF
  // y llenaban el log de errores. Se silencian solo para esta exportación.
  error_reporting(E_ALL & ~E_DEPRECATED);
  ini_set('display_errors', '0');
  require_once __DIR__ . '/../tcpdf/examples/tcpdf_include.php';
  chdir($cwd_prev);
  $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
  $pdf->SetCreator('SISGEDO');
  $pdf->SetTitle('Registro de documentos — Mesa de partes');
  $pdf->SetMargins(10, 12, 10);
  $pdf->SetAutoPageBreak(true, 12);
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->AddPage();
  $pdf->SetFont('helvetica', 'B', 13);
  $pdf->Cell(0, 8, 'SISGEDO — Registro de documentos (mesa de partes)', 0, 1);
  $pdf->SetFont('helvetica', '', 8);
  $pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i') . '  |  Registros: ' . count($datosFiltrados), 0, 1);
  $pdf->Ln(2);
  $htmlPdf = '<table border="0.5" cellpadding="3"><thead><tr style="background-color:#e2e8f0;font-weight:bold;">'
    . '<th width="10%">Expediente</th><th width="16%">Documento</th><th width="16%">Remitente</th><th width="14%">Destino</th><th width="24%">Asunto</th><th width="9%">Estado</th><th width="11%">Fecha</th></tr></thead><tbody>';
  foreach ($datosFiltrados as $rp) {
    $htmlPdf .= '<tr><td width="10%">' . h($rp[5]) . '</td><td width="16%">' . h($rp[1] . ' ' . $rp[6]) . '</td><td width="16%">' . h($rp[2]) . '</td><td width="14%">' . h($rp[4]) . '</td><td width="24%">' . h($rp[7]) . '</td><td width="9%">' . (intval($rp[13]) === 1 ? 'Recepcionado' : 'Pendiente') . '</td><td width="11%">' . h($rp[11]) . '</td></tr>';
  }
  $htmlPdf .= '</tbody></table>';
  $pdf->writeHTML($htmlPdf, true, false, true, false, '');
  $pdf->Output('registro_mesa_partes_' . date('Ymd_His') . '.pdf', 'I');
  exit;
}

$totalTabla = count($datosFiltrados);
$paginaActual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$totalPaginas = max(1, (int)ceil($totalTabla / $porPagina));
if ($paginaActual > $totalPaginas) {
  $paginaActual = $totalPaginas;
}
$inicioPagina = ($paginaActual - 1) * $porPagina;
$datosPagina = array_slice($datosFiltrados, $inicioPagina, $porPagina);
$first = isset($datosPagina[0]) ? $datosPagina[0] : null;

$queryBase = array('q' => $qFiltro, 'desde' => $desdeFiltro, 'hasta' => $hastaFiltro, 'estado' => $estadoFiltro, 'sort' => $sortFiltro, 'per_page' => $porPagina);
$tok = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registro de Documentos — SISGEDO</title>
  <link rel="icon" type="image/png" href="../<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/o_reg_documentos.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body data-csrf="<?= h($tok) ?>" data-idoficina="<?= h($idoficina) ?>">
  <div class="app">
    <!-- HEADER -->
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
      <?php $NAV_ACTIVO = 'vistas/o_reg_documentos.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <!-- CONTENIDO -->
      <div class="main">
        <h1 class="t">Registro de Documentos</h1>
        <div class="subt">Mesa de partes presencial — mostrando del <?= h(date('d/m/Y', strtotime($desdeSql))) ?> al <?= h(date('d/m/Y', strtotime($hastaSql))) ?> (amplía con los filtros de fecha)</div>

        <div class="kpis">
          <?php
          $cards = [
            ["Registrados hoy", number_format($cont['hoy']), "Mesa de partes", "fa-calendar-check-o", "t-blue", "#1e293b", "lab-blue"],
            ["Últimos 7 días", number_format($cont['semana']), "Registros recientes", "fa-calendar", "t-purple", "#6d28d9", "lab-purple"],
            ["Pendientes de recepción", number_format($cont['pendientes']), "Sin recepcionar en destino", "fa-clock-o", "t-orange", "#f97316", "lab-orange"],
            ["Este año", number_format($cont['anio']), "Total " . date('Y'), "fa-bar-chart", "t-green", "#16a34a", "lab-green"],
          ];
          foreach ($cards as $c) { ?>
            <div class="kpi">
              <div class="ic <?= $c[4] ?>"><i class="fa <?= $c[3] ?>"></i></div>
              <div>
                <div class="lab <?= $c[6] ?>"><?= $c[0] ?></div>
                <div class="num" style="color:<?= $c[5] ?>"><?= $c[1] ?></div>
                <div class="ss"><?= $c[2] ?></div>
              </div>
            </div>
          <?php } ?>
        </div>

        <!-- Filtros -->
        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" value="<?= h($qFiltro) ?>" placeholder="Buscar por expediente, remitente, asunto..." /><i class="fa fa-search"></i></div>
            </div>
            <div class="fld"><label>Desde</label>
              <div class="inp"><input id="desde" type="date" max="<?= h($hoy) ?>" value="<?= h($desdeFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Hasta</label>
              <div class="inp"><input id="hasta" type="date" max="<?= h($hoy) ?>" value="<?= h($hastaFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Estado</label>
              <div class="inp" style="padding:0"><select id="estado" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="Todos" <?= $estadoFiltro === 'Todos' ? 'selected' : '' ?>>Todos</option>
                  <option value="Pendiente" <?= $estadoFiltro === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                  <option value="Recepcionado" <?= $estadoFiltro === 'Recepcionado' ? 'selected' : '' ?>>Recepcionado</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn" onclick="limpiarFiltros()"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <!-- Tabla -->
        <div class="card table-card">
          <div class="tbar">
            <div class="g">
              <button class="btn btn-p btn-sm" id="btnNuevoReg"><i class="fa fa-plus"></i> Registrar documento</button>
              <div class="dd">
                <button class="btn btn-sm" id="btnExportar"><i class="fa fa-download"></i> Exportar <i class="fa fa-angle-down"></i></button>
                <div class="dd-menu">
                  <a href="#" id="expPdf"><i class="fa fa-file-pdf-o"></i> Descargar PDF</a>
                  <a href="#" id="expXls"><i class="fa fa-file-excel-o"></i> Descargar Excel (.xls)</a>
                  <a href="#" id="expCsv"><i class="fa fa-file-text-o"></i> Descargar CSV</a>
                  <a href="#" id="expPrint"><i class="fa fa-print"></i> Imprimir listado</a>
                </div>
              </div>
            </div>
            <div class="g"><span class="muted">Ordenar por:</span>
              <div class="inp btn-sm" style="width:160px;padding:0"><select id="sort" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="recentes" <?= $sortFiltro === 'recentes' ? 'selected' : '' ?>>Más recientes</option>
                  <option value="antiguos" <?= $sortFiltro === 'antiguos' ? 'selected' : '' ?>>Más antiguos</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
          </div>

          <div class="rowg thead">
            <span>Expediente</span><span>Documento</span><span>Remitente</span><span>Asunto</span><span>Destino</span><span>Estado</span><span>Fecha</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($datos) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No hay documentos registrados en el rango seleccionado (por defecto, últimos 7 días).</div>
            <?php }
            foreach ($datosPagina as $i => $r) {
              $est = intval($r[13]) === 1 ? 'Recepcionado' : 'Pendiente';
              $dotE = $est === 'Recepcionado' ? 'd-green' : 'd-orange';
              $editable = intval($r[13]) === 0;
              $adj = $r[14];
              $esWeb = isset($r[20]) && intval($r[20]) === 1;
              $origenReg = $esWeb ? 'Mesa de Partes Virtual' : ((isset($r[21]) && $r[21] !== '') ? $r[21] : 'Mesa de Partes');
              $sinDestino = ($r[4] === '');
              // NUEVO: llegó por Mesa de Partes Virtual y aún no se revisa/deriva.
              // Al derivarlo se le asigna oficina y la etiqueta desaparece.
              $esNuevo = $esWeb && $sinDestino && $editable
                && strtotime((string)$r[12]) >= strtotime('-' . intval(SISGEDO_DIAS_NUEVO) . ' days');
            ?>
              <div class="row rowg <?= $i === 0 ? 'sel' : '' ?><?= $esNuevo ? ' row-nuevo' : '' ?>" data-id="<?= h($r[0]) ?>" data-exp="<?= h($r[5]) ?>" data-doc="<?= h($r[1] . ' ' . $r[6]) ?>" data-iddoc="<?= h($r[15]) ?>" data-num="<?= h($r[6]) ?>" data-remit="<?= h($r[2]) ?>" data-idremit="<?= h($r[16]) ?>" data-tram="<?= h($r[3]) ?>" data-idtram="<?= h($r[17]) ?>" data-destino="<?= h($r[4]) ?>" data-idofi="<?= h($r[18]) ?>" data-asunto="<?= h($r[7]) ?>" data-parasu="<?= h($r[8]) ?>" data-obs="<?= h($r[9]) ?>" data-folios="<?= h($r[10]) ?>" data-proc="<?= h($r[19]) ?>" data-estado="<?= h($est) ?>" data-fecha="<?= h($r[11]) ?>" data-adj="<?= h($adj) ?>" data-editable="<?= $editable ? '1' : '0' ?>" data-origen="<?= h($origenReg) ?>" data-web="<?= $esWeb ? '1' : '0' ?>">
                <span class="exp"><?= h($r[5]) ?></span>
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa <?= $esWeb ? 'fa-globe' : 'fa-file-text-o' ?>" style="color:<?= $esWeb ? '#a855f7' : '#3b82f6' ?>;margin-top:2px"></i><span class="nm"><?= h($r[1]) ?> <?= h($r[6]) ?></span></div><span class="badge b-blue"><?= h(strtoupper($r[3])) ?></span><?php if ($esWeb) { ?> <span class="badge b-purple">VIRTUAL</span><?php } ?><?php if ($esNuevo) { ?> <span class="badge b-new" title="Documento nuevo de Mesa de Partes Virtual, pendiente de revisar y derivar"><i class="fa fa-star"></i> NEW</span><?php } ?>
                </div>
                <div class="dcell"><span class="cell-txt"><?= h($r[2]) ?></span></div>
                <div class="dcell">
                  <p class="asun"><?= h($r[7]) ?></p>
                </div>
                <div class="dcell"><?php if ($r[4] !== '') { ?><span class="cell-txt"><?= h($r[4]) ?></span><?php } else { ?><span class="badge" style="background:#fffbeb;color:#b45309">SIN DESTINO</span><?php } ?></div>
                <div><span class="st"><span class="dot <?= $dotE ?>"></span> <?= h($est) ?></span></div>
                <div class="muted"><?= h($r[11]) ?></div>
                <div class="act">
                  <?php if ($esWeb) {
                    // Documento de la Mesa de Partes Virtual: el ciudadano ya recibió
                    // su constancia por correo; el cargo solo aplica a lo registrado aquí. ?>
                    <span class="ab ab-d" title="Documento virtual: el ciudadano recibió su constancia por correo (no requiere cargo)" onclick="event.stopPropagation()"><i class="fa fa-print"></i></span>
                  <?php } elseif (!$sinDestino) { ?>
                    <a class="ab ab-b" title="Imprimir cargo" target="_blank" href="../tcpdf/examples/cargo_externo.php?exp=<?= h(rawurlencode((string)$r[5])) ?>" onclick="event.stopPropagation()"><i class="fa fa-print"></i></a>
                  <?php } else { ?>
                    <span class="ab ab-d" title="El cargo se genera al derivar a una oficina" onclick="event.stopPropagation()"><i class="fa fa-print"></i></span>
                  <?php } ?>
                  <a class="ab ab-b" title="Seguimiento" target="_blank" href="../consulta_publico.php?num_exp=<?= h(rawurlencode((string)$r[5])) ?>" onclick="event.stopPropagation()"><i class="fa fa-eye"></i></a>
                  <?php if ($editable) { ?>
                    <button class="ab ab-g" title="Derivar a una oficina" onclick="event.stopPropagation();abrirDerivarReg(this)"><i class="fa fa-share"></i></button>
                    <button class="ab ab-g" title="Editar" onclick="event.stopPropagation();abrirEditar(this)"><i class="fa fa-pencil"></i></button>
                    <button class="ab ab-r" title="Eliminar" onclick="event.stopPropagation();eliminarReg(this)"><i class="fa fa-trash-o"></i></button>
                  <?php } ?>
                  <?php if ($adj !== '#' && $adj !== '' && $adj !== null) { ?><a class="ab ab-b" title="Ver adjunto" target="_blank" href="<?= h($adj) ?>" onclick="event.stopPropagation()"><i class="fa fa-paperclip"></i></a><?php } else { ?><span class="ab ab-d" title="Sin adjunto"><i class="fa fa-paperclip"></i></span><?php } ?>
                </div>
              </div>
            <?php } ?>
          </div>

          <div class="pag">
            <?php $desdeN = $totalTabla === 0 ? 0 : $inicioPagina + 1;
            $hastaN = min($inicioPagina + $porPagina, $totalTabla); ?>
            <span class="muted">Mostrando <?= $desdeN ?> a <?= $hastaN ?> de <?= number_format($totalTabla) ?> registros</span>
            <div style="display:flex;align-items:center;gap:16px">
              <div class="inp btn-sm" style="width:120px;padding:0"><select id="per_page" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25 por página</option>
                  <option value="50" <?= $porPagina === 50 ? 'selected' : '' ?>>50 por página</option>
                  <option value="100" <?= $porPagina === 100 ? 'selected' : '' ?>>100 por página</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
              <div style="display:flex;gap:4px">
                <a class="pg" href="<?= h(page_url_reg(1, $queryBase)) ?>"><i class="fa fa-angle-double-left"></i></a>
                <a class="pg <?= $paginaActual > 1 ? '' : 'disabled' ?>" href="<?= h(page_url_reg(max(1, $paginaActual - 1), $queryBase)) ?>"><i class="fa fa-angle-left"></i></a>
                <?php
                $ventana = 2;
                $inicioBotones = max(1, $paginaActual - $ventana);
                $finBotones = min($totalPaginas, $paginaActual + $ventana);
                if ($inicioBotones > 1) {
                  echo '<a class="pg" href="' . h(page_url_reg(1, $queryBase)) . '">1</a>';
                  if ($inicioBotones > 2) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                }
                for ($p = $inicioBotones; $p <= $finBotones; $p++) {
                  $cls = $p === $paginaActual ? 'pg on' : 'pg';
                  echo '<a class="' . $cls . '" href="' . h(page_url_reg($p, $queryBase)) . '">' . $p . '</a>';
                }
                if ($finBotones < $totalPaginas) {
                  if ($finBotones < $totalPaginas - 1) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                  echo '<a class="pg" href="' . h(page_url_reg($totalPaginas, $queryBase)) . '">' . $totalPaginas . '</a>';
                }
                ?>
                <a class="pg <?= $paginaActual < $totalPaginas ? '' : 'disabled' ?>" href="<?= h(page_url_reg(min($totalPaginas, $paginaActual + 1), $queryBase)) ?>"><i class="fa fa-angle-right"></i></a>
                <a class="pg" href="<?= h(page_url_reg($totalPaginas, $queryBase)) ?>"><i class="fa fa-angle-double-right"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles del registro</h2>
        </div>
        <div class="bd">
          <?php if ($first) { ?>
            <div class="docbox">
              <div class="ic"><i class="fa fa-file-text-o"></i></div>
              <div style="min-width:0">
                <div id="d-doc" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($first[1] . ' ' . $first[6]) ?></div><span id="d-badge" class="badge b-blue"><?= h(strtoupper($first[3])) ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Información general</div>
              <dl>
                <div>
                  <dt>N° Expediente</dt>
                  <dd id="d-exp"><?= h($first[5]) ?></dd>
                </div>
                <div>
                  <dt>Origen</dt>
                  <dd id="d-origen"><?= h(isset($first[20]) && intval($first[20]) === 1 ? 'Mesa de Partes Virtual' : ((isset($first[21]) && $first[21] !== '') ? $first[21] : 'Mesa de Partes')) ?></dd>
                </div>
                <div>
                  <dt>Remitente</dt>
                  <dd id="d-remit"><?= h($first[2]) ?></dd>
                </div>
                <div>
                  <dt>Destino</dt>
                  <dd id="d-destino"><?= $first[4] !== '' ? h($first[4]) : 'Sin destino — usa "Derivar"' ?></dd>
                </div>
                <div>
                  <dt>Folios</dt>
                  <dd id="d-folios"><?= h($first[10]) ?></dd>
                </div>
                <div>
                  <dt>Estado</dt>
                  <dd id="d-estado"><?= intval($first[13]) === 1 ? 'Recepcionado' : 'Pendiente' ?></dd>
                </div>
                <div>
                  <dt>Fecha</dt>
                  <dd id="d-fecha"><?= h($first[11]) ?></dd>
                </div>
              </dl>
              <div class="sect">Asunto</div>
              <p id="d-asunto" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[7]) ?></p>
              <div class="sect">Observación</div>
              <p id="d-obs" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= $first[9] !== '' ? h($first[9]) : '—' ?></p>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Selecciona un registro para ver sus detalles.</div><?php } ?>
        </div>
        <div class="ftr">
          <button class="btn btn-p" style="flex:1;justify-content:center" onclick="abrirNuevo()"><i class="fa fa-plus"></i> Registrar documento</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL REGISTRAR / EDITAR DOCUMENTO -->
  <div class="modal" id="modalReg">
    <div class="box box-lg">
      <div class="mh">
        <h3 id="mr_titulo">Registrar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarReg()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div class="mr-grid">
          <div><label>Remitente <span class="req">*</span></label>
            <div style="display:flex;gap:6px">
              <input id="mr_remitente" list="mr_remitentes_list" placeholder="Escriba apellidos para buscar..." style="flex:1" autocomplete="off" />
              <button type="button" class="btn btn-sm" onclick="abrirNuevoRemit()" title="Nuevo remitente"><i class="fa fa-user-plus"></i></button>
            </div>
            <datalist id="mr_remitentes_list"></datalist>
            <input type="hidden" id="mr_idremitente" value="" />
          </div>
          <div><label>Tipo de documento <span class="req">*</span></label><select id="mr_tipo">
              <option value="">Seleccione...</option><?php foreach ($catDocumentos as $d) { ?><option value="<?= h($d[0]) ?>"><?= h($d[1]) ?></option><?php } ?>
            </select></div>
          <div><label>Tipo de trámite <span class="req">*</span></label><select id="mr_tram">
              <option value="">Seleccione...</option><?php foreach ($catTramites as $t) { ?><option value="<?= h($t[0]) ?>"><?= h($t[1]) ?></option><?php } ?>
            </select></div>
          <div><label>N° de documento</label><input id="mr_numero" type="text" placeholder="Ej. 0123-2026" /></div>
          <div><label>Folios <span class="req">*</span></label><input id="mr_folios" type="number" min="1" placeholder="Cantidad" /></div>
          <div><label>Procedencia</label><input id="mr_proc" type="text" placeholder="Lugar de procedencia (opcional)" /></div>
        </div>
        <div><label>Asunto <span class="req">*</span></label><input id="mr_asunto" type="text" placeholder="Asunto del documento" /></div>
        <div><label>Para su</label><input id="mr_parasu" type="text" placeholder="Ej. atención, conocimiento (opcional)" /></div>
        <div><label>Observación</label><textarea id="mr_obs" rows="2" placeholder="Observaciones (opcional)"></textarea></div>
        <div id="mr_ofi_wrap"><label>Oficina(s) destino <span class="req">*</span></label>
          <div class="msel" id="mr_ofi_msel">
            <div class="msel-control" onclick="toggleMsel('mr_ofi_msel')">
              <div class="msel-tags" id="mr_ofi_tags"><span class="msel-ph">Seleccione una o varias oficinas...</span></div><i class="fa fa-angle-down"></i>
            </div>
            <div class="msel-panel">
              <input type="text" id="mr_ofi_buscar" class="ofi-buscar" placeholder="Buscar oficina..." autocomplete="off" />
              <div class="msel-lista" id="mr_oficinas">
                <?php foreach ($catOficinas as $o) { ?>
                  <label class="ofi-item"><input type="checkbox" value="<?= h($o[0]) ?>"> <span><?= h($o[1]) ?></span></label>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="muted" style="font-size:11px;margin-top:4px">Se crea un registro por oficina, con el mismo expediente.</div>
        </div>
        <div id="mr_adj_wrap"><label>Adjunto</label><input id="mr_fichero" type="file" /></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarReg()">Cancelar</button><button class="btn btn-p" id="mr_guardar"><i class="fa fa-check"></i> <span id="mr_btn_txt">Registrar</span></button></div>
    </div>
  </div>

  <!-- MODAL DERIVAR A OFICINA (asigna la oficina destino de un registro pendiente,
     esencial para los documentos de la Mesa de Partes Virtual, que llegan sin destino) -->
  <div class="modal" id="modalDerivReg">
    <div class="box">
      <div class="mh">
        <h3>Derivar a una oficina</h3><button class="ib" style="color:#94a3b8" onclick="cerrarDerivarReg()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div class="muted" id="dr_info" style="font-size:12px"></div>
        <div><label>Oficina destino <span class="req">*</span></label><select id="dr_oficina">
            <option value="">Seleccione la oficina...</option><?php foreach ($catOficinas as $o) { ?><option value="<?= h($o[0]) ?>"><?= h($o[1]) ?></option><?php } ?>
          </select></div>
        <div class="muted" style="font-size:11.5px"><i class="fa fa-info-circle"></i> El documento aparecerá en la bandeja "Por recepcionar" de la oficina elegida. Los documentos de la Mesa de Partes Virtual llegan sin destino: usa esta opción para encaminarlos.</div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarDerivarReg()">Cancelar</button><button class="btn btn-p" id="dr_guardar"><i class="fa fa-share"></i> Derivar</button></div>
    </div>
  </div>

  <!-- MODAL NUEVO REMITENTE -->
  <div class="modal" id="modalRemit">
    <div class="box">
      <div class="mh">
        <h3>Nuevo remitente</h3><button class="ib" style="color:#94a3b8" onclick="cerrarNuevoRemit()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>DNI (búsqueda RENIEC)</label>
          <div style="display:flex;gap:6px">
            <input id="nr_dni" type="text" maxlength="8" placeholder="8 dígitos" style="flex:1" />
            <button type="button" class="btn btn-sm" id="nr_buscar"><i class="fa fa-search"></i> Buscar</button>
          </div>
        </div>
        <div><label>Apellidos <span class="req">*</span></label><input id="nr_apellidos" type="text" /></div>
        <div><label>Nombres <span class="req">*</span></label><input id="nr_nombres" type="text" /></div>
        <div><label>Dirección</label><input id="nr_direccion" type="text" /></div>
        <div><label>Email</label><input id="nr_email" type="email" /></div>
        <div><label>Teléfono</label><input id="nr_telefono" type="text" /></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarNuevoRemit()">Cancelar</button><button class="btn btn-p" id="nr_guardar"><i class="fa fa-user-plus"></i> Guardar remitente</button></div>
    </div>
  </div>

  <script src="../js/o_reg_documentos.js?v=<?= @filemtime(__DIR__ . '/../js/o_reg_documentos.js') ?>"></script>
</body>

</html>