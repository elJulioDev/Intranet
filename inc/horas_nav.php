<?php
if (!isset($navActive)) $navActive = '';
if (!isset($navTitle)) $navTitle = 'Sistema de Horas';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Horas · <?php echo htmlspecialchars($navTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ── Variables ─────────────────────────────── */
    :root {
      --bg:       #eef1f6;
      --surface:  #ffffff;
      --nav-bg:   #18243b;
      --nav-border: rgba(255,255,255,0.06);
      --nav-txt:  rgba(255,255,255,0.55);
      --nav-hover:#ffffff;
      --accent:   #8cc63f;
      --accent-d: #6fa030;
      --accent-bg:rgba(140,198,63,0.12);
      --txt:      #1a2035;
      --muted:    #64748b;
      --border:   #e2e8f0;
      --danger:   #e53e3e;
      --danger-bg:#fff0f0;
      --ok:       #38a169;
      --ok-bg:    #f0fff4;
      --warn:     #d97706;
      --warn-bg:  #fffbeb;
      --blue:     #3b82f6;
      --blue-bg:  #eff6ff;
      --shadow-sm:0 1px 3px rgba(0,0,0,0.07),0 1px 2px rgba(0,0,0,0.04);
      --shadow:   0 4px 16px rgba(0,0,0,0.07);
      --radius:   10px;
      --font:     'DM Sans', sans-serif;
    }

    /* ── Reset ──────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--txt);
      font-size: 14px;
      line-height: 1.5;
      min-height: 100vh;
    }
    a { color: inherit; text-decoration: none; }

    /* ── Navbar ─────────────────────────────────── */
    .hn {
      background: var(--nav-bg);
      border-bottom: 1px solid var(--nav-border);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .hn-inner {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
      display: flex;
      align-items: stretch;
      height: 52px;
      gap: 4px;
    }
    .hn-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #fff;
      font-weight: 700;
      font-size: 15px;
      letter-spacing: -0.2px;
      padding-right: 20px;
      border-right: 1px solid var(--nav-border);
      margin-right: 8px;
      white-space: nowrap;
    }
    .hn-brand-dot {
      width: 8px; height: 8px;
      background: var(--accent);
      border-radius: 50%;
      flex-shrink: 0;
    }
    .hn-links {
      display: flex;
      align-items: stretch;
      gap: 2px;
      flex: 1;
    }
    .hn-link {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 0 14px;
      color: var(--nav-txt);
      font-size: 13.5px;
      font-weight: 500;
      border-bottom: 2px solid transparent;
      transition: color .15s, border-color .15s, background .15s;
      white-space: nowrap;
    }
    .hn-link:hover {
      color: var(--nav-hover);
      background: rgba(255,255,255,0.04);
    }
    .hn-link.active {
      color: var(--accent);
      border-bottom-color: var(--accent);
    }
    .hn-link svg { flex-shrink: 0; opacity: .8; }
    .hn-link.active svg { opacity: 1; }

    /* ── Main layout ─────────────────────────────── */
    .hn-main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px 20px 40px;
    }

    /* ── Page header ─────────────────────────────── */
    .ph {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .ph-left h1 {
      font-size: 20px;
      font-weight: 700;
      letter-spacing: -.3px;
      color: var(--txt);
    }
    .ph-left p {
      color: var(--muted);
      font-size: 13px;
      margin-top: 2px;
    }
    .ph-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    /* ── Cards ───────────────────────────────────── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
    }
    .card-body { padding: 20px; }
    .card-header {
      padding: 14px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .card-header h2 {
      font-size: 15px;
      font-weight: 600;
      color: var(--txt);
    }

    /* ── Dashboard tiles ─────────────────────────── */
    .tile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 14px;
      margin-top: 8px;
    }
    .tile {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      box-shadow: var(--shadow-sm);
      transition: box-shadow .15s, border-color .15s, transform .15s;
      cursor: pointer;
    }
    .tile:hover {
      box-shadow: var(--shadow);
      border-color: var(--accent);
      transform: translateY(-1px);
    }
    .tile-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--accent-bg);
    }
    .tile-icon svg { color: var(--accent); }
    .tile h3 { font-size: 14px; font-weight: 600; }
    .tile p { font-size: 12.5px; color: var(--muted); line-height: 1.45; }
    .tile-arrow {
      color: var(--muted);
      font-size: 18px;
      align-self: flex-end;
      margin-top: auto;
      transition: color .15s;
    }
    .tile:hover .tile-arrow { color: var(--accent); }

    /* ── Buttons ─────────────────────────────────── */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 8px;
      font-family: var(--font);
      font-size: 13.5px;
      font-weight: 500;
      cursor: pointer;
      transition: all .15s;
      white-space: nowrap;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
      border: 1px solid transparent;
    }
    .btn-primary:hover { background: var(--accent-d); }
    .btn-ghost {
      background: transparent;
      color: var(--txt);
      border: 1px solid var(--border);
    }
    .btn-ghost:hover { background: var(--bg); border-color: #c5cdd8; }
    .btn-danger {
      background: transparent;
      color: var(--danger);
      border: 1px solid #fca5a5;
    }
    .btn-danger:hover { background: var(--danger-bg); }
    .btn-sm { padding: 5px 11px; font-size: 12.5px; }
    .btn svg { flex-shrink: 0; }

    /* ── Forms ───────────────────────────────────── */
    .form-grid {
      display: grid;
      gap: 14px;
    }
    .form-grid-2 { grid-template-columns: repeat(2, 1fr); }
    .form-grid-3 { grid-template-columns: repeat(3, 1fr); }
    .form-grid-4 { grid-template-columns: repeat(4, 1fr); }
    .form-grid-5 { grid-template-columns: repeat(5, 1fr); }
    .full { grid-column: 1 / -1; }
    .field { display: flex; flex-direction: column; gap: 5px; }
    .field label {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .4px;
    }
    .field input, .field select, .field textarea {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: var(--font);
      font-size: 14px;
      color: var(--txt);
      background: var(--surface);
      transition: border-color .15s, box-shadow .15s;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(140,198,63,.15);
    }
    .field .hint {
      font-size: 11.5px;
      color: var(--muted);
    }

    /* ── Table ───────────────────────────────────── */
    .table-wrap { overflow-x: auto; }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th {
      background: #f7f9fc;
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--muted);
      padding: 10px 14px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }
    td {
      padding: 11px 14px;
      border-bottom: 1px solid var(--border);
      font-size: 13.5px;
      vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: #f9fafb; }
    .td-muted { color: var(--muted); font-size: 12.5px; margin-top: 2px; }

    /* ── Status pills ────────────────────────────── */
    .pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 12px;
      arded font-weight: 600;
      white-space: nowrap;
    }
    .pill::before { content:''; width:6px; height:6px; border-radius:50%; background: currentColor; opacity:.7; }
    .pill-pending  { background: var(--warn-bg);  color: var(--warn);   }
    .pill-confirmed{ background: var(--blue-bg);  color: var(--blue);   }
    .pill-attended { background: var(--ok-bg);    color: var(--ok);     }
    .pill-no_show  { background: #f3f4f6;         color: #6b7280;       }
    .pill-cancelled{ background: var(--danger-bg);color: var(--danger); }
    .pill-open     { background: var(--ok-bg);    color: var(--ok);     }
    .pill-closed   { background: var(--danger-bg);color: var(--danger); }
    .pill-yes      { background: var(--danger-bg);color: var(--danger); }
    .pill-no       { background: var(--ok-bg);    color: var(--ok);     }

    /* ── Alerts ──────────────────────────────────── */
    .alert {
      padding: 11px 14px;
      border-radius: 8px;
      font-size: 13.5px;
      font-weight: 500;
      margin-bottom: 16px;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }
    .alert-ok  { background: var(--ok-bg);     color: var(--ok);     border: 1px solid #9ae6b4; }
    .alert-err { background: var(--danger-bg); color: var(--danger); border: 1px solid #fca5a5; }

    /* ── Debug bar ───────────────────────────────── */
    .debug-bar {
      background: #fff7d6;
      border: 1px solid #f0d27a;
      border-radius: 8px;
      padding: 8px 14px;
      margin-bottom: 16px;
      font-family: 'Courier New', monospace;
      font-size: 12px;
      color: #7a5c00;
    }

    /* ── Divider ─────────────────────────────────── */
    .sect { margin-top: 24px; }
    .sect h3 {
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .6px;
      color: var(--muted);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .sect h3::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* ── Detail table ─────────────────────────────── */
    .detail-table { width: 100%; border-collapse: collapse; }
    .detail-table th {
      width: 180px;
      text-align: left;
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .4px;
      padding: 11px 14px;
      background: #f7f9fc;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
      vertical-align: top;
    }
    .detail-table td {
      padding: 11px 16px;
      font-size: 13.5px;
      border-bottom: 1px solid var(--border);
    }
    .detail-table tr:last-child th,
    .detail-table tr:last-child td { border-bottom: none; }

    /* ── Empty state ─────────────────────────────── */
    .empty {
      text-align: center;
      padding: 36px 20px;
      color: var(--muted);
    }
    .empty svg { opacity: .3; margin-bottom: 10px; }
    .empty p { font-size: 13.5px; }

    /* ── Filter bar ──────────────────────────────── */
    .filter-bar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: flex-end;
      padding: 14px 20px;
      border-bottom: 1px solid var(--border);
      background: #fafbfd;
    }
    .filter-bar .field { min-width: 180px; }

    /* ── Responsive ─────────────────────────────── */
    @media (max-width: 700px) {
      .form-grid-5, .form-grid-4, .form-grid-3, .form-grid-2 { grid-template-columns: 1fr; }
      .hn-link span { display: none; }
      .hn-brand-label { display: none; }
    }
    @media (max-width: 480px) {
      .hn-main { padding: 16px 12px 32px; }
    }
  </style>
</head>
<body>

<nav class="hn">
  <div class="hn-inner">
    <a href="horas_dashboard.php" class="hn-brand">
      <span class="hn-brand-dot"></span>
      <span class="hn-brand-label">Horas</span>
    </a>
    <div class="hn-links">

      <a href="horas_dashboard.php" class="hn-link<?php echo ($navActive==='dashboard'?' active':''); ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span>Panel</span>
      </a>

      <a href="horas_config.php" class="hn-link<?php echo ($navActive==='config'?' active':''); ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/><path d="M12 1v3m0 16v3m-7.07-5.07 2.12-2.12M16.95 7.05l2.12-2.12M1 12h3m16 0h3M4.93 19.07l2.12-2.12m9.9-9.9 2.12-2.12"/></svg>
        <span>Configuración</span>
      </a>

      <a href="horas_excepciones.php" class="hn-link<?php echo ($navActive==='excepciones'?' active':''); ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="10" y1="14" x2="14" y2="18"/><line x1="14" y1="14" x2="10" y2="18"/></svg>
        <span>Excepciones</span>
      </a>

      <a href="horas_generar_slots.php" class="hn-link<?php echo ($navActive==='slots'?' active':''); ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
        <span>Generar Slots</span>
      </a>

      <a href="horas_solicitudes.php" class="hn-link<?php echo ($navActive==='solicitudes'?' active':''); ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Solicitudes</span>
      </a>

    </div>
  </div>
</nav>

<div class="hn-main">