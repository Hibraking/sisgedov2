function buscarCI() {
  var tipo = document.getElementById('tipoBusq').value,
    v = (document.getElementById('valorBusq').value || '').trim();
  if (v === '') {
    alert('Ingrese el número a consultar.');
    return;
  }
  window.location = tipo === 'interno' ?
    ('consulta_interna.php?codigo_doc=' + encodeURIComponent(v)) :
    ('consulta_interna.php?num_exp=' + encodeURIComponent(v));
}
document.getElementById('valorBusq').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    buscarCI();
  }
});

function consultarSug(tipo, valor) {
  window.location = tipo === 'interno' ?
    ('consulta_interna.php?codigo_doc=' + encodeURIComponent(valor)) :
    ('consulta_interna.php?num_exp=' + encodeURIComponent(valor));
}
