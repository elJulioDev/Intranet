<?php
// intranet/dashboard.php (PHP 5.6) — con header global
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

$fid    = current_user_id();
$nombre = !empty($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$hoy    = date('Y-m-d');

$esAdmin = function_exists('is_superadmin') && is_superadmin();

/* Actividades del día */
$misActividades = array();
try {
  $stmt = $pdo->prepare("
    SELECT id, hora, titulo, estado, prioridad, referencia
    FROM actividad_registro
    WHERE funcionario_id = ? AND fecha = ?
    ORDER BY hora ASC, id ASC
    LIMIT 20
  ");
  $stmt->execute(array($fid, $hoy));
  $misActividades = $stmt->fetchAll();
} catch (Exception $e) { $misActividades = array(); }

/* Cargo vigente */
$cargoVigente = null;
try {
  $stmt = $pdo->prepare("
    SELECT fc.tipo, fc.fecha_desde, fc.fecha_hasta,
           c.nombre AS cargo_nombre,
           u.nombre AS unidad_nombre,
           d.nombre AS direccion_nombre
    FROM funcionario_cargos fc
    JOIN cargos c       ON c.id = fc.cargo_id
    JOIN unidades u     ON u.id = c.unidad_id
    JOIN direcciones d  ON d.id = u.direccion_id
    WHERE fc.funcionario_id = ?
      AND fc.activo = 1
      AND fc.fecha_desde <= CURDATE()
      AND (fc.fecha_hasta IS NULL OR fc.fecha_hasta >= CURDATE())
    ORDER BY (fc.tipo='titular') DESC, fc.fecha_desde DESC
    LIMIT 1
  ");
  $stmt->execute(array($fid));
  $cargoVigente = $stmt->fetch();
} catch (Exception $e) { $cargoVigente = null; }

/* ── Helpers ── */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fechaLarga($f) {
  $dias  = array('domingo','lunes','martes','miércoles','jueves','viernes','sábado');
  $meses = array(1=>'enero','febrero','marzo','abril','mayo','junio',
                 'julio','agosto','septiembre','octubre','noviembre','diciembre');
  $ts = strtotime($f);
  return $dias[date('w',$ts)] . ', ' . date('j',$ts) . ' de ' . $meses[(int)date('n',$ts)] . ' de ' . date('Y',$ts);
}
function initials($n) {
  $w = array_filter(explode(' ', strtoupper(trim($n))));
  $o = '';
  foreach ($w as $x) { $o .= $x[0]; if (strlen($o) === 2) break; }
  return $o ?: '?';
}
function estadoClass($e) {
  $m = array('completada'=>'status-done','completado'=>'status-done','done'=>'status-done',
             'pendiente'=>'status-pending','pending'=>'status-pending',
             'en curso'=>'status-active','activo'=>'status-active','active'=>'status-active',
             'cancelada'=>'status-cancel','cancelado'=>'status-cancel');
  return isset($m[strtolower(trim($e))]) ? $m[strtolower(trim($e))] : 'status-none';
}
function prioClass($p) {
  $m = array('alta'=>'prio-high','high'=>'prio-high','media'=>'prio-medium','medium'=>'prio-medium',
             'baja'=>'prio-low','low'=>'prio-low');
  return isset($m[strtolower(trim($p))]) ? $m[strtolower(trim($p))] : 'prio-other';
}

/* Contadores */
$cntTotal = count($misActividades);
$cntComp = 0; $cntPend = 0;
foreach ($misActividades as $a) {
  $e = strtolower(trim($a['estado']));
  if (in_array($e, array('completada','completado','done'))) $cntComp++;
  if (in_array($e, array('pendiente','pending')))            $cntPend++;
}
$cntCurso = max(0, $cntTotal - $cntComp - $cntPend);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Intranet | Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="static/css/theme.css">
  <link rel="stylesheet" href="static/css/dashboard.css">
  <link rel="icon" type="image/x-icon" href="static/img/logo.png">
</head>
<body>

<div class="app-shell">

  <!-- ══ HEADER GLOBAL (reemplaza la topbar manual) ══════════ -->
  <?php require __DIR__ . '/inc/header.php'; ?>
  <!-- ════════════════════════════════════════════════════════ -->

  <div class="body-layout">

    <div class="sidebar-overlay is-hidden" id="sidebarOverlay"></div>

    <!-- Sidebar: solo en dashboard, complementa el header global -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-scroll">

        <div class="profile-block">
          <div class="profile-row">
            <div class="avatar"><?php echo h(initials($nombre)); ?></div>
            <div class="profile-info">
              <div class="profile-name"><?php echo h($nombre); ?></div>
              <div class="profile-rut"><?php echo h($_SESSION['rut'] ?? ''); ?></div>
            </div>
          </div>
          <div class="profile-badges">
            <?php if ($cargoVigente): ?>
              <span class="badge badge-blue"><?php echo h(strtoupper($cargoVigente['tipo'])); ?></span>
            <?php else: ?>
              <span class="badge badge-gray">SIN CARGO</span>
            <?php endif; ?>
            <span class="badge <?php echo $esAdmin ? 'badge-amber' : 'badge-gray'; ?>">
              <?php echo $esAdmin ? 'ADMIN' : 'USUARIO'; ?>
            </span>
          </div>
        </div>

        <?php if ($cargoVigente): ?>
        <div class="cargo-block">
          <div class="cargo-label">Cargo vigente</div>
          <div class="cargo-role"><?php echo h($cargoVigente['cargo_nombre']); ?></div>
          <div class="cargo-dir"><?php echo h($cargoVigente['direccion_nombre']); ?></div>
          <div class="cargo-unit"><?php echo h($cargoVigente['unidad_nombre']); ?></div>
          <div class="cargo-dates">
            <?php echo h($cargoVigente['fecha_desde']); ?>
            <?php echo $cargoVigente['fecha_hasta'] ? ' → '.h($cargoVigente['fecha_hasta']) : ' → vigente'; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="cargo-block">
          <div class="cargo-label">Cargo vigente</div>
          <div class="cargo-unit" style="color:var(--text-subtle);font-style:italic;font-size:12px;">Sin cargo asignado.</div>
        </div>
        <?php endif; ?>

        <nav class="nav-section section-personal">
          <div class="nav-section-label">Mi espacio</div>
          <a class="nav-item" href="actividades_form.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Registrar actividad
          </a>
          <a class="nav-item is-active" href="actividades_list.php?fecha=<?php echo h($hoy); ?>">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Actividades de hoy
          </a>
          <a class="nav-item" href="marcaciones/mi.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Mis marcaciones
          </a>
          <a class="nav-item" href="solicitud_horas.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
            Solicitar hora
          </a>
        </nav>

        <?php if ($esAdmin): ?>
        <nav class="nav-section section-admin">
          <div class="nav-section-label">Administración</div>
          <a class="nav-item" href="admin/index.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
            </svg>
            Panel Admin
          </a>
          <div class="nav-sep"></div>
          <a class="nav-item" href="admin/users_form.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
              <line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/>
            </svg>
            Crear usuario
          </a>
          <a class="nav-item" href="admin/direcciones_list.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Direcciones
          </a>
          <a class="nav-item" href="admin/unidades_list.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="2" y="7" width="20" height="14" rx="2"/>
              <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
            Unidades
          </a>
          <a class="nav-item" href="admin/cargos_list.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/>
            </svg>
            Cargos
          </a>
          <div class="nav-sep"></div>
          <a class="nav-item" href="admin/marcaciones/importar.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
              <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
            </svg>
            Importar marcaciones
          </a>
          <a class="nav-item" href="admin/marcaciones/index.php">
            <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Control marcaciones
          </a>
        </nav>
        <?php endif; ?>

      </div>

      <div class="sidebar-footer">
        <a class="nav-item is-danger" href="logout.php">
          <svg class="nav-item-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Cerrar sesión
        </a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-wrapper">

        <div class="page-header">
          <div>
            <h1 class="page-title">
              <span class="greeting-word">Hola, </span>
              <span class="greeting-name"><?php echo h($nombre); ?></span>
            </h1>
            <div class="page-subtitle">
              <span class="date-highlight"><?php echo h(fechaLarga($hoy)); ?></span>
              &nbsp;·&nbsp; Dashboard de actividades
            </div>
          </div>
          <div class="page-actions">
            <a class="btn btn-primary btn-sm" href="actividades_form.php">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              <span>Registrar Actividad</span>
            </a>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card accent-default">
            <div class="stat-label">Total hoy</div>
            <div class="stat-value"><?php echo $cntTotal; ?></div>
            <div class="stat-sub">actividades registradas</div>
          </div>
          <div class="stat-card accent-green">
            <div class="stat-label">Completadas</div>
            <div class="stat-value"><?php echo $cntComp; ?></div>
            <div class="stat-sub">finalizadas</div>
          </div>
          <div class="stat-card accent-amber">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value"><?php echo $cntPend; ?></div>
            <div class="stat-sub">en espera</div>
          </div>
          <div class="stat-card accent-blue">
            <div class="stat-label">En curso</div>
            <div class="stat-value"><?php echo $cntCurso; ?></div>
            <div class="stat-sub">en progreso</div>
          </div>
        </div>

        <div class="activities-panel card">
          <div class="panel-header card-header">
            <div class="panel-title card-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              Mis actividades de hoy
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <span class="count-bubble"><?php echo $cntTotal; ?></span>
              <a class="btn btn-secondary btn-sm" href="actividades_list.php?fecha=<?php echo h($hoy); ?>">Ver todas</a>
            </div>
          </div>

          <?php if (empty($misActividades)): ?>
            <div class="empty-state">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              <p>No tienes actividades registradas hoy.</p>
              <a class="btn btn-primary btn-sm" href="actividades_form.php" style="margin-top:12px;display:inline-flex;">
                + Registrar primera actividad
              </a>
            </div>
          <?php else: ?>
            <div class="table-responsive" style="overflow-x:auto;">
              <table class="table">
                <thead>
                  <tr>
                    <th style="width:90px;">Hora</th>
                    <th>Título</th>
                    <th style="width:130px;">Estado</th>
                    <th style="width:110px;">Prioridad</th>
                    <th style="width:150px;">Referencia</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($misActividades as $a): ?>
                    <tr>
                      <td class="td-mono"><?php echo h($a['hora']); ?></td>
                      <td><?php echo h($a['titulo']); ?></td>
                      <td><span class="status-pill <?php echo estadoClass($a['estado']); ?>"><?php echo h($a['estado']); ?></span></td>
                      <td><span class="<?php echo prioClass($a['prioridad']); ?>"><?php echo h($a['prioridad']); ?></span></td>
                      <td class="td-mono"><?php echo h($a['referencia']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </main>

  </div>
</div>

<script>
/* Sidebar toggle en móvil — accionado desde el header global */
(function () {
  /* En escritorio la sidebar es fija. En móvil se puede abrir
     desde el sidebar-overlay (tap fuera = cerrar).            */
  var overlay = document.getElementById('sidebarOverlay');
  var sidebar = document.getElementById('sidebar');

  if (overlay) {
    overlay.addEventListener('click', function () {
      if (sidebar) sidebar.classList.remove('is-open');
      overlay.classList.add('is-hidden');
    });
  }
})();
</script>

</body>
</html>