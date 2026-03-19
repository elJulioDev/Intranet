<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require __DIR__ . '/_helpers.php';
require_login();

if (!function_exists('is_superadmin') || !is_superadmin()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

$importId = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;
if ($importId <= 0) exit('import_id inválido');

// traer grupos (funcionario + fecha) OK de este import
$groups = $pdo->prepare("
  SELECT funcionario_id, fecha, COUNT(*) AS n
  FROM marcaciones_raw
  WHERE import_id=? AND parse_ok=1 AND funcionario_id IS NOT NULL AND fecha IS NOT NULL AND hora IS NOT NULL
  GROUP BY funcionario_id, fecha
");
$groups->execute(array($importId));
$rows = $groups->fetchAll(PDO::FETCH_ASSOC);

$del = $pdo->prepare("DELETE FROM marcaciones_validacion WHERE funcionario_id=? AND fecha=?");
$ins = $pdo->prepare("
  INSERT INTO marcaciones_validacion (funcionario_id, fecha, total_marcaciones, estado, detalle, primera, ultima)
  VALUES (?,?,?,?,?,?,?)
");

$getHoras = $pdo->prepare("
  SELECT hora
  FROM marcaciones_raw
  WHERE import_id=? AND funcionario_id=? AND fecha=? AND parse_ok=1
  ORDER BY hora ASC
");

foreach ($rows as $g) {
  $fid = (int)$g['funcionario_id'];
  $fecha = $g['fecha'];

  $getHoras->execute(array($importId, $fid, $fecha));
  $horas = $getHoras->fetchAll(PDO::FETCH_COLUMN);

  $estado = 'OK';
  $detalle = null;

  $n = count($horas);
  if ($n === 0) {
    $estado = 'ERROR';
    $detalle = 'Sin horas';
  } else {
    // duplicadas
    $uniq = array_unique($horas);
    if (count($uniq) !== $n) {
      $estado = 'OBS';
      $detalle = 'Horas duplicadas';
    }
    // impar
    if (($n % 2) === 1) {
      $estado = 'OBS';
      $detalle = $detalle ? ($detalle . ' + Marcaciones impares') : 'Marcaciones impares (falta entrada o salida)';
    }
  }

  $primera = $n ? $horas[0] : null;
  $ultima  = $n ? $horas[$n-1] : null;

  $del->execute(array($fid, $fecha));
  $ins->execute(array($fid, $fecha, $n, $estado, $detalle, $primera, $ultima));
}

// Redirect al dashboard global
header('Location: index.php?import_id='.(int)$importId);
exit;
