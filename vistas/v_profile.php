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
$idusuario = isset($_SESSION['usuario']) ? intval($_SESSION['usuario']) : 0;
$idoficinaSes = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
$tok = csrf_token();
$esAdmin = ($idusuario === 1);

$perfil = null;
$oficinasResp = array();
$nRoles = 0;
$nOficinas = 0;
$nDocs = 0;
$pctPermisos = 0;
$miembroDesde = '—';
try {
  $pdo = (new Conexion())->conectar();

  $st = $pdo->prepare("SELECT apellidos, nombres, COALESCE(direccion,''), COALESCE(email,''), COALESCE(telefono,''), COALESCE(usuario,''), COALESCE(imagen,'') FROM personal WHERE idpersonal=?");
  $st->execute(array($idusuario));
  $perfil = $st->fetch(PDO::FETCH_NUM);

  $st = $pdo->prepare("SELECT o.idoficinas, o.nombre, COALESCE(o.descripcion,''), r.estado FROM responsables r INNER JOIN oficinas o ON r.idoficina=o.idoficinas WHERE r.idpersonal=? ORDER BY r.estado DESC, o.nombre ASC");
  $st->execute(array($idusuario));
  $oficinasResp = $st->fetchAll(PDO::FETCH_NUM);
  $nOficinas = count($oficinasResp);

  // Roles: rol de sistema + responsabilidades activas
  $activas = 0;
  foreach ($oficinasResp as $o) {
    if (intval($o[3]) === 1) $activas++;
  }
  $nRoles = max(1, ($esAdmin ? 1 : 0) + ($activas > 0 ? 1 : 0));

  // Documentos en los que participó (mesa de partes + movimientos como responsable)
  $st = $pdo->prepare("SELECT COUNT(*) FROM registro_documentos WHERE idpersonal=?");
  $st->execute(array($idusuario));
  $nDocs += intval($st->fetchColumn());
  $st = $pdo->prepare("SELECT COUNT(*) FROM movimientos m INNER JOIN responsables r ON m.idresponsable=r.idresponsables WHERE r.idpersonal=?");
  $st->execute(array($idusuario));
  $nDocs += intval($st->fetchColumn());

  // Permisos: concedidos / total de módulos
  $totalMenus = intval($pdo->query("SELECT COUNT(*) FROM menu WHERE direccion IS NOT NULL AND direccion <> '#'")->fetchColumn());
  if ($esAdmin) {
    $pctPermisos = 100;
  } elseif ($totalMenus > 0) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM accesos_usuario a INNER JOIN menu m ON a.idmenu=m.idmenu WHERE a.idusuario=? AND a.access=1 AND m.direccion IS NOT NULL AND m.direccion <> '#'");
    $st->execute(array($idusuario));
    $pctPermisos = intval(round(intval($st->fetchColumn()) * 100 / $totalMenus));
  }

  // Miembro desde: primera actividad registrada del usuario
  $fechas = array();
  $st = $pdo->prepare("SELECT MIN(fecha_registro) FROM registro_documentos WHERE idpersonal=?");
  $st->execute(array($idusuario));
  $f = $st->fetchColumn();
  if ($f) $fechas[] = $f;
  $st = $pdo->prepare("SELECT MIN(m.fecha_hora) FROM movimientos m INNER JOIN responsables r ON m.idresponsable=r.idresponsables WHERE r.idpersonal=?");
  $st->execute(array($idusuario));
  $f = $st->fetchColumn();
  if ($f) $fechas[] = $f;
  if (count($fechas) > 0) {
    sort($fechas);
    $miembroDesde = date('d/m/Y', strtotime($fechas[0]));
  }
} catch (Exception $e) {
  error_log($e->getMessage());
}

$nombreCompleto = $perfil ? trim($perfil[1] . ' ' . $perfil[0]) : $alias;
$rolPrincipal = $esAdmin ? 'Administrador del Sistema' : 'Usuario del Sistema';

// Foto de perfil (solo si el archivo realmente existe en images/perfiles/)
$fotoUrl = '';
if ($perfil && $perfil[6] !== '' && is_file(__DIR__ . '/../images/perfiles/' . $perfil[6])) {
  $fotoUrl = '../images/perfiles/' . rawurlencode($perfil[6]);
}

// Último acceso REAL: clases/login.php guarda en sesión el acceso anterior
// y registra el actual en personal.ultimo_acceso (ver ultimo_acceso.sql).
$formatearAcceso = function ($ua) {
  $ts = strtotime($ua);
  if ($ts === false) {
    return 'No registrado aún';
  }
  return (date('Y-m-d', $ts) === date('Y-m-d')) ? ('Hoy, ' . date('h:i a', $ts)) : date('d/m/Y h:i a', $ts);
};
$ultimoAcceso = 'No registrado aún';
if (array_key_exists('ultimo_acceso_anterior', $_SESSION)) {
  $ua = $_SESSION['ultimo_acceso_anterior'];
  $ultimoAcceso = $ua ? $formatearAcceso($ua) : 'Este es tu primer acceso registrado';
} elseif (isset($pdo)) {
  // Sesión iniciada antes de activar el registro: intenta leer la columna
  try {
    $st = $pdo->prepare("SELECT ultimo_acceso FROM personal WHERE idpersonal=?");
    if ($st !== false && @$st->execute(array($idusuario))) {
      $ua = $st->fetchColumn();
      if ($ua) {
        $ultimoAcceso = $formatearAcceso($ua);
      }
    }
  } catch (Exception $e) { /* columna aún no creada */
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Perfil de Usuario — SISGEDO</title>
  <link rel="icon" type="image/png" href="../<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/v_vistas.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body class="v-profile" data-csrf="<?= h($tok) ?>">
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
      <?php $NAV_ACTIVO = 'vistas/v_profile.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main">
        <div class="pf-crumbs"><i class="fa fa-home" style="color:#94a3b8"></i> <a href="../index.php">Inicio</a> <i class="fa fa-angle-right" style="color:#cbd5e1"></i> <span>Configuración</span> <i class="fa fa-angle-right" style="color:#cbd5e1"></i> <b>Perfil de Usuario</b></div>

        <div class="pf-top">
          <div>
            <h1 class="t">Perfil de Usuario</h1>
            <div class="subt">Gestiona la información de tu cuenta y preferencias</div>
          </div>
          <button class="btn" style="color:#2563eb;border-color:#bfdbfe" onclick="abrirEditarPerfil()"><i class="fa fa-pencil"></i> Editar perfil</button>
        </div>

        <!-- HERO -->
        <div class="pf-hero">
          <div class="pf-avatar"><?php if ($fotoUrl !== '') { ?><img src="<?= h($fotoUrl) ?>" alt="Foto de perfil" /><?php } else { ?><i class="fa fa-user"></i><?php } ?></div>
          <div>
            <div class="pf-nombre"><?= h($nombreCompleto) ?></div>
            <span class="pf-rol"><i class="fa fa-check-circle"></i> <?= h($rolPrincipal) ?></span>
            <div class="pf-meta">
              <span><i class="fa fa-calendar"></i> Miembro desde <?= h($miembroDesde) ?></span>
              <span><i class="fa fa-clock-o"></i> Último acceso: <?= h($ultimoAcceso) ?></span>
            </div>
          </div>
          <div class="pf-stats">
            <div class="pf-stat"><span class="ic t-blue"><i class="fa fa-users"></i></span>
              <div>
                <div class="n"><?= number_format($nRoles) ?></div>
                <div class="l">Roles</div>
              </div>
            </div>
            <div class="pf-stat"><span class="ic t-green"><i class="fa fa-building-o"></i></span>
              <div>
                <div class="n"><?= number_format($nOficinas) ?></div>
                <div class="l">Oficinas</div>
              </div>
            </div>
            <div class="pf-stat"><span class="ic t-purple"><i class="fa fa-file-text-o"></i></span>
              <div>
                <div class="n"><?= number_format($nDocs) ?></div>
                <div class="l">Documentos</div>
              </div>
            </div>
            <div class="pf-stat"><span class="ic t-orange"><i class="fa fa-shield"></i></span>
              <div>
                <div class="n"><?= number_format($pctPermisos) ?>%</div>
                <div class="l">Permisos</div>
              </div>
            </div>
          </div>
        </div>

        <div class="pf-cards">
          <!-- INFORMACIÓN PERSONAL -->
          <div class="pf-card">
            <h2><i class="fa fa-user"></i> Información Personal</h2>
            <div class="pf-grid">
              <div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-user"></i> Usuario</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[5] !== '' ? h(strtoupper($perfil[5])) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-users"></i> Apellidos</div>
                  <div class="v"><span class="txt"><?= $perfil ? h(strtoupper($perfil[0])) : '—' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-user"></i> Nombres</div>
                  <div class="v"><span class="txt"><?= $perfil ? h(strtoupper($perfil[1])) : '—' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-map-marker"></i> Dirección</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[2] !== '' ? h($perfil[2]) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-envelope-o"></i> Email</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[3] !== '' ? h(strtoupper($perfil[3])) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
              </div>
              <div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-phone"></i> Teléfono</div>
                  <div class="v"><span class="txt"><?= $perfil && $perfil[4] !== '' ? h($perfil[4]) : '<span class="pf-vacio">No especificado</span>' ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-building-o"></i> Oficina</div>
                  <div class="v"><span class="txt"><?php
                                                    $ofiPrincipal = '';
                                                    foreach ($oficinasResp as $o) {
                                                      if (intval($o[0]) === $idoficinaSes) {
                                                        $ofiPrincipal = $o[1];
                                                        break;
                                                      }
                                                    }
                                                    if ($ofiPrincipal === '' && $nOficinas > 0) {
                                                      $ofiPrincipal = $oficinasResp[0][1];
                                                    }
                                                    echo $ofiPrincipal !== '' ? h($ofiPrincipal) : '<span class="pf-vacio">No asignada</span>';
                                                    ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-users"></i> Rol Principal</div>
                  <div class="v"><span class="txt"><?= h($rolPrincipal) ?></span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-check-circle"></i> Estado</div>
                  <div class="v"><span class="badge" style="background:#ecfdf3;color:#16a34a">Activo</span></div>
                </div>
                <div class="pf-fila">
                  <div class="k"><i class="fa fa-lock"></i> Contraseña</div>
                  <div class="v"><span class="txt">••••••••••••••</span><i class="fa fa-eye-slash" style="color:#94a3b8;cursor:pointer" title="La contraseña se guarda cifrada; puedes cambiarla en Editar perfil" onclick="alert('Tu contraseña se guarda cifrada y no puede mostrarse. Usa \'Editar perfil\' para cambiarla.')"></i></div>
                </div>
              </div>
            </div>
          </div>

          <!-- OFICINAS ASIGNADAS -->
          <div class="pf-card" style="flex:1;min-height:0;overflow-y:auto">
            <h2><i class="fa fa-building-o"></i> Oficinas asignadas</h2>
            <?php if ($nOficinas === 0) { ?>
              <div class="pf-vacio" style="font-size:12.5px">No estás asignado como responsable de ninguna oficina.</div>
            <?php }
            foreach ($oficinasResp as $o) {
              $ppal = intval($o[0]) === $idoficinaSes; ?>
              <div class="pf-ofi">
                <span class="oi"><i class="fa fa-building-o"></i></span>
                <div style="flex:1;min-width:0">
                  <div class="on"><?= h($o[1]) ?></div>
                  <div class="od"><?= $o[2] !== '' ? h($o[2]) : 'Oficina de la entidad' ?></div>
                </div>
                <?php if ($ppal) { ?><span class="pf-badge-ppal">Principal</span><?php } elseif (intval($o[3]) !== 1) { ?><span class="badge b-gray">Inactivo</span><?php } ?>
              </div>
            <?php } ?>
          </div>
        </div>

        <div class="pf-footer"><b>SISGEDO</b> © <?= date('Y') ?> Todos los derechos reservados. &nbsp;|&nbsp; Versión 2.0.0</div>
      </div>
    </div>
  </div>

  <!-- MODAL EDITAR PERFIL -->
  <div class="modal" id="modalPerfil">
    <div class="box">
      <div class="mh">
        <h3>Editar perfil</h3><button class="ib" style="color:#94a3b8" onclick="cerrarEditarPerfil()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>Foto de perfil <span class="muted" style="font-size:11px">(JPG, PNG, GIF o WEBP — máx. 3 MB)</span></label>
          <div style="display:flex;align-items:center;gap:12px">
            <span id="ep_prev" class="pf-avatar" style="width:54px;height:54px;font-size:22px;border-width:2px;flex-shrink:0"><?php if ($fotoUrl !== '') { ?><img src="<?= h($fotoUrl) ?>" alt="" /><?php } else { ?><i class="fa fa-user"></i><?php } ?></span>
            <input id="ep_foto" type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="flex:1;font-size:12px" />
          </div>
        </div>
        <div><label>Dirección</label><input id="ep_direccion" type="text" value="<?= $perfil ? h($perfil[2]) : '' ?>" /></div>
        <div><label>Email</label><input id="ep_email" type="email" value="<?= $perfil ? h($perfil[3]) : '' ?>" /></div>
        <div><label>Teléfono</label><input id="ep_telefono" type="text" value="<?= $perfil ? h($perfil[4]) : '' ?>" /></div>
        <div style="border-top:1px solid #f1f5f9;padding-top:12px"><label>Nueva contraseña <span class="muted" style="font-size:11px">(vacía = no cambiar)</span></label><input id="ep_clave_nueva" type="password" autocomplete="new-password" /></div>
        <div><label>Contraseña actual <span class="muted" style="font-size:11px">(solo si cambias la contraseña)</span></label><input id="ep_clave_actual" type="password" autocomplete="current-password" /></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarEditarPerfil()">Cancelar</button><button class="btn btn-p" id="ep_guardar"><i class="fa fa-check"></i> Guardar cambios</button></div>
    </div>
  </div>

  <script src="../js/v_profile.js"></script>
</body>

</html>