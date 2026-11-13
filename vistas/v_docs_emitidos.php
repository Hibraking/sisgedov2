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
require_once '../clases/docs_emitidos.php';

$idoficina = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
$alias     = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';

if (!function_exists('norm_txt_emi')) {
  function norm_txt_emi($text)
  {
    $text = trim((string)$text);
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
  }
}
function page_url_emi($page, $params)
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
  return $qs === '' ? 'v_docs_emitidos.php' : '?' . $qs;
}
/** Estado de un doc emitido: fila = [.., 5 destino, 6 estado(0/1), .., 11 idoficina] */
function estado_emision($r)
{
  if (intval($r[6]) === 1) return 'Recepcionado';
  if (intval($r[11]) === 0) return 'Sin destino';
  return 'Por recepcionar';
}

// ---- Filtros ----
$qFiltro = isset($_GET['q']) ? trim($_GET['q']) : '';
$desdeFiltro = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hastaFiltro = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$estadoFiltro = isset($_GET['estado']) ? trim($_GET['estado']) : 'Todos';
$sortFiltro = isset($_GET['sort']) ? trim($_GET['sort']) : 'recentes';
$porPagina = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;

$hoy = date('Y-m-d');
$desdeSql = ($desdeFiltro !== '') ? $desdeFiltro : date('Y-m-d', strtotime('-30 days'));
$hastaSql = ($hastaFiltro !== '') ? $hastaFiltro : $hoy;

$dml = new Reg_docs(); // clase de clases/docs_emitidos.php
$datos = $dml->datos_emitidos($idoficina, $desdeSql, $hastaSql);
$cont = $dml->contadores_emision($idoficina);
$catDocumentos = $dml->datos_documentos();
$catOficinas = $dml->datos_oficinas();

$datosFiltrados = array_values(array_filter($datos, function ($r) use ($qFiltro, $estadoFiltro) {
  if ($estadoFiltro !== 'Todos' && norm_txt_emi(estado_emision($r)) !== norm_txt_emi($estadoFiltro)) return false;
  if ($qFiltro !== '') {
    $texto = norm_txt_emi(implode(' ', array_map(function ($v) {
      return is_scalar($v) ? (string)$v : '';
    }, $r)));
    if (strpos($texto, norm_txt_emi($qFiltro)) === false) return false;
  }
  return true;
}));

if ($sortFiltro === 'antiguos') {
  usort($datosFiltrados, function ($a, $b) {
    return strcmp((string)$a[9], (string)$b[9]);
  });
}

// ---- Exportaciones (CSV / Excel / PDF), mismas opciones que Recepción ----
$exportFmt = isset($_GET['export']) ? $_GET['export'] : '';

if ($exportFmt === 'csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="emision_' . date('Ymd_His') . '.csv"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, array('Documento', 'Numero', 'Asunto', 'Detalles', 'Destino', 'Estado', 'Fecha'), ';', '"', '\\');
  foreach ($datosFiltrados as $rc) {
    fputcsv($out, array($rc[1], $rc[2], $rc[3], $rc[4], $rc[5], estado_emision($rc), $rc[8]), ';', '"', '\\');
  }
  fclose($out);
  exit;
}
if ($exportFmt === 'xls') {
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="emision_' . date('Ymd_His') . '.xls"');
  echo "\xEF\xBB\xBF";
  echo '<table border="1"><tr style="background:#e2e8f0;font-weight:bold"><th>Documento</th><th>Numero</th><th>Asunto</th><th>Detalles</th><th>Destino</th><th>Estado</th><th>Fecha</th></tr>';
  foreach ($datosFiltrados as $rx) {
    echo '<tr><td>' . h($rx[1]) . '</td><td>' . h($rx[2]) . '</td><td>' . h($rx[3]) . '</td><td>' . h($rx[4]) . '</td><td>' . h($rx[5]) . '</td><td>' . h(estado_emision($rx)) . '</td><td>' . h($rx[8]) . '</td></tr>';
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
  $pdf->SetTitle('Emisión de documentos');
  $pdf->SetMargins(10, 12, 10);
  $pdf->SetAutoPageBreak(true, 12);
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->AddPage();
  $pdf->SetFont('helvetica', 'B', 13);
  $pdf->Cell(0, 8, 'SISGEDO — Documentos emitidos', 0, 1);
  $pdf->SetFont('helvetica', '', 8);
  $pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i') . '  |  Registros: ' . count($datosFiltrados), 0, 1);
  $pdf->Ln(2);
  $htmlPdf = '<table border="0.5" cellpadding="3"><thead><tr style="background-color:#e2e8f0;font-weight:bold;">'
    . '<th width="18%">Documento</th><th width="12%">Numero</th><th width="28%">Asunto</th><th width="16%">Destino</th><th width="12%">Estado</th><th width="14%">Fecha</th></tr></thead><tbody>';
  foreach ($datosFiltrados as $rp) {
    $htmlPdf .= '<tr><td width="18%">' . h($rp[1]) . '</td><td width="12%">' . h($rp[2]) . '</td><td width="28%">' . h($rp[3]) . '</td><td width="16%">' . h($rp[5]) . '</td><td width="12%">' . h(estado_emision($rp)) . '</td><td width="14%">' . h($rp[8]) . '</td></tr>';
  }
  $htmlPdf .= '</tbody></table>';
  $pdf->writeHTML($htmlPdf, true, false, true, false, '');
  $pdf->Output('emision_' . date('Ymd_His') . '.pdf', 'I');
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

// Código de verificación digital (MGD) de los documentos de esta página
$cvdMap = array();
try {
  require_once '../clases/gestion_documental.php';
  foreach ($datosPagina as $rCvd) {
    $c = sgd_obtener_cvd('EMITIDO', $rCvd[0]);
    if ($c) $cvdMap[$rCvd[0]] = sgd_formato_cvd($c);
  }
} catch (Exception $e) {
  error_log('v_docs_emitidos CVD: ' . $e->getMessage());
}

// Firma digital (FIRMA PERÚ): estado de firma de los documentos de esta página
require_once '../conexion/firmaperu_config.php';
$firmaActiva = sgd_firmaperu_activo();
$firmaMap = array();
try {
  if (count($datosPagina) > 0) {
    $idsF = array_map(function ($x) { return intval($x[0]); }, $datosPagina);
    // Las filas de un mismo envío (una por destino) comparten el adjunto: la firma vale para todas.
    $stF = sgd_db()->prepare("SELECT de.iddocs_emitidos, GROUP_CONCAT(DISTINCT IF(f.tipo_firma='FIRMA','Firmado','V°B°') ORDER BY f.tipo_firma SEPARATOR ' + ')
                                FROM docs_emitidos de
                                INNER JOIN docs_emitidos g ON g.adjunto = de.adjunto AND g.idoficina_registro = de.idoficina_registro
                                INNER JOIN sgd_firma f ON f.iddocs_emitidos = g.iddocs_emitidos AND f.estado = 'FIRMADO'
                               WHERE de.adjunto IS NOT NULL AND de.iddocs_emitidos IN (" . implode(',', $idsF) . ")
                               GROUP BY de.iddocs_emitidos");
    $stF->execute();
    foreach ($stF->fetchAll(PDO::FETCH_NUM) as $f) $firmaMap[$f[0]] = $f[1];
  }
} catch (Exception $e) {
  error_log('v_docs_emitidos firma: ' . $e->getMessage());
}

$queryBase = array('q' => $qFiltro, 'desde' => $desdeFiltro, 'hasta' => $hastaFiltro, 'estado' => $estadoFiltro, 'sort' => $sortFiltro, 'per_page' => $porPagina);
$emitidosVentana = count($datos);
$tok = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Emisión de Documentos — SISGEDO</title>
  <link rel="icon" type="image/png" href="../<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css?v=<?= @filemtime(__DIR__ . '/../css/sisgedo-theme.css') ?>" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css?v=<?= @filemtime(__DIR__ . '/../css/v_recepcion_proveido.css') ?>" />
  <link rel="stylesheet" href="../css/v_docs_emitidos.css?v=<?= @filemtime(__DIR__ . '/../css/v_docs_emitidos.css') ?>" />
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
      <?php $NAV_ACTIVO = 'vistas/v_docs_emitidos.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <!-- CONTENIDO -->
      <div class="main">
        <h1 class="t">Emisión de Documentos</h1>
        <div class="subt">Crea y gestiona los documentos internos de tu oficina — mostrando del <?= h(date('d/m/Y', strtotime($desdeSql))) ?> al <?= h(date('d/m/Y', strtotime($hastaSql))) ?> (amplía con los filtros de fecha)</div>

        <div class="kpis">
          <?php
          $cards = [
            ["Emitidos", number_format($emitidosVentana), "En el período", "fa-folder-o", "t-blue", "#1e293b", "lab-blue", ""],
            ["Por recepcionar", number_format($cont['por_recepcionar']), "Aún no recibidos", "fa-clock-o", "t-orange", "#f97316", "lab-orange", "Por recepcionar"],
            ["Recepcionados", number_format($cont['recepcionados']), "Recibidos en destino", "fa-check-circle", "t-green", "#16a34a", "lab-green", "Recepcionado"],
            ["Sin destino", number_format($cont['sin_destino']), "Docs. de respuesta", "fa-question-circle", "t-purple", "#6d28d9", "lab-purple", "Sin destino"],
          ];
          foreach ($cards as $c) {
            $kpiOn = ($c[7] !== '' && norm_txt_emi($c[7]) === norm_txt_emi($estadoFiltro));
          ?>
            <div class="kpi<?= $kpiOn ? ' kpi-on' : '' ?>" <?php if ($c[7] !== '') { ?> style="cursor:pointer" onclick="setEstado('<?= $c[7] ?>')" <?php } ?>>
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
              <div class="inp"><input id="buscador" value="<?= h($qFiltro) ?>" placeholder="Buscar por documento, número, asunto..." /><i class="fa fa-search"></i></div>
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
                  <option value="Por recepcionar" <?= $estadoFiltro === 'Por recepcionar' ? 'selected' : '' ?>>Por recepcionar</option>
                  <option value="Recepcionado" <?= $estadoFiltro === 'Recepcionado' ? 'selected' : '' ?>>Recepcionado</option>
                  <option value="Sin destino" <?= $estadoFiltro === 'Sin destino' ? 'selected' : '' ?>>Sin destino</option>
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
              <button class="btn btn-p btn-sm" id="btnNuevoDoc"><i class="fa fa-plus"></i> Nuevo documento</button>
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
            <span>Documento</span><span>Asunto</span><span>Detalles</span><span>Destino</span><span>Estado</span><span>Fecha</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($datos) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No hay documentos emitidos en el rango seleccionado (por defecto, últimos 30 días).</div>
            <?php }
            foreach ($datosPagina as $i => $r) {
              $est = estado_emision($r);
              $dotE = ($est === 'Recepcionado') ? 'd-green' : (($est === 'Sin destino') ? 'd-amber' : 'd-orange');
              $editable = (intval($r[6]) === 0);
              $adj = $r[7];
            ?>
              <div class="row rowg <?= $i === 0 ? 'sel' : '' ?>" data-id="<?= h($r[0]) ?>" data-doc="<?= h($r[1] . ' N° ' . $r[2]) ?>" data-iddoc="<?= h($r[10]) ?>" data-num="<?= h($r[2]) ?>" data-asunto="<?= h($r[3]) ?>" data-det="<?= h($r[4]) ?>" data-destino="<?= h($r[5]) ?>" data-idofi="<?= h($r[11]) ?>" data-estado="<?= h($est) ?>" data-fecha="<?= h($r[8]) ?>" data-adj="<?= h($adj) ?>" data-editable="<?= $editable ? '1' : '0' ?>" data-cvd="<?= h(isset($cvdMap[$r[0]]) ? $cvdMap[$r[0]] : '') ?>" data-firma="<?= h(isset($firmaMap[$r[0]]) ? $firmaMap[$r[0]] : '') ?>" data-pdf="<?= (is_string($adj) && strtolower(substr($adj, -4)) === '.pdf') ? '1' : '0' ?>">
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa fa-file-text-o" style="color:#3b82f6;margin-top:2px"></i><span class="nm"><?= h($r[1]) ?> <?= h($r[2]) ?></span></div><span class="badge b-purple"><?= h(strtoupper($r[1])) ?></span>
                </div>
                <div class="dcell">
                  <p class="asun"><?= h($r[3]) ?></p>
                </div>
                <div class="dcell">
                  <p class="asun"><?= h($r[4]) ?></p>
                </div>
                <div class="dcell"><span class="cell-txt"><?= $r[5] !== '' ? h($r[5]) : '—' ?></span></div>
                <div><span class="st"><span class="dot <?= $dotE ?>"></span> <?= h($est) ?></span></div>
                <div class="muted"><?= h($r[8]) ?></div>
                <div class="act">
                  <a class="ab ab-b" title="Seguimiento" target="_blank" href="../reportes/consulta_interna.php?codigo_doc=<?= h($r[0]) ?>" onclick="event.stopPropagation()"><i class="fa fa-eye"></i></a>
                  <?php if ($editable) { ?>
                    <button class="ab ab-g" title="Editar" onclick="event.stopPropagation();abrirEditar(this)"><i class="fa fa-pencil"></i></button>
                    <button class="ab ab-r" title="Eliminar" onclick="event.stopPropagation();eliminarDoc(this)"><i class="fa fa-trash-o"></i></button>
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
                <a class="pg" href="<?= h(page_url_emi(1, $queryBase)) ?>"><i class="fa fa-angle-double-left"></i></a>
                <a class="pg <?= $paginaActual > 1 ? '' : 'disabled' ?>" href="<?= h(page_url_emi(max(1, $paginaActual - 1), $queryBase)) ?>"><i class="fa fa-angle-left"></i></a>
                <?php
                $ventana = 2;
                $inicioBotones = max(1, $paginaActual - $ventana);
                $finBotones = min($totalPaginas, $paginaActual + $ventana);
                if ($inicioBotones > 1) {
                  echo '<a class="pg" href="' . h(page_url_emi(1, $queryBase)) . '">1</a>';
                  if ($inicioBotones > 2) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                }
                for ($p = $inicioBotones; $p <= $finBotones; $p++) {
                  $cls = $p === $paginaActual ? 'pg on' : 'pg';
                  echo '<a class="' . $cls . '" href="' . h(page_url_emi($p, $queryBase)) . '">' . $p . '</a>';
                }
                if ($finBotones < $totalPaginas) {
                  if ($finBotones < $totalPaginas - 1) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                  echo '<a class="pg" href="' . h(page_url_emi($totalPaginas, $queryBase)) . '">' . $totalPaginas . '</a>';
                }
                ?>
                <a class="pg <?= $paginaActual < $totalPaginas ? '' : 'disabled' ?>" href="<?= h(page_url_emi(min($totalPaginas, $paginaActual + 1), $queryBase)) ?>"><i class="fa fa-angle-right"></i></a>
                <a class="pg" href="<?= h(page_url_emi($totalPaginas, $queryBase)) ?>"><i class="fa fa-angle-double-right"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles del documento</h2>
        </div>
        <div class="bd">
          <?php if ($first) { ?>
            <div class="docbox">
              <div class="ic"><i class="fa fa-file-text-o"></i></div>
              <div style="min-width:0">
                <div id="d-doc" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($first[1] . ' N° ' . $first[2]) ?></div><span id="d-badge" class="badge b-purple"><?= h(strtoupper($first[1])) ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Información general</div>
              <dl>
                <div>
                  <dt>Destino</dt>
                  <dd id="d-destino"><?= $first[5] !== '' ? h($first[5]) : '—' ?></dd>
                </div>
                <div>
                  <dt>Estado</dt>
                  <dd id="d-estado"><?= h(estado_emision($first)) ?></dd>
                </div>
                <div>
                  <dt>Fecha</dt>
                  <dd id="d-fecha"><?= h($first[8]) ?></dd>
                </div>
                <div>
                  <dt>Cód. verificación</dt>
                  <?php $cvdFirst = isset($cvdMap[$first[0]]) ? $cvdMap[$first[0]] : ''; ?>
                  <dd><span id="d-cvd" class="cvd-code"><?= $cvdFirst !== '' ? h($cvdFirst) : '—' ?></span>
                    <a id="d-cvd-link" class="cvd-link" target="_blank" href="../verificar.php?c=<?= h($cvdFirst) ?>" style="<?= $cvdFirst === '' ? 'display:none' : '' ?>" title="Abrir la página pública de verificación"><i class="fa fa-qrcode"></i> Verificar</a></dd>
                </div>
                <div>
                  <dt>Firma digital</dt>
                  <?php $fFirst = isset($firmaMap[$first[0]]) ? $firmaMap[$first[0]] : ''; ?>
                  <dd id="d-firma"><?= $fFirst !== '' ? '<span class="firma-ok"><i class="fa fa-certificate"></i> ' . h($fFirst) . '</span>' : '<span class="muted">Sin firma digital</span>' ?></dd>
                </div>
              </dl>
              <?php if ($firmaActiva) { ?>
                <?php $firstPdf = is_string($first[7]) && strtolower(substr($first[7], -4)) === '.pdf'; ?>
                <div id="d-firmar" class="firma-acc" style="<?= ($firstPdf && intval($first[6]) === 0) ? '' : 'display:none' ?>">
                  <button class="btn btn-p btn-sm" onclick="firmarDoc('FIRMA')"><i class="fa fa-pencil-square-o"></i> Firmar digitalmente</button>
                  <button class="btn btn-sm" onclick="firmarDoc('VISTO_BUENO')"><i class="fa fa-check-square-o"></i> Dar V° B°</button>
                  <div class="muted" style="font-size:11px;margin-top:6px">Requiere el adjunto en PDF, su DNIe o certificado digital y el cliente FIRMA PERÚ instalado.</div>
                </div>
              <?php } ?>
              <div class="sect">Asunto</div>
              <p id="d-asunto" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[3]) ?></p>
              <div class="sect">Detalles</div>
              <p id="d-det" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[4]) ?></p>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Selecciona un documento para ver sus detalles.</div><?php } ?>
        </div>
        <div class="ftr">
          <button class="btn btn-p" style="flex:1;justify-content:center" onclick="abrirNuevo()"><i class="fa fa-plus"></i> Nuevo documento</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL NUEVO / EDITAR DOCUMENTO -->
  <div class="modal" id="modalDoc">
    <div class="box">
      <div class="mh">
        <h3 id="md_titulo">Nuevo documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarDoc()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>Tipo de documento <span class="req">*</span></label><select id="md_tipo">
            <option value="">Seleccione...</option><?php foreach ($catDocumentos as $d) { ?><option value="<?= h($d[0]) ?>"><?= h($d[1]) ?></option><?php } ?>
          </select></div>
        <div id="md_num_modo_wrap"><label>Numeración</label>
          <div class="num-modo">
            <label><input type="radio" name="md_num_modo" value="auto" checked> Automática <span class="muted">(recomendado)</span></label>
            <label><input type="radio" name="md_num_modo" value="manual"> Manual <span class="muted">(excepcional)</span></label>
          </div>
        </div>
        <div><label>Número <span class="req">*</span></label><input id="md_numero" type="text" placeholder="Ej. 001-2026-MPH/OFI" />
          <div id="md_num_ayuda" class="muted" style="font-size:11px;margin-top:4px"></div></div>
        <div><label>Asunto <span class="req">*</span></label><input id="md_asunto" type="text" placeholder="Asunto del documento" /></div>
        <div><label>Detalles</label><textarea id="md_detalles" rows="2" placeholder="Contenido o indicaciones (opcional)"></textarea></div>
        <div id="md_ofi_wrap"><label>Enviar a (oficinas destino)</label>
          <div class="msel" id="md_ofi_msel">
            <div class="msel-control" onclick="toggleMsel('md_ofi_msel')">
              <div class="msel-tags" id="md_ofi_tags"><span class="msel-ph">Sin destino (opcional)...</span></div><i class="fa fa-angle-down"></i>
            </div>
            <div class="msel-panel">
              <input type="text" id="md_ofi_buscar" class="ofi-buscar" placeholder="Buscar oficina..." autocomplete="off" />
              <div class="msel-lista" id="md_oficinas">
                <?php foreach ($catOficinas as $o) { ?>
                  <label class="ofi-item"><input type="checkbox" value="<?= h($o[0]) ?>"> <span><?= h($o[1]) ?></span></label>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="muted" style="font-size:11px;margin-top:4px">Sin selección, el documento queda "sin destino" (p. ej. para usarlo como respuesta).</div>
        </div>
        <div id="md_adj_wrap"><label>Adjunto</label><input id="md_fichero" type="file" /></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarDoc()">Cancelar</button><button class="btn btn-p" id="md_guardar"><i class="fa fa-check"></i> <span id="md_btn_txt">Emitir documento</span></button></div>
    </div>
  </div>

  <?php if ($firmaActiva) { ?>
    <!-- Componente web de FIRMA PERÚ (requiere jQuery 3.6.0 en la variable jqFirmaPeru) -->
    <div id="addComponent" style="display:none;"></div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>var jqFirmaPeru = jQuery.noConflict(true);</script>
    <script src="<?= h(SGD_FIRMAPERU_JS) ?>"></script>
  <?php } ?>
  <script src="../js/v_docs_emitidos.js?v=<?= @filemtime(__DIR__ . '/../js/v_docs_emitidos.js') ?>"></script>
</body>

</html>