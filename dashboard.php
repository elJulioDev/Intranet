<?php
// intranet/dashboard.php (PHP 5.6)
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

/* ── Helpers ── */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fechaLarga($f) {
  $dias  = array('domingo','lunes','martes','miércoles','jueves','viernes','sábado');
  $meses = array(1=>'enero','febrero','marzo','abril','mayo','junio',
                 'julio','agosto','septiembre','octubre','noviembre','diciembre');
  $ts = strtotime($f);
  return $dias[date('w',$ts)] . ', ' . date('j',$ts) . ' de ' . $meses[(int)date('n',$ts)] . ' de ' . date('Y',$ts);
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
$cntComp  = 0; $cntPend = 0;
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
  <title>Intranet · Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="static/css/theme.css">
  <link rel="stylesheet" href="static/css/sidebar.css">
  <link rel="stylesheet" href="static/css/dashboard.css">
  <link rel="icon" type="image/x-icon" href="static/img/logo.png">
</head>
<body>

<div class="app-shell">

  <?php require __DIR__ . '/inc/sidebar.php'; ?>
  <!-- sidebar.php abre .body-layout e incluye el aside.sidebar -->
    <main class="main-content">
      <div class="page-wrapper">

        <!-- ── Encabezado de página ──────────────────────── -->
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

        <!-- ── Stats ─────────────────────────────────────── -->
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

        <!-- ── Actividades del día ────────────────────────── -->
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
            <div style="overflow-x:auto;">
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
                      <td>
                        <span class="status-pill <?php echo estadoClass($a['estado']); ?>">
                          <?php echo h($a['estado']); ?>
                        </span>
                      </td>
                      <td>
                        <span class="<?php echo prioClass($a['prioridad']); ?>">
                          <?php echo h($a['prioridad']); ?>
                        </span>
                      </td>
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
  </div><!-- /body-layout (opened by sidebar.php) -->
</div><!-- /app-shell -->

</body>
</html>