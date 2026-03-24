<?php
// intranet/admin/direcciones_form.php (PHP 5.6)
require __DIR__ . '/_guard.php';

function norm_txt($s) {
  $s = trim((string)$s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = ($id > 0);

$error = '';
$ok = '';

$row = array(
  'codigo' => '',
  'nombre' => '',
  'activo' => 1
);

if ($editing) {
  $st = $pdo->prepare("SELECT id, codigo, nombre, activo FROM direcciones WHERE id=? LIMIT 1");
  $st->execute(array($id));
  $db = $st->fetch();
  if (!$db) { http_response_code(404); exit('Dirección no encontrada'); }
  $row = array_merge($row, $db);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $codigo = strtoupper(norm_txt(isset($_POST['codigo']) ? $_POST['codigo'] : ''));
  $nombre = norm_txt(isset($_POST['nombre']) ? $_POST['nombre'] : '');
  $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;

  // Validaciones
  if ($codigo === '' || !preg_match('/^[A-Z0-9_]{2,15}$/', $codigo)) {
    $error = 'Código inválido. Usa 2-15 caracteres: A-Z, 0-9, guión bajo (Ej: DAF, SECPLAC, DIDECO).';
  } elseif ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 120) {
    $error = 'Nombre obligatorio (máx 120 caracteres).';
  }

  // Unicidad de código
  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("SELECT id FROM direcciones WHERE codigo=? AND id<>? LIMIT 1");
      $st->execute(array($codigo, $id));
      if ($st->fetch()) $error = 'Ya existe una dirección con ese código.';
    } else {
      $st = $pdo->prepare("SELECT id FROM direcciones WHERE codigo=? LIMIT 1");
      $st->execute(array($codigo));
      if ($st->fetch()) $error = 'Ya existe una dirección con ese código.';
    }
  }

  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("UPDATE direcciones SET codigo=?, nombre=?, activo=? WHERE id=?");
      $st->execute(array($codigo, $nombre, $activo, $id));
      $ok = 'Dirección actualizada.';
    } else {
      $st = $pdo->prepare("INSERT INTO direcciones (codigo, nombre, activo) VALUES (?,?,?)");
      $st->execute(array($codigo, $nombre, $activo));
      $id = (int)$pdo->lastInsertId();
      $editing = true;
      $ok = 'Dirección creada (ID: '.$id.').';
    }
  }

  $row['codigo'] = $codigo;
  $row['nombre'] = $nombre;
  $row['activo'] = $activo;
}
// ... [Toda la lógica de PHP de arriba se queda igual] ...
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?php echo $editing ? 'Editar Dirección' : 'Nueva Dirección'; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    /* 1. IMPORTANTE: Cambia el margin del body a 0 */
    body{font-family:Arial,sans-serif;margin:0;color:#111; background-color: #f8f9fa;}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;margin-right:8px;margin-top:6px;}
    .btn:hover{background:#111;color:#fff;}
    .card{border:1px solid #eee;border-radius:12px;padding:14px;max-width:900px; background: #fff;}
    .row{margin:10px 0;}
    label{display:block;font-weight:bold;margin-bottom:4px;}
    input,select{padding:8px;border:1px solid #ddd;border-radius:8px;max-width:520px;width:100%;}
    .muted{color:#666;}
  </style>
</head>
<body>

  <?php require __DIR__ . '/../inc/sidebar.php'; ?>

  <main style="padding: 20px; width: 100%; box-sizing: border-box; overflow-y: auto;">
  
    <h2><?php echo $editing ? 'Editar Dirección' : 'Crear Dirección'; ?></h2>
    <p>
      <a class="btn" href="index.php">← Admin</a>
      <a class="btn" href="direcciones_list.php">Listado</a>
      <a class="btn" href="direcciones_form.php">+ Nueva</a>
      <?php if ($editing): ?><span class="muted">ID: <?php echo (int)$id; ?></span><?php endif; ?>
    </p>

    <?php if ($error): ?><p style="color:#b00020;"><strong><?php echo _sb_h($error); ?></strong></p><?php endif; ?>
    <?php if ($ok): ?><p style="color:#0a7a2f;"><strong><?php echo _sb_h($ok); ?></strong></p><?php endif; ?>

    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?php echo _sb_h(csrf_token()); ?>">

        <div class="row">
          <label>Código *</label>
          <input name="codigo" value="<?php echo _sb_h($row['codigo']); ?>" placeholder="DAF" <?php echo $editing ? 'readonly' : ''; ?> required>
          <div class="muted">Código único (Ej: DAF, SECPLAC, DIDECO).</div>
        </div>

        <div class="row">
          <label>Nombre *</label>
          <input name="nombre" value="<?php echo _sb_h($row['nombre']); ?>" placeholder="Dirección de Administración y Finanzas" required>
        </div>

        <div class="row">
          <label>Activo</label>
          <select name="activo">
            <option value="1" <?php echo ((int)$row['activo']===1?'selected':''); ?>>Sí</option>
            <option value="0" <?php echo ((int)$row['activo']===0?'selected':''); ?>>No</option>
          </select>
        </div>

        <button class="btn" type="submit"><?php echo $editing ? 'Guardar cambios' : 'Crear dirección'; ?></button>
      </form>
    </div>

  </main> </div> </body>
</html>