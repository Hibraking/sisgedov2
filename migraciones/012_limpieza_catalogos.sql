-- =====================================================================
--  SISGEDO — MIGRACIÓN 012: Limpieza de catálogos
-- ---------------------------------------------------------------------
--  QUÉ HACE (no se pierde ningún documento):
--   1) Quita espacios sobrantes (al final o dobles) en los nombres de
--      tipos de documento, tipos de trámite y oficinas.
--   2) Une el tipo de documento mal escrito "DECLARACION JUARADA" con
--      "DECLARACIÓN JURADA" (le pasa sus documentos y lo elimina).
--   3) Tipos de documento SIN NOMBRE: borra los que nadie usa; los que
--      tienen documentos pasan a llamarse "SIN ESPECIFICAR".
--   4) Oficinas SIN NOMBRE: borra solo las que no usa nada (ni cargos, ni
--      documentos, ni movimientos, ni archivadores).
--
--  CÓMO EJECUTAR: phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--  Es IDEMPOTENTE (se puede repetir). Requiere MySQL 8.
-- =====================================================================
SET NAMES utf8mb4;

-- 1) Espacios sobrantes -------------------------------------------------
--    (CHAR_LENGTH: la comparación de texto de MySQL ignora espacios finales)
UPDATE documentos SET nombre_documento = TRIM(REGEXP_REPLACE(nombre_documento, ' {2,}', ' '))
 WHERE nombre_documento IS NOT NULL
   AND CHAR_LENGTH(nombre_documento) <> CHAR_LENGTH(TRIM(REGEXP_REPLACE(nombre_documento, ' {2,}', ' ')));
UPDATE tipo_tram SET nombre = TRIM(REGEXP_REPLACE(nombre, ' {2,}', ' '))
 WHERE nombre IS NOT NULL
   AND CHAR_LENGTH(nombre) <> CHAR_LENGTH(TRIM(REGEXP_REPLACE(nombre, ' {2,}', ' ')));
UPDATE oficinas SET nombre = TRIM(REGEXP_REPLACE(nombre, ' {2,}', ' '))
 WHERE nombre IS NOT NULL
   AND CHAR_LENGTH(nombre) <> CHAR_LENGTH(TRIM(REGEXP_REPLACE(nombre, ' {2,}', ' ')));

-- 2) "DECLARACION JUARADA" -> "DECLARACIÓN JURADA" ---------------------
SET @bueno := (SELECT MIN(iddocumentos) FROM documentos
                WHERE UPPER(nombre_documento) COLLATE utf8mb4_general_ci = 'DECLARACION JURADA');
DROP TEMPORARY TABLE IF EXISTS tmp_doc_malo;
CREATE TEMPORARY TABLE tmp_doc_malo (id INT PRIMARY KEY);
INSERT INTO tmp_doc_malo
SELECT iddocumentos FROM documentos
 WHERE UPPER(nombre_documento) COLLATE utf8mb4_general_ci = 'DECLARACION JUARADA' AND @bueno IS NOT NULL;

UPDATE registro_documentos SET iddocumento = @bueno WHERE iddocumento IN (SELECT id FROM tmp_doc_malo);
-- Emitidos: el trigger de UPDATE reescribiría la oficina emisora histórica;
-- se quita SOLO durante este cambio y se vuelve a crear idéntico.
DROP TRIGGER IF EXISTS `docs_emitidos_BEFORE_UPDATE`;
UPDATE docs_emitidos SET iddocumento = @bueno WHERE iddocumento IN (SELECT id FROM tmp_doc_malo);
DELIMITER $$
CREATE TRIGGER `docs_emitidos_BEFORE_UPDATE` BEFORE UPDATE ON `docs_emitidos` FOR EACH ROW BEGIN
	 set new.idoficina_registro=(select idoficina from responsables where idresponsables=new.idresponsable);
END$$
DELIMITER ;
DELETE FROM sgd_secuencia WHERE clave REGEXP '^EMI-[0-9]+-[0-9]+-'
   AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(clave, '-', 3), '-', -1) AS UNSIGNED) IN (SELECT id FROM tmp_doc_malo);
DELETE FROM documentos WHERE iddocumentos IN (SELECT id FROM tmp_doc_malo);
DROP TEMPORARY TABLE IF EXISTS tmp_doc_malo;

-- 3) Tipos de documento sin nombre ---------------------------------------
DELETE d FROM documentos d
 WHERE TRIM(COALESCE(d.nombre_documento, '')) IN ('', '-')
   AND NOT EXISTS (SELECT 1 FROM registro_documentos r WHERE r.iddocumento = d.iddocumentos)
   AND NOT EXISTS (SELECT 1 FROM docs_emitidos e WHERE e.iddocumento = d.iddocumentos);
UPDATE documentos SET nombre_documento = 'SIN ESPECIFICAR'
 WHERE TRIM(COALESCE(nombre_documento, '')) IN ('', '-');

-- 4) Oficinas sin nombre que nada usa ------------------------------------
DELETE o FROM oficinas o
 WHERE TRIM(COALESCE(o.nombre, '')) IN ('', '-')
   AND NOT EXISTS (SELECT 1 FROM responsables r WHERE r.idoficina = o.idoficinas)
   AND NOT EXISTS (SELECT 1 FROM registro_documentos rd WHERE rd.idoficina = o.idoficinas)
   AND NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.idoficina_derivar = o.idoficinas)
   AND NOT EXISTS (SELECT 1 FROM docs_emitidos e WHERE e.idoficina = o.idoficinas OR e.idoficina_registro = o.idoficinas)
   AND NOT EXISTS (SELECT 1 FROM archivador a WHERE a.idoficina = o.idoficinas);

SELECT 'Migración 012 aplicada' AS resultado,
       (SELECT COUNT(*) FROM documentos WHERE TRIM(COALESCE(nombre_documento,'')) IN ('','-')) AS docs_sin_nombre,
       (SELECT COUNT(*) FROM documentos WHERE UPPER(nombre_documento) COLLATE utf8mb4_general_ci = 'DECLARACION JUARADA') AS juarada,
       (SELECT COUNT(*) FROM oficinas WHERE TRIM(COALESCE(nombre,'')) IN ('','-')) AS oficinas_sin_nombre_en_uso;
