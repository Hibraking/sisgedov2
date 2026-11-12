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

