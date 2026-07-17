var CSRF = document.body.dataset.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

var URL_RES = '../clases/responsables.php';
var resCodigo = null;

function filtrarRes() {
  var q = (document.getElementById('buscador').value || '').trim().toLowerCase(), n = 0;
  document.querySelectorAll('#tbody .row').forEach(function(row) {
    var v = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1;
    row.style.display = v ? '' : 'none';
    if (v) n++;
  });
  document.getElementById('contadorRes').textContent = n + ' registro(s)';
}
document.getElementById('buscador').addEventListener('input', filtrarRes);

$('#tbody').on('click', '.row', function() {
  $('.row').removeClass('sel');
  $(this).addClass('sel');
  var d = this.dataset, S = function(id, v) { var e = document.getElementById(id); if (e) e.textContent = v; };
  S('p-per', d.per || '—');
  S('p-ofi', d.ofi || '—');
  S('p-est', d.est === '1' ? 'Activo (recepciona y deriva)' : 'Inactivo');
  S('p-est-badge', d.est === '1' ? 'ACTIVO' : 'INACTIVO');
});

function editarSeleccionadoRes() {
  var r = document.querySelector('#tbody .row.sel');
  if (r) abrirEditarRes(r.querySelector('.act .ab'));
}

function abrirNuevoRes() {
  resCodigo = null;
  document.getElementById('re_titulo').textContent = 'Asignar responsable';
  document.getElementById('re_personal').value = '';
  document.getElementById('re_oficina').value = '';
  document.getElementById('re_estado').checked = true;
  document.getElementById('modalRes').classList.add('show');
}

function abrirEditarRes(el) {
  var row = el.closest('.row');
  if (!row) return;
  resCodigo = String(row.dataset.id);
  document.getElementById('re_titulo').textContent = 'Editar responsable';
  document.getElementById('re_personal').value = String(row.dataset.idper || '');
  document.getElementById('re_oficina').value = String(row.dataset.idofi || '');
  document.getElementById('re_estado').checked = (row.dataset.est === '1');
  document.getElementById('modalRes').classList.add('show');
}

function cerrarRes() {
  document.getElementById('modalRes').classList.remove('show');
}

document.getElementById('re_guardar').addEventListener('click', function() {
  var per = document.getElementById('re_personal').value,
      ofi = document.getElementById('re_oficina').value;
  if (per === '') { alert('Seleccione a la persona.'); return; }
  if (ofi === '') { alert('Seleccione la oficina.'); return; }
  var datos = { Codigo: resCodigo === null ? '' : resCodigo, Personal: per, Oficina: ofi, _csrf: CSRF };
  if (document.getElementById('re_estado').checked) { datos.Estado = 'on'; }
  var btn = $('#re_guardar');
  btn.prop('disabled', true);
  $.ajax({ url: URL_RES, type: 'post', data: datos })
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

function eliminarRes(el) {
  var row = el.closest('.row');
  if (!row) return;
  if (!confirm('¿Quitar a "' + (row.dataset.per || '') + '" como responsable de "' + (row.dataset.ofi || '') + '"? Si tiene movimientos registrados, la eliminación fallará; en ese caso márcalo como inactivo.')) return;
  $.ajax({
    url: URL_RES, type: 'post',
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
    alert('No se pudo eliminar (tiene movimientos asociados); márcalo como inactivo.');
  });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(function(m) { m.classList.remove('show'); });
});
document.querySelectorAll('.modal').forEach(function(m) {
  m.addEventListener('mousedown', function(e) { if (e.target === m) m.classList.remove('show'); });
});
