<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';

horas_require_login();
$fid = horas_current_funcionario_id();

$services = horas_allowed_services($pdo, $fid, 'manage');

$serviceId = (int)(isset($_GET['service_id']) ? $_GET['service_id'] : 0);
$status = trim((string)(isset($_GET['status']) ? $_GET['status'] : ''));
$date = trim((string)(isset($_GET['date']) ? $_GET['date'] : ''));

$params = array();
$where = array();

if ($serviceId > 0) {
  if (!horas_can_access_service($pdo, $fid, $serviceId, 'manage')) { http_response_code(403); exit('No autorizado'); }
  $where[] = "a.service_id=?";
  $params[] = $serviceId;
} else {
  // restringir a servicios permitidos si no es superadmin
  if (!horas_is_superadmin($pdo, $fid)) {
    $ids = array();
    foreach($services as $s){ $ids[] = (int)$s['id']; }
    if (empty($ids)) $ids = array(-1);
    $where[] = "a.service_id IN (".implode(',', array_fill(0, count($ids), '?')).")";
    foreach($ids as $idv) $params[] = $idv;
  }
}

$labels = array(
  'pending'=>'Pendiente',
  'confirmed'=>'Confirmada',
  'attended'=>'Atendida',
  'no_show'=>'No asistió',
  'cancelled'=>'Cancelada'
);

if ($status !== '' && isset($labels[$status])) {
  $where[] = "a.status=?";
  $params[] = $status;
}

if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  $where[] = "s.date_day=?";
  $params[] = $date;
}

$sql = "SELECT a.id,a.code,a.status,a.requester_name,a.requester_rut,a.requester_phone,a.created_at,
               sv.name AS service_name, s.date_day, s.start_time, s.end_time
        FROM appointments a
        JOIN services sv ON sv.id=a.service_id
        JOIN slots s ON s.id=a.slot_id";
if (!empty($where)) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " ORDER BY s.date_day DESC, s.start_time DESC LIMIT 300";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8">
<title>Horas | Solicitudes</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
  body{font-family:Arial,sans-serif;margin:18px;color:#111;background:#fafafa;}
  .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;}
  .row{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;}
  .muted{color:#666;}
  a.btn, button.btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;cursor:pointer;}
  a.btn:hover, button.btn:hover{background:#111;color:#fff;}
  .toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:10px;}
  .toolbar .field{min-width:220px;}
  label{font-size:12px;color:#333;display:block;margin-bottom:6px;}
  input, select{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;}
  table{width:100%;border-collapse:collapse;margin-top:12px;}
  th,td{border:1px solid #ddd;padding:8px;vertical-align:top;text-align:left;}
  th{background:#f6f6f6;}
  .pill{display:inline-block;padding:3px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px;}
</style>
</head><body>

<div class="row">
  <div class="card" style="min-width:320px;flex:1;">
    <h2 style="margin:0 0 6px 0;">Solicitudes</h2>
    <div class="muted">Revisa, confirma, cancela o reprograma solicitudes.</div>

    <div class="toolbar">
      <a class="btn" href="horas_dashboard.php">← Volver</a>
    </div>

    <form method="get" class="toolbar">
      <div class="field">
        <label>Servicio</label>
        <select name="service_id">
          <option value="0">-- Todos (según permisos) --</option>
          <?php foreach($services as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$serviceId?'selected':''); ?>>
              <?php echo horas_h($s['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>Fecha</label>
        <input type="date" name="date" value="<?php echo horas_h($date); ?>">
      </div>

      <div class="field">
        <label>Estado</label>
        <select name="status">
          <option value="">-- todos --</option>
          <?php foreach($labels as $k=>$v): ?>
            <option value="<?php echo horas_h($k); ?>" <?php echo ($k===$status?'selected':''); ?>>
              <?php echo horas_h($v); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="min-width:140px;">
        <button class="btn" type="submit">Filtrar</button>
      </div>
    </form>

    <table>
      <tr>
        <th style="width:120px;">Fecha</th>
        <th style="width:120px;">Hora</th>
        <th>Servicio</th>
        <th>Solicitante</th>
        <th style="width:130px;">Estado</th>
        <th style="width:120px;">Código</th>
        <th style="width:90px;">Acción</th>
      </tr>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?php echo horas_h($r['date_day']); ?></td>
          <td><?php echo horas_h(substr($r['start_time'],0,5)); ?>-<?php echo horas_h(substr($r['end_time'],0,5)); ?></td>
          <td><?php echo horas_h($r['service_name']); ?></td>
          <td>
            <?php echo horas_h($r['requester_name']); ?><br>
            <span class="muted"><?php echo horas_h($r['requester_rut']); ?></span>
          </td>
          <td><span class="pill"><?php echo horas_h(isset($labels[$r['status']])?$labels[$r['status']]:$r['status']); ?></span></td>
          <td><?php echo horas_h($r['code']); ?></td>
          <td><a class="btn" href="horas_solicitud_ver.php?id=<?php echo (int)$r['id']; ?>">Ver</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($rows)): ?>
        <tr><td colspan="7" class="muted">No hay solicitudes con ese filtro.</td></tr>
      <?php endif; ?>
    </table>

  </div>
</div>

</body></html>
