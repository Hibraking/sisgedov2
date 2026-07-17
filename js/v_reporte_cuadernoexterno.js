function aplicarRango() {
  var ini = document.getElementById('f_inicio').value,
      fin = document.getElementById('f_fin').value;
  if (!ini || !fin) { alert('Seleccione el rango de fechas.'); return; }
  if (ini > fin) { alert('La fecha "Desde" no puede ser mayor que "Hasta".'); return; }
  window.location = '?desde=' + encodeURIComponent(ini) + '&hasta=' + encodeURIComponent(fin);
}

function generarCuaderno() {
  var ini = document.getElementById('f_inicio').value,
      fin = document.getElementById('f_fin').value;
  if (!ini || !fin) { alert('Seleccione el rango de fechas.'); return; }
  if (ini > fin) { alert('La fecha "Desde" no puede ser mayor que "Hasta".'); return; }
  window.open('../tcpdf/examples/reporte_diario.php?f_inicio=' + encodeURIComponent(ini) + '&f_fin=' + encodeURIComponent(fin), '_blank');
}
