-- ============================================================
-- SISGEDO: registro del último acceso de cada usuario
-- Ejecutar UNA sola vez en la base de datos (HeidiSQL / phpMyAdmin
-- o consola MySQL de Laragon):
--
--   mysql -u root xyltrylx_sisgedo_muni < ultimo_acceso.sql
--
-- El código PHP es tolerante: si la columna aún no existe,
-- el login sigue funcionando y el perfil usa el valor de respaldo.
-- ============================================================

ALTER TABLE personal
  ADD COLUMN ultimo_acceso DATETIME NULL DEFAULT NULL
  COMMENT 'Fecha y hora del último inicio de sesión (lo actualiza clases/login.php)';
