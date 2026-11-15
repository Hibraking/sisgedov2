/* =====================================================================
   SISGEDO — Mesa de Partes Virtual: BANDEJA EN VIVO (como el correo)
   ---------------------------------------------------------------------
   Cada 15 s pregunta al servidor si hubo cambios (consulta muy liviana).
   Si los hay, actualiza contadores, pestañas y lista SIN recargar la
   página, resalta lo nuevo y avisa con un mensaje, un sonido corto, el
   número de pendientes en la pestaña del navegador y (si se activan)
   notificaciones del sistema.
   - Si hay una ventana abierta (Recepcionar, Observar...) o el operador
     está escribiendo en el buscador, espera y actualiza al terminar.
   - Con la pestaña oculta revisa cada 60 s; al volver revisa al instante.
   Requiere js/v_mesa_partes_virtual.js (URL_MPV, CSRF, esc, cargarHistorial).
   ===================================================================== */
(function () {
  'use strict';
  var INTERVALO = 15000, INTERVALO_OCULTA = 60000;
  var firma = null, ultimoId = 0, pendienteRefrescar = false, ocupado = false;
  var timer = null, detenido = false, ultimaRevision = 0;
  var tituloBase = document.title.replace(/^\(\d+\)\s*/, '');
  var chip = document.getElementById('mpvVivo');

  function idsActuales() {
    var s = {};
    document.querySelectorAll('#tbody .row').forEach(function (r) { s[r.dataset.id] = 1; });
    return s;
  }

  function ponerTitulo(n) {
    document.title = (n > 0 ? '(' + n + ') ' : '') + tituloBase;
  }

  function hayInteraccion() {
    if (document.querySelector('.modal.show')) return true;
    var a = document.activeElement;
    return !!(a && (a.id === 'buscador' || a.id === 'desde' || a.id === 'hasta'));
  }

  /* ---- Aviso visual (toast) ---- */
  function toast(html, alClic) {
    var cont = document.getElementById('mpvToasts');
    if (!cont) {
      cont = document.createElement('div');
      cont.id = 'mpvToasts';
      cont.className = 'mpv-toasts';
      document.body.appendChild(cont);
    }
    var t = document.createElement('div');
    t.className = 'mpv-toast';
    t.innerHTML = '<span class="ti"><i class="fa fa-globe"></i></span><div class="tx">' + html +
      '</div><button type="button" class="tc" title="Cerrar">&times;</button>';
    var tm = setTimeout(quitar, 12000);
    function quitar() {
      clearTimeout(tm);
      t.classList.add('fuera');
      setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 250);
    }
    t.querySelector('.tc').onclick = function (e) { e.stopPropagation(); quitar(); };
    t.onclick = function () { if (alClic) alClic(); quitar(); };
    cont.appendChild(t);
  }

  /* ---- Sonido corto (sin archivos): dos tonos suaves ---- */
  var audioCtx = null;
  function sonar() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      [880, 1175].forEach(function (f, i) {
        var o = audioCtx.createOscillator(), g = audioCtx.createGain(), t0 = audioCtx.currentTime + i * 0.16;
        o.frequency.value = f;
        o.type = 'sine';
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(0.12, t0 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.15);
        o.connect(g);
        g.connect(audioCtx.destination);
        o.start(t0);
        o.stop(t0 + 0.16);
      });
    } catch (e) { /* el navegador no permite sonido todavía */ }
  }

  /* ---- Notificación del sistema (Windows/Android) si el operador la activó ---- */
  function notificar(u) {
    if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
    try {
      var ico = document.querySelector('link[rel=icon]');
      var n = new Notification('Nuevo documento — Mesa de Partes Virtual', {
        body: 'Exp. ' + u.exp + ' · ' + u.remitente + (u.asunto ? '\n' + u.asunto : ''),
        icon: ico ? ico.href : undefined,
        tag: 'mpv-' + u.id
      });
      n.onclick = function () { window.focus(); n.close(); };
    } catch (e) { }
  }

  function pintarChip(estado) {
    if (!chip) return;
    chip.className = 'mpv-vivo' + (estado ? ' ' + estado : '');
    var permiso = ('Notification' in window) ? Notification.permission : 'denied';
    chip.title = estado === 'error'
      ? 'No se pudo consultar al servidor; se reintentará.'
      : 'La bandeja se actualiza sola cada 15 segundos.' +
        (permiso === 'default' ? ' Haga clic para activar las notificaciones de Windows.' : '');
  }
  if (chip) {
    chip.addEventListener('click', function () {
      if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(function () { pintarChip('ok'); });
      }
      clearTimeout(timer);
      revisar(true);
    });
  }

  /* ---- Reemplaza las partes de la página con la versión nueva del servidor ---- */
  function refrescar(resaltarNuevos) {
    if (ocupado) return;
    if (hayInteraccion()) { pendienteRefrescar = true; return; }
    ocupado = true;
    pendienteRefrescar = false;
    var antes = idsActuales();
    var sel = document.querySelector('#tbody .row.sel');
    var selId = sel ? sel.dataset.id : null;
    var scroll = window.scrollY;
    fetch(location.href, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
      .then(function (html) {
        if (hayInteraccion()) { pendienteRefrescar = true; return; }
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (!doc.querySelector('#tbody')) throw new Error('respuesta inesperada');   // p. ej. sesión vencida
        ['.kpis', '.mpv-tabs', '#tbody', '.table-card .pag'].forEach(function (s) {
          var a = document.querySelector(s), b = doc.querySelector(s);
          if (a && b) a.innerHTML = b.innerHTML;
        });
        // Número rojo del menú lateral (Mesa de Partes Virtual)
        var enlA = document.querySelector('a[href$="v_mesa_partes_virtual.php"]'),
          enlB = doc.querySelector('a[href$="v_mesa_partes_virtual.php"]');
        if (enlA && enlB) {
          var bA = enlA.querySelector('.nav-new'), bB = enlB.querySelector('.nav-new');
          if (bA && !bB) bA.parentNode.removeChild(bA);
          else if (bB && !bA) enlA.appendChild(bB.cloneNode(true));
          else if (bA && bB) bA.textContent = bB.textContent;
        }
        var filaSel = selId ? document.querySelector('#tbody .row[data-id="' + selId + '"]') : null;
        document.querySelectorAll('#tbody .row.sel').forEach(function (r) { r.classList.remove('sel'); });
        if (filaSel) {
          filaSel.classList.add('sel');                        // sigue viendo el mismo documento
        } else {
          var pa = document.querySelector('.rp .bd'), pb = doc.querySelector('.rp .bd');
          if (pa && pb) pa.innerHTML = pb.innerHTML;           // el que veía ya no está: mostrar el primero
          var primera = document.querySelector('#tbody .row');
          if (primera) {
            primera.classList.add('sel');
            if (typeof cargarHistorial === 'function') cargarHistorial(primera.dataset.id, primera.dataset.presentado);
          }
        }
        if (resaltarNuevos) {
          document.querySelectorAll('#tbody .row').forEach(function (r) {
            if (!antes[r.dataset.id]) r.classList.add('row-nueva');
          });
        }
        window.scrollTo(0, scroll);
      })
      .catch(function () { pendienteRefrescar = true; })
      .then(function () { ocupado = false; });
  }

  function revisar(forzar) {
    if (detenido) return;
    ultimaRevision = Date.now();
    $.ajax({ url: URL_MPV, type: 'post', dataType: 'json', data: { novedades: '-', _csrf: CSRF }, global: false })
      .done(function (r) {
        pintarChip('ok');
        if (!r || !r.firma) return;
        ponerTitulo(r.pendientes);
        var u = r.ultimo;
        if (firma === null) {                                  // primera revisión: solo toma la referencia
          firma = r.firma;
          ultimoId = u ? u.id : 0;
          return;
        }
        var llegoNuevo = !!(u && u.id > ultimoId);
        if (r.firma !== firma || pendienteRefrescar || forzar === true) {
          firma = r.firma;
          refrescar(llegoNuevo);
        }
        if (llegoNuevo) {
          ultimoId = u.id;
          sonar();
          notificar(u);
          toast('<strong>Nuevo documento por Mesa de Partes Virtual</strong><br>Exp. ' + esc(u.exp) + ' · ' + esc(u.remitente) +
            (u.asunto ? '<br><span class="muted">' + esc(u.asunto) + '</span>' : ''),
            function () {
              if (document.body.dataset.bandeja !== 'pendientes') { location.href = 'v_mesa_partes_virtual.php'; return; }
              var f = document.querySelector('#tbody .row[data-id="' + u.id + '"]');
              if (f) { f.click(); f.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
            });
        }
      })
      .fail(function (xhr) {
        pintarChip('error');
        if (xhr && (xhr.status === 401 || xhr.status === 403)) detenido = true;   // sesión vencida: dejar de preguntar
      })
      .always(programar);
  }

  function programar() {
    clearTimeout(timer);
    if (detenido) return;
    timer = setTimeout(revisar, document.hidden ? INTERVALO_OCULTA : INTERVALO);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && Date.now() - ultimaRevision > 3000) { clearTimeout(timer); revisar(); }
  });
  // Al cerrar una ventana (modal) o salir del buscador, aplicar lo que quedó pendiente
  function aplicarPendiente() {
    setTimeout(function () { if (pendienteRefrescar && !hayInteraccion()) refrescar(false); }, 300);
  }
  document.addEventListener('click', aplicarPendiente);
  document.addEventListener('focusout', aplicarPendiente);

  pintarChip();
  revisar();
})();
