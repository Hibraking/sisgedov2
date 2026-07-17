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
require_once '../clases/recepcion_proveido.php';

$contexto = function_exists('sisgedo_contexto_usuario_actual') ? sisgedo_contexto_usuario_actual() : array();
$idoficina = isset($contexto['idoficina']) ? intval($contexto['idoficina']) : (isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0);
$idresponsable = isset($contexto['idresponsable']) ? intval($contexto['idresponsable']) : (isset($_SESSION['idresponsable']) ? intval($_SESSION['idresponsable']) : 0);
$alias     = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';

// ---- Filtros (se leen ANTES de consultar: el Estado decide la fuente de datos) ----
$qFiltro = isset($_GET['q']) ? trim($_GET['q']) : '';
$desdeFiltro = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hastaFiltro = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$tipoFiltro = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'Todos';
$origenFiltro = isset($_GET['origen']) ? trim($_GET['origen']) : 'Todos';
$estadoFiltro = isset($_GET['estado']) ? trim($_GET['estado']) : 'Todos';
$sortFiltro = isset($_GET['sort']) ? trim($_GET['sort']) : 'recentes';
$porPagina = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;

// Fuente de datos según Estado: pendientes (por defecto), recepcionados o derivados.
// Terminología oficial del trámite documentario peruano: "derivado"
// ("enviado" se acepta en la URL por compatibilidad con enlaces antiguos).
$estadoLower = strtolower($estadoFiltro);
if ($estadoLower === 'derivado') {
  $estadoLower = 'enviado';
}
$vistaEstado = ($estadoLower === 'recepcionado') ? 'recepcionado' : (($estadoLower === 'enviado') ? 'enviado' : 'pendiente');
if ($vistaEstado === 'enviado') {
  $estadoFiltro = 'Derivado';
} // canoniza etiqueta para filtros y enlaces
$colOrigen = ($vistaEstado === 'enviado') ? 'Oficina destino' : 'Origen';
$colFecha  = ($vistaEstado === 'enviado') ? 'Fecha de derivación' : 'Fecha recepción';
$sufijoExport = ($vistaEstado === 'pendiente') ? 'pendientes' : (($vistaEstado === 'recepcionado') ? 'recepcionados' : 'derivados');

// Ventana de fechas para consultar recepcionados/enviados (por defecto: últimos 30 días)
$hoySistema = date('Y-m-d');
$desdeSql = ($desdeFiltro !== '') ? $desdeFiltro : date('Y-m-d', strtotime('-30 days'));
$hastaSql = ($hastaFiltro !== '') ? $hastaFiltro : $hoySistema;

$dml = new Recepcion_proveido();
if ($vistaEstado === 'recepcionado') {
  $datos = $dml->datos_recepcionados($idoficina, $desdeSql, $hastaSql);
} elseif ($vistaEstado === 'enviado') {
  $datos = $dml->datos_enviados($idoficina, $desdeSql, $hastaSql);
} else {
  $datos = $dml->datos_por_recepcionar($idoficina);
}
$cont = $dml->contadores($idoficina);

$pendientes = ($vistaEstado === 'pendiente') ? count($datos) : $dml->contador_pendientes($idoficina);

// KPIs propios según la bandeja (Derivación tiene identidad propia)
$recepHoyKpi = 0;
$envHoyKpi = 0;
$envMesKpi = 0;
$destinosMesKpi = 0;
try {
  $pdoKpi = (new Conexion())->conectar();
  if ($vistaEstado === 'enviado') {
    $qk = $pdoKpi->prepare("select count(*) from movimientos m inner join movimientos ma on m.idmovimientoant=ma.idMovimientos inner join responsables r on ma.idresponsable=r.idresponsables where r.idoficina=? and date(m.fecha_hora)=curdate()");
    $qk->execute(array($idoficina));
    $envHoyKpi = intval($qk->fetchColumn());
    $qk = $pdoKpi->prepare("select count(*) from movimientos m inner join movimientos ma on m.idmovimientoant=ma.idMovimientos inner join responsables r on ma.idresponsable=r.idresponsables where r.idoficina=? and year(m.fecha_hora)=year(now()) and month(m.fecha_hora)=month(now())");
    $qk->execute(array($idoficina));
    $envMesKpi = intval($qk->fetchColumn());
    $qk = $pdoKpi->prepare("select count(distinct m.idoficina_derivar) from movimientos m inner join movimientos ma on m.idmovimientoant=ma.idMovimientos inner join responsables r on ma.idresponsable=r.idresponsables where r.idoficina=? and year(m.fecha_hora)=year(now()) and month(m.fecha_hora)=month(now())");
    $qk->execute(array($idoficina));
    $destinosMesKpi = intval($qk->fetchColumn());
  } else {
    $qk = $pdoKpi->prepare("select count(*) from movimientos where idoficina_derivar=? and estado=1 and date(fecha_hora)=curdate()");
    $qk->execute(array($idoficina));
    $recepHoyKpi = intval($qk->fetchColumn());
  }
} catch (Exception $eKpi) {
  error_log($eKpi->getMessage());
}
// Urgentes: pendientes con mas de 7 dias sin recepcionar (solo aplica a la bandeja de pendientes)
$urgentes = null;
if ($vistaEstado === 'pendiente') {
  $urgentes = 0;
  $lim7 = strtotime('-7 days');
  foreach ($datos as $d) {
    $t = (!empty($d[12])) ? strtotime($d[12]) : false;
    if ($t !== false && $t < $lim7) $urgentes++;
  }
}
$fechaMinRegistro = date('Y-m-d');
$fechaMaxRegistro = date('Y-m-d');
foreach ($datos as $d) {
  if (empty($d[12])) continue;
  $ts = strtotime($d[12]);
  if ($ts === false) continue;
  $fecha = date('Y-m-d', $ts);
  if ($fecha < $fechaMinRegistro) $fechaMinRegistro = $fecha;
  if ($fecha > $fechaMaxRegistro) $fechaMaxRegistro = $fecha;
}
$fechaMaxFiltro = date('Y-m-d');
if ($fechaMaxRegistro > $fechaMaxFiltro) $fechaMaxRegistro = $fechaMaxFiltro;
if ($vistaEstado !== 'pendiente') {
  // En recepcionados/enviados el usuario puede ampliar la ventana hacia atrás con el filtro "Desde"
  $fechaMinRegistro = date('Y-m-d', strtotime('-5 years'));
}

$tok = csrf_token();

function norm_txt($text)
{
  $text = trim((string)$text);
  return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}
function page_url($page, $params)
{
  // URL limpia: omite los parámetros con su valor por defecto (y page=1)
  if (intval($page) > 1) {
    $params['page'] = intval($page);
  } else {
    unset($params['page']);
  }
  $defaults = array('q' => '', 'desde' => '', 'hasta' => '', 'tipo' => 'Todos', 'origen' => 'Todos', 'estado' => 'Todos', 'sort' => 'recentes', 'per_page' => '25');
  foreach ($defaults as $k => $v) {
    if (array_key_exists($k, $params) && (string)$params[$k] === (string)$v) {
      unset($params[$k]);
    }
  }
  $qs = http_build_query($params);
  return $qs === '' ? 'v_recepcion_proveido.php' : '?' . $qs;
}

function badge_doc_color($nombre)
{
  $n = strtoupper($nombre);
  if (strpos($n, 'OFICIO') !== false) return 'purple';
  if (strpos($n, 'INFORME') !== false) return 'blue';
  return 'green';
}
function hace($f)
{
  $t = strtotime($f);
  if (!$t) return '';
  $s = time() - $t;
  if ($s < 0) $s = 0;
  if ($s < 60) return 'Hace ' . $s . ' seg';
  if ($s < 3600) return 'Hace ' . floor($s / 60) . ' min';
  if ($s < 86400) {
    $h = floor($s / 3600);
    return 'Hace ' . $h . ' hora' . ($h > 1 ? 's' : '');
  }
  $x = floor($s / 86400);
  return 'Hace ' . $x . ' día' . ($x > 1 ? 's' : '');
}
function estado_recepcion_label($tipo)
{
  $tipo = trim((string)$tipo);
  if ($tipo === '1') return 'Registrado, sin recepcionar';
  if ($tipo === '2') return 'Emitido, sin recepcionar';
  if ($tipo === '3') return 'Derivado, sin recepcionar';
  if ($tipo === 'R') return 'Recepcionado';
  if ($tipo === 'E') return 'Derivado';
  return $tipo !== '' ? $tipo : 'Por recepcionar';
}

if ($desdeFiltro !== '' && $desdeFiltro < $fechaMinRegistro) $desdeFiltro = $fechaMinRegistro;
if ($desdeFiltro !== '' && $desdeFiltro > $fechaMaxFiltro) $desdeFiltro = $fechaMaxFiltro;
if ($hastaFiltro !== '' && $hastaFiltro < $fechaMinRegistro) $hastaFiltro = $fechaMinRegistro;
if ($hastaFiltro !== '' && $hastaFiltro > $fechaMaxFiltro) $hastaFiltro = $fechaMaxFiltro;

$tiposDisponibles = array();
$origenesDisponibles = array();
$estadosDisponibles = array();
foreach ($datos as $r) {
  $tipo = strtoupper(trim((string)$r[3]));
  $origen = trim((string)$r[1]);
  $estado = estado_recepcion_label(isset($r[11]) ? $r[11] : '');
  if ($tipo !== '') $tiposDisponibles[$tipo] = $tipo;
  if ($origen !== '') $origenesDisponibles[$origen] = $origen;
  if ($estado !== '') $estadosDisponibles[$estado] = $estado;
}
ksort($tiposDisponibles);
asort($origenesDisponibles);
ksort($estadosDisponibles);

$datosFiltrados = array_values(array_filter($datos, function ($r) use ($qFiltro, $desdeFiltro, $hastaFiltro, $tipoFiltro, $origenFiltro, $estadoFiltro) {
  $texto = norm_txt(implode(' ', array_map(function ($valor) {
    return is_scalar($valor) ? (string)$valor : '';
  }, $r)));

  if ($qFiltro !== '' && strpos($texto, norm_txt($qFiltro)) === false) return false;
  if ($tipoFiltro !== 'Todos' && norm_txt(isset($r[3]) ? $r[3] : '') !== norm_txt($tipoFiltro)) return false;
  if ($origenFiltro !== 'Todos' && norm_txt(isset($r[1]) ? $r[1] : '') !== norm_txt($origenFiltro)) return false;
  if ($estadoFiltro !== 'Todos' && norm_txt(estado_recepcion_label(isset($r[11]) ? $r[11] : '')) !== norm_txt($estadoFiltro)) return false;

  $ts = isset($r[12]) ? strtotime($r[12]) : false;
  if ($desdeFiltro !== '') {
    $desdeTs = strtotime($desdeFiltro . ' 00:00:00');
    if ($ts === false || $ts < $desdeTs) return false;
  }
  if ($hastaFiltro !== '') {
    $hastaTs = strtotime($hastaFiltro . ' 23:59:59');
    if ($ts === false || $ts > $hastaTs) return false;
  }
  return true;
}));

usort($datosFiltrados, function ($a, $b) use ($sortFiltro) {
  $ta = isset($a[12]) ? strtotime($a[12]) : 0;
  $tb = isset($b[12]) ? strtotime($b[12]) : 0;
  switch ($sortFiltro) {
    case 'antiguos':
      return $ta <=> $tb;
    case 'exp_asc':
      return strnatcmp((string)($a[4] ?? ''), (string)($b[4] ?? ''));
    case 'exp_desc':
      return strnatcmp((string)($b[4] ?? ''), (string)($a[4] ?? ''));
    case 'recentes':
    default:
      return $tb <=> $ta;
  }
});

// ================= EXPORTACIONES (CSV / Excel / PDF) =================
// Descargan TODOS los registros filtrados, no solo la página visible.
$exportFmt = isset($_GET['export']) ? $_GET['export'] : '';

if ($exportFmt === 'csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="recepcion_' . $sufijoExport . '_' . date('Ymd_His') . '.csv"');
  echo "\xEF\xBB\xBF"; // BOM para que Excel reconozca UTF-8
  $out = fopen('php://output', 'w');
  fputcsv($out, array('Expediente', 'Documento', 'Numero', $colOrigen, 'Tramite', 'Asunto', 'Detalles', 'Estado', $colFecha), ';');
  foreach ($datosFiltrados as $rcsv) {
    fputcsv($out, array($rcsv[4], $rcsv[3], $rcsv[5], $rcsv[1], $rcsv[2], $rcsv[6], $rcsv[7], estado_recepcion_label(isset($rcsv[11]) ? $rcsv[11] : ''), $rcsv[9]), ';');
  }
  fclose($out);
  exit;
}

if ($exportFmt === 'xls') {
  // Excel: tabla HTML con cabecera application/vnd.ms-excel (no requiere librerías)
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="recepcion_' . $sufijoExport . '_' . date('Ymd_His') . '.xls"');
  echo "\xEF\xBB\xBF";
  echo '<table border="1"><tr style="background:#e2e8f0;font-weight:bold"><th>Expediente</th><th>Documento</th><th>Numero</th><th>' . h($colOrigen) . '</th><th>Tramite</th><th>Asunto</th><th>Detalles</th><th>Estado</th><th>' . h($colFecha) . '</th></tr>';
  foreach ($datosFiltrados as $rx) {
    echo '<tr><td>' . h($rx[4]) . '</td><td>' . h($rx[3]) . '</td><td>' . h($rx[5]) . '</td><td>' . h($rx[1]) . '</td><td>' . h($rx[2]) . '</td><td>' . h($rx[6]) . '</td><td>' . h($rx[7]) . '</td><td>' . h(estado_recepcion_label(isset($rx[11]) ? $rx[11] : '')) . '</td><td>' . h($rx[9]) . '</td></tr>';
  }
  echo '</table>';
  exit;
}

if ($exportFmt === 'pdf') {
  // PDF con TCPDF (la librería ya existe en el proyecto). Se hace chdir para
  // que tcpdf_include.php resuelva sus rutas internas de configuración.
  $cwd_prev = getcwd();
  chdir(__DIR__ . '/../tcpdf/examples');
  require_once __DIR__ . '/../tcpdf/examples/tcpdf_include.php';
  chdir($cwd_prev);
  $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
  $pdf->SetCreator('SISGEDO');
  $pdf->SetTitle('Recepción de documentos — ' . ucfirst($sufijoExport));
  $pdf->SetMargins(10, 12, 10);
  $pdf->SetAutoPageBreak(true, 12);
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->AddPage();
  $pdf->SetFont('helvetica', 'B', 13);
  $pdf->Cell(0, 8, 'SISGEDO — Documentos ' . $sufijoExport, 0, 1);
  $pdf->SetFont('helvetica', '', 8);
  $pdf->Cell(0, 5, 'Oficina: ' . $idoficina . '  |  Generado: ' . date('d/m/Y H:i') . '  |  Registros: ' . count($datosFiltrados), 0, 1);
  $pdf->Ln(2);
  $htmlPdf = '<table border="0.5" cellpadding="3"><thead><tr style="background-color:#e2e8f0;font-weight:bold;">'
    . '<th width="9%">Expediente</th><th width="16%">Documento</th><th width="16%">' . h($colOrigen) . '</th><th width="33%">Asunto</th><th width="12%">Estado</th><th width="14%">' . h($colFecha) . '</th></tr></thead><tbody>';
  foreach ($datosFiltrados as $rp) {
    $htmlPdf .= '<tr><td width="9%">' . h($rp[4]) . '</td><td width="16%">' . h($rp[3] . ' ' . $rp[5]) . '</td><td width="16%">' . h($rp[1]) . '</td><td width="33%">' . h($rp[6]) . '</td><td width="12%">' . h(estado_recepcion_label(isset($rp[11]) ? $rp[11] : '')) . '</td><td width="14%">' . h($rp[9]) . '</td></tr>';
  }
  $htmlPdf .= '</tbody></table>';
  $pdf->writeHTML($htmlPdf, true, false, true, false, '');
  $pdf->Output('recepcion_' . $sufijoExport . '_' . date('Ymd_His') . '.pdf', 'I');
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

$queryBase = array(
  'q' => $qFiltro,
  'desde' => $desdeFiltro,
  'hasta' => $hastaFiltro,
  'tipo' => $tipoFiltro,
  'origen' => $origenFiltro,
  'estado' => $estadoFiltro,
  'sort' => $sortFiltro,
  'per_page' => $porPagina,
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $vistaEstado === 'enviado' ? 'Documentos Derivados' : 'Recepción de Documentos' ?> — SISGEDO</title>
  <link rel="icon" type="image/png" href="../images/icono sistema.jpg" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body data-csrf="<?= h($tok) ?>" data-idoficina="<?= h($idoficina) ?>" data-idresponsable="<?= h($idresponsable) ?>">
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
            <div class="nm"><?= htmlspecialchars($alias) ?></div>
          </div>
          <a href="../clases/cerrarsesion.php" class="ib" title="Salir"><i class="fa fa-sign-out"></i></a>
        </div>
      </div>
    </div>

    <div class="body">
      <?php
      $NAV_ACTIVO = ($vistaEstado === 'enviado') ? 'vistas/v_recepcion_proveido.php?estado=Derivado' : 'vistas/v_recepcion_proveido.php';
      include __DIR__ . '/../partes/nav_nuevo.php';
      ?>

      <!-- CONTENIDO -->
      <div class="main">
        <h1 class="t"><?= $vistaEstado === 'enviado' ? 'Documentos Derivados' : 'Recepción de Documentos' ?></h1>
        <div class="subt"><?= $vistaEstado === 'enviado' ? 'Documentos derivados por tu oficina a otras dependencias' : 'Administra los documentos recibidos por la entidad' ?><?php if ($vistaEstado !== 'pendiente') { ?> — mostrando <b><?= $sufijoExport ?></b> del <?= h(date('d/m/Y', strtotime($desdeSql))) ?> al <?= h(date('d/m/Y', strtotime($hastaSql))) ?> (use los filtros de fecha para ampliar el rango)<?php } ?></div>

        <div class="kpis">
          <?php
          // KPIs propios de cada bandeja: Derivación (enviado) muestra sus propios
          // indicadores; Recepción muestra los suyos (sin duplicar la vista de enviados).
          if ($vistaEstado === 'enviado') {
            $cards = [
              ["Derivados hoy", number_format($envHoyKpi), "Documentos derivados hoy", "fa-paper-plane", "t-purple", "#6d28d9", "lab-purple", ""],
              ["Este mes", number_format($envMesKpi), "Derivaciones de " . date('m/Y'), "fa-calendar", "t-blue", "#1e293b", "lab-blue", ""],
              ["Destinos", number_format($destinosMesKpi), "Oficinas distintas este mes", "fa-building-o", "t-green", "#16a34a", "lab-green", ""],
              ["Histórico", number_format($cont['enviados']), "Total de derivaciones", "fa-archive", "t-orange", "#f97316", "lab-orange", ""],
            ];
          } else {
            $cards = [
              ["Pendientes", number_format($pendientes), "Por recepcionar", "fa-inbox", "t-blue", "#1e293b", "lab-blue", "Todos"],
              ["Recepcionados hoy", number_format($recepHoyKpi), "Completados el día de hoy", "fa-calendar-check-o", "t-purple", "#6d28d9", "lab-purple", "Recepcionado"],
              ["Recepcionados", number_format($cont['recepcionados']), "Completados (histórico)", "fa-check-circle", "t-green", "#16a34a", "lab-green", "Recepcionado"],
              ["Urgentes", ($urgentes === null ? '—' : number_format($urgentes)), "Requieren atención", "fa-exclamation-triangle", "t-orange", "#f97316", "lab-orange", "Todos"],
            ];
          }
          foreach ($cards as $idx => $c) {
            $kpiOn = $vistaEstado !== 'enviado' && (($idx === 0 && $vistaEstado === 'pendiente') || ($idx === 2 && $vistaEstado === 'recepcionado'));
          ?>
            <div class="kpi<?= $kpiOn ? ' kpi-on' : '' ?>" <?php if ($c[7] !== '') { ?> style="cursor:pointer" title="Ver <?= strtolower($c[0]) ?>" onclick="setEstado('<?= $c[7] ?>')" <?php } ?>>
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
              <div class="inp"><input id="buscador" value="<?= h($qFiltro) ?>" placeholder="Buscar por expediente, documento, asunto..." /><i class="fa fa-search"></i></div>
            </div>
            <div class="fld"><label>Desde</label>
              <div class="inp"><input id="desde" type="date" min="<?= h($fechaMinRegistro) ?>" max="<?= h($fechaMaxFiltro) ?>" value="<?= h($desdeFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Hasta</label>
              <div class="inp"><input id="hasta" type="date" min="<?= h($fechaMinRegistro) ?>" max="<?= h($fechaMaxFiltro) ?>" value="<?= h($hastaFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Tipo documento</label>
              <div class="inp" style="padding:0"><select id="tipo" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="Todos" <?= $tipoFiltro === 'Todos' ? 'selected' : '' ?>>Todos</option><?php foreach ($tiposDisponibles as $tipoOpt) { ?><option value="<?= h($tipoOpt) ?>" <?= norm_txt($tipoFiltro) === norm_txt($tipoOpt) ? 'selected' : '' ?>><?= h($tipoOpt) ?></option><?php } ?>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div class="fld"><label>Origen</label>
              <div class="inp" style="padding:0"><select id="origen" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="Todos" <?= $origenFiltro === 'Todos' ? 'selected' : '' ?>>Todos</option><?php foreach ($origenesDisponibles as $origenOpt) { ?><option value="<?= h($origenOpt) ?>" <?= norm_txt($origenFiltro) === norm_txt($origenOpt) ? 'selected' : '' ?>><?= h($origenOpt) ?></option><?php } ?>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div class="fld"><label>Estado</label>
              <div class="inp" style="padding:0"><select id="estado" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px"><?php
                                                                                                                                                                                    // Pendientes (con sus sub-estados) + las bandejas Recepcionado y Derivado
                                                                                                                                                                                    $opcionesEstado = $estadosDisponibles;
                                                                                                                                                                                    $opcionesEstado['Recepcionado'] = 'Recepcionado';
                                                                                                                                                                                    $opcionesEstado['Derivado'] = 'Derivado';
                                                                                                                                                                                    ?><option value="Todos" <?= $estadoFiltro === 'Todos' ? 'selected' : '' ?>>Pendientes (todos)</option><?php foreach ($opcionesEstado as $estadoOpt) { ?><option value="<?= h($estadoOpt) ?>" <?= norm_txt($estadoFiltro) === norm_txt($estadoOpt) ? 'selected' : '' ?>><?= h($estadoOpt) ?></option><?php } ?></select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
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
              <label class="selrow"><input type="checkbox" class="chk" id="selall"> Seleccionar todo</label>
              <?php if ($vistaEstado === 'pendiente') { ?>
                <button class="btn btn-p btn-sm" id="btnRecepSel"><i class="fa fa-check-circle"></i> Recepcionar seleccionados<span id="cntSel" class="selcount" style="display:none"></span></button>
                <button class="btn btn-sm" id="btnDerivar"><i class="fa fa-share"></i> Derivar</button>
              <?php } else { ?>
                <span class="muted" style="font-size:12px">Bandeja de <?= $sufijoExport ?><?= $vistaEstado === 'enviado' ? ' (solo consulta)' : '' ?><span id="cntSel" class="selcount"></span></span>
              <?php } ?>
              <div class="dd">
                <button class="btn btn-sm" id="btnExportar"><i class="fa fa-download"></i> Exportar <i class="fa fa-angle-down"></i></button>
                <div class="dd-menu">
                  <a href="#" id="expPdf"><i class="fa fa-file-pdf-o"></i> Descargar PDF</a>
                  <a href="#" id="expXls"><i class="fa fa-file-excel-o"></i> Descargar Excel (.xls)</a>
                  <a href="#" id="expCsv"><i class="fa fa-file-text-o"></i> Descargar CSV</a>
                  <a href="#" id="expPrint"><i class="fa fa-print"></i> Imprimir listado</a>
                </div>
              </div>
              <div class="dd">
                <button class="btn btn-sm" id="btnMas"><i class="fa fa-ellipsis-v"></i> Más acciones</button>
                <div class="dd-menu">
                  <a href="#" id="masActualizar"><i class="fa fa-refresh"></i> Actualizar bandeja</a>
                  <a href="#" id="masCopiar"><i class="fa fa-clipboard"></i> Copiar expedientes seleccionados</a>
                  <a href="#" id="masLimpiar"><i class="fa fa-eraser"></i> Limpiar filtros</a>
                </div>
              </div>
            </div>
            <div class="g"><span class="muted">Ordenar por:</span>
              <div class="inp btn-sm" style="width:160px;padding:0"><select id="sort" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="recentes" <?= $sortFiltro === 'recentes' ? 'selected' : '' ?>>Más recientes</option>
                  <option value="antiguos" <?= $sortFiltro === 'antiguos' ? 'selected' : '' ?>>Más antiguos</option>
                  <option value="exp_asc" <?= $sortFiltro === 'exp_asc' ? 'selected' : '' ?>>Expediente A-Z</option>
                  <option value="exp_desc" <?= $sortFiltro === 'exp_desc' ? 'selected' : '' ?>>Expediente Z-A</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div><button type="button" class="btn btn-sm" style="width:36px;padding:0" title="Aplicar orden" onclick="filtrar()"><i class="fa fa-sliders"></i></button>
            </div>
          </div>

          <div class="rowg thead">
            <span><input type="checkbox" class="chk" id="selall2"></span><span>Expediente</span><span>Documento</span><span><?= $colOrigen ?></span><span>Asunto</span><span>Estado</span><span><?= $colFecha ?></span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($datos) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px"><?= $vistaEstado === 'pendiente' ? 'No hay documentos por recepcionar en esta oficina.' : 'No hay documentos ' . $sufijoExport . ' en el rango de fechas seleccionado (por defecto, últimos 30 días).' ?></div>
            <?php }
            foreach ($datosPagina as $i => $r) {
              $dc = badge_doc_color($r[3]);
              $bcls = ['blue' => 'b-blue', 'purple' => 'b-purple', 'green' => 'b-green'][$dc];
              $icol = ['blue' => '#3b82f6', 'purple' => '#a855f7', 'green' => '#22c55e'][$dc];
              $ext = (stripos((string)$r[1], 'MESA DE PARTES') !== false);
              $oIcon = $ext ? 'fa-users' : 'fa-building-o';
              $oType = $ext ? 'EXTERNO' : 'INTERNO';
              $estadoFila = estado_recepcion_label(isset($r[11]) ? $r[11] : '');
              $dotFila = ($r[11] === 'R') ? 'd-blue' : (($r[11] === 'E') ? 'd-amber' : 'd-green');
              $fp = explode(' ', $r[9], 2);
              $adj = $r[10];
            ?>
              <div class="row rowg <?= $i === 0 ? 'sel' : '' ?>" data-codigo="<?= h($r[0]) ?>" data-tipo="<?= h($r[11]) ?>" data-exp="<?= h($r[4]) ?>" data-doc="<?= h($r[3] . ' N° ' . $r[5]) ?>" data-ori="<?= h($r[1]) ?>" data-tram="<?= h($r[2]) ?>" data-fecha="<?= h($r[9]) ?>" data-asunto="<?= h($r[6]) ?>" data-adj="<?= h($adj) ?>" data-estado="<?= h($estadoFila) ?>" data-badge="<?= h(strtoupper($r[3])) ?>" data-search="<?= strtolower(h($r[4] . ' ' . $r[3] . ' ' . $r[6] . ' ' . $r[1] . ' ' . $estadoFila)) ?>">
                <span><input type="checkbox" class="chk" onclick="event.stopPropagation()"></span>
                <span class="exp"><?= h($r[4]) ?></span>
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa fa-file-text-o" style="color:<?= $icol ?>;margin-top:2px"></i><span class="nm"><?= h($r[3]) ?> <?= h($r[5]) ?></span></div><span class="badge <?= $bcls ?>"><?= h(strtoupper($r[3])) ?></span>
                </div>
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa <?= $oIcon ?>" style="color:#94a3b8;margin-top:2px"></i><span class="cell-txt"><?= h($r[1]) ?></span></div><span class="badge b-gray"><?= $oType ?></span>
                </div>
                <div class="dcell">
                  <p class="asun"><?= h($r[6]) ?></p><?php if ((function_exists('mb_strlen') ? mb_strlen((string)$r[6], 'UTF-8') : strlen((string)$r[6])) > 72) { ?><button class="vermas" onclick="event.stopPropagation();toggleAsunto(this)" style="margin-top:4px;border:0;background:none;padding:0;font-size:12px;font-weight:500;color:#2563eb;cursor:pointer">Ver más</button><?php } ?>
                </div>
                <div><span class="st"><span class="dot <?= $dotFila ?>"></span> <?= h($estadoFila) ?></span></div>
                <div class="muted">
                  <div style="font-weight:500;color:#475569"><?= h($fp[0]) ?></div>
                  <div><?= isset($fp[1]) ? h($fp[1]) : '' ?></div>
                  <div style="color:#94a3b8;margin-top:2px"><?= hace($r[12]) ?></div>
                </div>
                <div class="act">
                  <?php if ($vistaEstado === 'pendiente') { ?><button class="ab ab-g" title="Recepcionar" onclick="event.stopPropagation();abrirRecep('<?= h($r[0]) ?>','<?= h($r[11]) ?>')"><i class="fa fa-download"></i></button><?php } ?>
                  <?php if ($vistaEstado === 'recepcionado') {
                    // Seguimiento: consulta pública para externos, consulta interna para internos
                    $segUrl = (strtoupper(trim((string)$r[2])) === 'INTERNO')
                      ? '../reportes/consulta_interna.php?codigo_doc=' . rawurlencode((string)$r[4])
                      : '../consulta_publico.php?num_exp=' . rawurlencode((string)$r[4]);
                  ?>
                    <button class="ab ab-g" title="Derivar" onclick="event.stopPropagation();abrirAccionR(this,'derivar')"><i class="fa fa-paper-plane"></i></button>
                    <button class="ab ab-b" title="Devolver" onclick="event.stopPropagation();abrirAccionR(this,'devolver')"><i class="fa fa-arrow-left"></i></button>
                    <button class="ab ab-b" title="Archivar" onclick="event.stopPropagation();abrirArchivar(this)"><i class="fa fa-archive"></i></button>
                    <a class="ab ab-b" title="Imprimir cargo" target="_blank" href="../tcpdf/examples/cargo_interno.php?exp=<?= h($r[0]) ?>" onclick="event.stopPropagation()"><i class="fa fa-print"></i></a>
                    <a class="ab ab-b" title="Seguimiento" target="_blank" href="<?= h($segUrl) ?>" onclick="event.stopPropagation()"><i class="fa fa-eye"></i></a>
                  <?php } ?>
                  <?php if ($vistaEstado === 'enviado') {
                    $segUrlE = (strtoupper(trim((string)$r[2])) === 'INTERNO')
                      ? '../reportes/consulta_interna.php?codigo_doc=' . rawurlencode((string)$r[4])
                      : '../consulta_publico.php?num_exp=' . rawurlencode((string)$r[4]);
                  ?>
                    <a class="ab ab-b" title="Seguimiento" target="_blank" href="<?= h($segUrlE) ?>" onclick="event.stopPropagation()"><i class="fa fa-eye"></i></a>
                    <a class="ab ab-b" title="Imprimir cargo de derivación" target="_blank" href="../tcpdf/examples/cargo_interno.php?exp=<?= h($r[0]) ?>" onclick="event.stopPropagation()"><i class="fa fa-print"></i></a>
                  <?php } ?>
                  <?php if ($adj !== '#' && $adj !== '' && $adj !== null) { ?><a class="ab ab-b" title="Ver adjunto" target="_blank" href="<?= h($adj) ?>" onclick="event.stopPropagation()"><i class="fa fa-paperclip"></i></a><?php } else { ?><span class="ab ab-d" title="Sin adjunto"><i class="fa fa-paperclip"></i></span><?php } ?>
                </div>
              </div>
            <?php } ?>
          </div>

          <div class="pag">
            <?php $desde = $totalTabla === 0 ? 0 : $inicioPagina + 1;
            $hasta = min($inicioPagina + $porPagina, $totalTabla); ?>
            <span class="muted">Mostrando <?= $desde ?> a <?= $hasta ?> de <?= number_format($totalTabla) ?> registros</span>
            <div style="display:flex;align-items:center;gap:16px">
              <div class="inp btn-sm" style="width:120px;padding:0"><select id="per_page" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25 por página</option>
                  <option value="50" <?= $porPagina === 50 ? 'selected' : '' ?>>50 por página</option>
                  <option value="100" <?= $porPagina === 100 ? 'selected' : '' ?>>100 por página</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
              <div style="display:flex;gap:4px">
                <a class="pg" href="<?= h(page_url(1, $queryBase)) ?>"><i class="fa fa-angle-double-left"></i></a>
                <a class="pg <?= $paginaActual > 1 ? '' : 'disabled' ?>" href="<?= h(page_url(max(1, $paginaActual - 1), $queryBase)) ?>"><i class="fa fa-angle-left"></i></a>
                <?php
                $ventana = 2;
                $inicioBotones = max(1, $paginaActual - $ventana);
                $finBotones = min($totalPaginas, $paginaActual + $ventana);
                if ($inicioBotones > 1) {
                  echo '<a class="pg" href="' . h(page_url(1, $queryBase)) . '">1</a>';
                  if ($inicioBotones > 2) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                }
                for ($p = $inicioBotones; $p <= $finBotones; $p++) {
                  $cls = $p === $paginaActual ? 'pg on' : 'pg';
                  echo '<a class="' . $cls . '" href="' . h(page_url($p, $queryBase)) . '">' . $p . '</a>';
                }
                if ($finBotones < $totalPaginas) {
                  if ($finBotones < $totalPaginas - 1) {
                    echo '<button class="pg" style="width:28px">...</button>';
                  }
                  echo '<a class="pg" href="' . h(page_url($totalPaginas, $queryBase)) . '">' . $totalPaginas . '</a>';
                }
                ?>
                <a class="pg <?= $paginaActual < $totalPaginas ? '' : 'disabled' ?>" href="<?= h(page_url(min($totalPaginas, $paginaActual + 1), $queryBase)) ?>"><i class="fa fa-angle-right"></i></a>
                <a class="pg" href="<?= h(page_url($totalPaginas, $queryBase)) ?>"><i class="fa fa-angle-double-right"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles del documento</h2><button class="ib" style="color:#94a3b8"><i class="fa fa-times"></i></button>
        </div>
        <div class="bd">
          <?php if ($first) {
            $dc0 = badge_doc_color($first[3]);
            $bcls0 = ['blue' => 'b-blue', 'purple' => 'b-purple', 'green' => 'b-green'][$dc0]; ?>
            <div class="docbox">
              <div class="ic"><i class="fa fa-file-text-o"></i></div>
              <div style="min-width:0">
                <div id="d-doc" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($first[3] . ' N° ' . $first[5]) ?></div><span id="d-badge" class="badge <?= $bcls0 ?>"><?= h(strtoupper($first[3])) ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Información general</div>
              <dl>
                <div>
                  <dt><?= $colOrigen ?></dt>
                  <dd id="d-ori"><?= h($first[1]) ?></dd>
                </div>
                <div>
                  <dt><?= $colFecha ?></dt>
                  <dd id="d-fecha"><?= h($first[9]) ?></dd>
                </div>
                <div>
                  <dt>Trámite</dt>
                  <dd id="d-tram"><?= h($first[2]) ?></dd>
                </div>
                <div>
                  <dt>N° Expediente</dt>
                  <dd id="d-exp"><?= h($first[4]) ?></dd>
                </div>
                <div>
                  <dt>Estado actual</dt>
                  <dd><span class="st"><span class="dot <?= ($first[11] === 'R') ? 'd-blue' : (($first[11] === 'E') ? 'd-amber' : 'd-green') ?>"></span> <?= h(estado_recepcion_label(isset($first[11]) ? $first[11] : '')) ?></span></dd>
                </div>
              </dl>
              <div class="sect">Asunto</div>
              <p id="d-asunto" style="margin-top:8px;font-size:13px;color:#475569;line-height:1.6"><?= h($first[6]) ?></p>
              <div class="sect">Archivos adjuntos</div>
              <div id="d-adjbox">
                <?php if ($first[10] !== '#' && $first[10] !== '' && $first[10] !== null) { ?>
                  <a class="adj" href="<?= h($first[10]) ?>" target="_blank" style="text-decoration:none"><i class="fa fa-file-pdf-o" style="color:#ef4444;font-size:20px"></i>
                    <div style="flex:1">
                      <div class="nm">Documento adjunto</div>
                      <div class="sz">Ver archivo</div>
                    </div><i class="fa fa-download" style="color:#94a3b8"></i>
                  </a>
                <?php } else { ?><div class="muted" style="margin-top:8px">Sin archivos adjuntos.</div><?php } ?>
              </div>
              <div style="margin-top:16px">
                <div class="coll"><span class="lbl"><i class="fa fa-comment-o" style="color:#94a3b8"></i> Observaciones <span class="pill">0</span></span><i class="fa fa-angle-down" style="color:#94a3b8"></i></div>
                <div class="coll" id="historialToggle" role="button" tabindex="0"><span class="lbl"><i class="fa fa-clock-o" style="color:#94a3b8"></i> Historial <span class="pill" id="historial-count">0</span></span><i class="fa fa-angle-down" id="historial-arrow" style="color:#94a3b8"></i></div>
                <div id="historial-list" class="history-box" aria-live="polite"></div>
              </div>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Selecciona un documento para ver sus detalles.</div><?php } ?>
        </div>
        <div class="ftr">
          <?php if ($vistaEstado === 'pendiente') { ?>
            <button class="btn btn-p" style="flex:1;justify-content:center" onclick="<?= $first ? "abrirRecep('" . h($first[0]) . "','" . h($first[11]) . "')" : "" ?>"><i class="fa fa-check-circle"></i> Recepcionar</button>
            <button class="btn" onclick="abrirDeriv()"><i class="fa fa-share"></i> Derivar</button>
            <button class="btn" style="padding:0 10px"><i class="fa fa-ellipsis-v"></i></button>
          <?php } else { ?>
            <div class="muted" style="flex:1;text-align:center;font-size:12px">Documento <?= $vistaEstado === 'recepcionado' ? 'ya recepcionado' : 'derivado a otra oficina' ?>.</div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL RECEPCIONAR -->
  <div class="modal" id="modalRecep">
    <div class="box">
      <div class="mh">
        <h3>Recepcionar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarRecep()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div class="muted" id="rc_info" style="font-size:12px"></div>
        <div><label>Acción a realizar <span class="req">*</span></label><select id="rc_accion">
            <option value="">Seleccione una acción...</option>
          </select></div>
        <div><label>Folios <span class="req">*</span></label><input id="rc_folio" type="number" min="1" placeholder="Cantidad de folios" /></div>
        <div><label>Detalles / Proveído</label><textarea id="rc_detalles" rows="3" placeholder="Observaciones (opcional)"></textarea></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarRecep()">Cancelar</button><button class="btn btn-p" id="rc_guardar"><i class="fa fa-check-circle"></i> Confirmar recepción</button></div>
    </div>
  </div>

  <!-- MODAL DERIVAR (recepciona el documento y lo deriva a otra oficina en un solo paso) -->
  <div class="modal" id="modalDeriv">
    <div class="box">
      <div class="mh">
        <h3 id="dv_titulo">Derivar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarDeriv()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div class="muted" id="dv_info" style="font-size:12px"></div>
        <div><label>Oficina destino <span class="req">*</span></label><select id="dv_oficina">
            <option value="">Seleccione oficina...</option>
          </select></div>
        <div><label>Acción <span class="req">*</span></label><select id="dv_accion">
            <option value="">Seleccione una acción...</option>
          </select></div>
        <div><label>Estado <span class="req">*</span></label><select id="dv_estado">
            <option value="">Seleccione estado...</option>
          </select></div>
        <div id="dv_folio_wrap"><label>Folios <span class="req">*</span></label><input id="dv_folio" type="number" min="1" placeholder="Cantidad de folios" /></div>
        <div><label>Detalles / Proveído</label><textarea id="dv_detalles" rows="3" placeholder="Indicaciones para la oficina destino (opcional)"></textarea></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarDeriv()">Cancelar</button><button class="btn btn-p" id="dv_guardar"><i class="fa fa-share"></i> <span id="dv_btn_txt">Recepcionar y derivar</span></button></div>
    </div>
  </div>

  <!-- MODAL ARCHIVAR (guarda el documento recepcionado en un archivador de la oficina) -->
  <div class="modal" id="modalArch">
    <div class="box">
      <div class="mh">
        <h3>Archivar documento</h3><button class="ib" style="color:#94a3b8" onclick="cerrarArchivar()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div class="muted" id="ar_info" style="font-size:12px"></div>
        <div><label>Archivador <span class="req">*</span></label><select id="ar_archivador">
            <option value="">Seleccione archivador...</option>
          </select></div>
        <div><label>Estado <span class="req">*</span></label><select id="ar_estado">
            <option value="">Seleccione estado...</option>
          </select></div>
        <div><label>Detalles</label><textarea id="ar_detalles" rows="3" placeholder="Observaciones del archivado (opcional)"></textarea></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarArchivar()">Cancelar</button><button class="btn btn-p" id="ar_guardar"><i class="fa fa-archive"></i> Archivar</button></div>
    </div>
  </div>

  <script src="../js/v_recepcion_proveido.js"></script>
</body>

</html>