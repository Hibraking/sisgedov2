# SISGEDO — Revisión general del proyecto y propuestas de mejora

Revisión de código por flujos (login → mesa de partes → consulta → módulos internos).
Cada punto está anclado a archivos reales. Prioridad: 🔴 alta · 🟠 media · 🟢 baja.

---

## 1. Bugs confirmados

- 🔴 **Condición de carrera en el número de expediente.** `nro_expediente()` genera el número con `max(nro_exp)+1` (en `clases/mpvirtual.php` y `clases/reg_docs.php`). Si dos personas envían un documento **al mismo tiempo**, ambos pueden obtener el **mismo expediente**. Es un bug real de concurrencia. Solución: usar una columna AUTO_INCREMENT o una tabla de secuencia/transacción con bloqueo (`SELECT ... FOR UPDATE`), o un índice único en `nro_exp` + reintento.

- 🟠 **`&` (bit a bit) en vez de `&&`** en 9 archivos (`accion, archivador, documentos, estado_doc, menus, oficinas, personal, remitentes, responsables`): `if($_POST['Codigo']=='' & $_POST['Nombre']!='')`. Hoy funciona por la precedencia de operadores, pero es frágil e incorrecto de estilo. Cambiar a `&&`.

- 🟠 **Remitentes duplicados por diseño.** En la mesa de partes, si el email no coincide con uno existente, se **crea un remitente nuevo**. Con emails vacíos o distintos se acumulan duplicados (ya limpiamos ~66, pero la causa persiste). Mejor: buscar por **DNI/RUC** además de email, y no crear si ya existe.

- 🟢 **Login redirige a `login.html` en cualquier fallo** sin distinguir "usuario no existe" de "clave incorrecta" (correcto por seguridad), pero **no da ningún mensaje** al usuario. Conviene un mensaje genérico "usuario o contraseña incorrectos".

---

## 2. Validaciones faltantes (lo más importante que falta)

La regla general: **casi toda la validación es solo del lado cliente (JavaScript)**, que se puede saltar. Falta validación **en el servidor**.

- 🔴 **Mesa de partes (`clases/mpvirtual.php`) no valida nada en servidor**: `iddocumento`, `idtipo_tramite`, `folios`, `numero_docu`, `asunto`, email y DNI se insertan tal cual. Un envío directo (sin pasar por el formulario) puede meter datos vacíos o basura. Falta validar: campos obligatorios, `folios` entero positivo, email con formato, DNI 8 dígitos / RUC 11 dígitos, longitudes máximas.

- 🟠 **Handlers internos**: casi no usan `intval()`/filtros (solo 3 de ~15). Los prepared statements evitan inyección SQL, pero **no** validan tipo, rango ni obligatoriedad. Ej.: se puede guardar una oficina con nombre vacío, folios negativos, etc.

- 🟠 **Email**: no se valida formato en ningún lado del servidor (`filter_var($email, FILTER_VALIDATE_EMAIL)`).

- 🟠 **Archivos adjuntos**: ya validamos extensión/tamaño; falta validar el **MIME real** con `finfo` (por si renombran la extensión).

---

## 3. Seguridad — lo que aún conviene cerrar

- 🔴 **Credencial de producción en el código** (`conexion/conexion.php`). Debería salir a un archivo de configuración fuera de la carpeta web (o variables de entorno).
- 🟠 **Regenerar la sesión al iniciar sesión** (`session_regenerate_id(true)` en `login.php`) — evita "session fixation".
- 🟠 **Cookie `Secure`** cuando el sitio use HTTPS (producción).
- 🟠 **Rate limiting / bloqueo temporal** en el login tras N intentos fallidos (anti fuerza bruta).
- 🟠 **Cabeceras de seguridad**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`.
- 🟢 **Autorización de endpoints de operaciones**: dejamos sin mapear recepción/registro/emisión/archivar para no romper flujos; se pueden proteger con una lógica "tiene acceso a alguno de estos menús".
- 🟢 **Límites de subida** (`upload_max_filesize`, `post_max_size`) y **limpieza** de la carpeta de adjuntos (hoy ~4.7 GB).

---

## 4. Rendimiento y escalabilidad

- 🟠 **Las tablas cargan TODAS las filas del rango en el navegador** (DataTables del lado cliente). Con `movimientos` (79k) y `registro_documentos` (25k), rangos amplios se vuelven lentos. Recomendado: **paginación del lado servidor** (server-side processing).
- 🟠 **Faltan índices** en la BD en las columnas usadas para JOIN/WHERE (`idremitente`, `idtipo_tramite`, `iddocumento`, `idoficina`, `nro_exp`, `fecha_registro`, etc.). Con los volúmenes actuales, acelerarían mucho las consultas.
- 🟠 **Subconsultas correlacionadas** pesadas en `recepcion_proveido` (varios `SELECT` anidados por fila). Se pueden reescribir con JOINs.

---

## 5. Integridad de datos (base de datos)

- 🟠 **No hay claves foráneas (FK)**: nada impide documentos con `idremitente` inexistente, movimientos huérfanos, etc. Añadir FKs (con `ON DELETE`) daría integridad real.
- 🟠 **`nro_exp` es `varchar`** y se compara/incrementa como número. Debería ser numérico con **índice único**.
- 🟢 **Índice único** en catálogos (`tipo_tram.nombre`, `documentos.nombre_documento`, `oficinas.nombre`) para que no se vuelvan a duplicar.
- 🟢 **Charset `utf8mb3` → `utf8mb4`** en todas las tablas (tildes, ñ, emojis).

---

## 6. UX y consistencia

- 🟠 **Mensajes de error crudos** al usuario (`ERROR_ARCHIVO`, `ERROR_CSRF`, `ERROR_ACCESO`). Convertirlos en mensajes amables y consistentes.
- 🟠 **Feedback inconsistente tras guardar**: unos módulos muestran `alert()`, otros nada. Unificar (toast/confirmación).
- 🟢 **jQuery cargado dos veces** (2.1.4 y 1.11.3) en las vistas — quitar la duplicidad.
- 🟢 **Confirmación de eliminación** (`confirm()`): revisar que esté en todos los borrados.
- 🟢 **PHP 7.3 (sin soporte)** en producción → actualizar a 8.x.

---

## 7. Notas por flujo

**Login** — Ya: contraseñas hasheadas, sesión endurecida. Falta: regenerar sesión, rate limit, mensaje de error, bloqueo por intentos.

**Mesa de partes (público)** — Ya: subida segura, XSS de salida y de cliente. Falta: validación de servidor (obligatorios, email, DNI, folios), evitar remitentes duplicados, y arreglar la carrera del `nro_exp`.

**Consulta de expedientes (pública e interna)** — Ya: XSS de cliente escapado, búsqueda unificada interna/externa. Mejora: validar el número ingresado, y mensaje claro cuando no hay resultados.

**Módulos internos (mantenimiento/operaciones)** — Ya: CSRF, autorización (página + endpoint), XSS de salida. Falta: validación de servidor por campo, índices en BD, paginación server-side.

---

## Orden sugerido de implementación (seguro primero)

1. 🔴 Validación de servidor en la mesa de partes + arreglo del `nro_exp` (carrera).
2. 🟠 Regenerar sesión + mensaje de login + rate limit.
3. 🟠 Validación de servidor en catálogos + `&`→`&&`.
4. 🟠 Índices en la BD + índice único en catálogos + utf8mb4.
5. 🟠 Cabeceras de seguridad + credencial fuera del código.
6. 🟢 Paginación server-side, limpieza de jQuery duplicado, mensajes de error amables.
