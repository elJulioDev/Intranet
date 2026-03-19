<?php
// intranet/admin/_guard.php (PHP 5.6)

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/csrf.php';

// Cargar ACL solo si existe (por si aún no lo implementas)
$aclPath = __DIR__ . '/../inc/acl.php';
if (file_exists($aclPath)) {
  require $aclPath;
}

require_login();

/**
 * Puente: mientras armas RBAC, el admin se controla por is_superadmin.
 * Si ya tienes RBAC (require_perm), puedes exigir admin_home/admin.
 */
if (!function_exists('is_superadmin') || !is_superadmin()) {
  if (function_exists('require_perm')) {
    require_perm('admin_home', 'admin');
  } else {
    http_response_code(403);
    exit('Acceso denegado.');
  }
}

if (!function_exists('h')) {
  function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}
