<?php

/**
 * SISGEDO — Consulta pública de expedientes (endpoint anónimo).
 */
require_once '../conexion/conexion.php';
class consulta_publico
{
    public function seguimiento($nro_exp)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select estado_documento.nombre,date_format(movimientos.fecha_hora,'%d/%m/%y') as fecha,date_format(ADDDATE(movimientos.fecha_hora, INTERVAL 0 hour),'%h:%i %p') as hora,
                        oficinas.nombre,aciones_documento.nombre_accion
                        from registro_documentos 
                        left join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                        left join tipo_tram on registro_documentos.idtipo_tramite=tipo_tram.idtipo_tram
                        left join remitente on registro_documentos.idremitente=remitente.idremitente
                        left join movimientos on registro_documentos.idregistro_documentos=movimientos.idregdocs
                        left join responsables on movimientos.idresponsable=responsables.idresponsables
                        left join oficinas on responsables.idoficina=oficinas.idoficinas
                        left join aciones_documento on movimientos.idacciondocs=aciones_documento.idaciones_documento
                        left join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
                        where registro_documentos.nro_exp=?
                        order by movimientos.fecha_hora asc";
            $result = $conectado->prepare($sql);
            $result->execute([$nro_exp]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function datos_registro($nro_exp)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select registro_documentos.idregistro_documentos as idreg, 'Secretaría General' as oficina_recepcion,concat(remitente.apellidos,' ',remitente.nombres) as ape_nom,
                        tipo_tram.nombre,registro_documentos.asunto,concat(documentos.nombre_documento,' ',registro_documentos.numero_docu ) as referencia,
                        date_format(adddate(registro_documentos.fecha_registro,interval 0 hour),'%d/%m/%y %h:%i %p') as fecha_hora,oficinas.nombre,
                        (select max(idMovimientos)
                        from movimientos 
                        where movimientos.idregdocs=idreg ) as idmovmax,
                        (select estado_documento.nombre
                        from movimientos 
                        inner join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
                        where movimientos.idMovimientos=idmovmax) as estado_Actual,
                        (select oficinas.nombre
                        from movimientos 
                        inner join responsables on movimientos.idresponsable=responsables.idresponsables
                        left join oficinas on responsables.idoficina= oficinas.idoficinas
                        where movimientos.idMovimientos=idmovmax) as oficina_actual
                        from registro_documentos
                        inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                        inner join remitente on registro_documentos.idremitente=remitente.idremitente
                        inner join tipo_tram on registro_documentos.idtipo_tramite=tipo_tram.idtipo_tram
                        left join oficinas on registro_documentos.idoficina=oficinas.idoficinas
                        where registro_documentos.nro_exp=?
                        group by idreg";
            $result = $conectado->prepare($sql);
            $result->execute([$nro_exp]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
}
$dml_consulta_publico = new consulta_publico();
if (isset($_POST['Seguimiento'])) {
    $dml_consulta_publico->seguimiento($_POST['num_exp']);
}
if (isset($_POST['datos_registro'])) {
    $dml_consulta_publico->datos_registro($_POST['num_exp']);
}
