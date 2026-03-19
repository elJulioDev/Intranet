<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require_login();

$fid = current_user_id();

$desde = !empty($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$uf = $pdo->prepare("SELECT rut, CONCAT(nombres,' ',apellidos) AS nombre FROM funcionarios WHERE id=? LIMIT 1");
$uf->execute(array($fid));
$func = $uf->fetch(PDO::FETCH_ASSOC);

$days = $pdo->prepare("
  SELECT fecha, estado, detalle, total_marcaciones, primera, ultima
  FROM marcaciones_validacion
  WHERE funcionario_id=? AND fecha BETWEEN ? AND ?
  ORDER BY fecha ASC
");
$days->execute(array($fid, $desde, $hasta));
$listaDias = $days->fetchAll(PDO::FETCH_ASSOC);

$getHoras = $pdo->prepare("
  SELECT hora
  FROM marcaciones_raw
  WHERE funcionario_id=? AND fecha=? AND parse_ok=1
  ORDER BY hora ASC
");
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mis marcaciones</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff}
    .muted{color:#666}
    .btn{display:inline-block;padding:8px 10px;border:1px solid #111;border-radius:8px;text-decoration:none}
    .btn:hover{background:#111;color:#fff}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top}
    th{background:#f6f6f6}
    code{background:#f6f6f6;padding:2px 6px;border-radius:6px}
  </style>
</head>
<body>
  <div class="card">
    <h2 style="margin:0 0 6px 0;">🕘 Mis marcaciones</h2>
    <div class="muted"><?php echo h($func ? $func['nombre'] : ''); ?> · <?php echo h($desde); ?> → <?php echo h($hasta); ?></div>

    <form method="get" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
      <div>
        <div class="muted">Desde</div>
        <input type="date" name="desde" value="<?php echo h($desde); ?>">
      </div>
      <div>
        <div class="muted">Hasta</div>
        <input type="date" name="hasta" value="<?php echo h($hasta); ?>">
      </div>
      <button class="btn" type="submit">Filtrar</button>
      <a class="btn" href="../dashboard.php">← Dashboard</a>
    </form>

    <table>
      <tr>
        <th style="width:120px;">Fecha</th>
        <th style="width:90px;">Estado</th>
        <th>Marcaciones</th>
        <th style="width:240px;">Detalle</th>
      </tr>
      <?php foreach($listaDias as $d): ?>
        <?php
          $getHoras->execute(array($fid, $d['fecha']));
          $horas = $getHoras->fetchAll(PDO::FETCH_COLUMN);
        ?>
        <tr>
          <td><?php echo h($d['fecha']); ?></td>
          <td><strong><?php echo h($d['estado']); ?></strong></td>
          <td><code><?php echo h(implode(', ', $horas)); ?></code></td>
          <td class="muted"><?php echo h($d['detalle']); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($listaDias)): ?>
        <tr><td colspan="4" class="muted">No hay datos en el rango.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</body>
</html>
