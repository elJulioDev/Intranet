<?php
// intranet/admin/cargos_form.php (PHP 5.6)
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
  'unidad_id' => 0,
  'nombre' => '',
  'activo' => 1
);

// Cargar direcciones activas
$direcciones = $pdo->query("SELECT id, codigo, nombre FROM direcciones WHERE activo=1 ORDER BY codigo, nombre")->fetchAll();

// Si edita, carga el cargo y su unidad/dirección
if ($editing) {
  $st = $pdo->prepare("
    SELECT c.id, c.nombre, c.unidad_id, c.activo, u.direccion_id
    FROM cargos c
    JOIN unidades u ON u.id=c.unidad_id
    WHERE c.id=?
    LIMIT 1
  ");
  $st->execute(array($id));
  $db = $st->fetch();
  if (!$db) { http_response_code(404); exit('Cargo no encontrado'); }

  $row['nombre'] = $db['nombre'];
  $row['unidad_id'] = (int)$db['unidad_id'];
  $row['direccion_id'] = (int)$db['direccion_id'];
  $row['activo'] = isset($db['activo']) ? (int)$db['activo'] : 1;
}

// Dirección seleccionada (GET o POST)
$direccionSel = isset($_GET['direccion_id']) ? (int)$_GET['direccion_id'] : (int)$row['direccion_id'];
if ($direccionSel <= 0 && !empty($direcciones)) $direccionSel = (int)$direcciones[0]['id'];

// Unidades según dirección seleccionada
$unidades = array();
if ($direccionSel > 0) {
  $st = $pdo->prepare("SELECT id, nombre FROM unidades WHERE activo=1 AND direccion_id=? ORDER BY nombre");
  $st->execute(array($direccionSel));
  $unidades = $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $direccionSel = isset($_POST['direccion_id']) ? (int)$_POST['direccion_id'] : 0;
  $unidadId = isset($_POST['unidad_id']) ? (int)$_POST['unidad_id'] : 0;
  $nombre = norm_txt(isset($_POST['nombre']) ? $_POST['nombre'] : '');
  $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;

  // Recargar unidades para la dirección elegida (para re-render tras error)
  $unidades = array();
  if ($direccionSel > 0) {
    $st = $pdo->prepare("SELECT id, nombre FROM unidades WHERE activo=1 AND direccion_id=? ORDER BY nombre");
    $st->execute(array($direccionSel));
    $unidades = $st->fetchAll();
  }

  // Validaciones
  if ($direccionSel <= 0) {
    $error = 'Debes seleccionar una dirección.';
  } elseif ($unidadId <= 0) {
    $error = 'Debes seleccionar una unidad.';
  } elseif ($nombre === '') {
    $error = 'El nombre del cargo es obligatorio.';
  } elseif (mb_strlen($nombre, 'UTF-8') > 120) {
    $error = 'El nombre del cargo es demasiado largo (máx 120).';
  } else {
    // Validar que unidad pertenezca a esa dirección (consistencia)
    $st = $pdo->prepare("SELECT id FROM unidades WHERE id=? AND direccion_id=? AND activo=1 LIMIT 1");
    $st->execute(array($unidadId, $direccionSel));
    if (!$st->fetch()) {
      $error = 'Unidad inválida para la dirección seleccionada.';
    }
  }

  // Unicidad sugerida: nombre de cargo único por unidad (opcional)
  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("SELECT id FROM cargos WHERE unidad_id=? AND nombre=? AND id<>? LIMIT 1");
      $st->execute(array($unidadId, $nombre, $id));
      if ($st->fetch()) $error = 'Ya existe un cargo con ese nombre en la misma unidad.';
    } else {
      $st = $pdo->prepare("SELECT id FROM cargos WHERE unidad_id=? AND nombre=? LIMIT 1");
      $st->execute(array($unidadId, $nombre));
      if ($st->fetch()) $error = 'Ya existe un cargo con ese nombre en la misma unidad.';
    }
  }

  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("UPDATE cargos SET unidad_id=?, nombre=?, activo=? WHERE id=?");
      $st->execute(array($unidadId, $nombre, $activo, $id));
      $ok = 'Cargo actualizado.';
    } else {
      $st = $pdo->prepare("INSERT INTO cargos (unidad_id, nombre, activo) VALUES (?,?,?)");
      $st->execute(array($unidadId, $nombre, $activo));
      $id = (int)$pdo->lastInsertId();
      $editing = true;
      $ok = 'Cargo creado (ID: '.$id.').';
    }
  }

  // Mantener valores para re-render
  $row['direccion_id'] = $direccionSel;
  $row['unidad_id'] = $unidadId;
  $row['nombre'] = $nombre;
  $row['activo'] = $activo;
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?php echo $editing ? 'Editar Cargo' : 'Nuevo Cargo'; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;}
    .card{border:1px solid #eee;border-radius:12px;padding:14px;max-width:900px;}
    .row{margin:10px 0;}
    label{display:block;font-weight:bold;margin-bottom:4px;}
    input,select{padding:8px;border:1px solid #ddd;border-radius:8px;max-width:520px;width:100%;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:900px;}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;}
    .btn:hover{background:#111;color:#fff;}
    .muted{color:#666;}
  </style>
</head>
<body>

  <h2><?php echo $editing ? 'Editar cargo' : 'Crear cargo'; ?></h2>
  <p>
    <a class="btn" href="index.php">← Admin</a>
    <?php if ($editing): ?>
      <span class="muted">ID: <?php echo (int)$id; ?></span>
    <?php endif; ?>
  </p>

  <?php if ($error): ?><p style="color:#b00020;"><strong><?php echo h($error); ?></strong></p><?php endif; ?>
  <?php if ($ok): ?><p style="color:#0a7a2f;"><strong><?php echo h($ok); ?></strong></p><?php endif; ?>

  <div class="card">
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">

      <div class="grid">
        <div class="row">
          <label>Dirección</label>
          <select name="direccion_id" onchange="this.form.submit()">
            <?php foreach ($direcciones as $d): ?>
              <option value="<?php echo (int)$d['id']; ?>"
                <?php echo ($direccionSel === (int)$d['id']) ? 'selected' : ''; ?>>
                <?php echo h($d['codigo'].' - '.$d['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="muted">Al cambiar dirección, el formulario recarga para listar sus unidades.</div>
        </div>

        <div class="row">
          <label>Unidad</label>
          <select name="unidad_id" required>
            <option value="0">-- Seleccione --</option>
            <?php foreach ($unidades as $u): ?>
              <option value="<?php echo (int)$u['id']; ?>"
                <?php echo ((int)$row['unidad_id'] === (int)$u['id']) ? 'selected' : ''; ?>>
                <?php echo h($u['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row" style="grid-column:1 / -1;">
          <label>Nombre del cargo</label>
          <input name="nombre" value="<?php echo h($row['nombre']); ?>" placeholder="Ej: Encargado de Informática" required>
        </div>

        <div class="row">
          <label>Activo</label>
          <select name="activo">
            <option value="1" <?php echo ((int)$row['activo']===1?'selected':''); ?>>Sí</option>
            <option value="0" <?php echo ((int)$row['activo']===0?'selected':''); ?>>No</option>
          </select>
        </div>
      </div>

      <br>
      <button class="btn" type="submit"><?php echo $editing ? 'Guardar cambios' : 'Crear cargo'; ?></button>
    </form>
  </div>

</body>
</html>
