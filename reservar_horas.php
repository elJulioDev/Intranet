<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

$slotId = (int)($_GET['slot_id'] ?? ($_POST['slot_id'] ?? 0));
if ($slotId<=0){ http_response_code(400); exit('Slot inválido'); }

$slotSt = $pdo->prepare("
  SELECT s.id,s.service_id,s.date_day,s.start_time,s.end_time,s.capacity_total,s.capacity_used,s.status,
         sv.name AS service_name
  FROM slots s
  JOIN services sv ON sv.id=s.service_id
  WHERE s.id=? AND sv.active=1
  LIMIT 1
");
$slotSt->execute([$slotId]);
$slot = $slotSt->fetch(PDO::FETCH_ASSOC);
if(!$slot || $slot['status']!=='open'){ http_response_code(404); exit('Horario no disponible'); }

$ok=false; $code=''; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!horas_csrf_check()) $err='CSRF inválido';
  else {
    $name  = trim((string)($_POST['name'] ?? ''));
    $rut   = trim((string)($_POST['rut'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $note  = trim((string)($_POST['note'] ?? ''));

    if ($name==='') $err='Nombre requerido';
    else {
      try {
        $pdo->beginTransaction();

        // lock slot
        $lock = $pdo->prepare("SELECT capacity_total,capacity_used,status FROM slots WHERE id=? FOR UPDATE");
        $lock->execute([$slotId]);
        $s2 = $lock->fetch(PDO::FETCH_ASSOC);
        if(!$s2 || $s2['status']!=='open') throw new Exception('Horario cerrado.');
        $free = (int)$s2['capacity_total'] - (int)$s2['capacity_used'];
        if ($free<=0) throw new Exception('Sin cupos.');

        // code único
        for($i=0;$i<6;$i++){
          $code = horas_random_code(10);
          $chk = $pdo->prepare("SELECT 1 FROM appointments WHERE code=? LIMIT 1");
          $chk->execute([$code]);
          if(!$chk->fetchColumn()) break;
          $code='';
        }
        if($code==='') throw new Exception('No se pudo generar código.');

        $ins = $pdo->prepare("
          INSERT INTO appointments(service_id,slot_id,code,requester_name,requester_rut,requester_phone,requester_email,requester_note,status)
          VALUES(?,?,?,?,?,?,?,?, 'pending')
        ");
        $ins->execute([
          (int)$slot['service_id'], $slotId, $code,
          $name,
          ($rut===''?null:$rut),
          ($phone===''?null:$phone),
          ($email===''?null:$email),
          ($note===''?null:$note)
        ]);

        $pdo->prepare("UPDATE slots SET capacity_used = capacity_used + 1 WHERE id=?")->execute([$slotId]);

        $pdo->commit();
        $ok=true;
      } catch(Exception $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err=$e->getMessage();
      }
    }
  }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Reservar</title></head>
<body>
  <h2>Reservar hora</h2>
  <p><b>Servicio:</b> <?= horas_h($slot['service_name']) ?><br>
     <b>Fecha:</b> <?= horas_h($slot['date_day']) ?><br>
     <b>Hora:</b> <?= horas_h(substr($slot['start_time'],0,5)) ?>-<?= horas_h(substr($slot['end_time'],0,5)) ?></p>

  <?php if($ok): ?>
    <h3>Solicitud registrada</h3>
    <p>Código: <b><?= horas_h($code) ?></b></p>
    <p><a href="estado_horas.php?code=<?= horas_h($code) ?>">Consultar estado</a></p>
    <p><a href="solicitud_horas.php">Volver</a></p>
  <?php else: ?>
    <?php if($err): ?><p style="color:red"><?= horas_h($err) ?></p><?php endif; ?>

    <form method="post">
      <input type="hidden" name="horas_csrf" value="<?= horas_h(horas_csrf_token()) ?>">
      <input type="hidden" name="slot_id" value="<?= (int)$slotId ?>">

      <label>Nombre *</label><br>
      <input name="name" required><br><br>

      <label>RUT</label><br>
      <input name="rut"><br><br>

      <label>Teléfono</label><br>
      <input name="phone"><br><br>

      <label>Email</label><br>
      <input name="email" type="email"><br><br>

      <label>Motivo</label><br>
      <textarea name="note" rows="4"></textarea><br><br>

      <button type="submit">Confirmar</button>
    </form>
    <p><a href="javascript:history.back()">Volver</a></p>
  <?php endif; ?>
</body></html>
