# SISGEDO — Manual de instalación

Sistema de Gestión Documentario de la Municipalidad Provincial de Huaytará.
Oficina de Tecnología de la Información y Comunicaciones (OTIyC).

## 1. Requisitos

| Componente | Versión mínima | Observación |
|---|---|---|
| Servidor web | Apache 2.4 (o LiteSpeed en cPanel) | Con `mod_rewrite` y lectura de `.htaccess` |
| PHP | 7.3 (recomendado 8.1 o superior) | Extensiones: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `curl`, `openssl` |
| Base de datos | MySQL 5.7 / 8.0 o MariaDB 10.4+ | Juego de caracteres `utf8mb4` |
| Correo | Cuenta de correo institucional con acceso SMTP (PHPMailer, incluido en `librerias/`) | Para la constancia de la Mesa de Partes Virtual (ver sección 7) |
| Certificado SSL | Obligatorio en producción | Necesario para la firma digital y la verificación de archivos en el navegador |

Espacio en disco: considerar el crecimiento de las carpetas de adjuntos (más de 4 GB actualmente).

## 2. Estructura de carpetas

| Carpeta | Contenido |
|---|---|
| `clases/` | Lógica y endpoints (PHP). `gestion_documental.php` concentra las funciones del Modelo de Gestión Documental |
| `vistas/`, `reportes/`, `partes/` | Pantallas internas |
| `conexion/` | Conexión a la BD (`conexion.php`), datos de la institución (`institucion.php`), configuración de FIRMA PERÚ (`firmaperu_config.php`) y del correo saliente (`correo_config.php`) |
| `librerias/` | Librerías PHP de terceros (PHPMailer 6.9.3). Acceso web bloqueado |
| `docs adjs reg_doc/` | Adjuntos de documentos recibidos (mesa de partes) |
| `docs adjs docs_emitidos/` | Adjuntos de documentos emitidos (incluye las versiones firmadas) |
| `docs adjs respuestas/` | Adjuntos de respuestas |
| `tcpdf/` | Librería de PDF (cargos con QR) |
| `migraciones/` | Scripts SQL de actualización de la base de datos (acceso web bloqueado) |
| `herramientas/` | Utilidades de mantenimiento (solo administrador) |
| `documentacion/` | Este manual, el manual de usuario y el diccionario de datos |
| `logs/` | Registro de errores de PHP en producción |

## 3. Instalación en local (Laragon)

1. Copiar el proyecto en `C:\laragon\www\sisgedov2`.
2. Iniciar Laragon (Apache y MySQL).
3. Crear la base de datos `sisgedov2` (utf8mb4) e importar la copia: HeidiSQL → *Archivo › Cargar archivo SQL* → `bd/sisgedov2_local.sql`.
4. Ejecutar las migraciones en orden (ver sección 5).
5. Abrir `http://localhost/sisgedov2/` (o `http://sisgedov2.test/`).

El sistema detecta automáticamente el entorno local por el nombre del servidor (`localhost`, `127.0.0.1` o `sisgedo.test`) y usa el usuario `root` sin contraseña.

## 4. Instalación en producción (cPanel)

1. Subir los archivos a la carpeta pública (p. ej. `public_html/sisgedo`), **sin** las carpetas `bd/` ni `_cuarentena_limpieza/`.
2. En cPanel › *Bases de datos MySQL*: crear la base de datos y el usuario, y otorgarle todos los privilegios.
3. Ajustar las credenciales de producción en `conexion/conexion.php` (sección *PRODUCCION*).
4. phpMyAdmin → seleccionar la base → *Importar* la copia de la base y luego las migraciones (sección 5).
5. Dar permisos de escritura (755 en carpetas) a `docs adjs reg_doc/`, `docs adjs docs_emitidos/`, `docs adjs respuestas/`, `images/perfiles/` y `logs/`.
6. Activar HTTPS (AutoSSL) y forzar `https://`.
7. Revisar `conexion/institucion.php` (nombre, siglas, correo de mesa de partes y logos).

## 5. Migraciones de base de datos

Se ejecutan **una sola vez por base de datos**, en orden numérico. Todas son idempotentes (si se ejecutan dos veces no generan errores ni duplican datos).

| Script | Qué hace |
|---|---|
| `optimizar_indices.sql` | Índices de rendimiento |
| `utf8mb4_migracion.sql` | Conversión de tablas a utf8mb4 |
| `migraciones/001_mgd_numeracion_integridad.sql` | Adecuación al MGD: secuencias de numeración, siglas de oficinas, metadatos/huella SHA-256 de adjuntos, código de verificación (CVD) y registro de firmas |
| `migraciones/002_registro_correos.sql` | Bitácora `sgd_correo`: cada correo que envía el sistema (destinatario, expediente, fecha, si salió o el error del servidor) |

Ejecución:
- Local: `mysql -u root sisgedov2 < migraciones/001_mgd_numeracion_integridad.sql` o desde HeidiSQL (F9).
- Producción: phpMyAdmin › *Importar* › seleccionar el archivo › *Continuar*.

### Pasos posteriores a la migración 001

1. **Registrar las siglas de cada oficina** (Mantenimiento › Oficinas › Editar › *Siglas para numerar documentos*). Ejemplos: `MPH/GM`, `MPH-OTIyC`, `MPH/GAF/SGL`. Sin siglas, la oficina solo puede numerar de forma manual.
2. **Registrar la huella de los adjuntos existentes** (opcional, recomendado):
   - Consola: `php herramientas/sgd_registrar_hashes.php`
   - Navegador (solo el usuario administrador): `https://<dominio>/sisgedo/herramientas/sgd_registrar_hashes.php` (recargar hasta que diga *Terminado*).

## 6. Configuración de la firma digital (FIRMA PERÚ)

1. Solicitar a la PCM las credenciales del *Firmador web* de FIRMA PERÚ (formato de solicitud de credenciales, firmado por el titular de la entidad).
2. Guardar el archivo `fwAuthorization.json` recibido **fuera de la carpeta pública**, en la ruta configurada en `conexion/firmaperu_config.php` (por defecto, una carpeta `firmaperu/` al lado de la carpeta del sistema: `public_html/firmaperu/fwAuthorization.json` si el sistema está en `public_html/sisgedo`).
3. Opcional: configurar sello de tiempo (TSA), filtro de certificados e imagen del estampado en `conexion/firmaperu_config.php`.
4. En cada PC que firme: instalar el cliente de FIRMA PERÚ y el lector de DNIe o el token del certificado digital.

Mientras no exista el archivo de credenciales, los botones de firma no se muestran y el resto del sistema funciona con normalidad.

## 7. Configuración del correo saliente

El sistema envía la constancia de recepción de la Mesa de Partes Virtual por **SMTP autenticado** con la cuenta `SISGEDO_MUNI_EMAIL` (`conexion/institucion.php`). Así el correo sale de verdad desde el servidor de correo de la municipalidad. Toda la configuración está en `conexion/correo_config.php`.

**Local (Laragon):** los correos van a **Mailpit** (`127.0.0.1:1025`) y no salen a internet. Se ven en `http://localhost:8025`. Si Mailpit no está corriendo, inícielo desde Laragon o con `C:\laragon\bin\mailpit\<versión>\mailpit.exe`.

**Producción (cPanel):**
1. cPanel › *Cuentas de correo*: confirme que existe la cuenta (p. ej. `mesadepartes@munihuaytara.gob.pe`) y que conoce su contraseña.
2. En esa cuenta › *Connect Devices*: anote el **servidor saliente** y el **puerto SMTP**. Si difieren de los valores por defecto (`mail.munihuaytara.gob.pe`, `465`, `ssl`), ajuste `SGD_CORREO_HOST`, `SGD_CORREO_PUERTO` y `SGD_CORREO_SEGURIDAD` en `correo_config.php`.
3. Copie `conexion/correo_clave.ejemplo.php` como `conexion/correo_clave.php` y escriba la contraseña. Este archivo **no** está en Git: hay que crearlo a mano en cada servidor.
4. cPanel › *Email Deliverability*: SPF y DKIM deben estar en *Valid*. Si no, Gmail/Outlook enviarán los correos a spam.
5. Ejecute la migración 002 y pruebe el envío:
   - Navegador (solo el administrador): `https://<dominio>/sisgedo/herramientas/probar_correo.php?a=sucorreo@gmail.com`
   - Consola SSH: `php herramientas/probar_correo.php sucorreo@gmail.com <dominio>`

   La prueba muestra la conversación con el servidor SMTP (sin la contraseña) y el resultado.

**Constancia de envío:** cada intento queda en la tabla `sgd_correo` con su estado (`ENVIADO`, `ERROR` o `DESACTIVADO`), el error del servidor si lo hubo y el `Message-ID`, que sirve para rastrear el correo en *Track Delivery* de cPanel. Ejemplo:

```sql
SELECT fecha, referencia AS expediente, destinatario, estado, detalle
FROM sgd_correo ORDER BY idcorreo DESC LIMIT 50;
```

Para suspender temporalmente los envíos: `SGD_CORREO_ACTIVO` en `false`.

## 8. Respaldo y restauración

- **Base de datos:** exportación diaria desde cPanel › *Copias de seguridad* o `mysqldump --single-transaction --routines --triggers`.
- **Adjuntos:** copia de las tres carpetas `docs adjs ...` (son la evidencia documental; nunca eliminarlas sin el procedimiento de eliminación del Archivo Central).
- Probar la restauración al menos una vez por semestre en el entorno local.

## 9. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| "Numeración automática no disponible" | Falta la migración 001 | Ejecutar la migración |
| "Su oficina no tiene siglas configuradas" | Siglas vacías | Mantenimiento › Oficinas |
| El cargo PDF sale sin QR | Falta la migración 001 o la extensión `gd` | Ejecutar la migración / habilitar `gd` |
| No aparecen los botones de firma | No se encontró `fwAuthorization.json` | Revisar la ruta en `firmaperu_config.php` |
| El firmador no inicia | Cliente FIRMA PERÚ no instalado o puerto 48596 bloqueado | Instalar el cliente / revisar antivirus y firewall |
| La verificación de archivo dice "se requiere HTTPS" | El sitio se abrió por `http://` | Usar `https://` |
| El ciudadano ve "No pudimos enviarte la constancia por correo" | Error SMTP | Ver la columna `detalle` en `sgd_correo` y ejecutar `herramientas/probar_correo.php` |
| `SMTP Error: Could not authenticate` | Contraseña incorrecta o falta `correo_clave.php` | Revisar `conexion/correo_clave.php` |
| `certificate verify failed` / `Could not connect to SMTP host` | El certificado no coincide con `SGD_CORREO_HOST`, o puerto bloqueado | Usar como host el nombre que muestra *Connect Devices*, probar `587` + `tls`, o como último recurso `SGD_CORREO_VERIFICAR_SSL = false` |
| El correo llega a spam | SPF/DKIM no válidos | cPanel › *Email Deliverability* › reparar |
