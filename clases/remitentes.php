<?php
require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
class Remitente
{
    public function lista_datos()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "SELECT remitente.idremitente,
                            remitente.apellidos,
                            remitente.nombres,
                            remitente.direccion,
                            remitente.email,
                            remitente.telefono
                        FROM remitente;";
            $result = $conectado->prepare($sql);
            $result->execute();
            $lista_cat = "";
            foreach ($result as $filas) {
                echo '<tr>';
                echo '<td class="center">';
                echo '<label class="pos-rel">';
                echo '<input type="checkbox" class="ace" />';
                echo '<span class="lbl"></span>';
                echo '</label>';
                echo '</td>';
                echo '<td>' . h($filas[0]) . '</td>';
                echo '<td>' . h($filas[1]) . '</td>';
                echo '<td >' . h($filas[2]) . '</td>';
                echo '<td >' . h($filas[3]) . '</td>';
                echo '<td >' . h($filas[4]) . '</td>';
                echo '<td >' . h($filas[5]) . '</td>';
                echo '<td>';
                echo '<div class="hidden-sm hidden-xs action-buttons">';
                //                                echo '<a class="blue" href="#">';
                //                                    echo'<i class="ace-icon fa fa-search-plus bigger-130"></i>';
                //                                echo '</a>';
                echo '<a class="green editar" href="#">';
                echo '<i class="ace-icon fa fa-pencil bigger-130"></i>';
                echo '</a>';
                echo '<a class="red eliminar" href="#">';
                echo '<i class="ace-icon fa fa-trash-o bigger-130"></i>';
                echo '</a>';
                echo '</div>';
                echo '<div class="hidden-md hidden-lg">';
                echo '<div class="inline pos-rel">';
                echo '<button class="btn btn-minier btn-yellow dropdown-toggle" data-toggle="dropdown" data-position="auto">';
                echo '<i class="ace-icon fa fa-caret-down icon-only bigger-120"></i>';
                echo '</button>';
                echo '<ul class="dropdown-menu dropdown-only-icon dropdown-yellow dropdown-menu-right dropdown-caret dropdown-close">';
                //                                        echo '<li>';
                //                                            echo '<a href="#" class="tooltip-info" data-rel="tooltip" title="View">';
                //                                                echo '<span class="blue">';
                //                                                    echo '<i class="ace-icon fa fa-search-plus bigger-120"></i>';
                //                                                echo '</span>';
                //                                            echo '</a>';
                //                                        echo '</li>';
                echo '<li>';
                echo '<a href="#" class="tooltip-success" data-rel="tooltip" title="Editar">';
                echo '<span class="green">';
                echo '<i class="ace-icon fa fa-pencil-square-o bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
                echo '<li>';
                echo '<a href="#" class="tooltip-error" data-rel="tooltip" title="Eliminar">';
                echo '<span class="red">';
                echo '<i class="ace-icon fa fa-trash-o bigger-120"></i>';
                echo '</span>';
                echo '</a>';
                echo '</li>';
                echo '</ul>';
                echo '</div>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            return substr($lista_cat, 0, strlen($lista_cat) - 5);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    // Paginacion en servidor (protocolo DataTables serverSide).
    // Devuelve solo la pagina solicitada, con busqueda y orden aplicados en la BD.
    public function datatable()
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();

            $draw   = isset($_POST['draw'])   ? intval($_POST['draw'])   : 0;
            $start  = isset($_POST['start'])  ? max(0, intval($_POST['start'])) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
            if ($length < 1 || $length > 200) {
                $length = 25;
            }
            $buscar = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

            // Orden: solo columnas permitidas (indice de columna -> columna real)
            $colmap = array(
                1 => 'remitente.idremitente',
                2 => 'remitente.apellidos',
                3 => 'remitente.nombres',
                4 => 'remitente.direccion',
                5 => 'remitente.email',
                6 => 'remitente.telefono'
            );
            $ordCol = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 2;
            $ordDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';
            $orderBy = isset($colmap[$ordCol]) ? $colmap[$ordCol] : 'remitente.apellidos';

            // Total sin filtrar
            $total = intval($conectado->query("SELECT COUNT(*) FROM remitente")->fetchColumn());

            // Filtro de busqueda
            $where = '';
            $params = array();
            if ($buscar !== '') {
                $where = "WHERE (remitente.apellidos LIKE ? OR remitente.nombres LIKE ?
                                  OR remitente.direccion LIKE ? OR remitente.email LIKE ?
                                  OR remitente.telefono LIKE ? OR remitente.idremitente = ?)";
                $like = '%' . $buscar . '%';
                $params = array($like, $like, $like, $like, $like, ctype_digit($buscar) ? intval($buscar) : -1);
            }

            $stc = $conectado->prepare("SELECT COUNT(*) FROM remitente $where");
            $stc->execute($params);
            $filtered = intval($stc->fetchColumn());

            // $length, $start, $orderBy y $ordDir estan validados (enteros / lista blanca)
            $sql = "SELECT idremitente, apellidos, nombres, direccion, email, telefono
                        FROM remitente $where
                        ORDER BY $orderBy $ordDir
                        LIMIT $length OFFSET $start";
            $st = $conectado->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_NUM);

            $chk = '<label class="pos-rel"><input type="checkbox" class="ace" /><span class="lbl"></span></label>';
            $acc = '<div class="hidden-sm hidden-xs action-buttons">'
                . '<a class="green editar" href="#"><i class="ace-icon fa fa-pencil bigger-130"></i></a>'
                . '<a class="red eliminar" href="#"><i class="ace-icon fa fa-trash-o bigger-130"></i></a>'
                . '</div>';

            $data = array();
            foreach ($rows as $f) {
                $data[] = array(
                    $chk,
                    h($f[0]),   // idremitente (Codigo)
                    h($f[1]),   // apellidos
                    h($f[2]),   // nombres
                    h($f[3]),   // direccion
                    h($f[4]),   // email
                    h($f[5]),   // telefono
                    $acc
                );
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data
            ));
        } catch (Exception $exc) {
            error_log('remitentes::datatable -> ' . $exc->getMessage());
            echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array()));
        }
    }
    public function getinforemitente($email)
    {
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "select idremitente
                    from remitente
                    where email=?";
            $result = $conectado->prepare($sql);
            $result->execute([$email]);
            foreach ($result as $filas) {
                return $filas[0];
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function insertar_datos($apellidos, $nombre, $direccion, $email, $telefono)
    {
        $idrem = self::getinforemitente($email);
        if (!$email == "") {
            if (!is_null($idrem)) {
                echo $idrem;
                return;
            }
        }
        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "INSERT INTO remitente(apellidos,nombres,direccion,email,telefono)
                        VALUES(?,?,?,?,?);";
            $result = $conectado->prepare($sql);
            $result->execute([$apellidos, $nombre, $direccion, $email, $telefono]);
            $stmt = $conectado->prepare("SELECT LAST_INSERT_ID()");
            $stmt->execute();
            $lastId = $stmt->fetchColumn();
            echo $lastId;
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }
    public function modificar_datos($apellidos, $nombre, $direccion, $email, $telefono, $codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "UPDATE remitente
                        SET
                        apellidos =?,
                        nombres = ?,
                        direccion = ?,
                        email = ?,
                        telefono =?
                        WHERE idremitente = ?;";
            $result = $conectado->prepare($sql);
            $result->execute([$apellidos, $nombre, $direccion, $email, $telefono, $codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    public function elimnar_datos($codigo)
    {

        try {
            $conexion = new Conexion();
            $conectado = $conexion->conectar();
            $sql = "DELETE FROM remitente WHERE idremitente=?;";
            $result = $conectado->prepare($sql);
            $result->execute([$codigo]);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            die();
        }
    }
}
//cuerpo de las ejecuciones  


$dml_remitente = new Remitente();

// Paginacion en servidor (DataTables): solo lectura, requiere sesion iniciada.
if (isset($_POST['draw'])) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['usuario'])) {
        http_response_code(403);
        echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array()));
        exit;
    }
    $dml_remitente->datatable();
    exit;
}

// Nota: "insertar" (minúscula) es el alta pública de remitente desde la mesa de partes (anónima): no lleva CSRF/autorización.
if (isset($_POST["Insertar"]) || isset($_POST["Modificar"]) || isset($_POST["Codigo"]) || isset($_POST["Emilinar"]) || isset($_POST["Eliminar"]) || isset($_POST["Estado"])) {
    csrf_require();
    exigir_acceso_endpoint();
}
if (isset($_POST['Codigo'])) {

    if ($_POST['Codigo'] == '' & $_POST['Nombres'] != '') {

        $dml_remitente->insertar_datos($_POST['Apellidos'], $_POST['Nombres'], $_POST['Direccion'], $_POST['Email'], $_POST['Telefono']);
        header("location: ../vistas/v_remitentes.php");
    } elseif ($_POST['Codigo'] != '') {
        $dml_remitente->modificar_datos($_POST['Apellidos'], $_POST['Nombres'], $_POST['Direccion'], $_POST['Email'], $_POST['Telefono'], $_POST['Codigo']);

        header("location: ../vistas/v_remitentes.php");
    }
}

if (isset($_POST['Emilinar'])) {
    $dml_remitente->elimnar_datos($_POST['Codigo']);
}
if (isset($_POST['insertar'])) {
    $dml_remitente->insertar_datos($_POST['apellidos'], $_POST['nombres'], $_POST['direccion'], $_POST['email'], $_POST['telefono']);
}
