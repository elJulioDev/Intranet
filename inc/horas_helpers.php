<?php
// intranet/inc/horas_helpers.php (PHP 5.6+)
// Helpers para el módulo de solicitud de horas.

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ──────────────────────────────────────────
   Escape HTML
────────────────────────────────────────── */
function horas_h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ──────────────────────────────────────────
   CSRF propio del módulo
   Usa clave 'horas_csrf' para no chocar
   con el CSRF global de la intranet.
────────────────────────────────────────── */
function horas_csrf_token() {
  if (empty($_SESSION['horas_csrf'])) {
    $_SESSION['horas_csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
  }
  return $_SESSION['horas_csrf'];
}

function horas_csrf_check() {
  $token = isset($_POST['horas_csrf']) ? (string)$_POST['horas_csrf'] : '';
  if (!$token || empty($_SESSION['horas_csrf'])) return false;
  return hash_equals($_SESSION['horas_csrf'], $token);
}

/* ──────────────────────────────────────────
   Autenticación
────────────────────────────────────────── */
function horas_require_login() {
  if (empty($_SESSION['funcionario_id'])) {
    header('Location: /login.php');
    exit;
  }
}

function horas_current_funcionario_id() {
  return !empty($_SESSION['funcionario_id']) ? (int)$_SESSION['funcionario_id'] : 0;
}

/* ──────────────────────────────────────────
   Verificar si el funcionario es superadmin
────────────────────────────────────────── */
function horas_is_superadmin(PDO $pdo, $funcionarioId) {
  if (!empty($_SESSION['is_superadmin']) && (int)$_SESSION['is_superadmin'] === 1) {
    return true;
  }
  $st = $pdo->prepare("SELECT is_superadmin FROM funcionarios WHERE id=? AND activo=1 LIMIT 1");
  $st->execute(array((int)$funcionarioId));
  return (bool)(int)$st->fetchColumn();
}

/* ──────────────────────────────────────────
   Servicios que el funcionario puede usar
   $nivel: 'config' | 'manage'
────────────────────────────────────────── */
function horas_allowed_services(PDO $pdo, $funcionarioId, $nivel) {
  if (horas_is_superadmin($pdo, $funcionarioId)) {
    $st = $pdo->query("SELECT id, name, description FROM services WHERE active=1 ORDER BY name");
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  $col = ($nivel === 'config') ? 'can_config' : 'can_manage';

  $st = $pdo->prepare("
    SELECT sv.id, sv.name, sv.description
    FROM services sv
    JOIN service_staff ss ON ss.service_id = sv.id
    WHERE sv.active   = 1
      AND ss.funcionario_id = ?
      AND ss.$col = 1
    ORDER BY sv.name
  ");
  $st->execute(array((int)$funcionarioId));
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ──────────────────────────────────────────
   Verificar acceso a un servicio concreto
────────────────────────────────────────── */
function horas_can_access_service(PDO $pdo, $funcionarioId, $serviceId, $nivel) {
  if (horas_is_superadmin($pdo, $funcionarioId)) return true;

  $col = ($nivel === 'config') ? 'can_config' : 'can_manage';

  $st = $pdo->prepare("
    SELECT 1
    FROM service_staff
    WHERE funcionario_id = ?
      AND service_id     = ?
      AND $col = 1
    LIMIT 1
  ");
  $st->execute(array((int)$funcionarioId, (int)$serviceId));
  return (bool)$st->fetchColumn();
}

/* ──────────────────────────────────────────
   Código aleatorio para appointments
────────────────────────────────────────── */
function horas_random_code($length) {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $code  = '';
  $max   = strlen($chars) - 1;
  for ($i = 0; $i < (int)$length; $i++) {
    $code .= $chars[mt_rand(0, $max)];
  }
  return $code;
}