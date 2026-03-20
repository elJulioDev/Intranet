<?php
// intranet/admin/users_form.php (PHP 5.6)
require __DIR__ . '/_guard.php';

/* ──────────────────────────────────────────
   Helpers
────────────────────────────────────────── */
function norm_rut_admin($rut) {
  $rut = strtoupper(trim((string)$rut));
  $rut = str_replace(array('.', '-', ' '), '', $rut);
  return $rut;
}

function valid_rut_format($rut) {
  return (bool)preg_match('/^\d{7,9}[0-9K]?$/', $rut);
}

function table_has_column(PDO $pdo, $table, $column) {
  try {
    $st = $pdo->prepare("
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $st->execute(array($table, $column));
    return (bool)$st->fetchColumn();
  } catch (Exception $e) { return false; }
}

/* ──────────────────────────────────────────
   Datos para selects
────────────────────────────────────────── */
$roles = array();
try {
  $roles = $pdo->query(
    "SELECT id, codigo, nombre FROM roles WHERE activo=1 ORDER BY nombre"
  )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $roles = array(); }

$dirHasCodigo = table_has_column($pdo, 'direcciones', 'codigo');
$dirHasActivo = table_has_column($pdo, 'direcciones', 'activo');
$uniHasActivo = table_has_column($pdo, 'unidades',    'activo');
$carHasActivo = table_has_column($pdo, 'cargos',      'activo');

$dirLabel = $dirHasCodigo
  ? "CONCAT(d.codigo,' - ',d.nombre)"
  : "d.nombre";

$where = array();
if ($carHasActivo) $where[] = "c.activo=1";
if ($uniHasActivo) $where[] = "u.activo=1";
if ($dirHasActivo) $where[] = "d.activo=1";
$whereSql = !empty($where) ? ("WHERE ".implode(" AND ", $where)) : "";

$cargos    = array();
$cargosErr = '';

$sqlCargos = "
  SELECT c.id,
         CONCAT($dirLabel,' / ',u.nombre,' / ',c.nombre) AS label
  FROM cargos c
  JOIN unidades u    ON u.id=c.unidad_id
  JOIN direcciones d ON d.id=u.direccion_id
  $whereSql
  ORDER BY ".($dirHasCodigo ? "d.codigo," : "")." d.nombre, u.nombre, c.nombre
";

try {
  $st = $pdo->query($sqlCargos);
  $cargos = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
} catch (Exception $e) {
  $cargos    = array();
  $cargosErr = $e->getMessage();
}

/* ──────────────────────────────────────────
   Estado del formulario
────────────────────────────────────────── */
$err = '';
$ok  = '';

$defaults = array(
  'rut'          => '',
  'nombres'      => '',
  'apellidos'    => '',
  'email'        => '',
  'telefono'     => '',
  'direccion_dom'=> '',
  'activo'       => 1,
  'is_superadmin'=> 0,
  'roles'        => array(),
  'password'     => '',
  'password2'    => '',
  'cargo_id'     => 0,
  'cargo_tipo'   => 'titular',
  'fecha_desde'  => date('Y-m-d'),
  'fecha_hasta'  => ''
);
$data = $defaults;

/* ──────────────────────────────────────────
   POST
────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $data['rut']           = norm_rut_admin(isset($_POST['rut']) ? $_POST['rut'] : '');
  $data['nombres']       = trim((string)(isset($_POST['nombres']) ? $_POST['nombres'] : ''));
  $data['apellidos']     = trim((string)(isset($_POST['apellidos']) ? $_POST['apellidos'] : ''));
  $data['email']         = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));
  $data['telefono']      = trim((string)(isset($_POST['telefono']) ? $_POST['telefono'] : ''));
  $data['direccion_dom'] = trim((string)(isset($_POST['direccion_dom']) ? $_POST['direccion_dom'] : ''));
  // Checkboxes: presentes solo si marcados → value="1"
  $data['activo']        = isset($_POST['activo'])       ? 1 : 0;
  $data['is_superadmin'] = isset($_POST['is_superadmin'])? 1 : 0;
  $data['password']      = (string)(isset($_POST['password'])  ? $_POST['password']  : '');
  $data['password2']     = (string)(isset($_POST['password2']) ? $_POST['password2'] : '');
  $data['roles']         = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : array();
  $data['cargo_id']      = (int)(isset($_POST['cargo_id']) ? $_POST['cargo_id'] : 0);
  $data['cargo_tipo']    = trim((string)(isset($_POST['cargo_tipo']) ? $_POST['cargo_tipo'] : 'titular'));
  $data['fecha_desde']   = (string)(isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : date('Y-m-d'));
  $data['fecha_hasta']   = trim((string)(isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : ''));

  // Validaciones
  if ($data['rut'] === '' || !valid_rut_format($data['rut'])) {
    $err = 'RUT inválido. Ingresa sin puntos ni guión (ej: 12345678K).';
  } elseif ($data['nombres'] === '' || $data['apellidos'] === '') {
    $err = 'Nombres y apellidos son obligatorios.';
  } elseif ($data['password'] === '' || strlen($data['password']) < 3) {
    $err = 'La clave debe tener al menos 3 caracteres.';
  } elseif ($data['password'] !== $data['password2']) {
    $err = 'Las claves no coinciden.';
  } elseif ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $err = 'Email inválido.';
  } elseif (!in_array($data['cargo_tipo'], array('titular','subrogante','suplente'), true)) {
    $err = 'Tipo de cargo inválido.';
  } else {
    $st = $pdo->prepare("SELECT id FROM funcionarios WHERE rut=? LIMIT 1");
    $st->execute(array($data['rut']));
    if ($st->fetch()) $err = 'Ya existe un funcionario con ese RUT.';

    if ($err === '' && $data['email'] !== '') {
      $st = $pdo->prepare("SELECT id FROM funcionarios WHERE email=? LIMIT 1");
      $st->execute(array($data['email']));
      if ($st->fetch()) $err = 'Ya existe un funcionario con ese email.';
    }
  }

  if ($err === '') {
    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    if (!$hash) {
      $err = 'No se pudo generar hash de contraseña.';
    } else {
      $pdo->beginTransaction();
      try {
        $ins = $pdo->prepare("
          INSERT INTO funcionarios
            (rut, nombres, apellidos, email, telefono, direccion_dom,
             clave_hash, activo, is_superadmin)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute(array(
          $data['rut'],
          $data['nombres'],
          $data['apellidos'],
          $data['email']         !== '' ? $data['email']         : null,
          $data['telefono']      !== '' ? $data['telefono']      : null,
          $data['direccion_dom'] !== '' ? $data['direccion_dom'] : null,
          $hash,
          (int)$data['activo'],
          (int)$data['is_superadmin']
        ));
        $newId = (int)$pdo->lastInsertId();

        if (!empty($data['roles'])) {
          $insR = $pdo->prepare("INSERT INTO funcionario_roles (funcionario_id, rol_id) VALUES (?, ?)");
          foreach ($data['roles'] as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) $insR->execute(array($newId, $rid));
          }
        }

        if ($data['cargo_id'] > 0) {
          $st = $pdo->prepare("SELECT id FROM cargos WHERE id=? LIMIT 1");
          $st->execute(array($data['cargo_id']));
          if (!$st->fetch()) throw new Exception('Cargo no existe.');
          $insC = $pdo->prepare("
            INSERT INTO funcionario_cargos
              (funcionario_id, cargo_id, tipo, fecha_desde, fecha_hasta, activo, observacion)
            VALUES (?, ?, ?, ?, ?, 1, ?)
          ");
          $insC->execute(array(
            $newId,
            (int)$data['cargo_id'],
            $data['cargo_tipo'],
            $data['fecha_desde'],
            ($data['fecha_hasta'] !== '' ? $data['fecha_hasta'] : null),
            'Creación inicial'
          ));
        }

        $pdo->commit();
        if (function_exists('acl_cache_reset')) acl_cache_reset();
        $ok   = 'Usuario creado correctamente (ID: '.$newId.').';
        $data = $defaults;

      } catch (Exception $e) {
        $pdo->rollBack();
        $err = 'Error al crear usuario: '.$e->getMessage();
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Crear Usuario</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../static/css/theme.css">
  <link rel="stylesheet" href="../static/css/users_form.css">
  <link rel="icon" type="image/x-icon" href="../static/img/logo.png">
</head>
<body>

<div class="uf-page">

  <!-- ── Topbar ─────────────────────────────────────────── -->
  <header class="uf-topbar">
    <a class="uf-topbar-back" href="index.php">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
      Admin
    </a>
    <span class="uf-topbar-sep">/</span>
    <span class="uf-topbar-title">Crear funcionario</span>
    <div class="uf-topbar-spacer"></div>
    <span class="uf-topbar-meta">users_form.php</span>
  </header>

  <div class="uf-wrap">

    <!-- ── Heading ──────────────────────────────────────── -->
    <div class="uf-heading">
      <div class="uf-heading-left">
        <div class="uf-heading-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <line x1="19" y1="8" x2="19" y2="14"/>
            <line x1="16" y1="11" x2="22" y2="11"/>
          </svg>
        </div>
        <div class="uf-heading-text">
          <h1>Crear funcionario</h1>
          <p>Registra un nuevo usuario en el sistema de gestión interna.</p>
        </div>
      </div>
    </div>

    <!-- ── Alerts ───────────────────────────────────────── -->
    <?php if ($err): ?>
    <div class="uf-alert uf-alert-error">
      <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
        <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
      </svg>
      <?php echo h($err); ?>
    </div>
    <?php endif; ?>

    <?php if ($ok): ?>
    <div class="uf-alert uf-alert-success">
      <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
        <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm3.03 3.97-3.78 5.03-1.78-1.78-1.06 1.06 2.5 2.5.53.53.53-.7 4.32-5.75-1.26-.89z"/>
      </svg>
      <?php echo h($ok); ?>
    </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" class="uf-form">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">

      <!-- ════ SECCIÓN 1: Identidad ════ -->
      <div class="uf-section">
        <div class="uf-section-header">
          <div class="uf-section-icon blue">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/>
            </svg>
          </div>
          <span class="uf-section-title">Datos personales</span>
          <span class="uf-section-subtitle">Información de identificación</span>
        </div>

        <div class="uf-section-body">
          <div class="uf-grid uf-grid-2">

            <div class="uf-field">
              <label class="uf-label" for="f-rut">
                RUT <span class="uf-required">*</span>
              </label>
              <input
                class="uf-input"
                id="f-rut"
                name="rut"
                value="<?php echo h($data['rut']); ?>"
                placeholder="12345678K"
                autocomplete="off"
                required>
              <span class="uf-hint">Sin puntos ni guión. Se normaliza automáticamente.</span>
            </div>

            <div class="uf-field">
              <!-- spacer para alinear grid — el toggle de activo va en la sección 2 -->
            </div>

            <div class="uf-field">
              <label class="uf-label" for="f-nombres">
                Nombres <span class="uf-required">*</span>
              </label>
              <input
                class="uf-input"
                id="f-nombres"
                name="nombres"
                value="<?php echo h($data['nombres']); ?>"
                placeholder="Juan Andrés"
                required>
            </div>

            <div class="uf-field">
              <label class="uf-label" for="f-apellidos">
                Apellidos <span class="uf-required">*</span>
              </label>
              <input
                class="uf-input"
                id="f-apellidos"
                name="apellidos"
                value="<?php echo h($data['apellidos']); ?>"
                placeholder="Pérez González"
                required>
            </div>

            <div class="uf-field">
              <label class="uf-label" for="f-email">Email</label>
              <input
                class="uf-input"
                id="f-email"
                name="email"
                type="email"
                value="<?php echo h($data['email']); ?>"
                placeholder="usuario@municipio.cl">
            </div>

            <div class="uf-field">
              <label class="uf-label" for="f-tel">Teléfono</label>
              <input
                class="uf-input"
                id="f-tel"
                name="telefono"
                value="<?php echo h($data['telefono']); ?>"
                placeholder="+56 9 1234 5678">
            </div>

            <div class="uf-field uf-col-full">
              <label class="uf-label" for="f-dom">Domicilio</label>
              <input
                class="uf-input"
                id="f-dom"
                name="direccion_dom"
                value="<?php echo h($data['direccion_dom']); ?>"
                placeholder="Calle Nombre 123, Comuna">
            </div>

          </div>
        </div>
      </div>

      <!-- ════ SECCIÓN 2: Acceso ════ -->
      <div class="uf-section">
        <div class="uf-section-header">
          <div class="uf-section-icon green">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </div>
          <span class="uf-section-title">Credenciales y acceso</span>
          <span class="uf-section-subtitle">Clave y permisos de cuenta</span>
        </div>

        <div class="uf-section-body">
          <div class="uf-grid uf-grid-2" style="margin-bottom:16px;">

            <div class="uf-field">
              <label class="uf-label" for="f-pw">
                Clave <span class="uf-required">*</span>
              </label>
              <div class="uf-input-pw-wrap">
                <input
                  class="uf-input"
                  id="f-pw"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  autocomplete="new-password"
                  required>
                <button type="button" class="uf-input-pw-toggle" onclick="togglePw('f-pw',this)" tabindex="-1">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>
              <span class="uf-hint">Se guarda como hash bcrypt. Mínimo 3 caracteres.</span>
            </div>

            <div class="uf-field">
              <label class="uf-label" for="f-pw2">
                Repetir clave <span class="uf-required">*</span>
              </label>
              <div class="uf-input-pw-wrap">
                <input
                  class="uf-input"
                  id="f-pw2"
                  name="password2"
                  type="password"
                  placeholder="••••••••"
                  autocomplete="new-password"
                  required>
                <button type="button" class="uf-input-pw-toggle" onclick="togglePw('f-pw2',this)" tabindex="-1">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>
            </div>

          </div>

          <!-- Toggles de cuenta -->
          <div class="uf-toggle-row">
            <div class="uf-toggle-info">
              <span class="uf-toggle-name">Cuenta activa</span>
              <span class="uf-toggle-desc">El funcionario puede iniciar sesión en el sistema.</span>
            </div>
            <label class="uf-toggle">
              <input type="checkbox" name="activo" value="1"
                <?php echo ((int)$data['activo'] === 1 ? 'checked' : ''); ?>>
              <span class="uf-toggle-track"></span>
            </label>
          </div>

          <div class="uf-toggle-row">
            <div class="uf-toggle-info">
              <span class="uf-toggle-name">Superadministrador</span>
              <span class="uf-toggle-desc">Acceso completo al panel de administración.</span>
            </div>
            <label class="uf-toggle is-amber">
              <input type="checkbox" name="is_superadmin" value="1"
                <?php echo ((int)$data['is_superadmin'] === 1 ? 'checked' : ''); ?>>
              <span class="uf-toggle-track"></span>
            </label>
          </div>

        </div>
      </div>

      <!-- ════ SECCIÓN 3: Roles ════ -->
      <div class="uf-section">
        <div class="uf-section-header">
          <div class="uf-section-icon purple">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>
            </svg>
          </div>
          <span class="uf-section-title">Roles</span>
          <span class="uf-section-subtitle">Opcional — define permisos del sistema RBAC</span>
        </div>

        <div class="uf-section-body">
          <?php if (empty($roles)): ?>
            <div class="uf-empty-note">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              No hay roles activos creados en el sistema.
            </div>
          <?php else: ?>
            <div class="uf-roles-grid">
              <?php foreach ($roles as $r): ?>
                <?php
                  $rid = (int)$r['id'];
                  $checked = in_array((string)$rid, array_map('strval', (array)$data['roles']));
                ?>
                <label class="uf-role-item">
                  <input type="checkbox" name="roles[]" value="<?php echo $rid; ?>"
                    <?php echo $checked ? 'checked' : ''; ?>>
                  <div class="uf-role-check-wrap">
                    <div class="uf-role-check">
                      <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <polyline points="2 6 5 9 10 3"/>
                      </svg>
                    </div>
                    <span class="uf-role-label"><?php echo h($r['nombre']); ?></span>
                  </div>
                  <span class="uf-role-code"><?php echo h($r['codigo']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ════ SECCIÓN 4: Cargo ════ -->
      <div class="uf-section">
        <div class="uf-section-header">
          <div class="uf-section-icon amber">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="2" y="7" width="20" height="14" rx="2"/>
              <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
          </div>
          <span class="uf-section-title">Cargo vigente</span>
          <span class="uf-section-subtitle">Opcional — asigna función desde hoy</span>
        </div>

        <div class="uf-section-body">
          <?php if (empty($cargos)): ?>
            <div class="uf-empty-note">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              No hay cargos disponibles para asignar.
            </div>
            <?php if (!empty($cargosErr)): ?>
              <div class="uf-debug" style="margin-top:8px;">SQL error: <?php echo h($cargosErr); ?></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="uf-grid uf-grid-2">

              <div class="uf-field uf-col-full uf-cargo-select-wrap">
                <label class="uf-label" for="f-cargo">Cargo</label>
                <div class="uf-select-wrap">
                  <select class="uf-select" id="f-cargo" name="cargo_id">
                    <option value="0">— Sin asignar —</option>
                    <?php foreach ($cargos as $c): ?>
                      <option value="<?php echo (int)$c['id']; ?>"
                        <?php echo ((int)$data['cargo_id'] === (int)$c['id'] ? 'selected' : ''); ?>>
                        <?php echo h($c['label']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="uf-field uf-col-full">
                <label class="uf-label">Tipo de vinculación</label>
                <div class="uf-tipo-row">
                  <?php foreach (array('titular','subrogante','suplente') as $tipo): ?>
                    <div class="uf-tipo-opt">
                      <input
                        type="radio"
                        name="cargo_tipo"
                        id="tipo-<?php echo $tipo; ?>"
                        value="<?php echo $tipo; ?>"
                        <?php echo ($data['cargo_tipo'] === $tipo ? 'checked' : ''); ?>>
                      <label class="uf-tipo-label" for="tipo-<?php echo $tipo; ?>">
                        <?php echo ucfirst($tipo); ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="uf-field">
                <label class="uf-label" for="f-desde">Desde</label>
                <input
                  class="uf-input"
                  id="f-desde"
                  name="fecha_desde"
                  type="date"
                  value="<?php echo h($data['fecha_desde']); ?>">
              </div>

              <div class="uf-field">
                <label class="uf-label" for="f-hasta">Hasta <span style="color:var(--text-subtle);font-weight:400;">(opcional)</span></label>
                <input
                  class="uf-input"
                  id="f-hasta"
                  name="fecha_hasta"
                  type="date"
                  value="<?php echo h($data['fecha_hasta']); ?>">
                <span class="uf-hint">Dejar vacío si el cargo es indefinido.</span>
              </div>

            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ════ Footer de acciones ════ -->
      <div class="uf-footer">
        <div class="uf-footer-info">
          <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
          </svg>
          Los campos marcados con <span style="color:var(--red);font-weight:700;margin:0 2px;">*</span> son obligatorios.
        </div>
        <div class="uf-footer-actions">
          <a class="uf-btn-cancel" href="index.php">Cancelar</a>
          <button class="uf-btn-submit" type="submit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Crear funcionario
          </button>
        </div>
      </div>

    </form>
  </div><!-- /.uf-wrap -->
</div><!-- /.uf-page -->

<script>
/* Toggle visibilidad de contraseña */
function togglePw(id, btn) {
  var inp = document.getElementById(id);
  if (!inp) return;
  var show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  btn.style.color = show ? 'var(--blue)' : '';
}

/* Sincroniza checkbox de roles con su estilo */
(function () {
  var items = document.querySelectorAll('.uf-role-item');
  for (var i = 0; i < items.length; i++) {
    (function (item) {
      var cb = item.querySelector('input[type="checkbox"]');
      if (!cb) return;
      function update() {
        item.style.borderColor = cb.checked ? 'var(--purple-border)' : '';
        item.style.background  = cb.checked ? 'rgba(163,113,247,.08)' : '';
      }
      update();
      cb.addEventListener('change', update);
    })(items[i]);
  }
})();
</script>

</body>
</html>