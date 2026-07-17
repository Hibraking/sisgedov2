function aplicar() {
  var p = new URLSearchParams();
  var q = (document.getElementById('buscador').value || '').trim();
  if (q) p.set('q', q);
  var d = document.getElementById('desde').value;
  if (d) p.set('desde', d);
  var h = document.getElementById('hasta').value;
  if (h) p.set('hasta', h);
  var e = document.getElementById('estado').value;
  if (e !== '0') p.set('estado', e);
  var qs = p.toString();
  window.location = qs ? ('?' + qs) : window.location.pathname;
}

document.getElementById('buscador').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') { e.preventDefault(); aplicar(); }
});

['desde', 'hasta', 'estado'].forEach(function(id) {
  document.getElementById(id).addEventListener('change', aplicar);
});

document.querySelectorAll('.dd > .btn').forEach(function(b) {
  b.addEventListener('click', function(e) {
    e.stopPropagation();
    this.parentNode.classList.toggle('open');
  });
});

document.addEventListener('click', function() {
  document.querySelectorAll('.dd.open').forEach(function(x) { x.classList.remove('open'); });
});

document.getElementById('expCsv').addEventListener('click', function(e) {
  e.preventDefault();
  var qs = new URLSearchParams(window.location.search);
  qs.set('export', 'csv');
  window.location = '?' + qs.toString();
});

document.getElementById('expPrint').addEventListener('click', function(e) {
  e.preventDefault();
  window.print();
});
