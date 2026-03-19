<?php
// intranet/admin/cargos_list.php (PHP 5.6)
require __DIR__ . '/_guard.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Filtros
$direccionId = isset($_GET['direccion_id']) ? (int)$_GET['direccion_id'] : 0;
$unidadId    = isset($_GET['unidad_id']) ? (int)$_GET['unidad_id'] : 0;
$q           = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$direcciones = $pdo->query("SELECT id, codigo, nombre FROM direcciones WHERE activo=1 ORDER BY codigo, nombre")->fetchAll();

// Unidades para filtro (según dirección)
$unidades = array();
if ($direccionId > 0) {
  $st = $pdo->prepare("SELECT id, nombre FROM unidades WHERE activo=1 AND direccion_id=? ORDER BY nombre");
  $st->execute(array($direccionId));
  $unidades = $st->fetchAll();
}

// Query principal
$sql = "
  SELECT
    c.id,
    c.nombre AS cargo_nombre,
    c.activo AS cargo_activo,
    u.id AS unidad_id,
    u.nombre AS unidad_nombre,
    d.id AS direccion_id,
    d.codigo AS dir_codigo,
    d.nombre AS dir_nombre
  FROM cargos c
  JOIN unidades u ON u.id = c.unidad_id
  JOIN direcciones d ON d.id = u.direccion_id
  WHERE 1=1
";

$params = array();

if ($direccionId > 0) {
  $sql .= " AND d.id = ? ";
  $params[] = $direccionId;
}
if ($unidadId > 0) {
  $sql .= " AND u.id = ? ";
  $params[] = $unidadId;
}
if ($q !== '') {
  $sql .= " AND (c.nombre LIKE ? OR u.nombre LIKE ? OR d.nombre LIKE ? OR d.codigo LIKE ?) ";
  $like = '%'.$q.'%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$sql .= " ORDER BY d.codigo, u.nombre, c.nombre ";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Cargos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;}
    .btn:hover{background:#111;color:#fff;}
    table{width:100%;border-collapse:collapse;margin-top:12px;}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top;}
    th{background:#f6f6f6;}
    .muted{color:#666;}
    .pill{display:inline-block;padding:2px 8px;border:1px solid #ddd;border-radius:999px;font-size:12px;}
    input,select{padding:8px;border:1px solid #ddd;border-radius:8px;}
  </style>
</head>
<body>

  <h2>Cargos</h2>
  <p>
    <a class="btn" href="index.php">← Admin</a>
    <a class="btn" href="cargos_form.php">+ Crear cargo</a>
  </p>

  <form method="get" style="margin-top:10px;">
    <label class="muted">Dirección:</label>
    <select name="direccion_id" onchange="this.form.submit()">
      <option value="0">-- Todas --</option>
      <?php foreach($direcciones as $d): ?>
        <option value="<?php echo (int)$d['id']; ?>" <?php echo ($direccionId===(int)$d['id']?'selected':''); ?>>
          <?php echo h($d['codigo'].' - '.$d['nombre']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="muted" style="margin-left:10px;">Unidad:</label>
    <select name="unidad_id">
      <option value="0">-- Todas --</option>
      <?php foreach($unidades as $u): ?>
        <option value="<?php echo (int)$u['id']; ?>" <?php echo ($unidadId===(int)$u['id']?'selected':''); ?>>
          <?php echo h($u['nombre']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="muted" style="margin-left:10px;">Buscar:</label>
    <input name="q" value="<?php echo h($q); ?>" placeholder="Cargo / Unidad / Dirección">

    <button class="btn" type="submit" style="margin-left:10px;">Filtrar</button>
  </form>

  <p class="muted" style="margin-top:12px;">
    Total: <strong><?php echo (int)count($rows); ?></strong>
  </p>

  <table>
    <tr>
      <th style="width:70px;">ID</th>
      <th>Cargo</th>
      <th style="width:220px;">Unidad</th>
      <th style="width:260px;">Dirección</th>
      <th style="width:90px;">Activo</th>
      <th style="width:120px;">Acciones</th>
    </tr>

    <?php foreach($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td>
          <strong><?php echo h($r['cargo_nombre']); ?></strong><br>
          <span class="muted">ID Unidad: <?php echo (int)$r['unidad_id']; ?></span>
        </td>
        <td><?php echo h($r['unidad_nombre']); ?></td>
        <td><?php echo h($r['dir_codigo'].' - '.$r['dir_nombre']); ?></td>
        <td>
          <?php if ((int)$r['cargo_activo']===1): ?>
            <span class="pill">Sí</span>
          <?php else: ?>
            <span class="pill">No</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="cargos_form.php?id=<?php echo (int)$r['id']; ?>">Editar</a>
        </td>
      </tr>
    <?php endforeach; ?>

    <?php if (empty($rows)): ?>
      <tr><td colspan="6" class="muted">Sin resultados.</td></tr>
    <?php endif; ?>
  </table>

</body>
</html>
