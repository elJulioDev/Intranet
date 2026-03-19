<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_helpers.php';
require_login();

if (!function_exists('is_superadmin') || !is_superadmin()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

$importId = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;

// filtros
$qCodigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : ''; // seleccionado en combo
$dpto    = isset($_GET['dpto']) ? trim($_GET['dpto']) : '';

// Base WHERE para "sin match"
$paramsBase = array();
$whereBase  = " WHERE funcionario_id IS NULL AND (parse_ok=1 OR parse_ok=0) ";

if ($importId > 0) { $whereBase .= " AND import_id=? "; $paramsBase[] = $importId; }

// 1) llenar combo con códigos sin match (nro)
$stCod = $pdo->prepare("
  SELECT nro AS codigo, COUNT(*) AS total
  FROM marcaciones_raw
  $whereBase
  GROUP BY nro
  ORDER BY nro ASC
  LIMIT 2000
");
$stCod->execute($paramsBase);
$codigos = $stCod->fetchAll(PDO::FETCH_ASSOC);

// 2) llenar combo de dptos (opcional)
$stDpto = $pdo->prepare("
  SELECT DISTINCT dpto
  FROM marcaciones_raw
  $whereBase
  ORDER BY dpto ASC
");
$stDpto->execute($paramsBase);
$dptos = $stDpto->fetchAll(PDO::FETCH_COLUMN);

// 3) query principal (filtrado)
$where = $whereBase;
$params = $paramsBase;

if ($qCodigo !== '') {
  $where .= " AND nro = ? ";
  $params[] = $qCodigo;
}

if ($dpto !== '') {
  $where .= " AND dpto = ? ";
  $params[] = $dpto;
}

// Resumen/tabla agrupada
$st = $pdo->prepare("
  SELECT
    nro AS codigo,
    COUNT(*) total,
    MIN(fecha) desde,
    MAX(fecha) hasta,
    MIN(hora) primera_hora,
    MAX(hora) ultima_hora
  FROM marcaciones_raw
  $where
  GROUP BY nro
  ORDER BY total DESC, codigo ASC
  LIMIT 2000
");
$st->execute($params);
$list = $st->fetchAll(PDO::FETCH_ASSOC);

// helper querystring (para botón imprimir)
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
  <title>Marcaciones sin match</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff}
    .muted{color:#666}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px}
    input,select{padding:7px 9px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid #ddd;padding:8px;text-align:left}
    th{background:#f6f6f6}
    .btn{display:inline-block;padding:8px 10px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff}
    .btn:hover{background:#111;color:#fff}
    .small{font-size:12px}
  </style>
</head>
<body>

  <div class="card">
    <h2 style="margin:0 0 6px 0;">🧩 Marcaciones sin match</h2>
    <div class="muted">
      Aquí aparecen los códigos (No.) del archivo que no tienen funcionario asociado.
      Cuando ingreses el funcionario, usa “Reprocesar match”.
    </div>

    <form method="get" class="row">
      <?php if ($importId>0): ?>
        <input type="hidden" name="import_id" value="<?php echo (int)$importId; ?>">
      <?php endif; ?>

      <div>
        <div class="muted small">Código/RUT base sin match</div>
        <select name="codigo">
          <option value="">(todos)</option>
          <?php foreach($codigos as $c): ?>
            <?php
              $val = (string)$c['codigo'];
              $lbl = $val . ' (' . (int)$c['total'] . ')';
            ?>
            <option value="<?php echo h($val); ?>" <?php echo ($qCodigo===$val?'selected':''); ?>>
              <?php echo h($lbl); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <div class="muted small">Dpto</div>
        <select name="dpto">
          <option value="">(todos)</option>
          <?php foreach($dptos as $dd): ?>
            <option value="<?php echo h($dd); ?>" <?php echo ($dpto===$dd?'selected':''); ?>>
              <?php echo h($dd); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button class="btn" type="submit">Filtrar</button>

      <a class="btn" href="sin_match.php<?php echo $importId?('?import_id='.$importId):''; ?>">Limpiar</a>

      <a class="btn" href="reprocesar_match.php<?php echo $importId?('?import_id='.$importId):''; ?>">🔄 Reprocesar match</a>

      <a class="btn" target="_blank" href="print_sin_match.php?<?php echo qs_keep(); ?>">🖨️ Imprimir / Guardar PDF</a>

      <a class="btn" href="index.php<?php echo $importId?('?import_id='.$importId):''; ?>">← Volver</a>
    </form>

    <div class="muted" style="margin-top:10px;">
      Grupos mostrados: <strong><?php echo (int)count($list); ?></strong>
      <?php if ($importId>0): ?> · Import: <strong>#<?php echo (int)$importId; ?></strong><?php endif; ?>
    </div>

    <table>
      <tr>
        <th style="width:160px;">Código (No.)</th>
        <th style="width:110px;">Registros</th>
        <th style="width:120px;">Desde</th>
        <th style="width:120px;">Hasta</th>
        <th style="width:120px;">Primera hora</th>
        <th style="width:120px;">Última hora</th>
      </tr>

      <?php foreach($list as $r): ?>
        <tr>
          <td><strong><?php echo h($r['codigo']); ?></strong></td>
          <td><?php echo (int)$r['total']; ?></td>
          <td><?php echo h($r['desde']); ?></td>
          <td><?php echo h($r['hasta']); ?></td>
          <td><?php echo h($r['primera_hora']); ?></td>
          <td><?php echo h($r['ultima_hora']); ?></td>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($list)): ?>
        <tr><td colspan="6" class="muted">No hay resultados.</td></tr>
      <?php endif; ?>
    </table>

  </div>

</body>
</html>
