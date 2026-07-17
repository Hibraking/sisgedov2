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
$conUsuario = 0;
$respActivos = 0;
$mapaOfis = array();
try {
  $pdo = (new Conexion())->conectar();
  $st = $pdo->prepare("SELECT idpersonal, apellidos, nombres, COALESCE(usuario,''), COALESCE(email,''), COALESCE(telefono,''), COALESCE(direccion,'') FROM personal ORDER BY apellidos ASC");
  $st->execute();
  $filas = $st->fetchAll(PDO::FETCH_NUM);
  $conUsuario = intval($pdo->query("SELECT COUNT(*) FROM personal WHERE usuario IS NOT NULL AND TRIM(usuario)<>''")->fetchColumn());
  $respActivos = intval($pdo->query("SELECT COUNT(DISTINCT idpersonal) FROM responsables WHERE estado=1")->fetchColumn());
  // Oficinas donde cada persona es responsable (para el panel de detalles)
  $st = $pdo->query("SELECT r.idpersonal, GROUP_CONCAT(o.nombre SEPARATOR ' • ') FROM responsables r INNER JOIN oficinas o ON r.idoficina=o.idoficinas GROUP BY r.idpersonal");
  foreach ($st->fetchAll(PDO::FETCH_NUM) as $mo) {
    $mapaOfis[intval($mo[0])] = (string)$mo[1];
  }
} catch (Exception $e) {
  error_log($e->getMessage());
}
$first = count($filas) > 0 ? $filas[0] : null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Personal — SISGEDO</title>
  <link rel="icon" type="image/png" href="../images/icono sistema.jpg" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/v_vistas.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body class="v-personal" data-csrf="<?= h($tok) ?>">
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
      <?php $NAV_ACTIVO = 'vistas/v_personal.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main">
        <h1 class="t">Personal</h1>
        <div class="subt">Trabajadores y usuarios del sistema — la contraseña se guarda siempre cifrada</div>

        <div class="kpis">
          <div class="kpi">
            <div class="ic t-blue"><i class="fa fa-users"></i></div>
            <div>
              <div class="lab lab-blue">Personal</div>
              <div class="num" style="color:#1e293b"><?= number_format(count($filas)) ?></div>
              <div class="ss">Registrados</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-purple"><i class="fa fa-key"></i></div>
            <div>
              <div class="lab lab-purple">Con acceso</div>
              <div class="num" style="color:#1e293b"><?= number_format($conUsuario) ?></div>
              <div class="ss">Tienen usuario del sistema</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-orange"><i class="fa fa-sitemap"></i></div>
            <div>
              <div class="lab lab-orange">Responsables</div>
              <div class="num" style="color:#1e293b"><?= number_format($respActivos) ?></div>
              <div class="ss">Activos en oficinas</div>
            </div>
          </div>
          <div class="kpi" style="cursor:pointer" onclick="abrirNuevoPer()">
            <div class="ic t-green"><i class="fa fa-user-plus"></i></div>
            <div>
              <div class="lab lab-green">Nuevo</div>
              <div class="num" style="color:#16a34a;font-size:15px;padding-top:6px">Agregar personal</div>
              <div class="ss">Crear un registro</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" placeholder="Buscar por apellidos, usuario, email..." autocomplete="off" /><i class="fa fa-search"></i></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn" onclick="document.getElementById('buscador').value='';filtrarPer()"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g"><button class="btn btn-p btn-sm" onclick="abrirNuevoPer()"><i class="fa fa-user-plus"></i> Nuevo personal</button></div>
            <div class="g"><span class="muted" id="contadorPer"><?= number_format(count($filas)) ?> registro(s)</span></div>
          </div>

          <div class="rowg thead">
            <span>Apellidos</span><span>Nombres</span><span>Usuario</span><span>Email</span><span>Teléfono</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <?php if (count($filas) === 0) { ?>
              <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No hay personal registrado.</div>
            <?php }
            foreach ($filas as $r) { ?>
              <div class="row rowg <?= $r === $first ? 'sel' : '' ?>" data-id="<?= h($r[0]) ?>" data-ape="<?= h($r[1]) ?>" data-nom="<?= h($r[2]) ?>" data-usu="<?= h($r[3]) ?>" data-ema="<?= h($r[4]) ?>" data-tel="<?= h($r[5]) ?>" data-dir="<?= h($r[6]) ?>" data-ofis="<?= h(isset($mapaOfis[intval($r[0])]) ? $mapaOfis[intval($r[0])] : '') ?>">
                <div class="dcell"><span class="cell-txt" style="font-weight:600;color:#1e293b"><?= h($r[1]) ?></span></div>
                <div class="dcell"><span class="cell-txt"><?= h($r[2]) ?></span></div>
                <div><?php if ($r[3] !== '') { ?><span class="badge b-blue"><?= h($r[3]) ?></span><?php } else { ?><span class="muted">—</span><?php } ?></div>
                <div class="dcell"><span class="cell-txt"><?= $r[4] !== '' ? h($r[4]) : '—' ?></span></div>
                <div class="dcell"><span class="cell-txt"><?= $r[5] !== '' ? h($r[5]) : '—' ?></span></div>
                <div class="act">
                  <button class="ab ab-g" title="Editar" onclick="event.stopPropagation();abrirEditarPer(this)"><i class="fa fa-pencil"></i></button>
                  <?php if ($r[3] !== '') { ?><a class="ab ab-b" title="Gestionar accesos al sistema" href="v_accesos_personal.php?u=<?= h($r[0]) ?>" onclick="event.stopPropagation()"><i class="fa fa-key"></i></a><?php } ?>
                  <button class="ab ab-r" title="Eliminar" onclick="event.stopPropagation();eliminarPer(this)"><i class="fa fa-trash-o"></i></button>
                </div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles del personal</h2>
        </div>
        <div class="bd">
          <?php if ($first) { ?>
            <div class="docbox">
              <div class="ic"><i class="fa fa-user"></i></div>
              <div style="min-width:0">
                <div id="p-nombre" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35"><?= h($first[1] . ', ' . $first[2]) ?></div><span class="badge b-blue" id="p-usu"><?= $first[3] !== '' ? h($first[3]) : 'SIN USUARIO' ?></span>
              </div>
            </div>
            <div class="rp-scroll">
              <div class="sect">Contacto</div>
              <dl style="margin-top:6px">
                <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                  <dt style="color:#94a3b8">Email</dt>
                  <dd id="p-ema" style="color:#1e293b;overflow-wrap:break-word"><?= $first[4] !== '' ? h($first[4]) : '—' ?></dd>
                </div>
                <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                  <dt style="color:#94a3b8">Teléfono</dt>
                  <dd id="p-tel" style="color:#1e293b"><?= $first[5] !== '' ? h($first[5]) : '—' ?></dd>
                </div>
                <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                  <dt style="color:#94a3b8">Dirección</dt>
                  <dd id="p-dir" style="color:#1e293b;overflow-wrap:break-word"><?= $first[6] !== '' ? h($first[6]) : '—' ?></dd>
                </div>
              </dl>
              <div class="sect" style="margin-top:14px">Responsable en</div>
              <p id="p-ofis" style="margin-top:8px;font-size:12.5px;color:#475569;line-height:1.6"><?= isset($mapaOfis[intval($first[0])]) ? h($mapaOfis[intval($first[0])]) : 'Ninguna oficina asignada' ?></p>
              <div class="muted" style="font-size:11.5px;margin-top:14px"><i class="fa fa-info-circle"></i> Haz clic en una fila para ver sus datos. Las asignaciones se gestionan en Responsables.</div>
            </div>
          <?php } else { ?><div style="padding:56px 0;text-align:center;color:#94a3b8;font-size:13px">Sin personal para mostrar.</div><?php } ?>
        </div>
        <?php if ($first) { ?>
          <div class="ftr">
            <button class="btn btn-p" style="flex:1;justify-content:center" onclick="editarSeleccionadoPer()"><i class="fa fa-pencil"></i> Editar</button>
            <a class="btn" style="text-decoration:none" href="v_accesos_personal.php"><i class="fa fa-key"></i> Accesos</a>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>

  <!-- MODAL NUEVO / EDITAR PERSONAL -->
  <div class="modal" id="modalPer">
    <div class="box">
      <div class="mh">
        <h3 id="pe_titulo">Nuevo personal</h3><button class="ib" style="color:#94a3b8" onclick="cerrarPer()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>Apellidos <span class="req">*</span></label><input id="pe_apellidos" type="text" /></div>
        <div><label>Nombres <span class="req">*</span></label><input id="pe_nombres" type="text" /></div>
        <div><label>Dirección</label><input id="pe_direccion" type="text" /></div>
        <div><label>Email</label><input id="pe_email" type="email" /></div>
        <div><label>Teléfono</label><input id="pe_telefono" type="text" /></div>
        <div><label>Usuario (para iniciar sesión)</label><input id="pe_usuario" type="text" autocomplete="off" /></div>
        <div><label>Contraseña <span id="pe_clave_hint" class="muted" style="font-size:11px"></span></label><input id="pe_clave" type="password" autocomplete="new-password" /></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarPer()">Cancelar</button><button class="btn btn-p" id="pe_guardar"><i class="fa fa-check"></i> Guardar</button></div>
    </div>
  </div>

  <script src="../js/v_personal.js"></script>
</body>

</html>