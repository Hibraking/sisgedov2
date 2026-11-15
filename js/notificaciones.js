/* =====================================================================
   SISGEDO — Notificaciones en vivo para TODAS las oficinas
   ---------------------------------------------------------------------
   Se carga con el menú lateral (partes/nav_nuevo.php), así funciona en
   cualquier pantalla interna. Cada 20 s consulta clases/notificaciones.php
   (60 s si la pestaña está oculta; al volver, al instante) y:
     - Cuando llega un documento POR RECEPCIONAR a la oficina (derivado,
       devuelto, registrado en ventanilla o emitido por otra oficina):
       aviso en pantalla, sonido corto, notificación de Windows (si se
       activó) y número en "Recepción" del menú y en la pestaña.
     - En la pantalla de Recepción (pendientes) además actualiza la lista
       sola, sin recargar, y resalta lo nuevo. Si el usuario está
       trabajando (casillas marcadas, una ventana abierta o escribiendo en
       un filtro), espera y actualiza al terminar.
     - Mesa de Partes Virtual (usuarios con acceso): aviso de documento
       nuevo y número del menú. En su propia pantalla lo hace
       js/mpv_en_vivo.js, así que aquí se omite para no repetir.
   No usa jQuery.
   ===================================================================== */
(function () {
  'use strict';
  if (window.__sgdNotif) return;
  window.__sgdNotif = true;

  var guion = document.currentScript;
  var BASE = (guion && guion.getAttribute('data-base')) || '../';
  var URL = BASE + 'clases/notificaciones.php';
  var URL_RECEP = BASE + 'vistas/v_recepcion_proveido.php';
  var URL_MPV = BASE + 'vistas/v_mesa_partes_virtual.php';
  var INTERVALO = 20000, INTERVALO_OCULTA = 60000;

  var ruta = location.pathname;
  var enMpv = /v_mesa_partes_virtual\.php$/.test(ruta);
  var enRecepcion = /v_recepcion_proveido\.php$/.test(ruta);
  var params = new URLSearchParams(location.search);
  var estadoRecep = (params.get('estado') || '').toLowerCase();
  var recepcionPendientes = enRecepcion && ['recepcionado', 'derivado', 'enviado'].indexOf(estadoRecep) === -1;

  var firma = '', marca = null, mpvUltimo = null, timer = null, detenido = false;
  var ultimaRevision = 0, pendienteRefrescar = false, ocupado = false;
  var tituloBase = document.title.replace(/^\(\d+\+?\)\s*/, '');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function corto(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }

  /* ---------- Número en el menú lateral ---------- */
  function ponerNumero(sufijoUrl, n, titulo) {
    var a = document.querySelector('.side a[href$="' + sufijoUrl + '"]');
    if (!a) return;
    var b = a.querySelector('.nav-new');
    if (n > 0) {
      if (!b) { b = document.createElement('span'); b.className = 'nav-new'; a.appendChild(document.createTextNode(' ')); a.appendChild(b); }
      b.textContent = n > 99 ? '99+' : String(n);
      b.title = titulo;
    } else if (b) {
      b.parentNode.removeChild(b);
    }
  }

  /* ---------- Aviso en pantalla ---------- */
  function toast(icono, color, html, alClic) {
    var cont = document.getElementById('sgdToasts');
    if (!cont) {
      cont = document.createElement('div');
      cont.id = 'sgdToasts';
      cont.className = 'sgd-toasts';
      cont.setAttribute('aria-live', 'polite');
      document.body.appendChild(cont);
    }
    var t = document.createElement('div');
    t.className = 'sgd-toast';
    t.style.borderLeftColor = color;
    t.innerHTML = '<span class="ti" style="color:' + color + '"><i class="fa ' + icono + '"></i></span><div class="tx">' + html +
      '</div><button type="button" class="tc" title="Cerrar">&times;</button>';
    var tm = setTimeout(quitar, 15000);
    function quitar() {
      clearTimeout(tm);
      t.classList.add('fuera');
      setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 250);
    }
    t.querySelector('.tc').onclick = function (e) { e.stopPropagation(); quitar(); };
    t.onclick = function () { quitar(); if (alClic) alClic(); };
    cont.appendChild(t);
  }

  /* ---------- Sonido corto (sin archivos) ---------- */
  var audioCtx = null;
  function sonar() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      [784, 1047].forEach(function (f, i) {
        var o = audioCtx.createOscillator(), g = audioCtx.createGain(), t0 = audioCtx.currentTime + i * 0.15;
        o.frequency.value = f;
        o.type = 'sine';
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(0.12, t0 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.14);
        o.connect(g);
        g.connect(audioCtx.destination);
        o.start(t0);
        o.stop(t0 + 0.15);
      });
    } catch (e) { /* el navegador aún no permite sonido */ }
  }

  /* ---------- Notificación de Windows (si el usuario la activó y no está mirando) ---------- */
  function notificarSistema(titulo, cuerpo, etiqueta, url) {
    if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
    try {
      var ico = document.querySelector('link[rel=icon]');
      var n = new Notification(titulo, { body: cuerpo, tag: etiqueta, icon: ico ? ico.href : undefined });
      n.onclick = function () { window.focus(); n.close(); if (url) location.href = url; };
    } catch (e) { }
  }

  /* ---------- Botón para activar las notificaciones de Windows (en el menú) ---------- */
  function botonPermiso() {
    if (!('Notification' in window) || Notification.permission !== 'default') return;
    var side = document.querySelector('.side'), sop = side && side.querySelector('.soporte');
    if (!side || document.getElementById('sgdActivarAvisos')) return;
    var b = document.createElement('button');
    b.type = 'button';
    b.id = 'sgdActivarAvisos';
    b.className = 'sgd-activar-avisos';
    b.innerHTML = '<i class="fa fa-bell"></i><span><b>Activar avisos</b><br>Enterarme aunque el navegador esté minimizado</span>';
    b.onclick = function () {
      Notification.requestPermission().then(function () { if (b.parentNode) b.parentNode.removeChild(b); });
    };
    side.insertBefore(b, sop || null);
  }

  /* ---------- Recepción: actualizar la lista sin recargar ---------- */
  function claveFila(r) { return (r.dataset.tipo || '') + '-' + (r.dataset.codigo || ''); }

  function hayTrabajoEnCurso() {
    if (document.querySelector('.modal.show')) return true;
    if (document.querySelector('#tbody input.chk:checked')) return true;
    // Escribiendo en un filtro (el buscador tiene el foco desde que abre la
    // página, por eso cuenta solo si se tecleó en los últimos 8 segundos)
    var a = document.activeElement;
    return !!(a && a.closest && a.closest('.filters') && /INPUT|SELECT|TEXTAREA/.test(a.tagName) &&
      Date.now() - ultimaTecla < 8000);
  }
  var ultimaTecla = 0;
  document.addEventListener('keydown', function () { ultimaTecla = Date.now(); }, true);

  function refrescarRecepcion() {
    if (!recepcionPendientes || ocupado) return;
    if (hayTrabajoEnCurso()) { pendienteRefrescar = true; return; }
    ocupado = true;
    pendienteRefrescar = false;
    var antes = {};
    document.querySelectorAll('#tbody .row').forEach(function (r) { antes[claveFila(r)] = 1; });
    var sel = document.querySelector('#tbody .row.sel'), selClave = sel ? claveFila(sel) : null;
    var scroll = window.scrollY;
    fetch(location.href, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
      .then(function (html) {
        if (hayTrabajoEnCurso()) { pendienteRefrescar = true; return; }
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (!doc.querySelector('#tbody')) throw new Error('respuesta inesperada');   // p. ej. sesión vencida
        ['.kpis', '#tbody', '.pag'].forEach(function (s) {
          var a = document.querySelector(s), b = doc.querySelector(s);
          if (a && b) a.innerHTML = b.innerHTML;
        });
        var filas = document.querySelectorAll('#tbody .row');
        filas.forEach(function (r) {
          r.classList.remove('sel');
          if (!antes[claveFila(r)]) r.classList.add('row-nueva');
        });
        var mantener = null;
        filas.forEach(function (r) { if (selClave && claveFila(r) === selClave) mantener = r; });
        if (mantener) {
          mantener.classList.add('sel');                       // sigue viendo el mismo documento
        } else if (filas.length) {
          var pa = document.querySelector('.rp .bd'), pb = doc.querySelector('.rp .bd');
          if (pa && pb && !pa.querySelector('#d-doc')) pa.innerHTML = pb.innerHTML;   // el panel estaba vacío
          filas[0].click();                                    // mostrar el primero en el panel
        }
        if (typeof window.actualizarContador === 'function') { try { window.actualizarContador(); } catch (e) { } }
        window.scrollTo(0, scroll);
      })
      .catch(function () { pendienteRefrescar = true; })
      .then(function () { ocupado = false; });
  }

  function aplicarPendiente() {
    setTimeout(function () { if (pendienteRefrescar && !hayTrabajoEnCurso()) refrescarRecepcion(); }, 300);
  }
  document.addEventListener('click', aplicarPendiente);
  document.addEventListener('focusout', aplicarPendiente);
  document.addEventListener('change', aplicarPendiente);

  /* ---------- Procesar la respuesta ---------- */
  function procesar(r) {
    var rec = r.recepcion;
    if (rec) {
      ponerNumero('vistas/v_recepcion_proveido.php', rec.pendientes, 'Documentos por recepcionar en su oficina');
      if (!enMpv) document.title = (rec.pendientes > 0 ? '(' + (rec.pendientes > 99 ? '99+' : rec.pendientes) + ') ' : '') + tituloBase;
      var cambio = firma !== '' && rec.firma !== firma;
      var cambioOficina = firma !== '' && firma.split('|')[0] !== rec.firma.split('|')[0];
      firma = rec.firma;
      if (rec.recientes) {
        var maxLlegada = rec.recientes.reduce(function (m, x) { return x.llegada > m ? x.llegada : m; }, '');
        if (marca === null || cambioOficina) {
          marca = maxLlegada;                                   // primera vez: solo toma la referencia
        } else {
          var nuevos = rec.recientes.filter(function (x) { return x.llegada > marca; });
          if (maxLlegada > marca) marca = maxLlegada;
          if (nuevos.length) avisarRecepcion(nuevos);
        }
      }
      if (cambio && !cambioOficina) refrescarRecepcion();
      else if (pendienteRefrescar) aplicarPendiente();
    }
    var mpv = r.mpv;
    if (mpv) {
      if (!enMpv) ponerNumero('vistas/v_mesa_partes_virtual.php', mpv.total, 'Documentos de Mesa de Partes Virtual por recepcionar o derivar');
      var u = mpv.ultimo;
      if (u) {
        if (mpvUltimo !== null && u.id > mpvUltimo && !enMpv) {
          sonar();
          notificarSistema('Nuevo documento — Mesa de Partes Virtual', 'Exp. ' + u.exp + ' · ' + u.remitente + (u.asunto ? '\n' + u.asunto : ''), 'mpv-' + u.id, URL_MPV);
          toast('fa-globe', '#a855f7', '<strong>Nuevo documento por Mesa de Partes Virtual</strong><br>Exp. ' + esc(u.exp) + ' · ' + esc(u.remitente) +
            (u.asunto ? '<br><span class="sgd-muted">' + esc(corto(u.asunto, 110)) + '</span>' : ''),
            function () { location.href = URL_MPV; });
        }
        mpvUltimo = Math.max(mpvUltimo || 0, u.id);
      }
    }
  }

  function avisarRecepcion(nuevos) {
    sonar();
    var irA = function (clave) {
      return function () {
        if (recepcionPendientes) {
          var f = null;
          document.querySelectorAll('#tbody .row').forEach(function (r) {
            if (clave && (r.dataset.tipo === '3' ? 'D' : r.dataset.tipo === '1' ? 'R' : 'E') + r.dataset.codigo === clave) f = r;
          });
          if (f) { f.click(); f.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
        }
        location.href = URL_RECEP;
      };
    };
    if (nuevos.length > 2) {
      toast('fa-inbox', '#2563eb', '<strong>' + nuevos.length + ' documentos nuevos para recepcionar</strong><br>' +
        '<span class="sgd-muted">Haga clic para verlos en Recepción.</span>', irA(null));
      notificarSistema(nuevos.length + ' documentos nuevos para recepcionar', 'Revíselos en Recepción.', 'recep-varios', URL_RECEP);
      return;
    }
    nuevos.forEach(function (x) {
      var devuelto = /devol/i.test(x.accion);
      var titulo = devuelto ? 'Le devolvieron un documento' : 'Nuevo documento para recepcionar';
      toast(devuelto ? 'fa-reply' : 'fa-inbox', devuelto ? '#ea580c' : '#2563eb',
        '<strong>' + titulo + '</strong><br>' + (x.exp ? 'Exp. ' + esc(x.exp) + ' · ' : '') + esc(corto(x.doc, 70)) +
        (x.origen ? '<br><span class="sgd-muted">De: ' + esc(x.origen) + (x.accion && !devuelto ? ' · ' + esc(x.accion) : '') + '</span>' : '') +
        (x.asunto ? '<br><span class="sgd-muted">' + esc(corto(x.asunto, 110)) + '</span>' : ''),
        irA(x.clave));
      notificarSistema(titulo, (x.exp ? 'Exp. ' + x.exp + ' · ' : '') + x.doc + (x.origen ? '\nDe: ' + x.origen : ''), 'recep-' + x.clave, URL_RECEP);
    });
  }

  /* ---------- Consulta periódica ---------- */
  function revisar() {
    if (detenido) return;
    ultimaRevision = Date.now();
    var url = URL + (firma ? '?firma=' + encodeURIComponent(firma) : '');
    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (resp) {
        if (resp.status === 401 || resp.status === 403) { detenido = true; return null; }   // sesión vencida
        return resp.ok ? resp.json() : null;
      })
      .then(function (r) { if (r && !r.error) procesar(r); })
      .catch(function () { /* sin conexión: se reintenta */ })
      .then(programar);
  }

  function programar() {
    clearTimeout(timer);
    if (detenido) return;
    timer = setTimeout(revisar, document.hidden ? INTERVALO_OCULTA : INTERVALO);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && Date.now() - ultimaRevision > 3000) { clearTimeout(timer); revisar(); }
  });

  botonPermiso();
  revisar();
})();
