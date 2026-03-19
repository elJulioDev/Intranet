<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';

horas_require_login();
$fid = horas_current_funcionario_id();

try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch(Exception $e) {}

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$msg = '';
$err = '';

$labels = array(
  'pending'   => 'Pendiente',
  'confirmed' => 'Confirmada',
  'attended'  => 'Atendida',
  'no_show'   => 'No asistió',
  'cancelled' => 'Cancelada'
);

// Cargar solicitud
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

// Acción POST: cambiar estado
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

        // Bloquear cita y slot para consistencia
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

        // Reglas de liberación de cupo:
        // - Si pasas a CANCELLED desde un estado NO cancelado => liberar cupo (capacity_used - 1)
        // - Si pasas de CANCELLED a NO cancelado => volver a consumir cupo (capacity_used + 1) si hay disponibilidad
        // Nota: esto asume que cuando se creó la cita se consumió cupo.
        if ($oldStatus !== 'cancelled' && $newStatus === 'cancelled') {
          if ((int)$slot['capacity_used'] > 0) {
            $pdo->prepare("UPDATE slots SET capacity_used = capacity_used - 1 WHERE id=?")
                ->execute(array($slotId));
          }
        } elseif ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
          $left = (int)$slot['capacity_total'] - (int)$slot['capacity_used'];
          if ($left <= 0) throw new Exception('No hay cupos para reactivar esta solicitud (slot lleno).');
          $pdo->prepare("UPDATE slots SET capacity_used = capacity_used + 1 WHERE id=?")
              ->execute(array($slotId));
        }

        // Actualizar estado de la cita
        $up = $pdo->prepare("UPDATE appointments SET status=? WHERE id=?");
        $up->execute(array($newStatus, $id));

        // Ajustar estado del slot según cupos
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
        $msg = 'Estado actualizado.';
      }

    } catch(Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = $e->getMessage();
    }

    // Recargar datos
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
  return $days[(int)date('w',$ts)].' '.date('d-m-Y', $ts);
}

$timeRange = substr($ap['start_time'],0,5) . ' - ' . substr($ap['end_time'],0,5);
$left = (int)$ap['capacity_total'] - (int)$ap['capacity_used'];
$apStatus = (string)$ap['status'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Horas | Ver solicitud</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;background:#fafafa;}
    .row{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;min-width:320px;}
    .muted{color:#666;}
    a.btn, button.btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;cursor:pointer;}
    a.btn:hover, button.btn:hover{background:#111;color:#fff;}
    .ok{color:#0a7a2f;}
    .bad{color:#b00020;}
    .pill{display:inline-block;padding:3px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px;}
    table{width:100%;border-collapse:collapse;margin-top:10px;}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top;}
    th{background:#f6f6f6;width:220px;}
    select{padding:9px;border:1px solid #ddd;border-radius:8px;min-width:240px;}
  </style>
</head>
<body>

<div class="row">
  <div class="card" style="flex:1;">
    <h2 style="margin:0 0 6px 0;">Solicitud #<?php echo (int)$ap['id']; ?></h2>
    <div class="muted">Servicio: <b><?php echo horas_h($ap['service_name']); ?></b></div>

    <div style="margin-top:10px;">
      <a class="btn" href="horas_solicitudes.php?service_id=<?php echo (int)$serviceId; ?>">← Volver a solicitudes</a>
      <a class="btn" href="horas_dashboard.php">Dashboard</a>
    </div>

    <?php if($msg): ?><p class="ok"><b><?php echo horas_h($msg); ?></b></p><?php endif; ?>
    <?php if($err): ?><p class="bad"><b><?php echo horas_h($err); ?></b></p><?php endif; ?>

    <h3 style="margin:14px 0 6px 0;">Detalle</h3>

    <table>
      <tr><th>Código</th><td><b><?php echo horas_h($ap['code']); ?></b></td></tr>
      <tr><th>Estado</th><td><span class="pill"><?php echo horas_h(isset($labels[$apStatus])?$labels[$apStatus]:$apStatus); ?></span></td></tr>
      <tr><th>Fecha</th><td><?php echo horas_h(fmt_date_es($ap['date_day'])); ?></td></tr>
      <tr><th>Horario</th><td><?php echo horas_h($timeRange); ?></td></tr>
      <tr><th>Slot</th><td>
        Estado slot: <span class="pill"><?php echo horas_h($ap['slot_status']); ?></span> ·
        Cupos: <?php echo (int)$ap['capacity_used']; ?>/<?php echo (int)$ap['capacity_total']; ?> ·
        Disponibles: <b><?php echo (int)$left; ?></b>
      </td></tr>
      <tr><th>Solicitante</th><td>
        <b><?php echo horas_h($ap['requester_name']); ?></b><br>
        RUT: <?php echo horas_h($ap['requester_rut']); ?><br>
        Tel: <?php echo horas_h($ap['requester_phone']); ?><br>
        <?php if (!empty($ap['requester_email'])): ?>
          Email: <?php echo horas_h($ap['requester_email']); ?><br>
        <?php endif; ?>
      </td></tr>
      <tr><th>Creada</th><td><?php echo horas_h($ap['created_at']); ?></td></tr>
    </table>

    <h3 style="margin:16px 0 6px 0;">Acciones</h3>

    <form method="post">
      <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
      <input type="hidden" name="action" value="set_status">

      <label class="muted">Cambiar estado</label><br>
      <select name="status">
        <?php foreach($labels as $k=>$v): ?>
          <option value="<?php echo horas_h($k); ?>" <?php echo ($k===$apStatus?'selected':''); ?>>
            <?php echo horas_h($v); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button class="btn" type="submit">Guardar</button>

      <div class="muted" style="margin-top:8px;font-size:12px;">
        Nota: Cancelar libera cupo del slot. Reactivar desde “Cancelada” vuelve a consumir cupo si hay disponibilidad.
      </div>
    </form>
  </div>
</div>

</body>
</html>
