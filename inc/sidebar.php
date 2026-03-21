<?php
/**
 * inc/sidebar.php — Sidebar global de navegación · Intranet Municipal
 *
 * Uso desde cualquier página:
 *   require __DIR__ . '/inc/sidebar.php';            (desde raíz)
 *   require __DIR__ . '/../inc/sidebar.php';          (desde admin/)
 *   require __DIR__ . '/../../inc/sidebar.php';       (desde admin/marcaciones/)
 *
 * Requisito: db.php y auth.php cargados antes.
 * Salida: <link> CSS + <header.topbar> + <div.body-layout> + <aside.sidebar> (sin cerrar body-layout)
 *
 * Template de la página que lo incluye:
 *   <div class="app-shell">
 *     <?php require __DIR__ . '/inc/sidebar.php'; ?>
 *     <!-- sidebar.php abre .body-layout y pone el aside dentro -->
 *     <main class="main-content">...</main>
 *   </div><!-- /body-layout -->
 * </div><!-- /app-shell -->
 */

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ── Base URL automática ─────────────────────────────────── */
$_sb_app_fs  = realpath(__DIR__ . '/..');
$_sb_root_fs = realpath(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
$_sb_base    = '';
if ($_sb_app_fs && $_sb_root_fs) {
  $_sb_base = rtrim(str_replace('\\', '/', str_replace($_sb_root_fs, '', $_sb_app_fs)), '/');
}

/* ── Estado del usuario ──────────────────────────────────── */
$_sb_fid      = !empty($_SESSION['funcionario_id']) ? (int)$_SESSION['funcionario_id'] : 0;
$_sb_nombre   = !empty($_SESSION['nombre'])         ? (string)$_SESSION['nombre']      : '';
$_sb_rut      = !empty($_SESSION['rut'])            ? (string)$_SESSION['rut']         : '';
$_sb_is_admin = !empty($_SESSION['is_superadmin'])  && (int)$_SESSION['is_superadmin'] === 1;
$_sb_logged   = $_sb_fid > 0;

/* ── Cargo vigente ───────────────────────────────────────── */
$_sb_cargo = null;
if ($_sb_logged && isset($pdo)) {
  try {
    $_sq = $pdo->prepare("
      SELECT fc.tipo, fc.fecha_desde, fc.fecha_hasta,
             c.nombre AS cargo_nombre,
             u.nombre AS unidad_nombre,
             d.nombre AS direccion_nombre
      FROM funcionario_cargos fc
      JOIN cargos c      ON c.id = fc.cargo_id
      JOIN unidades u    ON u.id = c.unidad_id
      JOIN direcciones d ON d.id = u.direccion_id
      WHERE fc.funcionario_id = ?
        AND fc.activo = 1
        AND fc.fecha_desde <= CURDATE()
        AND (fc.fecha_hasta IS NULL OR fc.fecha_hasta >= CURDATE())
      ORDER BY (fc.tipo='titular') DESC, fc.fecha_desde DESC
      LIMIT 1
    ");
    $_sq->execute(array($_sb_fid));
    $_sb_cargo = $_sq->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $_e) { $_sb_cargo = null; }
}

/* ── Permiso módulo Horas ────────────────────────────────── */
$_sb_horas = false;
if ($_sb_logged && $_sb_is_admin) {
  $_sb_horas = true;
} elseif ($_sb_logged && isset($pdo)) {
  try {
    $_sq = $pdo->prepare("SELECT 1 FROM service_staff WHERE funcionario_id=? AND (can_config=1 OR can_manage=1) LIMIT 1");
    $_sq->execute(array($_sb_fid));
    $_sb_horas = (bool)$_sq->fetchColumn();
  } catch (Exception $_e) { $_sb_horas = false; }
}

/* ── Página activa ───────────────────────────────────────── */
$_sb_self = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
function _sb_active($paths) {
  global $_sb_self;
  foreach ((array)$paths as $p) { if (strpos($_sb_self, $p) !== false) return ' is-active'; }
  return '';
}

/* ── Helpers ─────────────────────────────────────────────── */
function _sb_ini($n) {
  $w = array_filter(explode(' ', strtoupper(trim($n))));
  $o = '';
  foreach ($w as $x) { $o .= $x[0]; if (strlen($o) === 2) break; }
  return $o ?: '?';
}
function _sb_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<link rel="stylesheet" href="<?php echo $_sb_base; ?>/static/css/theme.css">
<link rel="stylesheet" href="<?php echo $_sb_base; ?>/static/css/sidebar.css">

<!-- ══ TOPBAR ═══════════════════════════════════════════════ -->
<header class="topbar">
  <button class="topbar-hamburger" id="hamburgerBtn" aria-label="Abrir menú" type="button">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <line x1="3" y1="6"  x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>

  <a href="<?php echo $_sb_base; ?>/dashboard.php" class="topbar-logo">
    <img src="<?php echo $_sb_base; ?>/static/img/logo.png" alt="Logo" class="topbar-logo-img">
    <span class="topbar-logo-name">Intranet <span class="topbar-logo-sub">Municipal</span></span>
  </a>

  <div class="topbar-sep"></div>
  <span class="topbar-org">Sistema de Gestión Interna</span>
</header>

<!-- ══ BODY LAYOUT: sidebar.php opens this div; the page closes it ══ -->
<div class="body-layout">

<!-- ══ SIDEBAR ════════════════════════════════════════════ -->
<div class="sidebar-overlay is-hidden" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-scroll">

    <!-- ── Perfil ────────────────────────────────────────── -->
    <div class="profile-block">
      <div class="profile-row">
        <div class="avatar"><?php echo _sb_h(_sb_ini($_sb_nombre)); ?></div>
        <div class="profile-info">
          <div class="profile-name"><?php echo _sb_h($_sb_nombre); ?></div>
          <div class="profile-rut"><?php echo _sb_h($_sb_rut); ?></div>
        </div>
      </div>
      <div class="profile-badges">
        <?php if ($_sb_cargo): ?>
          <span class="badge badge-blue"><?php echo _sb_h(strtoupper($_sb_cargo['tipo'])); ?></span>
        <?php else: ?>
          <span class="badge badge-gray">SIN CARGO</span>
        <?php endif; ?>
        <span class="badge <?php echo $_sb_is_admin ? 'badge-amber' : 'badge-gray'; ?>">
          <?php echo $_sb_is_admin ? 'ADMIN' : 'USUARIO'; ?>
        </span>
      </div>
    </div>

    <!-- ── Cargo vigente ─────────────────────────────────── -->
    <?php if ($_sb_cargo): ?>
    <div class="cargo-block">
      <div class="cargo-label">Cargo vigente</div>
      <div class="cargo-role"><?php echo _sb_h($_sb_cargo['cargo_nombre']); ?></div>
      <div class="cargo-dir"><?php echo _sb_h($_sb_cargo['direccion_nombre']); ?></div>
      <div class="cargo-unit"><?php echo _sb_h($_sb_cargo['unidad_nombre']); ?></div>
      <div class="cargo-dates">
        <?php echo _sb_h($_sb_cargo['fecha_desde']); ?>
        <?php echo $_sb_cargo['fecha_hasta'] ? ' → ' . _sb_h($_sb_cargo['fecha_hasta']) : ' → vigente'; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="cargo-block">
      <div class="cargo-label">Cargo vigente</div>
      <div class="cargo-unit" style="color:var(--text-subtle);font-style:italic;font-size:12px;">Sin cargo asignado para hoy.</div>
    </div>
    <?php endif; ?>

    <!-- ── Admin ─────────────────────────────────────────── -->
    <?php if ($_sb_is_admin): ?>
    <nav class="nav-section section-admin">
      <div class="nav-section-label">Administración</div>

      <a class="nav-item<?php echo _sb_active(array('/admin/index')); ?>" href="<?php echo $_sb_base; ?>/admin/index.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Panel Admin
      </a>

      <div class="nav-sep"></div>

      <a class="nav-item<?php echo _sb_active(array('/admin/users_form')); ?>" href="<?php echo $_sb_base; ?>/admin/users_form.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/>
        </svg>
        Crear usuario
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/direcciones_form')); ?>" href="<?php echo $_sb_base; ?>/admin/direcciones_form.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Nueva dirección
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/unidades_form')); ?>" href="<?php echo $_sb_base; ?>/admin/unidades_form.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="2" y="7" width="20" height="14" rx="2"/>
          <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
        </svg>
        Nueva unidad
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/cargos_form')); ?>" href="<?php echo $_sb_base; ?>/admin/cargos_form.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/>
        </svg>
        Nuevo cargo
      </a>

      <div class="nav-sep"></div>

      <a class="nav-item<?php echo _sb_active(array('/admin/direcciones_list')); ?>" href="<?php echo $_sb_base; ?>/admin/direcciones_list.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
        Direcciones
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/unidades_list')); ?>" href="<?php echo $_sb_base; ?>/admin/unidades_list.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
        Unidades
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/cargos_list')); ?>" href="<?php echo $_sb_base; ?>/admin/cargos_list.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
        Cargos
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/rol_permisos')); ?>" href="<?php echo $_sb_base; ?>/admin/rol_permisos.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>
        </svg>
        Roles y permisos
      </a>

      <div class="nav-sep"></div>

      <a class="nav-item<?php echo _sb_active(array('/marcaciones/importar')); ?>" href="<?php echo $_sb_base; ?>/admin/marcaciones/importar.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
          <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
        </svg>
        Importar marcaciones
      </a>
      <a class="nav-item<?php echo _sb_active(array('/marcaciones/index')); ?>" href="<?php echo $_sb_base; ?>/admin/marcaciones/index.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        Control de marcaciones
      </a>
      <a class="nav-item<?php echo _sb_active(array('/marcaciones/imports')); ?>" href="<?php echo $_sb_base; ?>/admin/marcaciones/imports.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
        </svg>
        Planillas subidas
      </a>
    </nav>
    <?php endif; ?>

    <!-- ── Horas ─────────────────────────────────────────── -->
    <?php if ($_sb_horas): ?>
    <nav class="nav-section section-gestion">
      <div class="nav-section-label">Gestión de Horas</div>
      <a class="nav-item<?php echo _sb_active(array('/admin/horas_dashboard')); ?>" href="<?php echo $_sb_base; ?>/admin/horas_dashboard.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Panel de horas
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/horas_solicitudes')); ?>" href="<?php echo $_sb_base; ?>/admin/horas_solicitudes.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        Solicitudes
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/horas_config')); ?>" href="<?php echo $_sb_base; ?>/admin/horas_config.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="12" cy="12" r="3"/>
          <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
        </svg>
        Configuración
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/horas_excepciones')); ?>" href="<?php echo $_sb_base; ?>/admin/horas_excepciones.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
          <line x1="12" y1="15" x2="12" y2="15" stroke-width="3"/>
        </svg>
        Excepciones
      </a>
      <a class="nav-item<?php echo _sb_active(array('/admin/horas_generar')); ?>" href="<?php echo $_sb_base; ?>/admin/horas_generar_slots.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polygon points="5 3 19 12 5 21 5 3"/>
        </svg>
        Generar slots
      </a>
    </nav>
    <?php endif; ?>

    <!-- ── Mi Espacio ────────────────────────────────────── -->
    <nav class="nav-section section-personal">
      <div class="nav-section-label">Mi espacio</div>
      <a class="nav-item<?php echo _sb_active(array('/solicitud_horas', '/horarios_horas', '/reservar_horas')); ?>" href="<?php echo $_sb_base; ?>/solicitud_horas.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
        </svg>
        Solicitar hora
      </a>
      <a class="nav-item<?php echo _sb_active(array('/marcaciones/mi')); ?>" href="<?php echo $_sb_base; ?>/marcaciones/mi.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        Mis marcaciones
      </a>
      <a class="nav-item<?php echo _sb_active(array('/actividades_form')); ?>" href="<?php echo $_sb_base; ?>/actividades_form.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Registrar actividad
      </a>
      <a class="nav-item<?php echo _sb_active(array('/actividades_list')); ?>" href="<?php echo $_sb_base; ?>/actividades_list.php">
        <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        Actividades de hoy
      </a>
    </nav>

  </div><!-- /sidebar-scroll -->

  <!-- ── Footer: cerrar sesión ─────────────────────────── -->
  <div class="sidebar-footer">
    <a class="nav-item is-danger" href="<?php echo $_sb_base; ?>/logout.php">
      <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Cerrar sesión
    </a>
  </div>

</aside><!-- /sidebar -->

<script>
(function () {
  var hamburger = document.getElementById('hamburgerBtn');
  var sidebar   = document.getElementById('sidebar');
  var overlay   = document.getElementById('sidebarOverlay');

  function openSidebar()  {
    if (sidebar)  sidebar.classList.add('is-open');
    if (overlay)  overlay.classList.remove('is-hidden');
    if (hamburger) hamburger.classList.add('is-open');
  }
  function closeSidebar() {
    if (sidebar)  sidebar.classList.remove('is-open');
    if (overlay)  overlay.classList.add('is-hidden');
    if (hamburger) hamburger.classList.remove('is-open');
  }

  if (hamburger) hamburger.addEventListener('click', openSidebar);
  if (overlay)   overlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) closeSidebar();
  });
})();
</script>