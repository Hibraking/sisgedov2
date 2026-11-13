<?php
/* =====================================================================
   SISGEDO — Supervisión del ADMINISTRADOR: "Ver / actuar en una oficina"
   ---------------------------------------------------------------------
   El administrador (usuario 1) puede:
     - ver el panel de supervisión de TODAS las oficinas (vistas/v_supervision.php);
     - elegir una oficina y trabajar en sus bandejas (Recepción, Archivo,
       Emisión, Derivados, Inicio) como si fuera de esa oficina.
   Trazabilidad: lo que haga en otra oficina queda registrado con un cargo
   propio "Administrador — en nombre de <oficina>" (fila en responsables con
   idpersonal = 1, estado = 0 / inactivo), así el historial muestra la oficina
   correcta Y que fue el administrador quien actuó.
   Cómo funciona: cambia $_SESSION['idoficina'] / ['idresponsable'] (que es lo
   que usan todos los módulos) y guarda los originales para volver.
   ===================================================================== */
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';

if (!function_exists('sgd_es_admin')) {
    function sgd_es_admin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return !empty($_SESSION['usuario']) && (int)$_SESSION['usuario'] === 1;
    }

    /** Oficina "propia" del administrador (la de su cargo real, antes de cambiar). */
    function sgd_admin_original()
    {
        if (isset($_SESSION['admin_original']) && is_array($_SESSION['admin_original'])) {
            return $_SESSION['admin_original'];
        }
        return array(
            'idoficina' => isset($_SESSION['idoficina']) ? (int)$_SESSION['idoficina'] : 0,
            'idresponsable' => isset($_SESSION['idresponsable']) ? (int)$_SESSION['idresponsable'] : 0,
        );
    }

    /**
     * Estado actual: array('en_otra' => bool, 'idoficina' => int, 'nombre' => string, 'propia_nombre' => string)
     * en_otra = true cuando el administrador está trabajando en una oficina que no es la suya.
     */
    function sgd_vista_actual()
    {
        $out = array('en_otra' => false, 'idoficina' => isset($_SESSION['idoficina']) ? (int)$_SESSION['idoficina'] : 0, 'nombre' => '', 'propia_nombre' => '');
        if (!sgd_es_admin()) {
            return $out;
        }
        $orig = sgd_admin_original();
        $out['en_otra'] = !empty($_SESSION['ver_oficina']) && (int)$_SESSION['ver_oficina'] !== (int)$orig['idoficina'];
        try {
            $st = (new Conexion())->conectar()->prepare("SELECT idoficinas, nombre FROM oficinas WHERE idoficinas IN (?, ?)");
            $st->execute(array($out['idoficina'], (int)$orig['idoficina']));
            foreach ($st->fetchAll(PDO::FETCH_NUM) as $o) {
                if ((int)$o[0] === $out['idoficina']) $out['nombre'] = $o[1];
                if ((int)$o[0] === (int)$orig['idoficina']) $out['propia_nombre'] = $o[1];
            }
        } catch (Exception $e) {
            error_log('sgd_vista_actual: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Cargo con el que el administrador actúa en una oficina: el suyo si ya es
     * de esa oficina; si no, uno "en nombre de" (inactivo, se crea una vez).
     */
    function sgd_responsable_en_nombre($idoficina)
    {
        $pdo = (new Conexion())->conectar();
        $st = $pdo->prepare("SELECT idresponsables FROM responsables WHERE idpersonal = 1 AND idoficina = ?
                              ORDER BY (estado = 1) DESC, idresponsables ASC LIMIT 1");
        $st->execute(array((int)$idoficina));
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
        $ins = $pdo->prepare("INSERT INTO responsables (idpersonal, idoficina, estado) VALUES (1, ?, 0)");
        $ins->execute(array((int)$idoficina));
        return (int)$pdo->lastInsertId();
    }

    /** Cambia la oficina de trabajo del administrador. $destino: 'propia' o id de oficina. */
    function sgd_cambiar_oficina($destino)
    {
        if (!sgd_es_admin()) {
            return 'Solo el administrador puede cambiar de oficina.';
        }
        if (!isset($_SESSION['admin_original'])) {
            $_SESSION['admin_original'] = sgd_admin_original();
        }
        $orig = $_SESSION['admin_original'];
        if ($destino === 'propia' || (int)$destino === (int)$orig['idoficina']) {
            $_SESSION['idoficina'] = (int)$orig['idoficina'];
            $_SESSION['idresponsable'] = (int)$orig['idresponsable'];
            unset($_SESSION['ver_oficina'], $_SESSION['admin_original']);
            return true;
        }
        $idofi = (int)$destino;
        try {
            $st = (new Conexion())->conectar()->prepare("SELECT COUNT(*) FROM oficinas WHERE idoficinas = ?");
            $st->execute(array($idofi));
            if ((int)$st->fetchColumn() === 0) {
                return 'La oficina no existe.';
            }
            $_SESSION['idresponsable'] = sgd_responsable_en_nombre($idofi);
            $_SESSION['idoficina'] = $idofi;
            $_SESSION['ver_oficina'] = $idofi;
            return true;
        } catch (Exception $e) {
            error_log('sgd_cambiar_oficina: ' . $e->getMessage());
            return 'No se pudo cambiar de oficina.';
        }
    }

    /** Oficinas para el selector. */
    function sgd_lista_oficinas()
    {
        try {
            return (new Conexion())->conectar()
                ->query("SELECT idoficinas, nombre FROM oficinas WHERE TRIM(COALESCE(nombre,'')) NOT IN ('','-') ORDER BY nombre")
                ->fetchAll(PDO::FETCH_NUM);
        } catch (Exception $e) {
            return array();
        }
    }
}

// ---------------------------------------------------------------------
// Petición: cambiar de oficina (formulario del menú lateral)
// ---------------------------------------------------------------------
if (basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') === 'vista_oficina.php'
    && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_sesion_endpoint();
    csrf_require();
    $destino = isset($_POST['ver_oficina']) ? (string)$_POST['ver_oficina'] : 'propia';
    $volver = isset($_POST['volver']) ? (string)$_POST['volver'] : '';
    if ($destino === 'todas') {
        $r = true;                     // el panel de supervisión no cambia la oficina
        $volver = 'vistas/v_supervision.php';
    } else {
        $r = sgd_cambiar_oficina($destino);
    }
    if ($r !== true) {
        http_response_code(400);
        echo 'ERROR_VALIDACION: ' . $r;
        exit;
    }
    // Solo se vuelve a páginas internas del sistema
    if (!preg_match('~^(vistas|reportes)/[a-z_0-9]+\.php(\?[\w=&%.-]*)?$~i', $volver) && $volver !== 'index.php') {
        $volver = 'index.php';
    }
    header('Location: ../' . $volver);
    exit;
}
