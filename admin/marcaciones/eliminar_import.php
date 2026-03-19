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
if ($importId <= 0) exit('import_id inválido');

$pdo->beginTransaction();

// borra validaciones (no tienen import_id, así que se recalculan igual)
$pdo->prepare("DELETE FROM marcaciones_raw WHERE import_id=?")->execute(array($importId));
$pdo->prepare("DELETE FROM imports WHERE id=?")->execute(array($importId));

$pdo->commit();

header('Location: imports.php');
exit;
