<?php
// horarios_horas.php — Reserva pública de horas · PHP 5.6
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

    $serviceId = (int)(isset($_POST['service_id']) ? $_POST['service_id'] : $serviceId);
    $dateDay   = (string)(isset($_POST['date']) ? $_POST['date'] : $dateDay);

    if ($slotId <= 0)                              $errMsg = 'Slot inválido.';
    elseif ($name === '' || strlen($name) < 3)     $errMsg = 'Ingresa tu nombre completo.';
    elseif ($rut === '' || strlen($rut) < 7)       $errMsg = 'Ingresa un RUT válido (sin puntos, ej: 12345678K).';
    elseif ($phone === '' || strlen($phone) < 8)   $errMsg = 'Ingresa un número de teléfono válido.';
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errMsg = 'Email inválido.';
    else {
      try {
        $pdo->beginTransaction();

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
        if ($slot['status'] !== 'open') throw new Exception('Este horario ya no está disponible.');

        $left = (int)$slot['capacity_total'] - (int)$slot['capacity_used'];
        if ($left <= 0) throw new Exception('Este horario ya no tiene cupos disponibles.');

        $code = horas_random_code(10);

        $ins = $pdo->prepare("
          INSERT INTO appointments
            (service_id, slot_id, code, requester_name, requester_rut, requester_phone, requester_email, status, created_at)
          VALUES (?,?,?,?,?,?,?, 'confirmed', NOW())
        ");
        $ins->execute(array(
          (int)$slot['service_id'], (int)$slot['id'], $code,
          $name, $rut, $phone,
          ($email === '' ? null : $email)
        ));

        $up = $pdo->prepare("
          UPDATE slots SET capacity_used = capacity_used + 1
          WHERE id = ? AND status='open' AND (capacity_total - capacity_used) > 0
        ");
        $up->execute(array((int)$slot['id']));
        if ($up->rowCount() <= 0) throw new Exception('No se pudo reservar el cupo. Intenta de nuevo.');

        $chk = $pdo->prepare("SELECT capacity_total, capacity_used FROM slots WHERE id=? FOR UPDATE");
        $chk->execute(array((int)$slot['id']));
        $cap = $chk->fetch(PDO::FETCH_ASSOC);
        if ($cap && ((int)$cap['capacity_total'] - (int)$cap['capacity_used']) <= 0) {
          $pdo->prepare("UPDATE slots SET status='closed' WHERE id=?")->execute(array((int)$slot['id']));
        }

        $pdo->commit();

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
        $okMsg = 'Reserva confirmada.';
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
    WHERE service_id=? AND date_day=? AND status='open' AND (capacity_total - capacity_used) > 0
    ORDER BY start_time ASC
  ");
  $st->execute(array($serviceId, $dateDay));
  $slots = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Fecha legible
$dayNames = array('Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado');
$monthNames = array(1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre');
$ts = strtotime($dateDay);
$labelDate = $dateDay;
if ($ts) {
  $labelDate = $dayNames[(int)date('w',$ts)] . ' ' . date('j',$ts) . ' de ' . $monthNames[(int)date('n',$ts)] . ' de ' . date('Y',$ts);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Solicitar Hora | Intranet Municipal</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="static/css/theme.css">
  <link rel="stylesheet" href="static/css/sidebar.css">
  <link rel="stylesheet" href="static/css/horarios_horas.css">
  <link rel="icon" type="image/x-icon" href="static/img/logo.png">
</head>
<body>

<div class="app-shell">

  <?php require __DIR__ . '/inc/sidebar.php'; ?>

  <main class="main-content">
    <div class="hh-page">

      <!-- ── Heading ────────────────────────────────────── -->
      <div class="hh-heading">
        <div class="hh-heading-left">
          <div class="hh-heading-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
              <line x1="8" y1="14" x2="8" y2="14" stroke-width="3"/>
              <line x1="12" y1="14" x2="12" y2="14" stroke-width="3"/>
              <line x1="16" y1="14" x2="16" y2="14" stroke-width="3"/>
            </svg>
          </div>
          <div class="hh-heading-text">
            <h1>Solicitar hora</h1>
            <p>Selecciona el servicio, la fecha y el horario que prefieras.</p>
          </div>
        </div>
      </div>

      <!-- ── Alertas globales ────────────────────────────── -->
      <?php if ($errMsg): ?>
      <div class="hh-alert hh-alert-error">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
        </svg>
        <?php echo horas_h($errMsg); ?>
      </div>
      <?php endif; ?>

      <!-- ── Confirmación ────────────────────────────────── -->
      <?php if ($confirm): ?>
      <div class="hh-confirm">
        <div class="hh-confirm-header">
          <div class="hh-confirm-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>
          <div>
            <div class="hh-confirm-title">Reserva confirmada</div>
            <div class="hh-confirm-sub">Guarda tu código para consultar el estado de tu solicitud</div>
          </div>
        </div>
        <div class="hh-confirm-body">
          <div class="hh-confirm-field" style="grid-column:1/-1;">
            <div class="hh-confirm-label">Código de reserva</div>
            <div class="hh-confirm-code"><?php echo horas_h($confirm['code']); ?></div>
          </div>
          <div class="hh-confirm-field">
            <div class="hh-confirm-label">Servicio</div>
            <div class="hh-confirm-value"><?php echo horas_h($confirm['service']); ?></div>
          </div>
          <div class="hh-confirm-field">
            <div class="hh-confirm-label">Fecha y hora</div>
            <div class="hh-confirm-value"><?php echo horas_h($confirm['date']); ?> · <?php echo horas_h($confirm['time']); ?></div>
          </div>
          <div class="hh-confirm-field">
            <div class="hh-confirm-label">Nombre</div>
            <div class="hh-confirm-value"><?php echo horas_h($confirm['name']); ?></div>
          </div>
          <div class="hh-confirm-field">
            <div class="hh-confirm-label">RUT</div>
            <div class="hh-confirm-value" style="font-family:var(--font-mono);"><?php echo horas_h($confirm['rut']); ?></div>
          </div>
          <?php if ($confirm['phone']): ?>
          <div class="hh-confirm-field">
            <div class="hh-confirm-label">Teléfono</div>
            <div class="hh-confirm-value"><?php echo horas_h($confirm['phone']); ?></div>
          </div>
          <?php endif; ?>
          <?php if ($confirm['email']): ?>
          <div class="hh-confirm-field">
            <div class="hh-confirm-label">Email</div>
            <div class="hh-confirm-value"><?php echo horas_h($confirm['email']); ?></div>
          </div>
          <?php endif; ?>
        </div>
        <div class="hh-confirm-actions">
          <a class="btn btn-secondary btn-sm" href="estado_horas.php?code=<?php echo horas_h($confirm['code']); ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
            Consultar estado
          </a>
          <a class="btn btn-secondary btn-sm" href="horarios_horas.php">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <line x1="12" y1="5" x2="12" y2="19"/>
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nueva reserva
          </a>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── Panel de filtros ─────────────────────────────── -->
      <div class="hh-filter">
        <div class="hh-filter-header">
          <div class="hh-filter-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="11" cy="11" r="8"/>
              <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
          </div>
          <span class="hh-filter-title">Buscar disponibilidad</span>
        </div>
        <form method="get" class="hh-filter-body">
          <div class="hh-filter-field">
            <label class="hh-filter-label" for="f-service">Servicio</label>
            <select class="hh-select" id="f-service" name="service_id">
              <?php foreach ($services as $s): ?>
                <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $serviceId ? 'selected' : ''); ?>>
                  <?php echo horas_h($s['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="hh-filter-field">
            <label class="hh-filter-label" for="f-date">Fecha</label>
            <input class="hh-input" type="date" id="f-date" name="date"
                   value="<?php echo horas_h($dateDay); ?>"
                   min="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div>
            <button class="btn btn-primary" type="submit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
              Ver horarios
            </button>
          </div>
        </form>
      </div>

      <!-- ── Contenido principal ──────────────────────────── -->
      <?php if (!$service): ?>
        <div class="hh-no-services">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
          <p>No hay servicios disponibles en este momento.</p>
        </div>
      <?php else: ?>

        <!-- Info del servicio + fecha seleccionada -->
        <div class="hh-service-strip">
          <div class="hh-service-info">
            <div class="hh-service-dot"></div>
            <div>
              <div class="hh-service-name"><?php echo horas_h($service['name']); ?></div>
              <?php if (!empty($service['description'])): ?>
                <div class="hh-service-desc"><?php echo horas_h($service['description']); ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="hh-date-badge">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <?php echo horas_h($labelDate); ?>
          </div>
        </div>

        <!-- Horarios disponibles -->
        <?php if (empty($slots)): ?>
          <div class="hh-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
            <div class="hh-empty-title">Sin disponibilidad para esta fecha</div>
            <div class="hh-empty-sub">No hay cupos para el <?php echo horas_h($labelDate); ?>. Prueba con otra fecha.</div>
          </div>
        <?php else: ?>

          <div class="hh-section-label">
            <?php echo count($slots); ?> horario<?php echo count($slots) !== 1 ? 's' : ''; ?> disponible<?php echo count($slots) !== 1 ? 's' : ''; ?>
          </div>

          <div class="hh-slots-grid">
            <?php foreach ($slots as $sl): ?>
              <?php
                $cupos = (int)$sl['capacity_total'] - (int)$sl['capacity_used'];
                $ini   = substr($sl['start_time'], 0, 5);
                $fin   = substr($sl['end_time'],   0, 5);
              ?>
              <div class="hh-slot">
                <div class="hh-slot-head">
                  <div class="hh-slot-time"><?php echo horas_h($ini . ' – ' . $fin); ?></div>
                  <div class="hh-slot-badge available">
                    <?php echo $cupos; ?> cupo<?php echo $cupos !== 1 ? 's' : ''; ?>
                  </div>
                </div>

                <div class="hh-slot-form">
                  <form method="post">
                    <input type="hidden" name="horas_csrf"  value="<?php echo horas_h(horas_csrf_token()); ?>">
                    <input type="hidden" name="slot_id"     value="<?php echo (int)$sl['id']; ?>">
                    <input type="hidden" name="service_id"  value="<?php echo (int)$serviceId; ?>">
                    <input type="hidden" name="date"        value="<?php echo horas_h($dateDay); ?>">

                    <div class="hh-slot-fields">
                      <div class="hh-slot-field full">
                        <label class="hh-slot-label" for="name_<?php echo (int)$sl['id']; ?>">Nombre completo *</label>
                        <input class="hh-slot-input" id="name_<?php echo (int)$sl['id']; ?>"
                               name="name" placeholder="Ej: Juan Pérez González" required>
                      </div>
                      <div class="hh-slot-field">
                        <label class="hh-slot-label" for="rut_<?php echo (int)$sl['id']; ?>">RUT *</label>
                        <input class="hh-slot-input" id="rut_<?php echo (int)$sl['id']; ?>"
                               name="rut" placeholder="12345678K" required>
                      </div>
                      <div class="hh-slot-field">
                        <label class="hh-slot-label" for="phone_<?php echo (int)$sl['id']; ?>">Teléfono *</label>
                        <input class="hh-slot-input" id="phone_<?php echo (int)$sl['id']; ?>"
                               name="phone" placeholder="+56912345678" required>
                      </div>
                      <div class="hh-slot-field full">
                        <label class="hh-slot-label" for="email_<?php echo (int)$sl['id']; ?>">Email <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></label>
                        <input class="hh-slot-input" type="email" id="email_<?php echo (int)$sl['id']; ?>"
                               name="email" placeholder="correo@ejemplo.cl">
                      </div>
                    </div>

                    <button class="hh-slot-submit" type="submit">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <polyline points="20 6 9 17 4 12"/>
                      </svg>
                      Reservar este horario
                    </button>
                  </form>
                  <div class="hh-slot-note">
                    <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor">
                      <path d="M8 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1zm0 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm-.75 3.25h1.5v4h-1.5v-4zm0 5h1.5v1.5h-1.5v-1.5z"/>
                    </svg>
                    Al confirmar recibirás un código único para seguimiento.
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

        <?php endif; ?>
      <?php endif; ?>

    </div><!-- /.hh-page -->
  </main>

  </div><!-- /body-layout -->
</div><!-- /app-shell -->

</body>
</html>