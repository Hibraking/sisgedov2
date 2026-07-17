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
$enUsoAnio = 0;
$conDias = 0;
try {
    $pdo = (new Conexion())->conectar();
    $st = $pdo->prepare("SELECT iddocumentos, nombre_documento, COALESCE(descripcion,''), COALESCE(dias_atension,0) FROM documentos ORDER BY nombre_documento ASC");
    $st->execute();
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    $enUsoAnio = intval($pdo->query("SELECT COUNT(DISTINCT iddocumento) FROM registro_documentos WHERE year(fecha_registro)=year(now())")->fetchColumn());
    $conDias = intval($pdo->query("SELECT COUNT(*) FROM documentos WHERE COALESCE(dias_atension,0) > 0")->fetchColumn());
} catch (Exception $e) {
    error_log($e->getMessage());
}

$CAT = array(
    'titulo'    => 'Tipos de Documento',
    'subtitulo' => 'Catálogo de tipos de documento (oficio, informe, solicitud, etc.)',
    'icono'     => 'fa-file-text-o',
    'activo'    => 'vistas/v_documentos.php',
    'endpoint'  => '../clases/documentos.php',
    'singular'  => 'el tipo de documento',
    'columnas'  => array('Nombre', 'Descripción', 'Días de atención'),
    'campos'    => array(
        array('post' => 'Nombre', 'label' => 'Nombre', 'tipo' => 'text', 'req' => true),
        array('post' => 'Descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'req' => false),
        array('post' => 'Dias', 'label' => 'Días de atención', 'tipo' => 'number', 'req' => false),
    ),
    'datos'     => $filas,
    'kpis_extra' => array(
        array('En uso este año', number_format($enUsoAnio), 'Tipos con registros ' . date('Y'), 'fa-check-circle', 't-green', 'lab-green'),
        array('Con plazo', number_format($conDias), 'Tienen días de atención', 'fa-clock-o', 't-orange', 'lab-orange'),
    ),
);
include __DIR__ . '/../partes/plantilla_catalogo.php';
