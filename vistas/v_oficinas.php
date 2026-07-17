<?php
session_start();
require_once '../clases/guardia.php';
$_SESSION["modulo"] = "1";
if (!isset($_SESSION['alias'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../conexion/conexion.php';
require_once '../clases/seguridad.php';

$alias = isset($_SESSION['alias']) ? $_SESSION['alias'] : 'usuario';
$tok = csrf_token();

$filas = array();
$conResp = 0;
$recibieronMes = 0;
try {
    $pdo = (new Conexion())->conectar();
    $st = $pdo->prepare("SELECT idoficinas, nombre, COALESCE(descripcion,'') FROM oficinas ORDER BY nombre ASC");
    $st->execute();
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    $conResp = intval($pdo->query("SELECT COUNT(DISTINCT idoficina) FROM responsables WHERE estado=1")->fetchColumn());
    $recibieronMes = intval($pdo->query("SELECT COUNT(DISTINCT idoficina_derivar) FROM movimientos WHERE year(fecha_hora)=year(now()) AND month(fecha_hora)=month(now())")->fetchColumn());
} catch (Exception $e) {
    error_log($e->getMessage());
}

$CAT = array(
    'titulo'    => 'Oficinas',
    'subtitulo' => 'Catálogo de oficinas y dependencias de la entidad',
    'icono'     => 'fa-building-o',
    'activo'    => 'vistas/v_oficinas.php',
    'endpoint'  => '../clases/oficinas.php',
    'singular'  => 'la oficina',
    'columnas'  => array('Nombre', 'Descripción'),
    'campos'    => array(
        array('post' => 'Nombre', 'label' => 'Nombre', 'tipo' => 'text', 'req' => true),
        array('post' => 'Descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'req' => false),
    ),
    'datos'     => $filas,
    'kpis_extra' => array(
        array('Con responsable', number_format($conResp), 'Responsable activo asignado', 'fa-user', 't-green', 'lab-green'),
        array('Activas este mes', number_format($recibieronMes), 'Recibieron documentos', 'fa-inbox', 't-purple', 'lab-purple'),
    ),
);
include __DIR__ . '/../partes/plantilla_catalogo.php';
