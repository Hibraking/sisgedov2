<?php
/* =====================================================================
   SISGEDO — Módulo MESA DE PARTES VIRTUAL (ingreso externo por internet)
   ---------------------------------------------------------------------
   Flujo del operador de mesa de partes:
     1) "Por recepcionar": documentos que el ciudadano envió por la MPV
        (y los que ya SUBSANÓ tras una observación).
        - RECEPCIONAR -> correo al ciudadano "documento recepcionado".
        - OBSERVAR    -> correo con el motivo, el plazo (2 días hábiles,
                         Ley 27444 art. 136) y un enlace para subsanar.
     2) "Observados": esperan que el ciudadano subsane. Vencido el plazo sin
        subsanar, el operador puede marcarlo NO PRESENTADO (correo de aviso).
     3) "Por derivar": recepcionados sin oficina -> DERIVAR.
     4) "Historial": derivados y no presentados.
   El horario de atención (conexion/institucion.php) define la fecha en que
   se considera presentado cada documento (clases/calendario.php).
   Requiere migraciones 007 y 008.
   ===================================================================== */
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/mpv_util.php';
require_once __DIR__ . '/calendario.php';

class MesaPartesVirtual
{
    /** Columnas comunes de las bandejas (índices usados por la vista). */
    private function sqlBase()
    {
        return "SELECT rd.idregistro_documentos,                                  -- 0
                       rd.nro_exp,                                                -- 1
                       COALESCE(d.nombre_documento,''),                           -- 2
                       COALESCE(rd.numero_docu,''),                               -- 3
                       TRIM(CONCAT(COALESCE(r.apellidos,''),' ',COALESCE(r.nombres,''))), -- 4
                       COALESCE(r.d_identidad,''),                                -- 5
                       COALESCE(r.email,''),                                      -- 6
                       COALESCE(r.telefono,''),                                   -- 7
                       COALESCE(t.nombre,''),                                     -- 8
                       COALESCE(rd.asunto,''),                                    -- 9
                       COALESCE(rd.folios,0),                                     -- 10
                       rd.fecha_registro,                                         -- 11
                       COALESCE(rd.adjunto,''),                                   -- 12
                       rd.mpv_recepcion,                                          -- 13
                       COALESCE(TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))),''), -- 14 quién recepcionó
                       COALESCE(o.nombre,''),                                     -- 15 oficina destino
                       COALESCE(rd.estado_recepcion,0),                           -- 16
                       COALESCE(r.direccion,''),                                  -- 17
                       COALESCE(rd.mpv_estado,''),                                -- 18
                       rd.mpv_plazo,                                              -- 19
                       COALESCE((SELECT e.detalle FROM sgd_mpv_evento e
                                  WHERE e.idregistro_documentos = rd.idregistro_documentos AND e.tipo = 'OBSERVADO'
                                  ORDER BY e.fecha DESC, e.idevento DESC LIMIT 1),'') -- 20 último motivo de observación
                  FROM registro_documentos rd
                  LEFT JOIN documentos d ON d.iddocumentos = rd.iddocumento
                  LEFT JOIN remitente r  ON r.idremitente = rd.idremitente
                  LEFT JOIN tipo_tram t  ON t.idtipo_tram = rd.idtipo_tramite
                  LEFT JOIN personal p   ON p.idpersonal = rd.mpv_idpersonal
                  LEFT JOIN oficinas o   ON o.idoficinas = rd.idoficina
                 WHERE COALESCE(rd.recepcion_web,0) = 1 ";
    }

    /** Condición SQL de cada bandeja. */
    private static function condicion($bandeja)
    {
        switch ($bandeja) {
            case 'pendientes':
                return " AND rd.mpv_recepcion IS NULL AND COALESCE(rd.mpv_estado,'') IN ('','SUBSANADO')";
            case 'observados':
                return " AND rd.mpv_recepcion IS NULL AND rd.mpv_estado = 'OBSERVADO'";
            case 'por_derivar':
                return " AND rd.mpv_recepcion IS NOT NULL AND rd.mpv_idpersonal IS NOT NULL
                         AND rd.idoficina IS NULL AND COALESCE(rd.estado_recepcion,0) = 0";
            default: // historial
                return " AND (rd.idoficina IS NOT NULL OR rd.mpv_estado = 'NO_PRESENTADO')";
        }
    }

    /**
     * Documentos de una bandeja: 'pendientes' | 'observados' | 'por_derivar' | 'derivados'.
     * $q filtra por expediente, remitente, DNI o asunto. $desde/$hasta solo
     * aplican al historial (por defecto, últimos 30 días).
     */
    public function lista($bandeja, $q = '', $desde = '', $hasta = '')
    {
        if (!sgd_mpv_ok()) {
            return array();
        }
        $sql = $this->sqlBase() . self::condicion($bandeja);
        $p = array();
        if ($bandeja === 'pendientes') {
            $orden = " ORDER BY rd.fecha_registro ASC";          // primero el más antiguo
        } elseif ($bandeja === 'observados') {
            $orden = " ORDER BY rd.mpv_plazo ASC, rd.fecha_registro ASC";
        } elseif ($bandeja === 'por_derivar') {
            $orden = " ORDER BY rd.mpv_recepcion ASC";
        } else {
            $sql .= " AND rd.fecha_registro >= ? AND rd.fecha_registro < DATE_ADD(?, INTERVAL 1 DAY)";
            $p[] = $desde !== '' ? $desde : date('Y-m-d', strtotime('-30 days'));
            $p[] = $hasta !== '' ? $hasta : date('Y-m-d');
            $orden = " ORDER BY rd.fecha_registro DESC";
        }
        $q = trim((string)$q);
        if ($q !== '') {
            $sql .= " AND (rd.nro_exp LIKE ? OR rd.asunto LIKE ? OR r.d_identidad LIKE ?
                           OR CONCAT(COALESCE(r.apellidos,''),' ',COALESCE(r.nombres,'')) LIKE ?)";
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like, $like);
        }
        try {
            $st = (new Conexion())->conectar()->prepare($sql . $orden . " LIMIT 500");
            $st->execute($p);
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $e) {
            error_log('MPV lista: ' . $e->getMessage());
            return array();
        }
    }

    public function contadores()
    {
        $out = array('pendientes' => 0, 'observados' => 0, 'vencidos' => 0, 'por_derivar' => 0, 'hoy' => 0, 'mes' => 0);
        if (!sgd_mpv_ok()) {
            return $out;
        }
        try {
            $c = (new Conexion())->conectar();
            $base = "SELECT COUNT(*) FROM registro_documentos rd WHERE COALESCE(rd.recepcion_web,0)=1 ";
            $out['pendientes'] = (int)$c->query($base . self::condicion('pendientes'))->fetchColumn();
            $out['observados'] = (int)$c->query($base . self::condicion('observados'))->fetchColumn();
            $out['vencidos'] = (int)$c->query($base . self::condicion('observados') . " AND rd.mpv_plazo < CURDATE()")->fetchColumn();
            $out['por_derivar'] = (int)$c->query($base . self::condicion('por_derivar'))->fetchColumn();
            $out['hoy'] = (int)$c->query($base . " AND rd.fecha_registro >= CURDATE() AND rd.fecha_registro < CURDATE() + INTERVAL 1 DAY")->fetchColumn();
            $out['mes'] = (int)$c->query($base . " AND rd.fecha_registro >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
        } catch (Exception $e) {
            error_log('MPV contadores: ' . $e->getMessage());
        }
        return $out;
    }

    /** Historial de un documento: array de array(tipo, detalle, archivo, plazo, fecha, quien). */
    public function historial($id)
    {
        if (!sgd_mpv_ok()) {
            return array();
        }
        try {
            $st = (new Conexion())->conectar()->prepare(
                "SELECT e.tipo, COALESCE(e.detalle,''), COALESCE(e.archivo,''), e.plazo, e.fecha,
                        COALESCE(TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))),'')
                   FROM sgd_mpv_evento e LEFT JOIN personal p ON p.idpersonal = e.idpersonal
                  WHERE e.idregistro_documentos = ? ORDER BY e.fecha ASC, e.idevento ASC");
            $st->execute(array((int)$id));
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $e) {
            error_log('MPV historial: ' . $e->getMessage());
            return array();
        }
    }

    /** Oficinas para el combo de derivar. */
    public function oficinas()
    {
        try {
            $st = (new Conexion())->conectar()->query("SELECT idoficinas, nombre FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-') ORDER BY nombre");
            return $st->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $e) {
            error_log('MPV oficinas: ' . $e->getMessage());
            return array();
        }
    }

    private function falta_migracion()
    {
        return array('ok' => false, 'msg' => 'Faltan las migraciones 007 y 008 (módulo Mesa de Partes Virtual).');
    }

    /**
     * RECEPCIONAR (documento nuevo o ya subsanado).
     * Devuelve array('ok'=>bool, 'msg'=>..., 'correo'=>'OK'|'FALLO'|'SIN_CORREO').
     */
    public function recepcionar($id, $idpersonal)
    {
        if (!sgd_mpv_ok()) {
            return $this->falta_migracion();
        }
        $c = (new Conexion())->conectar();
        $st = $c->prepare("UPDATE registro_documentos
                              SET mpv_recepcion = NOW(), mpv_idpersonal = ?, mpv_estado = NULL, mpv_token = NULL
                            WHERE idregistro_documentos = ? AND COALESCE(recepcion_web,0) = 1 AND mpv_recepcion IS NULL
                              AND COALESCE(mpv_estado,'') IN ('','SUBSANADO')");
        $st->execute(array((int)$idpersonal, (int)$id));
        if ($st->rowCount() === 0) {
            return array('ok' => false, 'msg' => 'El documento ya fue atendido por otra persona (recepcionado u observado). Actualice la página.');
        }
        sgd_mpv_evento($c, $id, 'RECEPCIONADO', null, null, null, (int)$idpersonal);
        return array('ok' => true, 'msg' => 'Recepcionado.', 'correo' => $this->correo_recepcionado((int)$id));
    }

    /** OBSERVAR: motivo + plazo de subsanación + enlace por correo. */
    public function observar($id, $motivo, $idpersonal)
    {
        if (!sgd_mpv_ok()) {
            return $this->falta_migracion();
        }
        $motivo = trim(preg_replace('/\s+/u', ' ', (string)$motivo));
        if (mb_strlen($motivo, 'UTF-8') < 10) {
            return array('ok' => false, 'msg' => 'Escriba el motivo de la observación (al menos 10 caracteres): el ciudadano lo leerá para corregir su documento.');
        }
        $motivo = mb_substr($motivo, 0, 1000, 'UTF-8');
        $plazo = sgd_cal_sumar_habiles(date('Y-m-d'), (int)SISGEDO_PLAZO_SUBSANAR);
        $token = bin2hex(random_bytes(20));
        $c = (new Conexion())->conectar();
        $st = $c->prepare("UPDATE registro_documentos
                              SET mpv_estado = 'OBSERVADO', mpv_plazo = ?, mpv_token = ?
                            WHERE idregistro_documentos = ? AND COALESCE(recepcion_web,0) = 1 AND mpv_recepcion IS NULL
                              AND COALESCE(mpv_estado,'') IN ('','SUBSANADO')");
        $st->execute(array($plazo, $token, (int)$id));
        if ($st->rowCount() === 0) {
            return array('ok' => false, 'msg' => 'El documento ya fue atendido por otra persona (recepcionado u observado). Actualice la página.');
        }
        $adj = $c->prepare("SELECT adjunto FROM registro_documentos WHERE idregistro_documentos = ?");
        $adj->execute(array((int)$id));
        sgd_mpv_evento($c, $id, 'OBSERVADO', $motivo, $adj->fetchColumn() ?: null, $plazo, (int)$idpersonal);
        return array('ok' => true, 'msg' => 'Observado.', 'plazo' => $plazo, 'correo' => $this->correo_observado((int)$id, $motivo, $plazo, $token));
    }

    /** NO PRESENTADO: solo si está observado y el plazo para subsanar ya venció. */
    public function no_presentado($id, $idpersonal)
    {
        if (!sgd_mpv_ok()) {
            return $this->falta_migracion();
        }
        $c = (new Conexion())->conectar();
        $q = $c->prepare("SELECT mpv_estado, mpv_plazo FROM registro_documentos WHERE idregistro_documentos = ? AND COALESCE(recepcion_web,0) = 1");
        $q->execute(array((int)$id));
        $x = $q->fetch(PDO::FETCH_NUM);
        if (!$x || $x[0] !== 'OBSERVADO') {
            return array('ok' => false, 'msg' => 'Solo se puede marcar como no presentado un documento observado. Actualice la página.');
        }
        if (time() <= strtotime(sgd_cal_cierre($x[1]))) {
            return array('ok' => false, 'msg' => 'El plazo para subsanar vence el ' . sgd_cal_texto(sgd_cal_cierre($x[1])) . '. Podrá marcarlo como no presentado después de esa fecha.');
        }
        $st = $c->prepare("UPDATE registro_documentos SET mpv_estado = 'NO_PRESENTADO', mpv_token = NULL
                            WHERE idregistro_documentos = ? AND mpv_estado = 'OBSERVADO'");
        $st->execute(array((int)$id));
        if ($st->rowCount() === 0) {
            return array('ok' => false, 'msg' => 'El documento cambió de estado. Actualice la página.');
        }
        sgd_mpv_evento($c, $id, 'NO_PRESENTADO', 'No se subsanó dentro del plazo (Ley 27444, art. 136).', null, null, (int)$idpersonal);
        return array('ok' => true, 'msg' => 'No presentado.', 'correo' => $this->correo_no_presentado((int)$id));
    }

    /** DERIVAR: asigna la oficina destino de un documento ya recepcionado. */
    public function derivar($id, $idoficina, $idpersonal)
    {
        if (!sgd_mpv_ok()) {
            return $this->falta_migracion();
        }
        $c = (new Conexion())->conectar();
        $o = $c->prepare("SELECT nombre FROM oficinas WHERE idoficinas = ?");
        $o->execute(array((int)$idoficina));
        $nomOfi = $o->fetchColumn();
        if ($nomOfi === false) {
            return array('ok' => false, 'msg' => 'Seleccione una oficina válida.');
        }
        $st = $c->prepare("UPDATE registro_documentos
                              SET idoficina = ?, idpersonal = ?
                            WHERE idregistro_documentos = ? AND COALESCE(recepcion_web,0) = 1
                              AND mpv_recepcion IS NOT NULL AND idoficina IS NULL AND COALESCE(estado_recepcion,0) = 0");
        $st->execute(array((int)$idoficina, (int)$idpersonal, (int)$id));
        if ($st->rowCount() === 0) {
            return array('ok' => false, 'msg' => 'No se pudo derivar: el documento debe estar recepcionado y sin derivar. Actualice la página.');
        }
        sgd_mpv_evento($c, $id, 'DERIVADO', $nomOfi, null, null, (int)$idpersonal);
        return array('ok' => true, 'msg' => 'Derivado.');
    }

    /* ------------------------------------------------------------------
       SUBSANACIÓN (página pública subsanar.php, con el enlace del correo)
       ------------------------------------------------------------------ */

    /** Documento observado que corresponde al enlace (o null). */
    public function buscar_por_token($token)
    {
        if (!sgd_mpv_ok() || !preg_match('/^[a-f0-9]{40}$/', (string)$token)) {
            return null;
        }
        try {
            $st = (new Conexion())->conectar()->prepare($this->sqlBase() . " AND rd.mpv_token = ? AND rd.mpv_estado = 'OBSERVADO' LIMIT 1");
            $st->execute(array($token));
            $r = $st->fetch(PDO::FETCH_NUM);
            return $r ?: null;
        } catch (Exception $e) {
            error_log('MPV buscar_por_token: ' . $e->getMessage());
            return null;
        }
    }

    /** ¿Sigue vigente el plazo para subsanar? */
    public static function plazo_vigente($plazo)
    {
        return $plazo !== null && time() <= strtotime(sgd_cal_cierre($plazo));
    }

    /**
     * El ciudadano sube el documento corregido. $archivo = $_FILES[...] (obligatorio).
     * Vuelve a "Por recepcionar" como SUBSANADO, con el mismo expediente.
     */
    public function subsanar($token, $archivo, $nota)
    {
        $doc = $this->buscar_por_token($token);
        if (!$doc) {
            return array('ok' => false, 'msg' => 'El enlace no es válido o ya fue usado.');
        }
        if (!self::plazo_vigente($doc[19])) {
            return array('ok' => false, 'msg' => 'El plazo para subsanar venció el ' . sgd_cal_texto(sgd_cal_cierre($doc[19])) . '.');
        }
        if (empty($archivo) || !is_uploaded_file($archivo['tmp_name'])) {
            return array('ok' => false, 'msg' => 'Adjunte el documento corregido.');
        }
        require_once __DIR__ . '/util_subida.php';
        if (!sisgedo_validar_archivo($archivo, $errSubida)) {
            return array('ok' => false, 'msg' => $errSubida);
        }
        $nombre = sisgedo_nombre_seguro($archivo);
        $destino = __DIR__ . '/../docs adjs reg_doc/' . $nombre;
        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            return array('ok' => false, 'msg' => 'No se pudo guardar el archivo. Intente nuevamente.');
        }
        require_once __DIR__ . '/gestion_documental.php';
        sgd_registrar_archivo('REGISTRO', $nombre, $destino, $archivo['name'], 'VIRTUAL');

        $nota = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$nota)), 0, 1000, 'UTF-8');
        $c = (new Conexion())->conectar();
        $st = $c->prepare("UPDATE registro_documentos
                              SET adjunto = ?, mpv_estado = 'SUBSANADO', mpv_token = NULL
                            WHERE idregistro_documentos = ? AND mpv_token = ? AND mpv_estado = 'OBSERVADO'");
        $st->execute(array($nombre, (int)$doc[0], $token));
        if ($st->rowCount() === 0) {
            @unlink($destino);
            return array('ok' => false, 'msg' => 'El enlace ya fue usado.');
        }
        sgd_mpv_evento($c, $doc[0], 'SUBSANADO', $nota !== '' ? $nota : null, $nombre, null, null);
        $this->correo_subsanacion_recibida((int)$doc[0]);
        return array('ok' => true, 'msg' => 'Subsanado.', 'exp' => $doc[1], 'id' => (int)$doc[0], 'archivo' => $destino, 'nombre_original' => $archivo['name'], 'nota' => $nota);
    }

    /** Aviso a la bandeja de mesa de partes: llegó una subsanación (con el archivo). */
    public function aviso_subsanacion($id, $rutaArchivo, $nombreOriginal, $nota)
    {
        require_once __DIR__ . '/correo.php';
        $destino = defined('SGD_CORREO_AVISO_MPV') ? trim((string)SGD_CORREO_AVISO_MPV) : '';
        $x = $this->datos_correo($id);
        if ($destino === '' || !$x) {
            return false;
        }
        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $cuerpo = '<p style="font-size:15px;line-height:1.5">El ciudadano <strong>subsanó</strong> el documento observado. Volvió a la bandeja <em>Por recepcionar</em> de la Mesa de Partes Virtual.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0;width:100%">'
            . '<tr><td style="padding:6px 0;color:#52606d;width:38%">Expediente N°</td><td style="padding:6px 0"><strong>' . $e($x['nro_exp']) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 0;color:#52606d">Ciudadano</td><td style="padding:6px 0">' . $e($x['ciudadano']) . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#52606d">Asunto</td><td style="padding:6px 0">' . $e($x['asunto']) . '</td></tr>'
            . ($nota !== '' ? '<tr><td style="padding:6px 0;color:#52606d">Nota del ciudadano</td><td style="padding:6px 0">' . $e($nota) . '</td></tr>' : '')
            . '</table><p style="font-size:13px;color:#52606d">Se adjunta el documento corregido.</p>';
        $responder = filter_var(trim((string)$x['email']), FILTER_VALIDATE_EMAIL) ? trim($x['email']) : '';
        return sgd_correo_enviar($destino, 'MPV - Subsanado - Exp. N° ' . $x['nro_exp'] . ' - ' . $x['ciudadano'],
            sgd_correo_plantilla('Documento subsanado', $cuerpo),
            array('tipo' => 'MPV_AVISO_SUBSANADO', 'referencia' => $x['nro_exp'], 'responder_a' => $responder,
                  'adjuntos' => array(array('ruta' => $rutaArchivo, 'nombre' => $nombreOriginal)), 'copia_enviados' => false));
    }

    /* ------------------------------------------------------------------
       Correos al ciudadano
       ------------------------------------------------------------------ */

    private function datos_correo($id)
    {
        try {
            $st = (new Conexion())->conectar()->prepare(
                "SELECT rd.nro_exp, rd.asunto, rd.fecha_registro, rd.mpv_recepcion, r.email,
                        TRIM(CONCAT(COALESCE(r.nombres,''),' ',COALESCE(r.apellidos,''))) AS ciudadano
                   FROM registro_documentos rd LEFT JOIN remitente r ON r.idremitente = rd.idremitente
                  WHERE rd.idregistro_documentos = ?");
            $st->execute(array((int)$id));
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log('MPV datos_correo: ' . $e->getMessage());
            return null;
        }
    }

    /** Envía un correo al ciudadano del documento. Devuelve 'OK' | 'FALLO' | 'SIN_CORREO'. */
    private function correo_ciudadano($id, $asunto, $titulo, $parrafos, $filas, $boton, $tipo)
    {
        require_once __DIR__ . '/correo.php';
        $x = $this->datos_correo($id);
        if (!$x || !filter_var(trim((string)$x['email']), FILTER_VALIDATE_EMAIL)) {
            return 'SIN_CORREO';
        }
        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $saludo = trim((string)$x['ciudadano']) !== '' ? 'Estimado(a) ' . $e($x['ciudadano']) . ':' : 'Estimado(a) ciudadano(a):';
        $html = '<p style="font-size:15px;line-height:1.5">' . $saludo . '</p>';
        foreach ($parrafos as $p) {
            $html .= '<p style="font-size:15px;line-height:1.5">' . $p . '</p>';
        }
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:16px 0;width:100%">'
            . '<tr><td style="padding:6px 0;color:#52606d;width:45%">Número de expediente</td><td style="padding:6px 0;font-size:18px"><strong>' . $e($x['nro_exp']) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 0;color:#52606d">Asunto</td><td style="padding:6px 0">' . $e($x['asunto']) . '</td></tr>';
        foreach ($filas as $etq => $val) {
            $html .= '<tr><td style="padding:6px 0;color:#52606d">' . $e($etq) . '</td><td style="padding:6px 0">' . $val . '</td></tr>';
        }
        $html .= '</table>';
        if ($boton) {
            $html .= '<p style="text-align:center;margin:24px 0"><a href="' . $e($boton[1]) . '" style="background:' . $boton[2] . ';color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;display:inline-block">' . $e($boton[0]) . '</a></p>'
                . '<p style="font-size:12px;color:#7b8794">Si el botón no funciona, copie este enlace en su navegador:<br>' . $e($boton[1]) . '</p>';
        }
        $ok = sgd_correo_enviar(trim($x['email']), $asunto . ' - Expediente N° ' . $x['nro_exp'],
            sgd_correo_plantilla($titulo, $html), array('tipo' => $tipo, 'referencia' => $x['nro_exp']));
        return $ok ? 'OK' : 'FALLO';
    }

    private function url_seguimiento($id)
    {
        $x = $this->datos_correo($id);
        return sisgedo_base_url('consulta_publico.php?num_exp=' . rawurlencode((string)($x ? $x['nro_exp'] : '')));
    }

    private function correo_recepcionado($id)
    {
        $x = $this->datos_correo($id);
        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        return $this->correo_ciudadano($id, 'Documento recepcionado', 'Su documento fue recepcionado',
            array('La Mesa de Partes de la ' . $e(SISGEDO_ENTIDAD) . ' <strong>recepcionó su documento</strong>. En breve será derivado al área competente para su atención.'),
            array(
                'Presentado el' => $e(sgd_cal_texto(sgd_cal_fecha_presentacion($x['fecha_registro']))),
                'Recepcionado el' => $e(sgd_cal_texto($x['mpv_recepcion'])),
            ),
            array('Ver el estado de mi trámite', $this->url_seguimiento($id), '#1f5bbf'), 'MPV_RECEPCIONADO');
    }

    private function correo_observado($id, $motivo, $plazo, $token)
    {
        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $vence = sgd_cal_texto(sgd_cal_cierre($plazo));
        $n = (int)SISGEDO_PLAZO_SUBSANAR;
        return $this->correo_ciudadano($id, 'Documento OBSERVADO', 'Su documento tiene una observación',
            array(
                'La Mesa de Partes de la ' . $e(SISGEDO_ENTIDAD) . ' revisó su documento y encontró una <strong>observación</strong> que debe corregir:',
                '<span style="display:block;background:#fff7ed;border-left:4px solid #f97316;padding:10px 14px;color:#7c2d12">' . nl2br($e($motivo)) . '</span>',
                'Tiene <strong>' . $n . ' días hábiles</strong> para subsanarla (Ley N° 27444, art. 136): <strong>hasta el ' . $e($vence) . '</strong>. '
                    . 'Pulse el botón, adjunte el documento corregido y envíelo. Mantendrá el mismo número de expediente y la fecha de presentación original. '
                    . 'Si no lo subsana dentro del plazo, el documento se considerará <strong>no presentado</strong>.',
            ),
            array('Plazo para subsanar' => '<strong>' . $e($vence) . '</strong>'),
            array('Subsanar mi documento', sisgedo_base_url('subsanar.php?t=' . $token), '#ea580c'), 'MPV_OBSERVADO');
    }

    private function correo_no_presentado($id)
    {
        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        return $this->correo_ciudadano($id, 'Documento NO PRESENTADO', 'Su documento se considera no presentado',
            array(
                'Su documento fue observado y <strong>no se subsanó dentro del plazo</strong>. Por ello se considera <strong>no presentado</strong> (Ley N° 27444, art. 136).',
                'Si aún desea realizar el trámite, puede presentarlo nuevamente por la Mesa de Partes Virtual de la ' . $e(SISGEDO_ENTIDAD) . '.',
            ),
            array(), array('Ir a la Mesa de Partes Virtual', sisgedo_base_url('mpvirtual.php'), '#1f5bbf'), 'MPV_NO_PRESENTADO');
    }

    private function correo_subsanacion_recibida($id)
    {
        $e = function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        return $this->correo_ciudadano($id, 'Subsanación recibida', 'Recibimos su documento corregido',
            array('Recibimos el documento corregido. La Mesa de Partes de la ' . $e(SISGEDO_ENTIDAD) . ' lo revisará nuevamente y le avisaremos por correo cuando sea recepcionado.'),
            array('Recibido el' => $e(sgd_cal_texto(date('Y-m-d H:i:s')))),
            array('Ver el estado de mi trámite', $this->url_seguimiento($id), '#1f5bbf'), 'MPV_SUBSANADO');
    }
}

// ---------------------------------------------------------------------
// Peticiones del operador (solo cuando se llama a este archivo directamente)
// ---------------------------------------------------------------------
if (basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') === 'mesa_partes_virtual.php'
    && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
    csrf_require();
    exigir_acceso_endpoint();
    $mpv = new MesaPartesVirtual();
    $idpersonal = !empty($_SESSION['usuario']) ? (int)$_SESSION['usuario'] : 0;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (isset($_POST['recepcionar'])) {
        $r = $mpv->recepcionar($id, $idpersonal);
        echo $r['ok'] ? 'OK ' . $r['correo'] : 'ERROR_VALIDACION: ' . $r['msg'];
        exit;
    }
    if (isset($_POST['observar'])) {
        $r = $mpv->observar($id, isset($_POST['motivo']) ? $_POST['motivo'] : '', $idpersonal);
        echo $r['ok'] ? 'OK ' . $r['correo'] : 'ERROR_VALIDACION: ' . $r['msg'];
        exit;
    }
    if (isset($_POST['no_presentado'])) {
        $r = $mpv->no_presentado($id, $idpersonal);
        echo $r['ok'] ? 'OK ' . $r['correo'] : 'ERROR_VALIDACION: ' . $r['msg'];
        exit;
    }
    if (isset($_POST['derivar'])) {
        $r = $mpv->derivar($id, isset($_POST['idoficina']) ? (int)$_POST['idoficina'] : 0, $idpersonal);
        echo $r['ok'] ? 'OK' : 'ERROR_VALIDACION: ' . $r['msg'];
        exit;
    }
    if (isset($_POST['historial'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($mpv->historial($id));
        exit;
    }
    echo 'ERROR_VALIDACION: Acción no reconocida.';
    exit;
}
