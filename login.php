<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/csrf.php';

if (!empty($_SESSION['funcionario_id'])) {
  header('Location: dashboard.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $rut = isset($_POST['rut']) ? $_POST['rut'] : '';
  $clave = isset($_POST['clave']) ? $_POST['clave'] : '';
  list($ok, $msg) = attempt_login($pdo, $rut, $clave);
  if ($ok) {
    header('Location: dashboard.php');
    exit;
  }
  $error = $msg;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Intranet - Login</title>
</head>
<body>
  <h2>Ingreso Intranet</h2>

  <?php if ($error): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <div>
      <label>RUT</label><br>
      <input name="rut" required>
    </div>
    <div>
      <label>Clave</label><br>
      <input type="password" name="clave" required>
    </div>
    <button type="submit">Ingresar</button>
  </form>
</body>
</html>
