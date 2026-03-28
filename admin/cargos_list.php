<?php
// intranet/admin/cargos_list.php (PHP 5.6) — Responsive: tabla + cards
require __DIR__ . '/_guard.php';

$direccionId = isset($_GET['direccion_id']) ? (int)$_GET['direccion_id'] : 0;
$unidadId    = isset($_GET['unidad_id'])    ? (int)$_GET['unidad_id']    : 0;
$q           = isset($_GET['q'])            ? trim((string)$_GET['q'])   : '';
$msg         = '';
$err         = '';

// ── Toggle activo ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
  csrf_check();
  $tid = (int)$_POST['toggle_id'];
  try {
    $st = $pdo->prepare("UPDATE cargos SET activo = IF(activo=1,0,1) WHERE id=?");
    $st->execute(array($tid));
    $msg = 'Estado actualizado.';
  } catch (Exception $e) {
    $msg = 'No se pudo actualizar: ' . $e->getMessage();
  }
}

// ── Direcciones para filtro ────────────────────────────────────────────────
$direcciones = array();
try {
  $direcciones = $pdo->query(
    "SELECT id, codigo, nombre FROM direcciones WHERE activo=1 ORDER BY codigo, nombre"
  )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $direcciones = array();
}

// ── Unidades para filtro ───────────────────────────────────────────────────
// Se cargan siempre. Si hay dirección seleccionada, se filtra por ella.
$unidades = array();
try {
  if ($direccionId > 0) {
    $st = $pdo->prepare(
      "SELECT u.id, u.nombre, d.codigo AS dir_codigo
       FROM unidades u
       JOIN direcciones d ON d.id = u.direccion_id
       WHERE u.activo=1 AND u.direccion_id=?
       ORDER BY u.nombre"
    );
    $st->execute(array($direccionId));
  } else {
    $st = $pdo->query(
      "SELECT u.id, u.nombre, d.codigo AS dir_codigo
       FROM unidades u
       JOIN direcciones d ON d.id = u.direccion_id
       WHERE u.activo=1
       ORDER BY d.codigo, u.nombre"
    );
  }
  $unidades = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $unidades = array();
}

// ── Query principal ────────────────────────────────────────────────────────
$params = array();
$sql = "
  SELECT
    c.id,
    c.nombre     AS cargo_nombre,
    c.activo     AS cargo_activo,
    u.id         AS unidad_id,
    u.nombre     AS unidad_nombre,
    d.id         AS direccion_id,
    d.codigo     AS dir_codigo,
    d.nombre     AS dir_nombre
  FROM cargos c
  JOIN unidades    u ON u.id = c.unidad_id
  JOIN direcciones d ON d.id = u.direccion_id
  WHERE 1=1
";

if ($direccionId > 0) {
  $sql    .= " AND d.id = ?";
  $params[] = $direccionId;
}
if ($unidadId > 0) {
  $sql    .= " AND u.id = ?";
  $params[] = $unidadId;
}
if ($q !== '') {
  $sql    .= " AND (c.nombre LIKE ? OR u.nombre LIKE ? OR d.nombre LIKE ? OR d.codigo LIKE ?)";
  $like     = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}
$sql .= " ORDER BY d.codigo, u.nombre, c.nombre";

$rows = array();
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = $e->getMessage();
}

// ── Contadores ─────────────────────────────────────────────────────────────
$totalActivos   = 0;
$totalInactivos = 0;
foreach ($rows as $r) {
  if ((int)$r['cargo_activo'] === 1) $totalActivos++; else $totalInactivos++;
}

$hayFiltro = ($direccionId > 0 || $unidadId > 0 || $q !== '');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Cargos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../static/css/theme.css">
  <link rel="stylesheet" href="../static/css/sidebar.css">
  <link rel="stylesheet" href="../static/css/form.css">
  <link rel="icon" type="image/x-icon" href="../static/img/logo.png">
  <style>
    .ls-page { padding: 28px 36px 64px; width: 100%; box-sizing: border-box; font-family: var(--font-sans); }

    /* ── Heading ── */
    .ls-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .ls-heading-left { display: flex; align-items: center; gap: 14px; }
    .ls-heading-icon { width: 48px; height: 48px; border-radius: var(--r-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ls-heading-text h1 { font-size: clamp(20px,2vw,28px); font-weight: 700; color: var(--text-primary); letter-spacing: -.3px; line-height: 1.2; margin: 0 0 4px; }
    .ls-heading-text p  { font-size: 13px; color: var(--text-muted); margin: 0; }

    /* ── Stats ── */
    .ls-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .ls-stat { display: inline-flex; align-items: center; gap: 7px; padding: 5px 12px; border-radius: 999px; background: var(--surface-1); border: 1px solid var(--border-1); font-size: 12px; font-weight: 500; color: var(--text-muted); }
    .ls-stat-value { font-family: var(--font-mono); font-weight: 700; }
    .ls-stat.accent-blue   .ls-stat-value { color: var(--blue); }
    .ls-stat.accent-green  .ls-stat-value { color: var(--green); }
    .ls-stat.accent-amber  .ls-stat-value { color: var(--amber); }
    .ls-stat.accent-purple .ls-stat-value { color: var(--purple); }

    /* ── Filter ── */
    .ls-filter { background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r-lg); overflow: hidden; margin-bottom: 16px; }
    .ls-filter-header { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-bottom: 1px solid var(--border-1); background: var(--surface-2); }
    .ls-filter-icon { width: 26px; height: 26px; border-radius: var(--r); background: var(--purple-subtle); border: 1px solid var(--purple-border); display: flex; align-items: center; justify-content: center; color: var(--purple); flex-shrink: 0; }
    .ls-filter-title { font-size: 12px; font-weight: 600; color: var(--text-primary); }
    .ls-filter-body { padding: 12px 16px; display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
    .ls-filter-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 160px; }
    .ls-filter-label { font-size: 11px; font-weight: 600; color: var(--text-subtle); }
    .ls-search-input,
    .ls-select-input { width: 100%; background: var(--bg); border: 1px solid var(--border-3); border-radius: var(--r-md); padding: 8px 12px; font-family: var(--font-sans); font-size: 14px; color: var(--text-primary); outline: none; box-sizing: border-box; transition: border-color var(--t-fast), box-shadow var(--t-fast); -webkit-appearance: none; }
    .ls-select-input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
    .ls-search-input:focus,
    .ls-select-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(163,113,247,.15); }
    .ls-search-input::placeholder { color: var(--text-subtle); }
    .ls-filter-actions { display: flex; gap: 8px; align-items: flex-end; flex-shrink: 0; }
    .ls-select-hint { font-size: 11px; color: var(--text-subtle); margin-top: 3px; }
    
    /* ── Hint de unidades vacío ── */
    .ls-select-hint { font-size: 11px; color: var(--text-subtle); margin-top: 3px; }

    /* ── Table card ── */
    .ls-table-card { background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r-lg); overflow: hidden; }
    .ls-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .ls-table th { padding: 9px 14px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--text-subtle); background: var(--surface-2); border-bottom: 1px solid var(--border-1); white-space: nowrap; }
    .ls-table tbody td { padding: 11px 14px; color: var(--text-primary); border-bottom: 1px solid var(--border-2); vertical-align: middle; }
    .ls-table tbody tr:last-child td { border-bottom: none; }
    .ls-table tbody tr:hover td { background: var(--surface-2); }
    .ls-table .td-id   { font-family: var(--font-mono); font-size: 12px; color: var(--text-subtle); white-space: nowrap; }
    .ls-table .td-name { word-break: break-word; }
    .td-actions { display: flex; gap: 4px; align-items: center; white-space: nowrap; }

    /* ── Badges jerárquicos ── */
    .ls-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; font-family: var(--font-mono); letter-spacing: .3px; white-space: nowrap; }
    .ls-badge-dir  { background: var(--amber-subtle);  border: 1px solid var(--amber-border);  color: var(--amber); }
    .ls-badge-unit { background: var(--blue-subtle);   border: 1px solid var(--blue-border);   color: var(--blue); }

    /* ── Celda unidad + dirección (tabla) ── */
    .td-unit-cell { display: flex; flex-direction: column; gap: 4px; min-width: 160px; }
    .td-unit-name { font-size: 13px; color: var(--text-primary); }
    .td-dir-line  { display: flex; align-items: center; gap: 5px; }
    .td-dir-name  { font-size: 11px; color: var(--text-subtle); }

    /* ── Action buttons ── */
    .ls-action-btn { display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 4px 10px; border-radius: var(--r); border: 1px solid var(--border-1); background: var(--surface-2); color: var(--text-muted); cursor: pointer; transition: all var(--t-fast); text-decoration: none; font-family: var(--font-sans); font-size: 11px; font-weight: 500; flex-shrink: 0; line-height: 1; white-space: nowrap; }
    .ls-action-btn:hover { text-decoration: none; }
    .ls-action-btn svg { flex-shrink: 0; width: 13px; height: 13px; }
    .ls-action-btn.is-edit { border-color: var(--blue-border); background: var(--blue-subtle); color: var(--blue); }
    .ls-action-btn.is-edit:hover { background: rgba(56,139,253,.3); }
    .ls-action-btn.is-deactivate:hover { background: var(--red-subtle); border-color: var(--red-border); color: var(--red); }
    .ls-action-btn.is-activate:hover  { background: var(--green-subtle); border-color: var(--green-border); color: var(--green); }

    /* ── Status pill ── */
    .ls-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    .ls-pill.active   { background: var(--green-subtle); border: 1px solid var(--green-border); color: var(--green); }
    .ls-pill.inactive { background: var(--surface-3); border: 1px solid var(--border-1); color: var(--text-subtle); }
    .ls-pill-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }

    /* ── Cards (móvil) ── */
    .ls-cards { display: none; }
    .ls-card { background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r-lg); padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
    .ls-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .ls-card-top-left { display: flex; flex-direction: column; gap: 6px; min-width: 0; flex: 1; }
    .ls-card-name { font-size: 14px; font-weight: 600; color: var(--text-primary); line-height: 1.3; }
    .ls-card-badges { display: flex; gap: 5px; flex-wrap: wrap; }
    .ls-card-dir-full { font-size: 12px; color: var(--text-subtle); }
    .ls-card-id { font-family: var(--font-mono); font-size: 11px; color: var(--text-subtle); background: var(--surface-3); padding: 2px 7px; border-radius: 999px; flex-shrink: 0; }
    .ls-card-bottom { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 8px; border-top: 1px solid var(--border-2); }
    .ls-card-actions { display: flex; gap: 6px; }

    /* ── Empty ── */
    .ls-empty { padding: 48px 24px; text-align: center; color: var(--text-subtle); background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r-lg); }
    .ls-empty svg { margin: 0 auto 12px; opacity: .25; display: block; }
    .ls-empty p   { font-size: 13px; color: var(--text-muted); }

    /* ══ Responsive ══ */
    @media (min-width:1440px) { .ls-page { padding: 36px 56px 80px; } .ls-heading-icon { width: 56px; height: 56px; } }
    @media (min-width:1920px) { .ls-page { padding: 48px 80px 96px; } .ls-heading-icon { width: 64px; height: 64px; } }
    @media (max-width:1100px) { .ls-page { padding: 24px 28px 56px; } }

    @media (max-width:800px) {
      .main-content { overflow: visible; align-items: stretch; }
      .ls-page { padding: 20px 16px 48px; }
      .ls-heading-text h1 { font-size: 18px; }
      .ls-heading-text p  { display: none; }
      .ls-heading { flex-direction: column; gap: 10px; }
      .ls-heading-icon { width: 40px; height: 40px; }
      .ls-table-card { display: none; }
      .ls-cards { display: flex; flex-direction: column; gap: 10px; }
      .ls-filter-body { flex-direction: column; align-items: stretch; gap: 12px; }
      .ls-filter-field { min-width: unset; }
      .ls-filter-actions { width: 100%; }
      .ls-filter-actions .btn { flex: 1; justify-content: center; }
      .ls-action-btn { padding: 6px 12px; font-size: 12px; }
      .ls-action-btn svg { width: 14px; height: 14px; }
    }
    @media (max-width:480px) {
      .ls-page { padding: 14px 12px 40px; }
      .ls-heading-icon { display: none; }
      .ls-stats { gap: 6px; }
      .ls-stat { padding: 4px 10px; font-size: 11px; }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <?php require __DIR__ . '/../inc/sidebar.php'; ?>
  <main class="main-content">
    <div class="ls-page">

      <!-- ════ Heading ════ -->
      <div class="ls-heading">
        <div class="ls-heading-left">
          <div class="ls-heading-icon" style="background:var(--blue-subtle);border:1px solid var(--blue-border);color:var(--blue);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
          </div>
          <div class="ls-heading-text">
            <h1>Cargos</h1>
            <p>Listado de cargos organizacionales registrados por unidad y dirección.</p>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
          <a class="btn btn-primary btn-sm" href="cargos_form.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo cargo
          </a>
        </div>
      </div>

      <!-- ════ Alertas ════ -->
      <?php if ($msg): ?>
      <div class="uf-alert uf-alert-success" style="margin-bottom:16px;">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm3.03 3.97-3.78 5.03-1.78-1.78-1.06 1.06 2.5 2.5.53.53.53-.7 4.32-5.75-1.26-.89z"/></svg>
        <?php echo _sb_h($msg); ?>
      </div>
      <?php endif; ?>
      <?php if ($err): ?>
      <div class="uf-alert uf-alert-error" style="margin-bottom:16px;">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/></svg>
        <?php echo _sb_h($err); ?>
      </div>
      <?php endif; ?>

      <!-- ════ Stats ════ -->
      <div class="ls-stats">
        <div class="ls-stat accent-blue">
          <span>Total</span>
          <span class="ls-stat-value"><?php echo count($rows); ?></span>
        </div>
        <div class="ls-stat accent-green">
          <span>Activos</span>
          <span class="ls-stat-value"><?php echo $totalActivos; ?></span>
        </div>
        <?php if ($totalInactivos > 0): ?>
        <div class="ls-stat accent-amber">
          <span>Inactivos</span>
          <span class="ls-stat-value"><?php echo $totalInactivos; ?></span>
        </div>
        <?php endif; ?>
        <?php if ($hayFiltro): ?>
        <div class="ls-stat">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          <span>Filtro activo</span>
        </div>
        <?php endif; ?>
      </div>

      <!-- ════ Filtro triple ════ -->
      <div class="ls-filter">
        <div class="ls-filter-header">
          <div class="ls-filter-icon">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          </div>
          <span class="ls-filter-title">Filtrar cargos</span>
        </div>
        <form method="get" class="ls-filter-body" id="filterForm">

          <!-- Selector Dirección (auto-submit al cambiar) -->
          <div class="ls-filter-field">
            <label class="ls-filter-label">Dirección</label>
            <select class="ls-select-input" name="direccion_id" id="selDireccion">
              <option value="0">— Todas las direcciones —</option>
              <?php foreach ($direcciones as $d): ?>
                <option value="<?php echo (int)$d['id']; ?>" <?php echo ($direccionId === (int)$d['id'] ? 'selected' : ''); ?>>
                  <?php echo _sb_h($d['codigo'] . ' · ' . $d['nombre']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Selector Unidad (siempre activo; muestra código dirección como contexto) -->
          <div class="ls-filter-field">
            <label class="ls-filter-label">Unidad</label>
            <?php if (!empty($unidades)): ?>
              <select class="ls-select-input" name="unidad_id">
                <option value="0">— Todas las unidades —</option>
                <?php foreach ($unidades as $u): ?>
                  <option value="<?php echo (int)$u['id']; ?>" <?php echo ($unidadId === (int)$u['id'] ? 'selected' : ''); ?>>
                    <?php if ($direccionId === 0): ?>
                      <?php echo _sb_h('[' . $u['dir_codigo'] . '] ' . $u['nombre']); ?>
                    <?php else: ?>
                      <?php echo _sb_h($u['nombre']); ?>
                    <?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <select class="ls-select-input" disabled>
                <option>Sin unidades activas</option>
              </select>
              <input type="hidden" name="unidad_id" value="0">
            <?php endif; ?>
          </div>

          <!-- Campo texto -->
          <div class="ls-filter-field">
            <label class="ls-filter-label">Nombre de cargo</label>
            <input class="ls-search-input" name="q" value="<?php echo _sb_h($q); ?>" placeholder="Ej: Jefe / Encargado / Administrativo...">
          </div>

          <div class="ls-filter-actions">
            <button class="btn btn-secondary btn-sm" type="submit">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              Filtrar
            </button>
            <?php if ($hayFiltro): ?>
              <a class="btn btn-secondary btn-sm" href="cargos_list.php">Limpiar</a>
            <?php endif; ?>
          </div>

        </form>
      </div>

      <!-- ════ Contenido ════ -->
      <?php if (empty($rows)): ?>
        <div class="ls-empty">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <circle cx="12" cy="8" r="4"/>
            <path d="M6 20v-2a6 6 0 0 1 12 0v2"/>
          </svg>
          <p>No se encontraron cargos<?php echo $hayFiltro ? ' con ese filtro' : ''; ?>.</p>
        </div>
      <?php else: ?>

        <!-- ── Tabla (desktop) ── -->
        <div class="ls-table-card">
          <table class="ls-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Cargo</th>
                <th>Unidad &amp; Dirección</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <tr id="row-<?php echo (int)$r['id']; ?>">
                <td class="td-id"><?php echo (int)$r['id']; ?></td>
                <td class="td-name">
                  <?php echo _sb_h($r['cargo_nombre']); ?>
                </td>
                <td>
                  <div class="td-unit-cell">
                    <span class="td-unit-name"><?php echo _sb_h($r['unidad_nombre']); ?></span>
                    <div class="td-dir-line">
                      <span class="ls-badge ls-badge-dir"><?php echo _sb_h($r['dir_codigo']); ?></span>
                      <span class="td-dir-name"><?php echo _sb_h($r['dir_nombre']); ?></span>
                    </div>
                  </div>
                </td>
                <td>
                  <?php if ((int)$r['cargo_activo'] === 1): ?>
                    <span class="ls-pill active"><span class="ls-pill-dot"></span>Activo</span>
                  <?php else: ?>
                    <span class="ls-pill inactive"><span class="ls-pill-dot"></span>Inactivo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="td-actions">
                    <a class="ls-action-btn is-edit" href="cargos_form.php?id=<?php echo (int)$r['id']; ?>#row-<?php echo (int)$r['id']; ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      Editar
                    </a>
                    <form method="post" action="cargos_list.php<?php echo $hayFiltro ? '?direccion_id='.$direccionId.'&unidad_id='.$unidadId.'&q='.urlencode($q) : ''; ?>#row-<?php echo (int)$r['id']; ?>">
                      <input type="hidden" name="csrf" value="<?php echo _sb_h(csrf_token()); ?>">
                      <input type="hidden" name="toggle_id" value="<?php echo (int)$r['id']; ?>">
                      <?php if ((int)$r['cargo_activo'] === 1): ?>
                        <button class="ls-action-btn is-deactivate" type="submit">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                          Desactivar
                        </button>
                      <?php else: ?>
                        <button class="ls-action-btn is-activate" type="submit">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                          Activar
                        </button>
                      <?php endif; ?>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- ── Cards (móvil) ── -->
        <div class="ls-cards">
          <?php foreach ($rows as $r): ?>
            <div class="ls-card" id="card-<?php echo (int)$r['id']; ?>">
              <div class="ls-card-top">
                <div class="ls-card-top-left">
                  <span class="ls-card-name"><?php echo _sb_h($r['cargo_nombre']); ?></span>
                  <div class="ls-card-badges">
                    <span class="ls-badge ls-badge-unit"><?php echo _sb_h($r['unidad_nombre']); ?></span>
                    <span class="ls-badge ls-badge-dir"><?php echo _sb_h($r['dir_codigo']); ?></span>
                  </div>
                  <span class="ls-card-dir-full"><?php echo _sb_h($r['dir_nombre']); ?></span>
                </div>
                <span class="ls-card-id">#<?php echo (int)$r['id']; ?></span>
              </div>
              <div class="ls-card-bottom">
                <?php if ((int)$r['cargo_activo'] === 1): ?>
                  <span class="ls-pill active"><span class="ls-pill-dot"></span>Activo</span>
                <?php else: ?>
                  <span class="ls-pill inactive"><span class="ls-pill-dot"></span>Inactivo</span>
                <?php endif; ?>
                <div class="ls-card-actions">
                  <a class="ls-action-btn is-edit" href="cargos_form.php?id=<?php echo (int)$r['id']; ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Editar
                  </a>
                  <form method="post" action="cargos_list.php">
                    <input type="hidden" name="csrf" value="<?php echo _sb_h(csrf_token()); ?>">
                    <input type="hidden" name="toggle_id" value="<?php echo (int)$r['id']; ?>">
                    <?php if ((int)$r['cargo_activo'] === 1): ?>
                      <button class="ls-action-btn is-deactivate" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                        Desactivar
                      </button>
                    <?php else: ?>
                      <button class="ls-action-btn is-activate" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Activar
                      </button>
                    <?php endif; ?>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>
    </div><!-- /.ls-page -->
  </main>

  </div><!-- /body-layout (abierto por sidebar.php) -->
</div><!-- /app-shell -->

<script>
  document.addEventListener('DOMContentLoaded', function () {

    // 1. Auto-submit al cambiar dirección (recarga unidades filtradas por dirección)
    var selDir = document.getElementById('selDireccion');
    if (selDir) {
      selDir.addEventListener('change', function () {
        // Resetear unidad al cambiar dirección para evitar selección huérfana
        var form = document.getElementById('filterForm');
        if (form) {
          var selUnit = form.querySelector('[name="unidad_id"]');
          if (selUnit) selUnit.value = '0';
        }
        form.submit();
      });
    }

    // 2. Restaurar scroll exacto
    var savedScroll = sessionStorage.getItem('ls_scroll_pos');
    if (savedScroll) {
      window.scrollTo(0, parseInt(savedScroll));
      var mc = document.querySelector('.main-content');
      if (mc) mc.scrollTop = parseInt(savedScroll);
      sessionStorage.removeItem('ls_scroll_pos');
    }

    // 3. Destello visual en fila/card editada
    var editedTarget = sessionStorage.getItem('ls_edited_target');
    if (editedTarget) {
      var el = document.querySelector(editedTarget);
      if (el) {
        el.style.transition = 'background-color 0.5s ease';
        el.style.backgroundColor = 'var(--surface-2)';
        setTimeout(function () { el.style.backgroundColor = ''; }, 1500);
      }
      sessionStorage.removeItem('ls_edited_target');
    }

    // 4. Interceptar formularios toggle para guardar posición y target
    document.querySelectorAll('form').forEach(function (form) {
      // Solo interceptar formularios de toggle (los que tienen toggle_id)
      if (!form.querySelector('[name="toggle_id"]')) return;
      form.addEventListener('submit', function () {
        var currentScroll = window.scrollY
          || document.documentElement.scrollTop
          || (document.querySelector('.main-content') ? document.querySelector('.main-content').scrollTop : 0);
        sessionStorage.setItem('ls_scroll_pos', currentScroll);

        var actionUrl = this.getAttribute('action');
        if (actionUrl && actionUrl.indexOf('#') !== -1) {
          sessionStorage.setItem('ls_edited_target', actionUrl.substring(actionUrl.indexOf('#')));
          this.setAttribute('action', actionUrl.substring(0, actionUrl.indexOf('#')));
        }
      });
    });

  });
</script>

</body>
</html>