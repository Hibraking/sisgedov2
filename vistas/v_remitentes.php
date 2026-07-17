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

$totalRemitentes = 0;
$conEmail = 0;
$nuevosMes = 0;
try {
  $pdo = (new Conexion())->conectar();
  $totalRemitentes = intval($pdo->query("SELECT COUNT(*) FROM remitente")->fetchColumn());
  $conEmail = intval($pdo->query("SELECT COUNT(*) FROM remitente WHERE email IS NOT NULL AND TRIM(email)<>''")->fetchColumn());
  $nuevosMes = intval($pdo->query("SELECT COUNT(DISTINCT idremitente) FROM registro_documentos WHERE year(fecha_registro)=year(now()) AND month(fecha_registro)=month(now())")->fetchColumn());
} catch (Exception $e) {
  error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Remitentes — SISGEDO</title>
  <link rel="icon" type="image/png" href="../images/icono sistema.jpg" />
  <link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="../css/sisgedo-theme.css" />
  <link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
  <link rel="stylesheet" href="../css/v_vistas.css" />
  <script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>

<body class="v-remitentes" data-csrf="<?= h($tok) ?>">
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
      <?php $NAV_ACTIVO = 'vistas/v_remitentes.php';
      include __DIR__ . '/../partes/nav_nuevo.php'; ?>

      <div class="main">
        <h1 class="t">Remitentes</h1>
        <div class="subt">Personas y entidades que presentan documentos en mesa de partes — búsqueda y paginación en servidor</div>

        <div class="kpis">
          <div class="kpi">
            <div class="ic t-blue"><i class="fa fa-user"></i></div>
            <div>
              <div class="lab lab-blue">Remitentes</div>
              <div class="num" style="color:#1e293b"><?= number_format($totalRemitentes) ?></div>
              <div class="ss">Total registrados</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-purple"><i class="fa fa-envelope-o"></i></div>
            <div>
              <div class="lab lab-purple">Con email</div>
              <div class="num" style="color:#1e293b"><?= number_format($conEmail) ?></div>
              <div class="ss">Pueden ser notificados</div>
            </div>
          </div>
          <div class="kpi">
            <div class="ic t-orange"><i class="fa fa-calendar"></i></div>
            <div>
              <div class="lab lab-orange">Activos este mes</div>
              <div class="num" style="color:#1e293b"><?= number_format($nuevosMes) ?></div>
              <div class="ss">Presentaron documentos</div>
            </div>
          </div>
          <div class="kpi" style="cursor:pointer" onclick="abrirNuevoRem()">
            <div class="ic t-green"><i class="fa fa-user-plus"></i></div>
            <div>
              <div class="lab lab-green">Nuevo</div>
              <div class="num" style="color:#16a34a;font-size:15px;padding-top:6px">Agregar remitente</div>
              <div class="ss">Crear un registro</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="filters">
            <div class="fld field-search"><label>&nbsp;</label>
              <div class="inp"><input id="buscador" placeholder="Buscar por apellidos, nombres, email, teléfono..." autocomplete="off" /><i class="fa fa-search"></i></div>
            </div>
            <div class="field-actions">
              <button type="button" class="btn" onclick="document.getElementById('buscador').value='';cargarPagina(0)"><i class="fa fa-refresh"></i> Limpiar</button>
            </div>
          </div>
        </div>

        <div class="card table-card">
          <div class="tbar">
            <div class="g">
              <button class="btn btn-p btn-sm" onclick="abrirNuevoRem()"><i class="fa fa-user-plus"></i> Nuevo remitente</button>
            </div>
            <div class="g"><span class="muted" id="contadorRem">Cargando...</span></div>
          </div>

          <div class="rowg thead">
            <span>Apellidos</span><span>Nombres</span><span>Dirección</span><span>Email</span><span>Teléfono</span><span>Acciones</span>
          </div>

          <div id="tbody">
            <div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">Cargando remitentes...</div>
          </div>

          <div class="pag">
            <span class="muted" id="pagInfo"></span>
            <div style="display:flex;align-items:center;gap:16px">
              <div class="inp btn-sm" style="width:120px;padding:0"><select id="per_page" style="width:100%;height:100%;border:0;outline:0;background:none;padding:0 10px;color:#475569;font-size:12px">
                  <option value="25">25 por página</option>
                  <option value="50">50 por página</option>
                  <option value="100">100 por página</option>
                </select><i class="fa fa-angle-down" style="padding-right:10px"></i></div>
              <div style="display:flex;gap:4px">
                <button class="pg" id="pgPrimera" title="Primera"><i class="fa fa-angle-double-left"></i></button>
                <button class="pg" id="pgAnterior" title="Anterior"><i class="fa fa-angle-left"></i></button>
                <span class="pg on" id="pgActual" style="width:auto;padding:0 12px">1</span>
                <button class="pg" id="pgSiguiente" title="Siguiente"><i class="fa fa-angle-right"></i></button>
                <button class="pg" id="pgUltima" title="Última"><i class="fa fa-angle-double-right"></i></button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PANEL DERECHO -->
      <div class="rp">
        <div class="hd">
          <h2>Detalles del remitente</h2>
        </div>
        <div class="bd">
          <div class="docbox">
            <div class="ic"><i class="fa fa-user"></i></div>
            <div style="min-width:0">
              <div id="p-nombre" style="font-size:13px;font-weight:600;color:#1e293b;line-height:1.35">Selecciona un remitente</div><span class="badge b-blue" id="p-id"></span>
            </div>
          </div>
          <div class="rp-scroll">
            <div class="sect">Contacto</div>
            <dl style="margin-top:6px">
              <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                <dt style="color:#94a3b8">Dirección</dt>
                <dd id="p-dir" style="color:#1e293b;overflow-wrap:break-word">—</dd>
              </div>
              <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                <dt style="color:#94a3b8">Email</dt>
                <dd id="p-ema" style="color:#1e293b;overflow-wrap:break-word">—</dd>
              </div>
              <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px">
                <dt style="color:#94a3b8">Teléfono</dt>
                <dd id="p-tel" style="color:#1e293b">—</dd>
              </div>
            </dl>
            <div class="muted" style="font-size:11.5px;margin-top:14px"><i class="fa fa-info-circle"></i> Haz clic en una fila para ver sus datos aquí. Usa el buscador para localizar por apellidos, email o teléfono.</div>
          </div>
        </div>
        <div class="ftr">
          <button class="btn btn-p" style="flex:1;justify-content:center" onclick="editarSeleccionadoRem()"><i class="fa fa-pencil"></i> Editar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL NUEVO / EDITAR REMITENTE -->
  <div class="modal" id="modalRem">
    <div class="box">
      <div class="mh">
        <h3 id="rm_titulo">Nuevo remitente</h3><button class="ib" style="color:#94a3b8" onclick="cerrarRem()"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb">
        <div><label>Apellidos <span class="req">*</span></label><input id="rm_apellidos" type="text" /></div>
        <div><label>Nombres <span class="req">*</span></label><input id="rm_nombres" type="text" /></div>
        <div><label>Dirección</label><input id="rm_direccion" type="text" /></div>
        <div><label>Email</label><input id="rm_email" type="email" /></div>
        <div><label>Teléfono</label><input id="rm_telefono" type="text" /></div>
      </div>
      <div class="mf"><button class="btn" onclick="cerrarRem()">Cancelar</button><button class="btn btn-p" id="rm_guardar"><i class="fa fa-check"></i> Guardar</button></div>
    </div>
  </div>

  <script src="../js/v_remitentes.js"></script>
</body>

</html>