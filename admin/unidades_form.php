<?php
// intranet/admin/unidades_form.php (PHP 5.6) — Rediseño con sidebar global
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
  'direccion_id' => 0,
  'nombre' => '',
  'activo' => 1
);

// Direcciones para el select
$direcciones = $pdo->query("SELECT id, codigo, nombre FROM direcciones WHERE activo=1 ORDER BY codigo, nombre")->fetchAll();

if ($editing) {
  $st = $pdo->prepare("SELECT id, direccion_id, nombre, activo FROM unidades WHERE id=? LIMIT 1");
  $st->execute(array($id));
  $db = $st->fetch();
  if (!$db) { http_response_code(404); exit('Unidad no encontrada'); }
  $row = array_merge($row, $db);
} else {
  // Default: primera dirección disponible
  if (!empty($direcciones)) $row['direccion_id'] = (int)$direcciones[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $direccionId = isset($_POST['direccion_id']) ? (int)$_POST['direccion_id'] : 0;
  $nombre      = norm_txt(isset($_POST['nombre']) ? $_POST['nombre'] : '');
  $activo      = isset($_POST['activo']) ? 1 : 0;

  // Validaciones
  if ($direccionId <= 0) {
    $error = 'Debes seleccionar una dirección.';
  } elseif ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 120) {
    $error = 'Nombre obligatorio (máx 120 caracteres).';
  } else {
    // Asegura que la dirección exista y esté activa
    $st = $pdo->prepare("SELECT id FROM direcciones WHERE id=? AND activo=1 LIMIT 1");
    $st->execute(array($direccionId));
    if (!$st->fetch()) $error = 'Dirección inválida.';
  }

  // Unicidad sugerida: unidad única por dirección
  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("SELECT id FROM unidades WHERE direccion_id=? AND nombre=? AND id<>? LIMIT 1");
      $st->execute(array($direccionId, $nombre, $id));
      if ($st->fetch()) $error = 'Ya existe una unidad con ese nombre en la misma dirección.';
    } else {
      $st = $pdo->prepare("SELECT id FROM unidades WHERE direccion_id=? AND nombre=? LIMIT 1");
      $st->execute(array($direccionId, $nombre));
      if ($st->fetch()) $error = 'Ya existe una unidad con ese nombre en la misma dirección.';
    }
  }

  if ($error === '') {
    if ($editing) {
      $st = $pdo->prepare("UPDATE unidades SET direccion_id=?, nombre=?, activo=? WHERE id=?");
      $st->execute(array($direccionId, $nombre, $activo, $id));
      $ok = 'Unidad actualizada.';
    } else {
      $st = $pdo->prepare("INSERT INTO unidades (direccion_id, nombre, activo) VALUES (?,?,?)");
      $st->execute(array($direccionId, $nombre, $activo));
      $id = (int)$pdo->lastInsertId();
      $editing = true;
      $ok = 'Unidad creada (ID: '.$id.').';
    }
  }

  $row['direccion_id'] = $direccionId;
  $row['nombre'] = $nombre;
  $row['activo'] = $activo;
}

// Nombre de la dirección seleccionada (para mostrar en edición)
$dirNombreSel = '';
if ((int)$row['direccion_id'] > 0) {
  foreach ($direcciones as $d) {
    if ((int)$d['id'] === (int)$row['direccion_id']) {
      $dirNombreSel = $d['codigo'] . ' - ' . $d['nombre'];
      break;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · <?php echo $editing ? 'Editar Unidad' : 'Nueva Unidad'; ?></title>
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
          <div class="uf-heading-icon" style="background:var(--amber-subtle);border-color:var(--amber-border);color:var(--amber);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="2" y="7" width="20" height="14" rx="2"/>
              <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
          </div>
          <div class="uf-heading-text">
            <h1><?php echo $editing ? 'Editar unidad' : 'Nueva unidad'; ?></h1>
            <p><?php echo $editing
              ? 'Modifica los datos de la unidad existente.'
              : 'Registra una nueva unidad dentro de una dirección.';
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

        <!-- ════ Sección 1: Dirección padre ════ -->
        <div class="uf-section">
          <div class="uf-section-header">
            <div class="uf-section-icon blue">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
              </svg>
            </div>
            <span class="uf-section-title">Dirección</span>
            <span class="uf-section-subtitle">A qué dirección pertenece esta unidad</span>
          </div>
          <div class="uf-section-body">
            <div class="uf-grid uf-grid-2">

              <!-- Select Dirección -->
              <div class="uf-field uf-col-full">
                <label class="uf-label" for="f-direccion">
                  Dirección <span class="uf-required">*</span>
                </label>
                <div class="uf-select-wrap">
                  <select class="uf-select" id="f-direccion" name="direccion_id" required>
                    <option value="0">-- Seleccione una dirección --</option>
                    <?php foreach ($direcciones as $d): ?>
                      <option value="<?php echo (int)$d['id']; ?>"
                        <?php echo ((int)$row['direccion_id'] === (int)$d['id']) ? 'selected' : ''; ?>>
                        <?php echo _sb_h($d['codigo'] . ' - ' . $d['nombre']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <span class="uf-hint">Selecciona la dirección organizacional a la que pertenecerá esta unidad.</span>
              </div>

              <?php if ($editing && $dirNombreSel): ?>
              <!-- Badge dirección actual -->
              <div class="uf-field uf-col-full">
                <div style="display:inline-flex;align-items:center;gap:8px;padding:9px 14px;background:var(--surface-3);border:1px solid var(--border-3);border-radius:var(--r-md);font-size:13px;color:var(--blue);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                  </svg>
                  <span style="font-weight:600;"><?php echo _sb_h($dirNombreSel); ?></span>
                </div>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </div>

        <!-- ════ Sección 2: Identificación ════ -->
        <div class="uf-section">
          <div class="uf-section-header">
            <div class="uf-section-icon amber">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="2" y="7" width="20" height="14" rx="2"/>
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
              </svg>
            </div>
            <span class="uf-section-title">Identificación</span>
            <span class="uf-section-subtitle">Nombre oficial de la unidad</span>
          </div>
          <div class="uf-section-body">
            <div class="uf-grid uf-grid-2">

              <!-- Nombre -->
              <div class="uf-field uf-col-full">
                <label class="uf-label" for="f-nombre">
                  Nombre de la unidad <span class="uf-required">*</span>
                </label>
                <input class="uf-input" id="f-nombre" name="nombre"
                       value="<?php echo _sb_h($row['nombre']); ?>"
                       placeholder="Ej: Informática / RRHH / Tesorería"
                       maxlength="120"
                       required>
                <span class="uf-hint">Nombre completo de la unidad organizacional. Máximo 120 caracteres.</span>
              </div>

            </div>
          </div>
        </div>

        <!-- ════ Sección 3: Estado ════ -->
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
                <span class="uf-toggle-name">Unidad activa</span>
                <span class="uf-toggle-desc">Si está activa, aparece en listados y puede asignarse a cargos y funcionarios.</span>
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
            <a class="uf-btn-cancel" href="unidades_list.php">Cancelar</a>
            <button class="uf-btn-submit" type="submit">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              <?php echo $editing ? 'Guardar cambios' : 'Crear unidad'; ?>
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