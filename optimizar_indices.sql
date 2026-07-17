-- =====================================================================
--  OPTIMIZACIÓN: ÍNDICES DE BASE DE DATOS  -  SISGEDO
--  Acelera los JOIN/WHERE de las consultas (recepción, registro, reportes).
--  SEGURO: solo AGREGA índices (no cambia datos). Idempotente: si un índice
--  ya existe, se salta. Reversible con DROP INDEX.
--
--  Ejecutar por CMD:
--    mysql -u root xyltrylx_sisgedo_muni < optimizar_indices.sql
-- =====================================================================

-- Procedimiento auxiliar: crea un índice solo si no existe
DROP PROCEDURE IF EXISTS sisgedo_add_index;
DELIMITER $$
CREATE PROCEDURE sisgedo_add_index(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64), IN p_cols VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name  = p_tabla
          AND index_name  = p_indice
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD INDEX `', p_indice, '` (', p_cols, ')');
        PREPARE st FROM @sql;
        EXECUTE st;
        DEALLOCATE PREPARE st;
    END IF;
END$$
DELIMITER ;

-- ---- personal (login busca por usuario) ----
CALL sisgedo_add_index('personal', 'idx_personal_usuario', '`usuario`');

-- ---- registro_documentos (mesa de partes / externos) ----
CALL sisgedo_add_index('registro_documentos', 'idx_rd_iddocumento',    '`iddocumento`');
CALL sisgedo_add_index('registro_documentos', 'idx_rd_idremitente',    '`idremitente`');
CALL sisgedo_add_index('registro_documentos', 'idx_rd_idtipo_tramite', '`idtipo_tramite`');
CALL sisgedo_add_index('registro_documentos', 'idx_rd_idoficina',      '`idoficina`');
CALL sisgedo_add_index('registro_documentos', 'idx_rd_nro_exp',        '`nro_exp`');
CALL sisgedo_add_index('registro_documentos', 'idx_rd_fecha',          '`fecha_registro`');
CALL sisgedo_add_index('registro_documentos', 'idx_rd_idpersonal',     '`idpersonal`');

-- ---- movimientos (la tabla más grande: recepción/derivación) ----
CALL sisgedo_add_index('movimientos', 'idx_mv_idregdocs',       '`idregdocs`');
CALL sisgedo_add_index('movimientos', 'idx_mv_iddocs_emitidos', '`iddocs_emitidos`');
CALL sisgedo_add_index('movimientos', 'idx_mv_idacciondocs',    '`idacciondocs`');
CALL sisgedo_add_index('movimientos', 'idx_mv_idoficina_deriv', '`idoficina_derivar`');
CALL sisgedo_add_index('movimientos', 'idx_mv_idestadodocs',    '`idestadodocs`');
CALL sisgedo_add_index('movimientos', 'idx_mv_idresponsable',   '`idresponsable`');
CALL sisgedo_add_index('movimientos', 'idx_mv_fecha',           '`fecha_hora`');
CALL sisgedo_add_index('movimientos', 'idx_mv_movant',          '`idmovimientoant`');

-- ---- docs_emitidos (documentos internos) ----
CALL sisgedo_add_index('docs_emitidos', 'idx_de_iddocumento',      '`iddocumento`');
CALL sisgedo_add_index('docs_emitidos', 'idx_de_idresponsable',    '`idresponsable`');
CALL sisgedo_add_index('docs_emitidos', 'idx_de_idoficina',        '`idoficina`');
CALL sisgedo_add_index('docs_emitidos', 'idx_de_idoficina_reg',    '`idoficina_registro`');
CALL sisgedo_add_index('docs_emitidos', 'idx_de_fecha',            '`fecha`');

-- ---- docs_archivados ----
CALL sisgedo_add_index('docs_archivados', 'idx_da_idarchivador',    '`idarchivador`');
CALL sisgedo_add_index('docs_archivados', 'idx_da_iddocs_emitidos', '`iddocs_emitidos`');
CALL sisgedo_add_index('docs_archivados', 'idx_da_idmovimientos',   '`idmovimientos`');

-- ---- accesos_usuario (autorización) ----
CALL sisgedo_add_index('accesos_usuario', 'idx_au_idusuario', '`idusuario`');
CALL sisgedo_add_index('accesos_usuario', 'idx_au_idmenu',    '`idmenu`');

-- ---- responsables ----
CALL sisgedo_add_index('responsables', 'idx_resp_idpersonal', '`idpersonal`');
CALL sisgedo_add_index('responsables', 'idx_resp_idoficina',  '`idoficina`');

-- ---- remitente (búsqueda por DNI/email en mesa de partes) ----
CALL sisgedo_add_index('remitente', 'idx_rem_dni',   '`d_identidad`');
CALL sisgedo_add_index('remitente', 'idx_rem_email', '`email`');

-- Limpieza del procedimiento auxiliar
DROP PROCEDURE IF EXISTS sisgedo_add_index;

-- Verificación: cuántos índices tiene ahora cada tabla clave
SELECT table_name AS tabla, COUNT(DISTINCT index_name) AS indices
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name IN ('personal','registro_documentos','movimientos','docs_emitidos',
                     'docs_archivados','accesos_usuario','responsables','remitente')
GROUP BY table_name;
