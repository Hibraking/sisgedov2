-- =====================================================================
--  SISGEDO — ACTUALIZACIÓN COMPLETA DE LA BASE DE DATOS
-- ---------------------------------------------------------------------
--  Reúne, en orden, TODAS las migraciones (000 a 009). Cada una comprueba
--  lo que ya existe, así que este archivo se puede ejecutar:
--    - sobre la base del hosting tal como está hoy, o
--    - sobre una base recién importada del sistema antiguo,
--  y también volver a ejecutarse sin duplicar ni romper nada.
--
--  ANTES: saque una copia de seguridad (phpMyAdmin > Exportar).
--  CÓMO:  phpMyAdmin > seleccione la base xyltrylx_sisgedov2 >
--         pestaña Importar > elija este archivo > Importar.
--         (o pestaña SQL > pegar todo > Continuar)
--
--  Contenido:
--   000 Base: charset utf8mb4 (solo tablas pendientes) + personal.ultimo_acceso
--   001 Numeración, integridad (SHA-256) y verificación de documentos
--   002 Registro de correos enviados
--   003 Índices de rendimiento
--   004 Responsables recuperados
--   005 Tipos de trámite sin repetidos
--   006 Tipos de documento y remitentes sin repetidos
--   007 Módulo Mesa de Partes Virtual
--   008 Mesa de Partes Virtual: observar, subsanar y horario
--   009 Integridad de recepción, derivación y archivo
-- =====================================================================


-- #####################################################################
-- ### 000_base.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 000: Base (charset utf8mb4 + último acceso)
-- ---------------------------------------------------------------------
--  Reemplaza a los antiguos utf8mb4_migracion.sql y ultimo_acceso.sql.
--   - Convierte a utf8mb4_general_ci SOLO las tablas que aún no lo están
--     (convertir una tabla ya convertida la reconstruye sin necesidad).
--   - personal.ultimo_acceso (lo usa el inicio de sesión y Mi perfil).
--  Es IDEMPOTENTE.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


SET @t='accesos_usuario';     SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='aciones_documento';   SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='archivador';          SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='docs_archivados';     SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='docs_emitidos';       SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='documentos';          SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='estado_documento';    SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='menu';                SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='modulo';              SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='movimientos';         SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='oficinas';            SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='personal';            SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='registro_documentos'; SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='remitente';           SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='responsables';        SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='tipo_tram';           SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='usuario';             SET @s=(SELECT IF(TABLE_COLLATION<>'utf8mb4_general_ci', CONCAT('ALTER TABLE `',@t,'` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'), 'DO 0') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=@t); PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET FOREIGN_KEY_CHECKS = 1;

-- personal.ultimo_acceso
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `personal` ADD COLUMN `ultimo_acceso` DATETIME NULL DEFAULT NULL COMMENT ''Fecha y hora del último inicio de sesión''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='personal' AND column_name='ultimo_acceso');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SELECT 'Migración 000 aplicada' AS resultado;


-- #####################################################################
-- ### 001_mgd_numeracion_integridad.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 001: Adecuación al Modelo de Gestión Documental
--  (RSGD N° 001-2017-PCM/SEGDI) — numeración, integridad y verificación
-- ---------------------------------------------------------------------
--  QUÉ HACE (solo AGREGA; no modifica ni borra datos existentes):
--   1. sgd_secuencia     : contadores atómicos (expediente, registro
--                          interno por oficina y numeración de documentos
--                          emitidos por oficina / tipo / año).
--   2. oficinas.siglas   : nomenclatura de cada oficina para numerar sus
--                          documentos (ej. MPH/GM, MPH-OTIyC).
--   3. sgd_archivo       : metadatos de cada archivo adjunto (formato,
--                          tamaño, huella SHA-256, quién/cuándo/canal).
--   4. sgd_verificacion  : Código de Verificación Digital (CVD) de cada
--                          expediente y documento emitido.
--   5. sgd_firma         : registro de documentos firmados digitalmente
--                          (se llenará cuando se active FIRMA PERÚ).
--   6. Índices de apoyo.
--
--  CÓMO EJECUTAR:
--   - Local (Laragon): HeidiSQL > abrir este archivo > Ejecutar (F9), o
--       mysql -u root sisgedov2 < migraciones/001_mgd_numeracion_integridad.sql
--   - Producción (cPanel): phpMyAdmin > BD xyltrylx_sisgedov2 > Importar.
--  Es IDEMPOTENTE: puede ejecutarse más de una vez sin error.
--  No usa procedimientos ni DELIMITER (compatible con phpMyAdmin de hosting compartido).
--  Compatible con MySQL 5.7 / 8.0 y MariaDB 10.x.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Secuencias (numeración sin duplicados aunque dos usuarios registren
--    al mismo tiempo). Claves usadas por el sistema:
--      EXP-<año>                          número de expediente
--      MOV-<idoficina>-<año>              registro interno de recepción
--      EMI-<idoficina>-<iddocumento>-<año> correlativo de documentos emitidos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sgd_secuencia` (
  `clave`       VARCHAR(80)  NOT NULL,
  `ultimo`      INT UNSIGNED NOT NULL DEFAULT 0,
  `actualizado` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Contadores atómicos de numeración (MGD)';

-- ---------------------------------------------------------------------
-- 2. Siglas / nomenclatura de cada oficina para la numeración automática
-- ---------------------------------------------------------------------
SET @s=(SELECT IF(COUNT(*)=0, 'ALTER TABLE `oficinas` ADD COLUMN `siglas` VARCHAR(60) NULL DEFAULT NULL COMMENT ''Nomenclatura para numerar documentos, ej. MPH/GM''', 'DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='oficinas' AND column_name='siglas');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 3. Metadatos e integridad de archivos adjuntos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sgd_archivo` (
  `idarchivo`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `origen`            ENUM('REGISTRO','EMITIDO') NOT NULL COMMENT 'REGISTRO = docs adjs reg_doc, EMITIDO = docs adjs docs_emitidos',
  `nombre_almacenado` VARCHAR(255) NOT NULL COMMENT 'Nombre del archivo en disco (= columna adjunto)',
  `nombre_original`   VARCHAR(255) NULL,
  `extension`         VARCHAR(10)  NULL,
  `mime`              VARCHAR(100) NULL,
  `tamano_bytes`      BIGINT UNSIGNED NULL,
  `hash_sha256`       CHAR(64)     NOT NULL,
  `canal`             ENUM('PRESENCIAL','VIRTUAL','INTERNO') NOT NULL DEFAULT 'INTERNO',
  `idpersonal`        INT NULL COMMENT 'Usuario que cargó el archivo (NULL = ciudadano por MPV)',
  `ip`                VARCHAR(45)  NULL,
  `fecha_carga`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idarchivo`),
  UNIQUE KEY `uq_sgd_archivo` (`origen`, `nombre_almacenado`),
  KEY `idx_sgd_archivo_hash` (`hash_sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Metadatos e integridad (SHA-256) de documentos adjuntos (MGD)';

-- ---------------------------------------------------------------------
-- 4. Código de Verificación Digital (CVD)
--    tipo EXPEDIENTE -> referencia = registro_documentos.nro_exp
--    tipo EMITIDO    -> referencia = docs_emitidos.iddocs_emitidos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sgd_verificacion` (
  `cvd`        CHAR(8)     NOT NULL,
  `tipo`       ENUM('EXPEDIENTE','EMITIDO') NOT NULL,
  `referencia` VARCHAR(45) NOT NULL,
  `creado`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cvd`),
  UNIQUE KEY `uq_sgd_verif_ref` (`tipo`, `referencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Código de verificación digital para representaciones impresas (MGD)';

-- ---------------------------------------------------------------------
-- 5. Firmas digitales (Ley N° 27269) — preparado para FIRMA PERÚ
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sgd_firma` (
  `idfirma`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `iddocs_emitidos`   INT NOT NULL,
  `idpersonal`        INT NULL COMMENT 'Usuario que inició la firma',
  `tipo_firma`        ENUM('FIRMA','VISTO_BUENO') NOT NULL DEFAULT 'FIRMA',
  `param_token`       CHAR(64) NOT NULL COMMENT 'Token de un solo uso para el firmador',
  `token_expira`      DATETIME NOT NULL,
  `archivo_origen`    VARCHAR(255) NULL COMMENT 'Adjunto antes de firmar',
  `archivo_firmado`   VARCHAR(255) NULL COMMENT 'Adjunto firmado (nuevo archivo)',
  `hash_sha256`       CHAR(64) NULL COMMENT 'Huella del archivo firmado',
  `creado`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_firma`       DATETIME NULL,
  `estado`            ENUM('PENDIENTE','FIRMADO','CANCELADO','ERROR') NOT NULL DEFAULT 'PENDIENTE',
  `detalle`           VARCHAR(500) NULL,
  PRIMARY KEY (`idfirma`),
  UNIQUE KEY `uq_sgd_firma_token` (`param_token`),
  KEY `idx_sgd_firma_doc` (`iddocs_emitidos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Firmas digitales de documentos emitidos (FIRMA PERÚ)';

-- ---------------------------------------------------------------------
-- 6. Índices de apoyo (búsqueda por número y siembra de secuencias)
-- ---------------------------------------------------------------------
SET @t='registro_documentos', @i='idx_rd_nro_exp', @c='`nro_exp`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='registro_documentos', @i='idx_rd_adjunto', @c='`adjunto`(100)';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='docs_emitidos', @i='idx_de_ofireg_doc_fecha', @c='`idoficina_registro`, `iddocumento`, `fecha`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='docs_emitidos', @i='idx_de_adjunto', @c='`adjunto`(100)';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @t='movimientos', @i='idx_mv_num_exp', @c='`num_exp`';
SET @s=(SELECT IF(COUNT(*)=0, CONCAT('ALTER TABLE `',@t,'` ADD INDEX `',@i,'` (',@c,')'), 'DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=@t AND index_name=@i);
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;



-- #####################################################################
-- ### 002_registro_correos.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 002: Registro de correos enviados
-- ---------------------------------------------------------------------
--  QUÉ HACE (solo AGREGA; no modifica ni borra datos existentes):
--   1. sgd_correo : bitácora de cada correo que el sistema intenta enviar
--                   (a quién, por qué expediente, cuándo, si salió o el
--                   error del servidor SMTP). Sirve como constancia de
--                   que se notificó al ciudadano.
--
--  CÓMO EJECUTAR:
--   - Local (Laragon): HeidiSQL > abrir este archivo > Ejecutar (F9), o
--       mysql -u root sisgedov2 < migraciones/002_registro_correos.sql
--   - Producción (cPanel): phpMyAdmin > BD xyltrylx_sisgedov2 > Importar.
--  Es IDEMPOTENTE: puede ejecutarse más de una vez sin error.
--  Si no se ejecuta, el sistema igual envía los correos, pero solo deja
--  constancia en el log de errores de PHP.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `sgd_correo` (
  `idcorreo`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`         VARCHAR(40)  NOT NULL COMMENT 'Motivo: MPV_RECEPCION, PRUEBA, ...',
  `referencia`   VARCHAR(45)  NULL     COMMENT 'Número de expediente u otra referencia',
  `destinatario` VARCHAR(255) NOT NULL,
  `asunto`       VARCHAR(255) NOT NULL,
  `estado`       ENUM('ENVIADO','ERROR','DESACTIVADO') NOT NULL,
  `detalle`      VARCHAR(1000) NULL    COMMENT 'Error del servidor SMTP, si hubo',
  `message_id`   VARCHAR(255) NULL     COMMENT 'Message-ID del correo (para rastrearlo en el servidor)',
  `servidor`     VARCHAR(120) NULL     COMMENT 'Servidor SMTP usado',
  `idpersonal`   INT          NULL     COMMENT 'Usuario que originó el envío (NULL = automático / ciudadano)',
  `ip`           VARCHAR(45)  NULL,
  `fecha`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idcorreo`),
  KEY `idx_sgd_correo_ref` (`referencia`),
  KEY `idx_sgd_correo_fecha` (`fecha`),
  KEY `idx_sgd_correo_dest` (`destinatario`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Bitácora de correos enviados por el sistema';


-- #####################################################################
-- ### 003_indices_rendimiento.sql
-- #####################################################################
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


-- #####################################################################
-- ### 004_recuperar_responsables.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 004: Recuperar responsables borrados
-- ---------------------------------------------------------------------
--  QUÉ HACE:
--   Los responsables 19, 21, 22, 23, 30, 34, 36, 44, 45, 50, 56 y 63 quedaron sin persona ni
--   oficina (idpersonal/idoficina = NULL), por eso el historial y los
--   reportes mostraban nombres y oficinas en blanco.
--   - La oficina de cada uno se dedujo de sus movimientos.
--   - La persona, de las iniciales con que firmaban sus documentos.
--   - Los que no se pudieron identificar quedan con un nombre provisional
--     ("Responsable anterior") que se puede corregir luego en Personal.
--   - Los responsables se dejan INACTIVOS (estado 0): son históricos y
--     no deben aparecer para recibir documentos nuevos.
--   - Renombra la oficina 3 ("-").
--
--  CÓMO EJECUTAR:
--   - phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--   - Local: mysql -u root sisgedov2 < migraciones/004_recuperar_responsables.sql
--  Es IDEMPOTENTE: solo toca responsables que siguen vacíos y no duplica
--  los nombres provisionales si se ejecuta otra vez.
-- =====================================================================
SET NAMES utf8mb4;
START TRANSACTION;

-- 1) Oficina 3: antes "-"
UPDATE oficinas SET nombre = 'Gerencia de Desarrollo Territorial e Infraestructura'
 WHERE idoficinas = 3 AND (nombre IS NULL OR TRIM(nombre) IN ('', '-'));

-- 2) Identificados por sus iniciales de firma (personas ya registradas)
--    #19 Unidad Formuladora           -> KNRC  Katerine Nyccol RAFAEL CHUCOS (personal 9)
--    #34 Control Previo y Recaudación -> HHR   Hugo HUAROTO RIOS (personal 17)
--    #21 Subg. Desarrollo Territorial -> MAHD  Miguel Angel Huaman Donayre (personal 32)
UPDATE responsables SET idpersonal = 9,  idoficina = 21 WHERE idresponsables = 19 AND idpersonal IS NULL;
UPDATE responsables SET idpersonal = 17, idoficina = 19 WHERE idresponsables = 34 AND idpersonal IS NULL;
UPDATE responsables SET idpersonal = 32, idoficina = 10 WHERE idresponsables = 21 AND idpersonal IS NULL;

-- 3) No identificados: persona provisional (sin usuario ni clave: no puede entrar)
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(firma JSVT)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma JSVT)');
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(firma NFAM)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma NFAM)');
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(Gerencia Municipal)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(Gerencia Municipal)');

--    #44 Gerencia de Desarrollo Territorial e Infraestructura (oficina 3)
UPDATE responsables SET idoficina = 3,
       idpersonal = (SELECT idpersonal FROM (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma JSVT)' LIMIT 1) t)
 WHERE idresponsables = 44 AND idpersonal IS NULL;
--    #36 Sub Gerencia de Salud y Bienestar Social (oficina 16)
UPDATE responsables SET idoficina = 16,
       idpersonal = (SELECT idpersonal FROM (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(firma NFAM)' LIMIT 1) t)
 WHERE idresponsables = 36 AND idpersonal IS NULL;
--    #50 Gerencia Municipal (oficina 5)
UPDATE responsables SET idoficina = 5,
       idpersonal = (SELECT idpersonal FROM (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(Gerencia Municipal)' LIMIT 1) t)
 WHERE idresponsables = 50 AND idpersonal IS NULL;

-- 4) Otros responsables vacíos con pocos documentos (2019-2021)
--    #56 Sala de Regidores -> firma JAECH = José Antonio ESCOBAR CHUQUIHUACCHA (personal 50)
UPDATE responsables SET idpersonal = 50, idoficina = 39 WHERE idresponsables = 56 AND idpersonal IS NULL;

--    Sin iniciales de firma: persona provisional común, oficina deducida
INSERT INTO personal (nombres, apellidos)
SELECT 'Responsable anterior', '(no identificado)' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(no identificado)');
SET @ni = (SELECT idpersonal FROM personal WHERE nombres = 'Responsable anterior' AND apellidos = '(no identificado)' LIMIT 1);
UPDATE responsables SET idpersonal = @ni, idoficina = 24 WHERE idresponsables = 22 AND idpersonal IS NULL; -- Defensa Civil
UPDATE responsables SET idpersonal = @ni, idoficina = 18 WHERE idresponsables = 23 AND idpersonal IS NULL; -- Turismo y Artesanía
UPDATE responsables SET idpersonal = @ni, idoficina = 31 WHERE idresponsables = 30 AND idpersonal IS NULL; -- Área de Adquisiciones
UPDATE responsables SET idpersonal = @ni, idoficina = 36 WHERE idresponsables = 45 AND idpersonal IS NULL; -- Portal de Transparencia
UPDATE responsables SET idpersonal = @ni, idoficina = 41 WHERE idresponsables = 63 AND idpersonal IS NULL; -- Registro Único de Víctimas

COMMIT;

-- Verificación: deben salir las 12 filas con persona y oficina
SELECT r.idresponsables, r.estado, o.nombre AS oficina, CONCAT_WS(' ', p.nombres, p.apellidos) AS persona
  FROM responsables r
  LEFT JOIN oficinas o ON o.idoficinas = r.idoficina
  LEFT JOIN personal p ON p.idpersonal = r.idpersonal
 WHERE r.idresponsables IN (19, 21, 22, 23, 30, 34, 36, 44, 45, 50, 56, 63);


-- #####################################################################
-- ### 005_limpiar_tipos_tramite.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 005: Limpieza de tipos de trámite repetidos
-- ---------------------------------------------------------------------
--  QUÉ HACE:
--   Había 135 tipos de trámite pero solo 57 nombres distintos
--   (37 "ATENCION", 27 "RENOVACION DE CONTRATO", 6 "REMISION DE
--   INFORMACION"...). En la lista de "Registrar documento" salían
--   repetidos.
--   - Agrupa los tipos con el MISMO nombre (sin distinguir mayúsculas,
--     tildes ni espacios de más).
--   - En cada grupo conserva el que tiene más documentos.
--   - Pasa los documentos de los repetidos a ese tipo (no se pierde nada).
--   - Borra los repetidos y los tipos sin nombre que no usa ningún documento.
--   No fusiona nombres parecidos pero distintos (p. ej. "APOYO" y
--   "APOYO ECONOMICO"): eso se revisa a mano desde Tipos de Trámite.
--
--  CÓMO EJECUTAR:
--   - phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--   - Local: mysql -u root sisgedov2 < migraciones/005_limpiar_tipos_tramite.sql
--  Es IDEMPOTENTE: ejecutarlo otra vez no cambia nada.
--  Requiere MySQL 8 (usa funciones de ventana).
-- =====================================================================
SET NAMES utf8mb4;

DROP TEMPORARY TABLE IF EXISTS tmp_tt_mapa;
CREATE TEMPORARY TABLE tmp_tt_mapa AS
SELECT id AS viejo,
       FIRST_VALUE(id) OVER (PARTITION BY clave ORDER BY usos DESC, id ASC) AS nuevo
  FROM (SELECT t.idtipo_tram AS id,
               UPPER(REGEXP_REPLACE(TRIM(t.nombre), ' {2,}', ' ')) COLLATE utf8mb4_general_ci AS clave,
               (SELECT COUNT(*) FROM registro_documentos r WHERE r.idtipo_tramite = t.idtipo_tram) AS usos
          FROM tipo_tram t
         WHERE TRIM(IFNULL(t.nombre, '')) <> '') x;

START TRANSACTION;

-- 1) Documentos de los repetidos -> tipo que se conserva
UPDATE registro_documentos r
  JOIN tmp_tt_mapa m ON r.idtipo_tramite = m.viejo
   SET r.idtipo_tramite = m.nuevo
 WHERE m.viejo <> m.nuevo;

-- 2) Borrar los repetidos (ya sin documentos)
DELETE t FROM tipo_tram t
  JOIN tmp_tt_mapa m ON t.idtipo_tram = m.viejo
 WHERE m.viejo <> m.nuevo;

-- 3) Tipos sin nombre que ningún documento usa
DELETE FROM tipo_tram
 WHERE TRIM(IFNULL(nombre, '')) = ''
   AND NOT EXISTS (SELECT 1 FROM registro_documentos r WHERE r.idtipo_tramite = tipo_tram.idtipo_tram);

-- 4) Quitar espacios de más en los nombres que quedan
UPDATE tipo_tram
   SET nombre = REGEXP_REPLACE(TRIM(nombre), ' {2,}', ' ')
 WHERE nombre <> REGEXP_REPLACE(TRIM(nombre), ' {2,}', ' ');

COMMIT;
DROP TEMPORARY TABLE IF EXISTS tmp_tt_mapa;

-- Verificación: "repetidos" debe ser 0
-- (el tipo #19 no tiene nombre: lo usan 7 documentos antiguos y ya no aparece en las listas)
SELECT COUNT(*) AS tipos_de_tramite,
       COUNT(*) - COUNT(DISTINCT UPPER(TRIM(nombre))) AS repetidos
  FROM tipo_tram
 WHERE TRIM(IFNULL(nombre, '')) <> '';


-- #####################################################################
-- ### 006_limpiar_documentos_remitentes.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 006: Limpieza de TIPOS DE DOCUMENTO y REMITENTES
--  repetidos (listas de "Registrar documento")
-- ---------------------------------------------------------------------
--  QUÉ HACE:
--   A) Tipos de documento: 174 registros para 147 nombres (4 "SOLICITUD",
--      5 "DECLARACION DE IMPACTO AMBIENTAL"...).
--      - Agrupa los de MISMO nombre (sin distinguir mayúsculas, tildes ni
--        espacios de más) y conserva el que más documentos usa.
--      - Pasa a ese tipo los documentos registrados y emitidos.
--      - Reinicia los contadores de numeración de los tipos fusionados
--        (se recalculan solos desde el número más alto ya usado).
--      - Mientras mueve los emitidos desactiva el trigger
--        docs_emitidos_BEFORE_UPDATE (que reescribiría la oficina emisora
--        histórica) y lo vuelve a crear idéntico al final.
--   B) Remitentes: muchos se guardaron varias veces (hasta 22 copias
--      seguidas del mismo nombre).
--      - Agrupa los de MISMO apellidos + nombres. NO fusiona un grupo si
--        tiene DNIs distintos (serían personas diferentes).
--      - Conserva el que tiene DNI, si no el que más documentos tiene, y le
--        completa DNI/dirección/email/teléfono vacíos con los de sus copias.
--      - Pasa los documentos de las copias al que se conserva.
--      - Borra los remitentes sin nombre que ningún documento usa.
--   No se pierde ningún documento ni se cambia su contenido.
--
--  CÓMO EJECUTAR:
--   - phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--   - Local: mysql -u root sisgedov2 < migraciones/006_limpiar_documentos_remitentes.sql
--  Es IDEMPOTENTE. Requiere MySQL 8.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- A) TIPOS DE DOCUMENTO
-- ---------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_doc_mapa;
CREATE TEMPORARY TABLE tmp_doc_mapa AS
SELECT id AS viejo,
       FIRST_VALUE(id) OVER (PARTITION BY clave ORDER BY usos DESC, id ASC) AS nuevo
  FROM (SELECT d.iddocumentos AS id,
               UPPER(REGEXP_REPLACE(TRIM(d.nombre_documento), ' {2,}', ' ')) COLLATE utf8mb4_general_ci AS clave,
               (SELECT COUNT(*) FROM registro_documentos r WHERE r.iddocumento = d.iddocumentos)
             + (SELECT COUNT(*) FROM docs_emitidos e WHERE e.iddocumento = d.iddocumentos) AS usos
          FROM documentos d
         WHERE TRIM(IFNULL(d.nombre_documento, '')) <> '') x;
DELETE FROM tmp_doc_mapa WHERE viejo = nuevo;
-- ids tocados (MySQL no permite usar la misma tabla temporal dos veces en una consulta)
DROP TEMPORARY TABLE IF EXISTS tmp_doc_ids;
CREATE TEMPORARY TABLE tmp_doc_ids (id INT PRIMARY KEY);
INSERT IGNORE INTO tmp_doc_ids SELECT viejo FROM tmp_doc_mapa;
INSERT IGNORE INTO tmp_doc_ids SELECT nuevo FROM tmp_doc_mapa;

-- 1) Documentos REGISTRADOS (sin triggers)
UPDATE registro_documentos r JOIN tmp_doc_mapa m ON r.iddocumento = m.viejo SET r.iddocumento = m.nuevo;

-- 2) Documentos EMITIDOS. El trigger docs_emitidos_BEFORE_UPDATE reescribiría
--    la oficina emisora histórica con la oficina ACTUAL del responsable, así
--    que se quita SOLO durante este UPDATE y se vuelve a crear idéntico.
DROP TRIGGER IF EXISTS `docs_emitidos_BEFORE_UPDATE`;
UPDATE docs_emitidos e JOIN tmp_doc_mapa m ON e.iddocumento = m.viejo SET e.iddocumento = m.nuevo;
DELIMITER $$
CREATE TRIGGER `docs_emitidos_BEFORE_UPDATE` BEFORE UPDATE ON `docs_emitidos` FOR EACH ROW BEGIN
	 set new.idoficina_registro=(select idoficina from responsables where idresponsables=new.idresponsable);
END$$
DELIMITER ;

-- 3) Contadores de numeración 'EMI-oficina-tipo-año' de los tipos tocados
DELETE FROM sgd_secuencia
 WHERE clave LIKE 'EMI-%'
   AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(clave, '-', 3), '-', -1) AS UNSIGNED) IN (SELECT id FROM tmp_doc_ids);

-- 4) Borrar los tipos repetidos (ya sin documentos) y quitar espacios de más
DELETE d FROM documentos d JOIN tmp_doc_mapa m ON d.iddocumentos = m.viejo;
UPDATE documentos SET nombre_documento = REGEXP_REPLACE(TRIM(nombre_documento), ' {2,}', ' ')
 WHERE nombre_documento <> REGEXP_REPLACE(TRIM(nombre_documento), ' {2,}', ' ');

DROP TEMPORARY TABLE IF EXISTS tmp_doc_mapa;
DROP TEMPORARY TABLE IF EXISTS tmp_doc_ids;

-- ---------------------------------------------------------------------
-- B) REMITENTES
-- ---------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_rem_base;
CREATE TEMPORARY TABLE tmp_rem_base AS
SELECT x.*,
       COUNT(NULLIF(x.dni, '')) OVER (PARTITION BY x.clave) AS con_dni,
       FIRST_VALUE(x.id) OVER (PARTITION BY x.clave ORDER BY (x.dni <> '') DESC, x.usos DESC, x.id ASC) AS nuevo
  FROM (SELECT t.idremitente AS id,
               UPPER(REGEXP_REPLACE(CONCAT_WS(' ', TRIM(t.apellidos), TRIM(t.nombres)), ' {2,}', ' ')) COLLATE utf8mb4_general_ci AS clave,
               TRIM(IFNULL(t.d_identidad, '')) AS dni,
               (SELECT COUNT(*) FROM registro_documentos r WHERE r.idremitente = t.idremitente) AS usos
          FROM remitente t
         WHERE TRIM(CONCAT(IFNULL(t.apellidos, ''), IFNULL(t.nombres, ''))) <> '') x;

-- grupos con DNIs distintos = posibles homónimos: no se tocan
DROP TEMPORARY TABLE IF EXISTS tmp_rem_conflicto;
CREATE TEMPORARY TABLE tmp_rem_conflicto AS
SELECT clave FROM tmp_rem_base WHERE dni <> '' GROUP BY clave HAVING COUNT(DISTINCT dni) > 1;

DROP TEMPORARY TABLE IF EXISTS tmp_rem_mapa;
CREATE TEMPORARY TABLE tmp_rem_mapa AS
SELECT b.id AS viejo, b.nuevo
  FROM tmp_rem_base b
 WHERE b.id <> b.nuevo
   AND b.clave NOT IN (SELECT clave FROM tmp_rem_conflicto);

-- datos a completar en el que se conserva (primer valor no vacío de sus copias)
DROP TEMPORARY TABLE IF EXISTS tmp_rem_datos;
CREATE TEMPORARY TABLE tmp_rem_datos AS
SELECT m.nuevo,
       MAX(NULLIF(TRIM(t.d_identidad), '')) AS dni,
       MAX(NULLIF(TRIM(t.direccion), ''))   AS direccion,
       MAX(NULLIF(TRIM(t.email), ''))       AS email,
       MAX(NULLIF(TRIM(t.telefono), ''))    AS telefono
  FROM tmp_rem_mapa m JOIN remitente t ON t.idremitente = m.viejo
 GROUP BY m.nuevo;

START TRANSACTION;
UPDATE remitente k JOIN tmp_rem_datos d ON k.idremitente = d.nuevo
   SET k.d_identidad = IF(TRIM(IFNULL(k.d_identidad, '')) = '', d.dni,       k.d_identidad),
       k.direccion   = IF(TRIM(IFNULL(k.direccion, ''))   = '', d.direccion, k.direccion),
       k.email       = IF(TRIM(IFNULL(k.email, ''))       = '', d.email,     k.email),
       k.telefono    = IF(TRIM(IFNULL(k.telefono, ''))    = '', d.telefono,  k.telefono);
UPDATE registro_documentos r JOIN tmp_rem_mapa m ON r.idremitente = m.viejo SET r.idremitente = m.nuevo;
DELETE t FROM remitente t JOIN tmp_rem_mapa m ON t.idremitente = m.viejo;
-- remitentes sin nombre que nadie usa
DELETE FROM remitente
 WHERE TRIM(CONCAT(IFNULL(apellidos, ''), IFNULL(nombres, ''))) = ''
   AND NOT EXISTS (SELECT 1 FROM registro_documentos r WHERE r.idremitente = remitente.idremitente);
COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_rem_base;
DROP TEMPORARY TABLE IF EXISTS tmp_rem_conflicto;
DROP TEMPORARY TABLE IF EXISTS tmp_rem_mapa;
DROP TEMPORARY TABLE IF EXISTS tmp_rem_datos;

-- ---------------------------------------------------------------------
-- Verificación: las columnas "repetidos" deben salir en 0
-- (remitentes: salvo homónimos con DNI distinto, que se dejan aparte)
-- ---------------------------------------------------------------------
SELECT (SELECT COUNT(*) FROM documentos WHERE TRIM(IFNULL(nombre_documento,'')) <> '') AS tipos_documento,
       (SELECT COUNT(*) - COUNT(DISTINCT UPPER(TRIM(nombre_documento))) FROM documentos WHERE TRIM(IFNULL(nombre_documento,'')) <> '') AS tipos_doc_repetidos,
       (SELECT COUNT(*) FROM remitente) AS remitentes,
       (SELECT COUNT(*) - COUNT(DISTINCT UPPER(REGEXP_REPLACE(CONCAT_WS(' ', TRIM(apellidos), TRIM(nombres)), ' {2,}', ' ')))
          FROM remitente WHERE TRIM(CONCAT(IFNULL(apellidos,''), IFNULL(nombres,''))) <> '') AS remitentes_repetidos;


-- #####################################################################
-- ### 007_mesa_partes_virtual.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 007: Módulo "Mesa de Partes Virtual"
-- ---------------------------------------------------------------------
--  QUÉ HACE:
--   Los documentos que llegan por internet (Mesa de Partes Virtual) tienen
--   ahora su propia bandeja. El operador de mesa de partes:
--     1) RECEPCIONA el documento (el ciudadano recibe un correo de aviso),
--     2) luego lo DERIVA a la oficina que corresponde.
--   - registro_documentos.mpv_recepcion   : fecha/hora en que mesa de partes
--                                            lo recepcionó (NULL = pendiente)
--   - registro_documentos.mpv_idpersonal  : quién lo recepcionó
--   - Documentos virtuales ANTIGUOS: se marcan como ya recepcionados (con su
--     fecha de registro) para que la bandeja empiece solo con lo reciente:
--     los ya derivados/atendidos y los de más de 30 días (mismo criterio que
--     la etiqueta "NEW" que usaba Registro).
--   - Menú "Mesa de Partes Virtual" (Operaciones) y acceso para quienes ya
--     tienen acceso a "Registro de Documentos".
--
--  CÓMO EJECUTAR:
--   - phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--   - Local: mysql -u root sisgedov2 < migraciones/007_mesa_partes_virtual.sql
--  Es IDEMPOTENTE: se puede ejecutar varias veces sin duplicar nada.
--  Ejecutarla ANTES de subir el código nuevo (el código funciona igual sin
--  ella, pero el módulo nuevo necesita estas columnas).
-- =====================================================================
SET NAMES utf8mb4;

-- ¿Primera ejecución? (si la columna aún no existe). El paso 2 solo se hace la
-- primera vez: repetirlo ocultaría documentos virtuales pendientes reales.
SET @mpv_primera = (SELECT COUNT(*) = 0 FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_recepcion');

-- 1) Columnas nuevas
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD COLUMN `mpv_recepcion` DATETIME NULL DEFAULT NULL COMMENT ''Mesa de Partes Virtual: fecha en que mesa de partes lo recepcionó (NULL = pendiente)''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_recepcion');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD COLUMN `mpv_idpersonal` INT NULL DEFAULT NULL COMMENT ''Mesa de Partes Virtual: personal que lo recepcionó''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_idpersonal');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD INDEX `idx_rd_mpv` (`recepcion_web`, `mpv_recepcion`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND index_name='idx_rd_mpv');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Documentos virtuales antiguos -> ya recepcionados (con su fecha de
--    registro y sin "quién", para distinguirlos de los recepcionados en el módulo)
UPDATE registro_documentos rd
   SET rd.mpv_recepcion = rd.fecha_registro
 WHERE @mpv_primera = 1
   AND COALESCE(rd.recepcion_web, 0) = 1
   AND rd.mpv_recepcion IS NULL
   AND (   rd.idoficina IS NOT NULL
        OR rd.estado_recepcion <> 0
        OR rd.fecha_registro < NOW() - INTERVAL 30 DAY
        OR EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = rd.idregistro_documentos));

-- 3) Menú del módulo (grupo Operaciones = 2)
INSERT INTO menu (idmodulo, nombre_menu, direccion, clase_llamar, dependencia, font_icon)
SELECT 1, 'Mesa de Partes Virtual', 'vistas/v_mesa_partes_virtual.php', 'Operaciones', 2, 'Operaciones' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM menu WHERE direccion = 'vistas/v_mesa_partes_virtual.php');

-- 4) Acceso para quienes ya pueden usar "Registro de Documentos"
SET @menu_mpv = (SELECT idmenu FROM menu WHERE direccion = 'vistas/v_mesa_partes_virtual.php' LIMIT 1);
SET @menu_reg = (SELECT idmenu FROM menu WHERE direccion = 'vistas/o_reg_documentos.php' LIMIT 1);
INSERT INTO accesos_usuario (idmenu, idusuario, access)
SELECT @menu_mpv, a.idusuario, 1
  FROM accesos_usuario a
 WHERE a.idmenu = @menu_reg AND a.access = 1
   AND NOT EXISTS (SELECT 1 FROM accesos_usuario b WHERE b.idmenu = @menu_mpv AND b.idusuario = a.idusuario);

-- Verificación
SELECT (SELECT COUNT(*) FROM registro_documentos WHERE COALESCE(recepcion_web,0)=1 AND mpv_recepcion IS NULL) AS mpv_por_recepcionar,
       (SELECT COUNT(*) FROM registro_documentos WHERE COALESCE(recepcion_web,0)=1 AND mpv_recepcion IS NOT NULL) AS mpv_ya_recepcionados,
       (SELECT COUNT(*) FROM accesos_usuario WHERE idmenu=@menu_mpv AND access=1) AS usuarios_con_acceso;


-- #####################################################################
-- ### 008_mpv_observar_horario.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 008: Mesa de Partes Virtual — OBSERVAR y HORARIO
--  (requiere la 007)
-- ---------------------------------------------------------------------
--  QUÉ HACE:
--   - registro_documentos.mpv_estado : NULL (normal) | 'OBSERVADO' |
--       'SUBSANADO' | 'NO_PRESENTADO'
--   - registro_documentos.mpv_plazo  : último día para subsanar
--   - registro_documentos.mpv_token  : clave del enlace "Subsanar" que se
--       envía por correo al ciudadano (se borra al usarla)
--   - Tabla sgd_mpv_evento: historial (recepcionado, observado, subsanado,
--       no presentado, derivado) que se muestra en el seguimiento público.
--   - Tabla sgd_dia_no_laborable: feriados locales y días no laborables
--       declarados (los feriados nacionales los calcula el sistema).
--
--  CÓMO EJECUTAR: phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--  Es IDEMPOTENTE.
-- =====================================================================
SET NAMES utf8mb4;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD COLUMN `mpv_estado` VARCHAR(20) NULL DEFAULT NULL COMMENT ''MPV: OBSERVADO | SUBSANADO | NO_PRESENTADO (NULL = normal)''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_estado');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD COLUMN `mpv_plazo` DATE NULL DEFAULT NULL COMMENT ''MPV: último día hábil para subsanar''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_plazo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD COLUMN `mpv_token` CHAR(40) NULL DEFAULT NULL COMMENT ''MPV: clave del enlace para subsanar''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_token');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD UNIQUE INDEX `uq_rd_mpv_token` (`mpv_token`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND index_name='uq_rd_mpv_token');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice para la bandeja "Por derivar" y el contador del menú (se consulta en cada página)
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD INDEX `idx_rd_mpv_derivar` (`recepcion_web`, `idoficina`, `mpv_idpersonal`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND index_name='idx_rd_mpv_derivar');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS `sgd_mpv_evento` (
  `idevento`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idregistro_documentos` INT NOT NULL,
  `tipo`                  ENUM('RECEPCIONADO','OBSERVADO','SUBSANADO','NO_PRESENTADO','DERIVADO') NOT NULL,
  `detalle`               VARCHAR(1000) NULL COMMENT 'Motivo de la observación / nota del ciudadano / oficina',
  `archivo`               VARCHAR(255) NULL COMMENT 'Adjunto vigente en ese momento (original o corregido)',
  `plazo`                 DATE NULL,
  `idpersonal`            INT NULL COMMENT 'NULL = el ciudadano',
  `fecha`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idevento`),
  KEY `idx_mpv_evento_reg` (`idregistro_documentos`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Historial de la Mesa de Partes Virtual (observaciones y subsanaciones)';

CREATE TABLE IF NOT EXISTS `sgd_dia_no_laborable` (
  `fecha`  DATE NOT NULL,
  `motivo` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Feriados locales y días no laborables (los nacionales los calcula el sistema)';

-- Ejemplo (descomentar y ajustar): aniversario de la provincia
-- INSERT IGNORE INTO sgd_dia_no_laborable (fecha, motivo) VALUES ('2026-12-03', 'Aniversario de la Provincia');

SELECT 'Migración 008 aplicada' AS resultado,
       (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name IN ('mpv_estado','mpv_plazo','mpv_token')) AS columnas_nuevas;


-- #####################################################################
-- ### 009_integridad_recepcion.sql
-- #####################################################################
-- =====================================================================
--  SISGEDO — MIGRACIÓN 009: Integridad de Recepción / Derivación / Archivo
-- ---------------------------------------------------------------------
--  PROBLEMAS QUE CORRIGE (encontrados al revisar los datos):
--   1) Trigger movimientos_BEFORE_DELETE: al borrar CUALQUIER movimiento de un
--      documento lo marcaba como "no recepcionado", aunque le quedaran otros.
--      El documento reaparecía en "Por recepcionar" de la oficina, se volvía a
--      recepcionar y así se generaban recepciones duplicadas.
--      -> Ahora solo lo marca pendiente si se borra su ÚLTIMO movimiento.
--   2) Documentos ya recibidos (con movimientos) que figuraban como pendientes
--      por lo anterior (aparecían otra vez en "Por recepcionar").
--      -> Se marcan como recibidos (lo que dicen sus movimientos).
--   3) Documentos archivados dos veces -> se deja el primer archivado.
--      Y se impide desde la base de datos volver a archivar dos veces.
--
--  QUÉ NO TOCA (a propósito): las recepciones/derivaciones duplicadas
--  históricas. Muchas tienen trabajo posterior colgando (derivaciones,
--  respuestas, archivos); borrarlas rompería el historial de los
--  expedientes. El sistema ya impide que se creen nuevas.
--
--  CÓMO EJECUTAR: phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--  Es IDEMPOTENTE.
-- =====================================================================
SET NAMES utf8mb4;

-- 1) Trigger corregido -------------------------------------------------
DROP TRIGGER IF EXISTS `movimientos_BEFORE_DELETE`;
DELIMITER $$
CREATE TRIGGER `movimientos_BEFORE_DELETE` BEFORE DELETE ON `movimientos` FOR EACH ROW BEGIN
	IF old.idregdocs IS NOT NULL THEN
		IF NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = old.idregdocs AND m.idMovimientos <> old.idMovimientos) THEN
			UPDATE registro_documentos SET estado_recepcion = 0 WHERE idregistro_documentos = old.idregdocs;
		END IF;
	ELSEIF old.iddocs_emitidos IS NOT NULL THEN
		IF NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.iddocs_emitidos = old.iddocs_emitidos AND m.idMovimientos <> old.idMovimientos) THEN
			UPDATE docs_emitidos SET estado = 0 WHERE iddocs_emitidos = old.iddocs_emitidos;
		END IF;
	END IF;
END$$
DELIMITER ;

-- 2) Documentos recibidos que figuraban como pendientes ----------------
UPDATE registro_documentos rd
   SET rd.estado_recepcion = 1
 WHERE rd.estado_recepcion = 0
   AND EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = rd.idregistro_documentos);

UPDATE docs_emitidos de
   SET de.estado = 1
 WHERE de.estado = 0
   AND EXISTS (SELECT 1 FROM movimientos m WHERE m.iddocs_emitidos = de.iddocs_emitidos);

-- 3) Archivados dos veces: se conserva el primero ----------------------
DELETE a FROM docs_archivados a
  JOIN docs_archivados b ON b.idmovimientos = a.idmovimientos AND b.iddocs_archivados < a.iddocs_archivados
 WHERE a.idmovimientos IS NOT NULL;
DELETE a FROM docs_archivados a
  JOIN docs_archivados b ON b.iddocs_emitidos = a.iddocs_emitidos AND b.iddocs_archivados < a.iddocs_archivados
 WHERE a.iddocs_emitidos IS NOT NULL;

-- ...y la base de datos impide archivar dos veces el mismo documento
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `docs_archivados` ADD UNIQUE INDEX `uq_da_movimiento` (`idmovimientos`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='docs_archivados' AND index_name='uq_da_movimiento');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `docs_archivados` ADD UNIQUE INDEX `uq_da_emitido` (`iddocs_emitidos`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='docs_archivados' AND index_name='uq_da_emitido');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) Índice para comprobar rápido "¿ya se envió a esa oficina y sigue pendiente?"
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `movimientos` ADD INDEX `idx_mv_ant_ofi_estado` (`idmovimientoant`, `idoficina_derivar`, `estado`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='movimientos' AND index_name='idx_mv_ant_ofi_estado');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 5) Índice para el panel de Supervisión del administrador (pendientes por oficina)
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `movimientos` ADD INDEX `idx_mv_estado_ofi` (`estado`, `idoficina_derivar`, `fecha_hora`, `idmovimientoant`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='movimientos' AND index_name='idx_mv_estado_ofi');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Verificación: las tres primeras columnas deben salir en 0
SELECT
  (SELECT COUNT(*) FROM registro_documentos rd WHERE rd.estado_recepcion = 0
      AND EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = rd.idregistro_documentos)) AS registro_recibido_como_pendiente,
  (SELECT COUNT(*) FROM docs_emitidos de WHERE de.estado = 0
      AND EXISTS (SELECT 1 FROM movimientos m WHERE m.iddocs_emitidos = de.iddocs_emitidos)) AS emitido_recibido_como_pendiente,
  (SELECT COUNT(*) FROM (SELECT idmovimientos FROM docs_archivados WHERE idmovimientos IS NOT NULL
      GROUP BY idmovimientos HAVING COUNT(*) > 1) x) AS archivados_dos_veces,
  'Migración 009 aplicada' AS resultado;


SELECT 'ACTUALIZACIÓN COMPLETA TERMINADA' AS resultado;
