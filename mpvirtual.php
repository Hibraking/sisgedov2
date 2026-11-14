<?php

require_once './clases/mpvirtual.php';
require_once './conexion/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mesa de Partes Virtual — <?= htmlspecialchars(SISGEDO_ENTIDAD, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/png" href="<?= SISGEDO_IMG_FAVICON ?>" />
  <link rel="stylesheet" href="assets/font-awesome/4.5.0/css/font-awesome.min.css" />
  <link rel="stylesheet" href="css/publico.css?v=<?= @filemtime(__DIR__ . '/css/publico.css') ?>" />
  <script src="assets/js/jquery-2.1.4.min.js"></script>
</head>

<body class="mpv">
  <!-- CABECERA MUNICIPAL -->
  <div class="pub-hdr">
    <img src="<?= SISGEDO_IMG_LOGO ?>" alt="<?= htmlspecialchars(SISGEDO_ENTIDAD, ENT_QUOTES, 'UTF-8') ?>" />
    <div class="th">
      <div class="t1"><?= htmlspecialchars(SISGEDO_ENTIDAD_MAYUS, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="t2">Mesa de Partes Virtual</div>
    </div>
    <a class="enl" href="consulta_publico.php"><i class="fa fa-search"></i> Consultar expediente</a>
  </div>

  <div class="pub-main mpv-main">
    <!-- COLUMNA DE INFORMACIÓN (a la izquierda en pantallas grandes) -->
    <aside class="mpv-info">
      <div class="pub-hero">
        <h1>Mesa de Partes Virtual</h1>
        <p>Presenta tus documentos a la municipalidad sin salir de casa y haz el seguimiento con tu número de expediente.</p>
      </div>
      <?php
      // Aviso SOLO fuera del horario de atención (noches, fines de semana y
      // feriados): indica desde qué fecha se considerará presentado el documento.
      require_once './clases/calendario.php';
      $mpvAhora = date('Y-m-d H:i:s');
      $mpvOficial = sgd_cal_fecha_presentacion($mpvAhora);
      $mpvFuera = strtotime($mpvOficial) !== strtotime($mpvAhora); ?>
      <?php if ($mpvFuera) { ?>
        <div class="mpv-horario">
          <span class="h-ico"><i class="fa fa-moon-o"></i></span>
          <div>
            <div class="h-tit">Fuera del horario de atención</div>
            <div>Puedes enviar tu documento igual: se considerará presentado el
              <b><?= htmlspecialchars(sgd_cal_texto($mpvOficial), ENT_QUOTES, 'UTF-8') ?></b></div>
            <div class="h-hor"><b>Horario:</b> <?= htmlspecialchars(SISGEDO_HORARIO_TEXTO, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
      <?php } ?>
    </aside>

    <!-- COLUMNA DEL FORMULARIO -->
    <section class="mpv-form">
    <!-- PASOS -->
    <div class="pasos">
      <div class="paso activo" id="ind1"><span class="pnum">1</span><span class="ptxt">Datos personales</span></div>
      <div class="paso-linea"></div>
      <div class="paso" id="ind2"><span class="pnum">2</span><span class="ptxt">Documento</span></div>
      <div class="paso-linea"></div>
      <div class="paso" id="ind3"><span class="pnum">3</span><span class="ptxt">Constancia</span></div>
    </div>

    <div class="error-msg" id="msgError"></div>

    <!-- PASO 1: DATOS PERSONALES -->
    <div class="pub-card" id="paso1">
      <div class="mpv-tit"><i class="fa fa-user"></i>Datos del remitente</div>
      <div class="fgrid">
        <div><label>Correo electrónico <span class="req">*</span></label><input id="f_email" type="email" placeholder="ejemplo@correo.com" autocomplete="email" />
          <div class="hint">Aquí te llegará la constancia de tu trámite.</div>
        </div>
        <div><label>DNI o RUC <span class="req">*</span></label><input id="f_dni" type="text" maxlength="11" placeholder="8 dígitos (DNI) u 11 (RUC)" inputmode="numeric" /></div>
        <div><label>Apellidos <span class="req">*</span></label><input id="f_apellidos" type="text" placeholder="Apellidos completos" autocomplete="family-name" /></div>
        <div><label>Nombres <span class="req">*</span></label><input id="f_nombres" type="text" placeholder="Nombres completos" autocomplete="given-name" /></div>
        <div><label>Dirección</label><input id="f_direccion" type="text" placeholder="Dirección (opcional)" autocomplete="street-address" /></div>
        <div><label>Teléfono</label><input id="f_telefono" type="text" placeholder="Celular o fijo (opcional)" inputmode="tel" /></div>
      </div>
      <div class="acciones">
        <span></span>
        <button class="btn btn-p" onclick="irPaso2()">Continuar <i class="fa fa-arrow-right"></i></button>
      </div>
    </div>

    <!-- PASO 2: DOCUMENTO -->
    <div class="pub-card" id="paso2" style="display:none">
      <div class="mpv-tit"><i class="fa fa-file-text-o"></i>Datos del documento</div>
      <div class="fgrid fgrid-doc">
        <div><label>Tipo de documento <span class="req">*</span></label><select id="f_tipodoc">
            <option value="">Seleccione...</option><?php $mpvirtual->lista_documentos(); ?>
          </select></div>
        <div><label>Tipo de trámite <span class="req">*</span></label><select id="f_tramite">
            <option value="">Seleccione...</option><?php $mpvirtual->lista_tipo_tram(); ?>
          </select></div>
        <div><label>N° del documento <span class="req">*</span></label><input id="f_numero" type="text" placeholder="Ej. 0123-2026 (o S/N si no tiene)" /></div>
        <div><label>Folios (páginas) <span class="req">*</span></label><input id="f_folios" type="number" min="1" placeholder="Cantidad de hojas" inputmode="numeric" /></div>
        <div class="c-archivo"><label>Archivo adjunto</label><input id="f_fichero" type="file" />
          <div class="hint">Opcional. PDF, Word, Excel o imagen — máximo 15 MB.</div>
        </div>
        <div class="full"><label>Asunto <span class="req">*</span></label><textarea id="f_asunto" rows="3" placeholder="Describe brevemente qué solicitas"></textarea></div>
      </div>
      <div class="acciones">
        <button class="btn" onclick="irPaso1()"><i class="fa fa-arrow-left"></i> Atrás</button>
        <button class="btn btn-p" id="btnEnviar" onclick="enviarTramite()"><i class="fa fa-paper-plane"></i> Presentar documento</button>
      </div>
    </div>

    <!-- PASO 3: CONSTANCIA -->
    <div class="pub-card" id="paso3" style="display:none">
      <div class="exito">
        <div class="ic"><i class="fa fa-check"></i></div>
        <h2>¡Tu documento fue presentado con éxito!</h2>
        <div class="expnum" id="expNum">—</div>
        <p>Guarda este <b>número de expediente</b>: con él puedes hacer el seguimiento de tu trámite.<br /><span id="msgCorreo">También te enviamos la constancia a tu correo electrónico.</span></p>
        <div class="acciones" style="justify-content:center;flex-wrap:wrap">
          <button class="btn" onclick="copiarExp()"><i class="fa fa-clipboard"></i> Copiar número</button>
          <a class="btn btn-p" id="lnkSeguimiento" href="consulta_publico.php"><i class="fa fa-search"></i> Hacer seguimiento</a>
          <button class="btn" onclick="location.reload()"><i class="fa fa-plus"></i> Presentar otro documento</button>
        </div>
      </div>
    </div>

    </section>
  </div>

  <div class="pub-footer">
    <b><?= htmlspecialchars(SISGEDO_MUNI_SIGLAS, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(SISGEDO_ENTIDAD, ENT_QUOTES, 'UTF-8') ?></b> · Mesa de Partes Virtual © <?= date('Y') ?>
  </div>

  <script src="./js/selector_buscable.js?v=<?php echo @filemtime(__DIR__ . '/js/selector_buscable.js'); ?>"></script>
  <script src="./js/mpvirtual.js?v=<?php echo @filemtime(__DIR__ . '/js/mpvirtual.js'); ?>"></script>
  <script>
    SelectorBuscable.crear(document.getElementById('f_tipodoc'), { placeholder: 'Buscar tipo de documento...' });
    SelectorBuscable.crear(document.getElementById('f_tramite'), { placeholder: 'Buscar tipo de trámite...' });
  </script>
</body>

</html>