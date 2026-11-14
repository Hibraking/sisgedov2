-- =====================================================================
--  SISGEDO — MIGRACIÓN 011: ARRANQUE DESDE CERO (cambio de sistema)
-- ---------------------------------------------------------------------
--  ¡SE EJECUTA UNA SOLA VEZ! Para el inicio del SISGEDO v2 (16/11/2026).
--  NO forma parte de ACTUALIZAR_BD_COMPLETO.sql.
--
--  QUÉ HACE (en la BD del sistema NUEVO, xyltrylx_sisgedov2):
--   1) Lee del sistema ANTERIOR (xyltrylx_sisgedo_muni) los últimos números
--      usados en 2026 y los deja como punto de partida de los contadores:
--        - N° de expediente (EXP-2026)           -> sigue con el siguiente.
--        - N° de documentos emitidos por oficina y tipo (EMI-...),
--          ej. si la Gerencia Municipal llegó a INFORME 245-2026-..., el
--          próximo será 246-2026-MPH/GM.
--        - N° de registro interno por oficina (MOV-...).
--        - Los ID internos (registro, emitidos, movimientos) continúan
--          después de los del sistema anterior.
--   2) Copia los tipos de documento que se crearon en el sistema anterior
--      y que aún no existen en el nuevo.
--   3) BORRA todos los documentos del sistema nuevo: registros, emitidos,
--      movimientos, archivados, eventos de Mesa de Partes Virtual, huellas
--      de archivos, códigos de verificación, firmas y registro de correos.
--
--  QUÉ CONSERVA: oficinas, personal, usuarios, cargos (responsables),
--  permisos, menú, tipos de documento, tipos de trámite, remitentes,
--  archivadores, acciones, estados y días no laborables.
--
--  ANTES DE EJECUTAR:
--   a) Que ya NADIE registre nada en el sistema anterior (cerrar su Mesa de
--      Partes Virtual y la ventanilla). Lo que se registre allí después
--      tendría números que el sistema nuevo volvería a usar.
--   b) Exportar una copia de seguridad de AMBAS bases de datos.
--   c) En cPanel > Bases de datos MySQL: el usuario de MySQL con el que se
--      entra a phpMyAdmin debe tener permiso sobre las dos bases.
--
--  CÓMO EJECUTAR: phpMyAdmin > seleccionar xyltrylx_sisgedov2 > pestaña SQL
--  > pegar todo > Continuar. Al final muestra el resumen de los números con
--  los que arranca el sistema.
--
--  SEGURIDAD: si ya hay documentos registrados desde el 16/11/2026 en el
--  sistema nuevo, se detiene sin borrar nada (evita ejecutarlo dos veces).
--  Si no puede leer la BD anterior, también se detiene ANTES de borrar.
-- =====================================================================
SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS sgd_arranque_cero;
DELIMITER $$
CREATE PROCEDURE sgd_arranque_cero()
BEGIN
    DECLARE v_inicio DATETIME DEFAULT '2026-11-16 00:00:00';
    DECLARE v_anio   INT      DEFAULT 2026;
    DECLARE v_exp, v_reg, v_emi, v_mov, v_arc INT DEFAULT 0;

    -- 0) Protección: no volver a ejecutarlo con el sistema ya en uso
    IF EXISTS (SELECT 1 FROM registro_documentos WHERE fecha_registro >= v_inicio)
       OR EXISTS (SELECT 1 FROM docs_emitidos WHERE fecha >= v_inicio)
       OR EXISTS (SELECT 1 FROM movimientos WHERE fecha_hora >= v_inicio) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'DETENIDO: ya hay documentos del 16/11/2026 en adelante. No se borro nada.';
    END IF;

    -- 1) Leer TODO del sistema anterior antes de borrar nada -------------
    SELECT IFNULL(MAX(CAST(SUBSTRING(nro_exp, 5) AS UNSIGNED)), 0) INTO v_exp
      FROM xyltrylx_sisgedo_muni.registro_documentos
     WHERE nro_exp LIKE CONCAT(v_anio, '%') AND fecha_registro >= CONCAT(v_anio, '-01-01');

    SELECT GREATEST(IFNULL((SELECT MAX(idregistro_documentos) FROM xyltrylx_sisgedo_muni.registro_documentos), 0),
                    IFNULL((SELECT MAX(idregistro_documentos) FROM registro_documentos), 0)),
           GREATEST(IFNULL((SELECT MAX(iddocs_emitidos) FROM xyltrylx_sisgedo_muni.docs_emitidos), 0),
                    IFNULL((SELECT MAX(iddocs_emitidos) FROM docs_emitidos), 0)),
           GREATEST(IFNULL((SELECT MAX(idMovimientos) FROM xyltrylx_sisgedo_muni.movimientos), 0),
                    IFNULL((SELECT MAX(idMovimientos) FROM movimientos), 0)),
           GREATEST(IFNULL((SELECT MAX(iddocs_archivados) FROM xyltrylx_sisgedo_muni.docs_archivados), 0),
                    IFNULL((SELECT MAX(iddocs_archivados) FROM docs_archivados), 0))
      INTO v_reg, v_emi, v_mov, v_arc;

    -- 1b) Tipos de documento creados en el sistema anterior que faltan aquí
    --     (misma comparación que la migración 006: sin mayúsculas/tildes/espacios de más)
    INSERT INTO documentos (nombre_documento, descripcion, dias_atension)
    SELECT MIN(TRIM(CONVERT(d.nombre_documento USING utf8mb4))), MIN(CONVERT(d.descripcion USING utf8mb4)), MIN(d.dias_atension)
      FROM xyltrylx_sisgedo_muni.documentos d
     WHERE TRIM(IFNULL(d.nombre_documento, '')) <> ''
       AND NOT EXISTS (SELECT 1 FROM documentos n
                        WHERE UPPER(REGEXP_REPLACE(TRIM(n.nombre_documento), ' {2,}', ' ')) COLLATE utf8mb4_general_ci
                            = UPPER(REGEXP_REPLACE(TRIM(CONVERT(d.nombre_documento USING utf8mb4)), ' {2,}', ' ')) COLLATE utf8mb4_general_ci)
     GROUP BY UPPER(REGEXP_REPLACE(TRIM(CONVERT(d.nombre_documento USING utf8mb4)), ' {2,}', ' ')) COLLATE utf8mb4_general_ci;

    -- Equivalencia: id del tipo en el sistema anterior -> id en el nuevo
    DROP TEMPORARY TABLE IF EXISTS tmp_doc_eq;
    CREATE TEMPORARY TABLE tmp_doc_eq (viejo INT PRIMARY KEY, nuevo INT NOT NULL);
    INSERT INTO tmp_doc_eq
    SELECT d.iddocumentos, MIN(n.iddocumentos)
      FROM xyltrylx_sisgedo_muni.documentos d
      JOIN documentos n
        ON UPPER(REGEXP_REPLACE(TRIM(n.nombre_documento), ' {2,}', ' ')) COLLATE utf8mb4_general_ci
         = UPPER(REGEXP_REPLACE(TRIM(CONVERT(d.nombre_documento USING utf8mb4)), ' {2,}', ' ')) COLLATE utf8mb4_general_ci
     GROUP BY d.iddocumentos;

    -- Último correlativo emitido en el año por oficina emisora + tipo
    -- (mismo criterio que sgd_extraer_correlativo: "NNN-2026" dentro del número)
    DROP TEMPORARY TABLE IF EXISTS tmp_contadores;
    CREATE TEMPORARY TABLE tmp_contadores (clave VARCHAR(80) PRIMARY KEY, ultimo INT UNSIGNED NOT NULL);

    INSERT INTO tmp_contadores VALUES (CONCAT('EXP-', v_anio), v_exp);

    INSERT INTO tmp_contadores
    SELECT CONCAT('EMI-', x.ofi, '-', x.doc, '-', v_anio), MAX(x.n)
      FROM (SELECT de.idoficina_registro AS ofi, eq.nuevo AS doc,
                   CAST(SUBSTRING_INDEX(REGEXP_SUBSTR(CONVERT(de.numero USING utf8mb4),
                        CONCAT('[0-9]{1,6}[[:space:]]*-[[:space:]]*', v_anio)), '-', 1) AS UNSIGNED) AS n
              FROM xyltrylx_sisgedo_muni.docs_emitidos de
              JOIN tmp_doc_eq eq ON eq.viejo = de.iddocumento
             WHERE de.fecha >= CONCAT(v_anio, '-01-01')
               AND de.idoficina_registro IS NOT NULL
               AND CONVERT(de.numero USING utf8mb4) REGEXP CONCAT('[0-9]{1,6}[[:space:]]*-[[:space:]]*', v_anio)) x
     WHERE x.n > 0
     GROUP BY x.ofi, x.doc;

    INSERT INTO tmp_contadores
    SELECT CONCAT('MOV-', r.idoficina, '-', v_anio), MAX(CAST(SUBSTRING(m.num_exp, 5) AS UNSIGNED))
      FROM xyltrylx_sisgedo_muni.movimientos m
      JOIN xyltrylx_sisgedo_muni.responsables r ON r.idresponsables = m.idresponsable
     WHERE m.num_exp LIKE CONCAT(v_anio, '%') AND m.fecha_hora >= CONCAT(v_anio, '-01-01')
       AND r.idoficina IS NOT NULL
     GROUP BY r.idoficina
    HAVING MAX(CAST(SUBSTRING(m.num_exp, 5) AS UNSIGNED)) > 0;

    -- 2) Borrar los documentos del sistema nuevo -------------------------
    SET FOREIGN_KEY_CHECKS = 0;
    TRUNCATE TABLE docs_archivados;
    TRUNCATE TABLE movimientos;
    TRUNCATE TABLE docs_emitidos;
    TRUNCATE TABLE registro_documentos;
    TRUNCATE TABLE sgd_mpv_evento;
    TRUNCATE TABLE sgd_archivo;
    TRUNCATE TABLE sgd_verificacion;
    TRUNCATE TABLE sgd_firma;
    TRUNCATE TABLE sgd_correo;
    TRUNCATE TABLE sgd_secuencia;
    SET FOREIGN_KEY_CHECKS = 1;

    -- 3) Contadores de numeración y continuación de los ID ---------------
    INSERT INTO sgd_secuencia (clave, ultimo) SELECT clave, ultimo FROM tmp_contadores;

    SET @sgd_sql = CONCAT('ALTER TABLE registro_documentos AUTO_INCREMENT = ', v_reg + 1);
    PREPARE s FROM @sgd_sql; EXECUTE s; DEALLOCATE PREPARE s;
    SET @sgd_sql = CONCAT('ALTER TABLE docs_emitidos AUTO_INCREMENT = ', v_emi + 1);
    PREPARE s FROM @sgd_sql; EXECUTE s; DEALLOCATE PREPARE s;
    SET @sgd_sql = CONCAT('ALTER TABLE movimientos AUTO_INCREMENT = ', v_mov + 1);
    PREPARE s FROM @sgd_sql; EXECUTE s; DEALLOCATE PREPARE s;
    SET @sgd_sql = CONCAT('ALTER TABLE docs_archivados AUTO_INCREMENT = ', v_arc + 1);
    PREPARE s FROM @sgd_sql; EXECUTE s; DEALLOCATE PREPARE s;

    -- 4) Resumen
    SELECT CONCAT(v_anio, LPAD(v_exp + 1, 4, '0')) AS proximo_expediente,
           (SELECT COUNT(*) FROM sgd_secuencia WHERE clave LIKE 'EMI-%') AS contadores_emitidos,
           (SELECT COUNT(*) FROM sgd_secuencia WHERE clave LIKE 'MOV-%') AS contadores_registro_interno,
           v_reg + 1 AS proximo_id_registro, v_emi + 1 AS proximo_id_emitido, v_mov + 1 AS proximo_id_movimiento;

    DROP TEMPORARY TABLE IF EXISTS tmp_doc_eq;
    DROP TEMPORARY TABLE IF EXISTS tmp_contadores;
END$$
DELIMITER ;

CALL sgd_arranque_cero();
DROP PROCEDURE IF EXISTS sgd_arranque_cero;

-- Próximos números de documentos emitidos por oficina (los 20 más altos)
SELECT o.siglas, d.nombre_documento, s.ultimo + 1 AS proximo
  FROM sgd_secuencia s
  JOIN oficinas o   ON o.idoficinas   = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(s.clave, '-', 2), '-', -1) AS UNSIGNED)
  JOIN documentos d ON d.iddocumentos = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(s.clave, '-', 3), '-', -1) AS UNSIGNED)
 WHERE s.clave LIKE 'EMI-%'
 ORDER BY s.ultimo DESC
 LIMIT 20;
