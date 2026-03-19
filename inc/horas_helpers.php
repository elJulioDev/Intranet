<?php
// inc/horas_helpers.php
if (session_status() === PHP_SESSION_NONE) session_start();

function horas_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function horas_current_funcionario_id(){
  if (!empty($_SESSION['funcionario_id'])) return (int)$_SESSION['funcionario_id'];
  if (!empty($_SESSION['uid'])) return (int)$_SESSION['uid'];        // fallback tÃ­pico
  if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id']; // fallback
  return 0;
}

function horas_require_login(){
  if (horas_current_funcionario_id() <= 0){
    http_response_code(403);
    exit('Acceso denegado (sin sesiÃ³n).');
  }
}

function horas_is_superadmin(PDO $pdo, $fid){
  $st = $pdo->prepare("SELECT is_superadmin FROM funcionarios WHERE id=? LIMIT 1");
  $st->execute([(int)$fid]);
  return (int)$st->fetchColumn() === 1;
}

function horas_can_access_service(PDO $pdo, $fid, $serviceId, $need='manage'){
  if (horas_is_superadmin($pdo, $fid)) return true;
  $col = ($need === 'config') ? 'can_config' : 'can_manage';
  $sql = "SELECT 1 FROM service_staff WHERE funcionario_id=? AND service_id=? AND {$col}=1 LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([(int)$fid, (int)$serviceId]);
  return (bool)$st->fetchColumn();
}

function horas_allowed_services(PDO $pdo, $fid, $need='manage'){
  if (horas_is_superadmin($pdo, $fid)){
    return $pdo->query("SELECT id, name FROM services WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
  }
  $col = ($need === 'config') ? 'can_config' : 'can_manage';
  $st = $pdo->prepare("
    SELECT s.id, s.name
    FROM services s
    JOIN service_staff ss ON ss.service_id=s.id
    WHERE s.active=1 AND ss.funcionario_id=? AND ss.{$col}=1
    ORDER BY s.name
  ");
  $st->execute([(int)$fid]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

// CSRF propio del mÃ³dulo (no colisiona con tus helpers)
function horas_csrf_token() {
  if (session_status() === PHP_SESSION_NONE) { @session_start(); }

  if (empty($_SESSION['horas_csrf'])) {
    // PHP 5.6 compatible
    if (function_exists('openssl_random_pseudo_bytes')) {
      $bytes = openssl_random_pseudo_bytes(32);
      $_SESSION['horas_csrf'] = bin2hex($bytes);
    } else {
      // Fallback (menos fuerte, pero funcional)
      $_SESSION['horas_csrf'] = sha1(uniqid(mt_rand(), true) . microtime(true));
    }
  }
  return $_SESSION['horas_csrf'];
}

function horas_csrf_check() {
  if (session_status() === PHP_SESSION_NONE) { @session_start(); }

  $sent = '';
  if (isset($_POST['horas_csrf'])) $sent = (string)$_POST['horas_csrf'];
  if (isset($_GET['horas_csrf']))  $sent = (string)$_GET['horas_csrf'];

  if ($sent === '') return false;
  if (empty($_SESSION['horas_csrf'])) return false;

  // hash_equals no existe en algunos PHP viejos; en 5.6 deber¨ªa estar, pero hacemos fallback
  if (function_exists('hash_equals')) {
    return hash_equals($_SESSION['horas_csrf'], $sent);
  }
  return $_SESSION['horas_csrf'] === $sent;
}



function horas_random_code($len=10){
  $chars='ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $max = strlen($chars) - 1;
  $out='';

  // Si hay OpenSSL, usamos bytes aleatorios
  if (function_exists('openssl_random_pseudo_bytes')) {
    $bytes = openssl_random_pseudo_bytes($len);
    for ($i=0; $i<$len; $i++) {
      $idx = ord($bytes[$i]) % ($max + 1);
      $out .= $chars[$idx];
    }
    return $out;
  }

  // Fallback: mt_rand (suficiente para c¨®digos no cr¨ªticos)
  for($i=0;$i<$len;$i++){
    $out .= $chars[mt_rand(0, $max)];
  }
  return $out;
}

