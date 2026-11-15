<?php
/* =====================================================================
   SISGEDO — Cifrar las contraseñas antiguas (una sola vez)
   ---------------------------------------------------------------------
   Las cuentas creadas en el sistema anterior guardan la contraseña tal
   cual (texto plano). El sistema las cifra recién cuando cada usuario
   vuelve a iniciar sesión; esta herramienta cifra TODAS de una vez
   (bcrypt, igual que el inicio de sesión). Los usuarios siguen entrando
   con su MISMA contraseña.

   USO
   - Por navegador (solo el administrador, usuario ID 1, con sesión):
       https://<dominio>/sisgedov2/herramientas/cifrar_claves.php
     Muestra cuántas hay y un botón "Cifrar ahora".
   - Por consola:
       php herramientas/cifrar_claves.php [dominio]      (solo muestra)
       php herramientas/cifrar_claves.php [dominio] --cifrar
   Se puede ejecutar varias veces: solo toca las que aún no están cifradas.
   ===================================================================== */

$esCli = (PHP_SAPI === 'cli');
if ($esCli) {
    $_SERVER['HTTP_HOST'] = isset($argv[1]) && $argv[1] !== '--cifrar' ? $argv[1] : 'localhost';
    $cifrar = in_array('--cifrar', $argv, true);
} else {
    session_start();
    if (empty($_SESSION['usuario']) || intval($_SESSION['usuario']) !== 1) {
        http_response_code(403);
        exit('Acceso restringido al administrador.');
    }
}
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../clases/seguridad.php';

if (!$esCli) {
    $cifrar = ($_SERVER['REQUEST_METHOD'] === 'POST');
    if ($cifrar) {
        csrf_require();
    }
}

$pdo = (new Conexion())->conectar();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Texto plano = no empieza con un prefijo de hash bcrypt/argon de PHP
$sqlPlanas = "SELECT idpersonal, clave FROM personal
               WHERE clave IS NOT NULL AND clave <> ''
                 AND clave NOT LIKE '$2y$%' AND clave NOT LIKE '$2a$%' AND clave NOT LIKE '$2b$%' AND clave NOT LIKE '\$argon2%'";
$planas = $pdo->query($sqlPlanas)->fetchAll(PDO::FETCH_NUM);
$hechas = 0;
$error = '';

if ($cifrar && count($planas) > 0) {
    @set_time_limit(300);
    try {
        $pdo->beginTransaction();
        // "AND clave = ?" evita pisar una clave que el usuario cambió en este instante
        $up = $pdo->prepare("UPDATE personal SET clave = ? WHERE idpersonal = ? AND clave = ?");
        foreach ($planas as $f) {
            $up->execute(array(password_hash((string)$f[1], PASSWORD_DEFAULT), (int)$f[0], (string)$f[1]));
            $hechas += $up->rowCount();
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'No se pudo completar (no se cambió nada): ' . $e->getMessage();
        $hechas = 0;
    }
    $planas = $pdo->query($sqlPlanas)->fetchAll(PDO::FETCH_NUM);
}
$total = (int)$pdo->query("SELECT COUNT(*) FROM personal WHERE clave IS NOT NULL AND clave <> ''")->fetchColumn();

if ($esCli) {
    if ($error !== '') { fwrite(STDERR, $error . "\n"); exit(1); }
    if ($cifrar) echo "Cifradas ahora: $hechas\n";
    echo "Cuentas con contraseña: $total | aún sin cifrar: " . count($planas) . "\n";
    if (!$cifrar && count($planas) > 0) echo "Para cifrarlas: php herramientas/cifrar_claves.php " . $_SERVER['HTTP_HOST'] . " --cifrar\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cifrar contraseñas — SISGEDO</title>
  <style>
    body { font-family: "Segoe UI", Arial, sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; padding: 32px 16px; }
    .c { max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 26px; }
    h1 { font-size: 20px; margin: 0 0 10px; color: #123a7a; }
    p { font-size: 14px; line-height: 1.55; color: #475569; }
    .n { font-size: 34px; font-weight: 700; color: #b45309; }
    .ok { background: #ecfdf3; border: 1px solid #bbf7d0; color: #14532d; padding: 12px 14px; border-radius: 10px; font-size: 14px; }
    .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 14px; border-radius: 10px; font-size: 14px; }
    button { background: #2563eb; color: #fff; border: 0; border-radius: 10px; padding: 11px 20px; font-size: 14px; font-weight: 600; cursor: pointer; }
    a { color: #2563eb; }
  </style>
</head>
<body>
  <div class="c">
    <h1>Cifrar las contraseñas antiguas</h1>
    <?php if ($error !== '') { ?><p class="err"><?= h($error) ?></p><?php } ?>
    <?php if ($cifrar && $error === '') { ?><p class="ok">Listo: se cifraron <b><?= (int)$hechas ?></b> contraseñas. Los usuarios siguen entrando con su misma clave.</p><?php } ?>
    <p>Cuentas con contraseña: <b><?= (int)$total ?></b></p>
    <p>Aún guardadas <b>sin cifrar</b>:</p>
    <div class="n"><?= count($planas) ?></div>
    <?php if (count($planas) > 0) { ?>
      <p>Se cifrarán con el mismo método que usa el inicio de sesión (bcrypt). <b>Nadie pierde el acceso</b>: cada usuario sigue usando su contraseña de siempre. Es seguro ejecutarlo más de una vez.</p>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>" />
        <button type="submit">Cifrar ahora</button>
      </form>
    <?php } else { ?>
      <p class="ok">Todas las contraseñas están cifradas. No hay nada que hacer.</p>
    <?php } ?>
    <p style="margin-top:20px"><a href="../index.php">Volver al sistema</a></p>
  </div>
</body>
</html>
