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
