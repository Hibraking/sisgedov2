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
$grupos = 0;
$conRuta = 0;
try {
    $pdo = (new Conexion())->conectar();
    $st = $pdo->prepare("SELECT idmenu, nombre_menu, COALESCE(direccion,''), COALESCE(dependencia,''), COALESCE(idmodulo,0), COALESCE(clase_llamar,'') FROM menu ORDER BY idmenu ASC");
    $st->execute();
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    $grupos = intval($pdo->query("SELECT COUNT(*) FROM menu WHERE COALESCE(direccion,'#')='#'")->fetchColumn());
    $conRuta = intval($pdo->query("SELECT COUNT(*) FROM menu WHERE direccion IS NOT NULL AND direccion <> '#'")->fetchColumn());
} catch (Exception $e) {
    error_log($e->getMessage());
}

$CAT = array(
    'titulo'    => 'Menús del Sistema',
    'subtitulo' => 'Ítems del menú y sus rutas. Cuidado: los permisos de acceso y la navegación dependen de esta tabla.',
    'icono'     => 'fa-bars',
    'activo'    => 'vistas/v_menu.php',
    'endpoint'  => '../clases/menus.php',
    'singular'  => 'el ítem de menú',
    'columnas'  => array('Nombre', 'Ruta (dirección)', 'Grupo (dependencia)', 'Módulo', 'Clase'),
    'campos'    => array(
        array('post' => 'Nombre', 'label' => 'Nombre', 'tipo' => 'text', 'req' => true),
        array('post' => 'Ruta', 'label' => 'Ruta (p. ej. vistas/v_oficinas.php, o # para grupos)', 'tipo' => 'text', 'req' => false),
        array('post' => 'Dependencia', 'label' => 'Grupo (dependencia)', 'tipo' => 'text', 'req' => false),
        array('post' => 'Modulo', 'label' => 'Módulo (número)', 'tipo' => 'number', 'req' => false),
        array('post' => 'Clase', 'label' => 'Clase / icono', 'tipo' => 'text', 'req' => false),
    ),
    'datos'     => $filas,
    'kpis_extra' => array(
        array('Módulos con ruta', number_format($conRuta), 'Ítems navegables', 'fa-link', 't-green', 'lab-green'),
        array('Grupos', number_format($grupos), 'Encabezados del menú', 'fa-folder-o', 't-purple', 'lab-purple'),
    ),
);
include __DIR__ . '/../partes/plantilla_catalogo.php';
