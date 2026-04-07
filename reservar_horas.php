<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

$slotId = (int)(isset($_GET['slot_id']) ? $_GET['slot_id'] : (isset($_POST['slot_id']) ? $_POST['slot_id'] : 0));
if ($slotId <= 0) { http_response_code(400); exit('Slot inválido'); }

$slotSt = $pdo->prepare("
  SELECT s.id, s.service_id, s.date_day, s.start_time, s.end_time,
         s.capacity_total, s.capacity_used, s.status,
         sv.name AS service_name
  FROM slots s
  JOIN services sv ON sv.id = s.service_id
  WHERE s.id = ? AND sv.active = 1
  LIMIT 1
");
$slotSt->execute(array($slotId));
$slot = $slotSt->fetch(PDO::FETCH_ASSOC);
if (!$slot || $slot['status'] !== 'open') { http_response_code(404); exit('Horario no disponible'); }

$ok = false; $code = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!horas_csrf_check()) $err = 'CSRF inválido';
  else {
    $name  = trim((string)(isset($_POST['name'])  ? $_POST['name']  : ''));
    $rut   = trim((string)(isset($_POST['rut'])   ? $_POST['rut']   : ''));
    $phone = trim((string)(isset($_POST['phone']) ? $_POST['phone'] : ''));
    $email = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));
    $note  = trim((string)(isset($_POST['note'])  ? $_POST['note']  : ''));

    if ($name === '') $err = 'El nombre es requerido.';
    else {
      // --- SISTEMA DE SEGURIDAD Y COOLDOWN ---
      $ip = $_SERVER['REMOTE_ADDR'];
      
      // 1. Cooldown de Sesión (1 minuto)
      if (isset($_SESSION['last_booking_time']) && (time() - $_SESSION['last_booking_time']) < 60) {
          $err = 'Por favor espera 1 minuto antes de intentar realizar otra reserva.';
      }
      
      // 2. Cooldown de RUT (1 por día)
      if ($err === '' && $rut !== '') {
          $stRut = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE requester_rut = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
          $stRut->execute(array($rut));
          if ($stRut->fetchColumn() >= 1) {
              $err = 'Ya existe una solicitud reciente asociada a este RUT. Intenta mañana.';
          }
      }

      // 3. Cooldown de IP (Máximo 3 por hora)
      if ($err === '') {
          $stIp = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
          $stIp->execute(array($ip));
          if ($stIp->fetchColumn() >= 3) {
              $err = 'Se ha superado el límite de solicitudes desde esta red. Intenta más tarde.';
          }
      }
      // --- FIN SISTEMA DE SEGURIDAD ---

      if ($err === '') {
        try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare("SELECT capacity_total,capacity_used,status FROM slots WHERE id=? FOR UPDATE");
        $lock->execute(array($slotId));
        $s2 = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$s2 || $s2['status'] !== 'open') throw new Exception('Horario cerrado.');
        $free = (int)$s2['capacity_total'] - (int)$s2['capacity_used'];
        if ($free <= 0) throw new Exception('Sin cupos disponibles.');

        for ($i = 0; $i < 6; $i++) {
          $code = horas_random_code(10);
          $chk  = $pdo->prepare("SELECT 1 FROM appointments WHERE code=? LIMIT 1");
          $chk->execute(array($code));
          if (!$chk->fetchColumn()) break;
          $code = '';
        }
        if ($code === '') throw new Exception('No se pudo generar código.');

        $ins = $pdo->prepare("
          INSERT INTO appointments(service_id,slot_id,code,requester_name,requester_rut,requester_phone,requester_email,requester_note,status, created_at, ip_address)
          VALUES(?,?,?,?,?,?,?,?,'pending', NOW(), ?)
        ");
        $ins->execute(array(
          (int)$slot['service_id'], $slotId, $code, $name,
          ($rut===''   ? null : $rut),
          ($phone==='' ? null : $phone),
          ($email==='' ? null : $email),
          ($note===''  ? null : $note),
          $ip
        ));

        $pdo->prepare("UPDATE slots SET capacity_used = capacity_used + 1 WHERE id=?")->execute(array($slotId));
        $pdo->commit();
        
        // Registrar tiempo para cooldown de sesión
        $_SESSION['last_booking_time'] = time();
        
        $ok = true;
      } catch(Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
      }
    }
  }
  }
}

$pubTitle = 'Reservar hora';
require __DIR__ . '/inc/horas_public_head.php';
?>

<div class="pub-wrap">

  <div class="pub-ph" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
    <div>
      <h1>Reservar hora</h1>
      <p>Completa tus datos para confirmar la reserva</p>
    </div>
    <button onclick="history.back()" class="btn btn-ghost" style="font-size:13px;padding:8px 14px;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Volver
    </button>
  </div>

  <!-- ── Info del slot ──────────────────────────── -->
  <div class="service-strip" style="margin-bottom:20px;">
    <div class="service-strip-left">
      <div class="service-dot"></div>
      <div>
        <div class="service-name"><?php echo horas_h($slot['service_name']); ?></div>
        <div class="service-desc"><?php echo horas_h($slot['date_day']); ?> · <?php echo horas_h(substr($slot['start_time'],0,5).' – '.substr($slot['end_time'],0,5)); ?></div>
      </div>
    </div>
    <div class="slot-cupos" style="padding:5px 14px;border-radius:999px;background:var(--accent-bg);color:var(--accent-d);font-size:13px;font-weight:600;">
      <?php echo (int)$slot['capacity_total'] - (int)$slot['capacity_used']; ?> cupo<?php echo ((int)$slot['capacity_total']-(int)$slot['capacity_used'])!==1?'s':''; ?> disponibles
    </div>
  </div>

  <?php if($ok): ?>
  <!-- Confirmación -->
  <div class="confirm-card">
    <div class="confirm-head">
      <div class="confirm-head-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div>
        <h3>Solicitud registrada</h3>
        <p>Guarda tu código para consultar el estado</p>
      </div>
    </div>
    <div class="confirm-body" style="grid-template-columns:1fr;">
      <div class="confirm-field">
        <label>Código de reserva</label>
        <div class="confirm-code"><?php echo horas_h($code); ?></div>
      </div>
    </div>
    <div class="confirm-footer">
      <a class="btn btn-primary" href="estado_horas.php?code=<?php echo horas_h($code); ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Ver estado
      </a>
      <a class="btn btn-ghost" href="solicitud_horas.php">
        Volver al inicio
      </a>
    </div>
  </div>

  <?php else: ?>

  <?php if($err): ?>
  <div class="alert alert-err">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <?php echo horas_h($err); ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header"><h2>Tus datos</h2></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
        <input type="hidden" name="slot_id" value="<?php echo (int)$slotId; ?>">

        <div class="fgrid fg2" style="margin-bottom:16px;">
          <div class="field full">
            <label>Nombre completo *</label>
            <input name="name" placeholder="Ej: Juan Pérez González" required>
          </div>
          <div class="field">
            <label>RUT</label>
            <input name="rut" placeholder="12345678K">
          </div>
          <div class="field">
            <label>Teléfono</label>
            <input name="phone" placeholder="+56912345678">
          </div>
          <div class="field full">
            <label>Email <span class="opt">(opcional)</span></label>
            <input type="email" name="email" placeholder="correo@ejemplo.cl">
          </div>
          <div class="field full">
            <label>Motivo <span class="opt">(opcional)</span></label>
            <textarea name="note" rows="3" style="resize:vertical;" placeholder="Describe brevemente el motivo de tu consulta..."></textarea>
          </div>
        </div>

        <button class="btn btn-primary btn-full btn-lg" type="submit">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Confirmar reserva
        </button>
      </form>
    </div>
  </div>

  <?php endif; ?>

</div><!-- pub-wrap -->
</body>
</html>