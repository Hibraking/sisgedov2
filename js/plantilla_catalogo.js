var CSRF = document.body.dataset.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });
var CAT_ENDPOINT = document.body.dataset.endpoint || '';
var CAT_CAMPOS = JSON.parse(document.body.dataset.campos || '[]');
var CAT_NCOLS = parseInt(document.body.dataset.ncols || '0', 10);
var CAT_SINGULAR = document.body.dataset.singular || '';
var catCodigo = null;

function filtrarCat() {
  var q = (document.getElementById('buscador').value || '').trim().toLowerCase();
  var visibles = 0;
  document.querySelectorAll('#tbody .row').forEach(function(row) {
    var mostrar = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1;
    row.style.display = mostrar ? '' : 'none';
    if (mostrar) visibles++;
  });
  var c = document.getElementById('contadorCat');
  if (c) c.textContent = visibles + ' registro(s)';
}
document.getElementById('buscador').addEventListener('input', filtrarCat);

function filaSeleccionada() {
  return document.querySelector('#tbody .row.sel');
}

function llenarPanel(row) {
  if (!row) return;
  var S = function(id, v) {
    var e = document.getElementById(id);
    if (e) e.textContent = v;
  };
  S('p-nombre', row.dataset.f1 || '—');
  S('p-id', 'ID ' + (row.dataset.id || ''));
  for (var i = 1; i <= CAT_NCOLS; i++) {
    var v = row.dataset['f' + i];
    S('p-f' + i, (v === undefined || v === '') ? '—' : v);
  }
}
$('#tbody').on('click', '.row', function() {
  $('.row').removeClass('sel');
  $(this).addClass('sel');
  llenarPanel(this);
});

function editarSeleccionado() {
  var r = filaSeleccionada();
  if (r) abrirEditarCat(r.querySelector('.act .ab'));
}

function eliminarSeleccionado() {
  var r = filaSeleccionada();
  if (r) eliminarCat(r.querySelector('.act .ab'));
}

function abrirNuevoCat() {
  catCodigo = null;
  document.getElementById('mc_titulo').textContent = 'Nuevo registro';
  document.getElementById('mc_btn_txt').textContent = 'Guardar';
  CAT_CAMPOS.forEach(function(f, i) {
    document.getElementById('mc_f' + (i + 1)).value = '';
  });
  document.getElementById('modalCat').classList.add('show');
}

function abrirEditarCat(el) {
  var row = el.closest('.row');
  if (!row) return;
  catCodigo = String(row.dataset.id);
  document.getElementById('mc_titulo').textContent = 'Editar registro';
  document.getElementById('mc_btn_txt').textContent = 'Guardar cambios';
  CAT_CAMPOS.forEach(function(f, i) {
    var v = row.dataset['f' + (i + 1)] || '';
    document.getElementById('mc_f' + (i + 1)).value = (v === '—') ? '' : v;
  });
  document.getElementById('modalCat').classList.add('show');
}

function cerrarCat() {
  document.getElementById('modalCat').classList.remove('show');
}

document.getElementById('mc_guardar').addEventListener('click', function() {
  var datos = {
    Codigo: catCodigo === null ? '' : catCodigo,
    _csrf: CSRF
  };
  for (var i = 0; i < CAT_CAMPOS.length; i++) {
    var v = (document.getElementById('mc_f' + (i + 1)).value || '').trim();
    if (CAT_CAMPOS[i].req && v === '') {
      alert('Complete el campo: ' + CAT_CAMPOS[i].label);
      return;
    }
    datos[CAT_CAMPOS[i].post] = v;
  }
  var btn = $('#mc_guardar');
  btn.prop('disabled', true);
  $.ajax({
      url: CAT_ENDPOINT,
      type: 'post',
      data: datos
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

function eliminarCat(el) {
  var row = el.closest('.row');
  if (!row) return;
  var nombre = row.dataset.f1 || '';
  if (!confirm('¿Eliminar ' + CAT_SINGULAR + ' "' + nombre + '"? Esta acción no se puede deshacer.')) return;
  $.ajax({
      url: CAT_ENDPOINT,
      type: 'post',
      data: {
        Emilinar: '-',
        Codigo: String(row.dataset.id),
        _csrf: CSRF
      }
    })
    .done(function(resp) {
      if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
        alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ''));
        return;
      }
      location.reload();
    })
    .fail(function(xhr) {
      alert('Error al eliminar: ' + (xhr.responseText || '') + '\nSi el registro está en uso por documentos, no puede eliminarse.');
    });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(function(m) {
    m.classList.remove('show');
  });
});
document.querySelectorAll('.modal').forEach(function(m) {
  m.addEventListener('mousedown', function(e) {
    if (e.target === m) m.classList.remove('show');
  });
});
