<?php
/* =====================================================================
   SISGEDO — Límite de frecuencia para páginas públicas
   ---------------------------------------------------------------------
   sgd_limite('consulta|IP', 30, 600) => true si aún no pasó de 30 usos
   en 10 minutos (y cuenta este uso); false si ya superó el límite.
   Se guarda en logs/limites.json (la carpeta logs/ no es accesible por
   la web). Si el archivo no se puede usar, se permite: es un freno
   contra abusos, no un control de permisos.
   ===================================================================== */

if (!function_exists('sgd_ip_cliente')) {
    function sgd_ip_cliente()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}

if (!function_exists('sgd_limite')) {
    function sgd_limite($clave, $maximo, $ventanaSeg, $contar = true)
    {
        $archivo = __DIR__ . '/../logs/limites.json';
        $fp = @fopen($archivo, 'c+');
        if (!$fp) {
            return true;
        }
        $ahora = time();
        $permitido = true;
        if (flock($fp, LOCK_EX)) {
            $datos = json_decode(stream_get_contents($fp), true);
            if (!is_array($datos)) {
                $datos = array();
            }
            // Limpiar usos vencidos de todas las claves (el archivo no crece sin fin)
            foreach ($datos as $k => $usos) {
                $datos[$k] = array_values(array_filter((array)$usos, function ($t) use ($ahora) {
                    return $t > $ahora - 86400;
                }));
                if (!$datos[$k]) {
                    unset($datos[$k]);
                }
            }
            $k = sha1($clave);
            $recientes = isset($datos[$k]) ? array_filter($datos[$k], function ($t) use ($ahora, $ventanaSeg) {
                return $t > $ahora - $ventanaSeg;
            }) : array();
            if (count($recientes) >= $maximo) {
                $permitido = false;
            } elseif ($contar) {
                $datos[$k][] = $ahora;
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($datos));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $permitido;
    }
}
