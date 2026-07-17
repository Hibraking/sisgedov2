var pageConfig = document.body ? document.body.dataset : {};
var CSRF = pageConfig.csrf || "";
var IDOFICINA = pageConfig.idoficina || "";
$.ajaxSetup({ headers: { "X-CSRF-Token": CSRF } });

var URL_ARCH = "../clases/archivar_docs.php";
var URL_RECEP = "../clases/recepcion_proveido.php";
var URL_ARCHIVADOR = "../clases/archivador.php";

/* ====================== Filtros (URL limpia, mismo patrón que Recepción) ====================== */
function gotoFiltros(page) {
  var params = new URLSearchParams();
  var set = function (k, v, def) {
    v = v == null ? "" : String(v);
    if (v !== "" && v !== def) params.set(k, v);
  };
  set("q", (document.getElementById("buscador").value || "").trim(), "");
  set("desde", document.getElementById("desde").value, "");
  set("hasta", document.getElementById("hasta").value, "");
  set("bandeja", document.getElementById("bandeja").value, "porarchivar");
  set("archivador", document.getElementById("archivador").value, "0");
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
  document.getElementById("bandeja").value = "porarchivar";
  document.getElementById("archivador").value = "0";
  document.getElementById("sort").value = "recentes";
  document.getElementById("per_page").value = "25";
  gotoFiltros(1);
}
function setBandeja(v) {
  document.getElementById("bandeja").value = v;
  filtrar();
}
function verArchivador(id) {
  document.getElementById("bandeja").value = "archivados";
  document.getElementById("archivador").value = String(id);
  filtrar();
}
document.getElementById("buscador").addEventListener("keydown", function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    filtrar();
  }
});
["desde", "hasta", "bandeja", "archivador", "sort", "per_page"].forEach(
  function (id) {
    document.getElementById(id).addEventListener("change", filtrar);
  },
);

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

/* ====================== Panel de detalles ====================== */
$("#tbody").on("click", ".row", function () {
  var d = this.dataset,
    S = function (id, v) {
      var e = document.getElementById(id);
      if (e) e.textContent = v;
    };
  S("d-doc", d.doc);
  S("d-eti", d.eti);
  S("d-fecha", d.fecha);
  S("d-asunto", d.asunto);
  S("d-det", d.det);
  var b = document.getElementById("d-badge");
  if (b) {
    b.textContent = d.clase;
  }
  $(".row").removeClass("sel");
  $(this).addClass("sel");
});

/* ====================== Archivar ====================== */
var arItem = null,
  estadosCargados = false;
function itemDesdeFila(el) {
  var row = el.closest(".row");
  if (!row) return null;
  var d = row.dataset;
  return { id: String(d.id), clase: String(d.clase), doc: d.doc || "" };
}
function cargarEstados() {
  if (estadosCargados) return;
  estadosCargados = true;
  $.ajax({
    dataType: "json",
    data: { carga_estados: "-" },
    url: URL_RECEP,
    type: "post",
    success: function (r) {
      var s = document.getElementById("ar_estado");
      (r || []).forEach(function (e) {
        s.appendChild(new Option(e[1], e[0]));
      });
      for (var i = 0; i < s.options.length; i++) {
        if (/ARCHIV/i.test(s.options[i].text)) {
          s.value = s.options[i].value;
          break;
        }
      }
    },
  });
}
function abrirArchivar(el) {
  var item = itemDesdeFila(el);
  if (!item) {
    alert("No se pudo identificar el documento.");
    return;
  }
  arItem = item;
  // El estado solo aplica a documentos tramitados (actualiza el movimiento)
  document.getElementById("ar_estado_wrap").style.display =
    item.clase === "TRAMITADO" ? "" : "none";
  if (item.clase === "TRAMITADO") cargarEstados();
  document.getElementById("ar_info").textContent = item.doc;
  document.getElementById("ar_detalles").value = "";
  document.getElementById("modalArch").classList.add("show");
}
function cerrarArchivar() {
  document.getElementById("modalArch").classList.remove("show");
}

$("#ar_guardar").on("click", function () {
  var idarch = document.getElementById("ar_archivador").value,
    det = document.getElementById("ar_detalles").value,
    idest = document.getElementById("ar_estado").value;
  if (idarch === "") {
    alert(
      'Seleccione un archivador. Si no existe ninguno, créelo con "Nuevo archivador".',
    );
    return;
  }
  if (!arItem) {
    alert("No hay documento para archivar.");
    return;
  }
  if (arItem.clase === "TRAMITADO" && idest === "") {
    alert("Seleccione el estado.");
    return;
  }
  var btn = $("#ar_guardar");
  btn.prop("disabled", true);
  var peticion;
  if (arItem.clase === "TRAMITADO") {
    // Reutiliza el endpoint de recepción: archiva Y actualiza el estado del movimiento
    peticion = $.ajax({
      data: {
        insert_archivar: "-",
        idarchivador: idarch,
        idmovimiento: arItem.id,
        detalles: det,
        idestadodoc: idest,
        _csrf: CSRF,
      },
      url: URL_RECEP,
      type: "post",
    });
  } else {
    // Documento emitido propio: endpoint clásico de archivo
    peticion = $.ajax({
      data: {
        insertar: "-",
        idarchivador: idarch,
        iddocs_emitidos: arItem.id,
        idmovimientos: 0,
        detalles: det,
        _csrf: CSRF,
      },
      url: URL_ARCH,
      type: "post",
    });
  }
  peticion
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

/* ====================== Nuevo archivador ====================== */
document.getElementById("btnNuevoArch").addEventListener("click", function () {
  document.getElementById("na_nombre").value = "";
  document.getElementById("na_detalles").value = "";
  document.getElementById("modalNuevoArch").classList.add("show");
});
function cerrarNuevoArch() {
  document.getElementById("modalNuevoArch").classList.remove("show");
}

$("#na_guardar").on("click", function () {
  var nombre = (document.getElementById("na_nombre").value || "").trim(),
    anio = (document.getElementById("na_anio").value || "").trim(),
    det = document.getElementById("na_detalles").value;
  if (nombre === "") {
    alert("Ingrese el nombre del archivador.");
    return;
  }
  if (anio === "" || parseInt(anio, 10) < 2000) {
    alert("Ingrese un año válido.");
    return;
  }
  var btn = $("#na_guardar");
  btn.prop("disabled", true);
  $.ajax({
    data: {
      Codigo: "",
      Nombre: nombre,
      Detalles: det,
      anio: anio,
      _csrf: CSRF,
    },
    url: URL_ARCHIVADOR,
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
      alert("Error al crear el archivador: " + (xhr.responseText || ""));
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
