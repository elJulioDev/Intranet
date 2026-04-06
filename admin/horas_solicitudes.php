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
  if (!horas_is_superadmin($pdo, $fid)) {
    $ids = array();
    foreach($services as $s){ $ids[] = (int)$s['id']; }
    if (empty($ids)) $ids = array(-1);
    $where[] = "a.service_id IN (".implode(',', array_fill(0, count($ids), '?')).")";
    foreach($ids as $idv) $params[] = $idv;
  }
}

$labels = array(
  'pending'   => 'Pendiente',
  'confirmed' => 'Confirmada',
  'attended'  => 'Atendida',
  'no_show'   => 'No asistió',
  'cancelled' => 'Cancelada'
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

$navActive = 'solicitudes';
$navTitle  = 'Solicitudes';
require __DIR__ . '/../inc/horas_nav.php';
?>

<div class="ph">
  <div class="ph-left">
    <h1>Solicitudes</h1>
    <p>Gestiona, confirma y cancela solicitudes de horas</p>
  </div>
</div>

<div class="card">
  <div class="filter-bar">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;width:100%;">
      <div class="field" style="min-width:200px;">
        <label>Servicio</label>
        <select name="service_id">
          <option value="0">— Todos —</option>
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
      <div class="field" style="min-width:180px;">
        <label>Estado</label>
        <select name="status">
          <option value="">— Todos —</option>
          <?php foreach($labels as $k=>$v): ?>
            <option value="<?php echo horas_h($k); ?>" <?php echo ($k===$status?'selected':''); ?>>
              <?php echo horas_h($v); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Filtrar
        </button>
      </div>
      <?php if($status!=='' || $date!=='' || $serviceId>0): ?>
      <div class="field" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <a href="horas_solicitudes.php" class="btn btn-ghost">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Limpiar
        </a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="card-header" style="border-top:1px solid var(--border);border-bottom:none;padding:10px 20px;">
    <span style="font-size:13px;color:var(--muted);">
      <b style="color:var(--txt);"><?php echo count($rows); ?></b> solicitud<?php echo count($rows)!==1?'es':''; ?> encontrada<?php echo count($rows)!==1?'s':''; ?>
    </span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Servicio</th>
          <th>Solicitante</th>
          <th>Estado</th>
          <th>Código</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><b><?php echo horas_h($r['date_day']); ?></b></td>
          <td style="white-space:nowrap;">
            <?php echo horas_h(substr($r['start_time'],0,5)); ?> – <?php echo horas_h(substr($r['end_time'],0,5)); ?>
          </td>
          <td><?php echo horas_h($r['service_name']); ?></td>
          <td>
            <div><?php echo horas_h($r['requester_name']); ?></div>
            <div class="td-muted"><?php echo horas_h($r['requester_rut']); ?></div>
          </td>
          <td>
            <span class="pill pill-<?php echo horas_h($r['status']); ?>">
              <?php echo horas_h(isset($labels[$r['status']])?$labels[$r['status']]:$r['status']); ?>
            </span>
          </td>
          <td style="font-family:monospace;font-size:12px;"><?php echo horas_h($r['code']); ?></td>
          <td>
            <a class="btn btn-ghost btn-sm" href="horas_solicitud_ver.php?id=<?php echo (int)$r['id']; ?>">
              Ver
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?>
        <tr><td colspan="7">
          <div class="empty">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <p>No hay solicitudes con ese filtro.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div><!-- hn-main -->
</body>
</html>