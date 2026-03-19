<?php
// intranet/dashboard.php (PHP 5.6)
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

$fid    = current_user_id();
$nombre = !empty($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rut    = !empty($_SESSION['rut']) ? $_SESSION['rut'] : '';

$esAdmin = function_exists('is_superadmin') && is_superadmin();
$rolTxt  = $esAdmin ? 'ADMIN' : 'USUARIO';

/**
 * Permiso módulo "Solicitud de Horas":
 * - Superadmin ve siempre
 * - O si está asignado en service_staff (can_config o can_manage)
 */
$tienePermHoras = false;
try {
  if ($esAdmin) {
    $tienePermHoras = true;
  } else {
    $stmt = $pdo->prepare("
      SELECT 1
      FROM service_staff
      WHERE funcionario_id = ?
        AND (can_config=1 OR can_manage=1)
      LIMIT 1
    ");
    $stmt->execute(array($fid));
    $tienePermHoras = (bool)$stmt->fetchColumn();
  }
} catch (Exception $e) {
  $tienePermHoras = false;
}

/**
 * Cargo vigente:
 * funcionario_cargos -> cargos -> unidades -> direcciones
 * Prioriza titular sobre subrogante, más reciente.
 */
$cargoVigente = null;
try {
  $stmt = $pdo->prepare("
    SELECT
      fc.tipo, fc.fecha_desde, fc.fecha_hasta,
      c.nombre AS cargo_nombre,
      u.nombre AS unidad_nombre,
      d.nombre AS direccion_nombre
    FROM funcionario_cargos fc
    JOIN cargos c       ON c.id = fc.cargo_id
    JOIN unidades u     ON u.id = c.unidad_id
    JOIN direcciones d  ON d.id = u.direccion_id
    WHERE fc.funcionario_id = ?
      AND fc.activo = 1
      AND fc.fecha_desde <= CURDATE()
      AND (fc.fecha_hasta IS NULL OR fc.fecha_hasta >= CURDATE())
    ORDER BY (fc.tipo='titular') DESC, fc.fecha_desde DESC
    LIMIT 1
  ");
  $stmt->execute(array($fid));
  $cargoVigente = $stmt->fetch();
} catch (Exception $e) {
  $cargoVigente = null;
}

/** Resumen actividades del día (del usuario) */
$hoy = date('Y-m-d');
$misActividades = array();
try {
  $stmt = $pdo->prepare("
    SELECT id, hora, titulo, estado, prioridad, referencia
    FROM actividad_registro
    WHERE funcionario_id = ?
      AND fecha = ?
    ORDER BY hora ASC, id ASC
    LIMIT 20
  ");
  $stmt->execute(array($fid, $hoy));
  $misActividades = $stmt->fetchAll();
} catch (Exception $e) {
  $misActividades = array();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Intranet | Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body { font-family: Arial, sans-serif; margin: 18px; color:#111; }
    .top { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
    .card { border:1px solid #ddd; border-radius:10px; padding:14px; background:#fff; min-width:260px; }
    .muted { color:#666; }
    a.btn { display:inline-block; padding:10px 12px; border:1px solid #111; border-radius:8px; text-decoration:none; margin-right:8px; margin-top:8px; }
    a.btn:hover { background:#111; color:#fff; }
    table { width:100%; border-collapse:collapse; margin-top:12px; }
    th, td { border:1px solid #ddd; padding:8px; text-align:left; vertical-align:top; }
    th { background:#f6f6f6; }
    .pill { display:inline-block; padding:3px 8px; border-radius:999px; border:1px solid #ddd; font-size:12px; }
    .badges { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .badge { display:inline-block; padding:4px 10px; border:1px solid #ddd; border-radius:999px; font-size:12px; }
    .admin-grid { display:flex; flex-wrap:wrap; gap:8px; }
  </style>
</head>
<body>

  <div class="top">
    <div class="card">
      <h2 style="margin:0 0 8px 0;">Dashboard</h2>
      <div><strong><?php echo h($nombre); ?></strong></div>
      <div class="muted">RUT: <?php echo h($rut); ?></div>

      <hr style="border:none;border-top:1px solid #eee;margin:12px 0;">

      <?php if ($cargoVigente): ?>
        <div class="badges">
          <span class="pill"><?php echo h($cargoVigente['tipo']); ?></span>
          <span class="badge"><?php echo h($rolTxt); ?></span>
        </div>

        <div style="margin-top:8px;"><strong><?php echo h($cargoVigente['cargo_nombre']); ?></strong></div>
        <div class="muted"><?php echo h($cargoVigente['direccion_nombre']); ?> · <?php echo h($cargoVigente['unidad_nombre']); ?></div>
        <div class="muted" style="margin-top:6px;">
          Vigencia: <?php echo h($cargoVigente['fecha_desde']); ?>
          <?php echo $cargoVigente['fecha_hasta'] ? ' → '.h($cargoVigente['fecha_hasta']) : ' → (vigente)'; ?>
        </div>
      <?php else: ?>
        <div class="badges">
          <span class="pill">sin cargo</span>
          <span class="badge"><?php echo h($rolTxt); ?></span>
        </div>
        <div class="muted" style="margin-top:8px;">
          No se encontró un cargo vigente para hoy.<br>
          (Revisa la tabla <code>funcionario_cargos</code> y sus fechas)
        </div>
      <?php endif; ?>

      <div style="margin-top:14px;">
        <?php if ($esAdmin): ?>
          <div class="admin-grid">
            <a class="btn" href="admin/index.php">⚙️ Admin</a>
            <a class="btn" href="admin/users_form.php">+ Crear usuario</a>
            <a class="btn" href="admin/direcciones_form.php">+ Dirección</a>
            <a class="btn" href="admin/unidades_form.php">+ Unidad</a>
            <a class="btn" href="admin/cargos_form.php">+ Cargo</a>

            <a class="btn" href="admin/direcciones_list.php">Direcciones</a>
            <a class="btn" href="admin/unidades_list.php">Unidades</a>
            <a class="btn" href="admin/cargos_list.php">Cargos</a>
            <a class="btn" href="admin/marcaciones/importar.php">⏱️ Importar marcaciones</a>
            <a class="btn" href="admin/marcaciones/index.php">📊 Control de marcaciones</a>

          </div>
        <?php endif; ?>

        <?php if ($tienePermHoras): ?>
          <a class="btn" href="admin/horas_dashboard.php">📅 Solicitud de horas (Admin)</a>
        <?php endif; ?>

        <a class="btn" href="solicitud_horas.php">📝 Solicitar hora</a>

        <a class="btn" href="marcaciones/mi.php">🕘 Mis marcaciones</a>
        <a class="btn" href="actividades_form.php">+ Registrar actividad</a>
        <a class="btn" href="actividades_list.php?fecha=<?php echo h($hoy); ?>">Ver actividades de hoy</a>
        <a class="btn" href="logout.php">Cerrar sesión</a>
      </div>
    </div>

    <div class="card" style="flex:1;">
      <h3 style="margin:0 0 6px 0;">Mis actividades de hoy (<?php echo h($hoy); ?>)</h3>
      <?php if (empty($misActividades)): ?>
        <p class="muted" style="margin:10px 0 0 0;">No tienes actividades registradas hoy.</p>
      <?php else: ?>
        <table>
          <tr>
            <th style="width:90px;">Hora</th>
            <th>Título</th>
            <th style="width:120px;">Estado</th>
            <th style="width:110px;">Prioridad</th>
            <th style="width:140px;">Referencia</th>
          </tr>
          <?php foreach ($misActividades as $a): ?>
            <tr>
              <td><?php echo h($a['hora']); ?></td>
              <td><?php echo h($a['titulo']); ?></td>
              <td><?php echo h($a['estado']); ?></td>
              <td><?php echo h($a['prioridad']); ?></td>
              <td><?php echo h($a['referencia']); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
