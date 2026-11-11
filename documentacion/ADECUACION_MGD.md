# SISGEDO — Adecuación al Modelo de Gestión Documental

Referencia: Resolución de Secretaría de Gobierno Digital N° 001-2017-PCM/SEGDI (Modelo de Gestión Documental, MGD).
Ámbito: obligatorio para el Poder Ejecutivo; de adopción voluntaria para los gobiernos locales (art. 2).

Estado del sistema tras la actualización de noviembre de 2026 (migración 001).

| Componente MGD | Estado | Cómo lo cumple el SISGEDO |
|---|---|---|
| 8.3.1 Recepción | ✅ Cumple | Mesa de partes presencial y virtual; registro con datos mínimos; número de expediente **atómico** (sin duplicados); adjunto digitalizado con **huella SHA-256**; cargo de recepción con **QR y código de verificación**; aviso por correo. |
| 8.3.2 Emisión | ✅ Cumple (firma pendiente de credenciales) | Tipología documental; **numeración automática correlativa** por oficina/tipo/año con control de duplicados; huella SHA-256 del adjunto; código de verificación; **firma digital y V° B° con FIRMA PERÚ** integrada (se activa al recibir las credenciales de la PCM). |
| 8.3.3 Archivo | ⚠️ Parcial | Archivadores por oficina y año; estado *Archivado*; conservación de las versiones original y firmada. **Pendiente:** cuadro de clasificación, series documentales, plazos de retención y transferencias al Archivo Central (normas del AGN). |
| 8.3.4 Despacho | ⚠️ Parcial | Derivación interna con cargo y trazabilidad completa (movimientos). **Pendiente:** control de entrega y cargo de notificación a destinatarios externos. |
| 8.2.6 Herramientas informáticas | ✅ Cumple | Procesos del MGD; metadatos de archivos (formato, tamaño, huella, canal, usuario, fecha); integridad verificable; documentación (manual de usuario, manual de instalación y diccionario de datos en `documentacion/`). |
| 8.2.7 Certificados y firmas digitales | ✅ Preparado | Integración PAdES con FIRMA PERÚ (DNIe o certificado); registro de cada firma (`sgd_firma`); verificación pública muestra las firmas. Requiere credenciales del *Firmador web* (PCM). |
| Representación impresa / verificación | ✅ Cumple | Código de verificación (CVD) + QR en cargos; página pública `verificar.php` con comprobación de integridad del archivo en el equipo del usuario. |
| 8.2.10 Interoperabilidad (PIDE) | ❌ Pendiente | Requiere convenio con la PCM para acceso a la PIDE (servicios RENIEC/SUNAT y envío de documentos entre entidades). La consulta de DNI actual usa un servicio público del JNE. |
| 8.2.1–8.2.5, 8.2.8, 8.2.9 (política, roles, procedimientos, lineamientos, supervisión, mejora, capacitación) | — Institucional | No dependen del software: directiva de gestión documental, designación de responsables, inclusión en el POI y en el plan de capacitación. |

## Pendientes priorizados

1. Activar FIRMA PERÚ: solicitar credenciales del *Firmador web* a la PCM e instalar `fwAuthorization.json`.
2. Gestionar el convenio de acceso a la PIDE.
3. Módulo de Archivo Central: cuadro de clasificación, series, plazos de retención y transferencias.
4. Despacho externo: registro de notificaciones y cargos de entrega.
5. Aprobar la directiva interna de gestión documental (lineamientos de recepción, emisión, archivo y despacho).
