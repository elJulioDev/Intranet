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

$desde = !empty($_GET['desde']) ? $_GET['desde'] : '';
$hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : '';

if ($importId > 0 && $desde === '' && $hasta === '') {
  $mm = $pdo->prepare("
    SELECT MIN(fecha) d1, MAX(fecha) d2
    FROM marcaciones_raw
    WHERE import_id=? AND funcionario_id=? AND fecha IS NOT NULL
  ");
  $mm->execute(array($importId, $fid));
  $r = $mm->fetch(PDO::FETCH_ASSOC);
  if ($r && $r['d1'] && $r['d2']) { $desde = $r['d1']; $hasta = $r['d2']; }
}

if ($desde === '') $desde = date('Y-m-01');
if ($hasta === '') $hasta = date('Y-m-t');

$uf = $pdo->prepare("SELECT rut, CONCAT(nombres,' ',apellidos) AS nombre FROM funcionarios WHERE id=? LIMIT 1");
$uf->execute(array($fid));
$func = $uf->fetch(PDO::FETCH_ASSOC);
if (!$func) exit('Funcionario no encontrado');

$days = $pdo->prepare("
  SELECT fecha, estado, detalle, total_marcaciones, primera, ultima
  FROM marcaciones_validacion
  WHERE funcionario_id=? AND fecha BETWEEN ? AND ?
  ORDER BY fecha ASC
");
$days->execute(array($fid, $desde, $hasta));
$listaDias = $days->fetchAll(PDO::FETCH_ASSOC);

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

$generado = date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Impresión - Marcaciones</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111}
    .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
    .btn{display:inline-block;padding:8px 10px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;cursor:pointer}
    .btn:hover{background:#111;color:#fff}
    .muted{color:#666}
    h1{font-size:18px;margin:0 0 4px 0}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{border:1px solid #bbb;padding:6px;text-align:left;vertical-align:top}
    th{background:#f2f2f2}
    code{background:#f6f6f6;padding:2px 6px;border-radius:6px}

    @media print{
      body{margin:0;font-size:11px}
      .toolbar{display:none !important}
      thead{display:table-header-group}
    }
  </style>
</head>
<body>

  <div class="toolbar">
    <button class="btn" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    <a class="btn" href="funcionario.php?<?php echo http_build_query($_GET); ?>">← Volver</a>
    <span class="muted">Tip: Chrome/Edge → “Guardar como PDF”.</span>
  </div>

  <h1>Marcaciones funcionario</h1>
  <div><strong><?php echo h($func['nombre']); ?></strong> — RUT: <?php echo h($func['rut']); ?></div>
  <div class="muted">Rango: <?php echo h($desde); ?> → <?php echo h($hasta); ?> · Generado: <?php echo h($generado); ?></div>

  <table>
    <thead>
      <tr>
        <th style="width:110px;">Fecha</th>
        <th style="width:70px;">Estado</th>
        <th style="width:200px;">Resumen</th>
        <th>Marcaciones</th>
        <th style="width:250px;">Pareo</th>
        <th style="width:220px;">Detalle</th>
      </tr>
    </thead>
    <tbody>
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

          $res = '';
          if (!empty($d['primera']) && !empty($d['ultima'])) {
            $res = 'Primera: '.$d['primera'].' · Última: '.$d['ultima'].' · Total: '.(int)$d['total_marcaciones'];
          } else {
            $res = 'Total: '.count($horas);
          }
        ?>
        <tr>
          <td><?php echo h($d['fecha']); ?></td>
          <td><?php echo h($d['estado']); ?></td>
          <td class="muted"><?php echo h($res); ?></td>
          <td><code><?php echo h(implode(', ', $horas)); ?></code></td>
          <td><?php echo h(implode(' | ', $pares)); ?></td>
          <td class="muted"><?php echo h($d['detalle']); ?></td>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($listaDias)): ?>
        <tr><td colspan="6" class="muted">No hay datos en el rango.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    // Si quieres que abra imprimiendo automáticamente, descomenta:
    // window.onload = function(){ window.print(); };
  </script>

</body>
</html>
