<?php
/* =====================================================================
   SISGEDO — Notificaciones en vivo (para TODAS las oficinas)
   ---------------------------------------------------------------------
   Lo consulta js/notificaciones.js cada 20 s desde cualquier pantalla
   interna (se carga con el menú lateral). Responde en JSON:
     - recepcion: documentos POR RECEPCIONAR de la oficina en sesión
       (derivados/devueltos, registros externos y documentos emitidos a ella).
     - mpv: documentos de la Mesa de Partes Virtual (solo usuarios con acceso).
   Para ser liviano, primero calcula una "firma" (conteos sobre índices);
   el detalle de lo más reciente solo se consulta cuando la firma cambió.
   Solo lectura (GET): no modifica nada.
   ===================================================================== */
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/mpv_util.php';

exigir_sesion_endpoint();
$idoficina = isset($_SESSION['idoficina']) ? (int)$_SESSION['idoficina'] : 0;
$verMpv = usuario_tiene_acceso('vistas/v_mesa_partes_virtual.php');
$verRecepcion = usuario_tiene_acceso('vistas/v_recepcion_proveido.php');
session_write_close();      // no bloquear otras peticiones del mismo usuario

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$firmaCliente = isset($_GET['firma']) ? (string)$_GET['firma'] : '';
$out = array('recepcion' => null, 'mpv' => null);

try {
    $db = (new Conexion())->conectar();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Misma oficina que muestra la pantalla de Recepción: la del CARGO en sesión
    // (sisgedo_contexto_usuario_actual en clases/recepcion_proveido.php).
    $idresp = isset($_SESSION['idresponsable']) ? (int)$_SESSION['idresponsable'] : 0;
    $idper = isset($_SESSION['usuario']) ? (int)$_SESSION['usuario'] : 0;
    if ($idper > 0) {
        $st = $db->prepare("SELECT idoficina FROM responsables WHERE idpersonal = ?
                             ORDER BY (idresponsables = ?) DESC, (estado = 1) DESC, idresponsables ASC LIMIT 1");
        $st->execute(array($idper, $idresp));
        $of = $st->fetchColumn();
        if ($of) $idoficina = (int)$of;
    }

    // ---------------- Recepción de la oficina ----------------
    if ($idoficina > 0 && $verRecepcion) {
        $q = $db->prepare("SELECT
              (SELECT CONCAT(COUNT(*), '.', COALESCE(SUM(idMovimientos),0)) FROM movimientos
                WHERE estado = 0 AND idoficina_derivar = :o1 AND idmovimientoant IS NOT NULL),
              (SELECT CONCAT(COUNT(*), '.', COALESCE(SUM(idregistro_documentos),0)) FROM registro_documentos
                WHERE estado_recepcion = 0 AND idoficina = :o2),
              (SELECT CONCAT(COUNT(*), '.', COALESCE(SUM(iddocs_emitidos),0)) FROM docs_emitidos
                WHERE idoficina = :o3 AND estado = 0)");
        $q->execute(array(':o1' => $idoficina, ':o2' => $idoficina, ':o3' => $idoficina));
        $p = $q->fetch(PDO::FETCH_NUM);
        $total = 0;
        foreach ($p as $parte) $total += (int)$parte;          // "conteo.suma" -> conteo
        $firma = $idoficina . '|' . implode('|', $p);
        $rec = array('oficina' => $idoficina, 'pendientes' => $total, 'firma' => $firma, 'recientes' => null);

        if ($firma !== $firmaCliente) {
            // Los 5 más recientes en llegar (para el aviso "Nuevo documento...")
            $sql = "(SELECT CONCAT('D', m.idMovimientos) AS clave, m.fecha_hora AS llegada,
                            COALESCE(rd.nro_exp, de.iddocs_emitidos) AS exp,
                            TRIM(CONCAT(COALESCE(d1.nombre_documento, d2.nombre_documento, ''), ' ', COALESCE(rd.numero_docu, de.numero, ''))) AS doc,
                            COALESCE(rd.asunto, de.asunto, '') AS asunto,
                            COALESCE(oant.nombre, '') AS origen,
                            COALESCE(ac.nombre_accion, '') AS accion
                       FROM movimientos m
                       LEFT JOIN movimientos mant ON mant.idMovimientos = m.idmovimientoant
                       LEFT JOIN oficinas oant ON oant.idoficinas = mant.idoficina_derivar
                       LEFT JOIN aciones_documento ac ON ac.idaciones_documento = m.idacciondocs
                       LEFT JOIN registro_documentos rd ON rd.idregistro_documentos = m.idregdocs
                       LEFT JOIN documentos d1 ON d1.iddocumentos = rd.iddocumento
                       LEFT JOIN docs_emitidos de ON de.iddocs_emitidos = m.iddocs_emitidos
                       LEFT JOIN documentos d2 ON d2.iddocumentos = de.iddocumento
                      WHERE m.estado = 0 AND m.idoficina_derivar = :o1 AND m.idmovimientoant IS NOT NULL
                      ORDER BY m.fecha_hora DESC LIMIT 5)
                    UNION ALL
                    (SELECT CONCAT('R', x.idregistro_documentos), x.llegada, x.nro_exp, x.doc, x.asunto, x.origen, ''
                       FROM (SELECT rd.idregistro_documentos, rd.nro_exp, COALESCE(rd.asunto, '') AS asunto,
                                    TRIM(CONCAT(COALESCE(d.nombre_documento, ''), ' ', COALESCE(rd.numero_docu, ''))) AS doc,
                                    IF(rd.recepcion_web = 1, 'Mesa de Partes Virtual', 'Mesa de Partes') AS origen,
                                    -- documentos virtuales: llegan a la oficina cuando mesa de partes los deriva
                                    IF(rd.recepcion_web = 1,
                                       COALESCE((SELECT MAX(e.fecha) FROM sgd_mpv_evento e
                                                  WHERE e.idregistro_documentos = rd.idregistro_documentos AND e.tipo = 'DERIVADO'),
                                                rd.mpv_recepcion, rd.fecha_registro),
                                       rd.fecha_registro) AS llegada
                               FROM registro_documentos rd
                               LEFT JOIN documentos d ON d.iddocumentos = rd.iddocumento
                              WHERE rd.estado_recepcion = 0 AND rd.idoficina = :o2) x
                      ORDER BY x.llegada DESC LIMIT 5)
                    UNION ALL
                    (SELECT CONCAT('E', de.iddocs_emitidos), de.fecha, de.iddocs_emitidos,
                            TRIM(CONCAT(COALESCE(d.nombre_documento, ''), ' ', COALESCE(de.numero, ''))),
                            COALESCE(de.asunto, ''), COALESCE(oe.nombre, ''), ''
                       FROM docs_emitidos de
                       LEFT JOIN documentos d ON d.iddocumentos = de.iddocumento
                       LEFT JOIN oficinas oe ON oe.idoficinas = de.idoficina_registro
                      WHERE de.idoficina = :o3 AND de.estado = 0
                      ORDER BY de.fecha DESC LIMIT 5)
                    ORDER BY llegada DESC LIMIT 5";
            if (!sgd_mpv_ok($db)) {
                // Sin la migración de la MPV no existe sgd_mpv_evento: la llegada es la fecha de registro
                $sql = preg_replace('/COALESCE\(\(SELECT MAX\(e\.fecha\).*?rd\.mpv_recepcion, rd\.fecha_registro\)/s', 'rd.fecha_registro', $sql);
            }
            $st = $db->prepare($sql);
            $st->execute(array(':o1' => $idoficina, ':o2' => $idoficina, ':o3' => $idoficina));
            $rec['recientes'] = array();
            foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) {
                $rec['recientes'][] = array('clave' => $r[0], 'llegada' => (string)$r[1], 'exp' => (string)$r[2],
                    'doc' => (string)$r[3], 'asunto' => (string)$r[4], 'origen' => (string)$r[5], 'accion' => (string)$r[6]);
            }
        }
        $out['recepcion'] = $rec;
    }

    // ---------------- Mesa de Partes Virtual ----------------
    if ($verMpv && sgd_mpv_ok($db)) {
        // Mismo número que el menú lateral: por recepcionar + observados vencidos + por derivar
        $n = (int)$db->query("SELECT
              (SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND mpv_recepcion IS NULL
                 AND (mpv_estado IS NULL OR mpv_estado = 'SUBSANADO' OR (mpv_estado = 'OBSERVADO' AND mpv_plazo < CURDATE())))
            + (SELECT COUNT(*) FROM registro_documentos WHERE recepcion_web = 1 AND idoficina IS NULL AND mpv_idpersonal IS NOT NULL
                 AND mpv_recepcion IS NOT NULL AND COALESCE(estado_recepcion,0) = 0)")->fetchColumn();
        $u = $db->query("SELECT rd.idregistro_documentos, rd.nro_exp,
                                TRIM(CONCAT(COALESCE(r.nombres,''),' ',COALESCE(r.apellidos,''))), COALESCE(rd.asunto,'')
                           FROM registro_documentos rd LEFT JOIN remitente r ON r.idremitente = rd.idremitente
                          WHERE rd.recepcion_web = 1 ORDER BY rd.idregistro_documentos DESC LIMIT 1")->fetch(PDO::FETCH_NUM);
        $out['mpv'] = array('total' => $n, 'ultimo' => $u
            ? array('id' => (int)$u[0], 'exp' => (string)$u[1], 'remitente' => (string)$u[2], 'asunto' => (string)$u[3])
            : null);
    }
} catch (Exception $e) {
    error_log('notificaciones: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('error' => 'No se pudo consultar.'));
    exit;
}

echo json_encode($out);
