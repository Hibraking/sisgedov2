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
$idoficina = isset($_SESSION['idoficina']) ? intval($_SESSION['idoficina']) : 0;
$tok = csrf_token();

$filas = array();
$docsArch = 0;
$anioActual = 0;
try {
    $pdo = (new Conexion())->conectar();
    $st = $pdo->prepare("SELECT idarchivador, nombre, COALESCE(detalles,''), COALESCE(anio,'') FROM archivador WHERE idoficina = ? ORDER BY anio DESC, nombre ASC");
    $st->execute(array($idoficina));
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    $st = $pdo->prepare("SELECT COUNT(*) FROM docs_archivados da INNER JOIN archivador a ON da.idarchivador=a.idarchivador WHERE a.idoficina=?");
    $st->execute(array($idoficina));
    $docsArch = intval($st->fetchColumn());
    $st = $pdo->prepare("SELECT COUNT(*) FROM archivador WHERE idoficina=? AND anio=year(now())");
    $st->execute(array($idoficina));
    $anioActual = intval($st->fetchColumn());
} catch (Exception $e) {
    error_log($e->getMessage());
}

$CAT = array(
    'titulo'    => 'Archivadores',
    'subtitulo' => 'Archivadores de tu oficina (los documentos archivados se guardan aquí)',
    'icono'     => 'fa-archive',
    'activo'    => 'vistas/v_archivador.php',
    'endpoint'  => '../clases/archivador.php',
    'singular'  => 'el archivador',
    'columnas'  => array('Nombre', 'Detalles', 'Año'),
    'campos'    => array(
        array('post' => 'Nombre', 'label' => 'Nombre', 'tipo' => 'text', 'req' => true),
        array('post' => 'Detalles', 'label' => 'Detalles', 'tipo' => 'textarea', 'req' => false),
        array('post' => 'anio', 'label' => 'Año', 'tipo' => 'number', 'req' => true),
    ),
    'datos'     => $filas,
    'kpis_extra' => array(
        array('Docs. archivados', number_format($docsArch), 'Guardados en tus archivadores', 'fa-file-text-o', 't-green', 'lab-green'),
        array('Del ' . date('Y'), number_format($anioActual), 'Archivadores del año actual', 'fa-calendar', 't-purple', 'lab-purple'),
    ),
);
include __DIR__ . '/../partes/plantilla_catalogo.php';
