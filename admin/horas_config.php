<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';

horas_require_login();
$fid = horas_current_funcionario_id();

// Forzar errores PDO visibles SOLO aquí (PHP 5.6 ok)
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch(Exception $e) {}

$services = horas_allowed_services($pdo, $fid, 'config');

$serviceId = (int)(isset($_GET['service_id']) ? $_GET['service_id'] : 0);
if ($serviceId <= 0 && !empty($services)) $serviceId = (int)$services[0]['id'];

$canConfig = ($serviceId > 0) ? horas_can_access_service($pdo, $fid, $serviceId, 'config') : false;
if ($serviceId > 0 && !$canConfig) { http_response_code(403); exit('No autorizado para configurar este servicio.'); }

$msg=''; $err='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!horas_csrf_check()) {
    $err = 'CSRF inválido (vuelve a cargar la página y reintenta).';
  } else {
    $action = (string)(isset($_POST['action']) ? $_POST['action'] : '');
    $serviceIdPost = (int)(isset($_POST['service_id']) ? $_POST['service_id'] : 0);

    if ($serviceIdPost <= 0 || !horas_can_access_service($pdo, $fid, $serviceIdPost, 'config')) {
      $err = 'Servicio inválido o sin permisos.';
    } else {
      try {
        if ($action === 'add') {
          $weekday = (int)(isset($_POST['weekday']) ? $_POST['weekday'] : 0);
          $start   = trim((string)(isset($_POST['start_time']) ? $_POST['start_time'] : ''));
          $end     = trim((string)(isset($_POST['end_time']) ? $_POST['end_time'] : ''));
          $mins    = (int)(isset($_POST['slot_minutes']) ? $_POST['slot_minutes'] : 20);
          $cap     = (int)(isset($_POST['capacity_per_slot']) ? $_POST['capacity_per_slot'] : 1);

          if ($weekday < 1 || $weekday > 7) throw new Exception('Día inválido.');
          if (!preg_match('/^\d{2}:\d{2}$/', $start)) throw new Exception('Hora inicio inválida (HH:MM).');
          if (!preg_match('/^\d{2}:\d{2}$/', $end)) throw new Exception('Hora término inválida (HH:MM).');
          if ($mins < 5 || $mins > 240) throw new Exception('Minutos inválidos.');
          if ($cap < 1 || $cap > 50) throw new Exception('Cupos inválidos.');

          $st = $pdo->prepare("INSERT INTO availability_rules(service_id,weekday,start_time,end_time,slot_minutes,capacity_per_slot,active)
                               VALUES(?,?,?,?,?,?,1)");
          $st->execute(array($serviceIdPost, $weekday, $start . ':00', $end . ':00', $mins, $cap));

          $msg = 'Regla creada.';
          $serviceId = $serviceIdPost;
        }

        if ($action === 'toggle') {
          $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
          $st = $pdo->prepare("UPDATE availability_rules SET active = IF(active=1,0,1) WHERE id=? AND service_id=?");
          $st->execute(array($id, $serviceIdPost));
          $msg = 'Estado actualizado.';
          $serviceId = $serviceIdPost;
        }

        if ($action === 'delete') {
          $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
          $st = $pdo->prepare("DELETE FROM availability_rules WHERE id=? AND service_id=?");
          $st->execute(array($id, $serviceIdPost));
          $msg = 'Regla eliminada.';
          $serviceId = $serviceIdPost;
        }

      } catch(Exception $e){
        $err = $e->getMessage();
      }
    }
  }
}

$rules = array();
try {
  if ($serviceId > 0) {
    $st = $pdo->prepare("SELECT * FROM availability_rules WHERE service_id=? ORDER BY weekday,start_time");
    $st->execute(array($serviceId));
    $rules = $st->fetchAll(PDO::FETCH_ASSOC);
  }
} catch(Exception $e){
  $err = 'Error cargando reglas: '.$e->getMessage();
}

$days = array(1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Horas | Configuración</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;background:#fafafa;}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;}
    .muted{color:#666;}
    a.btn, button.btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;cursor:pointer;}
    a.btn:hover, button.btn:hover{background:#111;color:#fff;}
    .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px;}
    .grid{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:10px;margin-top:10px;}
    .grid .full{grid-column:1/-1;}
    label{font-size:12px;color:#333;}
    input, select{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;}
    table{width:100%;border-collapse:collapse;margin-top:12px;}
    th,td{border:1px solid #ddd;padding:8px;vertical-align:top;text-align:left;}
    th{background:#f6f6f6;}
    .ok{color:#0a7a2f;}
    .bad{color:#b00020;}
    .debug{background:#fff7d6;border:1px solid #f0d27a;padding:10px;border-radius:10px;margin-top:10px;font-family:monospace;font-size:12px;}
  </style>
</head>
<body>

<div class="card">
  <h2 style="margin:0 0 6px 0;">Configuración (reglas de cupos/horario)</h2>
  <div class="muted">Primero defines reglas semanales. Luego “Generar slots” para publicar horas reales.</div>

  <div class="toolbar">
    <a class="btn" href="horas_dashboard.php">← Volver</a>
  </div>

  <div class="debug">
  DEBUG:
  funcionario_id=<?php echo (int)$fid; ?> |
  services_count=<?php echo (int)count($services); ?> |
  serviceId=<?php echo (int)$serviceId; ?> |
  canConfig=<?php echo $canConfig ? 'YES' : 'NO'; ?>
  <br>FILE=<?php echo __FILE__; ?>
  <br>MTIME=<?php echo date('Y-m-d H:i:s', filemtime(__FILE__)); ?>
</div>

  <?php if($msg): ?><p class="ok"><b><?php echo horas_h($msg); ?></b></p><?php endif; ?>
  <?php if($err): ?><p class="bad"><b><?php echo horas_h($err); ?></b></p><?php endif; ?>

  <form method="get" class="toolbar">
    <label style="min-width:70px;">Servicio</label>
    <select name="service_id" onchange="this.form.submit()">
      <?php foreach($services as $s): ?>
        <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$serviceId?'selected':''); ?>>
          <?php echo horas_h($s['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if($serviceId<=0): ?>
    <p class="muted" style="margin-top:12px;">No tienes servicios asignados para configurar.</p>
  <?php else: ?>

    <h3 style="margin:14px 0 6px 0;">Nueva regla</h3>

    <form method="post">
      <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">

      <div class="grid">
        <div>
          <label>Día</label>
          <select name="weekday" required>
            <?php foreach($days as $k=>$v): ?>
              <option value="<?php echo (int)$k; ?>"><?php echo horas_h($v); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Inicio (HH:MM)</label>
          <input name="start_time" placeholder="09:00" required>
        </div>

        <div>
          <label>Término (HH:MM)</label>
          <input name="end_time" placeholder="13:00" required>
        </div>

        <div>
          <label>Duración (min)</label>
          <input name="slot_minutes" type="number" value="20" min="5" max="240" required>
        </div>

        <div>
          <label>Cupos</label>
          <input name="capacity_per_slot" type="number" value="1" min="1" max="50" required>
        </div>

        <div class="full">
          <button class="btn" type="submit">Guardar regla</button>
        </div>
      </div>
    </form>

    <h3 style="margin:16px 0 6px 0;">Reglas existentes</h3>

    <table>
      <tr><th>Día</th><th>Rango</th><th>Min</th><th>Cupos</th><th>Activo</th><th>Acciones</th></tr>
      <?php foreach($rules as $r): ?>
        <tr>
          <td><?php echo horas_h(isset($days[(int)$r['weekday']]) ? $days[(int)$r['weekday']] : $r['weekday']); ?></td>
          <td><?php echo horas_h(substr($r['start_time'],0,5)); ?> - <?php echo horas_h(substr($r['end_time'],0,5)); ?></td>
          <td><?php echo (int)$r['slot_minutes']; ?></td>
          <td><?php echo (int)$r['capacity_per_slot']; ?></td>
          <td><?php echo ((int)$r['active']===1?'Sí':'No'); ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
              <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <input type="hidden" name="action" value="toggle">
              <button class="btn" type="submit">On/Off</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar regla?');">
              <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
              <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn" type="submit">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($rules)): ?>
        <tr><td colspan="6" class="muted">Aún no hay reglas para este servicio.</td></tr>
      <?php endif; ?>
    </table>

  <?php endif; ?>
</div>

</body>
</html>
