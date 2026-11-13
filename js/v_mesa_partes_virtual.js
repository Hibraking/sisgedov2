/* Módulo Mesa de Partes Virtual: recepcionar, observar, derivar documentos virtuales */
var pageConfig = document.body ? document.body.dataset : {};
var CSRF = pageConfig.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });
var URL_MPV = "../clases/mesa_partes_virtual.php";
var filaActual = null;

function esc(s) {
  return $("<div>").text(s == null ? "" : String(s)).html();
}

/**
 * Fila del documento a partir del elemento pulsado. Los botones de cada fila
 * pasan el BOTÓN (this); el panel derecho pasa la fila. Antes se usaba el botón
 * tal cual: la ventana salía vacía ("Exp. —", "no registró correo") y el
 * servidor recibía id vacío ("ya fue atendido por otra persona").
 */
function filaDe(el) {
  if (!el) return null;
  var row = el.closest ? el.closest("#tbody .row") : null;
  return row && row.dataset && row.dataset.id ? row : null;
}

/* ---------- Filtros ---------- */
function irFiltros() {
  var p = new URLSearchParams(window.location.search);
  var q = (document.getElementById("buscador").value || "").trim();
  q ? p.set("q", q) : p.delete("q");
  ["desde", "hasta"].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.value ? p.set(id, el.value) : p.delete(id);
  });
  var ord = document.getElementById("orden");
  if (ord) ord.value === "antiguos" ? p.set("orden", "antiguos") : p.delete("orden");
  var qs = p.toString();
  window.location = qs ? "?" + qs : window.location.pathname;
}
document.getElementById("buscador").addEventListener("keydown", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    irFiltros();
  }
});
["desde", "hasta", "orden"].forEach(function (id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener("change", irFiltros);
});

/* ---------- Historial (panel derecho) ---------- */
var TIPOS = {
  RECEPCIONADO: ["Recepcionado por mesa de partes", "#16a34a"],
  OBSERVADO: ["Observado", "#f97316"],
  SUBSANADO: ["Subsanado por el ciudadano", "#0d9488"],
  NO_PRESENTADO: ["No presentado", "#dc2626"],
  DERIVADO: ["Derivado", "#6d28d9"],
};
function fechaCorta(f) {
  // 'Y-m-d H:i:s' -> 'dd/mm/aaaa hh:mm'
  var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(f || "");
  return m ? m[3] + "/" + m[2] + "/" + m[1] + " " + m[4] + ":" + m[5] : f || "";
}
function cargarHistorial(id, presentado) {
  var box = document.getElementById("d-historial");
  if (!box) return;
  box.innerHTML = '<span class="muted">Cargando…</span>';
  $.post(URL_MPV, { historial: "-", id: id, _csrf: CSRF }, null, "json")
    .done(function (ev) {
      var html =
        '<div class="ev" style="--c:#3b82f6"><div class="tt">Enviado por internet</div><div class="ff">' +
        esc(presentado) +
        "</div></div>";
      (ev || []).forEach(function (e) {
        var t = TIPOS[e[0]] || [e[0], "#94a3b8"];
        var det = "";
        if (e[0] === "OBSERVADO") det = "Motivo: " + esc(e[1]) + (e[3] ? "<br>Plazo: hasta el " + esc(e[3].split("-").reverse().join("/")) : "");
        else if (e[0] === "SUBSANADO") det = (e[1] ? "Nota: " + esc(e[1]) + "<br>" : "") + (e[2] ? '<a target="_blank" href="../docs adjs reg_doc/' + encodeURIComponent(e[2]) + '"><i class="fa fa-paperclip"></i> Documento corregido</a>' : "");
        else if (e[0] === "DERIVADO") det = "A: " + esc(e[1]);
        else if (e[1]) det = esc(e[1]);
        if (e[0] === "OBSERVADO" && e[2]) det += '<br><a target="_blank" href="../docs adjs reg_doc/' + encodeURIComponent(e[2]) + '"><i class="fa fa-paperclip"></i> Documento observado</a>';
        html +=
          '<div class="ev" style="--c:' + t[1] + '"><div class="tt">' + esc(t[0]) + '</div><div class="ff">' +
          esc(fechaCorta(e[4])) + (e[5] ? " · " + esc(e[5]) : e[0] === "SUBSANADO" ? " · el ciudadano" : "") +
          "</div>" + (det ? '<div class="dd">' + det + "</div>" : "") + "</div>";
      });
      box.innerHTML = html;
    })
    .fail(function () {
      box.innerHTML = '<span class="muted">No se pudo cargar el historial.</span>';
    });
}

/* ---------- Panel de detalle ---------- */
$("#tbody").on("click", ".row", function () {
  var d = this.dataset,
    S = function (id, v) {
      var e = document.getElementById(id);
      if (e) e.textContent = v;
    };
  S("d-doc", d.doc);
  S("d-badge", (d.tram || "").toUpperCase());
  S("d-exp", d.exp);
  S("d-presentado", d.presentado);
  S("d-oficial", d.oficial);
  var w = document.getElementById("d-oficial-w");
  if (w) w.style.display = d.oficial ? "" : "none";
  S("d-recep", d.recep ? d.recep + (d.recepquien ? " — " + d.recepquien : "") : "Pendiente");
  S("d-destino", d.destino || "Sin derivar");
  S("d-folios", d.folios);
  S("d-remit", d.remit);
  S("d-dni", d.dni || "—");
  S("d-email", d.email || "—");
  S("d-tel", d.tel || "—");
  S("d-dir", d.dir || "—");
  S("d-asunto", d.asunto);
  var a = document.getElementById("f-adj");
  if (a) a.href = d.adj || "#";
  $(".row").removeClass("sel");
  $(this).addClass("sel");
  cargarHistorial(d.id, d.presentado);
});
(function () {
  var h = document.getElementById("d-historial");
  var r = document.querySelector("#tbody .row.sel");
  if (h && r) cargarHistorial(r.dataset.id, r.dataset.presentado);
})();

/* ---------- Modales ---------- */
function cerrarModales() {
  document.querySelectorAll(".modal.show").forEach(function (m) {
    m.classList.remove("show");
  });
}
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") cerrarModales();
});
document.querySelectorAll(".modal").forEach(function (m) {
  m.addEventListener("mousedown", function (e) {
    if (e.target === m) m.classList.remove("show");
  });
});
function resumen(d) {
  return (
    "<strong>Exp. " + esc(d.exp) + "</strong> — " + esc(d.doc) +
    "<br>" + esc(d.remit) + (d.dni ? " (DNI/RUC " + esc(d.dni) + ")" : "") +
    "<br><em>" + esc(d.asunto) + "</em>"
  );
}
function respuestaError(resp) {
  var t = typeof resp === "string" ? resp.trim() : "";
  if (t.indexOf("ERROR_") !== -1) {
    alert(t.replace(/^[\s\S]*?ERROR_[A-Z]+:\s*/, ""));
    return true;
  }
  return false;
}
function avisoCorreo(d, el, texto) {
  if (d.email) {
    el.className = "mpv-nota ok";
    el.innerHTML = '<i class="fa fa-envelope"></i> ' + texto + " <strong></strong>.";
    el.querySelector("strong").textContent = d.email;
  } else {
    el.className = "mpv-nota warn";
    el.innerHTML = '<i class="fa fa-exclamation-triangle"></i> El ciudadano no registró correo: no se le podrá avisar.';
  }
}
/** POST de una acción con botón "ocupado" y recarga al terminar. */
function accion(btn, datos, alTerminar) {
  var $b = $(btn).prop("disabled", true);
  var icono = $b.find(".fa").attr("class");
  $b.find(".fa").attr("class", "fa fa-spinner fa-spin");
  datos._csrf = CSRF;
  $.post(URL_MPV, datos)
    .done(function (resp) {
      if (respuestaError(resp)) return;
      if (String(resp || "").indexOf("FALLO") !== -1) {
        alert("La acción se realizó, pero NO se pudo enviar el correo al ciudadano. Puede avisarle por teléfono.");
      }
      if (alTerminar) alTerminar(resp);
      location.reload();
    })
    .fail(function (xhr) {
      alert("Error: " + (xhr.responseText || xhr.status));
    })
    .always(function () {
      $b.prop("disabled", false);
      $b.find(".fa").attr("class", icono);
    });
}

/* ---------- Recepcionar ---------- */
function abrirRecepcionar(row) {
  row = filaDe(row);
  if (!row) return;
  filaActual = row;
  document.getElementById("rc_info").innerHTML = resumen(row.dataset);
  avisoCorreo(row.dataset, document.getElementById("rc_correo"), "Se avisará al ciudadano por correo a");
  document.getElementById("modalRecep").classList.add("show");
}
$("#rc_ok").on("click", function () {
  if (filaActual) accion(this, { recepcionar: "-", id: filaActual.dataset.id });
});

/* ---------- Observar ---------- */
function abrirObservar(row) {
  row = filaDe(row);
  if (!row) return;
  filaActual = row;
  document.getElementById("ob_info").innerHTML = resumen(row.dataset);
  document.getElementById("ob_motivo").value = "";
  var n = document.getElementById("ob_plazo");
  n.innerHTML =
    '<i class="fa fa-clock-o"></i> El ciudadano tendrá ' + esc(pageConfig.plazoDias) +
    " días hábiles para subsanar: <strong>hasta el " + esc(pageConfig.plazoObs) + "</strong>. " +
    (row.dataset.email
      ? "Recibirá un correo en <strong>" + esc(row.dataset.email) + "</strong> con el motivo y un enlace para enviar el documento corregido."
      : "<br><strong>Atención:</strong> no registró correo; tendrá que avisarle por teléfono" + (row.dataset.tel ? " (" + esc(row.dataset.tel) + ")" : "") + ".");
  document.getElementById("modalObs").classList.add("show");
  setTimeout(function () {
    document.getElementById("ob_motivo").focus();
  }, 50);
}
$(".chip").on("click", function () {
  var t = document.getElementById("ob_motivo");
  t.value = (t.value.trim() ? t.value.trim() + " " : "") + this.dataset.txt;
  t.focus();
});
$("#ob_ok").on("click", function () {
  if (!filaActual) return;
  var motivo = (document.getElementById("ob_motivo").value || "").trim();
  if (motivo.length < 10) {
    alert("Escriba qué debe corregir el ciudadano (al menos 10 caracteres).");
    return;
  }
  accion(this, { observar: "-", id: filaActual.dataset.id, motivo: motivo });
});

/* ---------- No presentado ---------- */
function abrirNoPresentado(row) {
  row = filaDe(row);
  if (!row) return;
  filaActual = row;
  document.getElementById("np_info").innerHTML = resumen(row.dataset) + (row.dataset.motivo ? "<br>Observación: " + esc(row.dataset.motivo) : "");
  document.getElementById("np_plazo").textContent = row.dataset.plazo;
  document.getElementById("modalNoPres").classList.add("show");
}
$("#np_ok").on("click", function () {
  if (filaActual) accion(this, { no_presentado: "-", id: filaActual.dataset.id });
});

/* ---------- Derivar ---------- */
function abrirDerivar(row) {
  row = filaDe(row);
  if (!row) return;
  filaActual = row;
  document.getElementById("dv_info").innerHTML = resumen(row.dataset);
  document.getElementById("dv_oficina").value = "";
  document.getElementById("modalDeriv").classList.add("show");
}
$("#dv_ok").on("click", function () {
  if (!filaActual) return;
  var ofi = document.getElementById("dv_oficina").value;
  if (!ofi) {
    alert("Seleccione la oficina destino.");
    return;
  }
  var exp = filaActual.dataset.exp;
  // La pestaña del cargo se abre ANTES de la petición (si no, el navegador la bloquea)
  var ventana = document.getElementById("dv_cargo").checked ? window.open("", "_blank") : null;
  var $b = $(this).prop("disabled", true);
  $.post(URL_MPV, { derivar: "-", id: filaActual.dataset.id, idoficina: ofi, _csrf: CSRF })
    .done(function (resp) {
      if (respuestaError(resp)) {
        if (ventana) ventana.close();
        return;
      }
      if (ventana) ventana.location = "../tcpdf/examples/cargo_externo.php?exp=" + encodeURIComponent(exp);
      location.reload();
    })
    .fail(function (xhr) {
      if (ventana) ventana.close();
      alert("Error al derivar: " + (xhr.responseText || xhr.status));
    })
    .always(function () {
      $b.prop("disabled", false);
    });
});
