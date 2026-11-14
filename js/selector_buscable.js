/* =====================================================================
   SISGEDO — Selector con buscador (reemplaza visualmente a un <select>)
   ---------------------------------------------------------------------
   Uso:  SelectorBuscable.crear(document.getElementById('f_tipodoc'));
   - El <select> original queda oculto y SIEMPRE sincronizado (value +
     evento "change"), así el resto del código no cambia.
   - Busca sin importar mayúsculas ni tildes, por palabras en cualquier
     orden, y tolera errores de tipeo (una letra de más, de menos,
     cambiada o dos letras invertidas por palabra).
   - Teclado: ↑ ↓ para moverse, Enter para elegir, Esc para cerrar.
   ===================================================================== */
(function (global) {
  'use strict';

  function normalizar(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9ñ ]+/g, ' ').replace(/\s+/g, ' ').trim();
  }

  /* Distancia de Damerau-Levenshtein (con tope para cortar rápido). */
  function distancia(a, b, tope) {
    if (Math.abs(a.length - b.length) > tope) return tope + 1;
    var d = [], i, j;
    for (i = 0; i <= a.length; i++) { d[i] = [i]; }
    for (j = 0; j <= b.length; j++) { d[0][j] = j; }
    for (i = 1; i <= a.length; i++) {
      var minFila = tope + 1;
      for (j = 1; j <= b.length; j++) {
        var c = a[i - 1] === b[j - 1] ? 0 : 1;
        d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + c);
        if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
          d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
        }
        if (d[i][j] < minFila) minFila = d[i][j];
      }
      if (minFila > tope) return tope + 1;
    }
    return d[a.length][b.length];
  }

  /* Puntaje de una palabra buscada contra las palabras de la opción (mayor = mejor; 0 = no coincide). */
  function puntajePalabra(q, palabras, texto) {
    var mejor = 0;
    for (var k = 0; k < palabras.length; k++) {
      var w = palabras[k];
      if (w === q) return 100;
      if (w.indexOf(q) === 0) { mejor = Math.max(mejor, 80); continue; }
      if (q.length >= 3 && w.indexOf(q) > 0) { mejor = Math.max(mejor, 55); continue; }
      if (q.length >= 3) {
        var tope = q.length <= 4 ? 1 : 2;
        // contra la palabra completa y contra su comienzo (palabra aún incompleta)
        var dc = distancia(q, w, tope);
        var dp = w.length > q.length ? distancia(q, w.substr(0, q.length), tope) : tope + 1;
        var dd = Math.min(dc, dp);
        if (dd <= tope) mejor = Math.max(mejor, dd === 1 ? 40 : 25);
      }
    }
    if (!mejor && q.length >= 2 && texto.indexOf(q) !== -1) mejor = 30;
    return mejor;
  }

  function puntaje(consulta, op) {
    if (!consulta) return 1;
    var qs = consulta.split(' '), total = 0;
    for (var i = 0; i < qs.length; i++) {
      var p = puntajePalabra(qs[i], op.palabras, op.norm);
      if (!p) return 0;                       // todas las palabras deben coincidir
      total += p;
    }
    if (op.norm.indexOf(consulta) === 0) total += 60;          // empieza igual
    else if (op.norm.indexOf(consulta) !== -1) total += 30;    // contiene la frase
    return total - op.norm.length * 0.05;                     // a igualdad, el más corto
  }

  function escapar(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* Resalta en negrita las partes del texto que coinciden con las palabras buscadas. */
  function resaltar(texto, consulta) {
    if (!consulta) return escapar(texto);
    var norm = texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    if (norm.length !== texto.length) return escapar(texto);
    var marcas = new Array(texto.length);
    consulta.split(' ').forEach(function (q) {
      var p = norm.indexOf(q);
      while (q && p !== -1) { for (var i = p; i < p + q.length; i++) marcas[i] = 1; p = norm.indexOf(q, p + q.length); }
    });
    var out = '', dentro = false;
    for (var i = 0; i < texto.length; i++) {
      if (marcas[i] && !dentro) { out += '<mark>'; dentro = true; }
      if (!marcas[i] && dentro) { out += '</mark>'; dentro = false; }
      out += escapar(texto[i]);
    }
    return out + (dentro ? '</mark>' : '');
  }

  var contador = 0;

  function crear(select, opciones) {
    if (!select || select.getAttribute('data-buscable')) return null;
    opciones = opciones || {};
    select.setAttribute('data-buscable', '1');
    var id = 'sb' + (++contador);
    var vacio = '';
    var items = [];
    Array.prototype.forEach.call(select.options, function (o) {
      if (o.value === '') { vacio = o.textContent; return; }
      var n = normalizar(o.textContent);
      items.push({ valor: o.value, texto: o.textContent.trim(), norm: n, palabras: n.split(' ') });
    });

    var caja = document.createElement('div');
    caja.className = 'sb';
    caja.innerHTML =
      '<button type="button" class="sb-campo" aria-haspopup="listbox" aria-expanded="false">' +
      '<span class="sb-valor sb-ph"></span><i class="fa fa-angle-down sb-flecha"></i></button>' +
      '<div class="sb-panel" hidden>' +
      '<div class="sb-buscar"><i class="fa fa-search"></i>' +
      '<input type="text" autocomplete="off" spellcheck="false" role="combobox" aria-autocomplete="list" ' +
      'aria-controls="' + id + '" placeholder="' + escapar(opciones.placeholder || 'Escribe para buscar...') + '" /></div>' +
      '<ul class="sb-lista" role="listbox" id="' + id + '"></ul>' +
      '<div class="sb-pie"><span class="sb-cuenta"></span>' +
      '<span class="sb-teclas"><kbd>↑</kbd><kbd>↓</kbd> moverse <kbd>Enter</kbd> elegir <kbd>Esc</kbd> cerrar</span></div></div>';
    select.parentNode.insertBefore(caja, select.nextSibling);
    select.classList.add('sb-oculto');
    select.tabIndex = -1;

    var campo = caja.querySelector('.sb-campo'), valor = caja.querySelector('.sb-valor'),
      panel = caja.querySelector('.sb-panel'), input = caja.querySelector('input'),
      lista = caja.querySelector('.sb-lista'), cuenta = caja.querySelector('.sb-cuenta');
    var visibles = [], activo = -1;

    var lbl = select.id && document.querySelector('label[for="' + select.id + '"]');
    if (!lbl && select.parentNode.querySelector('label')) lbl = select.parentNode.querySelector('label');
    if (lbl) { campo.setAttribute('aria-label', lbl.textContent.replace('*', '').trim()); }

    function pintarValor() {
      var it = null;
      for (var i = 0; i < items.length; i++) if (items[i].valor === select.value) { it = items[i]; break; }
      valor.textContent = it ? it.texto : (vacio || 'Seleccione...');
      valor.classList.toggle('sb-ph', !it);
    }

    function filtrar() {
      var q = normalizar(input.value);
      visibles = items.map(function (it) { return { it: it, p: puntaje(q, it) }; })
        .filter(function (x) { return x.p > 0; });
      if (q) visibles.sort(function (a, b) { return b.p - a.p; });
      visibles = visibles.map(function (x) { return x.it; });
      cuenta.textContent = q ? (visibles.length === 1 ? '1 resultado' : visibles.length + ' resultados')
        : items.length + ' opciones';
      if (!visibles.length) {
        lista.innerHTML = '<li class="sb-nada"><i class="fa fa-info-circle"></i> No se encontró “' + escapar(input.value) + '”</li>';
        activo = -1;
        return;
      }
      var max = Math.min(visibles.length, 300), html = '';
      for (var i = 0; i < max; i++) {
        var it = visibles[i];
        html += '<li role="option" data-i="' + i + '" class="sb-op' + (it.valor === select.value ? ' sb-sel' : '') + '">' +
          resaltar(it.texto, q) + (it.valor === select.value ? '<i class="fa fa-check"></i>' : '') + '</li>';
      }
      lista.innerHTML = html;
      var iSel = -1;
      if (!q) for (var j = 0; j < max; j++) if (visibles[j].valor === select.value) { iSel = j; break; }
      marcar(q ? 0 : (iSel >= 0 ? iSel : 0), true);
    }

    function marcar(i, centrar) {
      var lis = lista.querySelectorAll('.sb-op');
      if (!lis.length) { activo = -1; return; }
      if (i < 0) i = 0;
      if (i >= lis.length) i = lis.length - 1;
      if (lis[activo]) lis[activo].classList.remove('sb-act');
      activo = i;
      lis[i].classList.add('sb-act');
      input.setAttribute('aria-activedescendant', '');
      var top = lis[i].offsetTop, h = lis[i].offsetHeight;
      if (centrar) lista.scrollTop = top - lista.clientHeight / 2 + h / 2;
      else if (top < lista.scrollTop) lista.scrollTop = top;
      else if (top + h > lista.scrollTop + lista.clientHeight) lista.scrollTop = top + h - lista.clientHeight;
    }

    function abrir() {
      if (!panel.hidden) return;
      cerrarOtros(caja);
      panel.hidden = false;
      caja.classList.add('sb-abierto');
      ubicar();
      campo.setAttribute('aria-expanded', 'true');
      input.value = '';
      filtrar();
      input.focus();
    }

    /* Ancho: al menos 340 px (o el del campo) sin salirse de la pantalla.
       Alto: el que quepa debajo del campo; si arriba hay más espacio, abre hacia arriba. */
    function ubicar() {
      var r = campo.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = window.innerHeight;
      var ancho = Math.min(Math.max(r.width, 340), vw - 24);
      panel.style.width = ancho + 'px';
      var izq = 0;
      if (r.left + ancho > vw - 12) izq = (vw - 12) - (r.left + ancho);   // correr a la izquierda
      if (r.left + izq < 12) izq = 12 - r.left;
      panel.style.left = izq + 'px';
      var abajo = vh - r.bottom - 16, arriba = r.top - 16;
      var haciaArriba = abajo < 260 && arriba > abajo;
      caja.classList.toggle('sb-arriba', haciaArriba);
      var disponible = (haciaArriba ? arriba : abajo) - 100;           // buscador + pie
      lista.style.maxHeight = Math.max(150, Math.min(300, disponible)) + 'px';
    }

    function cerrar(devolverFoco) {
      if (panel.hidden) return;
      panel.hidden = true;
      caja.classList.remove('sb-abierto', 'sb-arriba');
      campo.setAttribute('aria-expanded', 'false');
      if (devolverFoco) campo.focus();
    }

    function elegir(it) {
      if (it && select.value !== it.valor) {
        select.value = it.valor;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      pintarValor();
      cerrar(true);
    }

    campo.addEventListener('click', function () { panel.hidden ? abrir() : cerrar(true); });
    campo.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(); }
      else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) { abrir(); input.value = e.key; filtrar(); e.preventDefault(); }
    });
    input.addEventListener('input', filtrar);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); marcar(activo + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(activo - 1); }
      else if (e.key === 'PageDown') { e.preventDefault(); marcar(activo + 8); }
      else if (e.key === 'PageUp') { e.preventDefault(); marcar(activo - 8); }
      else if (e.key === 'Enter') { e.preventDefault(); if (activo >= 0) elegir(visibles[activo]); }
      else if (e.key === 'Escape') { e.preventDefault(); cerrar(true); }
      else if (e.key === 'Tab') { if (activo >= 0 && input.value) elegir(visibles[activo]); else cerrar(false); }
    });
    lista.addEventListener('mousedown', function (e) { e.preventDefault(); });
    lista.addEventListener('click', function (e) {
      var li = e.target.closest('.sb-op');
      if (li) elegir(visibles[+li.getAttribute('data-i')]);
    });
    lista.addEventListener('mousemove', function (e) {
      var li = e.target.closest('.sb-op');
      if (li && +li.getAttribute('data-i') !== activo) marcar(+li.getAttribute('data-i'));
    });
    window.addEventListener('resize', function () { if (!panel.hidden) ubicar(); });
    select.addEventListener('change', pintarValor);       // si otro código cambia el valor
    pintarValor();

    caja._cerrar = cerrar;
    return { abrir: abrir, cerrar: cerrar, refrescar: pintarValor };
  }

  function cerrarOtros(excepto) {
    Array.prototype.forEach.call(document.querySelectorAll('.sb.sb-abierto'), function (c) {
      if (c !== excepto && c._cerrar) c._cerrar(false);
    });
  }
  document.addEventListener('mousedown', function (e) {
    if (!e.target.closest || !e.target.closest('.sb')) cerrarOtros(null);
  });

  global.SelectorBuscable = { crear: crear, _puntaje: puntaje, _normalizar: normalizar };
})(window);
