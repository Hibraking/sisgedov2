# SISGEDO — Mapa de flujos del sistema

Referencia de qué ocurre en cada paso (pantalla → servidor → base de datos).
Actualizado: 13/11/2026.

## Estructura de carpetas

| Carpeta | Contenido |
|---|---|
| raíz | Páginas públicas: `login.php`, `mpvirtual.php`, `consulta_publico.php`, `subsanar.php`, `verificar.php`; `index.php` (inicio) |
| `vistas/` | Pantallas internas (una por módulo) |
| `clases/` | Lógica y acciones del servidor (una por módulo) + `seguridad.php`, `guardia.php`, `correo.php`, `calendario.php`… |
| `partes/` | Piezas compartidas: menú lateral (`nav_nuevo.php`), plantilla de catálogos |
| `js/`, `css/` | Un archivo por pantalla + `sisgedo-theme.css` y `v_recepcion_proveido.css` (comunes) |
| `reportes/` | Consulta interna y reportes |
| `conexion/` | Configuración: base de datos, institución, correo, Firma Perú (contraseñas en archivos fuera de Git) |
| `migraciones/` | Cambios de la base de datos; `ACTUALIZAR_BD_COMPLETO.sql` los reúne todos |
| `assets/` | Solo librerías externas en uso: Font Awesome 4.5 y jQuery 2.1.4 |
| `tcpdf/` | Librería de PDF reducida a lo usado (fuentes helvetica/times/courier) + `examples/` con los cargos y reportes propios |
| `librerias/`, `vendor/` | PHPMailer (correo) |
| `images/` | Logos (original y versiones web/correo), ilustración del login, fotos de perfil |
| `herramientas/` | Utilidades para el administrador (probar correo, huellas de archivos) |
| `docs adjs …/` | Archivos adjuntos subidos (no van a Git) |
| `bd/`, `logs/`, `_cuarentena_limpieza/` | Solo en la PC local (no van a Git ni al hosting) |

## Los tres tipos de documento

| Tipo | Dónde nace | Tabla | Dónde se atiende |
|---|---|---|---|
| **Externo presencial** | Ventanilla: *Operaciones > Registro* | `registro_documentos` (`recepcion_web = 0`) | Recepción de la oficina destino |
| **Externo virtual** | Ciudadano: `mpvirtual.php` | `registro_documentos` (`recepcion_web = 1`) | *Operaciones > Mesa de Partes Virtual* → luego Recepción de la oficina |
| **Interno (emitido)** | Oficina: *Operaciones > Emisión* | `docs_emitidos` (una fila por oficina destino) | Recepción de la oficina destino |

Cada recepción, derivación o respuesta es una fila en `movimientos`
(`idmovimientoant` = movimiento anterior; `estado` 0 = por recibir, 1 = recibido).

## 1. Inicio de sesión
`login.php` → `clases/login.php`
1. Bloqueo por intentos fallidos (por IP).
2. Busca al usuario y su cargo (`responsables`). **Si tiene varios, usa el activo.**
3. Verifica la clave (bcrypt; las claves antiguas en texto plano se cifran al entrar).
4. Sesión: `usuario` (idpersonal), `alias`, `idoficina`, `idresponsable`; registra `ultimo_acceso`.
5. Cada página pasa por `clases/guardia.php` (sesión + permiso del menú en `accesos_usuario`).
   Las acciones (POST) exigen token CSRF.

## 2. Mesa de Partes Virtual (ciudadano)
`mpvirtual.php` → `clases/mpvirtual.php`
1. Datos personales + documento (PDF ≤ 15 MB, se guarda su huella SHA-256).
2. Crea el remitente (o reutiliza el que tiene el mismo nombre) y el registro con nº de expediente.
3. Correo **constancia** al ciudadano (si envió fuera de horario, indica desde qué fecha se considera presentado).
4. Después de responder: correo **aviso a mesadepartes@** con el documento adjunto.

## 3. Mesa de Partes Virtual (operador) — *Operaciones > Mesa de Partes Virtual*
`vistas/v_mesa_partes_virtual.php` → `clases/mesa_partes_virtual.php`
- **Por recepcionar** → *Recepcionar* (correo al ciudadano) u *Observar* (motivo + plazo de 2 días hábiles + enlace para subsanar).
- **Observados** → el ciudadano subsana en `subsanar.php` (vuelve a *Por recepcionar* como SUBSANADO); vencido el plazo: *No presentado* (correo).
- **Por derivar** → *Derivar*: asigna la oficina; el documento aparece en su Recepción.
- **Historial** → derivados (y si la oficina ya lo recibió) y no presentados.
- Horario y feriados: `conexion/institucion.php` + tabla `sgd_dia_no_laborable`.

## 4. Registro presencial — *Operaciones > Registro*
`vistas/o_reg_documentos.php` → `clases/reg_docs.php`
- Solo documentos de ventanilla. Un registro por cada oficina destino (mismo expediente).
- Editar/eliminar solo mientras ninguna oficina lo haya recibido. Cargo PDF para el ciudadano.

## 5. Recepción — *Operaciones > Recepción*
`vistas/v_recepcion_proveido.php` → `clases/recepcion_proveido.php`
- **Por recepcionar** reúne: registros externos con destino a mi oficina (tipo 1), emitidos a mi oficina (tipo 2) y derivaciones pendientes (tipo 3).
- *Recepcionar* (tipo 1/2 → `insertar`; tipo 3 → `recibir_derivado`). **No se puede recepcionar dos veces.**
- *Derivar / Responder / Devolver* (`insert_deriv_resp_dev`): crea el movimiento hacia la otra oficina y cierra el actual, **en una sola operación**. No se puede enviar dos veces a la misma oficina mientras siga sin recibirse.
- *Archivar* (`insert_archivar`): **no se puede archivar dos veces**.
- Triggers de la BD: al insertar un movimiento marcan el documento como recibido; al borrar el **último** movimiento lo vuelven a pendiente.

## 6. Emisión — *Operaciones > Emisión*
`vistas/v_docs_emitidos.php` → `clases/docs_emitidos.php`
- Una fila por oficina destino; numeración automática (requiere siglas de la oficina) o manual (sin repetir).
- Firma digital / V°B° (FIRMA PERÚ) cuando existan las credenciales.

## 7. Archivo — *Operaciones > Archivo*
`vistas/v_archivar_docs.php` → `clases/archivar_docs.php` (emitidos propios) y `recepcion_proveido.php` (tramitados).

## 8. Supervisión del administrador
`vistas/v_supervision.php` + selector del menú lateral → `clases/vista_oficina.php`
- **Supervisión** (solo usuario 1): pendientes por oficina, más de 30 días, el más antiguo, recibidos hoy, derivados del mes.
- **Entrar / "Trabajar en nombre de"**: cambia `idoficina`/`idresponsable` de la sesión; todas las bandejas
  (Inicio, Recepción, Archivo, Emisión, Derivados) muestran esa oficina. Aviso naranja mientras dure.
- Trazabilidad: actúa con un cargo propio "Administrador en nombre de X" (`responsables`: idpersonal 1,
  oficina X, estado 0), así el historial muestra la oficina X y que fue el Administrador.
- *Mi oficina* restaura su cargo real. Al iniciar sesión siempre vuelve a su oficina.

## Base de datos
Ejecutar `migraciones/ACTUALIZAR_BD_COMPLETO.sql` (000 a 009, se puede repetir sin riesgo).
