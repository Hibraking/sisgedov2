var CSRF = document.body.dataset.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

var URL_REM = '../clases/remitentes.php';
var pagina = 0, porPagina = 25, totalFiltrado = 0,
    remCodigo = null, drawSeq = 1, timerBusqueda = null;

function escaparTexto(t) {
  var d = document.createElement('div');
  d.textContent = (t == null ? '' : String(t));
  return d.innerHTML;
}

function cargarPagina(p) {
  pagina = Math.max(0, p);
  porPagina = parseInt(document.getElementById('per_page').value, 10) || 25;
  var q = (document.getElementById('buscador').value || '').trim();
  $.ajax({
    dataType: 'json', url: URL_REM, type: 'post',
    data: {
      draw: drawSeq++, start: pagina * porPagina, length: porPagina,
      'search[value]': q, 'order[0][column]': 2, 'order[0][dir]': 'asc'
    }
  })
  .done(function(r) {
    totalFiltrado = r.recordsFiltered || 0;
    var tb = document.getElementById('tbody');
    tb.innerHTML = '';
    var data = r.data || [];
    if (data.length === 0) {
      tb.innerHTML = '<div style="padding:56px;text-align:center;color:#94a3b8;font-size:13px">No se encontraron remitentes.</div>';
    }
    data.forEach(function(f) {
      var row = document.createElement('div');
      row.className = 'row rowg';
      row.dataset.id = String(f[1]);
      row.dataset.ape = f[2];
      row.dataset.nom = f[3];
      row.dataset.dir = f[4];
      row.dataset.ema = f[5];
      row.dataset.tel = f[6];
      row.innerHTML =
        '<div class="dcell"><span class="cell-txt" style="font-weight:600;color:#1e293b">' + f[2] + '</span></div>' +
        '<div class="dcell"><span class="cell-txt">' + f[3] + '</span></div>' +
        '<div class="dcell"><span class="cell-txt">' + (f[4] || '—') + '</span></div>' +
        '<div class="dcell"><span class="cell-txt">' + (f[5] || '—') + '</span></div>' +
        '<div class="dcell"><span class="cell-txt">' + (f[6] || '—') + '</span></div>' +
        '<div class="act">' +
          '<button class="ab ab-g" title="Editar" onclick="event.stopPropagation();abrirEditarRem(this)"><i class="fa fa-pencil"></i></button>' +
          '<a class="ab ab-b" title="Ver sus documentos en mesa de partes" target="_blank" onclick="event.stopPropagation()" href="o_reg_documentos.php?q=' +
            encodeURIComponent((function(html) { var x = document.createElement('div'); x.innerHTML = html || ''; return x.textContent; })(f[2])) +
            '&desde=' + (new Date().getFullYear()) + '-01-01"><i class="fa fa-file-text-o"></i></a>' +
          '<button class="ab ab-r" title="Eliminar" onclick="event.stopPropagation();eliminarRem(this)"><i class="fa fa-trash-o"></i></button>' +
        '</div>';
      tb.appendChild(row);
    });
    var desde = totalFiltrado === 0 ? 0 : pagina * porPagina + 1;
    var hasta = Math.min((pagina + 1) * porPagina, totalFiltrado);
    document.getElementById('pagInfo').textContent = 'Mostrando ' + desde + ' a ' + hasta + ' de ' + totalFiltrado.toLocaleString() + ' registros';
    document.getElementById('contadorRem').textContent = totalFiltrado.toLocaleString() + ' registro(s)';
    document.getElementById('pgActual').textContent = String(pagina + 1);
  })
  .fail(function() {
    document.getElementById('tbody').innerHTML = '<div style="padding:56px;text-align:center;color:#ef4444;font-size:13px">No se pudo cargar la lista.</div>';
  });
}

function ultimaPagina() {
  return Math.max(0, Math.ceil(totalFiltrado / porPagina) - 1);
}

document.getElementById('pgPrimera').addEventListener('click', function() { cargarPagina(0); });
document.getElementById('pgAnterior').addEventListener('click', function() { cargarPagina(pagina - 1); });
document.getElementById('pgSiguiente').addEventListener('click', function() { if (pagina < ultimaPagina()) cargarPagina(pagina + 1); });
document.getElementById('pgUltima').addEventListener('click', function() { cargarPagina(ultimaPagina()); });
document.getElementById('per_page').addEventListener('change', function() { cargarPagina(0); });
document.getElementById('buscador').addEventListener('input', function() {
  clearTimeout(timerBusqueda);
  timerBusqueda = setTimeout(function() { cargarPagina(0); }, 350);
});

cargarPagina(0);

$('#tbody').on('click', '.row', function() {
  $('.row').removeClass('sel');
  $(this).addClass('sel');
  var d = this.dataset, dec = function(html) { var x = document.createElement('div'); x.innerHTML = (html || ''); return x.textContent; };
  var S = function(id, v) { var e = document.getElementById(id); if (e) e.textContent = (v !== '' ? v : '—'); };
  S('p-nombre', dec(d.ape) + ', ' + dec(d.nom));
  S('p-id', 'ID ' + (d.id || ''));
  S('p-dir', dec(d.dir));
  S('p-ema', dec(d.ema));
  S('p-tel', dec(d.tel));
});

function editarSeleccionadoRem() {
  var r = document.querySelector('#tbody .row.sel');
  if (!r) { alert('Selecciona un remitente de la lista.'); return; }
  abrirEditarRem(r.querySelector('.act .ab'));
}

function abrirNuevoRem() {
  remCodigo = null;
  document.getElementById('rm_titulo').textContent = 'Nuevo remitente';
  ['rm_apellidos', 'rm_nombres', 'rm_direccion', 'rm_email', 'rm_telefono'].forEach(function(id) {
    document.getElementById(id).value = '';
  });
  document.getElementById('modalRem').classList.add('show');
}

function abrirEditarRem(el) {
  var row = el.closest('.row');
  if (!row) return;
  remCodigo = String(row.dataset.id);
  document.getElementById('rm_titulo').textContent = 'Editar remitente';
  var dec = function(html) { var d = document.createElement('div'); d.innerHTML = html; return d.textContent; };
  document.getElementById('rm_apellidos').value = dec(row.dataset.ape || '');
  document.getElementById('rm_nombres').value = dec(row.dataset.nom || '');
  document.getElementById('rm_direccion').value = dec(row.dataset.dir || '');
  document.getElementById('rm_email').value = dec(row.dataset.ema || '');
  document.getElementById('rm_telefono').value = dec(row.dataset.tel || '');
  document.getElementById('modalRem').classList.add('show');
}

function cerrarRem() {
  document.getElementById('modalRem').classList.remove('show');
}

document.getElementById('rm_guardar').addEventListener('click', function() {
  var ape = (document.getElementById('rm_apellidos').value || '').trim(),
      nom = (document.getElementById('rm_nombres').value || '').trim();
  if (ape === '') { alert('Ingrese los apellidos.'); return; }
  if (nom === '') { alert('Ingrese los nombres.'); return; }
  var btn = $('#rm_guardar');
  btn.prop('disabled', true);
  $.ajax({
    url: URL_REM, type: 'post',
    data: {
      Codigo: remCodigo === null ? '' : remCodigo,
      Apellidos: ape, Nombres: nom,
      Direccion: document.getElementById('rm_direccion').value,
      Email: (document.getElementById('rm_email').value || '').trim(),
      Telefono: document.getElementById('rm_telefono').value,
      _csrf: CSRF
    }
  })
  .done(function(resp) {
    btn.prop('disabled', false);
    if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
      alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ''));
      return;
    }
    cerrarRem();
    cargarPagina(pagina);
  })
  .fail(function(xhr) {
    btn.prop('disabled', false);
    alert('Error al guardar: ' + (xhr.responseText || ''));
  });
});

function eliminarRem(el) {
  var row = el.closest('.row');
  if (!row) return;
  var dec = function(html) { var d = document.createElement('div'); d.innerHTML = html; return d.textContent; };
  if (!confirm('¿Eliminar al remitente "' + dec(row.dataset.ape || '') + ', ' + dec(row.dataset.nom || '') + '"? Si tiene documentos registrados, la eliminación fallará.')) return;
  $.ajax({
    url: URL_REM, type: 'post',
    data: { Emilinar: '-', Codigo: String(row.dataset.id), _csrf: CSRF }
  })
  .done(function(resp) {
    if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
      alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ''));
      return;
    }
    cargarPagina(pagina);
  })
  .fail(function() {
    alert('No se pudo eliminar (probablemente tiene documentos asociados).');
  });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(function(m) { m.classList.remove('show'); });
});
document.querySelectorAll('.modal').forEach(function(m) {
  m.addEventListener('mousedown', function(e) { if (e.target === m) m.classList.remove('show'); });
});
