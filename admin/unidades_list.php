<?php
// intranet/admin/unidades_list.php (PHP 5.6)
require __DIR__ . '/_guard.php';

$direccionId = isset($_GET['direccion_id']) ? (int)$_GET['direccion_id'] : 0;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$msg = '';
$err = '';

// Toggle activo (opcional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
  csrf_check();
  $id = (int)$_POST['toggle_id'];

  try {
    // Si la tabla no tiene 'activo', esto fallará; en ese caso, comenta este bloque.
    $st = $pdo->prepare("UPDATE unidades SET activo = IF(activo=1,0,1) WHERE id=?");
    $st->execute(array($id));
    $msg = 'Estado actualizado.';
  } catch (Exception $e) {
    $msg = 'No se pudo actualizar estado: '.$e->getMessage();
  }
}

// Direcciones para filtro
$direcciones = array();
try {
  $direcciones = $pdo->query("SELECT id, codigo, nombre FROM direcciones WHERE activo=1 ORDER BY codigo, nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $direcciones = array();
}

// Query principal
$params = array();
$sql = "
  SELECT
    u.id,
    u.nombre AS unidad_nombre,
    u.activo AS unidad_activo,
    d.id AS direccion_id,
    d.codigo AS dir_codigo,
    d.nombre AS dir_nombre
  FROM unidades u
  JOIN direcciones d ON d.id = u.direccion_id
  WHERE 1=1
";

if ($direccionId > 0) {
  $sql .= " AND d.id = ? ";
  $params[] = $direccionId;
}

if ($q !== '') {
  $sql .= " AND (u.nombre LIKE ? OR d.nombre LIKE ? OR d.codigo LIKE ?) ";
  $like = '%'.$q.'%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$sql .= " ORDER BY d.codigo, d.nombre, u.nombre ";

$rows = array();
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $rows = array();
  $err = $e->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Unidades</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;margin-right:8px;margin-top:6px;}
    .btn:hover{background:#111;color:#fff;}
    .btn-secondary{border-color:#ddd;color:#111;}
    .btn-secondary:hover{background:#f3f3f3;color:#111;}
    table{width:100%;border-collapse:collapse;margin-top:12px;}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top;}
    th{background:#f6f6f6;}
    input,select{padding:8px;border:1px solid #ddd;border-radius:8px;}
    input{max-width:360px;width:100%;}
    .muted{color:#666;}
    .pill{display:inline-block;padding:2px 8px;border:1px solid #ddd;border-radius:999px;font-size:12px;}
    form.inline{display:inline;}
  </style>
</head>
<body>

  <h2>Unidades</h2>

  <p>
    <a class="btn btn-secondary" href="index.php">← Admin</a>
    <a class="btn" href="unidades_form.php">+ Nueva unidad</a>
  </p>

  <form method="get" style="margin-top:10px;">
    <label class="muted">Dirección:</label><br>
    <select name="direccion_id">
      <option value="0">-- Todas --</option>
      <?php foreach($direcciones as $d): ?>
        <option value="<?php echo (int)$d['id']; ?>" <?php echo ($direccionId===(int)$d['id']?'selected':''); ?>>
          <?php echo h($d['codigo'].' - '.$d['nombre']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="muted" style="margin-left:10px;">Buscar:</label>
    <input name="q" value="<?php echo h($q); ?>" placeholder="Unidad / Dirección / Código">

    <button class="btn btn-secondary" type="submit" style="margin-left:10px;">Filtrar</button>
    <?php if ($q !== '' || $direccionId > 0): ?>
      <a class="btn btn-secondary" href="unidades_list.php">Limpiar</a>
    <?php endif; ?>
  </form>

  <?php if ($msg): ?>
    <p style="margin-top:12px;color:#0a7a2f;"><strong><?php echo h($msg); ?></strong></p>
  <?php endif; ?>

  <?php if ($err): ?>
    <p style="margin-top:12px;color:#b00020;"><strong>Error:</strong> <?php echo h($err); ?></p>
  <?php endif; ?>

  <p class="muted" style="margin-top:12px;">
    Total: <strong><?php echo (int)count($rows); ?></strong>
  </p>

  <table>
    <tr>
      <th style="width:70px;">ID</th>
      <th>Unidad</th>
      <th style="width:320px;">Dirección</th>
      <th style="width:90px;">Activo</th>
      <th style="width:210px;">Acciones</th>
    </tr>

    <?php foreach($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><strong><?php echo h($r['unidad_nombre']); ?></strong></td>
        <td><?php echo h($r['dir_codigo'].' - '.$r['dir_nombre']); ?></td>
        <td>
          <?php if ((int)$r['unidad_activo'] === 1): ?>
            <span class="pill">Sí</span>
          <?php else: ?>
            <span class="pill">No</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="unidades_form.php?id=<?php echo (int)$r['id']; ?>">Editar</a>

          <!-- Toggle activo (opcional) -->
          <form class="inline" method="post" style="margin-left:10px;">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
            <input type="hidden" name="toggle_id" value="<?php echo (int)$r['id']; ?>">
            <button class="btn btn-secondary" type="submit">
              <?php echo ((int)$r['unidad_activo']===1) ? 'Desactivar' : 'Activar'; ?>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>

    <?php if (empty($rows)): ?>
      <tr><td colspan="5" class="muted">Sin resultados.</td></tr>
    <?php endif; ?>
  </table>

</body>
</html>
