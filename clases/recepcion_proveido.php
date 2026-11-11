<?php


/**
 * SISGEDO — Recepción y proveído de documentos (bandejas, derivaciones, historial).
 */
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';

if (!function_exists('sisgedo_contexto_usuario_actual')) {
    function sisgedo_contexto_usuario_actual()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $contexto = array(
            'idpersonal'    => isset($_SESSION['usuario']) ? intval($_SESSION['usuario']) : 0,
            'idresponsable' => isset($_SESSION['idresponsable']) ? intval($_SESSION['idresponsable']) : 0,
            'idoficina'     => isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0,
        );

        if ($contexto['idpersonal'] <= 0) {
            return $contexto;
        }

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select responsables.idresponsables, responsables.idoficina
                    from personal
                    inner join responsables on personal.idpersonal = responsables.idpersonal
                    where personal.idpersonal = ?
                    limit 1";
            $stmt = $conectado->prepare($sql);
            $stmt->execute([$contexto['idpersonal']]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                if (!empty($fila['idresponsables'])) {
                    $contexto['idresponsable'] = intval($fila['idresponsables']);
                }
                if (!empty($fila['idoficina'])) {
                    $contexto['idoficina'] = intval($fila['idoficina']);
                }
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }

        return $contexto;
    }
}

// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
}

class Recepcion_proveido
{

    public function lista_recepcionadas($idoficina, $fecha = "")
    {
        $fechafin = date("Y-m-d", strtotime($fecha . "+ 20 days"));
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "(select movimientos.idMovimientos as idmovi,registro_documentos.nro_exp,movimientos.idmovimientoant as anterior,
if(movimientos.idmovimientoant is null,
'Secretaria General',
(select oficinas.nombre
from movimientos 
inner join responsables on movimientos.idresponsable=responsables.idresponsables
inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
where idMovimientos=anterior )) as origen,
'Externo' as Tramite,registro_documentos.asunto,
	documentos.nombre_documento,registro_documentos.numero_docu,
	movimientos.num_exp,movimientos.detalles,aciones_documento.nombre_accion,
	movimientos.idestadodocs,date_format( date_add(movimientos.fecha_hora,interval 0 hour),'%d/%m/%y %h:%i %p') as fecha_hora,if(registro_documentos.adjunto is null,'#',concat('../docs adjs reg_doc/',registro_documentos.adjunto))  as adjunto,
    concat('../consulta_publico.php?num_exp=',registro_documentos.nro_exp) as expediente
	from movimientos
	inner join registro_documentos on movimientos.idregdocs=registro_documentos.idregistro_documentos
    inner join responsables on movimientos.idresponsable=responsables.idresponsables
	inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
    inner join aciones_documento on movimientos.idacciondocs=aciones_documento.idaciones_documento
    inner join tipo_tram on registro_documentos.idtipo_tramite=tipo_tram.idtipo_tram
    left join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento 
    inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
    where oficinas.idoficinas=? and date(movimientos.fecha_hora) between ? and ?
    group by idMovimientos)
    
    union
    
    (select movimientos.idMovimientos as idmovi,docs_emitidos.iddocs_emitidos as nro_exp,movimientos.idmovimientoant as anterior,
    if(movimientos.idmovimientoant is null,
    (select oficinas.nombre
    from movimientos
    inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos
    inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
    inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
    where idMovimientos=idmovi),
    (select oficinas.nombre 
    from movimientos 
    inner join responsables on movimientos.idresponsable=responsables.idresponsables
    inner join oficinas on responsables.idoficina=oficinas.idoficinas
    where movimientos.idMovimientos=anterior)) as origen,'Interno' as Tramite,docs_emitidos.asunto,
		documentos.nombre_documento,docs_emitidos.numero,
		movimientos.num_exp,movimientos.detalles,aciones_documento.nombre_accion,
		movimientos.idestadodocs,date_format( date_add(movimientos.fecha_hora,interval 0 hour),'%d/%m/%y %h:%i %p' ) as fecha_hora,if(docs_emitidos.adjunto is null,'#',concat('../docs adjs docs_emitidos/',docs_emitidos.adjunto)) as adjunto
        ,concat('../reportes/consulta_interna.php?codigo_doc=',docs_emitidos.iddocs_emitidos) as expediente
		from movimientos
		inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos
		inner join responsables on movimientos.idresponsable=responsables.idresponsables
		inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
		inner join aciones_documento on movimientos.idacciondocs=aciones_documento.idaciones_documento
		left join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
		inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
		where oficinas.idoficinas=? and date(movimientos.fecha_hora) between ? and ?
        group by idMovimientos)
        order by idmovi desc;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $fecha, $fechafin, $idoficina, $fecha, $fechafin]);
            $estados = self::lista_estados();
            foreach ($result as $filas) {
                $selected = str_replace('value="' . $filas[11] . '"', 'value="' . $filas[11] . '" selected', $estados);
                echo '<tr>';
                echo '<td class="center">';
                echo '<label class="pos-rel">';
                echo '<input type="checkbox" class="ace" />';
                echo '<span class="lbl"></span>';
                echo '</label>';
                echo '</td>';
                echo '<td class="hidden" >' . h($filas[0]) . '</td>';
                echo '<td>' . h($filas[1]) . '</td>';
                echo '<td >' . h($filas[3]) . '</td>';
                echo '<td >' . h($filas[4]) . '</td>';
                echo '<td >' . h($filas[5]) . '</td>';
                echo '<td >' . h($filas[6]) . '</td>';
                echo '<td>' . h($filas[7]) . '</td>';
                echo '<td >' . h($filas[8]) . '</td>';
                echo '<td >' . h($filas[9]) . '</td>';
                echo '<td >' . h($filas[10]) . '</td>';
                echo '<td width="100">' . '<select class="Estados" name="Documento" id="' . $filas[0] . '">' . $selected . '</select>' . '</td>';
                echo '<td>' . h($filas[12]) . '</td>';
                if (is_null($filas[13])) {
                    $href = '#';
                } else {
                    $href = $filas[13];
                }
                echo '<td width="100">';
                echo '<div class=" action-buttons">';
                if ($href !== '#') {
                    echo '<a class="tooltip-info blue " data-rel="tooltip" title="Ver Adjunto" href="' . h($href) . '" target="_blank">';
                    echo '<i class="ace-icon fa fa-file bigger-130"></i>';
                    echo '</a>';
                } else {
                    echo '<span class="tooltip-info" data-rel="tooltip" title="Sin adjunto" style="opacity:.35;cursor:default;">';
                    echo '<i class="ace-icon fa fa-file bigger-130 grey"></i>';
                    echo '</span>';
                }

                echo '<a class="red tooltip-error eliminar"  data-rel="tooltip" title="Eliminar" href="#">';
                echo '<i class="ace-icon fa fa-trash-o bigger-130"></i>';
                echo '</a>';
                echo '<a href="../tcpdf/examples/cargo_interno.php?exp=' . $filas[0] . '" target="_blank" class="tooltip-error" data-rel="tooltip" title="Cargo">';
                echo '<i class="ace-icon fa fa-print bigger-130"></i>';
                echo '</a>';
                echo '<a class="blue tooltip-info seguimiento" target="_blank"  data-rel="tooltip" title="Seguimiento" href="' . $filas[14] . '">';
                echo '<i class="ace-icon fa fa-eye bigger-130"></i>';
                echo '</a>';
                echo '<a class="blue tooltip-info respuesta_usuario"  data-rel="tooltip" title="Respuesta Externa" href="#">';
                echo '<i class="ace-icon fa fa-envelope-o bigger-130"></i>';
                echo '</a>';

                echo '</div>';

                //dropdown list buttons
                echo '<div class="fixed-top ">';
                echo '<div class="inline pos-rel  ">';
                echo '<button class="btn btn-minier btn-yellow dropdown-toggle " data-toggle="dropdown" data-position="auto">';
                echo '<i class="ace-icon fa fa-chevron-circle-down icon-only bigger-120"></i>';
                echo '</button>';
                echo '<ul class="dropdown-menu dropdown-only-icon dropdown-yellow dropdown-menu-right dropdown-caret dropdown-close">';
                echo '<li>';
                echo '<a href="#modal-form" role="button" data-toggle="modal" class="tooltip-info Derivar" data-rel="tooltip" title="Derivar">';
                echo '<span class="blue">';
                echo '<i class="ace-icon fa fa-paper-plane bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
                echo '<li>';
                echo '<a href="#modal-form" role="button" data-toggle="modal" class="tooltip-info Devolver" data-rel="tooltip" title="Devolver">';
                echo '<span class="blue">';
                echo '<i class="ace-icon fa fa-arrow-left bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';

                echo '<li>';
                echo '<a href="#modal-form" role="button" data-toggle="modal" class="blue tooltip-info Responder" data-rel="tooltip" title="Responder">';
                echo '<span class="blue">';
                echo '<i class="ace-icon fa fa-share bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
                echo '<li>';
                echo '<a href="#modal-form-archivar" role="button" data-toggle="modal" class="blue tooltip-info Archivar" data-rel="tooltip" title="Archivar">';
                echo '<span class="blue">';
                echo '<i class="ace-icon fa fa-archive bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';

                echo '</ul>';
                echo '</div>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            $result->closeCursor();
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_enviadas($idoficina, $fecha = "")
    {
        if ($fecha === "") {
            $fecha = $this->fecha_actual;
        }
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select enviados.idMovimientos,recibidos.num_exp,recibidos.tramite,recibidos.asunto,enviados.num_exp,
enviados.fecha_hora,enviados.nombre,enviados.oficina
from
(select movimientos.num_exp,movimientos.idMovimientos,'Externo' as tramite,registro_documentos.asunto
from movimientos
inner join responsables on movimientos.idresponsable=responsables.idresponsables
inner join registro_documentos on movimientos.idregdocs=registro_documentos.idregistro_documentos
inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
where responsables.idoficina=? and date(movimientos.fecha_hora)>=?) as recibidos
inner join 
(select movimientos.num_exp,movimientos.idMovimientos, movimientos.idmovimientoant,date_format( date_add(movimientos.fecha_hora,interval 0 hour),'%d/%m/%y %h:%i %p') as fecha_hora,
estado_documento.nombre,movimientos.idoficina_derivar as idof,
(select oficinas.nombre from oficinas where idoficinas=idof) as oficina
from movimientos
inner join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
where idmovimientoant is not null ) as enviados on recibidos.idMovimientos=enviados.idmovimientoant

union

select enviados.idMovimientos,recibidos.num_exp,recibidos.tramite,recibidos.asunto,enviados.num_exp,
enviados.fecha_hora,enviados.nombre,enviados.oficina
from
(select movimientos.num_exp,movimientos.idMovimientos,'Interno' as tramite,docs_emitidos.asunto
from movimientos
inner join responsables on movimientos.idresponsable=responsables.idresponsables
inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos
inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
where responsables.idoficina=? and date(movimientos.fecha_hora)>=?) as recibidos
inner join 
(select movimientos.num_exp,movimientos.idMovimientos, movimientos.idmovimientoant,date_format( date_add(movimientos.fecha_hora,interval 0 hour),'%d/%m/%y %h:%i %p') as fecha_hora,
estado_documento.nombre,movimientos.idoficina_derivar as idof,
(select oficinas.nombre from oficinas where idoficinas=idof) as oficina
from movimientos
inner join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
where idmovimientoant is not null ) as enviados on recibidos.idMovimientos=enviados.idmovimientoant
order by idMovimientos desc;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $fecha, $idoficina, $fecha]);
            foreach ($result as $filas) {

                echo '<tr>';
                echo '<td class="center">';
                echo '<label class="pos-rel">';
                echo '<input type="checkbox" class="ace" />';
                echo '<span class="lbl"></span>';
                echo '</label>';
                echo '</td>';
                echo '<td class="hidden" >' . h($filas[0]) . '</td>';
                echo '<td>' . h($filas[1]) . '</td>';
                echo '<td>' . h($filas[2]) . '</td>';
                echo '<td>' . h($filas[3]) . '</td>';
                echo '<td>' . h($filas[4]) . '</td>';
                echo '<td>' . h($filas[7]) . '</td>';
                echo '<td>' . h($filas[6]) . '</td>';
                echo '<td>' . h($filas[5]) . '</td>';

                echo '<td>';
                if (is_null($filas[4])) {
                    echo '<div class="action-buttons center">';
                    echo '<a class="red tooltip-error eliminar"  data-rel="tooltip" title="Eliminar" href="#">';
                    echo '<i class="ace-icon fa fa-trash-o bigger-130"></i>';
                    echo '</a>';
                    echo '</div>';
                }
                echo '</td>';
                echo '</tr>';
            }
            $result->closeCursor();
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_docs_emitidos($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select docs_emitidos.iddocs_emitidos,concat(documentos.nombre_documento,' N:',docs_emitidos.numero,' - ',docs_emitidos.asunto,' - ',date(docs_emitidos.fecha),'/ ',oficinas.nombre) as detalles
                        from docs_emitidos
                        left join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                        left join oficinas on responsables.idoficina=oficinas.idoficinas
                        left join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                        where docs_emitidos.idoficina=? and docs_emitidos.estado=0 and date(docs_emitidos.fecha)>=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $this->fecha_actual]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_por_recepcionar($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "(select movimientos.idMovimientos as codigo,
                (select oficinas.nombre 
                from movimientos 
                inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
                where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo)) as oficina,
                'INTERNO' as tramite,documentos.nombre_documento,docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                movimientos.detalle_derivado as detalles,
                (select concat(documentos.nombre_documento,' N:',docs_emitidos.numero)
                from movimientos
                inner join docs_emitidos on movimientos.iddocrespuesta=docs_emitidos.iddocs_emitidos
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos 
                where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo) ) as Respuesta
                ,date_format( date_add( movimientos.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'3' as tipo,
                movimientos.fecha_hora as fecha_horaa
                from movimientos
                inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                
                union
                
                (select movimientos.idMovimientos as codigo,
                (select oficinas.nombre 
                from movimientos 
                inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
                where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo)) as oficina,
                tipo_tram.nombre as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,
                registro_documentos.asunto,movimientos.detalle_derivado as detalles,
                (select concat(documentos.nombre_documento,' N:',docs_emitidos.numero)
                from movimientos
                inner join docs_emitidos on movimientos.iddocrespuesta=docs_emitidos.iddocs_emitidos
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos 
                where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo) ) as Respuesta,
                date_format( date_add( movimientos.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs reg_doc/',adjunto)) as adjunto,'3' as tipo,
                movimientos.fecha_hora as fecha_horaa
                from movimientos
                inner join registro_documentos on movimientos.idregdocs= registro_documentos.idregistro_documentos
                inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                
                union
                
                (select registro_documentos.idregistro_documentos as codigo,'MESA DE PARTES' AS oficina,tipo_tram.nombre as tramite,documentos.nombre_documento,
                registro_documentos.nro_exp ,registro_documentos.numero_docu as numero ,registro_documentos.asunto,registro_documentos.observacion as detalles,'--' as Respuesta,
                date_format( date_add( registro_documentos.fecha_registro,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs reg_doc/',adjunto)) as adjunto,'1' as tipo,
                registro_documentos.fecha_registro as fecha_horaa
                from registro_documentos 
                inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                where registro_documentos.estado_recepcion=0 and idoficina=?)
                
                union
                
                (select docs_emitidos.iddocs_emitidos as codigo,oficinas.nombre as oficina,'INTERNO' AS tramite,documentos.nombre_documento,
                docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,docs_emitidos.detalles,'--' as Respuesta,
                date_format( date_add( docs_emitidos.fecha,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'2' as tipo,
                docs_emitidos.fecha as fecha_horaa
                from docs_emitidos
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                inner join oficinas on docs_emitidos.idoficina_registro=oficinas.idoficinas
                where docs_emitidos.estado=0 and docs_emitidos.idoficina=?)
                order by fecha_horaa desc;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $idoficina, $idoficina, $idoficina]);

            foreach ($result as $filas) {
                echo '<tr>';
                echo '<td class="center">';
                echo '<label class="pos-rel">';
                echo '<input type="checkbox" class="ace" />';
                echo '<span class="lbl"></span>';
                echo '</label>';
                echo '</td>';
                echo '<td class="hidden" >' . h($filas[0]) . '</td>';
                echo '<td class="hidden" >' . h($filas[11]) . '</td>';
                echo '<td>' . h($filas[4]) . '</td>';
                echo '<td>' . h($filas[1]) . '</td>';
                echo '<td>' . h($filas[2]) . '</td>';
                echo '<td>' . h($filas[3]) . '</td>';
                echo '<td>' . h($filas[5]) . '</td>';
                echo '<td>' . h($filas[6]) . '</td>';
                echo '<td>' . h($filas[7]) . '</td>';
                echo '<td>' . h($filas[8]) . '</td>';
                echo '<td>' . h($filas[9]) . '</td>';
                echo '<td>';
                echo '<div class=" action-buttons">';
                echo '<a href="#modal-form1" role="button" data-toggle="modal" class="tooltip-success recepcionar" data-rel="tooltip" title="Recepcionar">';
                echo '<span class="green">';
                echo '<i class="ace-icon fa fa-download"></i>';
                echo '</span>';
                echo '</a>';
                if ($filas[10] !== '#' && $filas[10] !== null && $filas[10] !== '') {
                    echo '<a href="' . h($filas[10]) . '" role="button" target="_blank" class="tooltip-info" data-rel="tooltip" title="Ver Adjunto">';
                    echo '<span class="blue">';
                    echo '<i class="ace-icon fa fa-file"></i>';
                    echo '</span>';
                    echo '</a>';
                } else {
                    echo '<span class="tooltip-info" data-rel="tooltip" title="Sin adjunto" style="opacity:.35;cursor:default;">';
                    echo '<i class="ace-icon fa fa-file grey"></i>';
                    echo '</span>';
                }

                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    // === NUEVO (solo lectura): datos de la bandeja "por recepcionar" como ARRAY,
    //     para renderizar el nuevo diseno. Misma consulta que lista_por_recepcionar. ===
    public function datos_por_recepcionar($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "(select movimientos.idMovimientos as codigo,
                (select oficinas.nombre from movimientos inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo)) as oficina,
                'INTERNO' as tramite,documentos.nombre_documento,docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                movimientos.detalle_derivado as detalles,'--' as Respuesta,
                date_format( date_add( movimientos.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'3' as tipo,
                movimientos.fecha_hora as fecha_horaa
                from movimientos inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                union
                (select movimientos.idMovimientos as codigo,
                (select oficinas.nombre from movimientos inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo)) as oficina,
                tipo_tram.nombre as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,
                registro_documentos.asunto,movimientos.detalle_derivado as detalles,'--' as Respuesta,
                date_format( date_add( movimientos.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs reg_doc/',adjunto)) as adjunto,'3' as tipo,
                movimientos.fecha_hora as fecha_horaa
                from movimientos inner join registro_documentos on movimientos.idregdocs= registro_documentos.idregistro_documentos inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                union
                (select registro_documentos.idregistro_documentos as codigo,
                if(coalesce(registro_documentos.recepcion_web,0)=1,'MESA DE PARTES VIRTUAL',
                   coalesce((select o9.nombre from responsables rr9 inner join oficinas o9 on rr9.idoficina=o9.idoficinas where rr9.idpersonal=registro_documentos.idpersonal limit 1),'MESA DE PARTES')) AS oficina,
                tipo_tram.nombre as tramite,documentos.nombre_documento,
                registro_documentos.nro_exp ,registro_documentos.numero_docu as numero ,registro_documentos.asunto,registro_documentos.observacion as detalles,'--' as Respuesta,
                date_format( date_add( registro_documentos.fecha_registro,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs reg_doc/',adjunto)) as adjunto,'1' as tipo,
                registro_documentos.fecha_registro as fecha_horaa
                from registro_documentos inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                where registro_documentos.estado_recepcion=0 and idoficina=?)
                union
                (select docs_emitidos.iddocs_emitidos as codigo,oficinas.nombre as oficina,'INTERNO' AS tramite,documentos.nombre_documento,
                docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,docs_emitidos.detalles,'--' as Respuesta,
                date_format( date_add( docs_emitidos.fecha,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'2' as tipo,
                docs_emitidos.fecha as fecha_horaa
                from docs_emitidos inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables inner join oficinas on docs_emitidos.idoficina_registro=oficinas.idoficinas
                where docs_emitidos.estado=0 and docs_emitidos.idoficina=?)
                order by fecha_horaa desc;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $idoficina, $idoficina, $idoficina]);
            return $result->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return array();
        }
    }

    // === NUEVO (solo lectura): documentos RECEPCIONADOS por la oficina en un rango de fechas,
    //     como ARRAY con la misma estructura de columnas que datos_por_recepcionar. ===
    public function datos_recepcionados($idoficina, $desde, $hasta)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select m.idMovimientos as codigo,
                if(m.idmovimientoant is null,'MESA DE PARTES',
                  (select o2.nombre from movimientos m2 inner join responsables r2 on m2.idresponsable=r2.idresponsables inner join oficinas o2 on r2.idoficina=o2.idoficinas where m2.idMovimientos=m.idmovimientoant)) as origen,
                'EXTERNO' as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,registro_documentos.asunto,
                m.detalles,ifnull(aciones_documento.nombre_accion,'--') as Respuesta,
                date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p') as fecha,
                if(registro_documentos.adjunto is null,'#',concat('../docs adjs reg_doc/',registro_documentos.adjunto)) as adjunto,
                'R' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join registro_documentos on m.idregdocs=registro_documentos.idregistro_documentos
                inner join oficinas on m.idoficina_derivar=oficinas.idoficinas
                inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                left join aciones_documento on m.idacciondocs=aciones_documento.idaciones_documento
                where oficinas.idoficinas=? and date(m.fecha_hora) between ? and ?";
            // NOTA: las dos partes se ejecutan POR SEPARADO y se unen en PHP.
            // Un UNION en SQL falla con "Illegal mix of collations" porque
            // registro_documentos y docs_emitidos quedaron con collations
            // distintas tras la migración a utf8mb4.
            $sql2 = "select m.idMovimientos as codigo,
                if(m.idmovimientoant is null,
                  (select o3.nombre from responsables r3 inner join oficinas o3 on r3.idoficina=o3.idoficinas where r3.idresponsables=docs_emitidos.idresponsable),
                  (select o2.nombre from movimientos m2 inner join responsables r2 on m2.idresponsable=r2.idresponsables inner join oficinas o2 on r2.idoficina=o2.idoficinas where m2.idMovimientos=m.idmovimientoant)) as origen,
                'INTERNO' as tramite,documentos.nombre_documento,cast(docs_emitidos.iddocs_emitidos as char) as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                m.detalles,ifnull(aciones_documento.nombre_accion,'--') as Respuesta,
                date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p') as fecha,
                if(docs_emitidos.adjunto is null,'#',concat('../docs adjs docs_emitidos/',docs_emitidos.adjunto)) as adjunto,
                'R' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join docs_emitidos on m.iddocs_emitidos=docs_emitidos.iddocs_emitidos
                inner join oficinas on m.idoficina_derivar=oficinas.idoficinas
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                left join aciones_documento on m.idacciondocs=aciones_documento.idaciones_documento
                where oficinas.idoficinas=? and date(m.fecha_hora) between ? and ?";
            $st1 = $conectado->prepare($sql);
            $st1->execute([$idoficina, $desde, $hasta]);
            $filas = $st1->fetchAll(PDO::FETCH_NUM);
            $st2 = $conectado->prepare($sql2);
            $st2->execute([$idoficina, $desde, $hasta]);
            $filas = array_merge($filas, $st2->fetchAll(PDO::FETCH_NUM));
            // Orden: más recientes primero (fecha_horaa está en la columna 12, formato YYYY-MM-DD HH:MM:SS)
            usort($filas, function ($a, $b) {
                return strcmp((string)$b[12], (string)$a[12]);
            });
            return $filas;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return array();
        }
    }

    // === NUEVO (solo lectura): documentos ENVIADOS/DERIVADOS por la oficina en un rango,
    //     como ARRAY. La columna [1] es la oficina DESTINO. ===
    public function datos_enviados($idoficina, $desde, $hasta)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            // NOTA: dos consultas separadas unidas en PHP (un UNION en SQL falla
            // con "Illegal mix of collations" por la migración parcial a utf8mb4).
            $sql = "select m.idMovimientos as codigo,
                (select o2.nombre from oficinas o2 where o2.idoficinas=m.idoficina_derivar) as destino,
                'EXTERNO' as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,registro_documentos.asunto,
                m.detalle_derivado as detalles,ifnull(estado_documento.nombre,'--') as Respuesta,
                date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p') as fecha,
                if(registro_documentos.adjunto is null,'#',concat('../docs adjs reg_doc/',registro_documentos.adjunto)) as adjunto,
                'E' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join movimientos ma on m.idmovimientoant=ma.idMovimientos
                inner join responsables r on ma.idresponsable=r.idresponsables
                inner join registro_documentos on m.idregdocs=registro_documentos.idregistro_documentos
                inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                left join estado_documento on m.idestadodocs=estado_documento.idestado_documento
                where r.idoficina=? and date(m.fecha_hora) between ? and ?";
            $sql2 = "select m.idMovimientos as codigo,
                (select o2.nombre from oficinas o2 where o2.idoficinas=m.idoficina_derivar) as destino,
                'INTERNO' as tramite,documentos.nombre_documento,cast(docs_emitidos.iddocs_emitidos as char) as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                m.detalle_derivado as detalles,ifnull(estado_documento.nombre,'--') as Respuesta,
                date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p') as fecha,
                if(docs_emitidos.adjunto is null,'#',concat('../docs adjs docs_emitidos/',docs_emitidos.adjunto)) as adjunto,
                'E' as tipo,m.fecha_hora as fecha_horaa
                from movimientos m
                inner join movimientos ma on m.idmovimientoant=ma.idMovimientos
                inner join responsables r on ma.idresponsable=r.idresponsables
                inner join docs_emitidos on m.iddocs_emitidos=docs_emitidos.iddocs_emitidos
                inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                left join estado_documento on m.idestadodocs=estado_documento.idestado_documento
                where r.idoficina=? and date(m.fecha_hora) between ? and ?";
            $st1 = $conectado->prepare($sql);
            $st1->execute([$idoficina, $desde, $hasta]);
            $filas = $st1->fetchAll(PDO::FETCH_NUM);
            $st2 = $conectado->prepare($sql2);
            $st2->execute([$idoficina, $desde, $hasta]);
            $filas = array_merge($filas, $st2->fetchAll(PDO::FETCH_NUM));
            // Orden: más recientes primero (fecha_horaa en columna 12, formato YYYY-MM-DD HH:MM:SS)
            usort($filas, function ($a, $b) {
                return strcmp((string)$b[12], (string)$a[12]);
            });
            return $filas;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return array();
        }
    }

    // === NUEVO (solo lectura): total de documentos por recepcionar (para el KPI
    //     cuando la tabla muestra recepcionados o enviados) ===
    public function contador_pendientes($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $n = 0;
            $q = $conectado->prepare("select count(*) from movimientos where idoficina_derivar=? and idmovimientoant is not null and estado=0");
            $q->execute([$idoficina]);
            $n += intval($q->fetchColumn());
            $q = $conectado->prepare("select count(*) from registro_documentos where estado_recepcion=0 and idoficina=?");
            $q->execute([$idoficina]);
            $n += intval($q->fetchColumn());
            $q = $conectado->prepare("select count(*) from docs_emitidos where estado=0 and idoficina=?");
            $q->execute([$idoficina]);
            $n += intval($q->fetchColumn());
            return $n;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return 0;
        }
    }

    // === NUEVO (solo lectura): contadores reales para las tarjetas KPI ===
    public function contadores($idoficina)
    {
        $out = array('recepcionados' => 0, 'enviados' => 0);
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            // Recepcionados: movimientos ya recibidos por esta oficina
            $q1 = $conectado->prepare("select count(*) from movimientos where idoficina_derivar=? and estado=1");
            $q1->execute([$idoficina]);
            $out['recepcionados'] = intval($q1->fetchColumn());
            // Enviados: movimientos generados por los responsables de esta oficina (derivaciones/envios)
            $q2 = $conectado->prepare("select count(*) from movimientos m inner join responsables r on m.idresponsable=r.idresponsables where r.idoficina=?");
            $q2->execute([$idoficina]);
            $out['enviados'] = intval($q2->fetchColumn());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
        return $out;
    }
    public function cant_lista($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select count(*) from(
(select movimientos.idMovimientos as codigo,
                        (select oficinas.nombre 
                        from movimientos 
                        inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
                        where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo)) as oficina,
                        'DERIVADO' as tramite,documentos.nombre_documento,docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,
                        movimientos.detalle_derivado as detalles,
                        (select concat(documentos.nombre_documento,' N:',docs_emitidos.numero)
                        from movimientos
                        inner join docs_emitidos on movimientos.iddocrespuesta=docs_emitidos.iddocs_emitidos
                        inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos 
                        where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo) ) as Respuesta
                        ,date_format( date_add( movimientos.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'3' as tipo,
                        movimientos.fecha_hora as fecha_horaa
                        from movimientos
                        inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos
                        inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                        where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                        
                        union
                        
                        (select movimientos.idMovimientos as codigo,
                        (select oficinas.nombre 
                        from movimientos 
                        inner join oficinas on movimientos.idoficina_derivar=oficinas.idoficinas
                        where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo)) as oficina,
                        tipo_tram.nombre as tramite,documentos.nombre_documento,registro_documentos.nro_exp,registro_documentos.numero_docu as numero,
                        registro_documentos.asunto,movimientos.detalle_derivado as detalles,
                        (select concat(documentos.nombre_documento,' N:',docs_emitidos.numero)
                        from movimientos
                        inner join docs_emitidos on movimientos.iddocrespuesta=docs_emitidos.iddocs_emitidos
                        inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos 
                        where movimientos.idMovimientos=(select idmovimientoant from movimientos where idMovimientos=codigo) ) as Respuesta,
                        date_format( date_add( movimientos.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'3' as tipo,
                        movimientos.fecha_hora as fecha_horaa
                        from movimientos
                        inner join registro_documentos on movimientos.idregdocs= registro_documentos.idregistro_documentos
                        inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                        inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                        where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0)
                        
                        union
                        
                        (select registro_documentos.idregistro_documentos as codigo,'MESA DE PARTES' AS oficina,tipo_tram.nombre as tramite,documentos.nombre_documento,
                        registro_documentos.nro_exp ,registro_documentos.numero_docu as numero ,registro_documentos.asunto,registro_documentos.observacion as detalles,'--' as Respuesta,
                        date_format( date_add( registro_documentos.fecha_registro,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'1' as tipo,
                        registro_documentos.fecha_registro as fecha_horaa
                        from registro_documentos 
                        inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                        inner join tipo_tram on registro_documentos.idtipo_tramite= tipo_tram.idtipo_tram
                        where registro_documentos.estado_recepcion=0 and idoficina=?)
                        
                        union
                        
                        (select docs_emitidos.iddocs_emitidos as codigo,oficinas.nombre as oficina,'INTERNO' AS tramite,documentos.nombre_documento,
                        docs_emitidos.iddocs_emitidos as nro_exp,docs_emitidos.numero,docs_emitidos.asunto,docs_emitidos.detalles,'--' as Respuesta,
                        date_format( date_add( docs_emitidos.fecha,interval 0 hour),'%d/%m/%Y %h:%i %p')as fecha,if(adjunto is null,'#', concat('../docs adjs docs_emitidos/',adjunto)) as adjunto,'2' as tipo,
                        docs_emitidos.fecha as fecha_horaa
                        from docs_emitidos
                        inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                        inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                        inner join oficinas on docs_emitidos.idoficina_registro=oficinas.idoficinas
                        where docs_emitidos.estado=0 and docs_emitidos.idoficina=?)) as todoo;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $idoficina, $idoficina, $idoficina]);
            foreach ($result as $filas) {
                $valor = $filas[0];
            }
            echo json_encode($valor);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_docs_externos($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT  idregistro_documentos,concat(documentos.nombre_documento,'/ ',nro_exp,'/ ',asunto) as detalles
                    FROM registro_documentos
                    inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                    where estado_recepcion=0 and idoficina=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_acciones()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT idaciones_documento,nombre_accion FROM aciones_documento;";
            $result = $conectado->prepare($sql);
            $result->execute();
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function lista_estados()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT idestado_documento,nombre FROM estado_documento;";
            $result = $conectado->prepare($sql);
            $result->execute();
            $resultado = '';
            foreach ($result as $filas) {
                $resultado .= '<option value="' . $filas[0] . '">' . $filas[1] . '</option>';
            }
            return $resultado;
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return '';
        }
    }
    // Estados en JSON (para los combos de la vista nueva de recepción)
    public function lista_estados_json()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $result = $conectado->prepare("SELECT idestado_documento,nombre FROM estado_documento;");
            $result->execute();
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo json_encode(array());
        }
    }
    public function historial_documento($codigo, $tipo)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $codigo = intval($codigo);
            $tipo = trim((string)$tipo);
            $historial = array();

            if ($codigo <= 0) {
                echo json_encode(array());
                return;
            }

            if ($tipo === '1') {
                $sql = "select rd.idregistro_documentos as codigo,
                                   date_format(date_add(rd.fecha_registro,interval 0 hour),'%d/%m/%Y') as fecha,
                                   date_format(date_add(rd.fecha_registro,interval 0 hour),'%h:%i %p') as hora,
                                   'Secretaría General' as oficina,
                                   'Registro' as accion,
                                   'Registrado, sin recepcionar' as estado,
                                   coalesce(rd.observacion,'') as detalles,
                                   'Registro' as evento
                            from registro_documentos rd
                            where rd.idregistro_documentos=?";
                $result = $conectado->prepare($sql);
                $result->execute([$codigo]);
                echo json_encode($result->fetchAll(PDO::FETCH_NUM));
                return;
            }

            if ($tipo === '2') {
                $sql = "select de.iddocs_emitidos as codigo,
                                   date_format(date_add(de.fecha,interval 0 hour),'%d/%m/%Y') as fecha,
                                   date_format(date_add(de.fecha,interval 0 hour),'%h:%i %p') as hora,
                                   coalesce(ofi.nombre,'') as oficina,
                                   'Registro' as accion,
                                   'Emitido, sin recepcionar' as estado,
                                   coalesce(de.detalles,'') as detalles,
                                   'Registro' as evento
                            from docs_emitidos de
                            left join oficinas ofi on de.idoficina_registro=ofi.idoficinas
                            where de.iddocs_emitidos=?";
                $result = $conectado->prepare($sql);
                $result->execute([$codigo]);
                echo json_encode($result->fetchAll(PDO::FETCH_NUM));
                return;
            }

            $q = $conectado->prepare("select idregdocs,iddocs_emitidos from movimientos where idMovimientos=? limit 1");
            $q->execute([$codigo]);
            $mov = $q->fetch(PDO::FETCH_ASSOC);
            if (!$mov) {
                echo json_encode(array());
                return;
            }

            $idregdocs = !empty($mov['idregdocs']) ? intval($mov['idregdocs']) : 0;
            $iddocs_emitidos = !empty($mov['iddocs_emitidos']) ? intval($mov['iddocs_emitidos']) : 0;

            if ($idregdocs > 0) {
                // Dos consultas separadas unidas en PHP (evita "Illegal mix of
                // collations" entre tablas con collation distinta tras utf8mb4).
                $sqlA = "select rd.idregistro_documentos as codigo,
                                       date_format(date_add(rd.fecha_registro,interval 0 hour),'%d/%m/%Y') as fecha,
                                       date_format(date_add(rd.fecha_registro,interval 0 hour),'%h:%i %p') as hora,
                                       'Secretaría General' as oficina,
                                       'Registro' as accion,
                                       'Registrado, sin recepcionar' as estado,
                                       coalesce(rd.observacion,'') as detalles,
                                       'Registro' as evento,
                                       rd.fecha_registro as orden
                                from registro_documentos rd
                                where rd.idregistro_documentos=?";
                $sqlB = "select m.idMovimientos as codigo,
                                       date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y') as fecha,
                                       date_format(date_add(m.fecha_hora,interval 0 hour),'%h:%i %p') as hora,
                                       coalesce(ofi.nombre,ofid.nombre,'') as oficina,
                                       coalesce(acc.nombre_accion,'--') as accion,
                                       coalesce(est.nombre,'--') as estado,
                                       coalesce(m.detalles,m.detalle_derivado,'') as detalles,
                                       case when m.idmovimientoant is null then 'Origen'
                                            when m.estado=0 then 'Ubicación actual'
                                            else 'Movimiento' end as evento,
                                       m.fecha_hora as orden
                                from movimientos m
                                left join responsables r on m.idresponsable=r.idresponsables
                                left join oficinas ofi on r.idoficina=ofi.idoficinas
                                left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                                left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                                left join estado_documento est on m.idestadodocs=est.idestado_documento
                                where m.idregdocs=?";
                $stA = $conectado->prepare($sqlA);
                $stA->execute([$idregdocs]);
                $historial = $stA->fetchAll(PDO::FETCH_NUM);
                $stB = $conectado->prepare($sqlB);
                $stB->execute([$idregdocs]);
                $historial = array_merge($historial, $stB->fetchAll(PDO::FETCH_NUM));
                usort($historial, function ($a, $b) {
                    $c = strcmp((string)$a[8], (string)$b[8]);
                    return $c !== 0 ? $c : (intval($a[0]) - intval($b[0]));
                });
                echo json_encode($historial);
                return;
            }

            if ($iddocs_emitidos > 0) {
                // Dos consultas separadas unidas en PHP (evita "Illegal mix of
                // collations" entre docs_emitidos y movimientos tras utf8mb4).
                $sqlA = "select de.iddocs_emitidos as codigo,
                                       date_format(date_add(de.fecha,interval 0 hour),'%d/%m/%Y') as fecha,
                                       date_format(date_add(de.fecha,interval 0 hour),'%h:%i %p') as hora,
                                       coalesce(ofi.nombre,'') as oficina,
                                       'Registro' as accion,
                                       'Emitido, sin recepcionar' as estado,
                                       coalesce(de.detalles,'') as detalles,
                                       'Registro' as evento,
                                       de.fecha as orden
                                from docs_emitidos de
                                left join oficinas ofi on de.idoficina_registro=ofi.idoficinas
                                where de.iddocs_emitidos=?";
                $sqlB = "select m.idMovimientos as codigo,
                                       date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y') as fecha,
                                       date_format(date_add(m.fecha_hora,interval 0 hour),'%h:%i %p') as hora,
                                       coalesce(ofi.nombre,ofid.nombre,'') as oficina,
                                       coalesce(acc.nombre_accion,'--') as accion,
                                       coalesce(est.nombre,'--') as estado,
                                       coalesce(m.detalles,m.detalle_derivado,'') as detalles,
                                       case when m.idmovimientoant is null then 'Origen'
                                            when m.estado=0 then 'Ubicación actual'
                                            else 'Movimiento' end as evento,
                                       m.fecha_hora as orden
                                from movimientos m
                                left join responsables r on m.idresponsable=r.idresponsables
                                left join oficinas ofi on r.idoficina=ofi.idoficinas
                                left join oficinas ofid on m.idoficina_derivar=ofid.idoficinas
                                left join aciones_documento acc on m.idacciondocs=acc.idaciones_documento
                                left join estado_documento est on m.idestadodocs=est.idestado_documento
                                where m.iddocs_emitidos=?";
                $stA = $conectado->prepare($sqlA);
                $stA->execute([$iddocs_emitidos]);
                $historial = $stA->fetchAll(PDO::FETCH_NUM);
                $stB = $conectado->prepare($sqlB);
                $stB->execute([$iddocs_emitidos]);
                $historial = array_merge($historial, $stB->fetchAll(PDO::FETCH_NUM));
                usort($historial, function ($a, $b) {
                    $c = strcmp((string)$a[8], (string)$b[8]);
                    return $c !== 0 ? $c : (intval($a[0]) - intval($b[0]));
                });
                echo json_encode($historial);
                return;
            }

            echo json_encode(array());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo json_encode(array());
        }
    }
    public function lista_oficinas()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT oficinas.idoficinas,
                        oficinas.nombre
                    FROM oficinas;";
            $result = $conectado->prepare($sql);
            $result->execute();
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo json_encode(array());
        }
    }
    public function gen_num_exp($idoficina)
    {
        // Número de registro interno por oficina, atómico (MGD) — antes usaba
        // MAX()+1 y podía repetirse si dos personas recibían al mismo tiempo.
        require_once __DIR__ . '/gestion_documental.php';
        try {
            return sgd_nuevo_registro_interno($idoficina);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return null;
        }
    }
    public function insertar_datos_respuesta($iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $adjunto)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO docs_emitidos(iddocumento,idresponsable,idoficina,asunto,detalles,numero,adjunto)
                          VALUES(?,?,?,?,?,?,?);";
            $result = $conectado->prepare($sql);

            $result->execute([$iddocumento, $idresponsable, $idoficina, $asunto, $detalles, $numero, $adjunto]);
            $stmt = $conectado->prepare("SELECT LAST_INSERT_ID()");
            $stmt->execute();
            $lastId = $stmt->fetchColumn();
            return $lastId;
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }
    public function insertar_datos($idregdocs, $iddocs_emitidos, $idaccion, $idoficina, $idresponsable, $num_exp, $detalles, $folio)
    {
        if (intval($idregdocs) > 0) {
            $iddocs_emitidos = null;
        } else {
            $idregdocs = null;
        }

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO movimientos(idregdocs,iddocs_emitidos,idacciondocs,idoficina_derivar,idresponsable,num_exp,detalles,folio)
                    VALUES(?,?,?,?,?,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$idregdocs, $iddocs_emitidos, $idaccion, $idoficina, $idresponsable, $num_exp, $detalles, $folio]);
            // Devuelve el id del movimiento creado (lo usa "recepcionar y derivar")
            return intval($conectado->lastInsertId());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            return 0;
        }
    }
    public function modificar_datos($codigo, $nombre, $descripcion)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "UPDATE oficinas
                            SET
                            nombre = ?,
                            descripcion = ?
                            WHERE idoficinas =?;";
            $result = $conectado->prepare($sql);
            $result->execute([$nombre, $descripcion, $codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "delete from movimientos where idMovimientos=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function obtener_datos_insertar($idmovimiento)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT idregdocs,iddocs_emitidos FROM movimientos
                        where idMovimientos=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idmovimiento]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function insert_deriv_resp_dev($idregdocs, $iddocs_emitidos, $idacciondocs, $idestado, $idoficina_derivar, $detalles, $codigo, $nuevo_estado, $iddocrespuesta)
    {
        if (intval($idregdocs) > 0) {
            $iddocs_emitidos = null;
        } else {
            $idregdocs = null;
        }
        if (intval($iddocrespuesta) <= 0) {
            $iddocrespuesta = null;
        } else {
            $iddocrespuesta = intval($iddocrespuesta);
        }

        try {

            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO movimientos(idregdocs,iddocs_emitidos,idacciondocs,idestadodocs,idoficina_derivar,detalle_derivado,idmovimientoant)
                    VALUES(?,?,?,?,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$idregdocs, $iddocs_emitidos, $idacciondocs, $idestado, $idoficina_derivar, $detalles, $codigo]);
            $sqll = "update movimientos
                        set estado=1,idestadodocs=?,iddocrespuesta=?
                        where idMovimientos=? ;";
            $resultt = $conectado->prepare($sqll);
            $resultt->execute([$nuevo_estado, $iddocrespuesta, $codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function update_status($valor, $codigo)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "update movimientos
                        set idestadodocs=?
                        where idMovimientos=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$valor, $codigo]);
        } catch (Exception $exc) {
            echo json_encode($exc->getMessage());
        }
    }
    public function lista_derivados($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "(select data_nueva.idMovimientos,concat(data_nueva.nombre_documento,' N:',data_nueva.numero_docu,'/ ', data_nueva.asunto,' Det.: ',data_nueva.detalle_derivado,'/ ',data_anterior.nombre)as detalle_derivado
from 
(select oficinas.nombre,movimientos.idMovimientos
from movimientos
inner join responsables on movimientos.idresponsable=responsables.idresponsables
inner join oficinas on responsables.idoficina=oficinas.idoficinas) as data_anterior
inner join

(select movimientos.idMovimientos,documentos.nombre_documento,registro_documentos.numero_docu,registro_documentos.asunto,movimientos.detalle_derivado,	movimientos.idmovimientoant as movant,
	(select num_exp from movimientos where idMovimientos=movant) as num_exp
	from movimientos
	inner join registro_documentos on movimientos.idregdocs=registro_documentos.idregistro_documentos
	inner join oficinas on movimientos.idoficina_derivar= oficinas.idoficinas
    inner join aciones_documento on movimientos.idacciondocs=aciones_documento.idaciones_documento
    inner join estado_documento on movimientos.idestadodocs=estado_documento.idestado_documento
    inner join documentos on registro_documentos.iddocumento=documentos.iddocumentos
    where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0 ) as data_nueva
on data_anterior.idMovimientos=data_nueva.movant)
union
    (
select data_nueva.idMovimientos,concat(data_nueva.nombre_documento,' N:',data_nueva.numero,'/ ', data_nueva.asunto,' Det.: ',data_nueva.detalle_derivado,'/ ',data_anterior.nombre)as detalle_derivado
from 
(select oficinas.nombre,movimientos.idMovimientos
from movimientos
inner join responsables on movimientos.idresponsable=responsables.idresponsables
inner join oficinas on responsables.idoficina=oficinas.idoficinas) as data_anterior
inner join

(select movimientos.idMovimientos,documentos.nombre_documento,docs_emitidos.numero,docs_emitidos.asunto,movimientos.detalle_derivado,	movimientos.idmovimientoant as movant,
	(select num_exp from movimientos where idMovimientos=movant) as num_exp
	from movimientos
	inner join docs_emitidos on movimientos.iddocs_emitidos=docs_emitidos.iddocs_emitidos
    inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
	inner join oficinas on responsables.idoficina=oficinas.idoficinas
    inner join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
    where movimientos.idoficina_derivar=? and movimientos.idmovimientoant is not null and movimientos.estado=0 ) as data_nueva
on data_anterior.idMovimientos=data_nueva.movant);";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina, $idoficina]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo json_encode(array());
        }
    }
    public function recibir_derivado($idresponsable, $numexp, $detalles, $folio, $idmovimiento)
    {
        try {
            //              if (session_status() == PHP_SESSION_NONE) {
            //                    header("location: ../login.php");
            //                   return;
            //               }
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "update movimientos
                    set idestadodocs=1,idresponsable=?,num_exp=?,detalles=?,fecha_hora=now(),estado=1,folio=?
                    where idMovimientos=?";
            $result = $conectado->prepare($sql);
            $result->execute([$idresponsable, $numexp, $detalles, $folio, $idmovimiento]);
        } catch (Exception $exc) {
            echo json_encode($exc->getMessage());
            die;
        }
    }
    public function obtener_docs_emitidos($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select docs_emitidos.iddocs_emitidos,concat(documentos.nombre_documento,' N:',docs_emitidos.numero,' - ',docs_emitidos.asunto,' - ',date(docs_emitidos.fecha),'/ ',oficinas.nombre) as detalles
from docs_emitidos
                        left join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                        left join oficinas on responsables.idoficina=oficinas.idoficinas
                        left join documentos on docs_emitidos.iddocumento=documentos.iddocumentos
                        where oficinas.idoficinas=? and docs_emitidos.idoficina is null
                        and not exists(
                        select iddocrespuesta
                        from movimientos
                        where movimientos.iddocrespuesta=docs_emitidos.iddocs_emitidos)
                        order by docs_emitidos.iddocs_emitidos desc;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo json_encode(array());
        }
    }
    public function recuperar_archivadores($idoficina)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select archivador.idarchivador,archivador.nombre
           from archivador
           where idoficina=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$idoficina]);
            echo json_encode($result->fetchAll());
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo json_encode(array());
        }
    }
    public function insert_archivar($idarchivador, $idmovimiento, $detalles, $idestadodoc)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO docs_archivados(iddocs_archivados,idarchivador,idmovimientos,detalles)
           VALUES(null,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$idarchivador, $idmovimiento, $detalles]);
            $sqll = "update movimientos
           set idestadodocs=?
           where idMovimientos=?;";
            $resultt = $conectado->prepare($sqll);
            $resultt->execute([$idestadodoc, $idmovimiento]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            echo json_encode(array());
        }
    }
}

$dml_recep_docs = new Recepcion_proveido();

// Protección CSRF: valida el token solo en acciones de escritura
if (
    isset($_POST['insertar']) || isset($_POST['Eliminar']) || isset($_POST['insert_deriv_resp_dev'])
    || isset($_POST['update_status']) || isset($_POST['recibir_derivado']) || isset($_POST['insert_archivar'])
) {
    csrf_require();
    exigir_acceso_endpoint();
}

if (isset($_POST['carga_docs_emitidos'])) {
    $dml_recep_docs->lista_docs_emitidos($_POST['idoficina']);
}
if (isset($_POST['carga_aciones'])) {
    $dml_recep_docs->lista_acciones();
}
if (isset($_POST['carga_estados'])) {
    $dml_recep_docs->lista_estados_json();
}
if (isset($_POST['historial_documento'])) {
    $dml_recep_docs->historial_documento($_POST['codigo'], isset($_POST['tipo']) ? $_POST['tipo'] : '');
}
if (isset($_POST['carga_oficinas'])) {
    $dml_recep_docs->lista_oficinas();
}
if (isset($_POST['carga_externos'])) {
    $dml_recep_docs->lista_docs_externos($_POST['idoficina']);
}
if (isset($_POST['carga_derivados'])) {
    $dml_recep_docs->lista_derivados($_POST['idoficina']);
}
if (isset($_POST['insertar'])) {
    // Validación de servidor (no confiar solo en el JavaScript del modal)
    $folio = isset($_POST['Folio']) ? trim((string)$_POST['Folio']) : '';
    if (!is_numeric($folio) || intval($folio) < 1) {
        echo 'ERROR_VALIDACION: Ingrese una cantidad de folios válida (entero mayor a 0).';
        exit;
    }
    $folio = intval($folio);
    $idaccion = isset($_POST['idacciondocs']) ? intval($_POST['idacciondocs']) : 0;
    if ($idaccion < 1) {
        echo 'ERROR_VALIDACION: Seleccione una acción a realizar.';
        exit;
    }
    $idregdocs = $_POST['idregdocs'];
    $iddocs_emitidos = $_POST['iddocs_emitidos'];
    // (Seguridad) El actor y su oficina salen de la SESIÓN, no del POST:
    // así nadie puede recepcionar a nombre de otro responsable u otra oficina.
    $contexto = sisgedo_contexto_usuario_actual();
    $idresponsable = !empty($contexto['idresponsable']) ? intval($contexto['idresponsable']) : null;
    $idoficina     = !empty($contexto['idoficina']) ? intval($contexto['idoficina']) : 0;
    $detalles = $_POST['detalles'];
    $num_exp = $dml_recep_docs->gen_num_exp($idoficina);
    $nuevo_mov = $dml_recep_docs->insertar_datos($idregdocs, $iddocs_emitidos, $idaccion, $idoficina, $idresponsable, $num_exp, $detalles, $folio);
    // Devuelve el id del movimiento creado; los llamadores antiguos ignoran la respuesta.
    echo intval($nuevo_mov);
}
if (isset($_POST['Eliminar'])) {
    $dml_recep_docs->elimnar_datos($_POST['Codigo']);
}
if (isset($_POST['Obtener'])) {
    $dml_recep_docs->obtener_datos_insertar($_POST['Codigo']);
}
if (isset($_POST['insert_deriv_resp_dev'])) {
    //cargando los archivos que vien completamente por formappend

    $iddocumento = isset($_POST["Documento"]) ? intval($_POST["Documento"]) : 0;
    // (Seguridad) El responsable que deriva/responde sale de la SESIÓN, no del POST.
    $idresponsable = !empty($_SESSION['idresponsable']) ? intval($_SESSION['idresponsable']) : 0;
    $cadena = isset($_POST["listaoficinas"]) ? $_POST["listaoficinas"] : '';
    $asunto = isset($_POST["Asunto"]) ? $_POST["Asunto"] : '';
    $detalles = isset($_POST["Detalles"]) ? $_POST["Detalles"] : '';
    $numero = isset($_POST["NumDoc"]) ? $_POST["NumDoc"] : '';
    $nombre_fichero = null;
    if (isset($_FILES['fichero'])) {
        if (is_uploaded_file($_FILES['fichero']['tmp_name'])) {
            require_once __DIR__ . '/util_subida.php';
            if (!sisgedo_validar_archivo($_FILES['fichero'], $err_subida)) {
                echo $err_subida;
                return;
            }
            $nombre_fichero = sisgedo_nombre_seguro($_FILES['fichero']);
            $ruta = "../docs adjs docs_emitidos/";
            $upload = $ruta . $nombre_fichero;
            if (!move_uploaded_file($_FILES['fichero']['tmp_name'], $upload)) { //movemos el archivo a su ubicacion
                echo "ERROR AL SUBIR EL ARCHIVO";
                return;
            }
            require_once __DIR__ . '/gestion_documental.php';
            sgd_registrar_archivo('EMITIDO', $nombre_fichero, $upload, $_FILES['fichero']['name'], 'INTERNO');
        }
    }
    //insertamos el documento de respuesta SOLO si realmente hay uno
    // (al derivar/devolver no se adjunta documento; antes se intentaba insertar
    //  una fila vacía en docs_emitidos con iddocumento=0)
    $iddocrespuesta = null;
    if ($iddocumento > 0) {
        // Numeración automática (MGD) si no se indicó número
        require_once __DIR__ . '/gestion_documental.php';
        $ofiResp = !empty($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
        if (trim((string)$numero) === '' && $ofiResp > 0) {
            $auto = sgd_reservar_numero_emitido($ofiResp, $iddocumento);
            if ($auto !== null) {
                $numero = $auto;
            }
        } elseif ($ofiResp > 0) {
            sgd_registrar_numero_manual($ofiResp, $iddocumento, $numero);
        }
        $iddocrespuesta = $dml_recep_docs->insertar_datos_respuesta($iddocumento, $idresponsable, null, $asunto, $detalles, $numero, $nombre_fichero);
        if ($iddocrespuesta) {
            sgd_obtener_cvd('EMITIDO', $iddocrespuesta);
        }
    }
    //insertamos el movimiento
    $dml_recep_docs->insert_deriv_resp_dev($_POST['idregdocs'], $_POST['iddocs_emitidos'], $_POST['idacciondocs'], $_POST['idestadodocs'], $_POST['idoficina_derivar'], $_POST['detalles'], $_POST['Codigo'], $_POST['NuevoEstado'], $iddocrespuesta);
}
if (isset($_POST['update_status'])) {
    $dml_recep_docs->update_status($_POST['codigo'], $_POST['id']);
}
if (isset($_POST['recibir_derivado'])) {
    // Validación de servidor (no confiar solo en el JavaScript del modal)
    $folio_rd = isset($_POST['Folio']) ? trim((string)$_POST['Folio']) : '';
    if (!is_numeric($folio_rd) || intval($folio_rd) < 1) {
        echo 'ERROR_VALIDACION: Ingrese una cantidad de folios válida (entero mayor a 0).';
        exit;
    }
    // (Seguridad) El actor y su oficina salen de la SESIÓN, no del POST.
    $contexto = sisgedo_contexto_usuario_actual();
    $idresponsable_rd = !empty($contexto['idresponsable']) ? intval($contexto['idresponsable']) : null;
    $idoficina_rd     = !empty($contexto['idoficina']) ? intval($contexto['idoficina']) : 0;
    $num_exp = $dml_recep_docs->gen_num_exp($idoficina_rd);
    $dml_recep_docs->recibir_derivado($idresponsable_rd, $num_exp, $_POST['detalles'], intval($folio_rd), $_POST['codigo']);
}
if (isset($_POST['carga_respuestas'])) {
    $dml_recep_docs->obtener_docs_emitidos($_POST['idoficina']);
}
if (isset($_POST['verificar_cantidad'])) {
    $dml_recep_docs->cant_lista($_POST['idoficina']);
}
if (isset($_POST['get_archivadores'])) {
    $dml_recep_docs->recuperar_archivadores($_POST['idoficina']);
}
if (isset($_POST['insert_archivar'])) {
    $dml_recep_docs->insert_archivar($_POST['idarchivador'], $_POST['idmovimiento'], $_POST['detalles'], $_POST['idestadodoc']);
}
