<?php

/**
 * SISGEDO — Reporte de estados de atención de documentos.
 */
require_once '../conexion/conexion.php';
class Estados_documento
{
    public function lista_ofi_estado()
    {
        try {
            // Cache en disco (5 min): el resumen es costoso (conteos sobre 79k movimientos),
            // asi que se sirve desde cache salvo que se pida refrescar con ?refrescar=1.
            $cacheFile = __DIR__ . '/../logs/cache_resumen_estado.json';
            $ttl = 300;
            if (!isset($_POST['refrescar']) && is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
                header('Content-Type: application/json; charset=utf-8');
                echo file_get_contents($cacheFile);
                return;
            }
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            // Compatible con MySQL 8 (ONLY_FULL_GROUP_BY): se parte de 'oficinas'
            // y los conteos se resuelven con subconsultas correlacionadas por
            // oficinas.idoficinas (sin usar el alias 'idof' dentro de subconsultas).
            // Se muestran solo las oficinas con algo pendiente (por recepcionar o por atender).
            $sql = "select idof, nombre, docs_por_recepcionar, docs_atender from (
                        select oficinas.idoficinas as idof,
                          oficinas.nombre,
                          ((select count(*) from docs_emitidos where docs_emitidos.estado=0 and docs_emitidos.idoficina=oficinas.idoficinas)
                          +(select count(*) from registro_documentos where estado_recepcion=0 and idoficina=oficinas.idoficinas)
                          +(select count(*) from movimientos where idoficina_derivar=oficinas.idoficinas and estado=0)) as docs_por_recepcionar,
                          (select count(*) from movimientos
                             inner join responsables on movimientos.idresponsable=responsables.idresponsables
                             where movimientos.idestadodocs=1 and responsables.idoficina=oficinas.idoficinas) as docs_atender
                        from oficinas
                      ) as resumen
                      where docs_por_recepcionar > 0 or docs_atender > 0
                      order by docs_atender desc, docs_por_recepcionar desc;";
            $result = $conectado->prepare($sql);
            $result->execute();
            $json = json_encode($result->fetchAll());
            @file_put_contents($cacheFile, $json, LOCK_EX);
            header('Content-Type: application/json; charset=utf-8');
            echo $json;
        } catch (Exception $exc) {
            echo  json_encode($exc->getMessage());
        }
    }

    // Documentos de una oficina: "por atender" + "por recepcionar" (3 fuentes),
    // unidos en una sola lista con una columna de Situacion.
    public function lista_docs_oficina($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            // Columnas (indice): 0 codigo, 1 num_exp, 2 of_origen, 3 tramite,
            // 4 asunto, 5 tipo_doc, 6 num_doc, 7 detalles, 8 situacion, 9 estado, 10 fecha
            $sql = "
                    select codigo, num_exp, of_origen, tramite, asunto, tipo_doc,
                           num_doc, detalles, situacion, estado, fecha
                    from (
                        /* 1) POR ATENDER: movimientos asignados al responsable de la oficina */
                        select
                            m.idMovimientos as codigo,
                            coalesce(rd.nro_exp, m.num_exp, '') as num_exp,
                            coalesce(ofd.nombre, '') as of_origen,
                            coalesce(tt.nombre, '') as tramite,
                            coalesce(rd.asunto, de.asunto, m.detalles, '') as asunto,
                            coalesce(doc.nombre_documento, docd.nombre_documento, '') as tipo_doc,
                            coalesce(rd.numero_docu, de.numero, '') as num_doc,
                            coalesce(m.detalles, '') as detalles,
                            'Por atender' as situacion,
                            coalesce(ed.nombre, '') as estado,
                            m.fecha_hora as orden,
                            date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p') as fecha
                        from movimientos m
                        inner join responsables r on m.idresponsable = r.idresponsables
                        left join estado_documento ed on m.idestadodocs = ed.idestado_documento
                        left join oficinas ofd on m.idoficina_derivar = ofd.idoficinas
                        left join registro_documentos rd on m.idregdocs = rd.idregistro_documentos
                        left join tipo_tram tt on rd.idtipo_tramite = tt.idtipo_tram
                        left join documentos doc on rd.iddocumento = doc.iddocumentos
                        left join docs_emitidos de on m.iddocs_emitidos = de.iddocs_emitidos
                        left join documentos docd on de.iddocumento = docd.iddocumentos
                        where r.idoficina = ? and m.idestadodocs = 1

                        union all

                        /* 2) POR RECEPCIONAR: movimientos derivados a la oficina, aun no recibidos */
                        select
                            m.idMovimientos,
                            coalesce(rd.nro_exp, m.num_exp, ''),
                            coalesce(ofori.nombre, ''),
                            coalesce(tt.nombre, ''),
                            coalesce(rd.asunto, de.asunto, m.detalles, ''),
                            coalesce(doc.nombre_documento, docd.nombre_documento, ''),
                            coalesce(rd.numero_docu, de.numero, ''),
                            coalesce(m.detalles, ''),
                            'Por recepcionar',
                            'Derivado, sin recepcionar',
                            m.fecha_hora,
                            date_format(m.fecha_hora,'%d/%m/%Y %h:%i %p')
                        from movimientos m
                        left join responsables r2 on m.idresponsable = r2.idresponsables
                        left join oficinas ofori on r2.idoficina = ofori.idoficinas
                        left join registro_documentos rd on m.idregdocs = rd.idregistro_documentos
                        left join tipo_tram tt on rd.idtipo_tramite = tt.idtipo_tram
                        left join documentos doc on rd.iddocumento = doc.iddocumentos
                        left join docs_emitidos de on m.iddocs_emitidos = de.iddocs_emitidos
                        left join documentos docd on de.iddocumento = docd.iddocumentos
                        where m.idoficina_derivar = ? and m.estado = 0

                        union all

                        /* 3) POR RECEPCIONAR: registros externos (mesa de partes) sin recepcionar */
                        select
                            rd.idregistro_documentos,
                            coalesce(rd.nro_exp, ''),
                            coalesce(nullif(rd.lugar_procedencia,''), 'Externo'),
                            coalesce(tt.nombre, ''),
                            coalesce(rd.asunto, ''),
                            coalesce(doc.nombre_documento, ''),
                            coalesce(rd.numero_docu, ''),
                            coalesce(rd.observacion, ''),
                            'Por recepcionar',
                            'Registrado, sin recepcionar',
                            rd.fecha_registro,
                            date_format(rd.fecha_registro,'%d/%m/%Y %h:%i %p')
                        from registro_documentos rd
                        left join tipo_tram tt on rd.idtipo_tramite = tt.idtipo_tram
                        left join documentos doc on rd.iddocumento = doc.iddocumentos
                        where rd.estado_recepcion = 0 and rd.idoficina = ?

                        union all

                        /* 4) POR RECEPCIONAR: documentos emitidos (internos) sin recepcionar */
                        select
                            de.iddocs_emitidos,
                            '',
                            coalesce(ofreg.nombre, ''),
                            '',
                            coalesce(de.asunto, ''),
                            coalesce(docd.nombre_documento, ''),
                            coalesce(de.numero, ''),
                            coalesce(de.detalles, ''),
                            'Por recepcionar',
                            'Emitido, sin recepcionar',
                            de.fecha,
                            date_format(de.fecha,'%d/%m/%Y %h:%i %p')
                        from docs_emitidos de
                        left join documentos docd on de.iddocumento = docd.iddocumentos
                        left join oficinas ofreg on de.idoficina_registro = ofreg.idoficinas
                        where de.estado = 0 and de.idoficina = ?
                    ) as t
                    order by orden desc
                    limit 400";
            $result = $conectado->prepare($sql);
            $id = intval($idoficina);
            $result->execute([$id, $id, $id, $id]);
            echo json_encode($result->fetchAll(PDO::FETCH_NUM));
        } catch (Exception $exc) {
            echo json_encode(array());
        }
    }
}
$dmlestados = new Estados_documento();
if (isset($_POST['obtener_resumen'])) {
    $dmlestados->lista_ofi_estado();
}
if (isset($_POST['docs_oficina'])) {
    $dmlestados->lista_docs_oficina($_POST['idof']);
}
