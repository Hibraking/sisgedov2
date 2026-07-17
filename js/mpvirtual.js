var URL_MPV = './clases/mpvirtual.php';
var URL_REMIT = './clases/remitentes.php';

function mostrarError(msj) {
  var e = document.getElementById('msgError');
  e.textContent = msj;
  e.classList.add('visible');
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
}

function limpiarError() {
  document.getElementById('msgError').classList.remove('visible');
}

function marcarPasos(n) {
  [1, 2, 3].forEach(function(i) {
    var el = document.getElementById('ind' + i);
    el.classList.remove('activo', 'hecho');
    if (i < n) el.classList.add('hecho');
    if (i === n) el.classList.add('activo');
  });
}

function ver(id1, id2, id3) {
  document.getElementById('paso1').style.display = id1 ? '' : 'none';
  document.getElementById('paso2').style.display = id2 ? '' : 'none';
  document.getElementById('paso3').style.display = id3 ? '' : 'none';
}

function irPaso1() {
  limpiarError();
  marcarPasos(1);
  ver(true, false, false);
}

function irPaso2() {
  limpiarError();
  var email = (document.getElementById('f_email').value || '').trim(),
    dni = (document.getElementById('f_dni').value || '').trim(),
    ape = (document.getElementById('f_apellidos').value || '').trim(),
    nom = (document.getElementById('f_nombres').value || '').trim();
  if (email === '' || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
    mostrarError('Ingresa un correo electrónico válido.');
    return;
  }
  if (!/^([0-9]{8}|[0-9]{11})$/.test(dni)) {
    mostrarError('El DNI debe tener 8 dígitos o el RUC 11 dígitos.');
    return;
  }
  if (ape === '') {
    mostrarError('Ingresa tus apellidos.');
    return;
  }
  if (nom === '') {
    mostrarError('Ingresa tus nombres.');
    return;
  }
  marcarPasos(2);
  ver(false, true, false);
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
}

function enviarTramite() {
  limpiarError();
  var tipodoc = document.getElementById('f_tipodoc').value,
    tramite = document.getElementById('f_tramite').value,
    numero = (document.getElementById('f_numero').value || '').trim(),
    folios = document.getElementById('f_folios').value,
    asunto = (document.getElementById('f_asunto').value || '').trim(),
    fichero = document.getElementById('f_fichero');
  if (tipodoc === '') {
    mostrarError('Selecciona el tipo de documento.');
    return;
  }
  if (tramite === '') {
    mostrarError('Selecciona el tipo de trámite.');
    return;
  }
  if (numero === '') {
    mostrarError('Ingresa el número del documento (o S/N).');
    return;
  }
  if (folios === '' || parseInt(folios, 10) < 1) {
    mostrarError('Ingresa la cantidad de folios (mínimo 1).');
    return;
  }
  if (asunto === '') {
    mostrarError('Describe el asunto de tu trámite.');
    return;
  }
  if (fichero.files && fichero.files[0] && fichero.files[0].size > 15 * 1024 * 1024) {
    mostrarError('El archivo no debe superar los 15 MB.');
    return;
  }

  var btn = document.getElementById('btnEnviar');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> Enviando...';
  var email = (document.getElementById('f_email').value || '').trim();

  $.ajax({
      dataType: 'json',
      url: URL_MPV,
      type: 'post',
      data: {
        gettemit: '-',
        email: email
      }
    })
    .always(function(r) {
      var idremitente = null;
      if (r && r.length > 0 && r[0] && r[0][0]) {
        idremitente = r[0][0];
      }
      presentar(idremitente);
    });

  function presentar(idremitente) {
    var fd = new FormData();
    fd.append('Insertar', '-');
    fd.append('iddocumento', tipodoc);
    fd.append('idtipo_tramite', tramite);
    fd.append('numero_docu', numero);
    fd.append('asunto', asunto);
    fd.append('folios', folios);
    fd.append('email', email);
    fd.append('d_identidad', (document.getElementById('f_dni').value || '').trim());
    fd.append('apellidos', (document.getElementById('f_apellidos').value || '').trim());
    fd.append('nombres', (document.getElementById('f_nombres').value || '').trim());
    fd.append('direccion', document.getElementById('f_direccion').value);
    fd.append('telefono', document.getElementById('f_telefono').value);
    if (idremitente !== null) fd.append('idremitente', idremitente);
    if (fichero.files && fichero.files[0]) fd.append('fichero', fichero.files[0]);

    $.ajax({
        url: URL_MPV,
        type: 'post',
        data: fd,
        processData: false,
        contentType: false
      })
      .done(function(resp) {
        restaurarBoton();
        var texto = String(resp || '');
        if (texto.indexOf('ERROR_') !== -1) {
          mostrarError(texto.replace(/^ERROR_[A-Z]+:\s*/, ''));
          return;
        }
        var m = texto.match(/\d{4,}/);
        if (!m || texto.indexOf('no se registro') !== -1) {
          mostrarError('No se pudo registrar tu documento. Inténtalo nuevamente o acércate a mesa de partes.');
          return;
        }
        var exp = m[0];
        document.getElementById('expNum').textContent = exp;
        document.getElementById('lnkSeguimiento').href = 'consulta_publico.php?num_exp=' + encodeURIComponent(exp);
        marcarPasos(3);
        document.getElementById('ind3').classList.add('hecho');
        ver(false, false, true);
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      })
      .fail(function() {
        restaurarBoton();
        mostrarError('Ocurrió un problema de conexión. Inténtalo nuevamente.');
      });
  }

  function restaurarBoton() {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-paper-plane"></i> Presentar documento';
  }
}

function copiarExp() {
  var t = document.getElementById('expNum').textContent;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(t).then(function() {
      alert('Número de expediente copiado: ' + t);
    });
  } else {
    window.prompt('Copia tu número de expediente:', t);
  }
}
