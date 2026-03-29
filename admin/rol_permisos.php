<?php
// intranet/admin/rol_permisos.php (PHP 5.6)
require __DIR__ . '/_guard.php'; // superadmin

$rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
$msg = '';
$err = '';

/* ── Roles ───────────────────────────────────────────────── */
$roles = array();
try {
  $roles = $pdo->query("SELECT id, codigo, nombre, activo FROM roles ORDER BY activo DESC, nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = 'No se pudieron cargar los roles: ' . $e->getMessage();
}
if ($rid <= 0 && !empty($roles)) $rid = (int)$roles[0]['id'];

/* ── Recursos ────────────────────────────────────────────── */
$recursos = array();
try {
  $recursos = $pdo->query("
    SELECT id, codigo, nombre, tipo, ruta, activo
    FROM recursos
    WHERE activo = 1
    ORDER BY tipo, nombre
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = $err ?: 'No se pudieron cargar los recursos: ' . $e->getMessage();
}

/* ── Agrupar recursos por tipo ───────────────────────────── */
$recursosPorTipo = array();
foreach ($recursos as $re) {
  $tipo = trim((string)$re['tipo']);
  if ($tipo === '') $tipo = 'otro';
  if (!isset($recursosPorTipo[$tipo])) $recursosPorTipo[$tipo] = array();
  $recursosPorTipo[$tipo][] = $re;
}

/* ── Acciones ────────────────────────────────────────────── */
$acciones = array();
try {
  $acciones = $pdo->query("SELECT id, codigo, nombre FROM acciones ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = $err ?: 'No se pudieron cargar las acciones: ' . $e->getMessage();
}

/* ── Guardar permisos ────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ridPost = isset($_POST['rid']) ? (int)$_POST['rid'] : 0;

  if ($ridPost <= 0) {
    $err = 'Rol inválido.';
  } else {
    $perm = (isset($_POST['perm']) && is_array($_POST['perm'])) ? $_POST['perm'] : array();

    $pdo->beginTransaction();
    try {
      $del = $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?");
      $del->execute(array($ridPost));

      $ins = $pdo->prepare("INSERT INTO rol_permisos (rol_id, recurso_id, accion_id, permitido) VALUES (?,?,?,1)");

      foreach ($perm as $recursoId => $accionesSel) {
        $recursoId = (int)$recursoId;
        if ($recursoId <= 0 || !is_array($accionesSel)) continue;
        foreach ($accionesSel as $accionId => $v) {
          $accionId = (int)$accionId;
          if ($accionId <= 0) continue;
          $ins->execute(array($ridPost, $recursoId, $accionId));
        }
      }

      $pdo->commit();
      $msg = 'Permisos guardados correctamente.';
      $rid = $ridPost;

    } catch (Exception $e) {
      $pdo->rollBack();
      $err = 'Error al guardar permisos: ' . $e->getMessage();
    }
  }
}

/* ── Mapa de permisos actuales [recurso_id][accion_id] = 1 ─ */
$permMap = array();
if ($rid > 0) {
  try {
    $st = $pdo->prepare("SELECT recurso_id, accion_id, permitido FROM rol_permisos WHERE rol_id = ?");
    $st->execute(array($rid));
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      if ((int)$r['permitido'] !== 1) continue;
      $re = (int)$r['recurso_id'];
      $ac = (int)$r['accion_id'];
      if (!isset($permMap[$re])) $permMap[$re] = array();
      $permMap[$re][$ac] = 1;
    }
  } catch (Exception $e) { /* silencioso */ }
}

/* ── Stats ───────────────────────────────────────────────── */
$totalChecked  = 0;
foreach ($permMap as $rp) $totalChecked += count($rp);
$totalRecursos = count($recursos);
$totalPosible  = $totalRecursos * count($acciones);

/* ── Rol actual ──────────────────────────────────────────── */
$rolActual = null;
foreach ($roles as $r) {
  if ((int)$r['id'] === $rid) { $rolActual = $r; break; }
}

/* ── Helpers de tipo ─────────────────────────────────────── */
function tipoIconSvg($t) {
  switch ($t) {
    case 'admin':
      return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
    case 'modulo':
      return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
    case 'pagina':
      return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    case 'reporte':
      return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
    case 'api':
      return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';
    default:
      return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Permisos por Rol</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../static/css/theme.css">
  <link rel="stylesheet" href="../static/css/sidebar.css">
  <link rel="stylesheet" href="../static/css/form.css">
  <link rel="icon" type="image/x-icon" href="../static/img/logo.png">
  <style>
    /* ══ Page ═════════════════════════════════════════════════ */
    .ls-page { padding: 28px 36px 80px; width: 100%; box-sizing: border-box; font-family: var(--font-sans); }

    /* ── Heading ────────────────────────────────────────────── */
    .ls-heading       { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .ls-heading-left  { display: flex; align-items: center; gap: 14px; }
    .ls-heading-icon  { width: 48px; height: 48px; border-radius: var(--r-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ls-heading-text h1 { font-size: clamp(20px,2vw,28px); font-weight: 700; color: var(--text-primary); letter-spacing: -.3px; line-height: 1.2; margin: 0 0 4px; }
    .ls-heading-text p  { font-size: 13px; color: var(--text-muted); margin: 0; }

    /* ── Role chip ──────────────────────────────────────────── */
    .rp-role-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; font-family: var(--font-mono); letter-spacing: .3px; background: var(--green-subtle); border: 1px solid var(--green-border); color: var(--green); }
    .rp-role-chip.is-inactive { background: var(--surface-2); border-color: var(--border-2); color: var(--text-subtle); }

    /* ── Stats ──────────────────────────────────────────────── */
    .ls-stats  { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .ls-stat   { display: inline-flex; align-items: center; gap: 7px; padding: 5px 12px; border-radius: 999px; background: var(--surface-1); border: 1px solid var(--border-1); font-size: 12px; font-weight: 500; color: var(--text-muted); }
    .ls-stat-value { font-family: var(--font-mono); font-weight: 700; }
    .ls-stat.accent-blue   .ls-stat-value { color: var(--blue); }
    .ls-stat.accent-green  .ls-stat-value { color: var(--green); }
    .ls-stat.accent-amber  .ls-stat-value { color: var(--amber); }
    .ls-stat.accent-purple .ls-stat-value { color: var(--purple); }

    /* ── Selector de rol ────────────────────────────────────── */
    .ls-filter        { background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r-lg); overflow: hidden; margin-bottom: 24px; }
    .ls-filter-header { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-bottom: 1px solid var(--border-1); background: var(--surface-2); }
    .ls-filter-icon   { width: 26px; height: 26px; border-radius: var(--r); background: var(--green-subtle); border: 1px solid var(--green-border); display: flex; align-items: center; justify-content: center; color: var(--green); flex-shrink: 0; }
    .ls-filter-title  { font-size: 12px; font-weight: 600; color: var(--text-primary); }
    .ls-filter-body   { padding: 12px 16px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
    .ls-filter-field  { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 220px; }
    .ls-filter-label  { font-size: 11px; font-weight: 600; color: var(--text-subtle); text-transform: uppercase; letter-spacing: .4px; }
    .ls-select-input  {
      width: 100%; background: var(--bg); border: 1px solid var(--border-3);
      border-radius: var(--r-md); padding: 8px 32px 8px 12px;
      font-family: var(--font-sans); font-size: 14px; color: var(--text-primary);
      outline: none; box-sizing: border-box; -webkit-appearance: none; appearance: none;
      transition: border-color var(--t-fast), box-shadow var(--t-fast);
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 12px center;
    }
    .ls-select-input:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(63,185,80,.15); }

    /* ══ Secciones por tipo ════════════════════════════════════ */
    .tipo-section { margin-bottom: 28px; }

    /* Encabezado de sección de tipo */
    .tipo-header {
      display: flex; align-items: center; justify-content: space-between;
      gap: 12px; padding: 13px 18px;
      border-radius: var(--r-lg) var(--r-lg) 0 0;
      border: 1px solid; border-bottom: none;
    }
    .tipo-header-left  { display: flex; align-items: center; gap: 10px; }
    .tipo-header-icon  { width: 32px; height: 32px; border-radius: var(--r); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid; }
    .tipo-header-info  { display: flex; flex-direction: column; gap: 1px; }
    .tipo-header-name  { font-size: 13px; font-weight: 700; letter-spacing: .2px; line-height: 1.2; }
    .tipo-header-count { font-size: 11px; color: var(--text-subtle); }
    .tipo-header-btns  { display: flex; gap: 6px; flex-shrink: 0; }

    /* Variantes de color por tipo */
    /* modulo = azul */
    .tipo-section-modulo  .tipo-header { background: rgba(56,139,253,.07);  border-color: var(--blue-border);   }
    .tipo-section-modulo  .tipo-header-icon { background: var(--blue-subtle);   border-color: var(--blue-border);   color: var(--blue);   }
    .tipo-section-modulo  .tipo-header-name { color: var(--blue); }
    /* pagina = purpura */
    .tipo-section-pagina  .tipo-header { background: rgba(163,113,247,.07); border-color: var(--purple-border); }
    .tipo-section-pagina  .tipo-header-icon { background: var(--purple-subtle); border-color: var(--purple-border); color: var(--purple); }
    .tipo-section-pagina  .tipo-header-name { color: var(--purple); }
    /* admin = rojo */
    .tipo-section-admin   .tipo-header { background: rgba(248,81,73,.07);   border-color: rgba(248,81,73,.35);  }
    .tipo-section-admin   .tipo-header-icon { background: rgba(248,81,73,.12);  border-color: rgba(248,81,73,.35);  color: #f85149; }
    .tipo-section-admin   .tipo-header-name { color: #f85149; }
    /* reporte = amber */
    .tipo-section-reporte .tipo-header { background: rgba(210,153,34,.07);  border-color: var(--amber-border);  }
    .tipo-section-reporte .tipo-header-icon { background: var(--amber-subtle);  border-color: var(--amber-border);  color: var(--amber);  }
    .tipo-section-reporte .tipo-header-name { color: var(--amber); }
    /* api = verde */
    .tipo-section-api     .tipo-header { background: rgba(63,185,80,.07);   border-color: var(--green-border);  }
    .tipo-section-api     .tipo-header-icon { background: var(--green-subtle);  border-color: var(--green-border);  color: var(--green);  }
    .tipo-section-api     .tipo-header-name { color: var(--green); }
    /* otro = gris */
    .tipo-section-otro    .tipo-header { background: var(--surface-2);       border-color: var(--border-1);      }
    .tipo-section-otro    .tipo-header-icon { background: var(--surface-3);     border-color: var(--border-2);      color: var(--text-subtle); }
    .tipo-section-otro    .tipo-header-name { color: var(--text-muted); }

    /* ── Tabla desktop ──────────────────────────────────────── */
    .pm-table-wrap {
      background: var(--surface-1); border: 1px solid var(--border-1);
      border-radius: 0 0 var(--r-lg) var(--r-lg); overflow-x: auto;
    }
    .pm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pm-table thead th {
      padding: 9px 10px; text-align: center;
      font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px;
      color: var(--text-subtle); background: var(--surface-2);
      border-bottom: 1px solid var(--border-1); white-space: nowrap;
    }
    .pm-table thead th.th-recurso { text-align: left; padding-left: 16px; min-width: 200px; }
    .pm-table thead th.th-accion  { min-width: 64px; }
    /* Fila select-all por columna */
    .tr-selectall td { padding: 7px 10px; background: var(--surface-2); border-bottom: 2px solid var(--border-1); text-align: center; }
    .tr-selectall .td-sel-label { text-align: left; padding-left: 16px; font-size: 10px; font-weight: 600; color: var(--text-subtle); text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
    /* Filas de datos */
    .pm-table tbody td { padding: 11px 10px; border-bottom: 1px solid var(--border-2); vertical-align: middle; text-align: center; }
    .pm-table tbody tr:last-child td { border-bottom: none; }
    .pm-table tbody tr:hover td { background: var(--surface-2); }
    /* Celda recurso */
    .td-re { text-align: left !important; padding-left: 16px !important; }
    .td-re-name { font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.3; }
    .td-re-code { font-family: var(--font-mono); font-size: 10px; color: var(--text-subtle); margin-top: 2px; }
    .td-re-ruta { font-size: 10px; color: var(--text-subtle); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px; }
    /* Checkbox */
    .td-ck       { position: relative; }
    .td-ck.is-on { background: rgba(63,185,80,.07); }
    .pm-cb       { width: 16px; height: 16px; cursor: pointer; accent-color: var(--green); vertical-align: middle; }

    /* ══ Cards móvil ═══════════════════════════════════════════ */
    .pm-cards { display: none; }

    .pm-mcard { background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r-lg); overflow: hidden; margin-bottom: 8px; }
    .pm-mcard-head {
      display: flex; align-items: center; justify-content: space-between;
      gap: 8px; padding: 11px 14px;
      background: var(--surface-2); border-bottom: 1px solid var(--border-2);
      cursor: pointer; -webkit-tap-highlight-color: transparent; user-select: none;
    }
    .pm-mcard-left   { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
    .pm-mcard-name   { font-size: 13px; font-weight: 600; color: var(--text-primary); }
    .pm-mcard-meta   { display: flex; align-items: center; gap: 6px; }
    .pm-mcard-code   { font-family: var(--font-mono); font-size: 10px; color: var(--text-subtle); }
    .pm-mcard-pill   { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 999px; border: 1px solid; white-space: nowrap; }
    .pm-mcard-arrow  { flex-shrink: 0; color: var(--text-subtle); transition: transform .2s ease; }
    .pm-mcard.is-open .pm-mcard-arrow { transform: rotate(90deg); }
    .pm-mcard-body   { display: none; }
    .pm-mcard.is-open .pm-mcard-body { display: block; }
    .pm-mcard-row    {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 14px; border-top: 1px solid var(--border-2);
    }
    .pm-mcard-row.is-on  { background: rgba(63,185,80,.07); }
    .pm-mcard-accode { font-family: var(--font-mono); font-size: 10px; font-weight: 700; color: var(--text-subtle); min-width: 46px; flex-shrink: 0; text-transform: uppercase; }
    .pm-mcard-acname { font-size: 12px; color: var(--text-muted); flex: 1; }
    .pm-cb-mobile    { width: 18px; height: 18px; cursor: pointer; accent-color: var(--green); flex-shrink: 0; }

    /* Pills de tipo (reutilizado para mcard) */
    .tp-modulo  { background: var(--blue-subtle);   border-color: var(--blue-border);   color: var(--blue);   }
    .tp-pagina  { background: var(--purple-subtle); border-color: var(--purple-border); color: var(--purple); }
    .tp-admin   { background: rgba(248,81,73,.1);   border-color: rgba(248,81,73,.3);   color: #f85149;       }
    .tp-reporte { background: var(--amber-subtle);  border-color: var(--amber-border);  color: var(--amber);  }
    .tp-api     { background: var(--green-subtle);  border-color: var(--green-border);  color: var(--green);  }
    .tp-otro    { background: var(--surface-3);     border-color: var(--border-2);      color: var(--text-subtle); }

    /* ── Barra de guardado (sticky) ─────────────────────────── */
    .pm-save-bar {
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
      padding: 14px 18px; background: var(--surface-1); border: 1px solid var(--border-1);
      border-radius: var(--r-lg); flex-wrap: wrap;
      position: sticky; bottom: 16px;
      box-shadow: 0 4px 24px rgba(0,0,0,.28);
    }
    .pm-save-info { font-size: 12px; color: var(--text-muted); line-height: 1.5; }
    .pm-save-info strong { color: var(--text-primary); }
    .pm-save-actions { display: flex; gap: 8px; flex-shrink: 0; }

    /* ── Empty ──────────────────────────────────────────────── */
    .ls-empty { padding: 48px 24px; text-align: center; background: var(--surface-1); border: 1px solid var(--border-1); border-radius: var(--r-lg); }
    .ls-empty svg { margin: 0 auto 12px; opacity: .2; display: block; }
    .ls-empty p   { font-size: 13px; color: var(--text-muted); margin: 0; }

    /* ══ Responsive ════════════════════════════════════════════ */
    @media (min-width:1440px) { .ls-page { padding: 36px 56px 96px; } .ls-heading-icon { width: 56px; height: 56px; } }
    @media (min-width:1920px) { .ls-page { padding: 48px 80px 112px; } }
    @media (max-width:1100px) { .ls-page { padding: 24px 28px 72px; } }

    @media (max-width:800px) {
      .ls-page            { padding: 20px 16px 64px; }
      .ls-heading-text h1 { font-size: 18px; }
      .ls-heading-text p  { display: none; }
      .ls-heading         { flex-direction: column; gap: 10px; }
      .ls-heading-icon    { width: 40px; height: 40px; }
      .ls-filter-body     { flex-direction: column; align-items: stretch; }
      .ls-filter-field    { min-width: unset; }
      /* En móvil el tipo-header queda completo (sin los botones) */
      .tipo-header        { border-radius: var(--r-lg); border-bottom: 1px solid; margin-bottom: 8px; }
      .tipo-header-btns   { display: none; }
      /* Tabla oculta, cards visibles */
      .pm-table-wrap      { display: none; }
      .pm-cards           { display: block; }
      /* Save bar */
      .pm-save-bar        { flex-direction: column; align-items: stretch; bottom: 8px; padding: 12px 14px; gap: 10px; z-index: 100; }
      .pm-save-info       { text-align: center; }
      .pm-save-actions    { flex-wrap: wrap; width: 100%; gap: 8px; }
      .pm-save-actions .btn { 
        flex: 1 1 calc(50% - 8px); /* Crea una grilla de 2 columnas (2 arriba, 2 abajo) */
        justify-content: center;   /* Centra el texto y el icono dentro del botón */
        white-space: nowrap; 
      }
    }
    @media (max-width:480px) {
      .ls-page        { padding: 14px 12px 56px; }
      .ls-heading-icon{ display: none; }
      .ls-stats       { gap: 6px; }
      .ls-stat        { padding: 4px 10px; font-size: 11px; }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <?php require __DIR__ . '/../inc/sidebar.php'; ?>
  <main class="main-content">
    <div class="ls-page">

      <!-- ════ Heading ════════════════════════════════════════ -->
      <div class="ls-heading">
        <div class="ls-heading-left">
          <div class="ls-heading-icon" style="background:var(--green-subtle);border:1px solid var(--green-border);color:var(--green);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </div>
          <div class="ls-heading-text">
            <h1>Permisos por Rol</h1>
            <p>Configura qué acciones puede ejecutar cada rol sobre los recursos del sistema.</p>
          </div>
        </div>
        <?php if ($rolActual): ?>
        <div style="display:flex;align-items:center;flex-shrink:0;">
          <span class="rp-role-chip<?php echo ((int)$rolActual['activo']===0)?' is-inactive':''; ?>">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>
            </svg>
            <?php echo _sb_h($rolActual['codigo']); ?>
            <?php echo ((int)$rolActual['activo']===0)?' &middot; INACTIVO':''; ?>
          </span>
        </div>
        <?php endif; ?>
      </div>

      <!-- ════ Alertas ════════════════════════════════════════ -->
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

      <!-- ════ Stats ═══════════════════════════════════════════ -->
      <div class="ls-stats">
        <div class="ls-stat accent-blue">
          <span>Recursos</span>
          <span class="ls-stat-value"><?php echo $totalRecursos; ?></span>
        </div>
        <div class="ls-stat accent-blue">
          <span>Acciones</span>
          <span class="ls-stat-value"><?php echo count($acciones); ?></span>
        </div>
        <div class="ls-stat accent-green">
          <span>Permisos activos</span>
          <span class="ls-stat-value" id="stat-count"><?php echo $totalChecked; ?></span>
          <span>De <?php echo $totalPosible; ?> posibles</span>
        </div>
      </div>

      <!-- ════ Selector de Rol ════════════════════════════════ -->
      <div class="ls-filter">
        <div class="ls-filter-header">
          <div class="ls-filter-icon">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>
            </svg>
          </div>
          <span class="ls-filter-title">Seleccionar Rol</span>
        </div>
        <form method="get" style="display:contents;">
          <div class="ls-filter-body">
            <div class="ls-filter-field">
              <label class="ls-filter-label">Rol</label>
              <select class="ls-select-input" name="rid" onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?>
                  <option value="<?php echo (int)$r['id']; ?>" <?php echo ((int)$rid===(int)$r['id'])?'selected':''; ?>>
                    <?php echo _sb_h($r['nombre'].' ('.$r['codigo'].')'); ?>
                    <?php echo ((int)$r['activo']===0)?' [INACTIVO]':''; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <noscript>
              <div style="display:flex;gap:8px;align-items:flex-end;flex-shrink:0;">
                <button type="submit" class="btn btn-secondary btn-sm">Cargar</button>
              </div>
            </noscript>
          </div>
        </form>
      </div>

      <!-- ════ Matrices por tipo ══════════════════════════════ -->
      <?php if (empty($roles)): ?>
        <div class="ls-empty">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
          <p>No hay roles registrados en el sistema.</p>
        </div>

      <?php elseif (empty($recursos)): ?>
        <div class="ls-empty">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
          <p>No hay recursos activos. Agrega recursos en la tabla <code>recursos</code>.</p>
        </div>

      <?php else: ?>

      <form method="post" id="perm-form">
        <input type="hidden" name="csrf" value="<?php echo _sb_h(csrf_token()); ?>">
        <input type="hidden" name="rid"  value="<?php echo (int)$rid; ?>">

        <?php foreach ($recursosPorTipo as $tipo => $recsDelTipo):
          $tipoSlug  = strtolower($tipo);
          $tipoLabel = ucfirst($tipo);
          $tipoCount = count($recsDelTipo);
          $groupId   = 'group-' . $tipoSlug;
        ?>

        <!-- ════════ Sección: <?php echo $tipoLabel; ?> ════════ -->
        <div class="tipo-section tipo-section-<?php echo $tipoSlug; ?>" id="<?php echo $groupId; ?>">

          <!-- Encabezado del tipo -->
          <div class="tipo-header">
            <div class="tipo-header-left">
              <div class="tipo-header-icon">
                <?php echo tipoIconSvg($tipoSlug); ?>
              </div>
              <div class="tipo-header-info">
                <span class="tipo-header-name"><?php echo _sb_h($tipoLabel); ?></span>
                <span class="tipo-header-count">
                  <?php echo $tipoCount; ?> recurso<?php echo $tipoCount !== 1 ? 's' : ''; ?>
                </span>
              </div>
            </div>
            <div class="tipo-header-btns">
              <button type="button" class="btn btn-secondary btn-sm"
                      onclick="rpSelectGroup('<?php echo $groupId; ?>',true)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                Marcar todo
              </button>
              <button type="button" class="btn btn-secondary btn-sm"
                      onclick="rpSelectGroup('<?php echo $groupId; ?>',false)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Desmarcar todo
              </button>
            </div>
          </div>

          <!-- ── Tabla (desktop) ─────────────────────────────── -->
          <div class="pm-table-wrap">
            <table class="pm-table">
              <thead>
                <tr>
                  <th class="th-recurso">Recurso</th>
                  <?php foreach ($acciones as $a): ?>
                    <th class="th-accion" title="<?php echo _sb_h($a['nombre']); ?>">
                      <?php echo _sb_h($a['codigo']); ?>
                    </th>
                  <?php endforeach; ?>
                </tr>
                <tr class="tr-selectall">
                  <td class="td-sel-label">Seleccionar columna completa</td>
                  <?php foreach ($acciones as $a):
                    $acId   = (int)$a['id'];
                    $allCol = true;
                    foreach ($recsDelTipo as $re) {
                      if (empty($permMap[(int)$re['id']][$acId])) { $allCol = false; break; }
                    }
                  ?>
                    <td>
                      <input type="checkbox" class="pm-cb col-cb"
                             data-accion="<?php echo $acId; ?>"
                             data-group="<?php echo $groupId; ?>"
                             title="<?php echo _sb_h($a['nombre']); ?>"
                             <?php echo $allCol ? 'checked' : ''; ?>>
                    </td>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recsDelTipo as $re):
                  $reId = (int)$re['id'];
                ?>
                <tr>
                  <td class="td-re">
                    <div class="td-re-name"><?php echo _sb_h($re['nombre']); ?></div>
                    <div class="td-re-code"><?php echo _sb_h($re['codigo']); ?></div>
                    <?php if (!empty($re['ruta'])): ?>
                      <div class="td-re-ruta"><?php echo _sb_h($re['ruta']); ?></div>
                    <?php endif; ?>
                  </td>
                  <?php foreach ($acciones as $a):
                    $acId = (int)$a['id'];
                    $ck   = !empty($permMap[$reId][$acId]);
                  ?>
                    <td class="td-ck<?php echo $ck ? ' is-on' : ''; ?>">
                      <input type="checkbox" class="pm-cb perm-cb"
                             name="perm[<?php echo $reId; ?>][<?php echo $acId; ?>]"
                             value="1"
                             data-re="<?php echo $reId; ?>"
                             data-ac="<?php echo $acId; ?>"
                             data-group="<?php echo $groupId; ?>"
                             <?php echo $ck ? 'checked' : ''; ?>>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div><!-- /.pm-table-wrap -->

          <!-- ── Cards (móvil) ──────────────────────────────── -->
          <div class="pm-cards">
            <?php foreach ($recsDelTipo as $re):
              $reId    = (int)$re['id'];
              $ckCount = !empty($permMap[$reId]) ? count($permMap[$reId]) : 0;
            ?>
            <div class="pm-mcard" id="mcard-<?php echo $reId; ?>">
              <div class="pm-mcard-head" onclick="rpToggleCard(<?php echo $reId; ?>)">
                <div class="pm-mcard-left">
                  <span class="pm-mcard-name"><?php echo _sb_h($re['nombre']); ?></span>
                  <div class="pm-mcard-meta">
                    <span class="pm-mcard-code"><?php echo _sb_h($re['codigo']); ?></span>
                    <span class="pm-mcard-pill tp-<?php echo $tipoSlug; ?>"><?php echo _sb_h($tipoLabel); ?></span>
                    <span class="pm-mcard-code">
                      &bull; <span class="perm-count-<?php echo $reId; ?>"><?php echo $ckCount; ?></span>/<?php echo count($acciones); ?>
                    </span>
                  </div>
                </div>
                <svg class="pm-mcard-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </div>
              <div class="pm-mcard-body">
                <?php foreach ($acciones as $a):
                  $acId = (int)$a['id'];
                  $ck   = !empty($permMap[$reId][$acId]);
                ?>
                <div class="pm-mcard-row<?php echo $ck ? ' is-on' : ''; ?>"
                     id="mrow-<?php echo $reId; ?>-<?php echo $acId; ?>">
                  <span class="pm-mcard-accode"><?php echo _sb_h($a['codigo']); ?></span>
                  <span class="pm-mcard-acname"><?php echo _sb_h($a['nombre']); ?></span>
                  <input type="checkbox" class="pm-cb-mobile perm-cb"
                         name="perm[<?php echo $reId; ?>][<?php echo $acId; ?>]"
                         value="1"
                         data-re="<?php echo $reId; ?>"
                         data-ac="<?php echo $acId; ?>"
                         data-group="<?php echo $groupId; ?>"
                         data-mrow="mrow-<?php echo $reId; ?>-<?php echo $acId; ?>"
                         <?php echo $ck ? 'checked' : ''; ?>>
                </div>
                <?php endforeach; ?>
              </div>
            </div><!-- /.pm-mcard -->
            <?php endforeach; ?>
          </div><!-- /.pm-cards -->

        </div><!-- /.tipo-section -->
        <?php endforeach; /* por tipo */ ?>

        <!-- ════ Barra de guardado sticky ═══════════════════ -->
        <div class="pm-save-bar">
          <div class="pm-save-info">
            <strong id="stat-count2"><?php echo $totalChecked; ?></strong> de <strong><?php echo $totalPosible; ?></strong> permisos activos
            &mdash; <strong><?php echo $rolActual ? _sb_h($rolActual['nombre']) : '—'; ?></strong>
          </div>
          <div class="pm-save-actions">
            <button type="button" class="btn btn-secondary btn-sm" onclick="rpSelectAll(true)" title="Marcar todos los permisos">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              Todo
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="rpSelectAll(false)" title="Desmarcar todos los permisos">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Ninguno
            </button>
            <a class="btn btn-secondary btn-sm" href="rol_permisos.php?rid=<?php echo (int)$rid; ?>">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
              Recargar
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Guardar permisos
            </button>
          </div>
        </div>

      </form>
      <?php endif; ?>

    </div><!-- /.ls-page -->
  </main>
</div><!-- /.app-shell -->

<script>
/* ════════════════════════════════════════════════
   rol_permisos.js — gestión de checkboxes
   ════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {

  /* ── Helpers ── */
  function getPermCbs()  { return document.querySelectorAll('.perm-cb'); }
  function getColCbs()   { return document.querySelectorAll('.col-cb');  }

  /* Cuenta permisos únicos marcados (desktop+móvil duplican el mismo name) */
  function countChecked() {
    var seen = {}, n = 0;
    getPermCbs().forEach(function(cb) {
      var k = cb.getAttribute('name');
      if (!seen[k]) { seen[k] = true; if (cb.checked) n++; }
    });
    return n;
  }

  function setStatCount(n) {
    var s1 = document.getElementById('stat-count');
    var s2 = document.getElementById('stat-count2');
    if (s1) s1.textContent = n;
    if (s2) s2.textContent = n;
  }

  /* Mini-contador del recurso en móvil */
  function updateReCount(reId) {
    var el = document.querySelector('.perm-count-' + reId);
    if (!el) return;
    var n = 0;
    /* Cuenta usando los del desktop (dentro de <td>) para evitar doble conteo */
    document.querySelectorAll('.perm-cb[data-re="' + reId + '"]').forEach(function(cb) {
      if (cb.parentNode && cb.parentNode.tagName === 'TD' && cb.checked) n++;
    });
    el.textContent = n;
  }

  /* Sincroniza desktop ↔ móvil para un par (reId, acId) */
  function syncPair(reId, acId, state) {
    document.querySelectorAll(
      '.perm-cb[data-re="' + reId + '"][data-ac="' + acId + '"]'
    ).forEach(function(cb) {
      cb.checked = state;
      /* highlight celda desktop */
      var td = cb.parentNode;
      if (td && td.tagName === 'TD') {
        if (state) td.classList.add('is-on'); else td.classList.remove('is-on');
      }
      /* highlight fila móvil */
      var mrowId = cb.getAttribute('data-mrow');
      if (mrowId) {
        var mrow = document.getElementById(mrowId);
        if (mrow) { if (state) mrow.classList.add('is-on'); else mrow.classList.remove('is-on'); }
      }
    });
  }

  /* Refresca el checkbox de columna de un grupo */
  function refreshColCb(acId, groupId) {
    var colCb = document.querySelector(
      '.col-cb[data-accion="' + acId + '"][data-group="' + groupId + '"]'
    );
    if (!colCb) return;
    var seen = {}, all = true, found = false;
    document.querySelectorAll('#' + groupId + ' .perm-cb[data-ac="' + acId + '"]').forEach(function(cb) {
      var k = cb.getAttribute('name');
      if (!seen[k]) { seen[k] = true; found = true; if (!cb.checked) all = false; }
    });
    colCb.checked = found && all;
  }

  /* ── Evento cambio de permiso ── */
  getPermCbs().forEach(function(cb) {
    cb.addEventListener('change', function() {
      var reId  = this.getAttribute('data-re');
      var acId  = this.getAttribute('data-ac');
      var grp   = this.getAttribute('data-group');
      syncPair(reId, acId, this.checked);
      updateReCount(reId);
      refreshColCb(acId, grp);
      setStatCount(countChecked());
    });
  });

  /* ── Evento select-all por columna ── */
  getColCbs().forEach(function(colCb) {
    colCb.addEventListener('change', function() {
      var acId = this.getAttribute('data-accion');
      var grp  = this.getAttribute('data-group');
      var seen = {};
      document.querySelectorAll('#' + grp + ' .perm-cb[data-ac="' + acId + '"]').forEach(function(cb) {
        var k = cb.getAttribute('name');
        if (!seen[k]) {
          seen[k] = true;
          syncPair(cb.getAttribute('data-re'), acId, colCb.checked);
          updateReCount(cb.getAttribute('data-re'));
        }
      });
      setStatCount(countChecked());
    });
  });
});

/* ── Marcar / desmarcar un grupo ── */
function rpSelectGroup(groupId, state) {
  var seen = {};
  document.querySelectorAll('#' + groupId + ' .perm-cb').forEach(function(cb) {
    var k = cb.getAttribute('name');
    if (!seen[k]) {
      seen[k] = true;
      cb.checked = state;
      var td = cb.parentNode;
      if (td && td.tagName === 'TD') { if (state) td.classList.add('is-on'); else td.classList.remove('is-on'); }
      var mrowId = cb.getAttribute('data-mrow');
      if (mrowId) { var mr = document.getElementById(mrowId); if (mr) { if (state) mr.classList.add('is-on'); else mr.classList.remove('is-on'); } }
    }
  });
  document.querySelectorAll('#' + groupId + ' .col-cb').forEach(function(cb) { cb.checked = state; });
  /* mini-contadores */
  var reSeen = {};
  document.querySelectorAll('#' + groupId + ' .perm-cb[data-re]').forEach(function(cb) {
    var rId = cb.getAttribute('data-re');
    if (!reSeen[rId] && cb.parentNode && cb.parentNode.tagName === 'TD') { reSeen[rId] = true; rpUpdateRe(rId); }
  });
  rpSetStat();
}

/* ── Marcar / desmarcar todo ── */
function rpSelectAll(state) {
  var seen = {};
  document.querySelectorAll('.perm-cb').forEach(function(cb) {
    var k = cb.getAttribute('name');
    if (!seen[k]) {
      seen[k] = true;
      cb.checked = state;
      var td = cb.parentNode;
      if (td && td.tagName === 'TD') { if (state) td.classList.add('is-on'); else td.classList.remove('is-on'); }
      var mrowId = cb.getAttribute('data-mrow');
      if (mrowId) { var mr = document.getElementById(mrowId); if (mr) { if (state) mr.classList.add('is-on'); else mr.classList.remove('is-on'); } }
    }
  });
  document.querySelectorAll('.col-cb').forEach(function(cb) { cb.checked = state; });
  var reSeen = {};
  document.querySelectorAll('.perm-cb[data-re]').forEach(function(cb) {
    var rId = cb.getAttribute('data-re');
    if (!reSeen[rId] && cb.parentNode && cb.parentNode.tagName === 'TD') { reSeen[rId] = true; rpUpdateRe(rId); }
  });
  rpSetStat();
}

/* ── Helpers globales ── */
function rpUpdateRe(reId) {
  var el = document.querySelector('.perm-count-' + reId);
  if (!el) return;
  var n = 0;
  document.querySelectorAll('.perm-cb[data-re="' + reId + '"]').forEach(function(cb) {
    if (cb.parentNode && cb.parentNode.tagName === 'TD' && cb.checked) n++;
  });
  el.textContent = n;
}
function rpSetStat() {
  var seen = {}, n = 0;
  document.querySelectorAll('.perm-cb').forEach(function(cb) {
    var k = cb.getAttribute('name');
    if (!seen[k]) { seen[k] = true; if (cb.checked) n++; }
  });
  var s1 = document.getElementById('stat-count');
  var s2 = document.getElementById('stat-count2');
  if (s1) s1.textContent = n;
  if (s2) s2.textContent = n;
}

/* ── Accordion tarjetas móvil ── */
function rpToggleCard(reId) {
  var card = document.getElementById('mcard-' + reId);
  if (card) card.classList.toggle('is-open');
}
</script>
</body>
</html>