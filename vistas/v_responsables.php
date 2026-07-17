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

$filas = array();
$personas = array();
$oficinas = array();
try {
  $pdo = (new Conexion())->conectar();
  $st = $pdo->prepare("SELECT r.idresponsables, CONCAT(p.apellidos,', ',p.nombres), o.nombre, r.estado, r.idpersonal, r.idoficina
                             FROM responsables r
                             INNER JOIN personal p ON r.idpersonal=p.idpersonal
                             INNER JOIN oficinas o ON r.idoficina=o.idoficinas
                             ORDER BY o.nombre ASC, p.apellidos ASC");
  $st->execute();
  $filas = $st->fetchAll(PDO::FETCH_NUM);
  $st = $pdo->prepare("SELECT idpersonal, CONCAT(apellidos,', ',nombres) FROM personal ORDER BY apellidos ASC");
  $st->execute();
  $personas = $st->fetchAll(PDO::FETCH_NUM);
  $st = $pdo->prepare("SELECT idoficinas, nombre FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-') ORDER BY nombre ASC");
  $st->execute();
  $oficinas = $st->fetchAll(PDO::FETCH_NUM);
} catch (Exception $e) {
  error_log($e->getMessage());
}
$activos = 0;
foreach ($filas as $r) {
  if (intval($r[3]) === 1) $activos++;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Responsables — SISGEDO</title>
  <link rel="icon" type="image/png" href="../images/icono sistema.jpg" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/v_vistas.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body class="v-responsables" data-csrf="<?= h($tok) ?>">
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
      <?php $NAV_ACTIVO = 'vistas/v_responsables.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main">
        <h1 class="t">Responsables</h1>
        <div class="subt">Asignación de personal responsable por oficina (quien recepciona y deriva los documentos)</div>

        <div class="kpis">
          <div class="kpi">
            <div class="ic t-blue"><i class="fa fa-sitemap"></i></div>
            <div>
              <div class="lab lab-blue">Asignaciones</div>
              <div class="num" style="color:#1e293b"><?= number_format(count($filas)) ?></div>
              <div class="ss">Total registradas</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-green"><i class="fa fa-check-circle"></i></div>
            <div>
              <div class="lab lab-green">Activos</div>
              <div class="num" style="color:#16a34a"><?= number_format($activos) ?></div>
              <div class="ss">Responsables vigentes</div>
            </div>
          </div>
          <div class="kpi" style="cursor:pointer" onclick="abrirNuevoRes()">
            <div class="ic t-purple"><i class="fa fa-plus"></i></div>
            <div>
              <div class="lab lab-purple">Nuevo</div>
              <div class="num" style="color:#6d28d9;font-size:16px;padding-top:6px">Asignar responsable</div>
              <div class="ss">Crear asignación</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" placeholder="Buscar por persona u oficina..." autocomplete="off" /><i class="fa fa-search"></i></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn" onclick="document.getElementById('buscador').value='';filtrarRes()"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g"><button class="btn btn-p btn-sm" onclick="abrirNuevoRes()"><i class="fa fa-plus"></i> Asignar responsable</button></div>
            <div class="g"><span class="muted" id="contadorRes"><?= number_format(count($filas)) ?> registro(s)</span></div>
          </div>

          <div class="rowg thead">
            <span>Personal</span><span>Oficina</span><span>Estado</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($filas) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No hay responsables asignados.</div>
            <?php }
            foreach ($filas as $idxR => $r) {
              $act = intval($r[3]) === 1; ?>
              <div class="row rowg <?= $idxR === 0 ? 'sel' : '' ?>" data-id="<?= h($r[0]) ?>" data-per="<?= h($r[1]) ?>" data-idper="<?= h($r[4]) ?>" data-ofi="<?= h($r[2]) ?>" data-idofi="<?= h($r[5]) ?>" data-est="<?= $act ? '1' : '0' ?>">
                <div class="dcell"><span class="cell-txt" style="font-weight:600;color:#1e293b"><?= h($r[1]) ?></span></div>
                <div class="dcell"><span class="cell-txt"><?= h($r[2]) ?></span></div>
                <div><span class="st"><span class="dot <?= $act ? 'd-green' : 'd-amber' ?>"></span> <?= $act ? 'Activo' : 'Inactivo' ?></span></div>
                <div class="act">
                  <button class="ab ab-g" title="Editar" onclick="abrirEditarRes(this)"><i class="fa fa-pencil"></i></button>
                  <button class="ab ab-r" title="Eliminar" onclick="eliminarRes(this)"><i class="fa fa-trash-o"></i></button>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles de la asignación</h2>
        </div>
        <div class="bd">
          <?php $first = count($filas) > 0 ? $filas[0] : null;
          if ($first) { ?>
            <div class="docbox">
              <div class="ic"><i class="fa fa-user"></i></div>
              <div style="min-width:0">
                <div id="p-per" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($first[1]) ?></div><span class="badge b-blue" id="p-est-badge"><?= intval($first[3]) === 1 ? 'ACTIVO' : 'INACTIVO' ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Asignación</div>
              <dl style="margin-top:6px">
                <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                  <dt style="color:#94a3b8">Oficina</dt>
                  <dd id="p-ofi" style="color:#1e293b;overflow-wrap:break-word"><?= h($first[2]) ?></dd>
                </div>
                <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                  <dt style="color:#94a3b8">Estado</dt>
                  <dd id="p-est" style="color:#1e293b"><?= intval($first[3]) === 1 ? 'Activo (recepciona y deriva)' : 'Inactivo' ?></dd>
                </div>
              </dl>
              <div class="muted" style="font-size:11.5px;margin-top:14px"><i class="fa fa-info-circle"></i> El responsable activo de una oficina es quien firma las recepciones y derivaciones. Si una persona deja el cargo, márcala como inactiva en lugar de eliminarla (conserva el historial).</div>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Sin asignaciones para mostrar.</div><?php } ?>
        </div>
        <?php if ($first) { ?>
          <div class="ftr">
            <button class="btn btn-p" style="flex:1;justify-content:center" onclick="editarSeleccionadoRes()"><i class="fa fa-pencil"></i> Editar</button>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>

  <!-- MODAL NUEVO / EDITAR RESPONSABLE -->
  <div class="modal" id="modalRes">
    <div class="box">
      <div class="mh">
        <h3 id="re_titulo">Asignar responsable</h3><button class="ib" style="color:#94a3b8" onclick="cerrarRes()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>Personal <span class="req">*</span></label><select id="re_personal">
            <option value="">Seleccione...</option><?php foreach ($personas as $p) { ?><option value="<?= h($p[0]) ?>"><?= h($p[1]) ?></option><?php } ?>
          </select></div>
        <div><label>Oficina <span class="req">*</span></label><select id="re_oficina">
            <option value="">Seleccione...</option><?php foreach ($oficinas as $o) { ?><option value="<?= h($o[0]) ?>"><?= h($o[1]) ?></option><?php } ?>
          </select></div>
        <div><label class="ofi-item" style="border:1px solid #e2e8f0;border-radius:8px"><input type="checkbox" id="re_estado" checked /> <span>Responsable activo</span></label></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarRes()">Cancelar</button><button class="btn btn-p" id="re_guardar"><i class="fa fa-check"></i> Guardar</button></div>
    </div>
  </div>

  <script src="../js/v_responsables.js"></script>
</body>

</html>