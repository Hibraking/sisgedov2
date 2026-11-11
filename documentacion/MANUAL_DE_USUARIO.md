# SISGEDO — Manual de usuario

Sistema de Gestión Documentario de la Municipalidad Provincial de Huaytará.
Implementa los procesos de **Recepción, Emisión, Archivo y Despacho** del Modelo de Gestión Documental (RSGD N° 001-2017-PCM/SEGDI).

---

## 1. Ingreso al sistema

1. Abra el sistema en el navegador e ingrese su **usuario** y **contraseña**.
2. Verá solo las opciones del menú que el administrador le asignó (Configuración › Acceso del Personal).
3. Para cambiar su contraseña o foto: **Mi perfil**.
4. Al terminar, cierre sesión desde su nombre (esquina superior derecha).

## 2. Conceptos clave

| Término | Significado |
|---|---|
| **Expediente** | Número único que recibe todo documento externo al ingresar por mesa de partes (ej. `20260125`). |
| **Registro interno** | Número correlativo que cada oficina asigna a los documentos que recibe. |
| **Número del documento emitido** | Correlativo por oficina, tipo de documento y año (ej. `045-2026-MPH/GM`). |
| **Código de verificación (CVD)** | Código de 8 caracteres (ej. `K7P2-9XQM`) que identifica de forma única un expediente o documento. Aparece en el cargo junto a un código QR. |
| **Huella digital (SHA-256)** | "Huella" del archivo adjunto: si el archivo cambia aunque sea un carácter, la huella cambia. Permite comprobar que no fue alterado. |
| **Cargo** | Constancia impresa de recepción o derivación. |

## 3. Mesa de Partes Virtual (ciudadano)

Página pública: `mpvirtual.php`.

1. El ciudadano llena sus datos (DNI/RUC, nombres, correo), el tipo de documento, el trámite, el asunto, los folios y adjunta su archivo.
2. Al enviar, el sistema le muestra su **número de expediente** y le envía un correo con:
   - el número de expediente,
   - el enlace de seguimiento,
   - su **código de verificación**.
3. Los documentos presentados fuera del horario de atención se procesan el siguiente día hábil.

## 4. Registro de documentos (mesa de partes presencial)

Menú **Operaciones › Registro**.

1. Pulse **Nuevo registro**.
2. Seleccione o registre el remitente, el tipo de documento, el trámite, el número del documento, los folios, el asunto y la(s) oficina(s) destino.
3. Adjunte el documento **digitalizado** (PDF de preferencia).
4. Guarde: el sistema asigna el **número de expediente** automáticamente y abre el **cargo** para imprimir.
5. El cargo incluye un **código QR** y el **código de verificación**: entréguelo al ciudadano.

> El número de expediente ya no puede repetirse, aunque dos personas registren al mismo tiempo.

## 5. Envío y recepción (derivación interna)

Menú **Operaciones › Derivados / Recepción**.

- **Recibir**: confirme la recepción indicando los folios. El sistema asigna el número de registro interno de su oficina.
- **Derivar**: elija la oficina destino, la acción (Atención, V° B°, Informe, etc.), el estado y los detalles.
- **Imprimir cargo**: genera el cargo interno con QR y código de verificación.
- **Seguimiento**: muestra todo el recorrido del documento.

## 6. Emisión de documentos

Menú **Operaciones › Emisión**.

### 6.1 Numeración automática (recomendada)

1. Pulse **Nuevo documento** y elija el **tipo de documento**.
2. Con **Numeración: Automática**, el sistema muestra el **siguiente número** de su oficina para ese tipo de documento (ej. `046-2026-MPH/GM`). Úselo en el documento que va a emitir.
3. Complete el asunto, los detalles, las oficinas destino (opcional) y adjunte el documento (PDF de preferencia).
4. Pulse **Emitir documento**. El sistema confirma el **número asignado** y el **código de verificación**.

> El número mostrado es referencial: si otra persona de su oficina emite un documento del mismo tipo antes que usted, se le asigna el siguiente. Confirme el número en el mensaje final.

### 6.2 Numeración manual (excepcional)

Marque **Manual** solo cuando el número ya fue asignado fuera del sistema. El sistema **no permite repetir** un número del mismo tipo en su oficina durante el año, y ajusta el correlativo automático para no repetirlo después.

### 6.3 Código de verificación

En el panel **Detalles del documento** aparece el **Cód. verificación** y el enlace **Verificar**. Puede consignarlo en el pie del documento para que cualquier persona compruebe su autenticidad.

### 6.4 Firma digital y visto bueno (FIRMA PERÚ)

*Disponible cuando la OTIyC active las credenciales de FIRMA PERÚ.*

1. El documento debe tener su adjunto en **PDF** y aún no haber sido recibido por el destino.
2. Seleccione el documento y pulse **Firmar digitalmente** (autor) o **Dar V° B°**.
3. Se abre el firmador de FIRMA PERÚ: inserte su **DNIe** o certificado digital, ubique el sello en la página y confirme con su PIN.
4. Al terminar, el adjunto se reemplaza por la **versión firmada** (la original se conserva en el servidor) y el panel muestra **Firmado** / **V°B°**.

Requisitos en su PC: cliente de FIRMA PERÚ instalado, lector de DNIe o token del certificado, y conexión a internet.

## 7. Archivo

Menú **Operaciones › Archivo**.

1. Seleccione los documentos atendidos y el **archivador** de su oficina (creado en Mantenimiento › Archivadores, por año).
2. Registre los detalles y confirme. El documento pasa al estado **Archivado**.

## 8. Consulta y verificación pública

- **Consulta de expediente** (`consulta_publico.php`): el ciudadano ingresa su número de expediente y ve en qué oficina está su trámite.
- **Verificación de documentos** (`verificar.php`): cualquier persona ingresa el **código de verificación** (o escanea el QR) y comprueba:
  - que el documento existe y fue registrado por la municipalidad (tipo, número, fecha, asunto, oficina);
  - si fue **firmado digitalmente**;
  - opcionalmente, que su copia del archivo es **idéntica** a la registrada (la comparación se hace en su propio equipo; el archivo no se envía a internet).

## 9. Mantenimiento (administrador)

| Opción | Uso |
|---|---|
| Oficinas | Unidades orgánicas. **Registre las siglas** de cada oficina para la numeración automática (ej. `MPH/GM`). |
| Personal / Responsables | Usuarios y su oficina. |
| Documentos | Tipos documentales y plazo de atención. |
| Tipo de trámite | Procedimientos (TUPA). |
| Acciones / Estados | Catálogos de la derivación. |
| Archivadores | Archivadores por oficina y año. |
| Acceso del Personal | Permisos por opción del menú. |

## 10. Buenas prácticas

- Adjunte siempre el documento **digitalizado en PDF**; es la evidencia del trámite.
- No elimine documentos ya recibidos: el sistema lo impide para no romper la trazabilidad.
- Use la numeración automática; la manual es solo para casos excepcionales.
- Entregue siempre el cargo con el código de verificación.
- Cierre sesión al dejar su puesto.
