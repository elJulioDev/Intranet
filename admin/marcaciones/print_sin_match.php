<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require __DIR__ . '/_helpers.php';
require_login();

if (!function_exists('is_superadmin') || !is_superadmin()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

// Composer autoload (ajusta si tu vendor está en otra ruta)
require __DIR__ . '/../../vendor/autoload.php';

$importId = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;
$q   = isset($_GET['q']) ? trim($_GET['q']) : '';
$dpto= isset($_GET['dpto']) ? trim($_GET['dpto']) : '';

$params = array();
$where = " WHERE funcionario_id IS NULL AND parse_ok=1 ";
if ($importId > 0) { $where .= " AND import_id=? "; $params[] = $importId; }

if ($q !== '') {
  $where .= " AND (nro LIKE ? OR nombre LIKE ?) ";
  $params[] = '%'.$q.'%';
  $params[] = '%'.$q.'%';
}
if ($dpto !== '') {
  $where .= " AND dpto = ? ";
  $params[] = $dpto;
}

$st = $pdo->prepare("
  SELECT nro AS codigo, COUNT(*) total, MIN(fecha) desde, MAX(fecha) hasta
  FROM marcaciones_raw
  $where
  GROUP BY nro
  ORDER BY total DESC
  LIMIT 5000
");
$st->execute($params);
$list = $st->fetchAll(PDO::FETCH_ASSOC);

$hoy = date('Y-m-d H:i');

$html = '
<style>
  body{font-family:dejavusans, sans-serif;font-size:11px}
  h1{font-size:16px;margin:0 0 8px 0}
  .muted{color:#666}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{border:1px solid #bbb;padding:6px;text-align:left}
  th{background:#f2f2f2}
</style>
<h1>Marcaciones sin match</h1>
<div class="muted">Generado: '.$hoy.' · Import: '.($importId?:'(todos)').' · Filtro: '.h($q).' · Dpto: '.h($dpto).'</div>
<table>
<tr>
  <th>Código (No.)</th><th>Registros</th><th>Desde</th><th>Hasta</th>
</tr>';

foreach($list as $r){
  $html .= '<tr>
    <td>'.h($r['codigo']).'</td>
    <td>'.(int)$r['total'].'</td>
    <td>'.h($r['desde']).'</td>
    <td>'.h($r['hasta']).'</td>
  </tr>';
}

if (empty($list)){
  $html .= '<tr><td colspan="4" class="muted">Sin resultados</td></tr>';
}

$html .= '</table>';

$mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
$mpdf->SetTitle('Sin match - Marcaciones');
$mpdf->WriteHTML($html);

$filename = 'sin_match_' . date('Ymd_His') . '.pdf';
$mpdf->Output($filename, 'I'); // I = inline en el navegador
exit;
