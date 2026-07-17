<?php

/**
 * Guardia de acceso. Se incluye al inicio de cada vista (antes de imprimir HTML).
 * Verifica que exista sesión y que el usuario tenga permiso al menú de la página actual.
 * Es seguro por diseño: el admin siempre pasa, y las páginas sin ítem de menú no se bloquean.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/seguridad.php';

// Debe haber sesión iniciada
if (!isset($_SESSION['usuario']) && !isset($_SESSION['alias'])) {
    header('Location: ../login.php');
    exit;
}

// Dirección de menú de la página actual (coincide con menu.direccion): p.ej. "vistas/v_oficinas.php"
$sisgedo_script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$sisgedo_carpeta = (strpos($sisgedo_script, '/reportes/') !== false) ? 'reportes/' : 'vistas/';
$sisgedo_dir = $sisgedo_carpeta . basename($sisgedo_script);

exigir_acceso($sisgedo_dir);
