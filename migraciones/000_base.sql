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
