<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/_helpers.php';
require_login();

// Permiso módulo marcaciones (si no existe, cae a superadmin)
if (!function_exists('can_marcaciones')) {
  function can_marcaciones() { return function_exists('is_superadmin') && is_superadmin(); }
}
if (!can_marcaciones()) {
  http_response_code(403);
  exit('Acceso denegado.');
}

$importId = isset($_GET['import_id']) ? (int)$_GET['import_id'] : 0;
if ($importId <= 0) exit('import_id inválido');

$modo = isset($_GET['modo']) ? strtolower(trim($_GET['modo'])) : 'match';
if (!in_array($modo, array('match','parse','todo'), true)) $modo = 'match';

function parse_fecha_multi($txt){
  $txt = trim((string)$txt);
  if ($txt === '') return null;
  $fmts = array('d-m-Y','d/m/Y','Y-m-d','Y/m/d');
  foreach ($fmts as $f){
    $dt = DateTime::createFromFormat($f, $txt);
    if ($dt && $dt->format($f) === $txt) return $dt->format('Y-m-d');
  }
  return null;
}
function parse_hora_multi($txt){
  $txt = trim((string)$txt);
  if ($txt === '') return null;
  $fmts = array('G:i:s','H:i:s','G:i','H:i');
  foreach ($fmts as $f){
    $dt = DateTime::createFromFormat($f, $txt);
    if ($dt) return $dt->format('H:i:s');
  }
  return null;
}

/**
 * PASO 1: Reprocesar parse (fecha/hora/parse_ok) si modo=parse o modo=todo
 */
if ($modo === 'parse' || $modo === 'todo') {

  $lastId = 0;

  $sel = $pdo->prepare("
    SELECT id, fecha_hora_txt, fecha, hora
    FROM marcaciones_raw
    WHERE import_id=?
      AND id > ?
      AND (fecha IS NULL OR hora IS NULL OR parse_ok=0)
    ORDER BY id ASC
    LIMIT 5000
  ");

  $upd = $pdo->prepare("
    UPDATE marcaciones_raw
    SET fecha=?, hora=?, parse_ok=?, parse_error=?
    WHERE id=?
  ");

  while (true) {
    $sel->execute(array($importId, $lastId));
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) break;

    foreach ($rows as $r) {
      $lastId = (int)$r['id'];

      $txt = trim((string)$r['fecha_hora_txt']);
      $fTxt = ''; $hTxt = '';
      if ($txt !== '') {
        $parts = preg_split('/\s+/', $txt);
        $fTxt = isset($parts[0]) ? $parts[0] : '';
        $hTxt = isset($parts[1]) ? $parts[1] : '';
      }

      $fecha = !empty($r['fecha']) ? $r['fecha'] : ($fTxt ? parse_fecha_multi($fTxt) : null);
      $hora  = !empty($r['hora'])  ? $r['hora']  : ($hTxt ? parse_hora_multi($hTxt)  : null);

      $parseOk = ($fecha && $hora) ? 1 : 0;
      $parseErr = $parseOk ? null : 'No se pudo parsear fecha u hora';

      $upd->execute(array($fecha, $hora, $parseOk, $parseErr, (int)$r['id']));
    }
  }
}

/**
 * PASO 2: Reprocesar match si modo=match o modo=todo
 * - Solo toma filas parse_ok=1 y funcionario_id IS NULL
 * - Reusa helper find_funcionario_by_rut_base() si existe en _helpers.php
 */
if ($modo === 'match' || $modo === 'todo') {

  if (!function_exists('find_funcionario_by_rut_base')) {
    // fallback mínimo (por si no existe tu helper)
    function find_funcionario_by_rut_base(PDO $pdo, $rutBase){
      $rutBase = preg_replace('/\D+/', '', (string)$rutBase);
      if ($rutBase === '') return array(null, 'RUT base vacío');

      // Busca por prefijo del rut normalizado
      $st = $pdo->prepare("
        SELECT id, rut
        FROM funcionarios
        WHERE REPLACE(REPLACE(rut,'.',''),'-','') LIKE ?
        LIMIT 2
      ");
      $st->execute(array($rutBase.'%'));
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);

      if (count($rows) === 1) return array((int)$rows[0]['id'], null);
      if (count($rows) > 1) return array(null, 'Match ambiguo (más de un funcionario coincide)');
      return array(null, 'Sin match en funcionarios');
    }
  }

  $lastId = 0;

  $sel = $pdo->prepare("
    SELECT id, nro, nombre
    FROM marcaciones_raw
    WHERE import_id=?
      AND id > ?
      AND parse_ok=1
      AND funcionario_id IS NULL
    ORDER BY id ASC
    LIMIT 5000
  ");

  $upd = $pdo->prepare("
    UPDATE marcaciones_raw
    SET funcionario_id=?, rut=?, parse_error=?
    WHERE id=?
  ");

  while (true) {
    $sel->execute(array($importId, $lastId));
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) break;

    foreach ($rows as $r) {
      $lastId = (int)$r['id'];

      $rutBase = $r['nro'] ? $r['nro'] : $r['nombre'];
      list($fid, $fidErr) = find_funcionario_by_rut_base($pdo, $rutBase);

      if ($fid) {
        $q = $pdo->prepare("SELECT rut FROM funcionarios WHERE id=? LIMIT 1");
        $q->execute(array((int)$fid));
        $rutReal = $q->fetchColumn();

        $upd->execute(array((int)$fid, $rutReal, null, (int)$r['id']));
      } else {
        // No bajamos parse_ok, solo dejamos observación
        $upd->execute(array(null, null, $fidErr ? $fidErr : 'Sin match en funcionarios', (int)$r['id']));
      }
    }
  }
}

/**
 * PASO 3: Recalcular stats del import
 */
$st = $pdo->prepare("
  SELECT
    COUNT(*) total,
    SUM(parse_ok=1) ok,
    SUM(parse_ok=0) err
  FROM marcaciones_raw
  WHERE import_id=?
");
$st->execute(array($importId));
$sum = $st->fetch(PDO::FETCH_ASSOC);

$upImp = $pdo->prepare("UPDATE imports SET rows_total=?, rows_ok=?, rows_error=? WHERE id=?");
$upImp->execute(array((int)$sum['total'], (int)$sum['ok'], (int)$sum['err'], $importId));

/**
 * Redirige a validar (que luego te manda al control)
 */
header('Location: validar.php?import_id='.$importId);
exit;
