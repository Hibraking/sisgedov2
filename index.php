<?php
require_once __DIR__ . '/conexion/conexion.php'; // endurece la cookie de sesión
session_start();
$_SESSION["modulo"] = "1";
if (!isset($_SESSION['usuario'])) {
  header("Location: ./login.php");
  exit;
}

require_once __DIR__ . '/clases/seguridad.php';

$alias = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';
$idoficina = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
$tok = csrf_token();

function hace_idx($f)
{
  $t = strtotime($f);
  if (!$t) return '';
  $s = time() - $t;
  if ($s < 0) $s = 0;
  if ($s < 3600) return 'Hace ' . max(1, floor($s / 60)) . ' min';
  if ($s < 86400) {
    $h = floor($s / 3600);
    return 'Hace ' . $h . ' hora' . ($h > 1 ? 's' : '');
  }
  $d = floor($s / 86400);
  return 'Hace ' . $d . ' día' . ($d > 1 ? 's' : '');
}

$h = intval(date('G'));
$saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
$mesesEs = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
$diasEs = array('Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo');
$fechaHoy = $diasEs[date('l')] . ' ' . date('j') . ' de ' . $mesesEs[intval(date('n'))] . ' de ' . date('Y');

$porRecepcionar = 0;
$recepHoy = 0;
$envHoy = 0;
$mesaHoy = 0;
$nombreOficina = '';
$recepMes = 0;
$envMes = 0;
$mesaMes = 0;
$archMes = 0;
$pendientes = array();
$actividad = array();
try {
  $pdo = (new Conexion())->conectar();

  // ---- KPIs de hoy ----
  $q = $pdo->prepare("select count(*) from movimientos where idoficina_derivar=? and idmovimientoant is not null and estado=0");
  $q->execute(array($idoficina));
  $porRecepcionar += intval($q->fetchColumn());
  $q = $pdo->prepare("select count(*) from registro_documentos where estado_recepcion=0 and idoficina=?");
  $q->execute(array($idoficina));
  $porRecepcionar += intval($q->fetchColumn());
  $q = $pdo->prepare("select count(*) from docs_emitidos where estado=0 and idoficina=?");
  $q->execute(array($idoficina));
  $porRecepcionar += intval($q->fetchColumn());
  $q = $pdo->prepare("select count(*) from movimientos where idoficina_derivar=? and estado=1 and fecha_hora >= curdate() and fecha_hora < curdate() + interval 1 day");
  $q->execute(array($idoficina));
  $recepHoy = intval($q->fetchColumn());
  $q = $pdo->prepare("select count(*) from movimientos m inner join movimientos ma on m.idmovimientoant=ma.idMovimientos inner join responsables r on ma.idresponsable=r.idresponsables where r.idoficina=? and m.fecha_hora >= curdate() and m.fecha_hora < curdate() + interval 1 day");
  $q->execute(array($idoficina));
  $envHoy = intval($q->fetchColumn());
  $q = $pdo->query("select count(*) from registro_documentos where fecha_registro >= curdate() and fecha_registro < curdate() + interval 1 day");
  $mesaHoy = intval($q->fetchColumn());
  $q = $pdo->prepare("select nombre from oficinas where idoficinas=?");
  $q->execute(array($idoficina));
  $nombreOficina = (string)$q->fetchColumn();

  // ---- Resumen del mes (panel derecho) ----
  $q = $pdo->prepare("select count(*) from movimientos where idoficina_derivar=? and estado=1 and fecha_hora >= date_format(now(),'%Y-%m-01') and fecha_hora < date_format(now(),'%Y-%m-01') + interval 1 month");
  $q->execute(array($idoficina));
  $recepMes = intval($q->fetchColumn());
  $q = $pdo->prepare("select count(*) from movimientos m inner join movimientos ma on m.idmovimientoant=ma.idMovimientos inner join responsables r on ma.idresponsable=r.idresponsables where r.idoficina=? and m.fecha_hora >= date_format(now(),'%Y-%m-01') and m.fecha_hora < date_format(now(),'%Y-%m-01') + interval 1 month");
  $q->execute(array($idoficina));
  $envMes = intval($q->fetchColumn());
  $q = $pdo->query("select count(*) from registro_documentos where fecha_registro >= date_format(now(),'%Y-%m-01') and fecha_registro < date_format(now(),'%Y-%m-01') + interval 1 month");
  $mesaMes = intval($q->fetchColumn());
  $q = $pdo->prepare("select count(*) from docs_archivados da inner join archivador a on da.idarchivador=a.idarchivador where a.idoficina=? and da.fecha_hora >= date_format(now(),'%Y-%m-01') and da.fecha_hora < date_format(now(),'%Y-%m-01') + interval 1 month");
  $q->execute(array($idoficina));
  $archMes = intval($q->fetchColumn());

  // ---- Últimos por recepcionar (3 consultas separadas, unidas en PHP) ----
  // [0 tipo doc, 1 nro/exp, 2 asunto, 3 origen, 4 fecha raw]
  $q = $pdo->prepare("select documentos.nombre_documento, rd.nro_exp, rd.asunto, 'MESA DE PARTES', rd.fecha_registro
                            from registro_documentos rd inner join documentos on rd.iddocumento=documentos.iddocumentos
                            where rd.estado_recepcion=0 and rd.idoficina=? order by rd.fecha_registro desc limit 6");
  $q->execute(array($idoficina));
  $pendientes = $q->fetchAll(PDO::FETCH_NUM);
  $q = $pdo->prepare("select documentos.nombre_documento, rd.nro_exp, rd.asunto,
                                   coalesce((select o2.nombre from movimientos m2 inner join responsables r2 on m2.idresponsable=r2.idresponsables inner join oficinas o2 on r2.idoficina=o2.idoficinas where m2.idMovimientos=m.idmovimientoant),'Otra oficina'),
                                   m.fecha_hora
                            from movimientos m
                            inner join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
                            inner join documentos on rd.iddocumento=documentos.iddocumentos
                            where m.idoficina_derivar=? and m.idmovimientoant is not null and m.estado=0
                            order by m.fecha_hora desc limit 6");
  $q->execute(array($idoficina));
  $pendientes = array_merge($pendientes, $q->fetchAll(PDO::FETCH_NUM));
  $q = $pdo->prepare("select documentos.nombre_documento, cast(de.iddocs_emitidos as char), de.asunto,
                                   coalesce((select o3.nombre from responsables r3 inner join oficinas o3 on r3.idoficina=o3.idoficinas where r3.idresponsables=de.idresponsable),'Oficina interna'),
                                   de.fecha
                            from docs_emitidos de inner join documentos on de.iddocumento=documentos.iddocumentos
                            where de.estado=0 and de.idoficina=? order by de.fecha desc limit 6");
  $q->execute(array($idoficina));
  $pendientes = array_merge($pendientes, $q->fetchAll(PDO::FETCH_NUM));
  usort($pendientes, function ($a, $b) {
    return strcmp((string)$b[4], (string)$a[4]);
  });
  $pendientes = array_slice($pendientes, 0, 6);

  // ---- Actividad de los últimos 7 días (movimientos hacia tu oficina) ----
  $q = $pdo->prepare("select date(fecha_hora), count(*) from movimientos
                            where idoficina_derivar=? and fecha_hora >= date_sub(curdate(), interval 6 day)
                            group by date(fecha_hora)");
  $q->execute(array($idoficina));
  $mapa = array();
  foreach ($q->fetchAll(PDO::FETCH_NUM) as $f) {
    $mapa[$f[0]] = intval($f[1]);
  }
  $dias3 = array('Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom');
  for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $actividad[] = array($dias3[date('D', strtotime($d))], isset($mapa[$d]) ? $mapa[$d] : 0, date('d/m', strtotime($d)));
  }
} catch (Exception $e) {
  error_log($e->getMessage());
}

$maxAct = 1;
foreach ($actividad as $a) {
  if ($a[1] > $maxAct) $maxAct = $a[1];
}
$totalSemana = 0;
foreach ($actividad as $a) {
  $totalSemana += $a[1];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inicio — SISGEDO</title>
  <link rel="icon" type="image/png" href="./<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="./assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="./css/sisgedo-theme.css" />
  <link rel="stylesheet" href="./css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="./css/dashboard.css" />
  <script src="./assets/js/jquery-2.1.4.min.js"></script>
</head>

<body data-csrf="<?= h($tok) ?>">
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
          <a href="./clases/cerrarsesion.php" class="ib" title="Salir"><i class="fa fa-sign-out"></i></a>
        </div>
      </div>
    </div>

    <div class="body">
      <?php $NAV_ACTIVO = 'index.php';
      $NAV_PREFIJO = './';
      include __DIR__ . '/partes/nav_nuevo.php'; ?>

      <!-- CONTENIDO -->
      <div class="main">
        <div class="tb-hola">
          <div>
            <h1 class="t"><?= h($saludo) ?>, <?= h($alias) ?> 👋</h1>
            <div class="subt"><?= $nombreOficina !== '' ? 'Oficina: ' . h($nombreOficina) . ' — ' : '' ?>este es el movimiento documentario de hoy</div>
          </div>
          <div class="tb-fecha"><i class="fa fa-calendar"></i> <?= h($fechaHoy) ?></div>
        </div>

        <div class="kpis">
          <div class="kpi" style="cursor:pointer" onclick="location='./vistas/v_recepcion_proveido.php'">
            <div class="ic t-orange"><i class="fa fa-inbox"></i></div>
            <div>
              <div class="lab lab-orange">Por recepcionar</div>
              <div class="num" style="color:#f97316"><?= number_format($porRecepcionar) ?></div>
              <div class="ss">En tu bandeja</div>
            </div>
          </div>
          <div class="kpi" style="cursor:pointer" onclick="location='./vistas/v_recepcion_proveido.php?estado=Recepcionado'">
            <div class="ic t-green"><i class="fa fa-check-circle"></i></div>
            <div>
              <div class="lab lab-green">Recepcionados hoy</div>
              <div class="num" style="color:#16a34a"><?= number_format($recepHoy) ?></div>
              <div class="ss">Por tu oficina</div>
            </div>
          </div>
          <div class="kpi" style="cursor:pointer" onclick="location='./vistas/v_recepcion_proveido.php?estado=Derivado'">
            <div class="ic t-purple"><i class="fa fa-paper-plane"></i></div>
            <div>
              <div class="lab lab-purple">Derivados hoy</div>
              <div class="num" style="color:#6d28d9"><?= number_format($envHoy) ?></div>
              <div class="ss">Desde tu oficina</div>
            </div>
          </div>
          <div class="kpi" style="cursor:pointer" onclick="location='./vistas/o_reg_documentos.php'">
            <div class="ic t-blue"><i class="fa fa-list-alt"></i></div>
            <div>
              <div class="lab lab-blue">Mesa de partes hoy</div>
              <div class="num" style="color:#1e293b"><?= number_format($mesaHoy) ?></div>
              <div class="ss">Toda la entidad</div>
            </div>
          </div>
        </div>

        <!-- ÚLTIMOS POR RECEPCIONAR -->
        <div class="card table-card" style="flex:0 1 auto">
          <div class="tbar">
            <div class="g"><span style="font-size:13px;font-weight:700;color:#1e293b"><i class="fa fa-inbox" style="color:#2563eb"></i> Últimos documentos por recepcionar</span></div>
            <div class="g"><a class="btn btn-sm" href="./vistas/v_recepcion_proveido.php" style="text-decoration:none"><i class="fa fa-arrow-right"></i> Ver bandeja completa</a></div>
          </div>
          <div class="rowg thead">
            <span>Documento</span><span>Asunto</span><span>Origen</span><span>Recibido</span>
          </div>
          <div>
            <?php if (count($pendientes) === 0) { ?>
              <div style="padding:36px;text-align:center;color:#94a3b8;font-size:13px"><i class="fa fa-check-circle" style="color:#22c55e"></i> ¡Bandeja al día! No tienes documentos pendientes.</div>
            <?php }
            foreach ($pendientes as $p) { ?>
              <div class="row rowg" style="cursor:pointer" onclick="location='./vistas/v_recepcion_proveido.php'">
                <div class="dcell">
                  <div style="display:flex;gap:8px"><i class="fa fa-file-text-o" style="color:#3b82f6;margin-top:2px"></i><span class="nm"><?= h($p[0]) ?> <?= h($p[1]) ?></span></div>
                </div>
                <div class="dcell">
                  <p class="asun"><?= h($p[2]) ?></p>
                </div>
                <div class="dcell"><?php if (stripos((string)$p[3], 'MESA') !== false) { ?><span class="badge b-blue">MESA DE PARTES</span><?php } else { ?><span class="cell-txt" style="font-size:12px;color:#475569"><i class="fa fa-building-o" style="color:#94a3b8;margin-right:6px"></i><?= h($p[3]) ?></span><?php } ?></div>
                <div class="muted"><?= h(hace_idx($p[4])) ?></div>
              </div>
            <?php } ?>
          </div>
        </div>

        <!-- ACTIVIDAD 7 DÍAS -->
        <div class="card" style="padding:16px 20px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:13px;font-weight:700;color:#1e293b"><i class="fa fa-bar-chart" style="color:#2563eb"></i> Actividad de tu oficina — últimos 7 días</span>
            <span class="muted" style="font-size:12px"><?= number_format($totalSemana) ?> movimiento(s) en la semana</span>
          </div>
          <div class="tb-grafico">
            <?php foreach ($actividad as $a) {
              $alto = intval(round($a[1] * 100 / $maxAct)); ?>
              <div class="tb-bar" title="<?= h($a[2]) ?>: <?= $a[1] ?> movimiento(s)">
                <span class="bv"><?= $a[1] > 0 ? number_format($a[1]) : '' ?></span>
                <div class="bb <?= $a[1] === 0 ? 'cero' : '' ?>" style="height:<?= max(4, $alto) ?>%"></div>
                <span class="bl"><?= h($a[0]) ?></span>
                <span class="bf"><?= h($a[2]) ?></span>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Resumen</h2>
        </div>
        <div class="bd">
          <div class="rp-scroll">
            <div class="docbox" style="margin-bottom:12px">
              <div class="ic"><i class="fa fa-building-o"></i></div>
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:700;color:#1e293b"><?= $nombreOficina !== '' ? h($nombreOficina) : 'Sin oficina asignada' ?></div>
                <div class="muted" style="font-size:11.5px">Tu oficina en el sistema</div>
              </div>
            </div>

            <div class="sect">Este mes</div>
            <div style="margin:8px 0 16px">
              <div class="rp-mini"><span class="mi t-green"><i class="fa fa-check-circle"></i></span><span class="mt">Recepcionados</span><span class="mn"><?= number_format($recepMes) ?></span></div>
              <div class="rp-mini"><span class="mi t-purple"><i class="fa fa-paper-plane"></i></span><span class="mt">Derivados</span><span class="mn"><?= number_format($envMes) ?></span></div>
              <div class="rp-mini"><span class="mi t-blue"><i class="fa fa-list-alt"></i></span><span class="mt">Mesa de partes</span><span class="mn"><?= number_format($mesaMes) ?></span></div>
              <div class="rp-mini"><span class="mi t-orange"><i class="fa fa-archive"></i></span><span class="mt">Archivados</span><span class="mn"><?= number_format($archMes) ?></span></div>
            </div>

            <div class="sect">Accesos rápidos</div>
            <div style="margin-top:8px">
              <a class="rp-link" href="./vistas/v_recepcion_proveido.php"><i class="fa fa-inbox"></i> Recepción de documentos <i class="fa fa-angle-right"></i></a>
              <a class="rp-link" href="./vistas/o_reg_documentos.php"><i class="fa fa-list-alt"></i> Registrar en mesa de partes <i class="fa fa-angle-right"></i></a>
              <a class="rp-link" href="./vistas/v_docs_emitidos.php"><i class="fa fa-folder-o"></i> Emitir documento interno <i class="fa fa-angle-right"></i></a>
              <a class="rp-link" href="./vistas/v_archivar_docs.php"><i class="fa fa-archive"></i> Archivo de documentos <i class="fa fa-angle-right"></i></a>
              <a class="rp-link" href="./reportes/consulta_interna.php"><i class="fa fa-search"></i> Consulta de expedientes <i class="fa fa-angle-right"></i></a>
              <a class="rp-link" href="./vistas/v_reporte_estados_documento.php"><i class="fa fa-tasks"></i> Estados de atención <i class="fa fa-angle-right"></i></a>
            </div>
          </div>
        </div>
        <div class="ftr">
          <a class="btn btn-p" style="flex:1;justify-content:center;text-decoration:none" href="./vistas/v_recepcion_proveido.php"><i class="fa fa-inbox"></i> Ir a mi bandeja</a>
        </div>
      </div>
    </div>
  </div>
</body>

</html>