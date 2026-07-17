import React, { useState } from "react";
import {
  Leaf,
  Search,
  Bell,
  HelpCircle,
  ChevronDown,
  ChevronUp,
  Home,
  LayoutGrid,
  List,
  Inbox,
  FolderClosed,
  Send,
  Share2,
  ClipboardList,
  FileBarChart,
  Settings,
  Wrench,
  Headphones,
  Filter,
  RefreshCw,
  Download,
  MoreVertical,
  CheckCircle2,
  Star,
  Calendar,
  X,
  Building2,
  Users,
  FileText,
  FileSpreadsheet,
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Sliders,
  MessageSquare,
  Clock,
  ChevronRight as ChevRight,
} from "lucide-react";

/* ============================ Datos de ejemplo ============================ */
const STATS = [
  {
    key: "pend",
    label: "Pendientes",
    value: "1,373",
    sub: "Por recepcionar",
    Icon: Inbox,
    tint: "blue",
  },
  {
    key: "recp",
    label: "Recepcionados",
    value: "86",
    sub: "Completados",
    Icon: CheckCircle2,
    tint: "green",
  },
  {
    key: "env",
    label: "Enviados",
    value: "61",
    sub: "Remitidos",
    Icon: Send,
    tint: "purple",
  },
  {
    key: "urg",
    label: "Urgentes",
    value: "18",
    sub: "Requieren atención",
    Icon: AlertTriangle,
    tint: "orange",
  },
];

const ROWS = [
  {
    id: 1,
    exp: "68994",
    selected: true,
    doc: "INFORME N° 034-2026-MPH/GDS/RPS-NIHA",
    docType: "INFORME",
    docColor: "blue",
    origin: "Gerencia de Desarrollo Social (e)",
    originType: "INTERNO",
    originIcon: Building2,
    asunto: "PEDIDO DE SERVICIO",
    asuntoTag: "NUEVO",
    status: "Pendiente",
    dot: "green",
    date: "03/07/2026",
    time: "02:45 PM",
    ago: "Hace 5 min",
  },
  {
    id: 2,
    exp: "20263304",
    doc: "OFICIO N°0156-2026-ANA-AAA-CHCH-ALA.P",
    docType: "OFICIO",
    docColor: "purple",
    origin: "MESA DE PARTES",
    originType: "EXTERNO",
    originIcon: Users,
    asunto: "SE REMITE 2 FICHAS TECNICAS REFERENCIALES DE PUNTOS",
    asuntoTag: "ATENCIÓN",
    status: "Urgente",
    dot: "orange",
    date: "03/07/2026",
    time: "01:21 PM",
    ago: "Hace 1 hora",
  },
  {
    id: 3,
    exp: "20263133",
    doc: "INFORME N°015-2026-MPH/GM",
    docType: "INFORME",
    docColor: "blue",
    origin: "Secretaría de Alcaldía - Mesa de Partes",
    originType: "INTERNO",
    originIcon: Building2,
    asunto:
      "INFORME DE CONSOLIDADO SOBRE LA VIABILIDAD TECNICA, PRESUPUESTAL Y LEGAL PARA EL COFINANCIAMIENTO DEL PROYECTO...",
    more: true,
    status: "En proceso",
    dot: "amber",
    date: "30/06/2026",
    time: "07:00 PM",
    ago: "Hace 1 día",
  },
  {
    id: 4,
    exp: "20263120",
    doc: "INFORME N°014-2026-MPH/GM",
    docType: "INFORME",
    docColor: "green",
    origin: "Secretaría de Alcaldía - Mesa de Partes",
    originType: "INTERNO",
    originIcon: Users,
    asunto:
      "INFORME CONSOLIDADO PARA LA PROCEDENCIA DE COFINANCIAMIENTO DEL PROYECTO...",
    more: true,
    status: "Recepcionado",
    dot: "blue",
    date: "30/06/2026",
    time: "06:07 PM",
    ago: "Hace 1 día",
  },
];

/* ============================ Utilidades de estilo ============================ */
const TINT = {
  blue: { wrap: "bg-blue-50", ic: "text-blue-600" },
  green: { wrap: "bg-green-50", ic: "text-green-600" },
  purple: { wrap: "bg-purple-50", ic: "text-purple-600" },
  orange: { wrap: "bg-orange-50", ic: "text-orange-500" },
};
const DOT = {
  green: "bg-green-500",
  orange: "bg-orange-500",
  amber: "bg-amber-400",
  blue: "bg-blue-500",
};
const DOC_BADGE = {
  blue: "bg-blue-50 text-blue-700",
  purple: "bg-purple-50 text-purple-700",
  green: "bg-green-50 text-green-700",
};

/* ============================ Componentes reutilizables ============================ */
function Badge({ children, className = "" }) {
  return (
    <span
      className={`inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold tracking-wide ${className}`}
    >
      {children}
    </span>
  );
}

function StatCard({ label, value, sub, Icon, tint }) {
  const t = TINT[tint];
  return (
    <div className="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div
        className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-full ${t.wrap}`}
      >
        <Icon className={`h-6 w-6 ${t.ic}`} />
      </div>
      <div>
        <div className="text-[13px] font-medium text-slate-500">{label}</div>
        <div
          className={`text-[26px] font-extrabold leading-tight ${tint === "orange" ? "text-orange-500" : "text-slate-800"}`}
        >
          {value}
        </div>
        <div className="text-[12px] text-slate-400">{sub}</div>
      </div>
    </div>
  );
}

function FieldLabel({ children }) {
  return (
    <label className="mb-1.5 block text-[12px] font-medium text-slate-500">
      {children}
    </label>
  );
}

function TextInput({ icon: Icon, placeholder }) {
  return (
    <div className="flex h-[42px] items-center gap-2 rounded-lg border border-slate-200 bg-white px-3">
      {Icon && <Icon className="h-4 w-4 text-slate-400" />}
      <input
        placeholder={placeholder}
        className="w-full bg-transparent text-[13px] text-slate-700 outline-none placeholder:text-slate-400"
      />
    </div>
  );
}

function DateInput({ value }) {
  return (
    <div className="flex h-[42px] items-center justify-between rounded-lg border border-slate-200 bg-white px-3">
      <span className="text-[13px] text-slate-600">{value}</span>
      <Calendar className="h-4 w-4 text-slate-400" />
    </div>
  );
}

function SelectInput({ value }) {
  return (
    <div className="flex h-[42px] items-center justify-between rounded-lg border border-slate-200 bg-white px-3">
      <span className="text-[13px] text-slate-600">{value}</span>
      <ChevronDown className="h-4 w-4 text-slate-400" />
    </div>
  );
}

function GhostBtn({ icon: Icon, children }) {
  return (
    <button className="flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 hover:bg-slate-50">
      {Icon && <Icon className="h-4 w-4 text-slate-500" />}
      {children}
      {children === "Exportar" || children === "Más acciones" ? null : null}
    </button>
  );
}

function SidebarItem({ icon: Icon, label, active, chevron }) {
  return (
    <button
      className={`group relative flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] font-medium ${
        active ? "bg-blue-50 text-blue-700" : "text-slate-600 hover:bg-slate-50"
      }`}
    >
      {active && (
        <span
          className="absolute left-0 top-1/2 h-6 -translate-y-1/2 rounded-r bg-blue-600"
          style={{ width: 3 }}
        />
      )}
      <Icon
        className={`h-[18px] w-[18px] ${active ? "text-blue-600" : "text-slate-400"}`}
      />
      <span className="flex-1 text-left">{label}</span>
      {chevron && <ChevronDown className="h-4 w-4 text-slate-400" />}
    </button>
  );
}

function StatusPill({ status, dot }) {
  return (
    <span className="inline-flex items-center gap-2 text-[13px] font-medium text-slate-700">
      <span className={`h-2 w-2 rounded-full ${DOT[dot]}`} />
      {status}
    </span>
  );
}

/* ============================ Vista principal ============================ */
export default function RecepcionDocumentos() {
  const [obsOpen, setObsOpen] = useState(false);
  const [histOpen, setHistOpen] = useState(false);

  return (
    <div className="flex h-screen flex-col bg-slate-50 font-sans text-slate-800">
      {/* ===================== HEADER ===================== */}
      <header className="flex h-14 shrink-0 items-center justify-between bg-gradient-to-r from-blue-600 to-blue-700 px-5 text-white">
        <div className="flex items-center gap-3">
          <button className="rounded p-1 hover:bg-white/10">
            <List className="h-5 w-5" />
          </button>
          <div className="flex items-center gap-2">
            <Leaf className="h-6 w-6 text-green-300" />
            <span className="text-lg font-bold tracking-wide">SISGEDO</span>
          </div>
          <span className="ml-3 hidden text-[13px] font-medium text-blue-100 md:block">
            Sistema de Gestión Documentario
          </span>
        </div>
        <div className="flex items-center gap-4">
          <button className="rounded-full p-1.5 hover:bg-white/10">
            <Search className="h-5 w-5" />
          </button>
          <button className="relative rounded-full p-1.5 hover:bg-white/10">
            <Bell className="h-5 w-5" />
            <span className="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-orange-500 text-[10px] font-bold">
              3
            </span>
          </button>
          <button className="rounded-full p-1.5 hover:bg-white/10">
            <HelpCircle className="h-5 w-5" />
          </button>
          <div className="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-white/10">
            <img
              src="https://i.pravatar.cc/64?img=12"
              alt=""
              className="h-8 w-8 rounded-full object-cover ring-2 ring-white/40"
            />
            <div className="leading-tight">
              <div className="text-[11px] text-blue-100">Bienvenido,</div>
              <div className="text-[13px] font-semibold">admin</div>
            </div>
            <ChevronDown className="h-4 w-4 text-blue-100" />
          </div>
        </div>
      </header>

      <div className="flex flex-1 overflow-hidden">
        {/* ===================== SIDEBAR ===================== */}
        <aside className="flex w-60 shrink-0 flex-col border-r border-slate-200 bg-white">
          <nav className="flex-1 space-y-1 overflow-y-auto p-3">
            <SidebarItem icon={Home} label="Inicio" />
            <SidebarItem icon={LayoutGrid} label="Tablero" />
            <SidebarItem icon={List} label="Operaciones" chevron />
            <div className="space-y-1 pl-3">
              <SidebarItem icon={Inbox} label="Recepción" active />
              <SidebarItem icon={FolderClosed} label="Archivo" />
              <SidebarItem icon={FolderClosed} label="Emisión" />
              <SidebarItem icon={Share2} label="Derivación" />
              <SidebarItem icon={ClipboardList} label="Registro" />
            </div>
            <SidebarItem icon={FileBarChart} label="Reportes" chevron />
            <SidebarItem icon={Settings} label="Configuración" chevron />
            <SidebarItem icon={List} label="Mantenimiento" chevron />
          </nav>
          <div className="p-3">
            <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50">
                <Headphones className="h-5 w-5 text-blue-600" />
              </div>
              <div className="flex-1 leading-tight">
                <div className="text-[13px] font-semibold text-slate-700">
                  Soporte
                </div>
                <div className="text-[11px] text-slate-400">
                  ¿Necesitas ayuda?
                </div>
              </div>
              <ChevRight className="h-4 w-4 text-slate-400" />
            </div>
          </div>
        </aside>

        {/* ===================== CONTENIDO ===================== */}
        <main className="flex-1 overflow-y-auto p-6">
          <h1 className="text-[22px] font-bold text-slate-800">
            Recepción de Documentos
          </h1>
          <p className="mt-1 text-[13px] text-slate-500">
            Administra los documentos recibidos por la entidad
          </p>

          {/* KPIs */}
          <div className="mt-5 grid grid-cols-4 gap-4">
            {STATS.map((s) => (
              <StatCard key={s.key} {...s} />
            ))}
          </div>

          {/* Filtros */}
          <div className="mt-5 rounded-xl border border-slate-200 bg-white p-4">
            <div className="grid grid-cols-12 gap-3">
              <div className="col-span-3">
                <div className="h-[18px]" />
                <TextInput
                  icon={Search}
                  placeholder="Buscar por expediente, documento, asunto..."
                />
              </div>
              <div className="col-span-2">
                <FieldLabel>Desde</FieldLabel>
                <DateInput value="01/06/2026" />
              </div>
              <div className="col-span-2">
                <FieldLabel>Hasta</FieldLabel>
                <DateInput value="26/06/2026" />
              </div>
              <div className="col-span-2">
                <FieldLabel>Tipo documento</FieldLabel>
                <SelectInput value="Todos" />
              </div>
              <div className="col-span-3 grid grid-cols-2 gap-3">
                <div>
                  <FieldLabel>Origen</FieldLabel>
                  <SelectInput value="Todos" />
                </div>
                <div>
                  <FieldLabel>Estado</FieldLabel>
                  <SelectInput value="Todos" />
                </div>
              </div>
            </div>
            <div className="mt-3 flex justify-end gap-2">
              <button className="flex h-[42px] items-center gap-2 rounded-lg bg-blue-600 px-5 text-[13px] font-semibold text-white hover:bg-blue-700">
                <Filter className="h-4 w-4" /> Filtrar
              </button>
              <button className="flex h-[42px] items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-[13px] font-medium text-slate-600 hover:bg-slate-50">
                <RefreshCw className="h-4 w-4" /> Limpiar
              </button>
            </div>
          </div>

          {/* Tabla */}
          <div className="mt-5 rounded-xl border border-slate-200 bg-white">
            {/* Toolbar */}
            <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <div className="flex items-center gap-3">
                <label className="flex items-center gap-2 text-[13px] text-slate-600">
                  <input
                    type="checkbox"
                    className="h-4 w-4 rounded border-slate-300 accent-blue-600"
                  />{" "}
                  Seleccionar todo
                </label>
                <button className="flex h-9 items-center gap-2 rounded-lg bg-blue-600 px-3 text-[13px] font-semibold text-white hover:bg-blue-700">
                  <CheckCircle2 className="h-4 w-4" /> Recepcionar seleccionados
                </button>
                <GhostBtn icon={Share2}>Derivar</GhostBtn>
                <GhostBtn icon={Download}>
                  Exportar <ChevronDown className="h-3.5 w-3.5" />
                </GhostBtn>
                <GhostBtn icon={MoreVertical}>Más acciones</GhostBtn>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-[13px] text-slate-500">Ordenar por:</span>
                <div className="flex h-9 w-40 items-center justify-between rounded-lg border border-slate-200 px-3 text-[13px] text-slate-600">
                  Más recientes{" "}
                  <ChevronDown className="h-4 w-4 text-slate-400" />
                </div>
                <button className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500">
                  <Sliders className="h-4 w-4" />
                </button>
              </div>
            </div>

            {/* Cabecera de tabla */}
            <div className="grid grid-cols-[36px_100px_1.5fr_1.4fr_1.6fr_120px_140px_70px] items-center gap-3 border-b border-slate-100 px-4 py-3 text-[12px] font-semibold uppercase tracking-wide text-slate-500">
              <span>
                <input
                  type="checkbox"
                  className="h-4 w-4 rounded border-slate-300 accent-blue-600"
                />
              </span>
              <span>Expediente</span>
              <span>Documento</span>
              <span>Origen</span>
              <span>Asunto</span>
              <span>Estado</span>
              <span>Fecha recepción</span>
              <span>Acciones</span>
            </div>

            {/* Filas */}
            {ROWS.map((r) => (
              <div
                key={r.id}
                className={`grid grid-cols-[36px_100px_1.5fr_1.4fr_1.6fr_120px_140px_70px] items-start gap-3 border-b border-slate-100 px-4 py-4 ${r.selected ? "bg-blue-50/40" : "hover:bg-slate-50"}`}
              >
                <span className="pt-0.5">
                  <input
                    type="checkbox"
                    defaultChecked={r.selected}
                    className="h-4 w-4 rounded border-slate-300 accent-blue-600"
                  />
                </span>
                <span className="flex items-center gap-1.5 pt-0.5">
                  <span className="text-[13px] font-semibold text-blue-600">
                    {r.exp}
                  </span>
                  <Star className="h-3.5 w-3.5 text-slate-300" />
                </span>
                <div className="min-w-0">
                  <div className="flex items-start gap-2">
                    <FileText
                      className={`mt-0.5 h-4 w-4 shrink-0 ${r.docColor === "purple" ? "text-purple-500" : r.docColor === "green" ? "text-green-500" : "text-blue-500"}`}
                    />
                    <span className="text-[13px] font-medium leading-snug text-slate-700">
                      {r.doc}
                    </span>
                  </div>
                  <Badge className={`mt-1.5 ${DOC_BADGE[r.docColor]}`}>
                    {r.docType}
                  </Badge>
                </div>
                <div className="min-w-0">
                  <div className="flex items-start gap-2">
                    <r.originIcon className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <span className="text-[13px] leading-snug text-slate-600">
                      {r.origin}
                    </span>
                  </div>
                  <Badge className="mt-1.5 bg-slate-100 text-slate-500">
                    {r.originType}
                  </Badge>
                </div>
                <div className="min-w-0">
                  <p className="text-[13px] leading-snug text-slate-600">
                    {r.asunto}
                  </p>
                  {r.more && (
                    <button className="mt-1 text-[12px] font-medium text-blue-600">
                      Ver más
                    </button>
                  )}
                  {r.asuntoTag && (
                    <Badge
                      className={`mt-1.5 ${r.asuntoTag === "NUEVO" ? "bg-blue-100 text-blue-700" : "bg-amber-100 text-amber-700"}`}
                    >
                      {r.asuntoTag}
                    </Badge>
                  )}
                </div>
                <div className="pt-0.5">
                  <StatusPill status={r.status} dot={r.dot} />
                </div>
                <div className="pt-0.5 text-[12px] leading-tight text-slate-600">
                  <div className="font-medium">{r.date}</div>
                  <div>{r.time}</div>
                  <div className="mt-0.5 text-slate-400">{r.ago}</div>
                </div>
                <div className="flex justify-center pt-0.5">
                  <button className="rounded p-1 hover:bg-slate-100">
                    <MoreVertical className="h-4 w-4 text-slate-400" />
                  </button>
                </div>
              </div>
            ))}

            {/* Paginación */}
            <div className="flex items-center justify-between px-4 py-4">
              <span className="text-[13px] text-slate-500">
                Mostrando 1 a 25 de 1,373 registros
              </span>
              <div className="flex items-center gap-4">
                <div className="flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-[13px] text-slate-600">
                  25 por página{" "}
                  <ChevronDown className="h-4 w-4 text-slate-400" />
                </div>
                <div className="flex items-center gap-1">
                  {[ChevronsLeft, ChevronLeft].map((I, i) => (
                    <button
                      key={i}
                      className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"
                    >
                      <I className="h-4 w-4" />
                    </button>
                  ))}
                  <button className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-[13px] font-semibold text-white">
                    1
                  </button>
                  {["2", "3", "...", "55"].map((n, i) => (
                    <button
                      key={i}
                      className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-[13px] text-slate-600 hover:bg-slate-50"
                    >
                      {n}
                    </button>
                  ))}
                  {[ChevronRight, ChevronsRight].map((I, i) => (
                    <button
                      key={i}
                      className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"
                    >
                      <I className="h-4 w-4" />
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </main>

        {/* ===================== PANEL DERECHO ===================== */}
        <aside className="flex w-[330px] shrink-0 flex-col border-l border-slate-200 bg-white">
          <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 className="text-[15px] font-bold text-slate-800">
              Detalles del documento
            </h2>
            <button className="rounded p-1 hover:bg-slate-100">
              <X className="h-4 w-4 text-slate-400" />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto px-5 py-4">
            {/* Documento */}
            <div className="flex items-start gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50">
                <FileText className="h-6 w-6 text-blue-600" />
              </div>
              <div className="min-w-0">
                <div className="text-[13px] font-semibold leading-snug text-slate-800">
                  INFORME N° 034-2026-MPH/GDS/RPS-NIHA
                </div>
                <Badge className="mt-1.5 bg-blue-50 text-blue-700">
                  INFORME
                </Badge>
              </div>
            </div>

            {/* Información general */}
            <div className="mt-5">
              <div className="text-[13px] font-semibold text-slate-700">
                Información general
              </div>
              <dl className="mt-3 space-y-3 text-[13px]">
                {[
                  ["Origen", "Gerencia de Desarrollo Social (e)"],
                  ["Fecha recepción", "03/07/2026  02:45 PM"],
                  ["Trámite", "INTERNO"],
                  ["N° Expediente", "68994"],
                ].map(([k, v]) => (
                  <div key={k}>
                    <dt className="text-[12px] text-slate-400">{k}</dt>
                    <dd className="mt-0.5 font-medium text-slate-700">{v}</dd>
                  </div>
                ))}
                <div>
                  <dt className="text-[12px] text-slate-400">Estado actual</dt>
                  <dd className="mt-1 inline-flex items-center gap-2 font-medium text-slate-700">
                    <span className="h-2 w-2 rounded-full bg-green-500" />{" "}
                    Pendiente de recepción
                  </dd>
                </div>
              </dl>
            </div>

            {/* Adjuntos */}
            <div className="mt-5">
              <div className="text-[13px] font-semibold text-slate-700">
                Archivos adjuntos (2)
              </div>
              <div className="mt-3 space-y-2">
                {[
                  {
                    name: "informe_034_2026.pdf",
                    size: "1.2 MB",
                    Icon: FileText,
                    color: "text-red-500",
                  },
                  {
                    name: "anexo_referencia.docx",
                    size: "850 KB",
                    Icon: FileSpreadsheet,
                    color: "text-blue-500",
                  },
                ].map((f) => (
                  <div
                    key={f.name}
                    className="flex items-center gap-3 rounded-lg border border-slate-200 p-2.5"
                  >
                    <f.Icon className={`h-5 w-5 ${f.color}`} />
                    <div className="flex-1 leading-tight">
                      <div className="text-[12.5px] font-medium text-slate-700">
                        {f.name}
                      </div>
                      <div className="text-[11px] text-slate-400">{f.size}</div>
                    </div>
                    <button className="rounded p-1 hover:bg-slate-100">
                      <Download className="h-4 w-4 text-slate-400" />
                    </button>
                  </div>
                ))}
              </div>
              <button className="mt-3 w-full rounded-lg border border-slate-200 py-2 text-[13px] font-medium text-blue-600 hover:bg-slate-50">
                Ver todos los archivos
              </button>
            </div>

            {/* Colapsables */}
            <div className="mt-5 divide-y divide-slate-100 border-y border-slate-100">
              {[
                {
                  key: "obs",
                  label: "Observaciones",
                  count: 1,
                  open: obsOpen,
                  set: setObsOpen,
                  Icon: MessageSquare,
                },
                {
                  key: "hist",
                  label: "Historial",
                  count: 3,
                  open: histOpen,
                  set: setHistOpen,
                  Icon: Clock,
                },
              ].map((c) => (
                <button
                  key={c.key}
                  onClick={() => c.set(!c.open)}
                  className="flex w-full items-center justify-between py-3.5"
                >
                  <span className="flex items-center gap-2 text-[13px] font-medium text-slate-700">
                    <c.Icon className="h-4 w-4 text-slate-400" /> {c.label}
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-500">
                      {c.count}
                    </span>
                  </span>
                  {c.open ? (
                    <ChevronUp className="h-4 w-4 text-slate-400" />
                  ) : (
                    <ChevronDown className="h-4 w-4 text-slate-400" />
                  )}
                </button>
              ))}
            </div>
          </div>

          {/* Acciones */}
          <div className="flex items-center gap-2 border-t border-slate-100 p-4">
            <button className="flex h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 text-[13px] font-semibold text-white hover:bg-blue-700">
              <CheckCircle2 className="h-4 w-4" /> Recepcionar
            </button>
            <button className="flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-[13px] font-medium text-slate-600 hover:bg-slate-50">
              <Share2 className="h-4 w-4" /> Derivar
            </button>
            <button className="flex h-10 items-center justify-center rounded-lg border border-slate-200 px-2 text-slate-500 hover:bg-slate-50">
              <MoreVertical className="h-4 w-4" />
            </button>
          </div>
        </aside>
      </div>
    </div>
  );
}
