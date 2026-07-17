<?php

/**
 * Consulta de seguimiento de documentos.
 * Unificada: busca primero como documento INTERNO (docs_emitidos) y,
 * si no encuentra, como documento EXTERNO (registro_documentos por nro_exp).
 */
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';

// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}

class Consulta_interna
{

    // ============ SEGUIMIENTO (recorrido del documento) ============
    public function segumiento($cod_doc)
    {
        try {
            $conexion  = new Conexion();
            $conectado = $conexion->conectar();

            // 1) Interno
            $rows = $this->seguimiento_interno($conectado, intval($cod_doc));
            // 2) Si no hay, externo (por número de expediente)
            if (empty($rows)) {
                $rows = $this->seguimiento_externo($conectado, $cod_doc);
            }
            echo json_encode($rows);
        } catch (Exception $exc) {
            error_log('consulta_interna::segumiento -> ' . $exc->getMessage());
            echo json_encode(array());
        }
    }

    private function seguimiento_interno($conectado, $cod_doc)
    {
        $sql = "select lista_movimientos.*,oficinas.nombre
                from
                (select estado_documento.nombre,date_format(date(movimientos.fecha_hora),'%d/%m/%Y') as fecha,
                date_format(date_add(movimientos.fecha_hora,interval 0 hour ),'%h:%i %p') as hora,aciones_documento.nombre_accion,movimientos.idoficina_derivar
                from docs_emitidos
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                inner join oficinas on docs_emitidos.idoficina_registro=oficinas.idoficinas
                inner join movimientos on docs_emitidos.iddocs_emitidos=movimientos.iddocs_emitidos
                inner join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
                inner join aciones_documento on movimientos.idacciondocs=aciones_documento.idaciones_documento
                where docs_emitidos.iddocs_emitidos=?) as lista_movimientos
                inner join oficinas on lista_movimientos.idoficina_derivar=oficinas.idoficinas";
        $result = $conectado->prepare($sql);
        $result->execute([$cod_doc]);
        return $result->fetchAll();
    }

    private function seguimiento_externo($conectado, $nro_exp)
    {
        // Mismas columnas y orden que el interno:
        // [0]estado [1]fecha [2]hora [3]accion [4]idoficina_derivar [5]oficina
        $sql = "select lista_movimientos.*,oficinas.nombre
                from
                (select estado_documento.nombre,date_format(date(movimientos.fecha_hora),'%d/%m/%Y') as fecha,
                date_format(date_add(movimientos.fecha_hora,interval 0 hour ),'%h:%i %p') as hora,aciones_documento.nombre_accion,movimientos.idoficina_derivar
                from registro_documentos
                inner join movimientos on registro_documentos.idregistro_documentos=movimientos.idregdocs
                inner join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
                inner join aciones_documento on movimientos.idacciondocs=aciones_documento.idaciones_documento
                where registro_documentos.nro_exp=?) as lista_movimientos
                inner join oficinas on lista_movimientos.idoficina_derivar=oficinas.idoficinas";
        $result = $conectado->prepare($sql);
        $result->execute([$nro_exp]);
        return $result->fetchAll();
    }

    // ============ DATOS DE REGISTRO (ficha del documento) ============
    public function registro_datos($codigo)
    {
        try {
            $conexion  = new Conexion();
            $conectado = $conexion->conectar();

            $rows = $this->registro_interno($conectado, intval($codigo));
            if (empty($rows)) {
                $rows = $this->registro_externo($conectado, $codigo);
            }
            echo json_encode($rows);
        } catch (Exception $exc) {
            error_log('consulta_interna::registro_datos -> ' . $exc->getMessage());
            echo json_encode(array());
        }
    }

    private function registro_interno($conectado, $codigo)
    {
        $sql = "select lista_docs.*,oficinas.nombre
                    from
                    (select  oficinas.nombre as origen,upper(concat(personal.apellidos,' ',personal.nombres))as ape_nom,'INTERNO',
                    concat(documentos.nombre_documento,' N°:',docs_emitidos.numero) as doc_referencia,docs_emitidos.asunto,
                    date_format(date_add( movimientos.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p') as fecha_hora,estado_documento.nombre,movimientos.idoficina_derivar as ifocina_Actual,
                    docs_emitidos.idoficina as deriv_ini,(select oficinas.nombre from oficinas where oficinas.idoficinas=deriv_ini) as derivini
                    from docs_emitidos
                    inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                    inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                    inner join personal on responsables.idpersonal=personal.idpersonal
                    inner join oficinas on docs_emitidos.idoficina_registro=oficinas.idoficinas
                    inner join movimientos on docs_emitidos.iddocs_emitidos=movimientos.iddocs_emitidos
                    inner join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
                    where docs_emitidos.iddocs_emitidos=?) as lista_docs

                    inner join oficinas on lista_docs.ifocina_Actual=oficinas.idoficinas
                    order by fecha_hora desc
                    limit 1";
        $result = $conectado->prepare($sql);
        $result->execute([$codigo]);
        return $result->fetchAll();
    }

    private function registro_externo($conectado, $nro_exp)
    {
        // 11 columnas en el MISMO orden que la consulta interna, para que
        // funcionen tanto el buscar por Enter (usa [8]) como por botón (usa [9] y [10]):
        // [0]oficina_recepcion [1]usuario [2]tramite [3]doc_referencia [4]asunto
        // [5]fecha_hora [6]situacion [7]id [8]oficina_actual [9]derivacion_inicial [10]oficina_actual
        $sql = "select 'Secretaría General' as oficina_recepcion,
                    concat(remitente.apellidos,' ',remitente.nombres) as usuario,
                    tipo_tram.nombre as tramite,
                    concat(documentos.nombre_documento,' ',registro_documentos.numero_docu) as doc_referencia,
                    registro_documentos.asunto,
                    date_format(date_add(registro_documentos.fecha_registro,interval 0 hour),'%d/%m/%Y %h:%i %p') as fecha_hora,
                    (select ed.nombre
                       from movimientos m
                       inner join estado_documento ed on m.idestadodocs=ed.idestado_documento
                       where m.idregdocs=registro_documentos.idregistro_documentos
                       order by m.idMovimientos desc limit 1) as situacion_actual,
                    registro_documentos.idoficina as ifocina_actual_id,
                    (select o.nombre
                       from movimientos m
                       inner join oficinas o on m.idoficina_derivar=o.idoficinas
                       where m.idregdocs=registro_documentos.idregistro_documentos
                       order by m.idMovimientos desc limit 1) as oficina_actual_a,
                    (select o.nombre from oficinas o where o.idoficinas=registro_documentos.idoficina) as derivacion_inicial,
                    (select o.nombre
                       from movimientos m
                       inner join oficinas o on m.idoficina_derivar=o.idoficinas
                       where m.idregdocs=registro_documentos.idregistro_documentos
                       order by m.idMovimientos desc limit 1) as oficina_actual
                from registro_documentos
                inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                inner join remitente on registro_documentos.idremitente=remitente.idremitente
                inner join tipo_tram on registro_documentos.idtipo_tramite=tipo_tram.idtipo_tram
                where registro_documentos.nro_exp=?
                limit 1";
        $result = $conectado->prepare($sql);
        $result->execute([$nro_exp]);
        return $result->fetchAll();
    }
}

$dml_consultas = new Consulta_interna();

if (isset($_POST['Seguimiento'])) {
    $dml_consultas->segumiento($_POST['codigo']);
}
if (isset($_POST['datos_registro'])) {
    $dml_consultas->registro_datos($_POST['codigo']);
}
