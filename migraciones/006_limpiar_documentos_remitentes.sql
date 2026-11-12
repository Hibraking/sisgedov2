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
