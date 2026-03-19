<?php
// intranet/admin/rol_permisos.php (PHP 5.6)
require __DIR__ . '/_guard.php'; // superadmin

$rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
$msg = '';
$err = '';

// Roles
$roles = array();
try {
  $roles = $pdo->query("SELECT id, codigo, nombre, activo FROM roles ORDER BY activo DESC, nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = 'No se pudieron cargar roles: '.$e->getMessage();
}
if ($rid <= 0 && !empty($roles)) $rid = (int)$roles[0]['id'];

// Recursos (tus páginas/sistemas)
$recursos = array();
try {
  $recursos = $pdo->query("
    SELECT id, codigo, nombre, tipo, ruta, activo
    FROM recursos
    WHERE activo=1
    ORDER BY tipo, nombre
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = $err ?: 'No se pudieron cargar recursos: '.$e->getMessage();
}

// Acciones (ya tienes: view/create/edit/delete/export/admin)
$acciones = array();
try {
  $acciones = $pdo->query("SELECT id, codigo, nombre FROM acciones ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = $err ?: 'No se pudieron cargar acciones: '.$e->getMessage();
}

// Guardar permisos del rol
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ridPost = isset($_POST['rid']) ? (int)$_POST['rid'] : 0;

  if ($ridPost <= 0) {
    $err = 'Rol inválido.';
  } else {
    $perm = isset($_POST['perm']) && is_array($_POST['perm']) ? $_POST['perm'] : array();

    $pdo->beginTransaction();
    try {
      // Limpia permisos actuales del rol
      $del = $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id=?");
      $del->execute(array($ridPost));

      // Inserta los seleccionados
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
      $msg = 'Permisos guardados.';
      $rid = $ridPost;

    } catch (Exception $e) {
      $pdo->rollBack();
      $err = 'Error al guardar permisos: '.$e->getMessage();
    }
  }
}

// Permisos actuales del rol: mapa [recurso_id][accion_id]=1
$permMap = array();
if ($rid > 0) {
  try {
    $st = $pdo->prepare("SELECT recurso_id, accion_id, permitido FROM rol_permisos WHERE rol_id=?");
    $st->execute(array($rid));
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      if ((int)$r['permitido'] !== 1) continue;
      $re = (int)$r['recurso_id'];
      $ac = (int)$r['accion_id'];
      if (!isset($permMap[$re])) $permMap[$re] = array();
      $permMap[$re][$ac] = 1;
    }
  } catch (Exception $e) {
    // no matamos la página
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Permisos por rol</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;margin-right:8px;margin-top:6px;}
    .btn:hover{background:#111;color:#fff;}
    .btn-secondary{border-color:#ddd;color:#111;}
    .btn-secondary:hover{background:#f3f3f3;color:#111;}
    .card{border:1px solid #eee;border-radius:12px;padding:14px;max-width:1200px;}
    .muted{color:#666;}
    select{padding:8px;border:1px solid #ddd;border-radius:8px;}
    table{width:100%;border-collapse:collapse;margin-top:12px;}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top;}
    th{background:#f6f6f6;}
    .small{font-size:12px;}
  </style>
</head>
<body>

<h2>Permisos por rol</h2>
<p>
  <a class="btn btn-secondary" href="index.php">← Admin</a>
</p>

<?php if ($err): ?><p style="color:#b00020;"><strong><?php echo h($err); ?></strong></p><?php endif; ?>
<?php if ($msg): ?><p style="color:#0a7a2f;"><strong><?php echo h($msg); ?></strong></p><?php endif; ?>

<div class="card">

  <form method="get" style="margin-bottom:12px;">
    <label class="muted">Rol</label><br>
    <select name="rid" onchange="this.form.submit()">
      <?php foreach($roles as $r): ?>
        <option value="<?php echo (int)$r['id']; ?>" <?php echo ((int)$rid===(int)$r['id']?'selected':''); ?>>
          <?php echo h($r['nombre'].' ('.$r['codigo'].')'); ?>
          <?php echo ((int)$r['activo']===0) ? ' [INACTIVO]' : ''; ?>
        </option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-secondary" type="submit">Cargar</button></noscript>
  </form>

  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
    <input type="hidden" name="rid" value="<?php echo (int)$rid; ?>">

    <p class="muted">Marca las acciones permitidas para cada recurso (página/sistema).</p>

    <table>
      <tr>
        <th>Recurso</th>
        <th class="small">Tipo</th>
        <th class="small">Ruta</th>
        <?php foreach($acciones as $a): ?>
          <th class="small" style="text-align:center;"><?php echo h($a['codigo']); ?></th>
        <?php endforeach; ?>
      </tr>

      <?php foreach($recursos as $re): ?>
        <?php $reId = (int)$re['id']; ?>
        <tr>
          <td>
            <strong><?php echo h($re['nombre']); ?></strong><br>
            <span class="muted small"><?php echo h($re['codigo']); ?></span>
          </td>
          <td class="small"><?php echo h($re['tipo']); ?></td>
          <td class="small muted"><?php echo h($re['ruta']); ?></td>

          <?php foreach($acciones as $a): ?>
            <?php
              $acId = (int)$a['id'];
              $ck = !empty($permMap[$reId]) && !empty($permMap[$reId][$acId]);
            ?>
            <td style="text-align:center;">
              <input type="checkbox"
                     name="perm[<?php echo $reId; ?>][<?php echo $acId; ?>]"
                     value="1" <?php echo $ck ? 'checked' : ''; ?>>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($recursos)): ?>
        <tr><td colspan="<?php echo 3 + count($acciones); ?>" class="muted">No hay recursos activos en <code>recursos</code>.</td></tr>
      <?php endif; ?>
    </table>

    <br>
    <button class="btn" type="submit">Guardar permisos</button>
  </form>

</div>
</body>
</html>
