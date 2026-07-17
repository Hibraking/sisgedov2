var pageConfig = document.body ? document.body.dataset : {};
var CSRF = pageConfig.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

var URL_REG = "../clases/reg_docs.php";
var URL_REMIT = "../clases/remitentes.php";
var URL_RENIEC = "../clases/consulta_reniec.php";
var modoReg = "nuevo"; // 'nuevo' | 'editar'
var regCodigo = null,
  regIdOfi = "";

/* ====================== Filtros (URL limpia) ====================== */
function gotoFiltros(page) {
  var params = new URLSearchParams();
  var set = function (k, v, def) {
    v = v == null ? "" : String(v);
    if (v !== "" && v !== def) params.set(k, v);
  };
  set("q", (document.getElementById("buscador").value || "").trim(), "");
  set("desde", document.getElementById("desde").value, "");
  set("hasta", document.getElementById("hasta").value, "");
  set("estado", document.getElementById("estado").value, "Todos");
  set("sort", document.getElementById("sort").value, "recentes");
  set("per_page", document.getElementById("per_page").value, "25");
  set("page", String(page || "1"), "1");
  var qs = params.toString();
  window.location = qs ? "?" + qs : window.location.pathname;
}
function filtrar() {
  gotoFiltros(1);
}
function limpiarFiltros() {
  document.getElementById("buscador").value = "";
  document.getElementById("desde").value = "";
  document.getElementById("hasta").value = "";
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
["desde", "hasta", "estado", "sort", "per_page"].forEach(function (id) {
  document.getElementById(id).addEventListener("change", filtrar);
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

/* ====================== Combo desplegable multi-selección de oficinas ====================== */
function oficinasMarcadas(idLista) {
  var out = [];
  document
    .querySelectorAll("#" + idLista + " input[type=checkbox]:checked")
    .forEach(function (c) {
      out.push(c.value);
    });
  return out;
}
function toggleMsel(id) {
  var m = document.getElementById(id);
  if (!m) return;
  var abierto = m.classList.toggle("open");
  if (abierto) {
    var b = m.querySelector(".ofi-buscar");
    if (b) {
      b.value = "";
      b.focus();
      m.querySelectorAll(".ofi-item").forEach(function (l) {
        l.classList.remove("oculto");
      });
    }
  }
}
function renderTagsOficinas(idLista, idTags, placeholder) {
  var tags = document.getElementById(idTags);
  if (!tags) return;
  tags.innerHTML = "";
  var marcados = document.querySelectorAll(
    "#" + idLista + " input[type=checkbox]:checked",
  );
  if (marcados.length === 0) {
    var ph = document.createElement("span");
    ph.className = "msel-ph";
    ph.textContent = placeholder;
    tags.appendChild(ph);
    return;
  }
  marcados.forEach(function (c) {
    var nombre = (c.parentNode.querySelector("span") || {}).textContent || "";
    var t = document.createElement("span");
    t.className = "msel-tag";
    var tx = document.createElement("span");
    tx.textContent = nombre;
    t.appendChild(tx);
    var x = document.createElement("b");
    x.textContent = "×";
    x.title = "Quitar";
    x.addEventListener("click", function (e) {
      e.stopPropagation();
      c.checked = false;
      renderTagsOficinas(idLista, idTags, placeholder);
    });
    t.appendChild(x);
    tags.appendChild(t);
  });
}
function limpiarOficinas(idLista, idBuscar, idTags, placeholder, idMsel) {
  document
    .querySelectorAll("#" + idLista + " input[type=checkbox]")
    .forEach(function (c) {
      c.checked = false;
    });
  document.querySelectorAll("#" + idLista + " .ofi-item").forEach(function (l) {
    l.classList.remove("oculto");
  });
  var b = document.getElementById(idBuscar);
  if (b) b.value = "";
  var m = document.getElementById(idMsel);
  if (m) m.classList.remove("open");
  renderTagsOficinas(idLista, idTags, placeholder);
}
function conectarPickerOficinas(idLista, idBuscar, idTags, placeholder) {
  var buscar = document.getElementById(idBuscar),
    lista = document.getElementById(idLista);
  if (!lista) return;
  if (buscar)
    buscar.addEventListener("input", function () {
      var q = this.value.trim().toLowerCase();
      lista.querySelectorAll(".ofi-item").forEach(function (l) {
        l.classList.toggle(
          "oculto",
          q !== "" && l.textContent.toLowerCase().indexOf(q) === -1,
        );
      });
    });
  lista.addEventListener("change", function () {
    renderTagsOficinas(idLista, idTags, placeholder);
  });
  renderTagsOficinas(idLista, idTags, placeholder);
}
conectarPickerOficinas(
  "mr_oficinas",
  "mr_ofi_buscar",
  "mr_ofi_tags",
  "Seleccione una o varias oficinas...",
);
// Cierra el desplegable al hacer clic fuera de él
document.addEventListener("mousedown", function (e) {
  document.querySelectorAll(".msel.open").forEach(function (m) {
    if (!m.contains(e.target)) m.classList.remove("open");
  });
});

/* ====================== Panel de detalles ====================== */
$("#tbody").on("click", ".row", function () {
  var d = this.dataset,
    S = function (id, v) {
      var e = document.getElementById(id);
      if (e) e.textContent = v;
    };
  S("d-doc", d.doc);
  S("d-exp", d.exp);
  S("d-remit", d.remit);
  S("d-destino", d.destino || 'Sin destino — usa "Derivar"');
  S("d-origen", d.origen || "Mesa de Partes");
  S("d-folios", d.folios);
  S("d-estado", d.estado);
  S("d-fecha", d.fecha);
  S("d-asunto", d.asunto);
  S("d-obs", d.obs || "—");
  var b = document.getElementById("d-badge");
  if (b) {
    b.textContent = (d.tram || "").toUpperCase();
  }
  $(".row").removeClass("sel");
  $(this).addClass("sel");
});

/* ====================== Remitentes (buscador con datalist) ====================== */
var mapaRemitentes = {}; // "APELLIDOS, NOMBRES" -> id
var remitentesCargados = false;
function cargarRemitentes(cb) {
  $.ajax({
    dataType: "json",
    data: { carga_remitente: "-" },
    url: URL_REG,
    type: "post",
    success: function (r) {
      var dl = document.getElementById("mr_remitentes_list");
      dl.innerHTML = "";
      mapaRemitentes = {};
      (r || []).forEach(function (x) {
        var id = x[0] !== undefined ? x[0] : x["idremitente"];
        var nom = x[1] !== undefined ? x[1] : x["ape_nom"];
        if (nom == null) return;
        mapaRemitentes[String(nom).trim().toLowerCase()] = id;
        var o = document.createElement("option");
        o.value = nom;
        dl.appendChild(o);
      });
      remitentesCargados = true;
      if (cb) cb();
    },
  });
}
function idRemitenteActual() {
  var txt = (document.getElementById("mr_remitente").value || "")
    .trim()
    .toLowerCase();
  if (txt === "") return "";
  if (mapaRemitentes[txt] !== undefined) return String(mapaRemitentes[txt]);
  return "";
}
document.getElementById("mr_remitente").addEventListener("change", function () {
  document.getElementById("mr_idremitente").value = idRemitenteActual();
});

/* ====================== Registrar / Editar ====================== */
function abrirNuevo() {
  modoReg = "nuevo";
  regCodigo = null;
  regIdOfi = "";
  document.getElementById("mr_titulo").textContent = "Registrar documento";
  document.getElementById("mr_btn_txt").textContent = "Registrar";
  [
    "mr_remitente",
    "mr_idremitente",
    "mr_numero",
    "mr_folios",
    "mr_proc",
    "mr_asunto",
    "mr_parasu",
    "mr_obs",
  ].forEach(function (id) {
    document.getElementById(id).value = "";
  });
  document.getElementById("mr_tipo").value = "";
  document.getElementById("mr_tram").value = "";
  document.getElementById("mr_fichero").value = "";
  limpiarOficinas(
    "mr_oficinas",
    "mr_ofi_buscar",
    "mr_ofi_tags",
    "Seleccione una o varias oficinas...",
    "mr_ofi_msel",
  );
  document.getElementById("mr_adj_wrap").style.display = "";
  document.getElementById("mr_ofi_wrap").style.display = "";
  if (!remitentesCargados) cargarRemitentes();
  document.getElementById("modalReg").classList.add("show");
}
function abrirEditar(el) {
  var row = el.closest(".row");
  if (!row) return;
  var d = row.dataset;
  if (d.editable !== "1") {
    alert("El documento ya fue recepcionado; no puede modificarse.");
    return;
  }
  modoReg = "editar";
  regCodigo = String(d.id);
  regIdOfi = String(d.idofi || "");
  document.getElementById("mr_titulo").textContent =
    "Editar registro — Exp. " + (d.exp || "");
  document.getElementById("mr_btn_txt").textContent = "Guardar cambios";
  document.getElementById("mr_remitente").value = d.remit || "";
  document.getElementById("mr_idremitente").value = d.idremit || "";
  document.getElementById("mr_tipo").value = String(d.iddoc || "");
  document.getElementById("mr_tram").value = String(d.idtram || "");
  document.getElementById("mr_numero").value = d.num || "";
  document.getElementById("mr_folios").value = d.folios || "";
  document.getElementById("mr_proc").value = d.proc || "";
  document.getElementById("mr_asunto").value = d.asunto || "";
  document.getElementById("mr_parasu").value = d.parasu || "";
  document.getElementById("mr_obs").value = d.obs || "";
  // El destino y el adjunto no se cambian al editar (mantiene el comportamiento del sistema)
  document.getElementById("mr_adj_wrap").style.display = "none";
  document.getElementById("mr_ofi_wrap").style.display = "none";
  if (!remitentesCargados) cargarRemitentes();
  document.getElementById("modalReg").classList.add("show");
}
function cerrarReg() {
  document.getElementById("modalReg").classList.remove("show");
}
document.getElementById("btnNuevoReg").addEventListener("click", abrirNuevo);

$("#mr_guardar").on("click", function () {
  var idremit =
      document.getElementById("mr_idremitente").value || idRemitenteActual(),
    tipo = document.getElementById("mr_tipo").value,
    tram = document.getElementById("mr_tram").value,
    numero = (document.getElementById("mr_numero").value || "").trim(),
    folios = document.getElementById("mr_folios").value,
    proc = document.getElementById("mr_proc").value,
    asunto = (document.getElementById("mr_asunto").value || "").trim(),
    parasu = document.getElementById("mr_parasu").value,
    obs = document.getElementById("mr_obs").value;
  if (idremit === "") {
    alert('Seleccione un remitente de la lista o regístrelo con el botón "+".');
    return;
  }
  if (tipo === "") {
    alert("Seleccione el tipo de documento.");
    return;
  }
  if (tram === "") {
    alert("Seleccione el tipo de trámite.");
    return;
  }
  if (asunto === "") {
    alert("Ingrese el asunto.");
    return;
  }
  if (folios === "" || parseInt(folios, 10) < 1) {
    alert("Ingrese un número de folios válido.");
    return;
  }
  var listaOfi = "";
  if (modoReg === "nuevo") {
    var sel = oficinasMarcadas("mr_oficinas");
    if (sel.length === 0) {
      alert("Marque al menos una oficina destino.");
      return;
    }
    listaOfi = sel.join(",");
  } else {
    listaOfi = regIdOfi !== "0" ? regIdOfi : "";
  }
  var btn = $("#mr_guardar");
  btn.prop("disabled", true);
  function listo(resp) {
    if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1) {
      btn.prop("disabled", false);
      alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ""));
      return;
    }
    location.reload();
  }
  function fallo(xhr) {
    btn.prop("disabled", false);
    alert("Error al guardar: " + (xhr.responseText || ""));
  }
  var fd = new FormData();
  fd.append(modoReg === "nuevo" ? "Insertar" : "Modificar", "-");
  if (modoReg === "editar") fd.append("Codigo", regCodigo);
  fd.append("Documento", tipo);
  fd.append("Remitente", idremit);
  fd.append("TipoTram", tram);
  fd.append("OficinaRecep", "");
  fd.append("NumDoc", numero);
  fd.append("Folios", folios);
  fd.append("Asunto", asunto);
  fd.append("Parasu", parasu);
  fd.append("observacion", obs);
  fd.append("Procedencia", proc);
  fd.append("lista_oficinas", listaOfi);
  fd.append("_csrf", CSRF);
  var f = document.getElementById("mr_fichero");
  if (modoReg === "nuevo" && f.files && f.files[0])
    fd.append("fichero", f.files[0]);
  $.ajax({
    url: URL_REG,
    type: "post",
    data: fd,
    processData: false,
    contentType: false,
  })
    .done(listo)
    .fail(fallo);
});

/* ====================== Derivar a una oficina ====================== */
// Asigna la oficina destino de un registro pendiente reutilizando el handler
// Modificar (los documentos de Mesa de Partes Virtual llegan sin destino).
var derivRegDatos = null,
  derivRegExp = "",
  derivRegSinDestino = false;
function abrirDerivarReg(el) {
  var row = el.closest(".row");
  if (!row) return;
  var d = row.dataset;
  if (d.editable !== "1") {
    alert("El documento ya fue recepcionado; no puede derivarse desde aquí.");
    return;
  }
  derivRegExp = d.exp || "";
  derivRegSinDestino = !d.destino; // sin destino => al derivar se genera su cargo por primera vez
  derivRegDatos = {
    Codigo: String(d.id),
    Documento: String(d.iddoc || ""),
    Remitente: String(d.idremit || ""),
    TipoTram: String(d.idtram || ""),
    NumDoc: d.num || "",
    Folios: d.folios || "1",
    Asunto: d.asunto || "",
    Parasu: d.parasu || "",
    observacion: d.obs || "",
    Procedencia: d.proc || "",
  };
  document.getElementById("dr_info").textContent =
    (d.doc || "") +
    " — Exp. " +
    (d.exp || "") +
    (d.destino
      ? ". Destino actual: " + d.destino
      : ". Actualmente SIN destino.");
  document.getElementById("dr_oficina").value =
    String(d.idofi || "") !== "0" ? String(d.idofi || "") : "";
  document.getElementById("modalDerivReg").classList.add("show");
}
function cerrarDerivarReg() {
  document.getElementById("modalDerivReg").classList.remove("show");
}

$("#dr_guardar").on("click", function () {
  var ofi = document.getElementById("dr_oficina").value;
  if (ofi === "") {
    alert("Selecciona la oficina destino.");
    return;
  }
  if (!derivRegDatos) {
    alert("No hay documento seleccionado.");
    return;
  }
  var btn = $("#dr_guardar");
  btn.prop("disabled", true);
  var datos = {
    Modificar: "-",
    OficinaRecep: "",
    lista_oficinas: ofi,
    _csrf: CSRF,
  };
  for (var k in derivRegDatos) {
    datos[k] = derivRegDatos[k];
  }
  $.ajax({ url: URL_REG, type: "post", data: datos })
    .done(function (resp) {
      btn.prop("disabled", false);
      if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1) {
        alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ""));
        return;
      }
      // Documento sin destino (p. ej. de Mesa de Partes Virtual): recién ahora
      // tiene oficina asignada, así que se genera su cargo automáticamente.
      if (derivRegSinDestino && derivRegExp !== "") {
        window.open(
          "../tcpdf/examples/cargo_externo.php?exp=" +
            encodeURIComponent(derivRegExp),
          "_blank",
        );
      }
      location.reload();
    })
    .fail(function (xhr) {
      btn.prop("disabled", false);
      alert("Error al derivar: " + (xhr.responseText || ""));
    });
});

/* ====================== Eliminar registro ====================== */
function eliminarReg(el) {
  var row = el.closest(".row");
  if (!row) return;
  var d = row.dataset;
  if (d.editable !== "1") {
    alert("El documento ya fue recepcionado; no puede eliminarse.");
    return;
  }
  if (
    !confirm(
      "¿Eliminar el registro del expediente " +
        (d.exp || "") +
        "? Esta acción no se puede deshacer.",
    )
  )
    return;
  $.ajax({
    url: URL_REG,
    type: "post",
    data: { Emilinar: "-", Codigo: String(d.id), _csrf: CSRF },
  })
    .done(function (resp) {
      if (typeof resp === "string" && resp.indexOf("ERROR_") !== -1) {
        alert(resp.replace(/^ERROR_[A-Z]+:\s*/, ""));
        return;
      }
      location.reload();
    })
    .fail(function (xhr) {
      alert("Error al eliminar: " + (xhr.responseText || ""));
    });
}

/* ====================== Nuevo remitente (con RENIEC) ====================== */
function abrirNuevoRemit() {
  [
    "nr_dni",
    "nr_apellidos",
    "nr_nombres",
    "nr_direccion",
    "nr_email",
    "nr_telefono",
  ].forEach(function (id) {
    document.getElementById(id).value = "";
  });
  document.getElementById("modalRemit").classList.add("show");
}
function cerrarNuevoRemit() {
  document.getElementById("modalRemit").classList.remove("show");
}

document.getElementById("nr_buscar").addEventListener("click", function () {
  var dni = (document.getElementById("nr_dni").value || "").trim();
  if (!/^[0-9]{8}$/.test(dni)) {
    alert("Ingrese un DNI de 8 dígitos.");
    return;
  }
  var btn = $("#nr_buscar");
  btn.prop("disabled", true);
  $.ajax({
    dataType: "json",
    data: { dni: dni },
    url: URL_RENIEC,
    type: "post",
  })
    .done(function (r) {
      btn.prop("disabled", false);
      if (r && (r[1] || r[2])) {
        document.getElementById("nr_apellidos").value = (
          (r[1] || "") +
          " " +
          (r[2] || "")
        ).trim();
        document.getElementById("nr_nombres").value = (r[3] || "").trim();
      } else {
        alert("No se encontraron datos para ese DNI. Complete manualmente.");
      }
    })
    .fail(function () {
      btn.prop("disabled", false);
      alert("No se pudo consultar RENIEC. Complete los datos manualmente.");
    });
});

$("#nr_guardar").on("click", function () {
  var ape = (document.getElementById("nr_apellidos").value || "").trim(),
    nom = (document.getElementById("nr_nombres").value || "").trim(),
    dir = document.getElementById("nr_direccion").value,
    ema = (document.getElementById("nr_email").value || "").trim(),
    tel = document.getElementById("nr_telefono").value;
  if (ape === "") {
    alert("Ingrese los apellidos.");
    return;
  }
  if (nom === "") {
    alert("Ingrese los nombres.");
    return;
  }
  var btn = $("#nr_guardar");
  btn.prop("disabled", true);
  $.ajax({
    url: URL_REMIT,
    type: "post",
    data: {
      insertar: "-",
      apellidos: ape,
      nombres: nom,
      direccion: dir,
      email: ema,
      telefono: tel,
      _csrf: CSRF,
    },
  })
    .done(function (resp) {
      btn.prop("disabled", false);
      var id = parseInt(String(resp).replace(/[^0-9]/g, ""), 10);
      if (!id) {
        alert("No se pudo registrar el remitente: " + resp);
        return;
      }
      // Refresca la lista y selecciona al nuevo remitente
      cargarRemitentes(function () {
        document.getElementById("mr_remitente").value = ape + ", " + nom;
        document.getElementById("mr_idremitente").value = String(id);
      });
      cerrarNuevoRemit();
    })
    .fail(function (xhr) {
      btn.prop("disabled", false);
      alert("Error al registrar remitente: " + (xhr.responseText || ""));
    });
});

/* ====================== Exportar ====================== */
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
  } else {
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
