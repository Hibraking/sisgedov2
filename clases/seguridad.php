<?php

/**
 * Utilidades de seguridad de salida (anti-XSS).
 *
 *   h($texto)  -> escapa un texto para mostrarlo dentro de HTML de forma segura.
 *
 * Uso:  echo h($fila['asunto']);
 */
if (!function_exists('h')) {
    function h($valor)
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

/* =====================================================================
 *  Protección CSRF
 * ===================================================================== */

if (!function_exists('csrf_token')) {
    /** Devuelve (creándolo si hace falta) el token CSRF de la sesión. */
    function csrf_token()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = function_exists('random_bytes')
                ? bin2hex(random_bytes(32))
                : md5(uniqid((string)mt_rand(), true));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_meta')) {
    /** Etiqueta <meta> para poner en el <head> de las vistas. */
    function csrf_meta()
    {
        return '<meta name="csrf-token" content="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_validar')) {
    /** Verifica el token recibido (cabecera X-CSRF-Token o campo _csrf). */
    function csrf_validar()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $enviado = null;
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (!empty($_POST['_csrf'])) {
            $enviado = $_POST['_csrf'];
        }
        return !empty($_SESSION['csrf_token'])
            && is_string($enviado)
            && hash_equals($_SESSION['csrf_token'], $enviado);
    }
}

if (!function_exists('csrf_require')) {
    /** Corta la ejecución si el token no es válido. Usar al inicio de handlers. */
    function csrf_require()
    {
        if (!csrf_validar()) {
            http_response_code(403);
            echo 'ERROR_CSRF: token de seguridad inválido o ausente. Recargue la página e intente de nuevo.';
            exit;
        }
    }
}

if (!function_exists('exigir_sesion_endpoint')) {
    /**
     * Corta con 401 si no hay sesión iniciada. Usar en endpoints AJAX internos
     * (clases/*.php) que solo deben responder a usuarios autenticados.
     * No usar en endpoints públicos (mpvirtual, consulta_publico, login,
     * ni el alta anónima de remitentes).
     */
    function exigir_sesion_endpoint()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['usuario']) && empty($_SESSION['alias'])) {
            http_response_code(401);
            echo 'ERROR_SESION: sesión no iniciada o expirada. Vuelva a iniciar sesión.';
            exit;
        }
    }
}

/* =====================================================================
 *  Autorización por permisos de menú (tabla accesos_usuario)
 * ===================================================================== */

if (!function_exists('usuario_tiene_acceso')) {
    /**
     * ¿El usuario en sesión tiene acceso al menú cuya dirección es $direccion?
     * Salvaguardas para NO bloquear indebidamente:
     *   - Admin (idusuario === 1) siempre tiene acceso.
     *   - Si $direccion no corresponde a un ítem de menú, se permite (no está gateado).
     *   - Ante cualquier error de BD, se permite (fail-open).
     */
    function usuario_tiene_acceso($direccion)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $idusuario = isset($_SESSION['usuario']) ? intval($_SESSION['usuario']) : 0;
        if ($idusuario === 0) {
            return false;
        } // no hay sesión
        if ($idusuario === 1) {
            return true;
        }  // admin: acceso total (salvaguarda)

        try {
            require_once __DIR__ . '/../conexion/conexion.php';
            $pdo = (new Conexion())->conectar();
            if (!$pdo) {
                return true;
            } // sin conexión -> no bloquear

            $m = $pdo->prepare("SELECT idmenu FROM menu WHERE direccion = ? LIMIT 1");
            $m->execute([$direccion]);
            $idmenu = $m->fetchColumn();
            if ($idmenu === false) {
                return true;
            } // no es ítem de menú -> no gateado

            $q = $pdo->prepare("SELECT COUNT(*) FROM accesos_usuario WHERE idusuario = ? AND idmenu = ? AND access = 1");
            $q->execute([$idusuario, $idmenu]);
            return ((int)$q->fetchColumn()) > 0;
        } catch (Exception $e) {
            error_log('usuario_tiene_acceso: ' . $e->getMessage());
            return true; // ante error, no bloquear
        }
    }
}

if (!function_exists('exigir_acceso')) {
    /** Si el usuario no tiene acceso a $direccion, lo manda al inicio. Usar antes de imprimir HTML. */
    function exigir_acceso($direccion)
    {
        if (!usuario_tiene_acceso($direccion)) {
            header('Location: ../index.php');
            exit;
        }
    }
}

if (!function_exists('exigir_acceso_endpoint')) {
    /**
     * Autorización a nivel de endpoint (para llamadas AJAX/POST a clases/*.php).
     * Mapea el archivo actual a su permiso de menú y responde 403 si no lo tiene.
     * Solo se mapean endpoints con relación 1:1 clara (gestión y catálogos). Los
     * de operaciones que se invocan desde varias vistas quedan sin mapear (no-op),
     * para no romper flujos legítimos entre módulos.
     */
    function exigir_acceso_endpoint()
    {
        $mapa = array(
            'personal.php'          => 'vistas/v_personal.php',
            'accesos_personal.php'  => 'vistas/v_accesos_personal.php',
            'menus.php'             => 'vistas/v_menu.php',
            'oficinas.php'          => 'vistas/v_oficinas.php',
            'documentos.php'        => 'vistas/v_documentos.php',
            'tipo_tramite.php'      => 'vistas/v_tipo_tramite.php',
            'accion.php'            => 'vistas/v_accion.php',
            'estado_doc.php'        => 'vistas/v_estados_doc.php',
            'archivador.php'        => 'vistas/v_archivador.php',
            'responsables.php'      => 'vistas/v_responsables.php',
            'remitentes.php'        => 'vistas/v_remitentes.php',
            'mesa_partes_virtual.php' => 'vistas/v_mesa_partes_virtual.php',
        );
        $base = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
        if (!isset($mapa[$base])) {
            return;
        } // endpoint no mapeado -> no gateado
        if (!usuario_tiene_acceso($mapa[$base])) {
            http_response_code(403);
            echo 'ERROR_ACCESO: no tiene permisos para realizar esta acción.';
            exit;
        }
    }
}
