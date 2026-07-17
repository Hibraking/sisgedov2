var pageConfig = document.body ? document.body.dataset : {};
var CSRF = pageConfig.csrf || "";
var IDOFICINA = pageConfig.idoficina || "";
var IDRESPONSABLE = pageConfig.idresponsable || "";

$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });
var URL_ENDPOINT = "../clases/recepcion_proveido.php";
var recLote = []; // documentos a recepcionar (uno o varios)
var derItem = null; // documento a derivar
var historialAbierto = false;

function escaparHtml(texto) {
  return String(texto == null ? "" : texto)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function setHistorialAbierto(estado) {
  historialAbierto = !!estado;
  var box = document.getElementById("historial-list");
  var arrow = document.getElementById("historial-arrow");
  if (box) box.classList.toggle("open", historialAbierto);
  if (arrow)
    arrow.className = historialAbierto ? "fa fa-angle-up" : "fa fa-angle-down";
}

function renderHistorial(rows) {
  var box = document.getElementById("historial-list");
  var count = document.getElementById("historial-count");
  if (!box) return;

  if (!rows || !rows.length) {
    box.innerHTML =
      '<div class="history-empty">No hay movimientos registrados para este documento.</div>';
    if (count) count.textContent = "0";
    setHistorialAbierto(false);
    return;
  }

  if (count) count.textContent = String(rows.length);
  var html = "";
  rows.forEach(function (row, index) {
    var esActual = index === rows.length - 1;
    var evento = String(row[7] || "").toLowerCase();
    var badgeClass = "history-badge neutral";
    if (/actual/.test(evento)) badgeClass = "history-badge amber";
    else if (/registro/.test(evento)) badgeClass = "history-badge green";
    else if (/origen/.test(evento)) badgeClass = "history-badge blue";
    else if (/movimiento/.test(evento)) badgeClass = "history-badge cyan";
    var markerClass = esActual ? "current" : "complete";
    html += '<div class="history-item ' + markerClass + '">';
    html += '<div class="history-head">';
    html += '<div class="history-stamp">';
    html += '<span class="history-ico"><i class="fa fa-calendar"></i></span>';
    html += '<div class="history-datetime">';
    html += '<div class="history-date">' + escaparHtml(row[1] || "") + "</div>";
    html +=
      '<div class="history-time"><i class="fa fa-clock-o"></i> ' +
      escaparHtml(row[2] || "") +
      "</div>";
    html += "</div>";
    html += "</div>";
    html +=
      '<span class="' +
      badgeClass +
      '">' +
      escaparHtml(row[7] || "Movimiento") +
      "</span>";
    html += "</div>";
    html +=
      '<div class="history-office">' + escaparHtml(row[3] || "") + "</div>";
    html += '<div class="history-divider"></div>';
    html += '<div class="history-duo">';
    html +=
      '<div class="history-field"><span class="history-mini-ico"><i class="fa fa-paper-plane"></i></span><div><strong>Acción:</strong> ' +
      escaparHtml(row[4] || "--") +
      "</div></div>";
    html +=
      '<div class="history-field right"><span class="history-mini-ico"><i class="fa fa-flag-o"></i></span><div><strong>Estado:</strong> ' +
      escaparHtml(row[5] || "--") +
      "</div></div>";
    html += "</div>";
    html += '<div class="history-divider"></div>';
    html += '<div class="history-details-row">';
    html +=
      '<span class="history-doc-ico"><i class="fa fa-file-text-o"></i></span>';
    html +=
      '<div class="history-details-text">' +
      escaparHtml(row[6] || "") +
      "</div>";
    html += "</div>";
    html += "</div>";
  });

  box.innerHTML = html;
  setHistorialAbierto(false);
}

function cargarHistorial(codigo, tipo) {
  var box = document.getElementById("historial-list");
  if (box) {
    box.innerHTML = '<div class="history-empty">Cargando historial...</div>';
  }
  $.ajax({
    dataType: "json",
    data: { historial_documento: "-", codigo: codigo, tipo: tipo },
    url: URL_ENDPOINT,
    type: "post",
  })
    .done(function (r) {
      renderHistorial(r || []);
    })
    .fail(function () {
      if (box) {
        box.innerHTML =
          '<div class="history-empty">No fue posible cargar el historial.</div>';
      }
      var count = document.getElementById("historial-count");
      if (count) count.textContent = "0";
      setHistorialAbierto(false);
    });
}

function gotoFiltros(page) {
  // URL limpia: solo se incluyen los parámetros que difieren de su valor por defecto
  var params = new URLSearchParams();
  var set = function (k, v, def) {
    v = v == null ? "" : String(v);
    if (v !== "" && v !== def) params.set(k, v);
  };
  set("q", (document.getElementById("buscador").value || "").trim(), "");
  set("desde", normalizarFecha("desde"), "");
  set("hasta", normalizarFecha("hasta"), "");
  set("tipo", document.getElementById("tipo").value, "Todos");
  set("origen", document.getElementById("origen").value, "Todos");
  set("estado", document.getElementById("estado").value, "Todos");
  set("sort", document.getElementById("sort").value, "recentes");
  set("per_page", document.getElementById("per_page").value, "25");
  set("page", String(page || "1"), "1");
  var qs = params.toString();
  window.location = qs ? "?" + qs : window.location.pathname;
}

function normalizarFecha(id) {
  var el = document.getElementById(id);
  if (!el) return "";
  var valor = el.value || "";
  var min = el.getAttribute("min") || "";
  var max = el.getAttribute("max") || "";
  if (min && valor && valor < min) valor = min;
  if (max && valor && valor > max) valor = max;
  el.value = valor;
  return valor;
}

function ajustarRangoFechas() {
  var desde = document.getElementById("desde");
  var hasta = document.getElementById("hasta");
  if (!desde || !hasta) return;

  var fechaMin = desde.getAttribute("min") || "";
  var fechaMax = desde.getAttribute("max") || "";
  desde.min = fechaMin;
  desde.max = fechaMax;
  hasta.min = fechaMin;
  hasta.max = fechaMax;

  if (desde.value && desde.value < fechaMin) desde.value = fechaMin;
  if (desde.value && fechaMax && desde.value > fechaMax) desde.value = fechaMax;
  if (hasta.value && hasta.value < fechaMin) hasta.value = fechaMin;
  if (hasta.value && fechaMax && hasta.value > fechaMax) hasta.value = fechaMax;
}

var historialToggle = document.getElementById("historialToggle");
if (historialToggle) {
  historialToggle.addEventListener("click", function () {
    setHistorialAbierto(!historialAbierto);
  });
  historialToggle.addEventListener("keydown", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      setHistorialAbierto(!historialAbierto);
    }
  });
}

function filtrar() {
  gotoFiltros(1);
}
function setEstado(v) {
  var s = document.getElementById("estado");
  if (s) {
    s.value = v;
    if (s.value !== v) {
      s.value = "Todos";
    }
  }
  filtrar();
}
function limpiarFiltros() {
  document.getElementById("buscador").value = "";
  document.getElementById("desde").value = "";
  document.getElementById("hasta").value = "";
  document.getElementById("tipo").value = "Todos";
  document.getElementById("origen").value = "Todos";
  document.getElementById("estado").value = "Todos";
  document.getElementById("sort").value = "recentes";
  document.getElementById("per_page").value = "25";
  gotoFiltros(1);
}

document.getElementById("buscador").addEventListener("keydown", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    filtrar();
  }
});
["desde", "hasta", "tipo", "origen", "estado"].forEach(function (id) {
  document.getElementById(id).addEventListener("change", function () {
    if (id === "desde" || id === "hasta") normalizarFecha(id);
    filtrar();
  });
});
document.getElementById("sort").addEventListener("change", filtrar);
document.getElementById("per_page").addEventListener("change", filtrar);
ajustarRangoFechas();

$.ajax({
  dataType: "json",
  data: { carga_aciones: "-" },
  url: "../clases/recepcion_proveido.php",
  type: "post",
  success: function (r) {
    var s = document.getElementById("rc_accion");
    (r || []).forEach(function (a) {
      s.appendChild(new Option(a[1], a[0]));
    });
  },
});

/* ====================== Cierre de modales (ESC / clic fuera / X) ====================== */
function cerrarModalesAbiertos() {
  document.querySelectorAll(".modal.show").forEach(function (m) {
    m.classList.remove("show");
  });
}
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") cerrarModalesAbiertos();
});
document.querySelectorAll(".modal").forEach(function (m) {
  m.addEventListener("mousedown", function (e) {
    if (e.target === m) m.classList.remove("show");
  });
});

/* ====================== "Ver más" en asuntos largos ====================== */
function toggleAsunto(btn) {
  var p = btn.parentNode.querySelector(".asun");
  if (!p) return;
  var abierto = p.classList.toggle("open");
  btn.textContent = abierto ? "Ver menos" : "Ver más";
}

/* ====================== Selección de filas ====================== */
function filasSeleccionadas() {
  var out = [];
  document.querySelectorAll("#tbody .row").forEach(function (row) {
    var chk = row.querySelector("input.chk");
    if (chk && chk.checked)
      out.push({
        codigo: String(row.dataset.codigo),
        tipo: String(row.dataset.tipo),
        exp: row.dataset.exp || "",
        doc: row.dataset.doc || "",
      });
  });
  return out;
}
function actualizarContador() {
  var n = filasSeleccionadas().length,
    el = document.getElementById("cntSel");
  if (el) {
    el.textContent = n > 0 ? " (" + n + ")" : "";
    el.style.display = n > 0 ? "inline" : "none";
  }
}
function marcarTodo(estado) {
  document.querySelectorAll("#tbody .row input.chk").forEach(function (c) {
    c.checked = estado;
  });
  var s1 = document.getElementById("selall"),
    s2 = document.getElementById("selall2");
  if (s1) s1.checked = estado;
  if (s2) s2.checked = estado;
  actualizarContador();
}
["selall", "selall2"].forEach(function (id) {
  var el = document.getElementById(id);
  if (el)
    el.addEventListener("change", function () {
      marcarTodo(this.checked);
    });
});
$("#tbody").on("change", "input.chk", actualizarContador);

/* ====================== Recepcionar (individual o en lote) ====================== */
function abrirRecep(codigo, tipo) {
  abrirRecepLote([
    { codigo: String(codigo), tipo: String(tipo), exp: "", doc: "" },
  ]);
}
function abrirRecepLote(lista) {
  recLote = lista;
  var info = document.getElementById("rc_info");
  if (info)
    info.textContent =
      lista.length > 1
        ? "Se recepcionarán " +
          lista.length +
          " documentos con la misma acción, folios y detalles."
        : "";
  document.getElementById("rc_accion").value = "";
  document.getElementById("rc_folio").value = "";
  document.getElementById("rc_detalles").value = "";
  document.getElementById("modalRecep").classList.add("show");
}
function cerrarRecep() {
  document.getElementById("modalRecep").classList.remove("show");
}

// El botón no existe en las bandejas de solo consulta (Recepcionado/Enviado)
var btnRecepSel = document.getElementById("btnRecepSel");
if (btnRecepSel)
  btnRecepSel.addEventListener("click", function () {
    var sel = filasSeleccionadas();
    if (sel.length === 0) {
      alert("Marque al menos un documento de la lista para recepcionar.");
      return;
    }
    abrirRecepLote(sel);
  });

function paramsRecepcion(item, idacc, folio, det) {
  if (item.tipo === "1")
    return {
      insertar: "-",
      idregdocs: item.codigo,
      iddocs_emitidos: null,
      idacciondocs: idacc,
      detalles: det,
      Folio: folio,
      _csrf: CSRF,
    };
  if (item.tipo === "2")
    return {
      insertar: "-",
      idregdocs: null,
      iddocs_emitidos: item.codigo,
      idacciondocs: idacc,
      detalles: det,
      Folio: folio,
      _csrf: CSRF,
    };
  if (item.tipo === "3")
    return {
      recibir_derivado: "-",
      detalles: det,
      codigo: item.codigo,
      Folio: folio,
      _csrf: CSRF,
    };
  return null;
}

$("#rc_guardar").on("click", function () {
  var folio = document.getElementById("rc_folio").value,
    idacc = document.getElementById("rc_accion").value,
    det = document.getElementById("rc_detalles").value;
  if (idacc === "") {
    alert("Por favor seleccione una acción a realizar");
    return;
  }
  if (folio === "" || parseInt(folio, 10) < 1) {
    alert("Ingrese una cantidad de folios válida");
    return;
  }
  if (recLote.length === 0) {
    alert("No hay documentos para recepcionar.");
    return;
  }
  $("#rc_guardar").prop("disabled", true);
  var cola = recLote.slice(),
    errores = [];
  (function siguiente() {
    if (cola.length === 0) {
      if (errores.length)
        alert(
          "Algunos documentos no se pudieron recepcionar:\n" +
            errores.join("\n"),
        );
      location.reload();
      return;
    }
    var item = cola.shift(),
      p = paramsRecepcion(item, idacc, folio, det);
    if (!p) {
      errores.push((item.exp || item.codigo) + ": tipo no reconocido");
      siguiente();
      return;
    }
    $.ajax({ data: p, url: URL_ENDPOINT, type: "post" })
      .done(function (resp) {
        if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1)
          errores.push(
            (item.exp || item.codigo) +
              ": " +
              resp.replace(/^ERROR_[A-Z]+:\s*/, ""),
          );
        siguiente();
      })
      .fail(function (xhr) {
        errores.push(
          (item.exp || item.codigo) +
            ": " +
            (xhr.responseText || "error de red"),
        );
        siguiente();
      });
  })();
});

/* ====================== Derivar (recepciona y deriva) ====================== */
var dvCombosCargados = false;
var derModo = "derivar"; // 'derivar' | 'devolver'
var dvPreEstadoRegex = null,
  dvPreOficinaNombre = null;

function aplicarPreseleccionDeriv() {
  var s = document.getElementById("dv_estado");
  if (s && dvPreEstadoRegex) {
    for (var i = 0; i < s.options.length; i++) {
      if (dvPreEstadoRegex.test(s.options[i].text)) {
        s.value = s.options[i].value;
        break;
      }
    }
  }
  var o = document.getElementById("dv_oficina");
  if (o && dvPreOficinaNombre) {
    var buscado = dvPreOficinaNombre.trim().toLowerCase();
    for (var j = 0; j < o.options.length; j++) {
      if (o.options[j].text.trim().toLowerCase() === buscado) {
        o.value = o.options[j].value;
        break;
      }
    }
  }
}
function cargarCombosDeriv() {
  if (dvCombosCargados) {
    aplicarPreseleccionDeriv();
    return;
  }
  dvCombosCargados = true;
  $.ajax({
    dataType: "json",
    data: { carga_oficinas: "-" },
    url: URL_ENDPOINT,
    type: "post",
    success: function (r) {
      var s = document.getElementById("dv_oficina");
      (r || []).forEach(function (o) {
        var nombre = String(o[1] == null ? "" : o[1]).trim();
        if (nombre === "" || nombre === "-") return; // omite oficinas basura
        s.appendChild(new Option(o[1], o[0]));
      });
      aplicarPreseleccionDeriv();
    },
  });
  $.ajax({
    dataType: "json",
    data: { carga_estados: "-" },
    url: URL_ENDPOINT,
    type: "post",
    success: function (r) {
      var s = document.getElementById("dv_estado");
      (r || []).forEach(function (e) {
        s.appendChild(new Option(e[1], e[0]));
      });
      var a = document.getElementById("ar_estado");
      if (a) {
        (r || []).forEach(function (e) {
          a.appendChild(new Option(e[1], e[0]));
        });
        for (var i = 0; i < a.options.length; i++) {
          if (/ARCHIV/i.test(a.options[i].text)) {
            a.value = a.options[i].value;
            break;
          }
        }
      }
      aplicarPreseleccionDeriv();
    },
  });
  var src = document.getElementById("rc_accion"),
    dst = document.getElementById("dv_accion");
  for (var i = 0; i < src.options.length; i++) {
    if (src.options[i].value !== "")
      dst.appendChild(new Option(src.options[i].text, src.options[i].value));
  }
}
function configurarModalDeriv(item, modo) {
  derItem = item;
  derModo = modo;
  var esRecepcionado = item.tipo === "R";
  dvPreEstadoRegex = modo === "devolver" ? /DEVU/i : /DERIV/i;
  dvPreOficinaNombre = modo === "devolver" ? item.ori || null : null;
  document.getElementById("dv_titulo").textContent =
    modo === "devolver" ? "Devolver documento" : "Derivar documento";
  document.getElementById("dv_btn_txt").textContent =
    modo === "devolver"
      ? "Devolver"
      : esRecepcionado
        ? "Derivar"
        : "Recepcionar y derivar";
  document.getElementById("dv_info").textContent =
    (item.doc ? item.doc + " — " : "") +
    "Exp. " +
    (item.exp || item.codigo) +
    (esRecepcionado ? "" : ". Se recepcionará y derivará en un solo paso.");
  // Folios solo se piden cuando falta la recepción
  document.getElementById("dv_folio_wrap").style.display = esRecepcionado
    ? "none"
    : "";
  document.getElementById("dv_folio").value = "";
  document.getElementById("dv_detalles").value = "";
  cargarCombosDeriv();
  aplicarPreseleccionDeriv();
  document.getElementById("modalDeriv").classList.add("show");
}
function abrirDeriv() {
  var sel = filasSeleccionadas(),
    item = null;
  if (sel.length === 1) item = sel[0];
  else if (sel.length === 0) {
    var act = document.querySelector("#tbody .row.sel");
    if (act)
      item = {
        codigo: String(act.dataset.codigo),
        tipo: String(act.dataset.tipo),
        exp: act.dataset.exp || "",
        doc: act.dataset.doc || "",
        ori: act.dataset.ori || "",
      };
  }
  if (sel.length > 1) {
    alert("Para derivar seleccione solo un documento.");
    return;
  }
  if (!item) {
    alert("Seleccione un documento para derivar.");
    return;
  }
  configurarModalDeriv(item, "derivar");
}
// Acciones sobre documentos YA recepcionados (bandeja Recepcionado)
function itemDesdeFila(el) {
  var row = el.closest(".row");
  if (!row) return null;
  var d = row.dataset;
  return {
    codigo: String(d.codigo),
    tipo: String(d.tipo),
    exp: d.exp || "",
    doc: d.doc || "",
    ori: d.ori || "",
  };
}
function abrirAccionR(el, modo) {
  var item = itemDesdeFila(el);
  if (!item) {
    alert("No se pudo identificar el documento.");
    return;
  }
  configurarModalDeriv(item, modo);
}
function cerrarDeriv() {
  document.getElementById("modalDeriv").classList.remove("show");
}
var btnDerivar = document.getElementById("btnDerivar");
if (btnDerivar) btnDerivar.addEventListener("click", abrirDeriv);

$("#dv_guardar").on("click", function () {
  var idofi = document.getElementById("dv_oficina").value,
    idacc = document.getElementById("dv_accion").value,
    idest = document.getElementById("dv_estado").value,
    folio = document.getElementById("dv_folio").value,
    det = document.getElementById("dv_detalles").value;
  if (idofi === "") {
    alert("Seleccione la oficina destino.");
    return;
  }
  if (idacc === "") {
    alert("Seleccione una acción.");
    return;
  }
  if (idest === "") {
    alert("Seleccione el estado.");
    return;
  }
  if (derItem.tipo !== "R" && (folio === "" || parseInt(folio, 10) < 1)) {
    alert("Ingrese una cantidad de folios válida.");
    return;
  }
  var btn = $("#dv_guardar");
  btn.prop("disabled", true);
  function fallo(msj) {
    btn.prop("disabled", false);
    alert(msj);
  }
  function derivar(codigoMov, idregdocs, iddocs) {
    $.ajax({
      data: {
        insert_deriv_resp_dev: "-",
        idregdocs: idregdocs,
        iddocs_emitidos: iddocs,
        idacciondocs: idacc,
        idestadodocs: idest,
        idoficina_derivar: idofi,
        detalles: det,
        Codigo: codigoMov,
        NuevoEstado: idest,
        _csrf: CSRF,
      },
      url: URL_ENDPOINT,
      type: "post",
    })
      .done(function (resp) {
        if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1) {
          fallo(resp);
          return;
        }
        location.reload();
      })
      .fail(function (xhr) {
        fallo("Error al derivar: " + (xhr.responseText || ""));
      });
  }
  if (derItem.tipo === "R") {
    // Documento YA recepcionado: derivar/devolver directamente (sin recepción previa)
    $.ajax({
      dataType: "json",
      data: { Obtener: "-", Codigo: derItem.codigo },
      url: URL_ENDPOINT,
      type: "post",
    })
      .done(function (r) {
        var reg = r && r[0] ? r[0] : {};
        derivar(
          derItem.codigo,
          reg["idregdocs"] !== undefined ? reg["idregdocs"] : reg[0],
          reg["iddocs_emitidos"] !== undefined
            ? reg["iddocs_emitidos"]
            : reg[1],
        );
      })
      .fail(function () {
        fallo("No se pudo obtener el documento del movimiento.");
      });
    return;
  }
  if (derItem.tipo === "3") {
    // Ya existe el movimiento: 1) recepcionarlo, 2) obtener su origen, 3) derivarlo
    $.ajax({
      data: {
        recibir_derivado: "-",
        detalles: det,
        codigo: derItem.codigo,
        Folio: folio,
        _csrf: CSRF,
      },
      url: URL_ENDPOINT,
      type: "post",
    })
      .done(function (resp) {
        if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1) {
          fallo(resp);
          return;
        }
        $.ajax({
          dataType: "json",
          data: { Obtener: "-", Codigo: derItem.codigo },
          url: URL_ENDPOINT,
          type: "post",
        })
          .done(function (r) {
            var reg = r && r[0] ? r[0] : {};
            derivar(
              derItem.codigo,
              reg["idregdocs"] !== undefined ? reg["idregdocs"] : reg[0],
              reg["iddocs_emitidos"] !== undefined
                ? reg["iddocs_emitidos"]
                : reg[1],
            );
          })
          .fail(function () {
            fallo(
              "Se recepcionó el documento pero no se pudo derivar. Inténtelo desde la bandeja.",
            );
          });
      })
      .fail(function (xhr) {
        fallo("Error al recepcionar: " + (xhr.responseText || ""));
      });
  } else {
    // Documento sin movimiento: "insertar" lo recepciona y devuelve el id del movimiento nuevo
    var p = paramsRecepcion(derItem, idacc, folio, det);
    $.ajax({ data: p, url: URL_ENDPOINT, type: "post" })
      .done(function (resp) {
        if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1) {
          fallo(resp);
          return;
        }
        var nuevoId = parseInt(String(resp).replace(/[^0-9]/g, ""), 10);
        if (!nuevoId) {
          fallo(
            "Se recepcionó el documento pero no se obtuvo el movimiento para derivar.",
          );
          return;
        }
        derivar(
          nuevoId,
          derItem.tipo === "1" ? derItem.codigo : null,
          derItem.tipo === "2" ? derItem.codigo : null,
        );
      })
      .fail(function (xhr) {
        fallo("Error al recepcionar: " + (xhr.responseText || ""));
      });
  }
});

/* ====================== Archivar (bandeja Recepcionado) ====================== */
var arItem = null,
  arCombosCargados = false;
function abrirArchivar(el) {
  var item = itemDesdeFila(el);
  if (!item) {
    alert("No se pudo identificar el documento.");
    return;
  }
  arItem = item;
  cargarCombosDeriv(); // carga estados (llena también ar_estado)
  if (!arCombosCargados) {
    arCombosCargados = true;
    $.ajax({
      dataType: "json",
      data: { get_archivadores: "-", idoficina: IDOFICINA },
      url: URL_ENDPOINT,
      type: "post",
      success: function (r) {
        var s = document.getElementById("ar_archivador");
        (r || []).forEach(function (a) {
          s.appendChild(new Option(a[1], a[0]));
        });
        if (s.options.length <= 1) {
          s.appendChild(
            new Option("— No hay archivadores en esta oficina —", ""),
          );
        }
      },
    });
  }
  document.getElementById("ar_info").textContent =
    (item.doc ? item.doc + " — " : "") + "Exp. " + (item.exp || item.codigo);
  document.getElementById("ar_detalles").value = "";
  document.getElementById("modalArch").classList.add("show");
}
function cerrarArchivar() {
  document.getElementById("modalArch").classList.remove("show");
}

$("#ar_guardar").on("click", function () {
  var idarch = document.getElementById("ar_archivador").value,
    idest = document.getElementById("ar_estado").value,
    det = document.getElementById("ar_detalles").value;
  if (idarch === "") {
    alert(
      "Seleccione un archivador. Si no existe ninguno, créelo en el módulo Archivo.",
    );
    return;
  }
  if (idest === "") {
    alert("Seleccione el estado.");
    return;
  }
  if (!arItem) {
    alert("No hay documento para archivar.");
    return;
  }
  var btn = $("#ar_guardar");
  btn.prop("disabled", true);
  $.ajax({
    data: {
      insert_archivar: "-",
      idarchivador: idarch,
      idmovimiento: arItem.codigo,
      detalles: det,
      idestadodoc: idest,
      _csrf: CSRF,
    },
    url: URL_ENDPOINT,
    type: "post",
  })
    .done(function (resp) {
      if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1) {
        btn.prop("disabled", false);
        alert(resp);
        return;
      }
      location.reload();
    })
    .fail(function (xhr) {
      btn.prop("disabled", false);
      alert("Error al archivar: " + (xhr.responseText || ""));
    });
});

/* ====================== Exportar y Más acciones ====================== */
document.querySelectorAll(".dd > .btn").forEach(function (b) {
  b.addEventListener("click", function (e) {
    e.stopPropagation();
    var dd = this.parentNode,
      abierto = dd.classList.contains("open");
    document.querySelectorAll(".dd.open").forEach(function (x) {
      x.classList.remove("open");
    });
    if (!abierto) dd.classList.add("open");
  });
});
document.addEventListener("click", function () {
  document.querySelectorAll(".dd.open").forEach(function (x) {
    x.classList.remove("open");
  });
});

function exportarListado(formato) {
  var qs = new URLSearchParams(window.location.search);
  qs.set("export", formato);
  if (formato === "pdf") {
    window.open("?" + qs.toString(), "_blank");
  } // el PDF se abre en otra pestaña
  else {
    window.location = "?" + qs.toString();
  }
}
document.getElementById("expCsv").addEventListener("click", function (e) {
  e.preventDefault();
  exportarListado("csv");
});
document.getElementById("expXls").addEventListener("click", function (e) {
  e.preventDefault();
  exportarListado("xls");
});
document.getElementById("expPdf").addEventListener("click", function (e) {
  e.preventDefault();
  exportarListado("pdf");
});
document.getElementById("expPrint").addEventListener("click", function (e) {
  e.preventDefault();
  window.print();
});
document
  .getElementById("masActualizar")
  .addEventListener("click", function (e) {
    e.preventDefault();
    location.reload();
  });
document.getElementById("masLimpiar").addEventListener("click", function (e) {
  e.preventDefault();
  limpiarFiltros();
});
document.getElementById("masCopiar").addEventListener("click", function (e) {
  e.preventDefault();
  var sel = filasSeleccionadas();
  if (sel.length === 0) {
    alert("Marque al menos un documento para copiar sus expedientes.");
    return;
  }
  var texto = sel
    .map(function (s) {
      return s.exp || s.codigo;
    })
    .join("\n");
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(texto).then(
      function () {
        alert(sel.length + " expediente(s) copiado(s) al portapapeles.");
      },
      function () {
        window.prompt("Copie los expedientes:", texto);
      },
    );
  } else {
    window.prompt("Copie los expedientes:", texto);
  }
});

// Detalles al seleccionar fila
$("#tbody").on("click", ".row", function () {
  var d = this.dataset,
    S = function (id, v) {
      var e = document.getElementById(id);
      if (e) e.textContent = v;
    };
  S("d-doc", d.doc);
  S("d-ori", d.ori);
  S("d-fecha", d.fecha);
  S("d-tram", d.tram);
  S("d-exp", d.exp);
  S("d-asunto", d.asunto);
  var b = document.getElementById("d-badge");
  if (b) {
    b.textContent = d.badge;
  }
  var box = document.getElementById("d-adjbox");
  if (box) {
    box.innerHTML = "";
    if (d.adj && d.adj !== "#") {
      // El enlace se construye con DOM (no concatenando HTML) para evitar XSS.
      var a = document.createElement("a");
      a.className = "adj";
      a.target = "_blank";
      a.rel = "noopener";
      a.style.textDecoration = "none";
      a.href = d.adj;
      a.innerHTML =
        '<i class="fa fa-file-pdf-o" style="color:#ef4444;font-size:20px"></i><div style="flex:1"><div class="nm">Documento adjunto</div><div class="sz">Ver archivo</div></div><i class="fa fa-download" style="color:#94a3b8"></i>';
      box.appendChild(a);
    } else {
      box.innerHTML =
        '<div class="muted" style="margin-top:8px">Sin archivos adjuntos.</div>';
    }
  }
  $(".row").removeClass("sel");
  $(this).addClass("sel");
  cargarHistorial(d.codigo, d.tipo);
});

var filaActivaInicial = document.querySelector("#tbody .row.sel");
if (filaActivaInicial) {
  cargarHistorial(
    filaActivaInicial.dataset.codigo,
    filaActivaInicial.dataset.tipo,
  );
}
