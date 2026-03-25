<?php
// intranet/admin/direcciones_form.php (PHP 5.6)
require __DIR__ . '/_guard.php';

function norm_txt($s) {
  $s = trim((string)$s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = ($id > 0);

$error = '';
$ok = '';

$row = array(
  'codigo' => '',
  'nombre' => '',
  'activo' => 1
);

if ($editing) {
  $st = $pdo->prepare("SELECT id, codigo, nombre, activo FROM direcciones WHERE id=? LIMIT 1");
  $st->execute(array($id));
  $db = $st->fetch();
  if (!$db) { http_response_code(404); exit('Dirección no encontrada'); }
  $row = array_merge($row, $db);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $codigo = strtoupper(norm_txt(isset($_POST['codigo']) ? $_POST['codigo'] : ''));
  $nombre = norm_txt(isset($_POST['nombre']) ? $_POST['nombre'] : '');
  $activo = isset($_POST['activo']) ? 1 : 0;

  if ($codigo === '' || !preg_match('/^[A-Z0-9_]{2,15}$/', $codigo)) {
    $error = 'Código inválido. Usa 2–15 caracteres: A–Z, 0–9, guión bajo (ej: DAF, SECPLAC, DIDECO).';
  } elseif ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 120) {
    $error = 'Nombre obligatorio (máx 120 caracteres).';
  }

  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("SELECT id FROM direcciones WHERE codigo=? AND id<>? LIMIT 1");
      $st->execute(array($codigo, $id));
      if ($st->fetch()) $error = 'Ya existe una dirección con ese código.';
    } else {
      $st = $pdo->prepare("SELECT id FROM direcciones WHERE codigo=? LIMIT 1");
      $st->execute(array($codigo));
      if ($st->fetch()) $error = 'Ya existe una dirección con ese código.';
    }
  }

  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("UPDATE direcciones SET codigo=?, nombre=?, activo=? WHERE id=?");
      $st->execute(array($codigo, $nombre, $activo, $id));
      $ok = 'Dirección actualizada correctamente.';
    } else {
      $st = $pdo->prepare("INSERT INTO direcciones (codigo, nombre, activo) VALUES (?,?,?)");
      $st->execute(array($codigo, $nombre, $activo));
      $id = (int)$pdo->lastInsertId();
      $editing = true;
      $ok = 'Dirección creada (ID: ' . $id . ').';
    }
  }

  $row['codigo'] = $codigo;
  $row['nombre'] = $nombre;
  $row['activo'] = $activo;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · <?php echo $editing ? 'Editar Dirección' : 'Nueva Dirección'; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../static/css/theme.css">
  <link rel="stylesheet" href="../static/css/sidebar.css">
  <link rel="stylesheet" href="../static/css/users_form.css">
  <link rel="icon" type="image/x-icon" href="../static/img/logo.png">
</head>
<body>

<div class="app-shell">

  <?php require __DIR__ . '/../inc/sidebar.php'; ?>

  <main class="main-content">
    <div class="uf-page">

      <!-- ── Heading ──────────────────────────────────────── -->
      <div class="uf-heading">
        <div class="uf-heading-left">
          <div class="uf-heading-icon" style="background:var(--blue-subtle);border-color:var(--blue-border);color:var(--blue);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
          <div class="uf-heading-text">
            <h1><?php echo $editing ? 'Editar dirección' : 'Nueva dirección'; ?></h1>
            <p><?php echo $editing
              ? 'Modifica los datos de la dirección existente.'
              : 'Registra una nueva dirección en la estructura organizacional.';
            ?></p>
          </div>
        </div>
        <?php if ($editing): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:var(--surface-3);border:1px solid var(--border-1);font-family:var(--font-mono);font-size:11px;color:var(--text-subtle);align-self:flex-start;margin-top:4px;">
            ID <?php echo (int)$id; ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- ── Alerts ────────────────────────────────────────── -->
      <?php if ($error): ?>
      <div class="uf-alert uf-alert-error">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
        </svg>
        <?php echo _sb_h($error); ?>
      </div>
      <?php endif; ?>

      <?php if ($ok): ?>
      <div class="uf-alert uf-alert-success">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm3.03 3.97-3.78 5.03-1.78-1.78-1.06 1.06 2.5 2.5.53.53.53-.7 4.32-5.75-1.26-.89z"/>
        </svg>
        <?php echo _sb_h($ok); ?>
      </div>
      <?php endif; ?>

      <!-- ── Form ──────────────────────────────────────────── -->
      <form method="post" autocomplete="off" class="uf-form">
        <input type="hidden" name="csrf" value="<?php echo _sb_h(csrf_token()); ?>">

        <!-- ════ Sección 1: Identificación ════ -->
        <div class="uf-section">
          <div class="uf-section-header">
            <div class="uf-section-icon blue">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
              </svg>
            </div>
            <span class="uf-section-title">Identificación</span>
            <span class="uf-section-subtitle">Código único y nombre oficial</span>
          </div>
          <div class="uf-section-body">
            <div class="uf-grid uf-grid-2">

              <!-- Código -->
              <div class="uf-field">
                <label class="uf-label" for="f-codigo">
                  Código <span class="uf-required">*</span>
                </label>
                <?php if ($editing): ?>
                  <input class="uf-input" id="f-codigo" name="codigo"
                         value="<?php echo _sb_h($row['codigo']); ?>"
                         style="font-family:var(--font-mono);letter-spacing:.5px;font-size:14px;background:var(--surface-3);color:var(--text-muted);cursor:not-allowed;"
                         readonly>
                  <span class="uf-hint">El código no puede modificarse una vez creado.</span>
                <?php else: ?>
                  <input class="uf-input" id="f-codigo" name="codigo"
                         value="<?php echo _sb_h($row['codigo']); ?>"
                         placeholder="DAF"
                         maxlength="15"
                         style="font-family:var(--font-mono);letter-spacing:.5px;font-size:14px;"
                         required>
                  <span class="uf-hint">2–15 caracteres. Solo letras A–Z, números y guión bajo. Se convierte a mayúsculas automáticamente.</span>
                <?php endif; ?>
              </div>

              <!-- Columna derecha: vacía en creación, badge del código en edición -->
              <div class="uf-field">
                <?php if ($editing): ?>
                  <label class="uf-label">Código actual</label>
                  <div style="display:inline-flex;align-items:center;gap:8px;padding:9px 14px;background:var(--surface-3);border:1px solid var(--border-3);border-radius:var(--r-md);font-family:var(--font-mono);font-size:18px;font-weight:700;color:var(--amber);letter-spacing:1px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                      <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
                    </svg>
                    <?php echo _sb_h($row['codigo']); ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Nombre -->
              <div class="uf-field uf-col-full">
                <label class="uf-label" for="f-nombre">
                  Nombre <span class="uf-required">*</span>
                </label>
                <input class="uf-input" id="f-nombre" name="nombre"
                       value="<?php echo _sb_h($row['nombre']); ?>"
                       placeholder="Dirección de Administración y Finanzas"
                       maxlength="120"
                       required>
                <span class="uf-hint">Nombre completo y oficial de la dirección. Máximo 120 caracteres.</span>
              </div>

            </div>
          </div>
        </div>

        <!-- ════ Sección 2: Estado ════ -->
        <div class="uf-section">
          <div class="uf-section-header">
            <div class="uf-section-icon green">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
            </div>
            <span class="uf-section-title">Estado</span>
            <span class="uf-section-subtitle">Visibilidad y disponibilidad en el sistema</span>
          </div>
          <div class="uf-section-body">
            <div class="uf-toggle-row" style="border-top:none;padding:4px 0 0 0;">
              <div class="uf-toggle-info">
                <span class="uf-toggle-name">Dirección activa</span>
                <span class="uf-toggle-desc">Si está activa, aparece en listados y puede asignarse a unidades y cargos.</span>
              </div>
              <label class="uf-toggle">
                <input type="checkbox" name="activo" value="1"
                       <?php echo ((int)$row['activo'] === 1 ? 'checked' : ''); ?>>
                <span class="uf-toggle-track"></span>
              </label>
            </div>
          </div>
        </div>

        <!-- ════ Footer ════ -->
        <div class="uf-footer">
          <div class="uf-footer-info">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor">
              <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
            </svg>
            Los campos <span style="color:var(--red);font-weight:700;margin:0 2px;">*</span> son obligatorios.
          </div>
          <div class="uf-footer-actions">
            <a class="uf-btn-cancel" href="direcciones_list.php">Cancelar</a>
            <button class="uf-btn-submit" type="submit">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              <?php echo $editing ? 'Guardar cambios' : 'Crear dirección'; ?>
            </button>
          </div>
        </div>

      </form>
    </div><!-- /.uf-page -->
  </main>

  </div><!-- /body-layout (abierto por sidebar.php) -->
</div><!-- /app-shell -->

</body>
</html>