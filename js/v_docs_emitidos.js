var pageConfig = document.body ? document.body.dataset : {};
var CSRF = pageConfig.csrf || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

var URL_EMI = "../clases/docs_emitidos.php";
var modoDoc = "nuevo"; // 'nuevo' | 'editar'
var docCodigo = null;

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
function setEstado(v) {
  document.getElementById("estado").value = v;
  filtrar();
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
  "md_oficinas",
  "md_ofi_buscar",
  "md_ofi_tags",
  "Sin destino (opcional)...",
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
  S("d-destino", d.destino || "—");
  S("d-estado", d.estado);
  S("d-fecha", d.fecha);
  S("d-asunto", d.asunto);
  S("d-det", d.det);
  $(".row").removeClass("sel");
  $(this).addClass("sel");
});

/* ====================== Nuevo / Editar documento ====================== */
function abrirNuevo() {
  modoDoc = "nuevo";
  docCodigo = null;
  document.getElementById("md_titulo").textContent = "Nuevo documento";
  document.getElementById("md_btn_txt").textContent = "Emitir documento";
  document.getElementById("md_tipo").value = "";
  document.getElementById("md_numero").value = "";
  document.getElementById("md_asunto").value = "";
  document.getElementById("md_detalles").value = "";
  document.getElementById("md_fichero").value = "";
  limpiarOficinas(
    "md_oficinas",
    "md_ofi_buscar",
    "md_ofi_tags",
    "Sin destino (opcional)...",
    "md_ofi_msel",
  );
  document.getElementById("md_adj_wrap").style.display = "";
  document.getElementById("md_ofi_wrap").style.display = "";
  document.getElementById("modalDoc").classList.add("show");
}
function abrirEditar(el) {
  var row = el.closest(".row");
  if (!row) return;
  var d = row.dataset;
  if (d.editable !== "1") {
    alert("El documento ya fue recepcionado; no puede modificarse.");
    return;
  }
  modoDoc = "editar";
  docCodigo = String(d.id);
  docIdOfi = String(d.idofi || "");
  document.getElementById("md_titulo").textContent = "Editar documento";
  document.getElementById("md_btn_txt").textContent = "Guardar cambios";
  document.getElementById("md_tipo").value = String(d.iddoc || "");
  document.getElementById("md_numero").value = d.num || "";
  document.getElementById("md_asunto").value = d.asunto || "";
  document.getElementById("md_detalles").value = d.det || "";
  // El destino y el adjunto no se cambian al editar (mantiene el comportamiento del sistema)
  document.getElementById("md_adj_wrap").style.display = "none";
  document.getElementById("md_ofi_wrap").style.display = "none";
  document.getElementById("modalDoc").classList.add("show");
}
var docIdOfi = "";
function cerrarDoc() {
  document.getElementById("modalDoc").classList.remove("show");
}
document.getElementById("btnNuevoDoc").addEventListener("click", abrirNuevo);

$("#md_guardar").on("click", function () {
  var tipo = document.getElementById("md_tipo").value,
    numero = (document.getElementById("md_numero").value || "").trim(),
    asunto = (document.getElementById("md_asunto").value || "").trim(),
    det = document.getElementById("md_detalles").value;
  if (tipo === "") {
    alert("Seleccione el tipo de documento.");
    return;
  }
  if (numero === "") {
    alert("Ingrese el número del documento.");
    return;
  }
  if (asunto === "") {
    alert("Ingrese el asunto.");
    return;
  }
  var btn = $("#md_guardar");
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
  if (modoDoc === "nuevo") {
    var seleccion = oficinasMarcadas("md_oficinas");
    var fd = new FormData();
    fd.append("Insertar", "-");
    fd.append("Documento", tipo);
    fd.append("listaoficinas", seleccion.join(","));
    fd.append("Asunto", asunto);
    fd.append("Detalles", det);
    fd.append("NumDoc", numero);
    fd.append("_csrf", CSRF);
    var f = document.getElementById("md_fichero");
    if (f.files && f.files[0]) fd.append("fichero", f.files[0]);
    $.ajax({
      url: URL_EMI,
      type: "post",
      data: fd,
      processData: false,
      contentType: false,
    })
      .done(listo)
      .fail(fallo);
  } else {
    $.ajax({
      url: URL_EMI,
      type: "post",
      data: {
        Modificar: "-",
        Codigo: docCodigo,
        Documento: tipo,
        listaoficinas: docIdOfi !== "0" ? docIdOfi : "",
        Asunto: asunto,
        Detalles: det,
        NumDoc: numero,
        _csrf: CSRF,
      },
    })
      .done(listo)
      .fail(fallo);
  }
});

/* ====================== Eliminar ====================== */
function eliminarDoc(el) {
  var row = el.closest(".row");
  if (!row) return;
  var d = row.dataset;
  if (d.editable !== "1") {
    alert("El documento ya fue recepcionado; no puede eliminarse.");
    return;
  }
  if (
    !confirm(
      '¿Eliminar el documento "' +
        (d.doc || "") +
        '"? Esta acción no se puede deshacer.',
    )
  )
    return;
  $.ajax({
    url: URL_EMI,
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
