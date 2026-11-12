-- =====================================================================
--  SISGEDO — MIGRACIÓN 003: Índices de rendimiento
-- ---------------------------------------------------------------------
--  QUÉ HACE (solo AGREGA índices; no modifica ni borra datos):
--   Crea los índices que necesitan las bandejas (Recepción, Registro,
--   Inicio, Archivo, Emisión, reportes). Sin ellos MySQL recorre tablas
--   completas (movimientos ~100 mil filas) en cada carga de página.
--
--  CÓMO EJECUTAR:
--   - phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar
--     (o pestaña Importar > elegir este archivo).
--   - Local: mysql -u root sisgedov2 < migraciones/003_indices_rendimiento.sql
--  Es IDEMPOTENTE: cada índice se crea solo si no existe, así que puede
--  ejecutarse varias veces y en cualquier orden respecto a la 001.
--  No usa procedimientos ni DELIMITER (compatible con hosting compartido).
-- =====================================================================

-- movimientos: bandeja por oficina (pendientes / recibidos / por fecha)
SET @t='movimientos', @i='idx_mv_ofi_estado_fecha', @c='`idoficina_derivar`,`estado`,`fecha_hora`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- movimientos: cadena de derivaciones y bandeja "enviados"
SET @t='movimientos', @i='idx_mv_movant_fecha', @c='`idmovimientoant`,`fecha_hora`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- movimientos: actividad y reportes por rango de fechas
SET @t='movimientos', @i='idx_mv_fecha', @c='`fecha_hora`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- registro_documentos: pendientes por oficina
SET @t='registro_documentos', @i='idx_rd_estado_ofi', @c='`estado_recepcion`,`idoficina`,`fecha_registro`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- registro_documentos: listado por fechas
SET @t='registro_documentos', @i='idx_rd_fecha', @c='`fecha_registro`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- registro_documentos: búsqueda por expediente (también lo crea la 001)
SET @t='registro_documentos', @i='idx_rd_nro_exp', @c='`nro_exp`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- registro_documentos: oficina de quien registró
SET @t='registro_documentos', @i='idx_rd_idpersonal', @c='`idpersonal`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- docs_emitidos: pendientes por oficina destino
SET @t='docs_emitidos', @i='idx_de_ofi_estado', @c='`idoficina`,`estado`,`fecha`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- docs_emitidos: emitidos por oficina en un rango
SET @t='docs_emitidos', @i='idx_de_ofi_registro', @c='`idoficina_registro`,`fecha`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- docs_emitidos: listado por fechas
SET @t='docs_emitidos', @i='idx_de_fecha', @c='`fecha`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- docs_archivados: resumen del mes en Inicio
SET @t='docs_archivados', @i='idx_da_fecha', @c='`fecha_hora`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- personal: inicio de sesión
SET @t='personal', @i='idx_personal_usuario', @c='`usuario`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- remitente: búsqueda por DNI
SET @t='remitente', @i='idx_rem_dni', @c='`d_identidad`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- accesos_usuario: menú y permisos de cada usuario (en cada página)
SET @t='accesos_usuario', @i='idx_au_usuario_access', @c='`idusuario`,`access`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Actualiza las estadísticas para que MySQL use los índices nuevos
ANALYZE TABLE movimientos, registro_documentos, docs_emitidos, docs_archivados, remitente, personal, accesos_usuario;
