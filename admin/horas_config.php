<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';

horas_require_login();
$fid = horas_current_funcionario_id();

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

$navActive = 'config';
$navTitle  = 'Configuración';
require __DIR__ . '/../inc/horas_nav.php';
?>

<div class="ph">
  <div class="ph-left">
    <h1>Configuración de reglas</h1>
    <p>Define los horarios y cupos semanales por servicio</p>
  </div>
  <?php if(count($services) > 1): ?>
  <div class="ph-actions">
    <form method="get" style="display:flex;gap:8px;align-items:center;">
      <div class="field" style="margin:0;min-width:220px;">
        <select name="service_id" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;">
          <?php foreach($services as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$serviceId?'selected':''); ?>>
              <?php echo horas_h($s['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if(isset($_GET['debug'])): ?>
<div class="debug-bar">
  DEBUG: funcionario_id=<?php echo (int)$fid; ?> | services=<?php echo count($services); ?> | serviceId=<?php echo (int)$serviceId; ?> | canConfig=<?php echo $canConfig?'YES':'NO'; ?>
</div>
<?php endif; ?>

<?php if($msg): ?>
<div class="alert alert-ok">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
  <?php echo horas_h($msg); ?>
</div>
<?php endif; ?>
<?php if($err): ?>
<div class="alert alert-err">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  <?php echo horas_h($err); ?>
</div>
<?php endif; ?>

<?php if($serviceId <= 0): ?>
  <div class="card"><div class="card-body">
    <div class="empty">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
      <p>No tienes servicios asignados para configurar.</p>
    </div>
  </div></div>
<?php else: ?>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h2>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nueva regla
    </h2>
  </div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">

      <div class="form-grid form-grid-5">
        <div class="field">
          <label>Día</label>
          <select name="weekday" required>
            <?php foreach($days as $k=>$v): ?>
              <option value="<?php echo (int)$k; ?>"><?php echo horas_h($v); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Inicio</label>
          <input name="start_time" placeholder="09:00" required>
        </div>
        <div class="field">
          <label>Término</label>
          <input name="end_time" placeholder="13:00" required>
        </div>
        <div class="field">
          <label>Duración (min)</label>
          <input name="slot_minutes" type="number" value="20" min="5" max="240" required>
        </div>
        <div class="field">
          <label>Cupos</label>
          <input name="capacity_per_slot" type="number" value="1" min="1" max="50" required>
        </div>
        <div class="full" style="display:flex;gap:8px;align-items:center;">
          <button class="btn btn-primary" type="submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
            Guardar regla
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Reglas existentes
      <span style="font-size:12px;font-weight:400;color:var(--muted);margin-left:8px;"><?php echo count($rules); ?> regla<?php echo count($rules)!==1?'s':''; ?></span>
    </h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Día</th>
          <th>Rango horario</th>
          <th>Duración</th>
          <th>Cupos</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rules as $r): ?>
        <tr>
          <td><b><?php echo horas_h(isset($days[(int)$r['weekday']]) ? $days[(int)$r['weekday']] : $r['weekday']); ?></b></td>
          <td><?php echo horas_h(substr($r['start_time'],0,5)); ?> – <?php echo horas_h(substr($r['end_time'],0,5)); ?></td>
          <td><?php echo (int)$r['slot_minutes']; ?> min</td>
          <td><?php echo (int)$r['capacity_per_slot']; ?></td>
          <td>
            <?php if((int)$r['active']===1): ?>
              <span class="pill pill-open">Activa</span>
            <?php else: ?>
              <span class="pill pill-closed">Inactiva</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;">
              <form method="post" style="display:inline">
                <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
                <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="btn btn-ghost btn-sm" type="submit">
                  <?php echo ((int)$r['active']===1 ? 'Desactivar' : 'Activar'); ?>
                </button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar esta regla?');">
                <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
                <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($rules)): ?>
        <tr><td colspan="6">
          <div class="empty">
            <p>Aún no hay reglas para este servicio.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

</div><!-- hn-main -->
</body>
</html>