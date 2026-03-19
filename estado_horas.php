<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
if ($code===''){ http_response_code(400); exit('Código requerido'); }

$st = $pdo->prepare("
  SELECT a.code,a.status,a.created_at, sv.name AS service_name, s.date_day,s.start_time,s.end_time
  FROM appointments a
  JOIN services sv ON sv.id=a.service_id
  JOIN slots s ON s.id=a.slot_id
  WHERE a.code=? LIMIT 1
");
$st->execute([$code]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if(!$row){ http_response_code(404); exit('No encontrado'); }

$labels = [
  'pending'=>'Pendiente','confirmed'=>'Confirmada','attended'=>'Atendida','no_show'=>'No asistió','cancelled'=>'Cancelada'
];
?>
<!doctype html><html><head><meta charset="utf-8"><title>Estado</title></head>
<body>
  <h2>Estado de solicitud</h2>
  <p><b>Código:</b> <?= horas_h($row['code']) ?></p>
  <p><b>Servicio:</b> <?= horas_h($row['service_name']) ?></p>
  <p><b>Fecha:</b> <?= horas_h($row['date_day']) ?></p>
  <p><b>Hora:</b> <?= horas_h(substr($row['start_time'],0,5)) ?>-<?= horas_h(substr($row['end_time'],0,5)) ?></p>
  <p><b>Estado:</b> <?= horas_h($labels[$row['status']] ?? $row['status']) ?></p>
  <p><a href="solicitud_horas.php">Volver</a></p>
</body></html>
