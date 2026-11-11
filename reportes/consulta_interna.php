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
$tok = csrf_token();

// Búsqueda: interno (codigo_doc = id de documento emitido) o externo (num_exp = expediente)
$codigoDoc = isset($_GET['codigo_doc']) ? intval($_GET['codigo_doc']) : 0;
$numExp = isset($_GET['num_exp']) ? trim($_GET['num_exp']) : '';
$busco = ($codigoDoc > 0 || $numExp !== '');

$cab = null;      // [tipo, documento, numero/exp, asunto, origen, fecha]
$eventos = array(); // [fecha, hora, oficina, accion, estado, detalles, evento, orden]
try {
  $pdo = (new Conexion())->conectar();

  if ($codigoDoc > 0) {
    // ---- Documento INTERNO ----
    $st = $pdo->prepare("select de.iddocs_emitidos, documentos.nombre_documento, de.numero, de.asunto,
                                        coalesce(o.nombre,''), date_format(date_add(de.fecha,interval 0 hour),'%d/%m/%Y %h:%i %p'),
                                        de.fecha, coalesce(de.detalles,'')
                                 from docs_emitidos de
                                 inner join documentos on de.iddocumento=documentos.iddocumentos
                                 left join oficinas o on de.idoficina_registro=o.idoficinas
                                 where de.iddocs_emitidos=?");
    $st->execute(array($codigoDoc));
    $d = $st->fetch(PDO::FETCH_NUM);
    if ($d) {
      $cab = array('INTERNO', $d[1], 'N° ' . $d[2], $d[3], $d[4], $d[5]);
      $eventos[] = array(date('d/m/Y', strtotime($d[6] . ' +2 hours')), date('h:i A', strtotime($d[6] . ' +2 hours')), $d[4], 'Registro', 'Emitido', $d[7], 'Registro', $d[6]);
      $st = $pdo->prepare("select date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y'),
                                            date_format(date_add(m.fecha_hora,interval 0 hour),'%h:%i %p'),
                                            coalesce(ofi.nombre, ofid.nombre, ''), coalesce(acc.nombre_accion,'--'),
                                            coalesce(ed.nombre,'--'), coalesce(m.detalles, m.detalle_derivado, ''),
                                            case when m.idmovimientoant is null then 'Origen' when m.estado=0 then 'Ubicación actual' else 'Movimiento' end,
                                            m.fecha_hora
                                     from movimientos m
                                     left join responsables r on m.idresponsable=r.idresponsables
                                     left join oficinas ofi on r.idoficina=ofi.idoficinas
                                     left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                                     left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                                     left join estado_documento ed on m.idestadodocs=ed.idestado_documento
                                     where m.iddocs_emitidos=? order by m.fecha_hora asc, m.idMovimientos asc");
      $st->execute(array($codigoDoc));
      $eventos = array_merge($eventos, $st->fetchAll(PDO::FETCH_NUM));
    }
  } elseif ($numExp !== '') {
    // ---- Documento EXTERNO (mesa de partes) ----
    $st = $pdo->prepare("select rd.idregistro_documentos, documentos.nombre_documento, rd.nro_exp, rd.asunto,
                                        concat(remitente.apellidos,', ',remitente.nombres),
                                        date_format(date_add(rd.fecha_registro,interval 0 hour),'%d/%m/%Y %h:%i %p'),
                                        rd.fecha_registro, coalesce(rd.observacion,''), coalesce(rd.recepcion_web,0)
                                 from registro_documentos rd
                                 inner join documentos on rd.iddocumento=documentos.iddocumentos
                                 inner join remitente on rd.idremitente=remitente.idremitente
                                 where rd.nro_exp=? order by rd.idregistro_documentos asc");
    $st->execute(array($numExp));
    $regs = $st->fetchAll(PDO::FETCH_NUM);
    if (count($regs) > 0) {
      $d = $regs[0];
      $cab = array('EXTERNO', $d[1], 'Exp. ' . $d[2], $d[3], 'Remitente: ' . $d[4], $d[5]);
      $eventos[] = array(date('d/m/Y', strtotime($d[6] . ' +2 hours')), date('h:i A', strtotime($d[6] . ' +2 hours')), (intval($d[8]) === 1 ? 'Mesa de Partes Virtual' : 'Mesa de Partes'), 'Registro', 'Registrado', $d[7], 'Registro', $d[6]);
      $ids = array();
      foreach ($regs as $rg) {
        $ids[] = intval($rg[0]);
      }
      $marcas = implode(',', array_fill(0, count($ids), '?'));
      $st = $pdo->prepare("select date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y'),
                                            date_format(date_add(m.fecha_hora,interval 0 hour),'%h:%i %p'),
                                            coalesce(ofi.nombre, ofid.nombre, ''), coalesce(acc.nombre_accion,'--'),
                                            coalesce(ed.nombre,'--'), coalesce(m.detalles, m.detalle_derivado, ''),
                                            case when m.idmovimientoant is null then 'Origen' when m.estado=0 then 'Ubicación actual' else 'Movimiento' end,
                                            m.fecha_hora
                                     from movimientos m
                                     left join responsables r on m.idresponsable=r.idresponsables
                                     left join oficinas ofi on r.idoficina=ofi.idoficinas
                                     left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                                     left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                                     left join estado_documento ed on m.idestadodocs=ed.idestado_documento
                                     where m.idregdocs in ($marcas) order by m.fecha_hora asc, m.idMovimientos asc");
      $st->execute($ids);
      $eventos = array_merge($eventos, $st->fetchAll(PDO::FETCH_NUM));
    }
  }
} catch (Exception $e) {
  error_log($e->getMessage());
}

// Sugerencias cuando aún no se busca nada: últimos documentos reales del sistema
$sugExternos = array();
$sugInternos = array();
if (!$busco) {
  try {
    $pdoS = (new Conexion())->conectar();
    $st = $pdoS->query("select rd.nro_exp, documentos.nombre_documento, rd.asunto,
                                       date_format(date_add(rd.fecha_registro,interval 0 hour),'%d/%m/%Y')
                                from registro_documentos rd
                                inner join documentos on rd.iddocumento=documentos.iddocumentos
                                order by rd.fecha_registro desc limit 5");
    $sugExternos = $st->fetchAll(PDO::FETCH_NUM);
    $st = $pdoS->query("select de.iddocs_emitidos, documentos.nombre_documento, de.asunto,
                                       date_format(date_add(de.fecha,interval 0 hour),'%d/%m/%Y')
                                from docs_emitidos de
                                inner join documentos on de.iddocumento=documentos.iddocumentos
                                order by de.fecha desc limit 5");
    $sugInternos = $st->fetchAll(PDO::FETCH_NUM);
  } catch (Exception $e) {
    error_log($e->getMessage());
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Consulta Interna — SISGEDO</title>
  <link rel="icon" type="image/png" href="../<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/consulta_interna.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
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
          <a href="../clases/cerrarsesion.php" class="ib" title="Salir"><i class="fa fa-sign-out"></i></a>
        </div>
      </div>
    </div>

    <div class="body">
      <?php $NAV_ACTIVO = 'reportes/consulta_interna.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main">
        <h1 class="t">Consulta Interna</h1>
        <div class="subt">Seguimiento completo de un expediente externo o un documento interno</div>

        <div class="card ci-wrap" style="padding:18px 20px">
          <div style="display:grid;grid-template-columns:235px 1fr auto;gap:10px;align-items:end">
            <div><label style="font-size:13px;color:#475569;display:block;margin-bottom:6px">Tipo</label>
              <div class="inp" style="padding:0"><select id="tipoBusq" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="externo" <?= $codigoDoc === 0 ? 'selected' : '' ?>>Externo (N° expediente)</option>
                  <option value="interno" <?= $codigoDoc > 0 ? 'selected' : '' ?>>Interno (código de documento)</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
            </div>
            <div><label style="font-size:13px;color:#475569;display:block;margin-bottom:6px">Número</label>
              <div class="inp"><input id="valorBusq" value="<?= h($codigoDoc > 0 ? $codigoDoc : $numExp) ?>" placeholder="Ej. 20263304" style="border:0;outline:0;width:100%;background:none;color:#475569;font-size:13px" /><i class="fa fa-search"></i></div>
            </div>
            <button class="btn btn-p" onclick="buscarCI()"><i class="fa fa-search"></i> Consultar</button>
          </div>
        </div>

        <?php if (!$busco) { ?>
          <div class="ci-sug">
            <div class="card" style="padding:16px 18px">
              <div class="sect" style="margin-bottom:10px"><i class="fa fa-users" style="color:#2563eb"></i> Últimos expedientes externos</div>
              <?php if (count($sugExternos) === 0) { ?><div class="muted" style="font-size:12px">Sin registros aún.</div><?php } ?>
              <?php foreach ($sugExternos as $s) { ?>
                <div class="ci-item" onclick="consultarSug('externo', <?= json_encode((string)$s[0]) ?>)">
                  <i class="fa fa-file-text-o" style="color:#3b82f6;margin-top:2px"></i>
                  <div style="min-width:0;flex:1">
                    <div class="n">Exp. <?= h($s[0]) ?> — <?= h($s[1]) ?></div>
                    <div class="a"><?= h($s[2]) ?></div>
                  </div>
                  <span class="f"><?= h($s[3]) ?></span>
                </div>
              <?php } ?>
            </div>
            <div class="card" style="padding:16px 18px">
              <div class="sect" style="margin-bottom:10px"><i class="fa fa-building-o" style="color:#6d28d9"></i> Últimos documentos internos</div>
              <?php if (count($sugInternos) === 0) { ?><div class="muted" style="font-size:12px">Sin registros aún.</div><?php } ?>
              <?php foreach ($sugInternos as $s) { ?>
                <div class="ci-item" onclick="consultarSug('interno', <?= json_encode((string)$s[0]) ?>)">
                  <i class="fa fa-file-text-o" style="color:#a855f7;margin-top:2px"></i>
                  <div style="min-width:0;flex:1">
                    <div class="n">Cód. <?= h($s[0]) ?> — <?= h($s[1]) ?></div>
                    <div class="a"><?= h($s[2]) ?></div>
                  </div>
                  <span class="f"><?= h($s[3]) ?></span>
                </div>
              <?php } ?>
            </div>
          </div>
          <div class="muted ci-wrap" style="font-size:12px;margin-top:4px"><i class="fa fa-info-circle"></i> Haz clic en cualquier documento para ver su recorrido completo, o busca por número en la parte superior.</div>
        <?php } ?>

        <?php if ($busco && $cab === null) { ?>
          <div class="card ci-wrap" style="padding:32px;text-align:center;color:#94a3b8;font-size:13px">
            <i class="fa fa-exclamation-circle" style="font-size:22px;color:#f59e0b"></i><br><br>
            No se encontró ningún documento con ese número. Verifica el tipo de búsqueda y el número.
          </div>
        <?php } ?>

        <?php if ($cab !== null) { ?>
          <div class="card ci-wrap" style="padding:18px 20px">
            <div class="docbox" style="margin-bottom:12px">
              <div class="ic"><i class="fa fa-file-text-o"></i></div>
              <div style="min-width:0">
                <div style="font-size:13.5px;font-weight:700;color:#1e293b"><?= h($cab[1]) ?> <?= h($cab[2]) ?> <span class="badge <?= $cab[0] === 'INTERNO' ? 'b-purple' : 'b-blue' ?>"><?= h($cab[0]) ?></span></div>
                <div class="muted" style="font-size:12px;margin-top:2px"><?= h($cab[4]) ?> — <?= h($cab[5]) ?></div>
              </div>
            </div>
            <div class="sect">Asunto</div>
            <p style="margin:8px 0 14px;font-size:13px;color:#475569;line-height:1.6"><?= h($cab[3]) ?></p>

            <div class="sect">Recorrido del documento <span class="pill" style="background:#eef2ff;color:#4f46e5;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700"><?= count($eventos) ?></span></div>
            <div class="history-box open ci-hist" style="margin-top:10px">
              <?php foreach ($eventos as $idx => $ev) {
                $esActual = (strpos($ev[6], 'actual') !== false) || ($idx === count($eventos) - 1 && strpos($ev[6], 'actual') !== false);
                $marker = (strpos($ev[6], 'actual') !== false) ? 'current' : 'complete';
                $badgeCls = 'history-badge neutral';
                if (strpos($ev[6], 'actual') !== false) $badgeCls = 'history-badge amber';
                elseif ($ev[6] === 'Registro') $badgeCls = 'history-badge green';
                elseif ($ev[6] === 'Origen') $badgeCls = 'history-badge blue';
                else $badgeCls = 'history-badge cyan';
              ?>
                <div class="history-item <?= $marker ?>">
                  <div class="history-head">
                    <div class="history-stamp">
                      <span class="history-ico"><i class="fa fa-calendar"></i></span>
                      <div class="history-datetime">
                        <div class="history-date"><?= h($ev[0]) ?></div>
                        <div class="history-time"><i class="fa fa-clock-o"></i> <?= h($ev[1]) ?></div>
                      </div>
                    </div>
                    <span class="<?= $badgeCls ?>"><?= h($ev[6]) ?></span>
                  </div>
                  <div class="history-office"><?= h($ev[2]) ?></div>
                  <div class="history-divider"></div>
                  <div class="history-duo">
                    <div class="history-field"><span class="history-mini-ico"><i class="fa fa-paper-plane"></i></span>
                      <div><strong>Acción:</strong> <?= h($ev[3]) ?></div>
                    </div>
                    <div class="history-field right"><span class="history-mini-ico"><i class="fa fa-flag-o"></i></span>
                      <div><strong>Estado:</strong> <?= h($ev[4]) ?></div>
                    </div>
                  </div>
                  <?php if (trim((string)$ev[5]) !== '') { ?>
                    <div class="history-divider"></div>
                    <div class="history-details-row">
                      <span class="history-doc-ico"><i class="fa fa-file-text-o"></i></span>
                      <div class="history-details-text"><?= h($ev[5]) ?></div>
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>

  <script src="../js/consulta_interna.js"></script>
</body>

</html>