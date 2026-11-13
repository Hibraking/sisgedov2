-- =====================================================================
--  SISGEDO — MIGRACIÓN 007: Módulo "Mesa de Partes Virtual"
-- ---------------------------------------------------------------------
--  QUÉ HACE:
--   Los documentos que llegan por internet (Mesa de Partes Virtual) tienen
--   ahora su propia bandeja. El operador de mesa de partes:
--     1) RECEPCIONA el documento (el ciudadano recibe un correo de aviso),
--     2) luego lo DERIVA a la oficina que corresponde.
--   - registro_documentos.mpv_recepcion   : fecha/hora en que mesa de partes
--                                            lo recepcionó (NULL = pendiente)
--   - registro_documentos.mpv_idpersonal  : quién lo recepcionó
--   - Documentos virtuales ANTIGUOS: se marcan como ya recepcionados (con su
--     fecha de registro) para que la bandeja empiece solo con lo reciente:
--     los ya derivados/atendidos y los de más de 30 días (mismo criterio que
--     la etiqueta "NEW" que usaba Registro).
--   - Menú "Mesa de Partes Virtual" (Operaciones) y acceso para quienes ya
--     tienen acceso a "Registro de Documentos".
--
--  CÓMO EJECUTAR:
--   - phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--   - Local: mysql -u root sisgedov2 < migraciones/007_mesa_partes_virtual.sql
--  Es IDEMPOTENTE: se puede ejecutar varias veces sin duplicar nada.
--  Ejecutarla ANTES de subir el código nuevo (el código funciona igual sin
--  ella, pero el módulo nuevo necesita estas columnas).
-- =====================================================================
SET NAMES utf8mb4;

-- 1) Columnas nuevas
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD COLUMN `mpv_recepcion` DATETIME NULL DEFAULT NULL COMMENT ''Mesa de Partes Virtual: fecha en que mesa de partes lo recepcionó (NULL = pendiente)''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_recepcion');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD COLUMN `mpv_idpersonal` INT NULL DEFAULT NULL COMMENT ''Mesa de Partes Virtual: personal que lo recepcionó''',
  'DO 0') FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND column_name='mpv_idpersonal');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `registro_documentos` ADD INDEX `idx_rd_mpv` (`recepcion_web`, `mpv_recepcion`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='registro_documentos' AND index_name='idx_rd_mpv');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Documentos virtuales antiguos -> ya recepcionados (con su fecha de
--    registro y sin "quién", para distinguirlos de los recepcionados en el módulo)
UPDATE registro_documentos rd
   SET rd.mpv_recepcion = rd.fecha_registro
 WHERE COALESCE(rd.recepcion_web, 0) = 1
   AND rd.mpv_recepcion IS NULL
   AND (   rd.idoficina IS NOT NULL
        OR rd.estado_recepcion <> 0
        OR rd.fecha_registro < NOW() - INTERVAL 30 DAY
        OR EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = rd.idregistro_documentos));

-- 3) Menú del módulo (grupo Operaciones = 2)
INSERT INTO menu (idmodulo, nombre_menu, direccion, clase_llamar, dependencia, font_icon)
SELECT 1, 'Mesa de Partes Virtual', 'vistas/v_mesa_partes_virtual.php', 'Operaciones', 2, 'Operaciones' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM menu WHERE direccion = 'vistas/v_mesa_partes_virtual.php');

-- 4) Acceso para quienes ya pueden usar "Registro de Documentos"
SET @menu_mpv = (SELECT idmenu FROM menu WHERE direccion = 'vistas/v_mesa_partes_virtual.php' LIMIT 1);
SET @menu_reg = (SELECT idmenu FROM menu WHERE direccion = 'vistas/o_reg_documentos.php' LIMIT 1);
INSERT INTO accesos_usuario (idmenu, idusuario, access)
SELECT @menu_mpv, a.idusuario, 1
  FROM accesos_usuario a
 WHERE a.idmenu = @menu_reg AND a.access = 1
   AND NOT EXISTS (SELECT 1 FROM accesos_usuario b WHERE b.idmenu = @menu_mpv AND b.idusuario = a.idusuario);

-- Verificación
SELECT (SELECT COUNT(*) FROM registro_documentos WHERE COALESCE(recepcion_web,0)=1 AND mpv_recepcion IS NULL) AS mpv_por_recepcionar,
       (SELECT COUNT(*) FROM registro_documentos WHERE COALESCE(recepcion_web,0)=1 AND mpv_recepcion IS NOT NULL) AS mpv_ya_recepcionados,
       (SELECT COUNT(*) FROM accesos_usuario WHERE idmenu=@menu_mpv AND access=1) AS usuarios_con_acceso;
