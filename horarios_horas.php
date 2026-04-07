<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

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

$okMsg = ''; $errMsg = ''; $confirm = null;

$services = array();
try {
  $services = $pdo->query("SELECT id, name, description FROM services WHERE active=1 ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $services = array(); }

if ($serviceId <= 0 && !empty($services)) $serviceId = (int)$services[0]['id'];

$service = null;
if ($serviceId > 0) {
  $st = $pdo->prepare("SELECT id, name, description FROM services WHERE id=? AND active=1 LIMIT 1");
  $st->execute(array($serviceId));
  $service = $st->fetch(PDO::FETCH_ASSOC);
  if (!$service) $serviceId = 0;
}

// ── POST: Reservar ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!horas_csrf_check()) {
    $errMsg = 'CSRF inválido. Recarga la página e intenta de nuevo.';
  } else {
    $slotId    = (int)(isset($_POST['slot_id'])    ? $_POST['slot_id']    : 0);
    $name      = trim((string)(isset($_POST['name'])      ? $_POST['name']      : ''));
    $rut       = rut_clean((string)(isset($_POST['rut'])   ? $_POST['rut']   : ''));
    $phone     = phone_clean((string)(isset($_POST['phone']) ? $_POST['phone'] : ''));
    $email     = trim((string)(isset($_POST['email'])     ? $_POST['email']     : ''));
    $serviceId = (int)(isset($_POST['service_id']) ? $_POST['service_id'] : $serviceId);
    $dateDay   = (string)(isset($_POST['date'])    ? $_POST['date']    : $dateDay);

    if      ($slotId <= 0)                        $errMsg = 'Slot inválido.';
    elseif  ($name === '' || strlen($name) < 3)   $errMsg = 'Ingresa tu nombre completo.';
    elseif  ($rut === '' || strlen($rut) < 7)     $errMsg = 'Ingresa un RUT válido (sin puntos, ej: 12345678K).';
    elseif  ($phone === '' || strlen($phone) < 8) $errMsg = 'Ingresa un número de teléfono válido.';
    elseif  ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errMsg = 'Email inválido.';
    else {
      // --- SISTEMA DE SEGURIDAD Y COOLDOWN ---
      $ip = $_SERVER['REMOTE_ADDR'];
      
      // 1. Cooldown de Dispositivo (Sesión): Evita el doble click o spam rápido (Ej: 1 minuto)
      if (isset($_SESSION['last_booking_time']) && (time() - $_SESSION['last_booking_time']) < 60) {
          $errMsg = 'Por favor espera 1 minuto antes de intentar realizar otra reserva.';
      }
      
      // 2. Cooldown de RUT: Máximo 1 reserva por RUT en las últimas 24 horas
      if ($errMsg === '') {
          $stRut = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE requester_rut = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
          $stRut->execute(array($rut));
          if ($stRut->fetchColumn() >= 1) {
              $errMsg = 'Ya existe una solicitud reciente asociada a este RUT. Intenta mañana.';
          }
      }

      // 3. Cooldown de IP: Máximo 3 reservas desde la misma IP en la última hora
      if ($errMsg === '') {
          $stIp = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
          $stIp->execute(array($ip));
          if ($stIp->fetchColumn() >= 3) {
              $errMsg = 'Se ha superado el límite de solicitudes desde esta red. Intenta más tarde.';
          }
      }
      // --- FIN SISTEMA DE SEGURIDAD ---
      
      if ($errMsg === '') { // Solo procede si pasó todas las pruebas de seguridad
      try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("
          SELECT s.id, s.service_id, s.date_day, s.start_time, s.end_time,
                 s.capacity_total, s.capacity_used, s.status,
                 sv.name AS service_name
          FROM slots s
          JOIN services sv ON sv.id = s.service_id
          WHERE s.id = ? FOR UPDATE
        ");
        $st->execute(array($slotId));
        $slot = $st->fetch(PDO::FETCH_ASSOC);

        if (!$slot)                                   throw new Exception('El horario seleccionado no existe.');
        if ((int)$slot['service_id'] !== $serviceId)  throw new Exception('El horario no corresponde al servicio seleccionado.');
        if ($slot['date_day'] !== $dateDay)            throw new Exception('El horario no corresponde a la fecha seleccionada.');
        if ($slot['status'] !== 'open')                throw new Exception('Este horario ya no está disponible.');
        $left = (int)$slot['capacity_total'] - (int)$slot['capacity_used'];
        if ($left <= 0)                                throw new Exception('Este horario ya no tiene cupos disponibles.');

        $code = horas_random_code(10);

        $ins = $pdo->prepare("
          INSERT INTO appointments
            (service_id, slot_id, code, requester_name, requester_rut, requester_phone, requester_email, status, created_at, ip_address)
          VALUES (?,?,?,?,?,?,?, 'confirmed', NOW(), ?)
        ");
        $ins->execute(array(
          (int)$slot['service_id'], (int)$slot['id'], $code,
          $name, $rut, $phone, ($email === '' ? null : $email), $ip
        ));

        $up = $pdo->prepare("UPDATE slots SET capacity_used = capacity_used + 1
                             WHERE id = ? AND status='open' AND (capacity_total - capacity_used) > 0");
        $up->execute(array((int)$slot['id']));
        if ($up->rowCount() <= 0) throw new Exception('No se pudo reservar el cupo. Intenta de nuevo.');

        $chk = $pdo->prepare("SELECT capacity_total, capacity_used FROM slots WHERE id=? FOR UPDATE");
        $chk->execute(array((int)$slot['id']));
        $cap = $chk->fetch(PDO::FETCH_ASSOC);
        if ($cap && ((int)$cap['capacity_total'] - (int)$cap['capacity_used']) <= 0) {
          $pdo->prepare("UPDATE slots SET status='closed' WHERE id=?")->execute(array((int)$slot['id']));
        }

        $pdo->commit();
        $_SESSION['last_booking_time'] = time();

        $confirm = array(
          'code'    => $code,
          'service' => $slot['service_name'],
          'date'    => $slot['date_day'],
          'time'    => substr($slot['start_time'],0,5) . ' – ' . substr($slot['end_time'],0,5),
          'name'    => $name,
          'rut'     => $rut,
          'phone'   => $phone,
          'email'   => $email
        );
      } catch(Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errMsg = $e->getMessage();
      }
    }
  }
  }
}

// Re-cargar servicio y slots
$service = null;
if ($serviceId > 0) {
  $st = $pdo->prepare("SELECT id, name, description FROM services WHERE id=? AND active=1 LIMIT 1");
  $st->execute(array($serviceId));
  $service = $st->fetch(PDO::FETCH_ASSOC);
  if (!$service) $serviceId = 0;
}

$slots = array();
if ($serviceId > 0) {
  $st = $pdo->prepare("SELECT id, date_day, start_time, end_time, capacity_total, capacity_used, status
                       FROM slots WHERE service_id=? AND date_day=? AND status='open' AND (capacity_total-capacity_used)>0
                       ORDER BY start_time ASC");
  $st->execute(array($serviceId, $dateDay));
  $slots = $st->fetchAll(PDO::FETCH_ASSOC);
}

$dayNames   = array('Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado');
$monthNames = array(1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
                    7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre');
$ts = strtotime($dateDay);
$labelDate = $dateDay;
if ($ts) {
  $labelDate = $dayNames[(int)date('w',$ts)] . ' ' . date('j',$ts) . ' de ' . $monthNames[(int)date('n',$ts)] . ' de ' . date('Y',$ts);
}

$pubTitle = 'Horarios disponibles';
require __DIR__ . '/inc/horas_public_head.php';
?>

<div class="pub-wrap-wide">

  <div class="pub-ph" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
    <div>
      <h1>Horarios disponibles</h1>
      <p>Elige un horario y completa tus datos para reservar</p>
    </div>
    <a href="solicitud_horas.php" class="btn btn-ghost" style="font-size:13px;padding:8px 14px;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Volver
    </a>
  </div>

  <!-- ── Alerta de error ──────────────────────────── -->
  <?php if($errMsg): ?>
  <div class="alert alert-err">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <?php echo horas_h($errMsg); ?>
  </div>
  <?php endif; ?>

  <!-- ── Confirmación ─────────────────────────────── -->
  <?php if($confirm): ?>
  <div class="confirm-card">
    <div class="confirm-head">
      <div class="confirm-head-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div>
        <h3>Reserva confirmada</h3>
        <p>Guarda tu código — lo necesitarás para consultar el estado</p>
      </div>
    </div>
    <div class="confirm-body">
      <div class="confirm-field full" style="grid-column:1/-1;">
        <label>Código de reserva</label>
        <div class="confirm-code"><?php echo horas_h($confirm['code']); ?></div>
      </div>
      <div class="confirm-field">
        <label>Servicio</label>
        <div class="val"><?php echo horas_h($confirm['service']); ?></div>
      </div>
      <div class="confirm-field">
        <label>Fecha y hora</label>
        <div class="val"><?php echo horas_h($confirm['date']); ?> · <?php echo horas_h($confirm['time']); ?></div>
      </div>
      <div class="confirm-field">
        <label>Nombre</label>
        <div class="val"><?php echo horas_h($confirm['name']); ?></div>
      </div>
      <div class="confirm-field">
        <label>RUT</label>
        <div class="val" style="font-family:var(--font-mono);"><?php echo horas_h($confirm['rut']); ?></div>
      </div>
      <?php if($confirm['phone']): ?>
      <div class="confirm-field">
        <label>Teléfono</label>
        <div class="val"><?php echo horas_h($confirm['phone']); ?></div>
      </div>
      <?php endif; ?>
      <?php if($confirm['email']): ?>
      <div class="confirm-field">
        <label>Email</label>
        <div class="val"><?php echo horas_h($confirm['email']); ?></div>
      </div>
      <?php endif; ?>
    </div>
    <div class="confirm-footer">
      <a class="btn btn-primary" href="estado_horas.php?code=<?php echo horas_h($confirm['code']); ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Ver estado
      </a>
      <a class="btn btn-ghost" href="horarios_horas.php">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva reserva
      </a>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Filtro de búsqueda ──────────────────────── -->
  <div class="card">
    <div class="card-body" style="padding:16px 20px;">
      <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="field" style="flex:1;min-width:200px;">
          <label>Servicio</label>
          <select name="service_id">
            <?php foreach($services as $s): ?>
              <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$serviceId?'selected':''); ?>>
                <?php echo horas_h($s['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="flex:1;min-width:160px;">
          <label>Fecha</label>
          <input type="date" name="date" value="<?php echo horas_h($dateDay); ?>" min="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <button class="btn btn-primary" type="submit" style="height:42px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Buscar
        </button>
      </form>
    </div>
  </div>

  <!-- ── Contenido ────────────────────────────────── -->
  <?php if(!$service): ?>
  <div class="card"><div class="card-body">
    <div class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <p>No hay servicios disponibles en este momento.</p>
    </div>
  </div></div>

  <?php else: ?>

  <!-- Servicio + fecha seleccionada -->
  <div class="service-strip">
    <div class="service-strip-left">
      <div class="service-dot"></div>
      <div>
        <div class="service-name"><?php echo horas_h($service['name']); ?></div>
        <?php if(!empty($service['description'])): ?>
          <div class="service-desc"><?php echo horas_h($service['description']); ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="date-badge">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <?php echo horas_h($labelDate); ?>
    </div>
  </div>

  <!-- Slots -->
  <?php if(empty($slots)): ?>
  <div class="card"><div class="card-body">
    <div class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <h3>Sin disponibilidad para esta fecha</h3>
      <p>No hay cupos para el <?php echo horas_h($labelDate); ?>.<br>Prueba seleccionando otra fecha.</p>
    </div>
  </div></div>

  <?php else: ?>

  <p class="section-label"><?php echo count($slots); ?> horario<?php echo count($slots)!==1?'s':''; ?> disponible<?php echo count($slots)!==1?'s':''; ?></p>

  <div class="slots-grid">
    <?php foreach($slots as $sl): ?>
    <?php
      $cupos = (int)$sl['capacity_total'] - (int)$sl['capacity_used'];
      $ini   = substr($sl['start_time'],0,5);
      $fin   = substr($sl['end_time'],0,5);
    ?>
    <div class="slot-card">
      <div class="slot-card-head">
        <div class="slot-time"><?php echo horas_h($ini . ' – ' . $fin); ?></div>
        <div class="slot-cupos"><?php echo $cupos; ?> cupo<?php echo $cupos!==1?'s':''; ?></div>
      </div>
      <div class="slot-form">
        <form method="post">
          <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
          <input type="hidden" name="slot_id"    value="<?php echo (int)$sl['id']; ?>">
          <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
          <input type="hidden" name="date"        value="<?php echo horas_h($dateDay); ?>">

          <div class="fgrid fg2" style="gap:10px;">
            <div class="field full">
              <label>Nombre completo *</label>
              <input name="name" placeholder="Ej: Juan Pérez González" required style="padding:8px 10px;font-size:13.5px;">
            </div>
            <div class="field">
              <label>RUT *</label>
              <input name="rut" placeholder="12345678K" required style="padding:8px 10px;font-size:13.5px;">
            </div>
            <div class="field">
              <label>Teléfono *</label>
              <input name="phone" placeholder="+56912345678" required style="padding:8px 10px;font-size:13.5px;">
            </div>
            <div class="field full">
              <label>Email <span class="opt">(opcional)</span></label>
              <input type="email" name="email" placeholder="correo@ejemplo.cl" style="padding:8px 10px;font-size:13.5px;">
            </div>
          </div>

          <button class="slot-submit" type="submit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Reservar este horario
          </button>
        </form>
        <div class="slot-note">
          <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm-.75 4.75h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/></svg>
          Al confirmar recibirás un código único para consultar el estado de tu solicitud.
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
  <?php endif; ?>

</div><!-- pub-wrap-wide -->
</body>
</html>