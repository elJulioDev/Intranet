<?php
// intranet/inc/auth.php (PHP 5.6)
// Guard: evita re-declarar funciones si se incluye más de una vez
if (defined('_AUTH_LOADED')) return;
define('_AUTH_LOADED', true);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/db.php';

function norm_rut($rut) {
  $rut = strtoupper(trim((string)$rut));
  $rut = str_replace(array('.', '-', ' '), '', $rut);
  return $rut;
}

function current_user_id() {
  return !empty($_SESSION['funcionario_id']) ? (int)$_SESSION['funcionario_id'] : 0;
}

function current_user_rut() {
  return !empty($_SESSION['rut']) ? (string)$_SESSION['rut'] : '';
}

function require_login() {
  if (empty($_SESSION['funcionario_id'])) {
    header('Location: login.php');
    exit;
  }
}

function is_superadmin() {
  return !empty($_SESSION['is_superadmin']) && (int)$_SESSION['is_superadmin'] === 1;
}

function log_auth($pdo, $funcionarioId, $rutIntentado, $evento, $permitido, $motivo) {
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
  $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

  $stmt = $pdo->prepare("INSERT INTO auth_log (funcionario_id, rut_intentado, evento, permitido, motivo, ip, user_agent)
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute(array(
    $funcionarioId ? $funcionarioId : null,
    $rutIntentado ? $rutIntentado : null,
    $evento,
    (int)$permitido,
    $motivo ? $motivo : null,
    $ip,
    $ua
  ));
}

/**
 * Anti fuerza bruta simple: bloquea si >=5 fallos en 10 minutos por RUT.
 */
function is_blocked_by_rut($pdo, $rutNorm) {
  $stmt = $pdo->prepare("SELECT COUNT(*) n
                         FROM auth_log
                         WHERE rut_intentado = ?
                           AND evento='login_fail'
                           AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
  $stmt->execute(array($rutNorm));
  $n = (int)$stmt->fetchColumn();
  return ($n >= 5);
}

function attempt_login($pdo, $rut, $clave) {
  $rutNorm = norm_rut($rut);

  if (is_blocked_by_rut($pdo, $rutNorm)) {
    log_auth($pdo, null, $rutNorm, 'login_fail', 0, 'bloqueo_fuerza_bruta');
    return array(false, 'Demasiados intentos. Intenta más tarde.');
  }

  $stmt = $pdo->prepare("SELECT id, rut, clave_hash, activo, nombres, apellidos, is_superadmin
                         FROM funcionarios
                         WHERE rut = ?
                         LIMIT 1");
  $stmt->execute(array($rutNorm));
  $u = $stmt->fetch();

  if (!$u) {
    log_auth($pdo, null, $rutNorm, 'login_fail', 0, 'usuario_no_existe');
    return array(false, 'Credenciales inválidas.');
  }

  if (empty($u['activo'])) {
    log_auth($pdo, (int)$u['id'], $rutNorm, 'login_fail', 0, 'usuario_inactivo');
    return array(false, 'Credenciales inválidas.');
  }

  $hash = isset($u['clave_hash']) ? (string)$u['clave_hash'] : '';
  if ($hash === '' || !password_verify((string)$clave, $hash)) {
    log_auth($pdo, (int)$u['id'], $rutNorm, 'login_fail', 0, 'clave_incorrecta');
    return array(false, 'Credenciales inválidas.');
  }

  // OK
  session_regenerate_id(true);
  $_SESSION['funcionario_id']  = (int)$u['id'];
  $_SESSION['rut']             = $rutNorm;
  $_SESSION['nombre']          = trim($u['nombres'].' '.$u['apellidos']);

  // PHP 5.6: NO usar ??
  $_SESSION['is_superadmin']   = isset($u['is_superadmin']) ? (int)$u['is_superadmin'] : 0;

  log_auth($pdo, (int)$u['id'], $rutNorm, 'login_ok', 1, null);

  return array(true, 'OK');
}