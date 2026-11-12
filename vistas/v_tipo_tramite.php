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
$tramAnio = 0;
$tiposEnUso = 0;
try {
    $pdo = (new Conexion())->conectar();
    $st = $pdo->prepare("SELECT idtipo_tram, nombre, COALESCE(descripcion,'') FROM tipo_tram ORDER BY nombre ASC");
    $st->execute();
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    $tramAnio = intval($pdo->query("SELECT COUNT(*) FROM registro_documentos WHERE fecha_registro >= makedate(year(now()),1) and fecha_registro < makedate(year(now())+1,1)")->fetchColumn());
    $tiposEnUso = intval($pdo->query("SELECT COUNT(DISTINCT idtipo_tramite) FROM registro_documentos WHERE fecha_registro >= makedate(year(now()),1) and fecha_registro < makedate(year(now())+1,1)")->fetchColumn());
} catch (Exception $e) {
    error_log($e->getMessage());
}

$CAT = array(
    'titulo'    => 'Tipos de Trámite',
    'subtitulo' => 'Catálogo de tipos de trámite de la mesa de partes',
    'icono'     => 'fa-random',
    'activo'    => 'vistas/v_tipo_tramite.php',
    'endpoint'  => '../clases/tipo_tramite.php',
    'singular'  => 'el tipo de trámite',
    'columnas'  => array('Nombre', 'Descripción'),
    'campos'    => array(
        array('post' => 'Nombre', 'label' => 'Nombre', 'tipo' => 'text', 'req' => true),
        array('post' => 'Descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'req' => false),
    ),
    'datos'     => $filas,
    'kpis_extra' => array(
        array('Trámites ' . date('Y'), number_format($tramAnio), 'Registrados este año', 'fa-file-text-o', 't-green', 'lab-green'),
        array('Tipos en uso', number_format($tiposEnUso), 'Con registros este año', 'fa-check-circle', 't-purple', 'lab-purple'),
    ),
);
include __DIR__ . '/../partes/plantilla_catalogo.php';
