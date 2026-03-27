<?php
// intranet/admin/direcciones_list.php (PHP 5.6) — Rediseño con sidebar global
require __DIR__ . '/_guard.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$msg = '';

// Toggle activo (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
  csrf_check();
  $tid = (int)$_POST['toggle_id'];

  try {
    $st = $pdo->prepare("UPDATE direcciones SET activo = IF(activo=1,0,1) WHERE id=?");
    $st->execute(array($tid));
    $msg = 'Estado actualizado.';
  } catch (Exception $e) {
    $msg = 'No se pudo actualizar estado: '.$e->getMessage();
  }
}

// Cargar lista
$params = array();
$sql = "SELECT id, codigo, nombre, activo FROM direcciones WHERE 1=1";

if ($q !== '') {
  $sql .= " AND (codigo LIKE ? OR nombre LIKE ?)";
  $like = '%'.$q.'%';
  $params[] = $like;
  $params[] = $like;
}

$sql .= " ORDER BY codigo, nombre";

$rows = array();
$err = '';
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $rows = array();
  $err = $e->getMessage();
}

// Contadores
$totalActivas = 0;
$totalInactivas = 0;
foreach ($rows as $r) {
  if ((int)$r['activo'] === 1) $totalActivas++;
  else $totalInactivas++;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Direcciones</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../static/css/theme.css">
  <link rel="stylesheet" href="../static/css/sidebar.css">
  <link rel="stylesheet" href="../static/css/form.css">
  <link rel="icon" type="image/x-icon" href="../static/img/logo.png">
  <style>
    /* ── Estilos específicos de listado ──────────────────── */
    .ls-page {
      padding: 28px 36px 64px;
      width: 100%;
      box-sizing: border-box;
      font-family: var(--font-sans);
    }

    /* Heading */
    .ls-heading {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
    .ls-heading-left { display: flex; align-items: center; gap: 14px; }
    .ls-heading-icon {
      width: 48px; height: 48px;
      border-radius: var(--r-md);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .ls-heading-text h1 {
      font-size: clamp(20px, 2vw, 28px);
      font-weight: 700; color: var(--text-primary);
      letter-spacing: -.3px; line-height: 1.2; margin: 0 0 4px;
    }
    .ls-heading-text p { font-size: 13px; color: var(--text-muted); margin: 0; }
    .ls-heading-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

    /* Stats strip */
    .ls-stats {
      display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;
    }
    .ls-stat {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 6px 14px; border-radius: 999px;
      background: var(--surface-1); border: 1px solid var(--border-1);
      font-size: 12px; font-weight: 500; color: var(--text-muted);
    }
    .ls-stat-value { font-family: var(--font-mono); font-weight: 700; }
    .ls-stat.accent-blue .ls-stat-value { color: var(--blue); }
    .ls-stat.accent-green .ls-stat-value { color: var(--green); }
    .ls-stat.accent-amber .ls-stat-value { color: var(--amber); }

    /* Filter bar */
    .ls-filter {
      background: var(--surface-1); border: 1px solid var(--border-1);
      border-radius: var(--r-lg); overflow: hidden; margin-bottom: 16px;
    }
    .ls-filter-header {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 18px; border-bottom: 1px solid var(--border-1);
      background: var(--surface-2);
    }
    .ls-filter-icon {
      width: 26px; height: 26px; border-radius: var(--r);
      background: var(--blue-subtle); border: 1px solid var(--blue-border);
      display: flex; align-items: center; justify-content: center;
      color: var(--blue); flex-shrink: 0;
    }
    .ls-filter-title { font-size: 12px; font-weight: 600; color: var(--text-primary); }
    .ls-filter-body {
      padding: 14px 18px;
      display: flex; gap: 10px; align-items: end; flex-wrap: wrap;
    }
    .ls-filter-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 200px; }
    .ls-filter-label { font-size: 11px; font-weight: 600; color: var(--text-subtle); }
    .ls-search-input {
      width: 100%; background: var(--bg); border: 1px solid var(--border-3);
      border-radius: var(--r-md); padding: 8px 12px; font-family: var(--font-sans);
      font-size: 13px; color: var(--text-primary); outline: none;
      transition: border-color var(--t-fast), box-shadow var(--t-fast);
    }
    .ls-search-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px var(--blue-subtle); }
    .ls-search-input::placeholder { color: var(--text-subtle); }

    /* Table card */
    .ls-table-card {
      background: var(--surface-1); border: 1px solid var(--border-1);
      border-radius: var(--r-lg); overflow-x: auto;
    }
    .ls-table {
      width: 100%; border-collapse: collapse;
    }
    .ls-table th {
      padding: 9px 14px; text-align: left;
      font-size: 10px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .7px; color: var(--text-subtle);
      background: var(--surface-2); border-bottom: 1px solid var(--border-1);
      white-space: nowrap;
    }
    .ls-table td {
      padding: 9px 14px; font-size: 13px; color: var(--text-primary);
      border-bottom: 1px solid var(--border-2); vertical-align: middle;
      white-space: nowrap;
    }
    .ls-table tbody tr:last-child td { border-bottom: none; }
    .ls-table tbody tr:hover td { background: var(--surface-2); }

    /* Column-specific */
    .ls-table .td-id {
      font-family: var(--font-mono); font-size: 12px; color: var(--text-subtle);
    }
    .ls-table .td-code {
      font-family: var(--font-mono); font-size: 13px; font-weight: 700;
      color: var(--amber); letter-spacing: .5px;
    }
    .ls-table .td-name {
      white-space: normal; word-break: break-word;
    }

    /* Actions row */
    .td-actions {
      display: flex; gap: 4px; align-items: center;
    }

    /* Action buttons: icon + visible label */
    .ls-action-btn {
      display: inline-flex; align-items: center; justify-content: center;
      gap: 5px; padding: 4px 10px; border-radius: var(--r);
      border: 1px solid var(--border-1); background: var(--surface-2);
      color: var(--text-muted); cursor: pointer;
      transition: all var(--t-fast); text-decoration: none;
      font-family: var(--font-sans); font-size: 11px; font-weight: 500;
      flex-shrink: 0; line-height: 1; white-space: nowrap;
    }
    .ls-action-btn:hover { text-decoration: none; }
    .ls-action-btn svg { flex-shrink: 0; width: 13px; height: 13px; }

    .ls-action-btn.is-edit {
      border-color: var(--blue-border); background: var(--blue-subtle); color: var(--blue);
    }
    .ls-action-btn.is-edit:hover {
      background: rgba(56,139,253,.3); color: var(--blue);
    }
    .ls-action-btn.is-deactivate:hover {
      background: var(--red-subtle); border-color: var(--red-border); color: var(--red);
    }
    .ls-action-btn.is-activate:hover {
      background: var(--green-subtle); border-color: var(--green-border); color: var(--green);
    }

    /* Empty */
    .ls-empty {
      padding: 48px 24px; text-align: center; color: var(--text-subtle);
    }
    .ls-empty svg { margin: 0 auto 12px; opacity: .25; }
    .ls-empty p { font-size: 13px; color: var(--text-muted); }

    /* ── Responsive ─────────────────────────────────────── */
    @media (min-width: 1440px) {
      .ls-page { padding: 36px 56px 80px; }
      .ls-heading-icon { width: 56px; height: 56px; }
    }
    @media (min-width: 1920px) {
      .ls-page { padding: 48px 80px 96px; }
      .ls-heading-icon { width: 64px; height: 64px; }
    }
    @media (max-width: 1100px) {
      .ls-page { padding: 24px 28px 56px; }
    }
    @media (max-width: 800px) {
      .main-content { overflow: visible; align-items: stretch; }
      .ls-page { padding: 20px 16px 48px; }
      .ls-heading-text h1 { font-size: 18px; }
      .ls-heading { flex-direction: column; }
    }
    @media (max-width: 600px) {
      .ls-filter-body { flex-direction: column; }
      .ls-table-card { overflow-x: auto; }
    }
  </style>
</head>
<body>

<div class="app-shell">

  <?php require __DIR__ . '/../inc/sidebar.php'; ?>

  <main class="main-content">
    <div class="ls-page">

      <!-- ── Heading ──────────────────────────────────────── -->
      <div class="ls-heading">
        <div class="ls-heading-left">
          <div class="ls-heading-icon" style="background:var(--blue-subtle);border-color:var(--blue-border);color:var(--blue);border:1px solid var(--blue-border);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
          </div>
          <div class="ls-heading-text">
            <h1>Direcciones</h1>
            <p>Listado de direcciones organizacionales registradas en el sistema.</p>
          </div>
        </div>
        <div class="ls-heading-actions">
          <a class="btn btn-primary btn-sm" href="direcciones_form.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nueva dirección
          </a>
        </div>
      </div>

      <!-- ── Alerts ────────────────────────────────────────── -->
      <?php if ($msg): ?>
      <div class="uf-alert uf-alert-success" style="margin-bottom:16px;">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm3.03 3.97-3.78 5.03-1.78-1.78-1.06 1.06 2.5 2.5.53.53.53-.7 4.32-5.75-1.26-.89z"/>
        </svg>
        <?php echo _sb_h($msg); ?>
      </div>
      <?php endif; ?>

      <?php if ($err): ?>
      <div class="uf-alert uf-alert-error" style="margin-bottom:16px;">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
        </svg>
        <?php echo _sb_h($err); ?>
      </div>
      <?php endif; ?>

      <!-- ── Stats ─────────────────────────────────────────── -->
      <div class="ls-stats">
        <div class="ls-stat accent-blue">
          <span>Total</span>
          <span class="ls-stat-value"><?php echo count($rows); ?></span>
        </div>
        <div class="ls-stat accent-green">
          <span>Activas</span>
          <span class="ls-stat-value"><?php echo $totalActivas; ?></span>
        </div>
        <?php if ($totalInactivas > 0): ?>
        <div class="ls-stat accent-amber">
          <span>Inactivas</span>
          <span class="ls-stat-value"><?php echo $totalInactivas; ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── Filtro ────────────────────────────────────────── -->
      <div class="ls-filter">
        <div class="ls-filter-header">
          <div class="ls-filter-icon">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
          </div>
          <span class="ls-filter-title">Buscar dirección</span>
        </div>
        <form method="get" class="ls-filter-body">
          <div class="ls-filter-field">
            <label class="ls-filter-label">Código o nombre</label>
            <input class="ls-search-input" name="q" value="<?php echo _sb_h($q); ?>" placeholder="Ej: DAF / SECPLAC / Dirección...">
          </div>
          <div style="display:flex;gap:8px;align-items:end;">
            <button class="btn btn-secondary btn-sm" type="submit">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
              Filtrar
            </button>
            <?php if ($q !== ''): ?>
              <a class="btn btn-secondary btn-sm" href="direcciones_list.php">Limpiar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ── Tabla ─────────────────────────────────────────── -->
      <div class="ls-table-card">
        <?php if (empty($rows)): ?>
          <div class="ls-empty">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <p>No se encontraron direcciones<?php echo $q !== '' ? ' con ese filtro' : ''; ?>.</p>
          </div>
        <?php else: ?>
          <table class="ls-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="td-id"><?php echo (int)$r['id']; ?></td>
                  <td class="td-code"><?php echo _sb_h($r['codigo']); ?></td>
                  <td class="td-name"><?php echo _sb_h($r['nombre']); ?></td>
                  <td>
                    <?php if ((int)$r['activo'] === 1): ?>
                      <span class="status-pill status-done">Activa</span>
                    <?php else: ?>
                      <span class="status-pill status-none">Inactiva</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="td-actions">
                      <a class="ls-action-btn is-edit" href="direcciones_form.php?id=<?php echo (int)$r['id']; ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Editar
                      </a>
                      <form method="post" style="display:inline;margin:0;">
                        <input type="hidden" name="csrf" value="<?php echo _sb_h(csrf_token()); ?>">
                        <input type="hidden" name="toggle_id" value="<?php echo (int)$r['id']; ?>">
                        <?php if ((int)$r['activo'] === 1): ?>
                          <button class="ls-action-btn is-deactivate" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                              <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            Desactivar
                          </button>
                        <?php else: ?>
                          <button class="ls-action-btn is-activate" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                              <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
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
        <?php endif; ?>
      </div>

    </div><!-- /.ls-page -->
  </main>

  </div><!-- /body-layout -->
</div><!-- /app-shell -->

</body>
</html>