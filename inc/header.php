<?php
/**
 * inc/header.php — Header global de la Intranet Municipal
 *
 * Uso:
 *   require __DIR__ . '/inc/header.php';          (desde raíz)
 *   require __DIR__ . '/../inc/header.php';        (desde admin/)
 *   require __DIR__ . '/../../inc/header.php';     (desde admin/marcaciones/)
 *
 * Requiere que db.php y auth.php se hayan cargado antes.
 * Inyecta <link> de theme.css y header.css automáticamente.
 */

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ── Base URL automática ──────────────────────────────────── */
$_gh_app_fs  = realpath(__DIR__ . '/..');
$_gh_root_fs = realpath(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
$_gh_base    = '';
if ($_gh_app_fs && $_gh_root_fs) {
  $_gh_base = rtrim(str_replace('\\', '/', str_replace($_gh_root_fs, '', $_gh_app_fs)), '/');
}

/* ── Estado del usuario ───────────────────────────────────── */
$_gh_fid      = !empty($_SESSION['funcionario_id']) ? (int)$_SESSION['funcionario_id'] : 0;
$_gh_nombre   = !empty($_SESSION['nombre'])         ? (string)$_SESSION['nombre']      : '';
$_gh_is_admin = !empty($_SESSION['is_superadmin'])  && (int)$_SESSION['is_superadmin'] === 1;
$_gh_logged   = $_gh_fid > 0;

/* ── Permiso módulo Horas ─────────────────────────────────── */
$_gh_horas = false;
if ($_gh_logged && $_gh_is_admin) {
  $_gh_horas = true;
} elseif ($_gh_logged && isset($pdo)) {
  try {
    $_s = $pdo->prepare("SELECT 1 FROM service_staff WHERE funcionario_id=? AND (can_config=1 OR can_manage=1) LIMIT 1");
    $_s->execute(array($_gh_fid));
    $_gh_horas = (bool)$_s->fetchColumn();
  } catch (Exception $_e) { $_gh_horas = false; }
}

/* ── Página activa ────────────────────────────────────────── */
$_gh_self = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
function _gh_active($paths) {
  global $_gh_self;
  foreach ((array)$paths as $p) { if (strpos($_gh_self, $p) !== false) return ' is-active'; }
  return '';
}
function _gh_ini($n) {
  $w = array_filter(explode(' ', strtoupper(trim($n))));
  $o = '';
  foreach ($w as $x) { $o .= $x[0]; if (strlen($o) === 2) break; }
  return $o ?: '?';
}
function _gh_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<link rel="stylesheet" href="<?php echo $_gh_base; ?>/static/css/theme.css">
<link rel="stylesheet" href="<?php echo $_gh_base; ?>/static/css/header.css">

<header class="gh-bar" id="ghBar">
  <div class="gh-inner">

    <!-- ── Marca ──────────────────────────────────────────── -->
    <a class="gh-brand" href="<?php echo $_gh_base; ?>/dashboard.php">
      <img class="gh-logo" src="<?php echo $_gh_base; ?>/static/img/logo.png" alt="Logo">
      <span class="gh-brand-name">Intranet <span class="gh-brand-sub">Municipal</span></span>
    </a>

    <!-- ── Navegación escritorio ──────────────────────────── -->
    <?php if ($_gh_logged): ?>
    <nav class="gh-nav" id="ghNav">

      <!-- ─── MI ESPACIO ─── -->
      <div class="gh-group<?php echo _gh_active(array('/actividades', '/marcaciones/mi', '/solicitud_horas', '/horarios_horas', '/reservar_horas', '/estado_horas')); ?>">
        <button class="gh-nav-btn" type="button">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          Mi espacio
          <svg class="gh-chev" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="gh-drop">
          <a class="gh-drop-item<?php echo _gh_active(array('/actividades_form')); ?>" href="<?php echo $_gh_base; ?>/actividades_form.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Registrar actividad
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/actividades_list')); ?>" href="<?php echo $_gh_base; ?>/actividades_list.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Actividades de hoy
          </a>
          <div class="gh-drop-sep"></div>
          <a class="gh-drop-item<?php echo _gh_active(array('/marcaciones/mi')); ?>" href="<?php echo $_gh_base; ?>/marcaciones/mi.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Mis marcaciones
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/solicitud_horas', '/horarios_horas', '/reservar_horas')); ?>" href="<?php echo $_gh_base; ?>/solicitud_horas.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
            Solicitar hora
          </a>
        </div>
      </div><!-- /MI ESPACIO -->

      <?php if ($_gh_horas): ?>
      <!-- ─── HORAS ─── -->
      <div class="gh-group<?php echo _gh_active(array('/admin/horas_')); ?>">
        <button class="gh-nav-btn" type="button">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          Horas
          <svg class="gh-chev" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="gh-drop">
          <a class="gh-drop-item<?php echo _gh_active(array('/horas_dashboard')); ?>" href="<?php echo $_gh_base; ?>/admin/horas_dashboard.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Panel de horas
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/horas_solicitudes')); ?>" href="<?php echo $_gh_base; ?>/admin/horas_solicitudes.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            Solicitudes
          </a>
          <div class="gh-drop-sep"></div>
          <div class="gh-drop-label">Configurar</div>
          <a class="gh-drop-item<?php echo _gh_active(array('/horas_config')); ?>" href="<?php echo $_gh_base; ?>/admin/horas_config.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="3"/>
              <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
            Reglas de cupos
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/horas_excepciones')); ?>" href="<?php echo $_gh_base; ?>/admin/horas_excepciones.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
              <line x1="12" y1="15" x2="12" y2="15" stroke-width="3"/>
            </svg>
            Excepciones por fecha
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/horas_generar')); ?>" href="<?php echo $_gh_base; ?>/admin/horas_generar_slots.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <polygon points="5 3 19 12 5 21 5 3"/>
            </svg>
            Generar slots
          </a>
        </div>
      </div><!-- /HORAS -->
      <?php endif; ?>

      <?php if ($_gh_is_admin): ?>
      <!-- ─── ADMIN ─── -->
      <div class="gh-group gh-group--admin<?php echo _gh_active(array('/admin/')); ?>">
        <button class="gh-nav-btn" type="button">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
          </svg>
          Admin
          <span class="gh-pill-amber">SA</span>
          <svg class="gh-chev" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="gh-drop gh-drop--wide">

          <!-- Panel principal -->
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/index')); ?>" href="<?php echo $_gh_base; ?>/admin/index.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Panel Admin
          </a>

          <div class="gh-drop-sep"></div>

          <!-- Crear nuevos -->
          <div class="gh-drop-label">Crear</div>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/users_form')); ?>" href="<?php echo $_gh_base; ?>/admin/users_form.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/>
            </svg>
            + Usuario
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/direcciones_form')); ?>" href="<?php echo $_gh_base; ?>/admin/direcciones_form.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            + Dirección
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/unidades_form')); ?>" href="<?php echo $_gh_base; ?>/admin/unidades_form.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="2" y="7" width="20" height="14" rx="2"/>
              <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
            + Unidad
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/cargos_form')); ?>" href="<?php echo $_gh_base; ?>/admin/cargos_form.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/>
            </svg>
            + Cargo
          </a>

          <div class="gh-drop-sep"></div>

          <!-- Gestionar listados -->
          <div class="gh-drop-label">Gestionar</div>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/direcciones_list')); ?>" href="<?php echo $_gh_base; ?>/admin/direcciones_list.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            Direcciones
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/unidades_list')); ?>" href="<?php echo $_gh_base; ?>/admin/unidades_list.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            Unidades
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/cargos_list')); ?>" href="<?php echo $_gh_base; ?>/admin/cargos_list.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
            Cargos
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/admin/rol_permisos')); ?>" href="<?php echo $_gh_base; ?>/admin/rol_permisos.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>
            </svg>
            Roles y permisos
          </a>

          <div class="gh-drop-sep"></div>

          <!-- Marcaciones -->
          <div class="gh-drop-label">Marcaciones</div>
          <a class="gh-drop-item<?php echo _gh_active(array('/marcaciones/importar')); ?>" href="<?php echo $_gh_base; ?>/admin/marcaciones/importar.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
              <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
            </svg>
            Importar marcaciones
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/marcaciones/index')); ?>" href="<?php echo $_gh_base; ?>/admin/marcaciones/index.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Control de marcaciones
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/marcaciones/imports')); ?>" href="<?php echo $_gh_base; ?>/admin/marcaciones/imports.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            Planillas subidas
          </a>
          <a class="gh-drop-item<?php echo _gh_active(array('/marcaciones/sin_match')); ?>" href="<?php echo $_gh_base; ?>/admin/marcaciones/sin_match.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Sin match
          </a>

        </div>
      </div><!-- /ADMIN -->
      <?php endif; ?>

    </nav>
    <?php endif; ?>

    <!-- ── Derecha: perfil + salir + hamburger ─────────────── -->
    <div class="gh-right">
      <?php if ($_gh_logged): ?>
        <div class="gh-chip">
          <div class="gh-chip-av"><?php echo _gh_h(_gh_ini($_gh_nombre)); ?></div>
          <div class="gh-chip-info">
            <span class="gh-chip-name"><?php echo _gh_h($_gh_nombre); ?></span>
            <?php if ($_gh_is_admin): ?>
              <span class="gh-pill-amber" style="margin-left:5px;">ADMIN</span>
            <?php endif; ?>
          </div>
        </div>
        <a class="gh-logout" href="<?php echo $_gh_base; ?>/logout.php" title="Cerrar sesión">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          <span class="gh-logout-txt">Salir</span>
        </a>
      <?php else: ?>
        <a class="gh-login-btn" href="<?php echo $_gh_base; ?>/login.php">Iniciar sesión</a>
      <?php endif; ?>

      <button class="gh-hamburger" id="ghHamburger" aria-label="Menú" type="button">
        <span></span><span></span><span></span>
      </button>
    </div>

  </div><!-- /gh-inner -->

  <!-- ── Menú móvil ─────────────────────────────────────────── -->
  <?php if ($_gh_logged): ?>
  <div class="gh-mob-menu" id="ghMobMenu">

    <!-- Mi Espacio -->
    <div class="gh-mob-sect">
      <div class="gh-mob-label">Mi Espacio</div>
      <a class="gh-mob-item<?php echo _gh_active(array('/actividades_form')); ?>"     href="<?php echo $_gh_base; ?>/actividades_form.php">+ Registrar actividad</a>
      <a class="gh-mob-item<?php echo _gh_active(array('/actividades_list')); ?>"     href="<?php echo $_gh_base; ?>/actividades_list.php">Actividades de hoy</a>
      <a class="gh-mob-item<?php echo _gh_active(array('/marcaciones/mi')); ?>"       href="<?php echo $_gh_base; ?>/marcaciones/mi.php">Mis marcaciones</a>
      <a class="gh-mob-item<?php echo _gh_active(array('/solicitud_horas', '/horarios_horas')); ?>" href="<?php echo $_gh_base; ?>/solicitud_horas.php">Solicitar hora</a>
    </div>

    <!-- Horas (condicional) -->
    <?php if ($_gh_horas): ?>
    <div class="gh-mob-sect">
      <div class="gh-mob-label">Horas</div>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/horas_dashboard.php">Panel de horas</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/horas_solicitudes.php">Solicitudes</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/horas_config.php">Reglas de cupos</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/horas_excepciones.php">Excepciones por fecha</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/horas_generar_slots.php">Generar slots</a>
    </div>
    <?php endif; ?>

    <!-- Admin (condicional) -->
    <?php if ($_gh_is_admin): ?>
    <div class="gh-mob-sect">
      <div class="gh-mob-label">Admin</div>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/index.php">⚙️ Panel Admin</a>
    </div>
    <div class="gh-mob-sect">
      <div class="gh-mob-label">Crear</div>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/users_form.php">+ Usuario</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/direcciones_form.php">+ Dirección</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/unidades_form.php">+ Unidad</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/cargos_form.php">+ Cargo</a>
    </div>
    <div class="gh-mob-sect">
      <div class="gh-mob-label">Gestionar</div>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/direcciones_list.php">Direcciones</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/unidades_list.php">Unidades</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/cargos_list.php">Cargos</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/rol_permisos.php">Roles y permisos</a>
    </div>
    <div class="gh-mob-sect">
      <div class="gh-mob-label">Marcaciones</div>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/marcaciones/importar.php">Importar marcaciones</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/marcaciones/index.php">Control de marcaciones</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/marcaciones/imports.php">Planillas subidas</a>
      <a class="gh-mob-item" href="<?php echo $_gh_base; ?>/admin/marcaciones/sin_match.php">Sin match</a>
    </div>
    <?php endif; ?>

    <div class="gh-mob-sect">
      <a class="gh-mob-item is-danger" href="<?php echo $_gh_base; ?>/logout.php">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Cerrar sesión
      </a>
    </div>

  </div>
  <?php endif; ?>

</header>

<script>
/* ── Header interactivity (PHP 5.6 safe, no ES6) ─────────── */
(function () {
  'use strict';

  var hamburger = document.getElementById('ghHamburger');
  var mobMenu   = document.getElementById('ghMobMenu');
  var groups    = document.querySelectorAll('.gh-group');
  var i;

  /* Hamburger → menú móvil */
  if (hamburger && mobMenu) {
    hamburger.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = mobMenu.classList.toggle('is-open');
      hamburger.classList.toggle('is-open', open);
    });
  }

  /* Dropdowns escritorio → clic en botón */
  function closeAll() {
    for (var j = 0; j < groups.length; j++) groups[j].classList.remove('is-open');
  }

  function attachGroup(g) {
    var btn = g.querySelector('.gh-nav-btn');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var wasOpen = g.classList.contains('is-open');
      closeAll();
      if (!wasOpen) g.classList.add('is-open');
    });
  }

  for (i = 0; i < groups.length; i++) attachGroup(groups[i]);

  /* Clic fuera → cerrar todo */
  document.addEventListener('click', function () {
    closeAll();
    if (mobMenu) {
      mobMenu.classList.remove('is-open');
      if (hamburger) hamburger.classList.remove('is-open');
    }
  });

  /* Escape → cerrar todo */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      closeAll();
      if (mobMenu) {
        mobMenu.classList.remove('is-open');
        if (hamburger) hamburger.classList.remove('is-open');
      }
    }
  });
})();
</script>