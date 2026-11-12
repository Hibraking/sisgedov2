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
