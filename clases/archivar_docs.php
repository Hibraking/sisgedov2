<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
// Endpoint interno: cualquier POST sin sesión iniciada se rechaza con 401.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
   exigir_sesion_endpoint();
}
class Archivar_docs
{
   public function lista_docs_emitidos($idoficina)
   {
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $sql = "select docs_emitidos.iddocs_emitidos,concat(docs_emitidos.numero,' - ',docs_emitidos.asunto,' - ',DATE_FORMAT(docs_emitidos.fecha,'%d/%m/%y')) as detalles
                        from docs_emitidos
                        inner join responsables on docs_emitidos.idresponsable=responsables.idresponsables
                        where responsables.idoficina=? and not exists (select * from docs_archivados
                        where docs_archivados.iddocs_emitidos=docs_emitidos.iddocs_emitidos ) 
                        order by docs_emitidos.fecha desc;";
         $result = $conectado->prepare($sql);
         $result->execute([$idoficina]);
         echo json_encode($result->fetchAll());
      } catch (Exception $exc) {
         error_log($exc->getMessage());
      }
   }
   public function lista_docs_tramitados($idoficina)
   {
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $sql = "call lista_archivar_tramitados(?);";
         $result = $conectado->prepare($sql);
         $result->execute([$idoficina]);
         echo json_encode($result->fetchAll());
      } catch (Exception $exc) {
         error_log($exc->getMessage());
      }
   }
   public function lista_archivadores($idoficina)
   {
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $sql = "select idarchivador,concat(nombre,'-',anio) as detalles
                        from archivador
                        where idoficina=?
                        order by nombre asc,anio desc";
         $result = $conectado->prepare($sql);
         $result->execute([$idoficina]);
         echo json_encode($result->fetchAll());
      } catch (Exception $exc) {
         error_log($exc->getMessage());
      }
   }
        // ================= NUEVO (solo lectura, para la vista rediseñada) =================
        // Todas las consultas se ejecutan por separado y se unen en PHP para evitar
        // "Illegal mix of collations" entre tablas con collation distinta (utf8mb4 parcial).

   /** Archivadores de la oficina con conteo de documentos. */
   public function datos_archivadores($idoficina)
   {
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $sql = "select a.idarchivador,a.nombre,a.anio,coalesce(a.detalles,'') as detalles,
                        (select count(*) from docs_archivados da where da.idarchivador=a.idarchivador) as ndocs
                      from archivador a where a.idoficina=? order by a.anio desc, a.nombre asc";
         $st = $conectado->prepare($sql);
         $st->execute([$idoficina]);
         return $st->fetchAll(PDO::FETCH_NUM);
      } catch (Exception $exc) {
         error_log($exc->getMessage());
         return array();
      }
   }

   /** Documentos pendientes de archivar: emitidos propios + tramitados recibidos.
    *  Columnas: 0 id, 1 clase(EMITIDO|TRAMITADO), 2 tipo doc, 3 numero/exp,
    *  4 asunto, 5 detalles, 6 fecha display, 7 fecha raw, 8 adjunto */
   public function datos_por_archivar($idoficina, $desde, $hasta)
   {
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $filas = array();
         // a) Documentos emitidos por la oficina, aún sin archivar (sin ventana: es trabajo pendiente)
         $q = "select de.iddocs_emitidos,'EMITIDO',documentos.nombre_documento,de.numero,de.asunto,coalesce(de.detalles,''),
                      date_format(date_add(de.fecha,interval 0 hour),'%d/%m/%Y %h:%i %p'),de.fecha,
                      if(de.adjunto is null,'#',concat('../docs adjs docs_emitidos/',de.adjunto))
                    from docs_emitidos de
                    inner join responsables r on de.idresponsable=r.idresponsables
                    inner join documentos on de.iddocumento=documentos.iddocumentos
                    where r.idoficina=? and not exists (select 1 from docs_archivados da where da.iddocs_emitidos=de.iddocs_emitidos)";
         $st = $conectado->prepare($q);
         $st->execute([$idoficina]);
         $filas = array_merge($filas, $st->fetchAll(PDO::FETCH_NUM));
         // b) Movimientos tramitados (recepcionados) de documentos externos, sin archivar
         $q = "select m.idMovimientos,'TRAMITADO',documentos.nombre_documento,rd.nro_exp,rd.asunto,coalesce(m.detalles,''),
                      date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p'),m.fecha_hora,
                      if(rd.adjunto is null,'#',concat('../docs adjs reg_doc/',rd.adjunto))
                    from movimientos m
                    inner join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
                    inner join documentos on rd.iddocumento=documentos.iddocumentos
                    where m.idoficina_derivar=? and m.estado=1 and date(m.fecha_hora) between ? and ?
                      and not exists (select 1 from docs_archivados da where da.idmovimientos=m.idMovimientos)";
         $st = $conectado->prepare($q);
         $st->execute([$idoficina, $desde, $hasta]);
         $filas = array_merge($filas, $st->fetchAll(PDO::FETCH_NUM));
         // c) Movimientos tramitados de documentos internos, sin archivar
         $q = "select m.idMovimientos,'TRAMITADO',documentos.nombre_documento,de.numero,de.asunto,coalesce(m.detalles,''),
                      date_format(date_add(m.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p'),m.fecha_hora,
                      if(de.adjunto is null,'#',concat('../docs adjs docs_emitidos/',de.adjunto))
                    from movimientos m
                    inner join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
                    inner join documentos on de.iddocumento=documentos.iddocumentos
                    where m.idoficina_derivar=? and m.estado=1 and date(m.fecha_hora) between ? and ?
                      and not exists (select 1 from docs_archivados da where da.idmovimientos=m.idMovimientos)";
         $st = $conectado->prepare($q);
         $st->execute([$idoficina, $desde, $hasta]);
         $filas = array_merge($filas, $st->fetchAll(PDO::FETCH_NUM));
         usort($filas, function ($a, $b) {
            return strcmp((string)$b[7], (string)$a[7]);
         });
         return $filas;
      } catch (Exception $exc) {
         error_log($exc->getMessage());
         return array();
      }
   }

   /** Documentos ya archivados de la oficina.
    *  Columnas: 0 iddocs_archivados, 1 archivador, 2 tipo doc, 3 numero/exp,
    *  4 asunto, 5 detalles del archivado, 6 fecha display, 7 fecha raw,
    *  8 adjunto, 9 clase, 10 idarchivador */
   public function datos_archivados($idoficina)
   {
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $filas = array();
         $q = "select da.iddocs_archivados,concat(a.nombre,' - ',a.anio),documentos.nombre_documento,de.numero,de.asunto,coalesce(da.detalles,''),
                      date_format(date_add(da.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p'),da.fecha_hora,
                      if(de.adjunto is null,'#',concat('../docs adjs docs_emitidos/',de.adjunto)),'EMITIDO',a.idarchivador
                    from docs_archivados da
                    inner join archivador a on da.idarchivador=a.idarchivador
                    inner join docs_emitidos de on da.iddocs_emitidos=de.iddocs_emitidos
                    inner join documentos on de.iddocumento=documentos.iddocumentos
                    where a.idoficina=?";
         $st = $conectado->prepare($q);
         $st->execute([$idoficina]);
         $filas = array_merge($filas, $st->fetchAll(PDO::FETCH_NUM));
         $q = "select da.iddocs_archivados,concat(a.nombre,' - ',a.anio),documentos.nombre_documento,rd.nro_exp,rd.asunto,coalesce(da.detalles,''),
                      date_format(date_add(da.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p'),da.fecha_hora,
                      if(rd.adjunto is null,'#',concat('../docs adjs reg_doc/',rd.adjunto)),'TRAMITADO',a.idarchivador
                    from docs_archivados da
                    inner join archivador a on da.idarchivador=a.idarchivador
                    inner join movimientos m on da.idmovimientos=m.idMovimientos
                    inner join registro_documentos rd on m.idregdocs=rd.idregistro_documentos
                    inner join documentos on rd.iddocumento=documentos.iddocumentos
                    where a.idoficina=?";
         $st = $conectado->prepare($q);
         $st->execute([$idoficina]);
         $filas = array_merge($filas, $st->fetchAll(PDO::FETCH_NUM));
         $q = "select da.iddocs_archivados,concat(a.nombre,' - ',a.anio),documentos.nombre_documento,de.numero,de.asunto,coalesce(da.detalles,''),
                      date_format(date_add(da.fecha_hora,interval 0 hour),'%d/%m/%Y %h:%i %p'),da.fecha_hora,
                      if(de.adjunto is null,'#',concat('../docs adjs docs_emitidos/',de.adjunto)),'TRAMITADO',a.idarchivador
                    from docs_archivados da
                    inner join archivador a on da.idarchivador=a.idarchivador
                    inner join movimientos m on da.idmovimientos=m.idMovimientos
                    inner join docs_emitidos de on m.iddocs_emitidos=de.iddocs_emitidos
                    inner join documentos on de.iddocumento=documentos.iddocumentos
                    where a.idoficina=?";
         $st = $conectado->prepare($q);
         $st->execute([$idoficina]);
         $filas = array_merge($filas, $st->fetchAll(PDO::FETCH_NUM));
         usort($filas, function ($a, $b) {
            return strcmp((string)$b[7], (string)$a[7]);
         });
         return $filas;
      } catch (Exception $exc) {
         error_log($exc->getMessage());
         return array();
      }
   }

   /** Contadores para las tarjetas KPI de la vista de archivo. */
   public function contadores_archivo($idoficina)
   {
      $out = array('archivadores' => 0, 'archivados' => 0, 'emitidos_sin_archivar' => 0, 'tramitados_sin_archivar' => 0);
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $q = $conectado->prepare("select count(*) from archivador where idoficina=?");
         $q->execute([$idoficina]);
         $out['archivadores'] = intval($q->fetchColumn());
         $q = $conectado->prepare("select count(*) from docs_archivados da inner join archivador a on da.idarchivador=a.idarchivador where a.idoficina=?");
         $q->execute([$idoficina]);
         $out['archivados'] = intval($q->fetchColumn());
         $q = $conectado->prepare("select count(*) from docs_emitidos de inner join responsables r on de.idresponsable=r.idresponsables where r.idoficina=? and not exists (select 1 from docs_archivados da where da.iddocs_emitidos=de.iddocs_emitidos)");
         $q->execute([$idoficina]);
         $out['emitidos_sin_archivar'] = intval($q->fetchColumn());
         $q = $conectado->prepare("select count(*) from movimientos m where m.idoficina_derivar=? and m.estado=1 and not exists (select 1 from docs_archivados da where da.idmovimientos=m.idMovimientos)");
         $q->execute([$idoficina]);
         $out['tramitados_sin_archivar'] = intval($q->fetchColumn());
      } catch (Exception $exc) {
         error_log($exc->getMessage());
      }
      return $out;
   }

   public function insert_data($idarchivador, $iddocsemitidos, $idmovimiento, $detalles)
   {
      if (intval($iddocsemitidos) == 0) {
         $iddocsemitidos = null;
      }
      if (intval($idmovimiento) == 0) {
         $idmovimiento = null;
      }
      try {
         $conexion = new Conexion();
         $conectado = $conexion->conectar();
         $sql = "INSERT INTO docs_archivados(idarchivador,iddocs_emitidos,idmovimientos,detalles)
                      VALUES(?,?,?,?);";
         $result = $conectado->prepare($sql);
         $result->execute([$idarchivador, $iddocsemitidos, $idmovimiento, $detalles]);
      } catch (Exception $exc) {
         error_log($exc->getMessage());
      }
   }
}
$dml_archivar_docs = new Archivar_docs();
if (isset($_POST["Insertar"]) || isset($_POST["insertar"]) || isset($_POST["Modificar"]) || isset($_POST["Codigo"]) || isset($_POST["Emilinar"]) || isset($_POST["Eliminar"]) || isset($_POST["Estado"])) {
   csrf_require();
   exigir_acceso_endpoint();
}
if (isset($_POST['carga_internos'])) {
   $dml_archivar_docs->lista_docs_emitidos($_POST['idoficina']);
}
if (isset($_POST['carga_tramitados'])) {
   $dml_archivar_docs->lista_docs_tramitados($_POST['idoficina']);
}
if (isset($_POST['carga_archivador'])) {
   $dml_archivar_docs->lista_archivadores($_POST['idoficina']);
}
if (isset($_POST['insertar'])) {
   $dml_archivar_docs->insert_data($_POST['idarchivador'], $_POST['iddocs_emitidos'], $_POST['idmovimientos'], $_POST['detalles']);
}
