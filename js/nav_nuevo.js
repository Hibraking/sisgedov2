function navToggleGrupo(g) {
  var sub = g.nextElementSibling;
  if (!sub || !sub.classList.contains('subm')) return;
  g.classList.toggle('open');
  sub.classList.toggle('cerrado');
}

(function() {
  var side = document.querySelector('.side');
  var foto = side ? side.dataset.foto : '';
  if (!foto) return;
  var av = document.querySelector('.hdr .usr .av');
  if (!av) return;
  av.innerHTML = '';
  var img = document.createElement('img');
  img.src = foto;
  img.alt = '';
  img.style.cssText = 'width:100%;height:100%;border-radius:50%;object-fit:cover;display:block';
  av.appendChild(img);
})();

/* ===== Menú lateral responsive (móvil/tablet) =====
   Inserta el botón hamburguesa en la cabecera y una capa oscura.
   En escritorio el botón queda oculto por CSS: nada cambia. */
(function () {
  var side = document.querySelector('.side');
  var hdr = document.querySelector('.hdr');
  if (!side || !hdr) return;

  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'nav-burger';
  btn.setAttribute('aria-label', 'Abrir menú');
  btn.innerHTML = '<i class="fa fa-bars"></i>';
  var izq = hdr.querySelector('.l') || hdr.firstElementChild;
  if (izq) { izq.insertBefore(btn, izq.firstChild); } else { hdr.insertBefore(btn, hdr.firstChild); }

  var ovl = document.createElement('div');
  ovl.className = 'nav-ovl';
  document.body.appendChild(ovl);

  function cerrarMenu() { document.body.classList.remove('side-open'); }
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    document.body.classList.toggle('side-open');
  });
  ovl.addEventListener('click', cerrarMenu);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) cerrarMenu();
  });
  side.addEventListener('click', function (e) {
    var t = e.target;
    while (t && t !== side) {
      if (t.tagName === 'A') { cerrarMenu(); break; }
      t = t.parentNode;
    }
  });
})();

/* ===== Selects con buscador (listas largas, p. ej. oficinas) =====
   Cualquier <select> con muchas opciones se abre como un panel con
   campo de búsqueda. El <select> original sigue siendo el que guarda
   el valor, así que el resto del código no cambia. */
(function () {
  var panel = null, selActivo = null;

  function cerrar() {
    if (panel && panel.parentNode) panel.parentNode.removeChild(panel);
    panel = null;
    selActivo = null;
  }

  function disparaChange(sel) {
    try {
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (err) {
      var ev = document.createEvent('HTMLEvents');
      ev.initEvent('change', true, false);
      sel.dispatchEvent(ev);
    }
  }

  function abrir(sel) {
    cerrar();
    selActivo = sel;
    var r = sel.getBoundingClientRect();
    panel = document.createElement('div');
    panel.className = 'selb-panel';
    panel.style.left = r.left + 'px';
    panel.style.top = (r.bottom + 4) + 'px';
    panel.style.width = Math.max(r.width, 220) + 'px';
    panel.innerHTML =
      '<div class="selb-buscar"><i class="fa fa-search"></i><input type="text" placeholder="Buscar..." /></div>' +
      '<div class="selb-lista"></div>';
    document.body.appendChild(panel);
    var inp = panel.querySelector('input');
    var lista = panel.querySelector('.selb-lista');

    function pintar(filtro) {
      lista.innerHTML = '';
      var f = (filtro || '').toLowerCase();
      for (var i = 0; i < sel.options.length; i++) {
        var o = sel.options[i];
        var txt = (o.text || '').trim();
        if (txt === '' || txt === '-') continue;
        if (f && txt.toLowerCase().indexOf(f) === -1) continue;
        var it = document.createElement('div');
        it.className = 'selb-item' + (o.value === sel.value ? ' on' : '');
        it.textContent = txt;
        it.setAttribute('data-v', o.value);
        lista.appendChild(it);
      }
      if (!lista.firstChild) lista.innerHTML = '<div class="selb-vacio">Sin coincidencias</div>';
    }
    pintar('');

    inp.addEventListener('input', function () { pintar(inp.value); });
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var p = lista.querySelector('.selb-item');
        if (p) elegir(p);
      }
    });
    function elegir(it) {
      sel.value = it.getAttribute('data-v');
      disparaChange(sel);
      cerrar();
    }
    lista.addEventListener('click', function (e) {
      var it = e.target;
      while (it && it !== lista && (!it.className || it.className.indexOf('selb-item') === -1)) it = it.parentNode;
      if (it && it !== lista) elegir(it);
    });

    /* Si no cabe hacia abajo, se abre hacia arriba */
    var ph = panel.offsetHeight;
    if (r.bottom + ph + 8 > window.innerHeight) {
      panel.style.top = Math.max(8, r.top - ph - 4) + 'px';
    }
    setTimeout(function () { inp.focus(); }, 0);
  }

  function esBuscable(t) {
    if (!t || t.tagName !== 'SELECT' || t.multiple || t.disabled) return false;
    var n = t.options.length;
    var enModal = false, p = t.parentNode;
    while (p && p !== document) { if (p.className && String(p.className).indexOf('modal') !== -1) { enModal = true; break; } p = p.parentNode; }
    return (enModal && n >= 10) || n >= 12;
  }

  document.addEventListener('mousedown', function (e) {
    var t = e.target;
    if (panel && panel.contains(t)) return;
    if (esBuscable(t)) {
      e.preventDefault();
      if (selActivo === t) { cerrar(); } else { abrir(t); }
      return;
    }
    if (panel) cerrar();
  });
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Escape' || e.keyCode === 27) && panel) cerrar();
  });
  window.addEventListener('resize', cerrar);
  document.addEventListener('scroll', function (e) {
    if (panel && !panel.contains(e.target)) cerrar();
  }, true);
})();
