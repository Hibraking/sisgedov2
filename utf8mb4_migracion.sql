-- =====================================================================
--  MIGRACIÓN DE CHARSET: utf8mb3  ->  utf8mb4   -  SISGEDO
--  Unifica el charset de las tablas con el de la conexión (utf8mb4).
--  SEGURO en MySQL 8.x. Reversible. Idempotente (re-ejecutar no daña nada).
--  Puede tardar en las tablas grandes (movimientos, registro_documentos).
--
--  Ejecutar por CMD:
--    mysql -u root xyltrylx_sisgedo_muni < utf8mb4_migracion.sql
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `accesos_usuario`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `aciones_documento`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `archivador`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `docs_archivados`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `docs_emitidos`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `documentos`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `estado_documento`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `menu`                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `modulo`              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `movimientos`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `oficinas`            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `personal`            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `registro_documentos` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `remitente`           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `responsables`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `tipo_tram`           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `usuario`             CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación: charset por tabla (debe decir utf8mb4)
SELECT table_name AS tabla, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;
