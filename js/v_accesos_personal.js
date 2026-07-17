var CSRF = document.body.dataset.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

var URL_ACC = '../clases/accesos_personal.php';
var USUARIO = document.body.dataset.usuario || "0";

function cambiarUsuario() {
  var u = document.getElementById('selUsuario').value;
  window.location = u === '0' ? 'v_accesos_personal.php' : ('?u=' + encodeURIComponent(u));
}

document.getElementById('buscador').addEventListener('input', function() {
  var q = this.value.trim().toLowerCase(), n = 0;
  document.querySelectorAll('#tbody .row').forEach(function(row) {
    var v = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1;
    row.style.display = v ? '' : 'none';
    if (v) n++;
  });
  document.getElementById('contadorAcc').textContent = n + ' módulo(s)';
});

function conceder(el) {
  var row = el.closest('.row');
  if (!row || USUARIO === '0') return;
  $(el).prop('disabled', true);
  $.ajax({
    url: URL_ACC, type: 'post',
    data: { otorgar: '-', Idmenu: String(row.dataset.idmenu), Idusuario: USUARIO, _csrf: CSRF }
  })
  .done(function(resp) {
    if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
      $(el).prop('disabled', false);
      alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ''));
      return;
    }
    location.reload();
  })
  .fail(function(xhr) {
    $(el).prop('disabled', false);
    alert('Error al conceder: ' + (xhr.responseText || ''));
  });
}

function revocar(el) {
  var row = el.closest('.row');
  if (!row) return;
  if (!confirm('¿Revocar el acceso a "' + (row.dataset.nombre || '') + '"?')) return;
  $(el).prop('disabled', true);
  $.ajax({
    url: URL_ACC, type: 'post',
    data: { Emilinar: '-', Codigo: String(row.dataset.idacceso), _csrf: CSRF }
  })
  .done(function(resp) {
    if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
      $(el).prop('disabled', false);
      alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ''));
      return;
    }
    location.reload();
  })
  .fail(function(xhr) {
    $(el).prop('disabled', false);
    alert('Error al revocar: ' + (xhr.responseText || ''));
  });
}
