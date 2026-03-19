<?php
// horarios_horas.php (Público) - Reserva en 1 paso - PHP 5.6
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

// No exigimos login (público)
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch(Exception $e) {}

function rut_clean($rut){
  $rut = strtoupper(trim($rut));
  $rut = preg_replace('/[^0-9K]/', '', $rut);
  return $rut;
}

function phone_clean($p){
  $p = trim($p);
  $p = preg_replace('/[^\d+]/', '', $p);
  return $p;
}

$serviceId = (int)(isset($_GET['service_id']) ? $_GET['service_id'] : 0);
$dateDay   = trim((string)(isset($_GET['date']) ? $_GET['date'] : date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDay)) $dateDay = date('Y-m-d');

$okMsg = '';
$errMsg = '';
$confirm = null;

// Servicios activos
$services = array();
try {
  $services = $pdo->query("SELECT id, name, description FROM services WHERE active=1 ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
  $services = array();
}

if ($serviceId <= 0 && !empty($services)) $serviceId = (int)$services[0]['id'];

// Servicio seleccionado
$service = null;
if ($serviceId > 0) {
  $st = $pdo->prepare("SELECT id, name, description FROM services WHERE id=? AND active=1 LIMIT 1");
  $st->execute(array($serviceId));
  $service = $st->fetch(PDO::FETCH_ASSOC);
  if (!$service) $serviceId = 0;
}

// ============ RESERVA (POST) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!horas_csrf_check()) {
    $errMsg = 'CSRF inválido. Recarga la página e intenta de nuevo.';
  } else {
    $slotId = (int)(isset($_POST['slot_id']) ? $_POST['slot_id'] : 0);
    $name   = trim((string)(isset($_POST['name']) ? $_POST['name'] : ''));
    $rut    = rut_clean((string)(isset($_POST['rut']) ? $_POST['rut'] : ''));
    $phone  = phone_clean((string)(isset($_POST['phone']) ? $_POST['phone'] : ''));
    $email  = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));

    // Mantener filtros en la URL (para recargar mostrando el mismo servicio/fecha)
    $serviceId = (int)(isset($_POST['service_id']) ? $_POST['service_id'] : $serviceId);
    $dateDay   = (string)(isset($_POST['date']) ? $_POST['date'] : $dateDay);

    if ($slotId <= 0) $errMsg = 'Slot inválido.';
    elseif ($name === '' || strlen($name) < 3) $errMsg = 'Ingresa tu nombre.';
    elseif ($rut === '' || strlen($rut) < 7) $errMsg = 'Ingresa un RUT válido (sin puntos).';
    elseif ($phone === '' || strlen($phone) < 8) $errMsg = 'Ingresa un teléfono válido.';
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errMsg = 'Email inválido.';
    else {
      try {
        $pdo->beginTransaction();

        // Bloquear slot para evitar doble reserva simultánea
        $st = $pdo->prepare("
          SELECT s.id, s.service_id, s.date_day, s.start_time, s.end_time,
                 s.capacity_total, s.capacity_used, s.status,
                 sv.name AS service_name
          FROM slots s
          JOIN services sv ON sv.id = s.service_id
          WHERE s.id = ?
          FOR UPDATE
        ");
        $st->execute(array($slotId));
        $slot = $st->fetch(PDO::FETCH_ASSOC);

        if (!$slot) throw new Exception('El horario seleccionado no existe.');
        if ((int)$slot['service_id'] !== (int)$serviceId) throw new Exception('El horario no corresponde al servicio seleccionado.');
        if ($slot['date_day'] !== $dateDay) throw new Exception('El horario no corresponde a la fecha seleccionada.');
        if ($slot['status'] !== 'open') throw new Exception('Este horario no está disponible (cerrado).');

        $left = (int)$slot['capacity_total'] - (int)$slot['capacity_used'];
        if ($left <= 0) throw new Exception('Este horario ya no tiene cupos.');

        // Crear código y guardar cita
        $code = horas_random_code(10);

        // Ajusta columnas si tu tabla appointments difiere
        $ins = $pdo->prepare("
          INSERT INTO appointments
            (service_id, slot_id, code, requester_name, requester_rut, requester_phone, requester_email, status, created_at)
          VALUES
            (?,?,?,?,?,?,?, 'confirmed', NOW())
        ");
        $ins->execute(array(
          (int)$slot['service_id'],
          (int)$slot['id'],
          $code,
          $name,
          $rut,
          $phone,
          ($email === '' ? null : $email)
        ));

        $appointmentId = (int)$pdo->lastInsertId();

        // Consumir cupo
        $up = $pdo->prepare("
          UPDATE slots
          SET capacity_used = capacity_used + 1
          WHERE id = ? AND status='open' AND (capacity_total - capacity_used) > 0
        ");
        $up->execute(array((int)$slot['id']));
        if ($up->rowCount() <= 0) throw new Exception('No se pudo tomar el cupo (posible concurrencia). Reintenta.');

        // Si se llenó, cerrar slot
        $chk = $pdo->prepare("SELECT capacity_total, capacity_used FROM slots WHERE id=? FOR UPDATE");
        $chk->execute(array((int)$slot['id']));
        $cap = $chk->fetch(PDO::FETCH_ASSOC);
        if ($cap && ((int)$cap['capacity_total'] - (int)$cap['capacity_used']) <= 0) {
          $pdo->prepare("UPDATE slots SET status='closed' WHERE id=?")->execute(array((int)$slot['id']));
        }

        $pdo->commit();

        $confirm = array(
          'code' => $code,
          'service' => $slot['service_name'],
          'date' => $slot['date_day'],
          'time' => substr($slot['start_time'],0,5) . ' - ' . substr($slot['end_time'],0,5),
          'name' => $name,
          'rut' => $rut,
          'phone' => $phone,
          'email' => $email
        );

        $okMsg = 'Reserva creada correctamente.';
      } catch(Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errMsg = $e->getMessage();
      }
    }
  }
}

// Re-cargar servicio seleccionado
$service = null;
if ($serviceId > 0) {
  $st = $pdo->prepare("SELECT id, name, description FROM services WHERE id=? AND active=1 LIMIT 1");
  $st->execute(array($serviceId));
  $service = $st->fetch(PDO::FETCH_ASSOC);
  if (!$service) $serviceId = 0;
}

// Slots disponibles para el servicio/fecha
$slots = array();
if ($serviceId > 0) {
  $st = $pdo->prepare("
    SELECT id, date_day, start_time, end_time, capacity_total, capacity_used, status
    FROM slots
    WHERE service_id=?
      AND date_day=?
      AND status='open'
      AND (capacity_total - capacity_used) > 0
    ORDER BY start_time ASC
  ");
  $st->execute(array($serviceId, $dateDay));
  $slots = $st->fetchAll(PDO::FETCH_ASSOC);
}

$dayName = array('Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado');
$ts = strtotime($dateDay);
$labelDate = $dateDay;
if ($ts) $labelDate = $dayName[(int)date('w',$ts)].' '.date('d-m-Y',$ts);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Horarios disponibles</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111;background:#fafafa;}
    .wrap{max-width:980px;margin:0 auto;}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;}
    .muted{color:#666;}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:10px;}
    .field{min-width:240px;flex:1;}
    label{font-size:12px;color:#333;display:block;margin-bottom:6px;}
    input, select{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;}
    button.btn, a.btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;cursor:pointer;}
    button.btn:hover, a.btn:hover{background:#111;color:#fff;}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:12px;margin-top:12px;}
    .slot{border:1px solid #eee;border-radius:10px;padding:12px;background:#fff;}
    .slotTop{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;}
    .pill{display:inline-block;padding:3px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px;}
    .ok{color:#0a7a2f;}
    .bad{color:#b00020;}
    .mini{display:grid;grid-template-columns:repeat(2,minmax(120px,1fr));gap:8px;margin-top:10px;}
    .mini .full{grid-column:1/-1;}
    .hr{border:none;border-top:1px solid #eee;margin:14px 0;}
    .confirm{background:#f6fff3;border:1px solid #bfe7b2;border-radius:10px;padding:12px;margin-top:12px;}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 6px 0;">Horarios disponibles</h2>
    <div class="muted">Selecciona un servicio y fecha. Reserva en un paso.</div>

    <?php if($okMsg): ?><p class="ok"><b><?php echo horas_h($okMsg); ?></b></p><?php endif; ?>
    <?php if($errMsg): ?><p class="bad"><b><?php echo horas_h($errMsg); ?></b></p><?php endif; ?>

    <?php if($confirm): ?>
      <div class="confirm">
        <div><b>✅ Reserva confirmada</b></div>
        <div>Código: <b><?php echo horas_h($confirm['code']); ?></b></div>
        <div>Servicio: <?php echo horas_h($confirm['service']); ?></div>
        <div>Fecha/Hora: <?php echo horas_h($confirm['date']); ?> · <?php echo horas_h($confirm['time']); ?></div>
        <div>Nombre: <?php echo horas_h($confirm['name']); ?> · RUT: <?php echo horas_h($confirm['rut']); ?></div>
        <div>Teléfono: <?php echo horas_h($confirm['phone']); ?><?php echo ($confirm['email']!=='' ? ' · Email: '.horas_h($confirm['email']) : ''); ?></div>
      </div>
    <?php endif; ?>

    <form method="get" class="toolbar">
      <div class="field">
        <label>Servicio</label>
        <select name="service_id" required>
          <?php foreach($services as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$serviceId?'selected':''); ?>>
              <?php echo horas_h($s['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="min-width:220px;max-width:260px;">
        <label>Fecha</label>
        <input type="date" name="date" value="<?php echo horas_h($dateDay); ?>" required>
      </div>

      <div style="min-width:140px;">
        <button class="btn" type="submit">Ver horarios</button>
      </div>
    </form>

    <hr class="hr">

    <?php if(!$service): ?>
      <p class="muted">No hay servicios disponibles.</p>
    <?php else: ?>
      <h3 style="margin:0 0 6px 0;"><?php echo horas_h($service['name']); ?></h3>
      <?php if(!empty($service['description'])): ?>
        <div class="muted" style="margin-bottom:10px;"><?php echo nl2br(horas_h($service['description'])); ?></div>
      <?php endif; ?>

      <div class="muted"><b><?php echo horas_h($labelDate); ?></b></div>

      <?php if(empty($slots)): ?>
        <p class="muted" style="margin-top:10px;">
          No hay cupos disponibles para esta fecha.
          <br>Tip: si recién configuraste reglas, recuerda usar “Generar slots” en Admin.
        </p>
      <?php else: ?>
        <div class="grid">
          <?php foreach($slots as $sl): ?>
            <?php
              $cupos = (int)$sl['capacity_total'] - (int)$sl['capacity_used'];
              $ini = substr($sl['start_time'],0,5);
              $fin = substr($sl['end_time'],0,5);
            ?>
            <div class="slot">
              <div class="slotTop">
                <div>
                  <div style="font-size:18px;"><b><?php echo horas_h($ini.' - '.$fin); ?></b></div>
                  <div class="muted">Disponible: <span class="pill ok"><?php echo (int)$cupos; ?> cupo(s)</span></div>
                </div>
              </div>

              <form method="post" style="margin-top:10px;">
                <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
                <input type="hidden" name="slot_id" value="<?php echo (int)$sl['id']; ?>">
                <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
                <input type="hidden" name="date" value="<?php echo horas_h($dateDay); ?>">

                <div class="mini">
                  <div class="full">
                    <label>Nombre y Apellido</label>
                    <input name="name" required placeholder="Ej: Juan Pérez">
                  </div>
                  <div>
                    <label>RUT</label>
                    <input name="rut" required placeholder="12345678K">
                  </div>
                  <div>
                    <label>Teléfono</label>
                    <input name="phone" required placeholder="+56912345678">
                  </div>
                  <div class="full">
                    <label>Email (opcional)</label>
                    <input name="email" placeholder="correo@ejemplo.cl">
                  </div>

                  <div class="full">
                    <button class="btn" type="submit">Reservar</button>
                  </div>
                </div>
              </form>

              <div class="muted" style="margin-top:8px;font-size:12px;">
                Al reservar se genera un código de confirmación.
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
