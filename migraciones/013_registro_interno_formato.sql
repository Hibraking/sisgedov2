-- =====================================================================
--  SISGEDO — MIGRACIÓN 013: Formato propio del N° de registro de cada oficina
-- ---------------------------------------------------------------------
--  Antes, cuando una oficina recibía un documento, se le asignaba un
--  "registro interno" con el MISMO formato que un expediente (20260594),
--  y se confundía con el expediente. Desde ahora usa las siglas de la
--  oficina:  GODUyR-2026-0596  (siglas-año-correlativo).
--  El correlativo CONTINÚA el de cada oficina (no vuelve a 1).
--
--  Esta migración solo amplía la columna (antes 15 caracteres) para que
--  quepan las siglas. Los registros antiguos se conservan tal cual.
--  Es IDEMPOTENTE (se puede repetir).
-- =====================================================================
SET NAMES utf8mb4;

SET @largo := (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'movimientos' AND column_name = 'num_exp');
SET @sql := IF(@largo IS NOT NULL AND @largo < 30,
               'ALTER TABLE movimientos MODIFY num_exp VARCHAR(30) NULL',
               'SELECT ''num_exp ya tiene el tamaño correcto'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'Migración 013 aplicada' AS resultado,
       (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'movimientos' AND column_name = 'num_exp') AS largo_num_exp;
