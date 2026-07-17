<?php
    session_start();
    require_once '../clases/guardia.php';
    $_SESSION["modulo"] = "1";
    if (!isset($_SESSION['alias'])) { header("Location: ../login.php"); exit; }

    require_once '../conexion/conexion.php';
    require_once '../clases/seguridad.php';

    $alias = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';
    $idoficina = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
    $tok = csrf_token();
    $hoy = date('Y-m-d');

    $desdeSel = isset($_GET['desde']) && trim($_GET['desde']) !== '' ? trim($_GET['desde']) : $hoy;
    $hastaSel = isset($_GET['hasta']) && trim($_GET['hasta']) !== '' ? trim($_GET['hasta']) : $hoy;

    $emiHoy = 0; $emiMes = 0; $enRango = 0; $vista = array();
    try {
        $pdo = (new Conexion())->conectar();
        $q = $pdo->prepare("SELECT COUNT(*) FROM docs_emitidos WHERE idoficina_registro=? AND date(fecha)=curdate()");
        $q->execute(array($idoficina)); $emiHoy = intval($q->fetchColumn());
        $q = $pdo->prepare("SELECT COUNT(*) FROM docs_emitidos WHERE idoficina_registro=? AND year(fecha)=year(now()) AND month(fecha)=month(now())");
        $q->execute(array($idoficina)); $emiMes = intval($q->fetchColumn());
        // Vista previa real del rango seleccionado
        $q = $pdo->prepare("SELECT COUNT(*) FROM docs_emitidos WHERE idoficina_registro=? AND date(fecha) BETWEEN ? AND ?");
        $q->execute(array($idoficina, $desdeSel, $hastaSel)); $enRango = intval($q->fetchColumn());
        $q = $pdo->prepare("SELECT de.iddocs_emitidos, documentos.nombre_documento, de.numero, de.asunto,
                                   COALESCE((SELECT o2.nombre FROM oficinas o2 WHERE o2.idoficinas=de.idoficina),'—'),
                                   date_format(date_add(de.fecha,interval 0 hour),'%d/%m/%Y %h:%i %p'), de.estado
                            FROM docs_emitidos de
                            INNER JOIN documentos ON de.iddocumento=documentos.iddocumentos
                            WHERE de.idoficina_registro=? AND date(de.fecha) BETWEEN ? AND ?
                            ORDER BY de.fecha DESC LIMIT 100");
        $q->execute(array($idoficina, $desdeSel, $hastaSel));
        $vista = $q->fetchAll(PDO::FETCH_NUM);
    } catch (Exception $e) { error_log($e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Cuaderno Interno — SISGEDO</title>
<link rel="icon" type="image/png" href="../images/icono sistema.jpg" />
<link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />
<link rel="stylesheet" href="../css/sisgedo-theme.css" />
<link rel="stylesheet" href="../css/v_recepcion_proveido.css" />
<link rel="stylesheet" href="../css/v_vistas.css" />
<script src="../assets/js/jquery-2.1.4.min.js"></script>
</head>
<body class="v-reporte-cuadernointerno" data-csrf="<?= h($tok) ?>" data-idoficina="<?= h($idoficina) ?>">
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
        <div><div class="wl">Bienvenido,</div><div class="nm"><?= h($alias) ?></div></div>
        <a href="../clases/cerrarsesion.php" class="ib" title="Salir"><i class="fa fa-sign-out"></i></a>
      </div>
    </div>
  </div>

  <div class="body">
    <?php $NAV_ACTIVO = 'vistas/v_reporte_cuadernointerno.php'; include __DIR__ . '/../partes/nav_nuevo.php'; ?>

    <div class="main">
      <h1 class="t">Cuaderno de Registro Interno</h1>
      <div class="subt">Genera el PDF del cuaderno de documentos emitidos por tu oficina</div>

      <div class="kpis">
        <div class="kpi"><div class="ic t-blue"><i class="fa fa-calendar-check-o"></i></div>
          <div><div class="lab lab-blue">Emitidos hoy</div><div class="num" style="color:#1e293b"><?= number_format($emiHoy) ?></div><div class="ss">Por tu oficina</div></div>
        </div>
        <div class="kpi"><div class="ic t-purple"><i class="fa fa-filter"></i></div>
          <div><div class="lab lab-purple">En el rango</div><div class="num" style="color:#1e293b"><?= number_format($enRango) ?></div><div class="ss">Del <?= h(date('d/m',strtotime($desdeSel))) ?> al <?= h(date('d/m',strtotime($hastaSel))) ?></div></div>
        </div>
        <div class="kpi"><div class="ic t-green"><i class="fa fa-book"></i></div>
          <div><div class="lab lab-green">Este mes</div><div class="num" style="color:#16a34a"><?= number_format($emiMes) ?></div><div class="ss">Documentos internos</div></div>
        </div>
        <div class="kpi" style="cursor:pointer" onclick="generarCuaderno()"><div class="ic t-orange"><i class="fa fa-file-pdf-o"></i></div>
          <div><div class="lab lab-orange">Cuaderno</div><div class="num" style="color:#f97316;font-size:15px;padding-top:6px">Generar PDF</div><div class="ss">Formato oficial</div></div>
        </div>
      </div>

      <div class="card">
        <div class="filters">
          <div class="fld"><label>Desde</label><div class="inp"><input id="f_inicio" type="date" value="<?= h($desdeSel) ?>" max="<?= h($hoy) ?>" /></div></div>
          <div class="fld"><label>Hasta</label><div class="inp"><input id="f_fin" type="date" value="<?= h($hastaSel) ?>" max="<?= h($hoy) ?>" /></div></div>
          <div class="field-actions">
            <button type="button" class="btn btn-p" onclick="aplicarRango()"><i class="fa fa-search"></i> Ver rango</button>
            <button type="button" class="btn" onclick="window.location='v_reporte_cuadernointerno.php'"><i class="fa fa-refresh"></i> Hoy</button>
            <button type="button" class="btn" onclick="generarCuaderno()"><i class="fa fa-file-pdf-o"></i> PDF</button>
          </div>
        </div>
      </div>

      <div class="card table-card">
        <div class="tbar">
          <div class="g"><span style="font-size:13px;font-weight:700;color:#1e293b"><i class="fa fa-book" style="color:#2563eb"></i> Vista previa del cuaderno — <?= h(date('d/m/Y',strtotime($desdeSel))) ?> al <?= h(date('d/m/Y',strtotime($hastaSel))) ?></span></div>
          <div class="g"><span class="muted"><?= number_format($enRango) ?> registro(s)<?= $enRango>100 ? ' — mostrando los últimos 100 (el PDF incluye todos)' : '' ?></span></div>
        </div>
        <div class="rowg thead">
          <span>Código</span><span>Documento</span><span>Número</span><span>Asunto</span><span>Destino</span><span>Fecha</span><span></span>
        </div>
        <div>
        <?php if (count($vista) === 0) { ?>
          <div style="padding:44px;text-align:center;color:#94a3b8;font-size:13px">No hay documentos emitidos en el rango seleccionado.</div>
        <?php } foreach ($vista as $v) { ?>
          <div class="row rowg">
            <span class="exp"><?= h($v[0]) ?></span>
            <div class="dcell"><span class="cell-txt"><?= h($v[1]) ?></span></div>
            <div class="dcell"><span class="cell-txt"><?= h($v[2]) ?></span></div>
            <div class="dcell"><p class="asun"><?= h($v[3]) ?></p></div>
            <div class="dcell"><span class="cell-txt"><?= h($v[4]) ?></span></div>
            <div class="muted"><?= h($v[5]) ?></div>
            <div class="act"><a class="ab ab-b" title="Seguimiento" target="_blank" href="../reportes/consulta_interna.php?codigo_doc=<?= h($v[0]) ?>"><i class="fa fa-eye"></i></a></div>
          </div>
        <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../js/v_reporte_cuadernointerno.js"></script>
</body>
</html>
