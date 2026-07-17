<?php
// DIAGNOSTICO TEMPORAL. Borrar despues.
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(30);
header('Content-Type: text/plain; charset=utf-8');
require_once '../conexion/conexion.php';
$cx = (new Conexion())->conectar();
$cx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$idof = isset($_GET['idof']) ? intval($_GET['idof']) : 2;
echo "=== Detalle (por atender + por recepcionar) para oficina $idof ===\n\n";

$sql = "
    select codigo, num_exp, of_origen, tramite, asunto, tipo_doc,
           num_doc, detalles, situacion, estado, fecha
    from (
        select m.idMovimientos as codigo, coalesce(rd.nro_exp, m.num_exp, '') as num_exp,
            coalesce(ofd.nombre,'') as of_origen, coalesce(tt.nombre,'') as tramite,
            coalesce(rd.asunto, de.asunto, m.detalles,'') as asunto,
            coalesce(doc.nombre_documento, docd.nombre_documento,'') as tipo_doc,
            coalesce(rd.numero_docu, de.numero,'') as num_doc, coalesce(m.detalles,'') as detalles,
            'Por atender' as situacion, coalesce(ed.nombre,'') as estado,
            m.fecha_hora as orden, date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha
        from movimientos m
        inner join responsables r on m.idresponsable=r.idresponsables
        left join estado_documento ed on m.idestadodocs=ed.idestado_documento
        left join oficinas ofd on m.idoficina_derivar=ofd.idoficinas
        left join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
        left join tipo_tram tt on rd.idtipo_tramite=tt.idtipo_tram
        left join documentos doc on rd.iddocumento=doc.iddocumentos
        left join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
        left join documentos docd on de.iddocumento=docd.iddocumentos
        where r.idoficina=? and m.idestadodocs=1
        union all
        select m.idMovimientos, coalesce(rd.nro_exp, m.num_exp,''), coalesce(ofori.nombre,''),
            coalesce(tt.nombre,''), coalesce(rd.asunto, de.asunto, m.detalles,''),
            coalesce(doc.nombre_documento, docd.nombre_documento,''), coalesce(rd.numero_docu, de.numero,''),
            coalesce(m.detalles,''), 'Por recepcionar', 'Derivado, sin recepcionar',
            m.fecha_hora, date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p')
        from movimientos m
        left join responsables r2 on m.idresponsable=r2.idresponsables
        left join oficinas ofori on r2.idoficina=ofori.idoficinas
        left join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
        left join tipo_tram tt on rd.idtipo_tramite=tt.idtipo_tram
        left join documentos doc on rd.iddocumento=doc.iddocumentos
        left join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
        left join documentos docd on de.iddocumento=docd.iddocumentos
        where m.idoficina_derivar=? and m.estado=0
        union all
        select rd.idregistro_documentos, coalesce(rd.nro_exp,''),
            coalesce(nullif(rd.lugar_procedencia,''),'Externo'), coalesce(tt.nombre,''),
            coalesce(rd.asunto,''), coalesce(doc.nombre_documento,''), coalesce(rd.numero_docu,''),
            coalesce(rd.observacion,''), 'Por recepcionar', 'Registrado, sin recepcionar',
            rd.fecha_registro, date_format(rd.fecha_registro,'%d/%m/%Y %h:%i %p')
        from registro_documentos rd
        left join tipo_tram tt on rd.idtipo_tramite=tt.idtipo_tram
        left join documentos doc on rd.iddocumento=doc.iddocumentos
        where rd.estado_recepcion=0 and rd.idoficina=?
        union all
        select de.iddocs_emitidos, '', coalesce(ofreg.nombre,''), '',
            coalesce(de.asunto,''), coalesce(docd.nombre_documento,''), coalesce(de.numero,''),
            coalesce(de.detalles,''), 'Por recepcionar', 'Emitido, sin recepcionar',
            de.fecha, date_format(de.fecha,'%d/%m/%Y %h:%i %p')
        from docs_emitidos de
        left join documentos docd on de.iddocumento=docd.iddocumentos
        left join oficinas ofreg on de.idoficina_registro=ofreg.idoficinas
        where de.estado=0 and de.idoficina=?
    ) as t
    order by orden desc
    limit 400";

$t0 = microtime(true);
try {
    $st = $cx->prepare($sql);
    $st->execute([$idof, $idof, $idof, $idof]);
    $rows = $st->fetchAll(PDO::FETCH_NUM);
    $ms = round((microtime(true) - $t0) * 1000);
    echo "OK en {$ms} ms. Filas: " . count($rows) . "\n";
    $atender = 0;
    $recep = 0;
    foreach ($rows as $r) {
        if ($r[8] === 'Por atender') $atender++;
        else $recep++;
    }
    echo "  Por atender: $atender   |   Por recepcionar: $recep\n\n";
    for ($i = 0; $i < min(8, count($rows)); $i++) {
        echo "  [" . $rows[$i][8] . "] exp:" . $rows[$i][1] . " | " . substr($rows[$i][4], 0, 40) . " | " . $rows[$i][10] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
