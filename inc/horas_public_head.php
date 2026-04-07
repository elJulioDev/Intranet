<?php
if (!isset($pubTitle)) $pubTitle = 'Solicitar Hora';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($pubTitle, ENT_QUOTES, 'UTF-8'); ?> · Municipalidad</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
:root {
      /* Paleta base refinada (Tonos Slate para un look más moderno e institucional) */
      --bg:           #f8fafc;
      --surface:      #ffffff;
      --accent:       #8cc63f;
      --accent-d:     #75a633;
      --accent-bg:    #f3f9eb;
      --txt:          #0f172a;
      --txt-light:    #334155;
      --muted:        #64748b;
      --border:       #e2e8f0;
      
      /* Colores semánticos con mejor contraste */
      --danger:       #ef4444;
      --danger-bg:    #fef2f2;
      --ok:           #10b981;
      --ok-bg:        #ecfdf5;
      --warn:         #f59e0b;
      --warn-bg:      #fffbeb;
      --blue:         #3b82f6;
      --blue-bg:      #eff6ff;
      
      /* Navegación y sombras modernas */
      --nav-bg:       rgba(15, 23, 42, 0.95);
      --shadow-sm:    0 1px 2px 0 rgb(0 0 0 / 0.05);
      --shadow:       0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.04);
      --shadow-lg:    0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.05);
      --shadow-accent:0 8px 16px -4px rgba(140, 198, 63, 0.3);
      
      /* Radios y tipografía */
      --radius:       16px;
      --radius-md:    12px;
      --radius-sm:    8px;
      --font:         'DM Sans', system-ui, -apple-system, sans-serif;
      --font-mono:    'ui-monospace', 'SFMono-Regular', 'Menlo', monospace;
      --transition:   all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--txt);
      font-size: 15px; /* Ligeramente más grande para mejor legibilidad */
      line-height: 1.6;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
    
    a { color: inherit; text-decoration: none; }

    /* ── Top bar (Efecto Glassmorphism) ───────────────────────── */
    .pub-topbar {
      background: var(--nav-bg);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      padding: 0 24px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 50;
      box-shadow: var(--shadow-sm);
    }
    .pub-topbar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #fff;
      font-weight: 700;
      font-size: 16px;
      letter-spacing: -0.2px;
    }
    .pub-topbar-brand-dot {
      width: 10px; height: 10px;
      border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 10px rgba(140, 198, 63, 0.5);
    }
    .pub-topbar-link {
      font-size: 13.5px;
      font-weight: 500;
      color: rgba(255,255,255,.7);
      display: flex;
      align-items: center;
      gap: 6px;
      transition: var(--transition);
    }
    .pub-topbar-link:hover { color: #fff; transform: translateX(-2px); }

    /* ── Page wrapper ─────────────────────────────── */
    .pub-wrap { max-width: 800px; margin: 0 auto; padding: 40px 20px 80px; }
    .pub-wrap-wide { max-width: 1060px; margin: 0 auto; padding: 40px 20px 80px; }

    /* ── Page header ──────────────────────────────── */
    .pub-ph { margin-bottom: 32px; }
    .pub-ph h1 {
      font-size: 28px;
      font-weight: 800;
      letter-spacing: -0.5px;
      color: var(--txt);
      line-height: 1.2;
    }
    .pub-ph p {
      color: var(--muted);
      font-size: 15px;
      margin-top: 6px;
    }

    /* ── Cards ────────────────────────────────────── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      margin-bottom: 20px;
      transition: var(--transition);
    }
    .card:hover { box-shadow: var(--shadow); }
    
    .card-header {
      padding: 18px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      background: rgba(248, 250, 252, 0.5);
      border-radius: var(--radius) var(--radius) 0 0;
    }
    .card-header h2 { font-size: 16px; font-weight: 700; color: var(--txt); }
    .card-body { padding: 24px; }

    /* ── Alerts ───────────────────────────────────── */
    .alert {
      padding: 14px 18px;
      border-radius: var(--radius-md);
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .alert svg { flex-shrink: 0; margin-top: 2px; }
    .alert-ok  { background: var(--ok-bg); color: var(--ok); border: 1px solid #a7f3d0; }
    .alert-err { background: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; }

    /* ── Forms ────────────────────────────────────── */
    .fgrid { display: grid; gap: 20px; }
    .fg2   { grid-template-columns: 1fr 1fr; }
    .fg3   { grid-template-columns: 1fr 1fr 1fr; }
    .full  { grid-column: 1 / -1; }

    .field { display: flex; flex-direction: column; gap: 8px; }
    .field label {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: var(--txt-light);
    }
    .field label .opt {
      font-weight: 500;
      text-transform: none;
      letter-spacing: 0;
      color: var(--muted);
    }
    .field input, .field select, .field textarea {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: var(--font);
      font-size: 15px;
      color: var(--txt);
      background: var(--bg);
      transition: var(--transition);
    }
    .field input:hover, .field select:hover, .field textarea:hover {
      border-color: #cbd5e1;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
      outline: none;
      border-color: var(--accent);
      background: var(--surface);
      box-shadow: 0 0 0 4px rgba(140, 198, 63, 0.15);
    }

    /* ── Buttons ──────────────────────────────────── */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: var(--radius-sm);
      font-family: var(--font);
      font-size: 14.5px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      white-space: nowrap;
      border: 1px solid transparent;
      user-select: none;
    }
    .btn:active { transform: scale(0.98); }
    .btn-primary { 
      background: var(--accent); 
      color: #fff; 
      box-shadow: var(--shadow-sm);
    }
    .btn-primary:hover { 
      background: var(--accent-d); 
      box-shadow: var(--shadow-accent);
      transform: translateY(-1px);
    }
    .btn-ghost { 
      background: transparent; 
      color: var(--txt-light); 
      border-color: var(--border); 
    }
    .btn-ghost:hover { 
      background: var(--border); 
      color: var(--txt);
    }
    .btn-full { width: 100%; justify-content: center; }
    .btn-lg { padding: 14px 28px; font-size: 16px; }

    /* ── Status pills ─────────────────────────────── */
    .pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .pill::before { content:''; width:8px; height:8px; border-radius:50%; background:currentColor; }
    .pill-pending  { background: var(--warn-bg); color: var(--warn); }
    .pill-confirmed{ background: var(--blue-bg); color: var(--blue); }
    .pill-attended { background: var(--ok-bg); color: var(--ok); }
    .pill-no_show  { background: #f1f5f9; color: #64748b; }
    .pill-cancelled{ background: var(--danger-bg); color: var(--danger); }

    /* ── Slot cards ───────────────────────────────── */
    .slots-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 20px;
    }
    .slot-card {
      background: var(--surface);
      border: 2px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: var(--transition);
      display: flex;
      flex-direction: column;
    }
    .slot-card:hover { 
      border-color: var(--accent); 
      box-shadow: var(--shadow-lg); 
      transform: translateY(-3px);
    }
    .slot-card-head {
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--border);
      background: #f8fafc;
    }
    .slot-time {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.5px;
      color: var(--txt);
    }
    .slot-cupos {
      font-size: 13px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 999px;
      background: var(--accent-bg);
      color: var(--accent-d);
    }
    .slot-form { padding: 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;}
    .slot-form .fgrid { gap: 14px; margin-bottom: 16px; }
    
    .slot-submit {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 12px;
      border-radius: var(--radius-sm);
      background: var(--accent);
      color: #fff;
      font-family: var(--font);
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      transition: var(--transition);
    }
    .slot-submit:hover { 
      background: var(--accent-d); 
      box-shadow: var(--shadow-accent);
    }
    .slot-note {
      margin-top: 12px;
      font-size: 12.5px;
      color: var(--muted);
      display: flex;
      align-items: flex-start;
      gap: 6px;
      line-height: 1.5;
    }

    /* ── Confirm banner ───────────────────────────── */
    .confirm-card {
      background: var(--surface);
      border: 2px solid var(--accent);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 24px;
      box-shadow: var(--shadow-accent);
    }
    .confirm-head {
      background: var(--accent);
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      color: #fff;
    }
    .confirm-head-icon {
      width: 48px; height: 48px;
      border-radius: 50%;
      background: rgba(255,255,255,.2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      backdrop-filter: blur(4px);
    }
    .confirm-head h3 { font-size: 18px; font-weight: 800; }
    .confirm-head p  { font-size: 14.5px; opacity: .9; margin-top: 2px; }
    .confirm-body {
      padding: 24px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px 24px;
    }
    .confirm-field label {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--muted);
      display: block;
      margin-bottom: 6px;
    }
    .confirm-field .val {
      font-size: 15px;
      font-weight: 600;
      color: var(--txt);
    }
    .confirm-code {
      font-family: var(--font-mono);
      font-size: 24px;
      font-weight: 800;
      letter-spacing: 4px;
      color: var(--accent-d);
      background: var(--accent-bg);
      padding: 12px 20px;
      border-radius: var(--radius-sm);
      display: inline-block;
      border: 1px dashed rgba(140, 198, 63, 0.5);
    }
    .confirm-footer {
      padding: 16px 24px;
      background: #f8fafc;
      border-top: 1px solid var(--border);
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    /* ── Service strip ────────────────────────────── */
    .service-strip {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 16px 20px;
      margin-bottom: 24px;
      flex-wrap: wrap;
      box-shadow: var(--shadow-sm);
    }
    .service-strip-left { display: flex; align-items: center; gap: 14px; }
    .service-dot {
      width: 12px; height: 12px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
      box-shadow: 0 0 0 4px var(--accent-bg);
    }
    .service-name { font-size: 16px; font-weight: 700; color: var(--txt); }
    .service-desc { font-size: 14px; color: var(--muted); margin-top: 2px; }
    .date-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      background: var(--bg);
      color: var(--txt-light);
      font-size: 14px;
      font-weight: 600;
      white-space: nowrap;
      border: 1px solid var(--border);
    }

    /* ── Section label ────────────────────────────── */
    .section-label {
      font-size: 13px;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* ── Empty state ──────────────────────────────── */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }
    .empty-state svg { opacity: .3; margin-bottom: 16px; width: 56px; height: 56px; }
    .empty-state h3  { font-size: 18px; font-weight: 700; color: var(--txt); margin-bottom: 8px; }
    .empty-state p   { font-size: 15px; max-width: 400px; margin: 0 auto; }

    /* ── Detail table ─────────────────────────────── */
    .dtable { width: 100%; border-collapse: collapse; }
    .dtable th {
      width: 180px;
      text-align: left;
      font-size: 12.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--txt-light);
      padding: 16px 20px;
      background: #f8fafc;
      border-bottom: 1px solid var(--border);
      border-right: 1px solid var(--border);
      vertical-align: top;
    }
    .dtable td {
      padding: 16px 20px;
      font-size: 15px;
      color: var(--txt);
      border-bottom: 1px solid var(--border);
    }
    .dtable tr:last-child th, .dtable tr:last-child td { border-bottom: none; }

    /* ── Tabs (landing) ───────────────────────────── */
    .tabs {
      display: flex;
      border-bottom: 2px solid var(--border);
      margin-bottom: 28px;
      gap: 8px;
    }
    .tab {
      padding: 12px 24px;
      font-size: 15px;
      font-weight: 600;
      color: var(--muted);
      border: none;
      background: transparent;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tab:hover { color: var(--txt); background: rgba(248,250,252,0.5); border-radius: 8px 8px 0 0; }
    .tab.active { color: var(--accent-d); border-bottom-color: var(--accent); }
    .tab-panel { display: none; animation: fadeIn 0.4s ease; }
    .tab-panel.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* ── Responsive ───────────────────────────────── */
    @media (max-width: 640px) {
      .fg2, .fg3 { grid-template-columns: 1fr; }
      .confirm-body { grid-template-columns: 1fr; }
      .slots-grid   { grid-template-columns: 1fr; }
      .pub-wrap, .pub-wrap-wide { padding: 24px 16px 60px; }
      .pub-topbar { padding: 0 16px; }
      .dtable th { width: 120px; padding: 12px; }
      .dtable td { padding: 12px; }
      .tab { padding: 12px 16px; flex: 1; justify-content: center; font-size: 14px; }
    }
  </style>
</head>
<body>

<header class="pub-topbar">
  <div class="pub-topbar-brand">
    <span class="pub-topbar-brand-dot"></span>
    Municipalidad · Horas
  </div>
  <a href="solicitud_horas.php" class="pub-topbar-link">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    Inicio
  </a>
</header>