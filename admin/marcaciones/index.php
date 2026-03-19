<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_helpers.php';
require_login();

if (!function_exists('can_marcaciones')) {
  function can_marcaciones(){ return function_exists('is_superadmin') && is_superadmin(); }
}
if (!can_marcaciones()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

$importId = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;

$desde = !empty($_GET['desde']) ? $_GET['desde'] : '';
$hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : '';

// Si viene import_id y no hay fechas, usa MIN/MAX del import
if ($importId > 0 && $desde === '' && $hasta === '') {
  $mm = $pdo->prepare("SELECT MIN(fecha) d1, MAX(fecha) d2 FROM marcaciones_raw WHERE import_id=? AND fecha IS NOT NULL");
  $mm->execute(array($importId));
  $r = $mm->fetch(PDO::FETCH_ASSOC);
  if ($r && $r['d1'] && $r['d2']) {
    $desde = $r['d1'];
    $hasta = $r['d2'];
  }
}

// fallback si aún está vacío
if ($desde === '') $desde = date('Y-m-01');
if ($hasta === '') $hasta = date('Y-m-t');

$estado = !empty($_GET['estado']) ? $_GET['estado'] : '';
$q = !empty($_GET['q']) ? trim($_GET['q']) : '';

$params = array($desde, $hasta);

$whereEstado = '';
if (in_array($estado, array('OK','OBS','ERROR'), true)) {
  $whereEstado = " AND mv.estado = ? ";
  $params[] = $estado;
}

$whereQ = '';
if ($q !== '') {
  // Credenciales: rut y nombre (no hay nombres/apellidos separados)
  $whereQ = " AND (c.rut LIKE ? OR c.nombre LIKE ?) ";
  $params[] = '%'.$q.'%';
  $params[] = '%'.$q.'%';
}

// Import: la validación NO tiene import_id por defecto.
// Si quieres que el dashboard muestre solo el import seleccionado, debes agregar import_id a marcaciones_validacion.
// Mientras tanto, mostramos por rango (global).
$whereImport = '';
if ($importId > 0) {
  // Recomendado: agregar import_id a marcaciones_validacion y descomentar esto:
  // $whereImport = " AND mv.import_id = ? ";
  // $params[] = $importId;

  // Alternativa rápida (sin alterar tabla): filtrar solo credenciales presentes en ese import
  $whereImport = " AND mv.credencial_id IN (
      SELECT DISTINCT r.credencial_id
      FROM marcaciones_raw r
      WHERE r.import_id = ? AND r.credencial_id IS NOT NULL
    ) ";
  $params[] = $importId;
}

$sql = "
  SELECT
    c.id AS credencial_id,
    c.rut,
    c.nombre,
    c.unidad,
    c.cargo,
    SUM(mv.estado='OK')    AS ok_cnt,
    SUM(mv.estado='OBS')   AS obs_cnt,
    SUM(mv.estado='ERROR') AS err_cnt
  FROM marcaciones_validacion mv
  JOIN credenciales c ON c.id = mv.credencial_id
  WHERE mv.fecha BETWEEN ? AND ?
    $whereImport
    $whereEstado
    $whereQ
  GROUP BY c.id
  ORDER BY err_cnt DESC, obs_cnt DESC, c.nombre ASC
  LIMIT 500
";
$st = $pdo->prepare($sql);
$st->execute($params);
$list = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Control de marcaciones</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top}
    th{background:#f6f6f6}
    .muted{color:#666}
    .btn{display:inline-block;padding:8px 10px;border:1px solid #111;border-radius:8px;text-decoration:none}
    .btn:hover{background:#111;color:#fff}
  </style>
</head>
<body>

  <div class="card">
    <h2 style="margin:0 0 6px 0;">📊 Control de marcaciones</h2>
    <div class="muted">
      Rango: <?php echo h($desde); ?> → <?php echo h($hasta); ?>
      <?php if ($importId): ?>
        · Import: #<?php echo (int)$importId; ?>
      <?php endif; ?>
      <a class="btn" href="sin_match.php?import_id=<?php echo (int)$importId; ?>">🧩 Ver sin match</a>
      <a class="btn" href="imports.php">📁 Planillas subidas</a>
    </div>

    <form method="get" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
      <div>
        <div class="muted">Desde</div>
        <input type="date" name="desde" value="<?php echo h($desde); ?>">
      </div>
      <div>
        <div class="muted">Hasta</div>
        <input type="date" name="hasta" value="<?php echo h($hasta); ?>">
      </div>
      <div>
        <div class="muted">Estado</div>
        <select name="estado">
          <option value="">(todos)</option>
          <option value="OK" <?php echo $estado==='OK'?'selected':''; ?>>OK</option>
          <option value="OBS" <?php echo $estado==='OBS'?'selected':''; ?>>OBS</option>
          <option value="ERROR" <?php echo $estado==='ERROR'?'selected':''; ?>>ERROR</option>
        </select>
      </div>
      <div>
        <div class="muted">Buscar</div>
        <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="RUT o nombre">
      </div>
      <?php if ($importId): ?>
        <input type="hidden" name="import_id" value="<?php echo (int)$importId; ?>">
      <?php endif; ?>
      <button class="btn" type="submit">Filtrar</button>

      <a class="btn" href="importar.php">⏱️ Importar</a>
      <a class="btn" href="../../dashboard.php">↩️ Admin</a>
    </form>

    <table>
      <tr>
        <th>Funcionario</th>
        <th style="width:120px;">OK</th>
        <th style="width:120px;">OBS</th>
        <th style="width:120px;">ERROR</th>
        <th style="width:160px;">Acción</th>
      </tr>
      <?php foreach($list as $r): ?>
        <tr>
          <td>
            <strong><?php echo h($r['nombre']); ?></strong><br>
            <span class="muted">RUT: <?php echo h($r['rut']); ?></span><br>
            <span class="muted"><?php echo h($r['unidad']); ?> · <?php echo h($r['cargo']); ?></span>
          </td>
          <td><?php echo (int)$r['ok_cnt']; ?></td>
          <td><?php echo (int)$r['obs_cnt']; ?></td>
          <td><?php echo (int)$r['err_cnt']; ?></td>
          <td>
            <a class="btn"
               href="funcionario.php?credencial_id=<?php echo (int)$r['credencial_id']; ?>&import_id=<?php echo (int)$importId; ?>&desde=<?php echo h($desde); ?>&hasta=<?php echo h($hasta); ?>">
              Ver detalle
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
        <tr><td colspan="5" class="muted">Sin resultados en el rango.</td></tr>
      <?php endif; ?>
    </table>
  </div>

</body>
</html>