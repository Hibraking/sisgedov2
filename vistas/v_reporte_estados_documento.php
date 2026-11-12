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

$alias = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';
$idoficina = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
$tok = csrf_token();
$hoy = date('Y-m-d');

$desdeFiltro = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hastaFiltro = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$estadoFiltro = isset($_GET['estado']) ? intval($_GET['estado']) : 0;
$qFiltro = isset($_GET['q']) ? trim($_GET['q']) : '';

$desdeSql = ($desdeFiltro !== '') ? $desdeFiltro : date('Y-m-d', strtotime('-30 days'));
$hastaSql = ($hastaFiltro !== '') ? $hastaFiltro : $hoy;

$estados = array();
$filas = array();
$porEstado = array();
try {
  $pdo = (new Conexion())->conectar();
  $st = $pdo->prepare("SELECT idestado_documento, nombre FROM estado_documento ORDER BY idestado_documento");
  $st->execute();
  $estados = $st->fetchAll(PDO::FETCH_NUM);

  // Movimientos tramitados por la oficina en el rango (dos consultas separadas
  // unidas en PHP: un UNION falla por collations mixtas tras utf8mb4).
  $cond = $estadoFiltro > 0 ? " and m.idestadodocs = " . intval($estadoFiltro) : "";
  $q1 = "select m.idMovimientos, rd.nro_exp, documentos.nombre_documento, rd.asunto,
                  coalesce(ed.nombre,'--'), date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p'),
                  m.fecha_hora, 'EXTERNO'
               from movimientos m
               inner join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
               inner join documentos on rd.iddocumento=documentos.iddocumentos
               left join estado_documento ed on m.idestadodocs=ed.idestado_documento
               where m.idoficina_derivar=? and m.estado=1 and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)" . $cond;
  $st = $pdo->prepare($q1);
  $st->execute(array($idoficina, $desdeSql, $hastaSql));
  $filas = $st->fetchAll(PDO::FETCH_NUM);
  $q2 = "select m.idMovimientos, cast(de.iddocs_emitidos as char), documentos.nombre_documento, de.asunto,
                  coalesce(ed.nombre,'--'), date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p'),
                  m.fecha_hora, 'INTERNO'
               from movimientos m
               inner join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
               inner join documentos on de.iddocumento=documentos.iddocumentos
               left join estado_documento ed on m.idestadodocs=ed.idestado_documento
               where m.idoficina_derivar=? and m.estado=1 and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)" . $cond;
  $st = $pdo->prepare($q2);
  $st->execute(array($idoficina, $desdeSql, $hastaSql));
  $filas = array_merge($filas, $st->fetchAll(PDO::FETCH_NUM));
  usort($filas, function ($a, $b) {
    return strcmp((string)$b[6], (string)$a[6]);
  });

  // Resumen por estado (para las tarjetas)
  $st = $pdo->prepare("select coalesce(ed.nombre,'Sin estado'), count(*)
                             from movimientos m
                             left join estado_documento ed on m.idestadodocs=ed.idestado_documento
                             where m.idoficina_derivar=? and m.estado=1 and m.fecha_hora >= ? and m.fecha_hora < date_add(?, interval 1 day)
                             group by ed.nombre order by count(*) desc");
  $st->execute(array($idoficina, $desdeSql, $hastaSql));
  $porEstado = $st->fetchAll(PDO::FETCH_NUM);
} catch (Exception $e) {
  error_log($e->getMessage());
}

if ($qFiltro !== '') {
  $qn = function_exists('mb_strtolower') ? mb_strtolower($qFiltro, 'UTF-8') : strtolower($qFiltro);
  $filas = array_values(array_filter($filas, function ($r) use ($qn) {
    $t = implode(' ', array_map(function ($v) {
      return is_scalar($v) ? (string)$v : '';
    }, $r));
    $t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
    return strpos($t, $qn) !== false;
  }));
}

// Exportación CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="estados_atencion_' . date('Ymd_His') . '.csv"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, array('Expediente', 'Documento', 'Tramite', 'Asunto', 'Estado', 'Fecha'), ';');
  foreach ($filas as $rc) {
    fputcsv($out, array($rc[1], $rc[2], $rc[7], $rc[3], $rc[4], $rc[5]), ';');
  }
  fclose($out);
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Estados de Atención — SISGEDO</title>
  <link rel="icon" type="image/png" href="../<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/v_vistas.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body class="v-reporte-estados-documento" data-csrf="<?= h($tok) ?>">
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
      <?php $NAV_ACTIVO = 'vistas/v_reporte_estados_documento.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main">
        <h1 class="t">Estados de Atención</h1>
        <div class="subt">Documentos tramitados por tu oficina según su estado — del <?= h(date('d/m/Y', strtotime($desdeSql))) ?> al <?= h(date('d/m/Y', strtotime($hastaSql))) ?></div>

        <div class="kpis">
          <?php $tints = array('t-blue' => '#1e293b', 't-green' => '#16a34a', 't-purple' => '#6d28d9', 't-orange' => '#f97316');
          $labs = array('lab-blue', 'lab-green', 'lab-purple', 'lab-orange');
          $ics = array_keys($tints);
          $i = 0;
          foreach (array_slice($porEstado, 0, 4) as $pe) {
            $tt = $ics[$i % 4]; ?>
            <div class="kpi">
              <div class="ic <?= $tt ?>"><i class="fa fa-flag-o"></i></div>
              <div>
                <div class="lab <?= $labs[$i % 4] ?>"><?= h($pe[0]) ?></div>
                <div class="num" style="color:<?= $tints[$tt] ?>"><?= number_format($pe[1]) ?></div>
                <div class="ss">En el período</div>
              </div>
            </div>
          <?php $i++;
          }
          if (count($porEstado) === 0) { ?>
            <div class="kpi">
              <div class="ic t-blue"><i class="fa fa-flag-o"></i></div>
              <div>
                <div class="lab lab-blue">Sin datos</div>
                <div class="num" style="color:#1e293b">0</div>
                <div class="ss">En el período</div>
              </div>
            </div>
          <?php } ?>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" value="<?= h($qFiltro) ?>" placeholder="Buscar por expediente, asunto..." /><i class="fa fa-search"></i></div>
            </div>
            <div class="fld"><label>Desde</label>
              <div class="inp"><input id="desde" type="date" max="<?= h($hoy) ?>" value="<?= h($desdeFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Hasta</label>
              <div class="inp"><input id="hasta" type="date" max="<?= h($hoy) ?>" value="<?= h($hastaFiltro) ?>" /></div>
            </div>
            <div class="fld"><label>Estado</label>
              <div class="inp" style="padding:0"><select id="estado" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="0">Todos</option><?php foreach ($estados as $e) { ?><option value="<?= h($e[0]) ?>" <?= $estadoFiltro === intval($e[0]) ? 'selected' : '' ?>><?= h($e[1]) ?></option><?php } ?>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn" onclick="window.location='v_reporte_estados_documento.php'"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g">
              <div class="dd">
                <button class="btn btn-sm" id="btnExportar"><i class="fa fa-download"></i> Exportar <i class="fa fa-angle-down"></i></button>
                <div class="dd-menu">
                  <a href="#" id="expCsv"><i class="fa fa-file-text-o"></i> Descargar CSV</a>
                  <a href="#" id="expPrint"><i class="fa fa-print"></i> Imprimir listado</a>
                </div>
              </div>
            </div>
            <div class="g"><span class="muted"><?= number_format(count($filas)) ?> registro(s)</span></div>
          </div>

          <div class="rowg thead">
            <span>Expediente</span><span>Documento</span><span>Asunto</span><span>Estado</span><span>Fecha</span><span>Trámite</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($filas) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No hay documentos tramitados con esos filtros.</div>
            <?php }
            foreach (array_slice($filas, 0, 500) as $r) { ?>
              <div class="row rowg">
                <span class="exp"><?= h($r[1]) ?></span>
                <div class="dcell"><span class="cell-txt"><?= h($r[2]) ?></span></div>
                <div class="dcell">
                  <p class="asun"><?= h($r[3]) ?></p>
                </div>
                <div><span class="badge b-blue"><?= h(strtoupper($r[4])) ?></span></div>
                <div class="muted"><?= h($r[5]) ?></div>
                <div><span class="badge b-gray"><?= h($r[7]) ?></span></div>
                <div class="act">
                  <?php $segUrlR = ($r[7] === 'INTERNO') ? '../reportes/consulta_interna.php?codigo_doc=' . rawurlencode((string)$r[1]) : '../consulta_publico.php?num_exp=' . rawurlencode((string)$r[1]); ?>
                  <a class="ab ab-b" title="Seguimiento" target="_blank" href="<?= h($segUrlR) ?>"><i class="fa fa-eye"></i></a>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../js/v_reporte_estados_documento.js"></script>
</body>

</html>