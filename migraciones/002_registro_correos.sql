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
