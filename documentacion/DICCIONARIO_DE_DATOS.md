# SISGEDO — Diccionario de datos

Base de datos: `sisgedov2` (producción: `xyltrylx_sisgedov2`) · MySQL 8 / MariaDB · juego de caracteres utf8mb4.

Generado a partir de la estructura de la base de datos (incluye la migración 001 del Modelo de Gestión Documental) el 10/11/2026.

Las tablas con prefijo `sgd_` se agregaron para la adecuación al Modelo de Gestión Documental (RSGD N° 001-2017-PCM/SEGDI).

## Índice de tablas

| Tabla | Descripción |
|---|---|
| [`accesos_usuario`](#accesos_usuario) | Permisos de cada usuario (personal) sobre cada opción del menú. |
| [`aciones_documento`](#aciones_documento) | Catálogo de acciones que se solicitan al derivar un documento (Firma, V° B°, Atención, etc.). |
| [`archivador`](#archivador) | Archivadores físicos/lógicos de cada oficina por año (archivo de gestión). |
| [`docs_archivados`](#docs_archivados) | Documentos (emitidos o recibidos) archivados en un archivador. |
| [`docs_emitidos`](#docs_emitidos) | Documentos emitidos por las oficinas (una fila por cada oficina destino del mismo envío). |
| [`documentos`](#documentos) | Catálogo de tipos documentales (Oficio, Informe, Memorándum, etc.) y plazo de atención. |
| [`estado_documento`](#estado_documento) | Catálogo de estados del trámite (Recepcionado, Derivado, Atendido, Archivado, etc.). |
| [`menu`](#menu) | Opciones del menú del sistema y su ruta. |
| [`modulo`](#modulo) | Módulos del sistema. |
| [`movimientos`](#movimientos) | Trazabilidad: cada recepción, derivación, respuesta, devolución o archivo de un documento. |
| [`oficinas`](#oficinas) | Unidades orgánicas de la entidad. |
| [`personal`](#personal) | Usuarios del sistema (servidores de la entidad). |
| [`registro_documentos`](#registro_documentos) | Documentos externos recibidos por mesa de partes presencial o virtual (expedientes). Una fila por oficina destino. |
| [`remitente`](#remitente) | Ciudadanos o entidades que presentan documentos. |
| [`responsables`](#responsables) | Asignación de personal a una oficina (responsable de recibir/emitir en esa oficina). |
| [`tipo_tram`](#tipo_tram) | Catálogo de tipos de trámite / procedimientos (TUPA). |
| [`usuario`](#usuario) | Tabla heredada de usuarios (no usada por el flujo actual; el acceso usa la tabla personal). |
| [`sgd_archivo`](#sgd_archivo) | Metadatos e integridad (huella SHA-256) de cada archivo adjunto (MGD). |
| [`sgd_firma`](#sgd_firma) | Solicitudes y registros de firma digital / visto bueno con FIRMA PERÚ (Ley N° 27269). |
| [`sgd_secuencia`](#sgd_secuencia) | Contadores atómicos de numeración (MGD): expediente, registro interno por oficina y correlativo de documentos emitidos. |
| [`sgd_verificacion`](#sgd_verificacion) | Código de Verificación Digital (CVD) de expedientes y documentos emitidos, usado en el QR de la representación impresa (MGD). |

## accesos_usuario

Permisos de cada usuario (personal) sobre cada opción del menú.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idaccesos_usuario` | int(11) | No | PK |  |  |
| `idmenu` | int(11) | Sí | Índice | NULL | FK → `menu.idmenu`. |
| `idusuario` | int(11) | Sí | Índice | NULL | FK → `personal.idpersonal`. |
| `access` | tinyint(4) | Sí |  | NULL |  |

## aciones_documento

Catálogo de acciones que se solicitan al derivar un documento (Firma, V° B°, Atención, etc.).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idaciones_documento` | int(11) | No | PK |  |  |
| `nombre_accion` | varchar(45) | Sí |  | NULL |  |
| `descripcion` | varchar(45) | Sí |  | NULL |  |

## archivador

Archivadores físicos/lógicos de cada oficina por año (archivo de gestión).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idarchivador` | int(11) | No | PK |  |  |
| `idoficina` | int(11) | No | Índice |  | FK → `oficinas.idoficinas`. |
| `nombre` | varchar(100) | Sí |  | NULL |  |
| `detalles` | varchar(100) | Sí |  | NULL |  |
| `anio` | year(4) | Sí |  | NULL |  |

## docs_archivados

Documentos (emitidos o recibidos) archivados en un archivador.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `iddocs_archivados` | int(11) | No | PK |  |  |
| `idarchivador` | int(11) | Sí | Índice | NULL | FK → `archivador.idarchivador`. |
| `iddocs_emitidos` | int(11) | Sí | Índice | NULL | FK → `docs_emitidos.iddocs_emitidos`. |
| `idmovimientos` | int(11) | Sí | Índice | NULL | FK → `movimientos.idMovimientos`. |
| `detalles` | varchar(100) | Sí |  | NULL |  |
| `fecha_hora` | datetime | Sí |  | current_timestamp() |  |

## docs_emitidos

Documentos emitidos por las oficinas (una fila por cada oficina destino del mismo envío).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `iddocs_emitidos` | int(11) | No | PK |  |  |
| `iddocumento` | int(11) | No | Índice |  | FK → `documentos.iddocumentos`. |
| `idresponsable` | int(11) | Sí | Índice | NULL | FK → `responsables.idresponsables`. |
| `idoficina` | int(11) | Sí |  | NULL | Oficina destino (NULL = sin destino / documento de respuesta). |
| `asunto` | varchar(50) | Sí |  | NULL |  |
| `detalles` | varchar(500) | Sí |  | NULL |  |
| `numero` | varchar(45) | Sí |  | NULL | Número del documento (ej. 045-2026-MPH/GM). Automático por oficina/tipo/año o manual. |
| `adjunto` | varchar(255) | Sí | Índice | NULL | Nombre del archivo en la carpeta "docs adjs docs_emitidos". |
| `fecha` | datetime | Sí |  | current_timestamp() |  |
| `estado` | tinyint(4) | Sí |  | 0 | 0 = por recepcionar, 1 = recepcionado por el destino. |
| `idoficina_registro` | int(11) | Sí | Índice | NULL | Oficina emisora (la asigna un trigger según el responsable). |

## documentos

Catálogo de tipos documentales (Oficio, Informe, Memorándum, etc.) y plazo de atención.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `iddocumentos` | int(11) | No | PK |  |  |
| `nombre_documento` | varchar(50) | Sí |  | NULL |  |
| `descripcion` | varchar(45) | Sí |  | NULL |  |
| `dias_atension` | int(11) | Sí |  | NULL | Plazo de atención en días. |

## estado_documento

Catálogo de estados del trámite (Recepcionado, Derivado, Atendido, Archivado, etc.).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idestado_documento` | int(11) | No | PK |  |  |
| `nombre` | varchar(45) | Sí |  | NULL |  |
| `descripcion` | varchar(150) | Sí |  | NULL |  |

## menu

Opciones del menú del sistema y su ruta.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idmenu` | int(11) | No | PK |  |  |
| `idmodulo` | int(11) | Sí | Índice | NULL | FK → `modulo.idmodulo`. |
| `nombre_menu` | varchar(45) | Sí |  | NULL |  |
| `direccion` | varchar(100) | Sí |  | NULL |  |
| `clase_llamar` | varchar(45) | Sí |  | NULL |  |
| `dependencia` | int(11) | Sí |  | NULL |  |
| `font_icon` | varchar(45) | Sí |  | NULL |  |

## modulo

Módulos del sistema.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idmodulo` | int(11) | No | PK |  |  |
| `nombre` | varchar(45) | Sí |  | NULL |  |

## movimientos

Trazabilidad: cada recepción, derivación, respuesta, devolución o archivo de un documento.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idMovimientos` | int(11) | No | PK |  |  |
| `idregdocs` | int(11) | Sí | Índice | NULL | Documento externo movido (FK registro_documentos). FK → `registro_documentos.idregistro_documentos`. |
| `iddocs_emitidos` | int(11) | Sí | Índice | NULL | Documento interno movido (FK docs_emitidos). FK → `docs_emitidos.iddocs_emitidos`. |
| `idacciondocs` | int(11) | Sí | Índice | NULL | Acción solicitada (FK aciones_documento). FK → `aciones_documento.idaciones_documento`. |
| `idoficina_derivar` | int(11) | Sí | Índice | NULL | Oficina a la que se deriva. FK → `oficinas.idoficinas`. |
| `idestadodocs` | int(11) | Sí | Índice | 1 | Estado del trámite tras el movimiento. FK → `estado_documento.idestado_documento`. |
| `idresponsable` | int(11) | Sí | Índice | NULL | FK → `responsables.idresponsables`. |
| `num_exp` | varchar(15) | Sí | Índice | NULL | Número de registro interno de la oficina que recibe (año + correlativo por oficina). |
| `detalles` | varchar(500) | Sí |  | NULL |  |
| `fecha_hora` | datetime | Sí |  | current_timestamp() |  |
| `estado` | tinyint(4) | Sí |  | 0 | 0 = pendiente de recepción por el destino; 1 = recibido. |
| `iddocrespuesta` | int(11) | Sí |  | NULL | Documento emitido como respuesta (FK docs_emitidos). |
| `idmovimientoant` | int(11) | Sí |  | NULL | Movimiento anterior (cadena de trazabilidad). |
| `detalle_derivado` | varchar(500) | Sí |  | NULL |  |
| `folio` | int(11) | Sí |  | NULL |  |
| `t_a` | int(11) | Sí |  | 1 |  |
| `comodin` | varchar(45) | Sí |  | NULL |  |

## oficinas

Unidades orgánicas de la entidad.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idoficinas` | int(11) | No | PK |  |  |
| `nombre` | varchar(100) | Sí |  | NULL |  |
| `descripcion` | varchar(45) | Sí |  | NULL |  |
| `siglas` | varchar(60) | Sí |  | NULL | Nomenclatura para la numeración automática (ej. MPH/GM). |

## personal

Usuarios del sistema (servidores de la entidad).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idpersonal` | int(11) | No | PK |  |  |
| `apellidos` | varchar(45) | Sí |  | NULL |  |
| `nombres` | varchar(45) | Sí |  | NULL |  |
| `direccion` | varchar(105) | Sí |  | NULL |  |
| `email` | varchar(45) | Sí |  | NULL |  |
| `telefono` | varchar(45) | Sí |  | NULL |  |
| `imagen` | varchar(255) | Sí |  | NULL |  |
| `clave` | varchar(200) | Sí |  | NULL | Contraseña cifrada (hash). |
| `usuario` | varchar(20) | Sí |  | NULL | Nombre de usuario para iniciar sesión. |
| `ultimo_acceso` | datetime | Sí |  | NULL |  |

## registro_documentos

Documentos externos recibidos por mesa de partes presencial o virtual (expedientes). Una fila por oficina destino.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idregistro_documentos` | int(11) | No | PK |  |  |
| `iddocumento` | int(11) | Sí | Índice | NULL | FK → `documentos.iddocumentos`. |
| `idremitente` | int(11) | Sí | Índice | NULL | FK → `remitente.idremitente`. |
| `idtipo_tramite` | int(11) | Sí | Índice | NULL | FK → `tipo_tram.idtipo_tram`. |
| `idoficina` | int(11) | Sí | Índice | NULL | Oficina destino (FK oficinas). FK → `oficinas.idoficinas`. |
| `nro_exp` | varchar(45) | Sí | Índice | NULL | Número de expediente: año + correlativo (ej. 20260001). Generado por sgd_nuevo_expediente(). |
| `numero_docu` | varchar(45) | Sí |  | NULL | Número del documento presentado por el ciudadano. |
| `asunto` | varchar(500) | Sí |  | NULL |  |
| `parasu` | varchar(80) | Sí |  | NULL | Indicación "para su" (atención, conocimiento, etc.). |
| `observacion` | varchar(200) | Sí |  | NULL |  |
| `fecha_registro` | datetime | Sí |  | current_timestamp() |  |
| `folios` | int(11) | Sí |  | NULL | Cantidad de folios. |
| `adjunto` | varchar(255) | Sí | Índice | NULL | Nombre del archivo en la carpeta "docs adjs reg_doc". |
| `estado_recepcion` | tinyint(4) | Sí |  | 0 | 1 = la oficina destino ya lo recibió (lo actualiza un trigger de movimientos). |
| `idpersonal` | int(11) | Sí |  | NULL | Usuario que registró en mesa de partes (NULL si fue por la Mesa de Partes Virtual). |
| `lugar_procedencia` | varchar(100) | Sí |  | NULL |  |
| `recepcion_web` | tinyint(4) | Sí |  | 0 | 1 = recibido por Mesa de Partes Virtual; 0 = presencial. |

## remitente

Ciudadanos o entidades que presentan documentos.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idremitente` | int(11) | No | PK |  |  |
| `d_identidad` | varchar(15) | Sí |  | NULL | DNI o RUC. |
| `apellidos` | varchar(45) | Sí |  | NULL |  |
| `nombres` | varchar(45) | Sí |  | NULL |  |
| `direccion` | varchar(250) | Sí |  | NULL |  |
| `email` | varchar(45) | Sí |  | NULL |  |
| `telefono` | varchar(45) | Sí |  | NULL |  |

## responsables

Asignación de personal a una oficina (responsable de recibir/emitir en esa oficina).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idresponsables` | int(11) | No | PK |  |  |
| `idpersonal` | int(11) | Sí | Índice | NULL | FK → `personal.idpersonal`. |
| `idoficina` | int(11) | Sí | Índice | NULL | FK → `oficinas.idoficinas`. |
| `estado` | tinyint(4) | Sí |  | 1 | 1 = activo. |

## tipo_tram

Catálogo de tipos de trámite / procedimientos (TUPA).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idtipo_tram` | int(11) | No | PK |  |  |
| `nombre` | varchar(255) | Sí |  | NULL |  |
| `descripcion` | varchar(45) | Sí |  | NULL |  |

## usuario

Tabla heredada de usuarios (no usada por el flujo actual; el acceso usa la tabla personal).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idUsuario` | varchar(15) | No | PK |  |  |
| `dni` | varchar(45) | No |  |  |  |
| `apellidos` | varchar(45) | No |  |  |  |
| `nombres` | varchar(45) | No |  |  |  |
| `telefono` | varchar(20) | No |  |  |  |
| `direccion` | varchar(100) | No |  |  |  |
| `imagen` | longblob | Sí |  | NULL |  |
| `contrasena` | varchar(1500) | Sí |  | NULL |  |

## sgd_archivo

Metadatos e integridad (huella SHA-256) de cada archivo adjunto (MGD).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idarchivo` | int(10) unsigned | No | PK |  |  |
| `origen` | enum('REGISTRO','EMITIDO') | No | Índice |  | REGISTRO = docs adjs reg_doc, EMITIDO = docs adjs docs_emitidos |
| `nombre_almacenado` | varchar(255) | No |  |  | Nombre del archivo en disco (= columna adjunto) |
| `nombre_original` | varchar(255) | Sí |  | NULL |  |
| `extension` | varchar(10) | Sí |  | NULL |  |
| `mime` | varchar(100) | Sí |  | NULL |  |
| `tamano_bytes` | bigint(20) unsigned | Sí |  | NULL |  |
| `hash_sha256` | char(64) | No | Índice |  |  |
| `canal` | enum('PRESENCIAL','VIRTUAL','INTERNO') | No |  | 'INTERNO' |  |
| `idpersonal` | int(11) | Sí |  | NULL | Usuario que cargÃ³ el archivo (NULL = ciudadano por MPV) |
| `ip` | varchar(45) | Sí |  | NULL |  |
| `fecha_carga` | datetime | No |  | current_timestamp() |  |

## sgd_firma

Solicitudes y registros de firma digital / visto bueno con FIRMA PERÚ (Ley N° 27269).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `idfirma` | int(10) unsigned | No | PK |  |  |
| `iddocs_emitidos` | int(11) | No | Índice |  |  |
| `idpersonal` | int(11) | Sí |  | NULL | Usuario que iniciÃ³ la firma |
| `tipo_firma` | enum('FIRMA','VISTO_BUENO') | No |  | 'FIRMA' |  |
| `param_token` | char(64) | No | Única |  | Token de un solo uso para el firmador |
| `token_expira` | datetime | No |  |  |  |
| `archivo_origen` | varchar(255) | Sí |  | NULL | Adjunto antes de firmar |
| `archivo_firmado` | varchar(255) | Sí |  | NULL | Adjunto firmado (nuevo archivo) |
| `hash_sha256` | char(64) | Sí |  | NULL | Huella del archivo firmado |
| `creado` | datetime | No |  | current_timestamp() |  |
| `fecha_firma` | datetime | Sí |  | NULL |  |
| `estado` | enum('PENDIENTE','FIRMADO','CANCELADO','ERROR') | No |  | 'PENDIENTE' |  |
| `detalle` | varchar(500) | Sí |  | NULL |  |

## sgd_secuencia

Contadores atómicos de numeración (MGD): expediente, registro interno por oficina y correlativo de documentos emitidos.

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `clave` | varchar(80) | No | PK |  |  |
| `ultimo` | int(10) unsigned | No |  | 0 |  |
| `actualizado` | datetime | No |  | current_timestamp() |  |

## sgd_verificacion

Código de Verificación Digital (CVD) de expedientes y documentos emitidos, usado en el QR de la representación impresa (MGD).

| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |
|---|---|---|---|---|---|
| `cvd` | char(8) | No | PK |  |  |
| `tipo` | enum('EXPEDIENTE','EMITIDO') | No | Índice |  |  |
| `referencia` | varchar(45) | No |  |  |  |
| `creado` | datetime | No |  | current_timestamp() |  |

## Disparadores (triggers)

| Disparador | Tabla | Momento | Función |
|---|---|---|---|
| `docs_emitidos_BEFORE_INSERT` | docs_emitidos | BEFORE INSERT | Asigna la oficina emisora (idoficina_registro) según el responsable; estado 0 si tiene destino. |
| `docs_emitidos_BEFORE_UPDATE` | docs_emitidos | BEFORE UPDATE | Recalcula la oficina emisora según el responsable. |
| `movimientos_AFTER_INSERT` | movimientos | AFTER INSERT | Marca el documento (externo o interno) como recibido. |
| `movimientos_BEFORE_DELETE` | movimientos | BEFORE DELETE | Revierte la marca de recibido al eliminar el movimiento. |
| `movimientos_BEFORE_INSERT` | movimientos | BEFORE INSERT | Fija la fecha/hora del movimiento y la oficina por defecto. |
| `movimientos_BEFORE_UPDATE` | movimientos | BEFORE UPDATE | Completa la oficina destino por defecto. |
