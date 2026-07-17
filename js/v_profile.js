var CSRF = document.body.dataset.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

function abrirEditarPerfil() {
  document.getElementById('modalPerfil').classList.add('show');
}

function cerrarEditarPerfil() {
  document.getElementById('modalPerfil').classList.remove('show');
}

document.getElementById('ep_foto').addEventListener('change', function() {
  var f = this.files && this.files[0];
  if (!f) return;
  if (f.size > 3 * 1024 * 1024) {
    alert('La foto no debe superar los 3 MB.');
    this.value = '';
    return;
  }
  var r = new FileReader();
  r.onload = function(ev) {
    document.getElementById('ep_prev').innerHTML = '<img src="' + ev.target.result + '" alt="" />';
  };
  r.readAsDataURL(f);
});

document.getElementById('ep_guardar').addEventListener('click', function() {
  var nueva = document.getElementById('ep_clave_nueva').value,
      actual = document.getElementById('ep_clave_actual').value;
  if (nueva !== '' && actual === '') {
    alert('Para cambiar la contraseña, ingresa también tu contraseña actual.');
    return;
  }
  var btn = $('#ep_guardar');
  btn.prop('disabled', true);
  var fd = new FormData();
  fd.append('actualizar_perfil', '-');
  fd.append('direccion', document.getElementById('ep_direccion').value);
  fd.append('email', (document.getElementById('ep_email').value || '').trim());
  fd.append('telefono', document.getElementById('ep_telefono').value);
  fd.append('clave_nueva', nueva);
  fd.append('clave_actual', actual);
  fd.append('_csrf', CSRF);
  var f = document.getElementById('ep_foto');
  if (f.files && f.files[0]) fd.append('foto', f.files[0]);
  $.ajax({
    url: '../clases/profile.php', type: 'post',
    data: fd, processData: false, contentType: false
  })
  .done(function(resp) {
    btn.prop('disabled', false);
    if (typeof resp === 'string' && resp.indexOf('ERROR_') !== -1) {
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

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal.show').forEach(function(m) { m.classList.remove('show'); });
});
document.querySelectorAll('.modal').forEach(function(m) {
  m.addEventListener('mousedown', function(e) { if (e.target === m) m.classList.remove('show'); });
});
