<?php

/**
 * Sidebar compartido del diseño nuevo (patrón v_recepcion_proveido).
 *
 * Uso: definir $NAV_ACTIVO (p. ej. 'vistas/v_oficinas.php') antes de incluir.
 * Renderiza <div class="side">...</div>. El header lo pone cada vista.
 *
 * Muestra solo los ítems a los que el usuario tiene acceso (tabla accesos_usuario);
 * el admin (idusuario=1) ve todo. Ante error de BD no bloquea (fail-open),
 * igual que guardia.php: el permiso real lo sigue validando cada página.
 */
if (!isset($NAV_ACTIVO)) {
  $NAV_ACTIVO = '';
}

$SISGEDO_MENU = array(
  array('grupo' => 'Operaciones', 'icono' => 'fa-list', 'items' => array(
    array('Recepción', 'vistas/v_recepcion_proveido.php', 'fa-inbox'),
    array('Archivo', 'vistas/v_archivar_docs.php', 'fa-folder'),
    array('Emisión', 'vistas/v_docs_emitidos.php', 'fa-folder-o'),
    array('Derivados', 'vistas/v_recepcion_proveido.php?estado=Derivado', 'fa-share-alt', 'vistas/v_recepcion_proveido.php'),
    array('Registro', 'vistas/o_reg_documentos.php', 'fa-list-alt'),
  )),
  array('grupo' => 'Reportes', 'icono' => 'fa-bar-chart', 'items' => array(
    array('Consulta Interna', 'reportes/consulta_interna.php', 'fa-search'),
    array('Estados de Atención', 'vistas/v_reporte_estados_documento.php', 'fa-tasks'),
    array('Cuaderno Externo', 'vistas/v_reporte_cuadernoexterno.php', 'fa-book'),
    array('Cuaderno Interno', 'vistas/v_reporte_cuadernointerno.php', 'fa-book'),
  )),
  array('grupo' => 'Mantenimiento', 'icono' => 'fa-wrench', 'items' => array(
    array('Oficinas', 'vistas/v_oficinas.php', 'fa-building-o'),
    array('Personal', 'vistas/v_personal.php', 'fa-users'),
    array('Documentos', 'vistas/v_documentos.php', 'fa-file-text-o'),
    array('Tipos de Trámite', 'vistas/v_tipo_tramite.php', 'fa-random'),
    array('Remitentes', 'vistas/v_remitentes.php', 'fa-user'),
    array('Responsables', 'vistas/v_responsables.php', 'fa-sitemap'),
    array('Acciones Doc.', 'vistas/v_accion.php', 'fa-bolt'),
    array('Estados Doc.', 'vistas/v_estados_doc.php', 'fa-flag-o'),
    array('Archivadores', 'vistas/v_archivador.php', 'fa-archive'),
  )),
  array('grupo' => 'Configuración', 'icono' => 'fa-cog', 'items' => array(
    array('Menús', 'vistas/v_menu.php', 'fa-bars'),
    array('Accesos del Personal', 'vistas/v_accesos_personal.php', 'fa-key'),
  )),
);

// Direcciones permitidas para el usuario (null = todas, admin o fail-open)
$NAV_PERMITIDOS = null;
$nav_idusuario = isset($_SESSION['usuario']) ? intval($_SESSION['usuario']) : 0;
if ($nav_idusuario !== 1) {
  try {
    $nav_pdo = (new Conexion())->conectar();
    if ($nav_pdo) {
      $nav_st = $nav_pdo->prepare("select m.direccion from accesos_usuario a inner join menu m on a.idmenu=m.idmenu where a.idusuario=? and a.access=1");
      $nav_st->execute(array($nav_idusuario));
      $NAV_PERMITIDOS = array();
      foreach ($nav_st->fetchAll(PDO::FETCH_NUM) as $nav_f) {
        $NAV_PERMITIDOS[] = trim((string)$nav_f[0]);
      }
    }
  } catch (Exception $nav_e) {
    error_log('nav_nuevo: ' . $nav_e->getMessage());
    $NAV_PERMITIDOS = null;
  }
}
function nav_puede($direccionPermiso)
{
  global $NAV_PERMITIDOS;
  if ($NAV_PERMITIDOS === null) {
    return true;
  }
  return in_array($direccionPermiso, $NAV_PERMITIDOS, true);
}
// Prefijo relativo: las vistas viven en vistas/ y reportes/ (un nivel bajo la raíz).
// index.php (raíz) define $NAV_PREFIJO = '' antes de incluir.
if (!isset($NAV_PREFIJO)) {
  $NAV_PREFIJO = '../';
}

// Foto de perfil para el avatar de la cabecera (cacheada en sesión; el módulo
// Mi Perfil actualiza la caché al subir una foto nueva).
if (!isset($_SESSION['foto_perfil'])) {
  $_SESSION['foto_perfil'] = '';
  try {
    $nav_pdo2 = (isset($nav_pdo) && $nav_pdo) ? $nav_pdo : (new Conexion())->conectar();
    if ($nav_pdo2) {
      $nav_stf = $nav_pdo2->prepare("SELECT COALESCE(imagen,'') FROM personal WHERE idpersonal=?");
      if ($nav_stf !== false && @$nav_stf->execute(array($nav_idusuario))) {
        $_SESSION['foto_perfil'] = (string)$nav_stf->fetchColumn();
      }
    }
  } catch (Exception $nav_e2) {
    error_log('nav foto: ' . $nav_e2->getMessage());
  }
}
// Documentos NUEVOS de la Mesa de Partes Virtual (sin revisar ni derivar):
// se muestran como contador junto a "Registro". Dejan de contarse al derivarlos.
$NAV_NUEVOS_VIRTUAL = 0;
if (nav_puede('vistas/o_reg_documentos.php')) {
  try {
    $nav_pdo3 = (isset($nav_pdo) && $nav_pdo) ? $nav_pdo : (new Conexion())->conectar();
    if ($nav_pdo3) {
      $nav_stn = $nav_pdo3->prepare("SELECT COUNT(*) FROM registro_documentos WHERE COALESCE(recepcion_web,0)=1 AND idoficina IS NULL AND estado_recepcion=0 AND fecha_registro >= DATE_SUB(NOW(), INTERVAL ? DAY)");
      if ($nav_stn !== false && $nav_stn->execute(array(intval(SISGEDO_DIAS_NUEVO)))) {
        $NAV_NUEVOS_VIRTUAL = intval($nav_stn->fetchColumn());
      }
    }
  } catch (Exception $nav_e3) {
    error_log('nav nuevos: ' . $nav_e3->getMessage());
  }
}
$NAV_FOTO = '';
if ($_SESSION['foto_perfil'] !== '' && is_file(__DIR__ . '/../images/perfiles/' . $_SESSION['foto_perfil'])) {
  $NAV_FOTO = $NAV_PREFIJO . 'images/perfiles/' . rawurlencode($_SESSION['foto_perfil']);
}
?>
<div class="side" data-foto="<?= h($NAV_FOTO) ?>">
  <nav>
    <a href="<?= $NAV_PREFIJO ?>index.php"><i class="fa fa-home"></i> Inicio</a>
    <?php $nav_g = 0;
    foreach ($SISGEDO_MENU as $nav_grupo) {
      $nav_g++;
      $nav_items_visibles = array();
      $nav_grupo_activo = false;
      foreach ($nav_grupo['items'] as $nav_it) {
        $nav_permiso = isset($nav_it[3]) ? $nav_it[3] : $nav_it[1];
        if (!nav_puede($nav_permiso)) {
          continue;
        }
        $nav_es_activo = ($NAV_ACTIVO === $nav_it[1]);
        if ($nav_es_activo) {
          $nav_grupo_activo = true;
        }
        $nav_items_visibles[] = array($nav_it[0], $nav_it[1], $nav_it[2], $nav_es_activo);
      }
      if (count($nav_items_visibles) === 0) {
        continue;
      }
    ?>
      <div class="grp<?= $nav_grupo_activo ? ' open' : '' ?>" onclick="navToggleGrupo(this)"><i class="fa <?= $nav_grupo['icono'] ?>"></i> <span class="sp"><?= h($nav_grupo['grupo']) ?></span><i class="fa fa-angle-down caret"></i></div>
      <div class="subm<?= $nav_grupo_activo ? '' : ' cerrado' ?>">
        <?php foreach ($nav_items_visibles as $nav_v) { ?>
          <a href="<?= $NAV_PREFIJO . h($nav_v[1]) ?>" <?= $nav_v[3] ? ' class="active"' : '' ?>><i class="fa <?= $nav_v[2] ?>"></i> <?= h($nav_v[0]) ?><?php if ($nav_v[1] === 'vistas/o_reg_documentos.php' && $NAV_NUEVOS_VIRTUAL > 0) { ?> <span class="nav-new" title="Documentos nuevos de Mesa de Partes Virtual por revisar"><?= $NAV_NUEVOS_VIRTUAL > 99 ? '99+' : $NAV_NUEVOS_VIRTUAL ?></span><?php } ?></a>
        <?php } ?>
      </div>
    <?php } ?>
    <a href="<?= $NAV_PREFIJO ?>vistas/v_profile.php" <?= $NAV_ACTIVO === 'vistas/v_profile.php' ? ' class="active"' : '' ?>><i class="fa fa-user"></i> Mi perfil</a>
  </nav>
  <div class="soporte">
    <span class="ic"><i class="fa fa-headphones"></i></span>
    <div style="flex:1">
      <div class="tt">Soporte</div>
      <div class="ss">¿Necesitas ayuda?</div>
    </div>
    <i class="fa fa-angle-right" style="color:#cbd5e1"></i>
  </div>
</div>
<script src="<?= $NAV_PREFIJO ?>js/nav_nuevo.js"></script>