<?php
// intranet/admin/unidades_form.php (PHP 5.6)
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
  'direccion_id' => 0,
  'nombre' => '',
  'activo' => 1
);

// Direcciones para el select
$direcciones = $pdo->query("SELECT id, codigo, nombre FROM direcciones WHERE activo=1 ORDER BY codigo, nombre")->fetchAll();

if ($editing) {
  $st = $pdo->prepare("SELECT id, direccion_id, nombre, activo FROM unidades WHERE id=? LIMIT 1");
  $st->execute(array($id));
  $db = $st->fetch();
  if (!$db) { http_response_code(404); exit('Unidad no encontrada'); }
  $row = array_merge($row, $db);
} else {
  // Default: primera dirección disponible
  if (!empty($direcciones)) $row['direccion_id'] = (int)$direcciones[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $direccionId = isset($_POST['direccion_id']) ? (int)$_POST['direccion_id'] : 0;
  $nombre      = norm_txt(isset($_POST['nombre']) ? $_POST['nombre'] : '');
  $activo      = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;

  // Validaciones
  if ($direccionId <= 0) {
    $error = 'Debes seleccionar una dirección.';
  } elseif ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 120) {
    $error = 'Nombre obligatorio (máx 120 caracteres).';
  } else {
    // Asegura que la dirección exista y esté activa
    $st = $pdo->prepare("SELECT id FROM direcciones WHERE id=? AND activo=1 LIMIT 1");
    $st->execute(array($direccionId));
    if (!$st->fetch()) $error = 'Dirección inválida.';
  }

  // Unicidad sugerida: unidad única por dirección
  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("SELECT id FROM unidades WHERE direccion_id=? AND nombre=? AND id<>? LIMIT 1");
      $st->execute(array($direccionId, $nombre, $id));
      if ($st->fetch()) $error = 'Ya existe una unidad con ese nombre en la misma dirección.';
    } else {
      $st = $pdo->prepare("SELECT id FROM unidades WHERE direccion_id=? AND nombre=? LIMIT 1");
      $st->execute(array($direccionId, $nombre));
      if ($st->fetch()) $error = 'Ya existe una unidad con ese nombre en la misma dirección.';
    }
  }

  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("UPDATE unidades SET direccion_id=?, nombre=?, activo=? WHERE id=?");
      $st->execute(array($direccionId, $nombre, $activo, $id));
      $ok = 'Unidad actualizada.';
    } else {
      $st = $pdo->prepare("INSERT INTO unidades (direccion_id, nombre, activo) VALUES (?,?,?)");
      $st->execute(array($direccionId, $nombre, $activo));
      $id = (int)$pdo->lastInsertId();
      $editing = true;
      $ok = 'Unidad creada (ID: '.$id.').';
    }
  }

  $row['direccion_id'] = $direccionId;
  $row['nombre'] = $nombre;
  $row['activo'] = $activo;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?php echo $editing ? 'Editar Unidad' : 'Nueva Unidad'; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;margin-right:8px;margin-top:6px;}
    .btn:hover{background:#111;color:#fff;}
    .card{border:1px solid #eee;border-radius:12px;padding:14px;max-width:900px;}
    .row{margin:10px 0;}
    label{display:block;font-weight:bold;margin-bottom:4px;}
    input,select{padding:8px;border:1px solid #ddd;border-radius:8px;max-width:520px;width:100%;}
    .muted{color:#666;}
  </style>
</head>
<body>

  <h2><?php echo $editing ? 'Editar Unidad' : 'Crear Unidad'; ?></h2>
  <p>
    <a class="btn" href="index.php">← Admin</a>
    <a class="btn" href="unidades_list.php">Listado</a>
    <a class="btn" href="unidades_form.php">+ Nueva</a>
    <?php if ($editing): ?><span class="muted">ID: <?php echo (int)$id; ?></span><?php endif; ?>
  </p>

  <?php if ($error): ?><p style="color:#b00020;"><strong><?php echo h($error); ?></strong></p><?php endif; ?>
  <?php if ($ok): ?><p style="color:#0a7a2f;"><strong><?php echo h($ok); ?></strong></p><?php endif; ?>

  <div class="card">
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">

      <div class="row">
        <label>Dirección *</label>
        <select name="direccion_id" required>
          <option value="0">-- Seleccione --</option>
          <?php foreach($direcciones as $d): ?>
            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)$row['direccion_id']===(int)$d['id']?'selected':''); ?>>
              <?php echo h($d['codigo'].' - '.$d['nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row">
        <label>Nombre de la unidad *</label>
        <input name="nombre" value="<?php echo h($row['nombre']); ?>" placeholder="Ej: Informática / RRHH / Tesorería" required>
      </div>

      <div class="row">
        <label>Activo</label>
        <select name="activo">
          <option value="1" <?php echo ((int)$row['activo']===1?'selected':''); ?>>Sí</option>
          <option value="0" <?php echo ((int)$row['activo']===0?'selected':''); ?>>No</option>
        </select>
      </div>

      <button class="btn" type="submit"><?php echo $editing ? 'Guardar cambios' : 'Crear unidad'; ?></button>
    </form>
  </div>

</body>
</html>
