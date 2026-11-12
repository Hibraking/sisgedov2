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
$estEnUso = 0;
try {
    $pdo = (new Conexion())->conectar();
    $st = $pdo->prepare("SELECT idestado_documento, nombre, COALESCE(descripcion,'') FROM estado_documento ORDER BY idestado_documento ASC");
    $st->execute();
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    $usosAnio = intval($pdo->query("SELECT COUNT(*) FROM movimientos WHERE idestadodocs IS NOT NULL AND fecha_hora >= makedate(year(now()),1) and fecha_hora < makedate(year(now())+1,1)")->fetchColumn());
    $estEnUso = intval($pdo->query("SELECT COUNT(DISTINCT idestadodocs) FROM movimientos WHERE idestadodocs IS NOT NULL AND fecha_hora >= makedate(year(now()),1) and fecha_hora < makedate(year(now())+1,1)")->fetchColumn());
} catch (Exception $e) {
    error_log($e->getMessage());
}

$CAT = array(
    'titulo'    => 'Estados de Documento',
    'subtitulo' => 'Catálogo de estados del trámite (recepcionado, derivado, archivado, etc.). Cuidado: los flujos del sistema dependen de estos estados.',
    'icono'     => 'fa-flag-o',
    'activo'    => 'vistas/v_estados_doc.php',
    'endpoint'  => '../clases/estado_doc.php',
    'singular'  => 'el estado',
    'columnas'  => array('Nombre', 'Descripción'),
    'campos'    => array(
        array('post' => 'Nombre', 'label' => 'Nombre', 'tipo' => 'text', 'req' => true),
        array('post' => 'Descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'req' => false),
    ),
    'datos'     => $filas,
    'kpis_extra' => array(
        array('Usos ' . date('Y'), number_format($usosAnio), 'Movimientos con estado', 'fa-check-circle', 't-green', 'lab-green'),
        array('En uso', number_format($estEnUso), 'Estados aplicados este año', 'fa-flag-o', 't-purple', 'lab-purple'),
    ),
);
include __DIR__ . '/../partes/plantilla_catalogo.php';
