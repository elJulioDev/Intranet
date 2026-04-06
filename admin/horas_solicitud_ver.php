<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';

horas_require_login();
$fid = horas_current_funcionario_id();

try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch(Exception $e) {}

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$msg = ''; $err = '';

$labels = array(
  'pending'   => 'Pendiente',
  'confirmed' => 'Confirmada',
  'attended'  => 'Atendida',
  'no_show'   => 'No asistió',
  'cancelled' => 'Cancelada'
);

$st = $pdo->prepare("
  SELECT a.*,
         sv.name AS service_name,
         s.date_day, s.start_time, s.end_time, s.capacity_total, s.capacity_used, s.status AS slot_status
  FROM appointments a
  JOIN services sv ON sv.id = a.service_id
  JOIN slots s     ON s.id  = a.slot_id
  WHERE a.id = ?
  LIMIT 1
");
$st->execute(array($id));
$ap = $st->fetch(PDO::FETCH_ASSOC);
if (!$ap) { http_response_code(404); exit('Solicitud no encontrada'); }

$serviceId = (int)$ap['service_id'];
if (!horas_can_access_service($pdo, $fid, $serviceId, 'manage')) {
  http_response_code(403); exit('No autorizado para gestionar este servicio.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!horas_csrf_check()) {
    $err = 'CSRF inválido. Recarga la página e intenta de nuevo.';
  } else {
    $action = (string)(isset($_POST['action']) ? $_POST['action'] : '');
    try {
      if ($action === 'set_status') {
        $newStatus = (string)(isset($_POST['status']) ? $_POST['status'] : '');
        if (!isset($labels[$newStatus])) throw new Exception('Estado inválido.');

        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT id,status,slot_id,service_id FROM appointments WHERE id=? FOR UPDATE");
        $st->execute(array($id));
        $cur = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cur) throw new Exception('Solicitud no encontrada.');
        if ((int)$cur['service_id'] !== $serviceId) throw new Exception('Servicio no coincide.');

        $slotId = (int)$cur['slot_id'];
        $st2 = $pdo->prepare("SELECT id,capacity_total,capacity_used,status FROM slots WHERE id=? FOR UPDATE");
        $st2->execute(array($slotId));
        $slot = $st2->fetch(PDO::FETCH_ASSOC);
        if (!$slot) throw new Exception('Slot no encontrado.');

        $oldStatus = (string)$cur['status'];

        if ($oldStatus !== 'cancelled' && $newStatus === 'cancelled') {
          if ((int)$slot['capacity_used'] > 0) {
            $pdo->prepare("UPDATE slots SET capacity_used = capacity_used - 1 WHERE id=?")->execute(array($slotId));
          }
        } elseif ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
          $left = (int)$slot['capacity_total'] - (int)$slot['capacity_used'];
          if ($left <= 0) throw new Exception('No hay cupos para reactivar esta solicitud (slot lleno).');
          $pdo->prepare("UPDATE slots SET capacity_used = capacity_used + 1 WHERE id=?")->execute(array($slotId));
        }

        $pdo->prepare("UPDATE appointments SET status=? WHERE id=?")->execute(array($newStatus, $id));

        $st3 = $pdo->prepare("SELECT capacity_total,capacity_used FROM slots WHERE id=? FOR UPDATE");
        $st3->execute(array($slotId));
        $cap = $st3->fetch(PDO::FETCH_ASSOC);
        if ($cap) {
          $left2 = (int)$cap['capacity_total'] - (int)$cap['capacity_used'];
          if ($left2 <= 0) {
            $pdo->prepare("UPDATE slots SET status='closed' WHERE id=?")->execute(array($slotId));
          } else {
            $pdo->prepare("UPDATE slots SET status='open' WHERE id=?")->execute(array($slotId));
          }
        }

        $pdo->commit();
        $msg = 'Estado actualizado correctamente.';
      }
    } catch(Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = $e->getMessage();
    }

    // Reload
    $st = $pdo->prepare("
      SELECT a.*,
             sv.name AS service_name,
             s.date_day, s.start_time, s.end_time, s.capacity_total, s.capacity_used, s.status AS slot_status
      FROM appointments a
      JOIN services sv ON sv.id = a.service_id
      JOIN slots s     ON s.id  = a.slot_id
      WHERE a.id = ?
      LIMIT 1
    ");
    $st->execute(array($id));
    $ap = $st->fetch(PDO::FETCH_ASSOC);
  }
}

function fmt_date_es($ymd){
  $ts = strtotime($ymd);
  if (!$ts) return $ymd;
  $days = array('Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado');
  return $days[(int)date('w',$ts)].', '.date('d-m-Y', $ts);
}

$timeRange = substr($ap['start_time'],0,5) . ' – ' . substr($ap['end_time'],0,5);
$left = (int)$ap['capacity_total'] - (int)$ap['capacity_used'];
$apStatus = (string)$ap['status'];

$navActive = 'solicitudes';
$navTitle  = 'Solicitud #'.$ap['id'];
require __DIR__ . '/../inc/horas_nav.php';
?>

<div class="ph">
  <div class="ph-left">
    <h1>Solicitud #<?php echo (int)$ap['id']; ?></h1>
    <p>Servicio: <b><?php echo horas_h($ap['service_name']); ?></b></p>
  </div>
  <div class="ph-actions">
    <a class="btn btn-ghost" href="horas_solicitudes.php?service_id=<?php echo (int)$serviceId; ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Volver a solicitudes
    </a>
  </div>
</div>

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

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

  <div class="card">
    <div class="card-header">
      <h2>Detalle de la solicitud</h2>
      <span class="pill pill-<?php echo horas_h($apStatus); ?>">
        <?php echo horas_h(isset($labels[$apStatus])?$labels[$apStatus]:$apStatus); ?>
      </span>
    </div>
    <table class="detail-table">
      <tr>
        <th>Código</th>
        <td><code style="font-size:14px;font-weight:600;"><?php echo horas_h($ap['code']); ?></code></td>
      </tr>
      <tr>
        <th>Fecha</th>
        <td><?php echo horas_h(fmt_date_es($ap['date_day'])); ?></td>
      </tr>
      <tr>
        <th>Horario</th>
        <td><?php echo horas_h($timeRange); ?></td>
      </tr>
      <tr>
        <th>Estado del slot</th>
        <td>
          <span class="pill pill-<?php echo horas_h($ap['slot_status']); ?>" style="margin-right:8px;">
            <?php echo horas_h($ap['slot_status']); ?>
          </span>
          <span style="font-size:12.5px;color:var(--muted);">
            <?php echo (int)$ap['capacity_used']; ?>/<?php echo (int)$ap['capacity_total']; ?> cupos ·
            <b style="color:<?php echo $left>0?'var(--ok)':'var(--danger)'; ?>"><?php echo (int)$left; ?> disponible<?php echo $left!==1?'s':''; ?></b>
          </span>
        </td>
      </tr>
      <tr>
        <th>Nombre</th>
        <td><b><?php echo horas_h($ap['requester_name']); ?></b></td>
      </tr>
      <tr>
        <th>RUT</th>
        <td><?php echo horas_h($ap['requester_rut']); ?></td>
      </tr>
      <tr>
        <th>Teléfono</th>
        <td><?php echo horas_h($ap['requester_phone']); ?></td>
      </tr>
      <?php if (!empty($ap['requester_email'])): ?>
      <tr>
        <th>Email</th>
        <td><?php echo horas_h($ap['requester_email']); ?></td>
      </tr>
      <?php endif; ?>
      <tr>
        <th>Creada</th>
        <td style="color:var(--muted);"><?php echo horas_h($ap['created_at']); ?></td>
      </tr>
    </table>
  </div>

  <div class="card">
    <div class="card-header"><h2>Cambiar estado</h2></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
        <input type="hidden" name="action" value="set_status">

        <div class="form-grid" style="gap:12px;">
          <div class="field">
            <label>Nuevo estado</label>
            <select name="status">
              <?php foreach($labels as $k=>$v): ?>
                <option value="<?php echo horas_h($k); ?>" <?php echo ($k===$apStatus?'selected':''); ?>>
                  <?php echo horas_h($v); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              Guardar cambio
            </button>
          </div>
          <p style="font-size:12px;color:var(--muted);line-height:1.5;">
            Cancelar libera cupo del slot automáticamente. Reactivar desde "Cancelada" vuelve a consumir cupo si hay disponibilidad.
          </p>
        </div>
      </form>
    </div>
  </div>

</div>

</div><!-- hn-main -->
</body>
</html>