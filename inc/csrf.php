<?php
// intranet/inc/csrf.php (PHP 5.6)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function csrf_token() {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
  }
  return $_SESSION['csrf'];
}

function csrf_check() {
  $t = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
  if (!$t || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
    http_response_code(400);
    exit('CSRF inválido.');
  }
}
