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
$usosAnio = 0;
$accEnUso = 0;
try {
    $pdo = (new Conexion())->conectar();
    $st = $pdo->prepare("SELECT idaciones_documento, nombre_accion, COALESCE(descripcion,'') FROM aciones_documento ORDER BY nombre_accion ASC");
    $st->execute();
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    $usosAnio = intval($pdo->query("SELECT COUNT(*) FROM movimientos WHERE idacciondocs IS NOT NULL AND fecha_hora >= makedate(year(now()),1) and fecha_hora < makedate(year(now())+1,1)")->fetchColumn());
    $accEnUso = intval($pdo->query("SELECT COUNT(DISTINCT idacciondocs) FROM movimientos WHERE idacciondocs IS NOT NULL AND fecha_hora >= makedate(year(now()),1) and fecha_hora < makedate(year(now())+1,1)")->fetchColumn());
} catch (Exception $e) {
    error_log($e->getMessage());
}

$CAT = array(
    'titulo'    => 'Acciones de Documento',
    'subtitulo' => 'Catálogo de acciones al recepcionar o derivar (atención, conocimiento, etc.)',
    'icono'     => 'fa-bolt',
    'activo'    => 'vistas/v_accion.php',
    'endpoint'  => '../clases/accion.php',
    'singular'  => 'la acción',
    'columnas'  => array('Nombre', 'Descripción'),
    'campos'    => array(
        array('post' => 'Nombre', 'label' => 'Nombre', 'tipo' => 'text', 'req' => true),
        array('post' => 'Descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'req' => false),
    ),
    'datos'     => $filas,
    'kpis_extra' => array(
        array('Usos ' . date('Y'), number_format($usosAnio), 'Movimientos con acción', 'fa-check-circle', 't-green', 'lab-green'),
        array('En uso', number_format($accEnUso), 'Acciones aplicadas este año', 'fa-bolt', 't-orange', 'lab-orange'),
    ),
);
include __DIR__ . '/../partes/plantilla_catalogo.php';
