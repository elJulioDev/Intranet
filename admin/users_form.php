<?php
// intranet/admin/users_form.php (PHP 5.6)
// Formulario para CREAR usuario funcionario + (opcional) asignar roles y cargo vigente

require __DIR__ . '/_guard.php'; // exige login + superadmin

// Helpers
function norm_rut_admin($rut) {
  $rut = strtoupper(trim((string)$rut));
  $rut = str_replace(array('.', '-', ' '), '', $rut);
  return $rut;
}

function valid_rut_format($rut) {
  // Validación simple de formato (no dígito verificador completo).
  // Acepta: 7-9 dígitos + opcional DV (0-9 o K). Ej: 175202056, 12345678K
  return (bool)preg_match('/^\d{7,9}[0-9K]?$/', $rut);
}

/**
 * Detecta si una tabla tiene una columna (para armar SQL compatible con tu esquema).
 */
function table_has_column(PDO $pdo, $table, $column) {
  try {
    // Más robusto que SHOW COLUMNS (no explota si la tabla no existe)
    $st = $pdo->prepare("
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $st->execute(array($table, $column));
    return (bool)$st->fetchColumn();
  } catch (Exception $e) {
    // Si el hosting restringe information_schema, no mates la página.
    return false;
  }
}

/* ------------------ Datos para selects ------------------ */

// Roles
$roles = array();
try {
  $roles = $pdo->query("SELECT id, codigo, nombre FROM roles WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $roles = array();
}

// Cargos (robusto a tu esquema)
$dirHasCodigo  = table_has_column($pdo, 'direcciones', 'codigo');
$dirHasActivo  = table_has_column($pdo, 'direcciones', 'activo');
$uniHasActivo  = table_has_column($pdo, 'unidades', 'activo');
$carHasActivo  = table_has_column($pdo, 'cargos', 'activo');

$dirLabel = $dirHasCodigo
  ? "CONCAT(d.codigo,' - ',d.nombre)"
  : "d.nombre";

$where = array();
if ($carHasActivo) $where[] = "c.activo=1";
if ($uniHasActivo) $where[] = "u.activo=1";
if ($dirHasActivo) $where[] = "d.activo=1";
$whereSql = !empty($where) ? ("WHERE ".implode(" AND ", $where)) : "";

$cargos = array();
$cargosErr = '';

$sqlCargos = "
  SELECT
    c.id,
    CONCAT($dirLabel,' / ',u.nombre,' / ',c.nombre) AS label
  FROM cargos c
  JOIN unidades u     ON u.id=c.unidad_id
  JOIN direcciones d  ON d.id=u.direccion_id
  $whereSql
  ORDER BY ".($dirHasCodigo ? "d.codigo," : "")." d.nombre, u.nombre, c.nombre
";

try {
  $st = $pdo->query($sqlCargos);
  $cargos = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
} catch (Exception $e) {
  $cargos = array();
  $cargosErr = $e->getMessage();
}

/* ------------------ Estado del formulario ------------------ */

$err = '';
$ok  = '';

$defaults = array(
  'rut' => '',
  'nombres' => '',
  'apellidos' => '',
  'email' => '',
  'telefono' => '',
  'direccion_dom' => '',
  'activo' => 1,
  'is_superadmin' => 0,
  'roles' => array(),
  'password' => '',
  'password2' => '',
  'cargo_id' => 0,
  'cargo_tipo' => 'titular',
  'fecha_desde' => date('Y-m-d'),
  'fecha_hasta' => ''
);

$data = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $data['rut'] = norm_rut_admin(isset($_POST['rut']) ? $_POST['rut'] : '');
  $data['nombres'] = trim((string)(isset($_POST['nombres']) ? $_POST['nombres'] : ''));
  $data['apellidos'] = trim((string)(isset($_POST['apellidos']) ? $_POST['apellidos'] : ''));
  $data['email'] = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));
  $data['telefono'] = trim((string)(isset($_POST['telefono']) ? $_POST['telefono'] : ''));
  $data['direccion_dom'] = trim((string)(isset($_POST['direccion_dom']) ? $_POST['direccion_dom'] : ''));
  $data['activo'] = (int)(isset($_POST['activo']) ? $_POST['activo'] : 0);
  $data['is_superadmin'] = (int)(isset($_POST['is_superadmin']) ? $_POST['is_superadmin'] : 0);

  $data['password'] = (string)(isset($_POST['password']) ? $_POST['password'] : '');
  $data['password2'] = (string)(isset($_POST['password2']) ? $_POST['password2'] : '');

  $data['roles'] = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : array();

  $data['cargo_id'] = (int)(isset($_POST['cargo_id']) ? $_POST['cargo_id'] : 0);
  $data['cargo_tipo'] = trim((string)(isset($_POST['cargo_tipo']) ? $_POST['cargo_tipo'] : 'titular'));
  $data['fecha_desde'] = (string)(isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : date('Y-m-d'));
  $data['fecha_hasta'] = trim((string)(isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : ''));

  // Validaciones
  if ($data['rut'] === '' || !valid_rut_format($data['rut'])) {
    $err = 'RUT inválido. Usa sin puntos/guión (ej: 12345678K).';
  } elseif ($data['nombres'] === '' || $data['apellidos'] === '') {
    $err = 'Nombres y apellidos son obligatorios.';
  } elseif ($data['password'] === '' || strlen($data['password']) < 3) {
    $err = 'Debes ingresar una clave (mínimo 3 caracteres).';
  } elseif ($data['password'] !== $data['password2']) {
    $err = 'Las claves no coinciden.';
  } elseif ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $err = 'Email inválido.';
  } elseif (!in_array($data['cargo_tipo'], array('titular','subrogante','suplente'), true)) {
    $err = 'Tipo de cargo inválido.';
  } else {
    // Unicidad RUT / email
    $st = $pdo->prepare("SELECT id FROM funcionarios WHERE rut=? LIMIT 1");
    $st->execute(array($data['rut']));
    if ($st->fetch()) {
      $err = 'Ya existe un funcionario con ese RUT.';
    }

    if ($err === '' && $data['email'] !== '') {
      $st = $pdo->prepare("SELECT id FROM funcionarios WHERE email=? LIMIT 1");
      $st->execute(array($data['email']));
      if ($st->fetch()) {
        $err = 'Ya existe un funcionario con ese email.';
      }
    }
  }

  if ($err === '') {
    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    if (!$hash) {
      $err = 'No se pudo generar hash de contraseña.';
    } else {
      $pdo->beginTransaction();
      try {
        // Crear funcionario
        $ins = $pdo->prepare("
          INSERT INTO funcionarios
          (rut, nombres, apellidos, email, telefono, direccion_dom, clave_hash, activo, is_superadmin)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute(array(
          $data['rut'],
          $data['nombres'],
          $data['apellidos'],
          $data['email'] !== '' ? $data['email'] : null,
          $data['telefono'] !== '' ? $data['telefono'] : null,
          $data['direccion_dom'] !== '' ? $data['direccion_dom'] : null,
          $hash,
          (int)$data['activo'],
          (int)$data['is_superadmin']
        ));

        $newId = (int)$pdo->lastInsertId();

        // Asignar roles (opcional)
        if (!empty($data['roles'])) {
          $insR = $pdo->prepare("INSERT INTO funcionario_roles (funcionario_id, rol_id) VALUES (?, ?)");
          foreach ($data['roles'] as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) $insR->execute(array($newId, $rid));
          }
        }

        // Asignar cargo vigente (opcional)
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

        $ok = 'Usuario creado correctamente (ID: '.$newId.').';
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
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;}
    .row{margin:10px 0;}
    label{display:block;font-weight:bold;margin-bottom:4px;}
    input,select,textarea{padding:8px;border:1px solid #ddd;border-radius:8px;max-width:520px;width:100%;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:900px;}
    .card{border:1px solid #eee;border-radius:12px;padding:14px;max-width:900px;}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;}
    .btn:hover{background:#111;color:#fff;}
    .muted{color:#666;}
  </style>
</head>
<body>


<?php if (empty($cargos) && !empty($cargosErr)): ?>
  <div class="muted" style="margin-top:6px;">
    <strong>Error SQL:</strong> <?php echo h($cargosErr); ?>
  </div>
<?php endif; ?>



  <h2>Crear usuario (funcionario)</h2>
  <p><a class="btn" href="index.php">← Admin</a></p>

  <?php if ($err): ?><p style="color:#b00020;"><strong><?php echo h($err); ?></strong></p><?php endif; ?>
  <?php if ($ok): ?><p style="color:#0a7a2f;"><strong><?php echo h($ok); ?></strong></p><?php endif; ?>

  <div class="card">
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">

      <div class="grid">
        <div class="row">
          <label>RUT (sin puntos/guión) *</label>
          <input name="rut" value="<?php echo h($data['rut']); ?>" placeholder="12345678K" required>
          <div class="muted">Se guardará normalizado (sin . ni -).</div>
        </div>

        <div class="row">
          <label>Activo</label>
          <select name="activo">
            <option value="1" <?php echo ((int)$data['activo']===1?'selected':''); ?>>Sí</option>
            <option value="0" <?php echo ((int)$data['activo']===0?'selected':''); ?>>No</option>
          </select>
        </div>

        <div class="row">
          <label>Nombres *</label>
          <input name="nombres" value="<?php echo h($data['nombres']); ?>" required>
        </div>

        <div class="row">
          <label>Apellidos *</label>
          <input name="apellidos" value="<?php echo h($data['apellidos']); ?>" required>
        </div>

        <div class="row">
          <label>Email (opcional)</label>
          <input name="email" value="<?php echo h($data['email']); ?>" placeholder="usuario@muni.cl">
        </div>

        <div class="row">
          <label>Teléfono (opcional)</label>
          <input name="telefono" value="<?php echo h($data['telefono']); ?>">
        </div>

        <div class="row" style="grid-column:1 / -1;">
          <label>Domicilio (opcional)</label>
          <input name="direccion_dom" value="<?php echo h($data['direccion_dom']); ?>">
        </div>

        <div class="row">
          <label>Clave *</label>
          <input type="password" name="password" value="" required>
          <div class="muted">Se guardará como hash bcrypt.</div>
        </div>

        <div class="row">
          <label>Repetir clave *</label>
          <input type="password" name="password2" value="" required>
        </div>

        <div class="row">
          <label>¿Superadmin?</label>
          <select name="is_superadmin">
            <option value="0" <?php echo ((int)$data['is_superadmin']===0?'selected':''); ?>>No</option>
            <option value="1" <?php echo ((int)$data['is_superadmin']===1?'selected':''); ?>>Sí</option>
          </select>
          <div class="muted">Puente para acceder al admin (antes de RBAC completo).</div>
        </div>

        <div class="row" style="grid-column:1 / -1;">
          <label>Roles (opcional)</label>
          <?php if (empty($roles)): ?>
            <div class="muted">No hay roles creados.</div>
          <?php else: ?>
            <?php foreach($roles as $r): ?>
              <label style="display:block;font-weight:normal;margin:6px 0;">
                <input type="checkbox" name="roles[]" value="<?php echo (int)$r['id']; ?>">
                <?php echo h($r['nombre'].' ('.$r['codigo'].')'); ?>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="row" style="grid-column:1 / -1;">
          <hr style="border:none;border-top:1px solid #eee;margin:4px 0 10px 0;">
          <strong>Asignación de cargo (opcional)</strong>
          <div class="muted">Si seleccionas un cargo, se creará un registro en <code>funcionario_cargos</code>.</div>
        </div>

        <div class="row" style="grid-column:1 / -1;">
          <label>Cargo</label>
          <select name="cargo_id">
            <option value="0">-- Sin cargo --</option>
            <?php foreach($cargos as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$data['cargo_id']===(int)$c['id']?'selected':''); ?>>
                <?php echo h($c['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <?php if (empty($cargos)): ?>
            <div class="muted" style="margin-top:6px;">
              No hay cargos para mostrar (o la consulta falló).
              <?php if (!empty($cargosErr)): ?>
                <br><strong>Error SQL:</strong> <?php echo h($cargosErr); ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="row">
          <label>Tipo</label>
          <select name="cargo_tipo">
            <option value="titular" <?php echo ($data['cargo_tipo']==='titular'?'selected':''); ?>>titular</option>
            <option value="subrogante" <?php echo ($data['cargo_tipo']==='subrogante'?'selected':''); ?>>subrogante</option>
            <option value="suplente" <?php echo ($data['cargo_tipo']==='suplente'?'selected':''); ?>>suplente</option>
          </select>
        </div>

        <div class="row">
          <label>Desde</label>
          <input type="date" name="fecha_desde" value="<?php echo h($data['fecha_desde']); ?>">
        </div>

        <div class="row">
          <label>Hasta (opcional)</label>
          <input type="date" name="fecha_hasta" value="<?php echo h($data['fecha_hasta']); ?>">
        </div>

      </div>

      <br>
      <button class="btn" type="submit">Crear usuario</button>
    </form>
  </div>

</body>
</html>
