<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

$code = strtoupper(trim((string)(isset($_GET['code']) ? $_GET['code'] : '')));
$row  = null;
$err  = '';

if ($code !== '') {
  $st = $pdo->prepare("
    SELECT a.code, a.status, a.created_at, a.requester_name, a.requester_rut,
           sv.name AS service_name,
           s.date_day, s.start_time, s.end_time
    FROM appointments a
    JOIN services sv ON sv.id = a.service_id
    JOIN slots s ON s.id = a.slot_id
    WHERE a.code = ? LIMIT 1
  ");
  $st->execute(array($code));
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) $err = 'No se encontró ninguna reserva con ese código.';
}

$labels = array(
  'pending'   => 'Pendiente',
  'confirmed' => 'Confirmada',
  'attended'  => 'Atendida',
  'no_show'   => 'No asistió',
  'cancelled' => 'Cancelada'
);

function fmt_date_full($ymd){
  $ts = strtotime($ymd);
  if (!$ts) return $ymd;
  $days   = array('Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado');
  $months = array(1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
                  7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre');
  return $days[(int)date('w',$ts)] . ', ' . date('j',$ts) . ' de ' . $months[(int)date('n',$ts)] . ' de ' . date('Y',$ts);
}

$pubTitle = 'Estado de reserva';
require __DIR__ . '/inc/horas_public_head.php';
?>

<div class="pub-wrap">

  <div class="pub-ph" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
    <div>
      <h1>Estado de reserva</h1>
      <p>Consulta el estado actual de tu hora</p>
    </div>
    <a href="solicitud_horas.php" class="btn btn-ghost" style="font-size:13px;padding:8px 14px;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Volver
    </a>
  </div>

  <!-- ── Formulario de búsqueda ──────────────────── -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-body">
      <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="field" style="flex:1;min-width:240px;">
          <label>Código de reserva</label>
          <input name="code" value="<?php echo horas_h($code); ?>" required
                 placeholder="Ej: AB3K7X9QMP"
                 style="font-family:var(--font-mono);letter-spacing:2px;font-size:15px;text-transform:uppercase;">
        </div>
        <button class="btn btn-primary" type="submit" style="height:42px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Consultar
        </button>
      </form>
    </div>
  </div>

  <?php if($err): ?>
  <div class="alert alert-err">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <?php echo horas_h($err); ?>
  </div>
  <?php endif; ?>

  <?php if($row): ?>
  <?php
    $status = (string)$row['status'];
    $label  = isset($labels[$status]) ? $labels[$status] : $status;
    $timeRange = substr($row['start_time'],0,5) . ' – ' . substr($row['end_time'],0,5);
  ?>

  <!-- ── Resultado ──────────────────────────────── -->
  <div class="card">
    <div class="card-header">
      <h2>Reserva encontrada</h2>
      <span class="pill pill-<?php echo horas_h($status); ?>"><?php echo horas_h($label); ?></span>
    </div>

    <table class="dtable">
      <tr>
        <th>Código</th>
        <td>
          <span style="font-family:var(--font-mono);font-size:18px;font-weight:700;letter-spacing:2px;color:var(--accent-d);">
            <?php echo horas_h($row['code']); ?>
          </span>
        </td>
      </tr>
      <tr>
        <th>Estado</th>
        <td>
          <span class="pill pill-<?php echo horas_h($status); ?>"><?php echo horas_h($label); ?></span>
          <?php if($status==='confirmed'): ?>
            <span style="font-size:12.5px;color:var(--muted);margin-left:10px;">Tu reserva está confirmada ✓</span>
          <?php elseif($status==='cancelled'): ?>
            <span style="font-size:12.5px;color:var(--danger);margin-left:10px;">Esta reserva fue cancelada.</span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>Servicio</th>
        <td><b><?php echo horas_h($row['service_name']); ?></b></td>
      </tr>
      <tr>
        <th>Fecha</th>
        <td><?php echo horas_h(fmt_date_full($row['date_day'])); ?></td>
      </tr>
      <tr>
        <th>Horario</th>
        <td><?php echo horas_h($timeRange); ?></td>
      </tr>
      <tr>
        <th>Nombre</th>
        <td><?php echo horas_h($row['requester_name']); ?></td>
      </tr>
      <tr>
        <th>RUT</th>
        <td style="font-family:var(--font-mono);"><?php echo horas_h($row['requester_rut']); ?></td>
      </tr>
      <tr>
        <th>Solicitado</th>
        <td style="color:var(--muted);"><?php echo horas_h($row['created_at']); ?></td>
      </tr>
    </table>

    <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;">
      <a class="btn btn-ghost" href="solicitud_horas.php">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva reserva
      </a>
    </div>
  </div>

  <?php elseif($code === ''): ?>
  <div class="card"><div class="card-body">
    <div class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <h3>Ingresa tu código</h3>
      <p>Escribe el código que recibiste al reservar para ver el estado de tu hora.</p>
    </div>
  </div></div>
  <?php endif; ?>

</div><!-- pub-wrap -->
</body>
</html>