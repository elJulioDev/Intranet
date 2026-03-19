<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_helpers.php';
require_login();

if (!function_exists('is_superadmin') || !is_superadmin()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

// listado imports con stats + rango de fechas real del raw
$st = $pdo->prepare("
  SELECT
    i.id,
    i.filename,
    i.uploaded_by_rut,
    i.uploaded_at,
    i.rows_total,
    i.rows_ok,
    i.rows_error,
    (SELECT MIN(fecha) FROM marcaciones_raw r WHERE r.import_id=i.id AND r.fecha IS NOT NULL) AS fecha_min,
    (SELECT MAX(fecha) FROM marcaciones_raw r WHERE r.import_id=i.id AND r.fecha IS NOT NULL) AS fecha_max,
    (SELECT SUM(r.funcionario_id IS NULL) FROM marcaciones_raw r WHERE r.import_id=i.id) AS sin_match
  FROM imports i
  ORDER BY i.id DESC
  LIMIT 50
");
$st->execute();
$list = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Planillas subidas</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top}
    th{background:#f6f6f6}
    .btn{display:inline-block;padding:7px 10px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;margin-right:6px}
    .btn:hover{background:#111;color:#fff}
    .muted{color:#666}
  </style>
</head>
<body>
  <div class="card">
    <h2 style="margin:0 0 6px 0;">📁 Planillas subidas (Imports)</h2>
    <div class="muted">Cada import es un “lote”. Usa “Ver” para navegar sin duplicar datos.</div>

    <div style="margin-top:10px;">
      <a class="btn" href="importar.php">⏱️ Importar nueva</a>
      <a class="btn" href="index.php">📊 Volver a control</a>
    </div>

    <table>
      <tr>
        <th style="width:70px;">ID</th>
        <th>Archivo</th>
        <th style="width:170px;">Subida</th>
        <th style="width:160px;">Rango fechas</th>
        <th style="width:190px;">Totales</th>
        <th style="width:220px;">Acciones</th>
      </tr>
      <?php foreach($list as $r): ?>
        <tr>
          <td><strong><?php echo (int)$r['id']; ?></strong></td>
          <td>
            <?php echo h($r['filename']); ?><br>
            <span class="muted">por RUT: <?php echo h($r['uploaded_by_rut']); ?></span>
          </td>
          <td><?php echo h($r['uploaded_at']); ?></td>
          <td class="muted">
            <?php echo h($r['fecha_min']); ?> → <?php echo h($r['fecha_max']); ?>
          </td>
          <td class="muted">
            Total: <?php echo (int)$r['rows_total']; ?><br>
            Parse OK: <?php echo (int)$r['rows_ok']; ?><br>
            Parse Err: <?php echo (int)$r['rows_error']; ?><br>
            Sin match: <?php echo (int)$r['sin_match']; ?>
          </td>
          <td>
            <a class="btn" href="index.php?import_id=<?php echo (int)$r['id']; ?>">Ver control</a>
            <a class="btn" href="sin_match.php?import_id=<?php echo (int)$r['id']; ?>">Ver sin match</a>
            <a class="btn" href="validar.php?import_id=<?php echo (int)$r['id']; ?>">Revalidar</a>
            <a class="btn" href="eliminar_import.php?import_id=<?php echo (int)$r['id']; ?>" onclick="return confirm('¿Eliminar import #<?php echo (int)$r['id']; ?> y todas sus marcaciones?');">Eliminar</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
        <tr><td colspan="6" class="muted">Aún no hay planillas subidas.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</body>
</html>
