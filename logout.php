<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';

$fid = current_user_id();
$rut = !empty($_SESSION['rut']) ? $_SESSION['rut'] : null;

if ($fid) {
  log_auth($pdo, $fid, $rut, 'logout', 1, null);
}

$_SESSION = array();
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"], $params["secure"], $params["httponly"]
  );
}
session_destroy();

header('Location: login.php');
exit;
