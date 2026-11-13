-- =====================================================================
--  SISGEDO — MIGRACIÓN 009: Integridad de Recepción / Derivación / Archivo
-- ---------------------------------------------------------------------
--  PROBLEMAS QUE CORRIGE (encontrados al revisar los datos):
--   1) Trigger movimientos_BEFORE_DELETE: al borrar CUALQUIER movimiento de un
--      documento lo marcaba como "no recepcionado", aunque le quedaran otros.
--      El documento reaparecía en "Por recepcionar" de la oficina, se volvía a
--      recepcionar y así se generaban recepciones duplicadas.
--      -> Ahora solo lo marca pendiente si se borra su ÚLTIMO movimiento.
--   2) Documentos ya recibidos (con movimientos) que figuraban como pendientes
--      por lo anterior (aparecían otra vez en "Por recepcionar").
--      -> Se marcan como recibidos (lo que dicen sus movimientos).
--   3) Documentos archivados dos veces -> se deja el primer archivado.
--      Y se impide desde la base de datos volver a archivar dos veces.
--
--  QUÉ NO TOCA (a propósito): las recepciones/derivaciones duplicadas
--  históricas. Muchas tienen trabajo posterior colgando (derivaciones,
--  respuestas, archivos); borrarlas rompería el historial de los
--  expedientes. El sistema ya impide que se creen nuevas.
--
--  CÓMO EJECUTAR: phpMyAdmin > BD > pestaña SQL > pegar todo > Continuar.
--  Es IDEMPOTENTE.
-- =====================================================================
SET NAMES utf8mb4;

-- 1) Trigger corregido -------------------------------------------------
DROP TRIGGER IF EXISTS `movimientos_BEFORE_DELETE`;
DELIMITER $$
CREATE TRIGGER `movimientos_BEFORE_DELETE` BEFORE DELETE ON `movimientos` FOR EACH ROW BEGIN
	IF old.idregdocs IS NOT NULL THEN
		IF NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = old.idregdocs AND m.idMovimientos <> old.idMovimientos) THEN
			UPDATE registro_documentos SET estado_recepcion = 0 WHERE idregistro_documentos = old.idregdocs;
		END IF;
	ELSEIF old.iddocs_emitidos IS NOT NULL THEN
		IF NOT EXISTS (SELECT 1 FROM movimientos m WHERE m.iddocs_emitidos = old.iddocs_emitidos AND m.idMovimientos <> old.idMovimientos) THEN
			UPDATE docs_emitidos SET estado = 0 WHERE iddocs_emitidos = old.iddocs_emitidos;
		END IF;
	END IF;
END$$
DELIMITER ;

-- 2) Documentos recibidos que figuraban como pendientes ----------------
UPDATE registro_documentos rd
   SET rd.estado_recepcion = 1
 WHERE rd.estado_recepcion = 0
   AND EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = rd.idregistro_documentos);

UPDATE docs_emitidos de
   SET de.estado = 1
 WHERE de.estado = 0
   AND EXISTS (SELECT 1 FROM movimientos m WHERE m.iddocs_emitidos = de.iddocs_emitidos);

-- 3) Archivados dos veces: se conserva el primero ----------------------
DELETE a FROM docs_archivados a
  JOIN docs_archivados b ON b.idmovimientos = a.idmovimientos AND b.iddocs_archivados < a.iddocs_archivados
 WHERE a.idmovimientos IS NOT NULL;
DELETE a FROM docs_archivados a
  JOIN docs_archivados b ON b.iddocs_emitidos = a.iddocs_emitidos AND b.iddocs_archivados < a.iddocs_archivados
 WHERE a.iddocs_emitidos IS NOT NULL;

-- ...y la base de datos impide archivar dos veces el mismo documento
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `docs_archivados` ADD UNIQUE INDEX `uq_da_movimiento` (`idmovimientos`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='docs_archivados' AND index_name='uq_da_movimiento');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `docs_archivados` ADD UNIQUE INDEX `uq_da_emitido` (`iddocs_emitidos`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='docs_archivados' AND index_name='uq_da_emitido');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) Índice para comprobar rápido "¿ya se envió a esa oficina y sigue pendiente?"
SET @s=(SELECT IF(COUNT(*)=0,
  'ALTER TABLE `movimientos` ADD INDEX `idx_mv_ant_ofi_estado` (`idmovimientoant`, `idoficina_derivar`, `estado`)',
  'DO 0') FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='movimientos' AND index_name='idx_mv_ant_ofi_estado');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Verificación: las tres primeras columnas deben salir en 0
SELECT
  (SELECT COUNT(*) FROM registro_documentos rd WHERE rd.estado_recepcion = 0
      AND EXISTS (SELECT 1 FROM movimientos m WHERE m.idregdocs = rd.idregistro_documentos)) AS registro_recibido_como_pendiente,
  (SELECT COUNT(*) FROM docs_emitidos de WHERE de.estado = 0
      AND EXISTS (SELECT 1 FROM movimientos m WHERE m.iddocs_emitidos = de.iddocs_emitidos)) AS emitido_recibido_como_pendiente,
  (SELECT COUNT(*) FROM (SELECT idmovimientos FROM docs_archivados WHERE idmovimientos IS NOT NULL
      GROUP BY idmovimientos HAVING COUNT(*) > 1) x) AS archivados_dos_veces,
  'Migración 009 aplicada' AS resultado;
