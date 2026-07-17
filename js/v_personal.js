var CSRF = document.body.dataset.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

var URL_PER = '../clases/personal.php';
var perCodigo = null;

function filtrarPer() {
  var q = (document.getElementById('buscador').value || '').trim().toLowerCase(), n = 0;
  document.querySelectorAll('#tbody .row').forEach(function(row) {
    var v = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1;
    row.style.display = v ? '' : 'none';
    if (v) n++;
  });
  document.getElementById('contadorPer').textContent = n + ' registro(s)';
}
document.getElementById('buscador').addEventListener('input', filtrarPer);

$('#tbody').on('click', '.row', function() {
  $('.row').removeClass('sel');
  $(this).addClass('sel');
  var d = this.dataset, S = function(id, v) { var e = document.getElementById(id); if (e) e.textContent = v; };
  S('p-nombre', (d.ape || '') + ', ' + (d.nom || ''));
  S('p-usu', d.usu !== '' ? d.usu : 'SIN USUARIO');
  S('p-ema', d.ema !== '' ? d.ema : '—');
  S('p-tel', d.tel !== '' ? d.tel : '—');
  S('p-dir', d.dir !== '' ? d.dir : '—');
  S('p-ofis', d.ofis !== '' ? d.ofis : 'Ninguna oficina asignada');
});

function editarSeleccionadoPer() {
  var r = document.querySelector('#tbody .row.sel');
  if (r) abrirEditarPer(r.querySelector('.act .ab'));
}

function abrirNuevoPer() {
  perCodigo = null;
  document.getElementById('pe_titulo').textContent = 'Nuevo personal';
  document.getElementById('pe_clave_hint').textContent = '(obligatoria si tendrá usuario)';
  ['pe_apellidos', 'pe_nombres', 'pe_direccion', 'pe_email', 'pe_telefono', 'pe_usuario', 'pe_clave'].forEach(function(id) {
    document.getElementById(id).value = '';
  });
  document.getElementById('modalPer').classList.add('show');
}

function abrirEditarPer(el) {
  var row = el.closest('.row');
  if (!row) return;
  perCodigo = String(row.dataset.id);
  document.getElementById('pe_titulo').textContent = 'Editar personal';
  document.getElementById('pe_clave_hint').textContent = '(déjala vacía para no cambiarla)';
  document.getElementById('pe_apellidos').value = row.dataset.ape || '';
  document.getElementById('pe_nombres').value = row.dataset.nom || '';
  document.getElementById('pe_direccion').value = row.dataset.dir || '';
  document.getElementById('pe_email').value = row.dataset.ema || '';
  document.getElementById('pe_telefono').value = row.dataset.tel || '';
  document.getElementById('pe_usuario').value = row.dataset.usu || '';
  document.getElementById('pe_clave').value = '';
  document.getElementById('modalPer').classList.add('show');
}

function cerrarPer() {
  document.getElementById('modalPer').classList.remove('show');
}

document.getElementById('pe_guardar').addEventListener('click', function() {
  var ape = (document.getElementById('pe_apellidos').value || '').trim(),
      nom = (document.getElementById('pe_nombres').value || '').trim(),
      usu = (document.getElementById('pe_usuario').value || '').trim(),
      cla = document.getElementById('pe_clave').value;
  if (ape === '') { alert('Ingrese los apellidos.'); return; }
  if (nom === '') { alert('Ingrese los nombres.'); return; }
  if (perCodigo === null && usu !== '' && cla === '') {
    alert('Ingrese una contraseña para el nuevo usuario.');
    return;
  }
  var btn = $('#pe_guardar');
  btn.prop('disabled', true);
  $.ajax({
    url: URL_PER, type: 'post',
    data: {
      Codigo: perCodigo === null ? '' : perCodigo,
      Apellidos: ape, Nombres: nom,
      Direccion: document.getElementById('pe_direccion').value,
      Email: (document.getElementById('pe_email').value || '').trim(),
      Telefono: document.getElementById('pe_telefono').value,
      nusu: usu, Clave: cla, _csrf: CSRF
    }
  })
  .done(function(resp) {
    if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
      btn.prop('disabled', false);
      alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ''));
      return;
    }
    location.reload();
  })
  .fail(function(xhr) {
    btn.prop('disabled', false);
    alert('Error al guardar: ' + (xhr.responseText || ''));
  });
});

function eliminarPer(el) {
  var row = el.closest('.row');
  if (!row) return;
  if (!confirm('¿Eliminar a "' + (row.dataset.ape || '') + ', ' + (row.dataset.nom || '') + '"? Si es responsable de una oficina o tiene documentos, la eliminación fallará.')) return;
  $.ajax({
    url: URL_PER, type: 'post',
    data: { Emilinar: '-', Codigo: String(row.dataset.id), _csrf: CSRF }
  })
  .done(function(resp) {
    if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
      alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ''));
      return;
    }
    location.reload();
  })
  .fail(function() {
    alert('No se pudo eliminar (probablemente tiene registros asociados).');
  });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(function(m) { m.classList.remove('show'); });
});
document.querySelectorAll('.modal').forEach(function(m) {
  m.addEventListener('mousedown', function(e) { if (e.target === m) m.classList.remove('show'); });
});
