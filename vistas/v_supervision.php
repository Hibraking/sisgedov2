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
require_once '../clases/vista_oficina.php';
require_once '../clases/mpv_util.php';

// Solo el administrador (esta página no es un ítem de menú: se controla aquí)
if (!sgd_es_admin()) {
  header('Location: ../index.php');
  exit;
}
$alias = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';
$tok = csrf_token();

// ---- Indicadores por oficina (consultas agregadas, una por indicador) ----
$ofis = array();
$pdo = (new Conexion())->conectar();
foreach ($pdo->query("SELECT idoficinas, nombre FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-')")->fetchAll(PDO::FETCH_NUM) as $o) {
  $ofis[(int)$o[0]] = array('id' => (int)$o[0], 'nombre' => $o[1], 'ext' => 0, 'int' => 0, 'der' => 0, 'viejos' => 0,
    'antiguo' => null, 'hoy' => 0, 'mes' => 0);
}
$sumar = function ($sql, $campo, $conFecha = false) use ($pdo, &$ofis) {
  try {
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as $r) {
      $id = (int)$r[0];
      if (!isset($ofis[$id])) continue;
      $ofis[$id][$campo] += (int)$r[1];
      if ($conFecha) {
        $ofis[$id]['viejos'] += (int)$r[3];
        if ($r[2] !== null && ($ofis[$id]['antiguo'] === null || $r[2] < $ofis[$id]['antiguo'])) {
          $ofis[$id]['antiguo'] = $r[2];
        }
      }
    }
  } catch (Exception $e) {
    error_log('supervision: ' . $e->getMessage());
  }
};
// Por recepcionar: externos (registro), internos (emitidos) y derivaciones
$sumar("SELECT idoficina, COUNT(*), MIN(fecha_registro), SUM(fecha_registro < NOW() - INTERVAL 30 DAY)
          FROM registro_documentos WHERE estado_recepcion = 0 AND idoficina IS NOT NULL GROUP BY idoficina", 'ext', true);
$sumar("SELECT idoficina, COUNT(*), MIN(fecha), SUM(fecha < NOW() - INTERVAL 30 DAY)
          FROM docs_emitidos WHERE estado = 0 AND idoficina IS NOT NULL GROUP BY idoficina", 'int', true);
$sumar("SELECT idoficina_derivar, COUNT(*), MIN(fecha_hora), SUM(fecha_hora < NOW() - INTERVAL 30 DAY)
          FROM movimientos WHERE estado = 0 AND idmovimientoant IS NOT NULL GROUP BY idoficina_derivar", 'der', true);
// Recibidos hoy y derivaciones del mes
$sumar("SELECT idoficina_derivar, COUNT(*) FROM movimientos
         WHERE estado = 1 AND fecha_hora >= CURDATE() AND fecha_hora < CURDATE() + INTERVAL 1 DAY GROUP BY idoficina_derivar", 'hoy');
$sumar("SELECT r.idoficina, COUNT(*) FROM movimientos m
          JOIN movimientos ma ON m.idmovimientoant = ma.idMovimientos
          JOIN responsables r ON ma.idresponsable = r.idresponsables
         WHERE m.fecha_hora >= DATE_FORMAT(NOW(), '%Y-%m-01') GROUP BY r.idoficina", 'mes');

$tot = array('pend' => 0, 'viejos' => 0, 'hoy' => 0, 'mes' => 0, 'con_pend' => 0);
foreach ($ofis as &$o) {
  $o['pend'] = $o['ext'] + $o['int'] + $o['der'];
  $tot['pend'] += $o['pend'];
  $tot['viejos'] += $o['viejos'];
  $tot['hoy'] += $o['hoy'];
  $tot['mes'] += $o['mes'];
  if ($o['pend'] > 0) $tot['con_pend']++;
}
unset($o);
usort($ofis, function ($a, $b) {
  return $b['pend'] - $a['pend'] ?: strcmp($a['nombre'], $b['nombre']);
});

// Mesa de Partes Virtual (global)
$mpv = array('pend' => 0, 'vencidos' => 0);
if (sgd_mpv_ok($pdo)) {
  $mpv['pend'] = (int)$pdo->query("SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND mpv_recepcion IS NULL AND (mpv_estado IS NULL OR mpv_estado = 'SUBSANADO')")->fetchColumn()
    + (int)$pdo->query("SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND idoficina IS NULL AND mpv_idpersonal IS NOT NULL AND mpv_recepcion IS NOT NULL AND COALESCE(estado_recepcion,0) = 0")->fetchColumn();
  $mpv['vencidos'] = (int)$pdo->query("SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND mpv_estado = 'OBSERVADO' AND mpv_plazo < CURDATE()")->fetchColumn();
}

function sup_hace($f)
{
  if (!$f) return '—';
  $d = (int)floor((time() - strtotime($f)) / 86400);
  if ($d < 1) return 'hoy';
  if ($d < 60) return 'hace ' . $d . ($d === 1 ? ' día' : ' días');
  if ($d < 730) return 'hace ' . (int)round($d / 30) . ' meses';
  return 'hace ' . (int)round($d / 365) . ' años';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Supervisión — SISGEDO</title>
  <link rel="icon" type="image/png" href="../<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css?v=<?= @filemtime(__DIR__ . '/../css/sisgedo-theme.css') ?>" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css?v=<?= @filemtime(__DIR__ . '/../css/v_recepcion_proveido.css') ?>" />
  <style>
    .sup-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
    .sup-tabla th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #64748b; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; position: sticky; top: 0; }
    .sup-tabla td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .sup-tabla tr:hover td { background: #f8fafc; }
    .sup-tabla .num { text-align: right; font-variant-numeric: tabular-nums; }
    .sup-pend { font-weight: 700; color: #c2410c; }
    .sup-cero { color: #94a3b8; }
    .sup-det { font-size: 11px; color: #94a3b8; }
    .sup-viejo { color: #b91c1c; font-weight: 600; }
    .sup-scroll { max-height: calc(100vh - 360px); overflow: auto; }
    .sup-tabla tfoot td { font-weight: 700; background: #f8fafc; border-top: 2px solid #e2e8f0; }
    .sup-ayuda { font-size: 12.5px; color: #475569; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; line-height: 1.5; }
  </style>
</head>

<body>
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
      <?php $NAV_ACTIVO = 'vistas/v_supervision.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main" style="max-width:none">
        <h1 class="t">Supervisión de oficinas</h1>
        <div class="subt">Situación de todas las áreas. Solo visible para el administrador.</div>

        <div class="sup-ayuda"><i class="fa fa-info-circle"></i>
          Pulse <b>Entrar</b> para trabajar en la bandeja de una oficina (Recepción, Archivo, Emisión, Derivados).
          Lo que haga allí quedará registrado como <b>Administrador en nombre de esa oficina</b>.
          Para volver a la suya, elija <b>Mi oficina</b> en el selector del menú lateral.
        </div>

        <div class="kpis">
          <?php
          $cards = array(
            array('Por recepcionar', $tot['pend'], 'En ' . $tot['con_pend'] . ' oficinas', 'fa-inbox', 't-orange', '#f97316', 'lab-orange'),
            array('Más de 30 días', $tot['viejos'], 'Pendientes antiguos', 'fa-exclamation-triangle', 't-orange', '#dc2626', 'lab-orange'),
            array('Recibidos hoy', $tot['hoy'], 'En todas las oficinas', 'fa-check-circle', 't-green', '#16a34a', 'lab-green'),
            array('Mesa de Partes Virtual', $mpv['pend'], $mpv['vencidos'] > 0 ? $mpv['vencidos'] . ' observados vencidos' : 'Por atender', 'fa-globe', 't-purple', '#6d28d9', 'lab-purple'),
          );
          foreach ($cards as $c) { ?>
            <div class="kpi">
              <div class="ic <?= $c[4] ?>"><i class="fa <?= $c[3] ?>"></i></div>
              <div>
                <div class="lab <?= $c[6] ?>"><?= h($c[0]) ?></div>
                <div class="num" style="color:<?= $c[5] ?>"><?= number_format($c[1]) ?></div>
                <div class="ss"><?= h($c[2]) ?></div>
              </div>
            </div>
          <?php } ?>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscar" placeholder="Buscar oficina..." oninput="filtrarSup(this.value)" /><i class="fa fa-search"></i></div>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="sup-scroll">
            <table class="sup-tabla" id="tablaSup">
              <thead>
                <tr>
                  <th>Oficina</th>
                  <th class="num">Por recepcionar</th>
                  <th class="num">Más de 30 días</th>
                  <th>Pendiente más antiguo</th>
                  <th class="num">Recibidos hoy</th>
                  <th class="num">Derivados este mes</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ofis as $o) { ?>
                  <tr data-n="<?= h(mb_strtolower($o['nombre'], 'UTF-8')) ?>">
                    <td><b><?= h($o['nombre']) ?></b></td>
                    <td class="num">
                      <span class="<?= $o['pend'] > 0 ? 'sup-pend' : 'sup-cero' ?>"><?= number_format($o['pend']) ?></span>
                      <?php if ($o['pend'] > 0) { ?><div class="sup-det" title="Externos / internos (emitidos) / derivados de otras oficinas"><?= $o['ext'] ?> ext · <?= $o['int'] ?> int · <?= $o['der'] ?> der</div><?php } ?>
                    </td>
                    <td class="num <?= $o['viejos'] > 0 ? 'sup-viejo' : 'sup-cero' ?>"><?= number_format($o['viejos']) ?></td>
                    <td><?= $o['antiguo'] ? h(date('d/m/Y', strtotime($o['antiguo']))) . ' <span class="sup-det">' . h(sup_hace($o['antiguo'])) . '</span>' : '<span class="sup-cero">—</span>' ?></td>
                    <td class="num <?= $o['hoy'] ? '' : 'sup-cero' ?>"><?= number_format($o['hoy']) ?></td>
                    <td class="num <?= $o['mes'] ? '' : 'sup-cero' ?>"><?= number_format($o['mes']) ?></td>
                    <td style="text-align:right">
                      <form method="post" action="../clases/vista_oficina.php" style="margin:0">
                        <input type="hidden" name="_csrf" value="<?= h($tok) ?>" />
                        <input type="hidden" name="ver_oficina" value="<?= (int)$o['id'] ?>" />
                        <input type="hidden" name="volver" value="vistas/v_recepcion_proveido.php" />
                        <button class="btn btn-sm btn-p" type="submit" title="Trabajar en la bandeja de esta oficina"><i class="fa fa-sign-in"></i> Entrar</button>
                      </form>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
              <tfoot>
                <tr>
                  <td>Total (<?= count($ofis) ?> oficinas)</td>
                  <td class="num"><?= number_format($tot['pend']) ?></td>
                  <td class="num"><?= number_format($tot['viejos']) ?></td>
                  <td></td>
                  <td class="num"><?= number_format($tot['hoy']) ?></td>
                  <td class="num"><?= number_format($tot['mes']) ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    function filtrarSup(t) {
      t = (t || '').trim().toLowerCase();
      document.querySelectorAll('#tablaSup tbody tr').forEach(function(tr) {
        tr.style.display = !t || tr.dataset.n.indexOf(t) !== -1 ? '' : 'none';
      });
    }
  </script>
</body>

</html>
