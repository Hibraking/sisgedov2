<?php
/* =====================================================================
   SISGEDO — Calendario de días hábiles y horario de atención
   ---------------------------------------------------------------------
   - Día hábil: figura en SISGEDO_HORARIO (conexion/institucion.php), no es
     feriado nacional (SISGEDO_FERIADOS_FIJOS + Jueves/Viernes Santo) ni día
     no laborable registrado en la tabla sgd_dia_no_laborable.
   - Lo presentado fuera del horario se considera presentado el siguiente
     día hábil a la hora de inicio.
   ===================================================================== */

if (!function_exists('sgd_cal_feriados')) {

    /** Domingo de Pascua (algoritmo gregoriano anónimo; no requiere ext-calendar). */
    function sgd_cal_pascua($anio)
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /** Feriados y días no laborables de un año: array('Y-m-d' => 'motivo'). */
    function sgd_cal_feriados($anio)
    {
        static $cache = array();
        $anio = (int)$anio;
        if (isset($cache[$anio])) {
            return $cache[$anio];
        }
        $out = array();
        foreach ((defined('SISGEDO_FERIADOS_FIJOS') ? SISGEDO_FERIADOS_FIJOS : array()) as $md => $motivo) {
            $out[$anio . '-' . $md] = $motivo;
        }
        $pascua = strtotime(sgd_cal_pascua($anio));
        $out[date('Y-m-d', strtotime('-3 days', $pascua))] = 'Jueves Santo';
        $out[date('Y-m-d', strtotime('-2 days', $pascua))] = 'Viernes Santo';
        // Días no laborables / feriados locales registrados por la institución
        try {
            if (class_exists('Conexion')) {
                $pdo = (new Conexion())->conectar();
                $st = $pdo ? @$pdo->prepare("SELECT fecha, motivo FROM sgd_dia_no_laborable WHERE fecha BETWEEN ? AND ?") : false;
                if ($st && @$st->execute(array($anio . '-01-01', $anio . '-12-31'))) {
                    foreach ($st->fetchAll(PDO::FETCH_NUM) as $f) {
                        $out[$f[0]] = $f[1];
                    }
                }
            }
        } catch (Exception $e) {
            // tabla aún no creada (migración 008): solo feriados nacionales
        }
        return $cache[$anio] = $out;
    }

    /** Horario [inicio, fin] del día (null si no es día hábil). $ymd = 'Y-m-d'. */
    function sgd_cal_horario($ymd)
    {
        $horario = defined('SISGEDO_HORARIO') ? SISGEDO_HORARIO : array();
        $dow = (int)date('N', strtotime($ymd));
        if (!isset($horario[$dow])) {
            return null;
        }
        $fer = sgd_cal_feriados((int)substr($ymd, 0, 4));
        if (isset($fer[$ymd])) {
            return null;
        }
        return $horario[$dow];
    }

    function sgd_cal_es_habil($ymd)
    {
        return sgd_cal_horario($ymd) !== null;
    }

    /** Siguiente día hábil DESPUÉS de $ymd. */
    function sgd_cal_siguiente_habil($ymd)
    {
        $t = strtotime($ymd);
        for ($i = 1; $i <= 60; $i++) {
            $d = date('Y-m-d', strtotime("+$i day", $t));
            if (sgd_cal_es_habil($d)) {
                return $d;
            }
        }
        return date('Y-m-d', strtotime('+1 day', $t));
    }

    /** Fecha (Y-m-d) que resulta de sumar $n días hábiles a $ymd (sin contar $ymd). */
    function sgd_cal_sumar_habiles($ymd, $n)
    {
        $d = $ymd;
        for ($i = 0; $i < (int)$n; $i++) {
            $d = sgd_cal_siguiente_habil($d);
        }
        return $d;
    }

    /**
     * Fecha y hora en que se considera PRESENTADO un documento enviado en
     * $fechaHora ('Y-m-d H:i:s'). Dentro del horario: la misma. Antes de la
     * hora de inicio de un día hábil: ese día a la hora de inicio. Después del
     * cierre, fin de semana o feriado: siguiente día hábil a la hora de inicio.
     */
    function sgd_cal_fecha_presentacion($fechaHora)
    {
        $t = strtotime((string)$fechaHora);
        $ymd = date('Y-m-d', $t);
        $hm = date('H:i', $t);
        $h = sgd_cal_horario($ymd);
        if ($h !== null && $hm >= $h[0] && $hm < $h[1]) {
            return date('Y-m-d H:i:s', $t);
        }
        if ($h !== null && $hm < $h[0]) {
            return $ymd . ' ' . $h[0] . ':00';
        }
        $sig = sgd_cal_siguiente_habil($ymd);
        $hs = sgd_cal_horario($sig);
        return $sig . ' ' . ($hs ? $hs[0] : '08:00') . ':00';
    }

    /** ¿Se envió fuera del horario de atención? */
    function sgd_cal_fuera_de_horario($fechaHora)
    {
        return strtotime(sgd_cal_fecha_presentacion($fechaHora)) !== strtotime((string)$fechaHora);
    }

    /** Hora de cierre (Y-m-d H:i:s) de un día hábil; si no es hábil, 23:59:59. */
    function sgd_cal_cierre($ymd)
    {
        $h = sgd_cal_horario($ymd);
        return $ymd . ' ' . ($h ? $h[1] . ':00' : '23:59:59');
    }

    /** "lunes 16/11/2026 08:00 a. m." */
    function sgd_cal_texto($fechaHora, $conHora = true)
    {
        $dias = array(1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo');
        $t = strtotime((string)$fechaHora);
        $txt = $dias[(int)date('N', $t)] . ' ' . date('d/m/Y', $t);
        if ($conHora) {
            $txt .= ' ' . date('h:i', $t) . (date('H', $t) < 12 ? ' a. m.' : ' p. m.');
        }
        return $txt;
    }
}
