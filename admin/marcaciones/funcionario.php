<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_helpers.php';
require_login();

if (!function_exists('is_superadmin') || !is_superadmin()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

$fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 0;
if ($fid <= 0) exit('fid inválido');

$importId = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;

// rango
$desde = !empty($_GET['desde']) ? $_GET['desde'] : '';
$hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : '';

// si viene import_id y no hay rango, usa min/max del import para este funcionario
if ($importId > 0 && $desde === '' && $hasta === '') {
  $mm = $pdo->prepare("
    SELECT MIN(fecha) d1, MAX(fecha) d2
    FROM marcaciones_raw
    WHERE import_id=? AND funcionario_id=? AND fecha IS NOT NULL
  ");
  $mm->execute(array($importId, $fid));
  $r = $mm->fetch(PDO::FETCH_ASSOC);
  if ($r && $r['d1'] && $r['d2']) {
    $desde = $r['d1'];
    $hasta = $r['d2'];
  }
}

// fallback general
if ($desde === '') $desde = date('Y-m-01');
if ($hasta === '') $hasta = date('Y-m-t');

// funcionario
$uf = $pdo->prepare("SELECT rut, CONCAT(nombres,' ',apellidos) AS nombre FROM funcionarios WHERE id=? LIMIT 1");
$uf->execute(array($fid));
$func = $uf->fetch(PDO::FETCH_ASSOC);
if (!$func) exit('Funcionario no encontrado');

// días (desde validación si existe; si no, lo armamos desde raw)
$days = $pdo->prepare("
  SELECT fecha, estado, detalle, total_marcaciones, primera, ultima
  FROM marcaciones_validacion
  WHERE funcionario_id=? AND fecha BETWEEN ? AND ?
  ORDER BY fecha ASC
");
$days->execute(array($fid, $desde, $hasta));
$listaDias = $days->fetchAll(PDO::FETCH_ASSOC);

// Si no hay validación (por ejemplo no se corrió validar), armar desde raw
if (empty($listaDias)) {
  $tmp = $pdo->prepare("
    SELECT fecha,
           COUNT(*) total_marcaciones,
           MIN(hora) primera,
           MAX(hora) ultima
    FROM marcaciones_raw
    WHERE funcionario_id=? AND fecha BETWEEN ? AND ? AND parse_ok=1
    GROUP BY fecha
    ORDER BY fecha ASC
  ");
  $tmp->execute(array($fid, $desde, $hasta));
  $listaDias = $tmp->fetchAll(PDO::FETCH_ASSOC);

  // completar columnas faltantes
  foreach ($listaDias as &$d) {
    $d['estado'] = ((int)$d['total_marcaciones'] % 2 === 0) ? 'OK' : 'OBS';
    $d['detalle'] = ((int)$d['total_marcaciones'] % 2 === 0) ? null : 'Marcaciones impares (falta entrada o salida)';
  }
  unset($d);
}

$getHoras = $pdo->prepare("
  SELECT hora
  FROM marcaciones_raw
  WHERE funcionario_id=? AND fecha=? AND parse_ok=1
  ORDER BY hora ASC
");

function qs_keep($extra=array()){
  $base = $_GET;
  foreach($extra as $k=>$v){ $base[$k] = $v; }
  return http_build_query($base);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Marcaciones - <?php echo h($func['nombre']); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff}
    .muted{color:#666}
    .btn{display:inline-block;padding:8px 10px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff}
    .btn:hover{background:#111;color:#fff}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top}
    th{background:#f6f6f6}
    code{background:#f6f6f6;padding:2px 6px;border-radius:6px}
    .badge{display:inline-block;padding:3px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px}
  </style>
</head>
<body>

  <div class="card">
    <h2 style="margin:0 0 6px 0;"><?php echo h($func['nombre']); ?></h2>
    <div class="muted">RUT: <?php echo h($func['rut']); ?></div>

    <form method="get" class="row">
      <input type="hidden" name="fid" value="<?php echo (int)$fid; ?>">
      <?php if ($importId > 0): ?>
        <input type="hidden" name="import_id" value="<?php echo (int)$importId; ?>">
      <?php endif; ?>

      <div>
        <div class="muted" style="font-size:12px;">Desde</div>
        <input type="date" name="desde" value="<?php echo h($desde); ?>">
      </div>
      <div>
        <div class="muted" style="font-size:12px;">Hasta</div>
        <input type="date" name="hasta" value="<?php echo h($hasta); ?>">
      </div>
      <button class="btn" type="submit">Filtrar</button>

      <a class="btn" href="funcionario_print.php?<?php echo qs_keep(); ?>" target="_blank">🖨️ Imprimir / Guardar PDF</a>

      <a class="btn" href="index.php<?php echo $importId?('?import_id='.$importId):''; ?>">← Volver</a>
    </form>

    <table>
      <tr>
        <th style="width:120px;">Fecha</th>
        <th style="width:90px;">Estado</th>
        <th style="width:220px;">Resumen</th>
        <th>Marcaciones</th>
        <th style="width:260px;">Pareo (entrada→salida)</th>
        <th style="width:240px;">Detalle</th>
      </tr>

      <?php foreach($listaDias as $d): ?>
        <?php
          $getHoras->execute(array($fid, $d['fecha']));
          $horas = $getHoras->fetchAll(PDO::FETCH_COLUMN);

          $pares = array();
          for ($i=0; $i<count($horas); $i+=2){
            $e = isset($horas[$i]) ? $horas[$i] : null;
            $s = isset($horas[$i+1]) ? $horas[$i+1] : null;
            if ($e && $s) $pares[] = $e.' → '.$s;
            elseif ($e && !$s) $pares[] = $e.' → (falta salida)';
          }

          $estado = isset($d['estado']) ? $d['estado'] : '';
          $detalle = isset($d['detalle']) ? $d['detalle'] : '';
          $res = '';
          if (!empty($d['primera']) && !empty($d['ultima'])) {
            $res = 'Primera: '.$d['primera'].' · Última: '.$d['ultima'].' · Total: '.(int)$d['total_marcaciones'];
          } else {
            $res = 'Total: '.count($horas);
          }
        ?>
        <tr>
          <td><?php echo h($d['fecha']); ?></td>
          <td><span class="badge"><?php echo h($estado); ?></span></td>
          <td class="muted"><?php echo h($res); ?></td>
          <td><code><?php echo h(implode(', ', $horas)); ?></code></td>
          <td><?php echo h(implode(' | ', $pares)); ?></td>
          <td class="muted"><?php echo h($detalle); ?></td>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($listaDias)): ?>
        <tr><td colspan="6" class="muted">No hay datos en el rango seleccionado.</td></tr>
      <?php endif; ?>
    </table>
  </div>

</body>
</html>
