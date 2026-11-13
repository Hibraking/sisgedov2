<?php
/* =====================================================================
   SISGEDO — Utilidades del módulo Mesa de Partes Virtual (MPV)
   ---------------------------------------------------------------------
   El módulo necesita las columnas registro_documentos.mpv_recepcion y
   mpv_idpersonal (migraciones/007_mesa_partes_virtual.sql). Si el código
   se sube antes que la migración, todo sigue funcionando como antes
   (sgd_mpv_ok() devuelve false y no se usan esas columnas).
   ===================================================================== */

if (!function_exists('sgd_mpv_ok')) {
    /** ¿Están aplicadas las migraciones 007 y 008? (se consulta una vez por petición) */
    function sgd_mpv_ok($pdo = null)
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            if ($pdo === null) {
                $pdo = (new Conexion())->conectar();
            }
            $st = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
                                WHERE table_schema = DATABASE() AND table_name = 'registro_documentos'
                                  AND column_name IN ('mpv_recepcion', 'mpv_estado')");
            $ok = $st && ((int)$st->fetchColumn()) === 2;
        } catch (Exception $e) {
            $ok = false;
        }
        if (!$ok) {
            error_log('SISGEDO: faltan migraciones/007_mesa_partes_virtual.sql y/o 008_mpv_observar_horario.sql (módulo Mesa de Partes Virtual).');
        }
        return $ok;
    }

    /** Registra un paso en el historial de la Mesa de Partes Virtual. */
    function sgd_mpv_evento($pdo, $idreg, $tipo, $detalle = null, $archivo = null, $plazo = null, $idpersonal = null)
    {
        try {
            $st = $pdo->prepare("INSERT INTO sgd_mpv_evento (idregistro_documentos, tipo, detalle, archivo, plazo, idpersonal)
                                 VALUES (?, ?, ?, ?, ?, ?)");
            $st->execute(array((int)$idreg, $tipo, $detalle, $archivo, $plazo, $idpersonal));
        } catch (Exception $e) {
            error_log('sgd_mpv_evento: ' . $e->getMessage());
        }
    }
}
