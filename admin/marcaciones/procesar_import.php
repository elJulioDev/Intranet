<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require __DIR__ . '/_helpers.php';
require_login();

if (!function_exists('is_superadmin') || !is_superadmin()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
  exit('Error al subir archivo.');
}

$tmp = $_FILES['archivo']['tmp_name'];
$orig = basename($_FILES['archivo']['name']);

$uploadsDir = __DIR__ . '/../uploads/marcaciones';
if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0775, true); }

$safeName = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_', $orig);
$dest = $uploadsDir . '/' . $safeName;
if (!move_uploaded_file($tmp, $dest)) {
  exit('No se pudo guardar el archivo.');
}

// Crear import
$rutUploader = !empty($_SESSION['rut']) ? $_SESSION['rut'] : null;

$pdo->beginTransaction();
$st = $pdo->prepare("INSERT INTO imports (filename, uploaded_by_rut) VALUES (?,?)");
$st->execute(array($safeName, $rutUploader));
$importId = (int)$pdo->lastInsertId();
$pdo->commit();

$fh = fopen($dest, 'r');
if (!$fh) exit('No se pudo abrir el archivo.');

$firstLine = fgets($fh);
if ($firstLine === false) exit('Archivo vacío.');
$delim = detect_delimiter($firstLine);

// re-iniciar lectura
rewind($fh);

$ins = $pdo->prepare("
  INSERT INTO marcaciones_raw
    (import_id, dpto, nombre, nro, fecha_hora_txt, fecha, hora, funcionario_id, rut, parse_ok, parse_error)
  VALUES (?,?,?,?,?,?,?,?,?,?,?)
");

$total=0; $ok=0; $err=0;

// Nota: tu muestra parece: Dpto | Nombre(=5679268) | No.(=5679268) | Fecha | Hora
while (($row = fgetcsv($fh, 0, $delim)) !== false) {
  // limpiar filas vacías
  if (count($row) === 1 && trim($row[0]) === '') continue;

  $total++;

  $dpto  = isset($row[0]) ? trim($row[0]) : null;
  $col2  = isset($row[1]) ? trim($row[1]) : null; // "Nombre" en tu archivo, pero numérico
  $col3  = isset($row[2]) ? trim($row[2]) : null; // "No."
  $fTxt  = isset($row[3]) ? trim($row[3]) : null;
  $hTxt  = isset($row[4]) ? trim($row[4]) : null;

  $fecha = $fTxt ? parse_fecha($fTxt) : null;
  $hora  = $hTxt ? parse_hora($hTxt) : null;

  $parseOk = 1;
  $parseErr = null;

  if (!$fecha || !$hora) {
    $parseOk = 0;
    $parseErr = 'No se pudo parsear fecha u hora';
  }

  // Rut base: preferimos col3 si viene (No.), si no col2
  $rutBase = $col3 ? $col3 : $col2;
  $funcionarioId = null;
  $rutReal = null;

  if ($parseOk) {
    list($refId, $refErr) = find_ref_by_rut_base($pdo, $rutBase);
    if ($fid) {
      $funcionarioId = $fid;
      // opcional: guardar rut real (texto) en raw
      $q = $pdo->prepare("SELECT rut FROM funcionarios WHERE id=? LIMIT 1");
      $q->execute(array($fid));
      $rutReal = $q->fetchColumn();
    } else {
      $parseOk = 0;
      $parseErr = $fidErr ? $fidErr : 'Sin match';
    }
  }

  $fechaHoraTxt = trim(($fTxt?:'') . ' ' . ($hTxt?:''));

  try {
    $ins->execute(array(
      $importId,
      $dpto,
      $col2,
      $col3,
      $fechaHoraTxt,
      $fecha,
      $hora,
      $funcionarioId,
      $rutReal,
      $parseOk,
      $parseErr
    ));
    if ($parseOk) $ok++; else $err++;
  } catch (Exception $e) {
    $err++;
  }
}
fclose($fh);

// actualizar import stats
$up = $pdo->prepare("UPDATE imports SET rows_total=?, rows_ok=?, rows_error=? WHERE id=?");
$up->execute(array($total, $ok, $err, $importId));

// Ejecutar validación y redirigir
header('Location: validar.php?import_id='.(int)$importId);
exit;
