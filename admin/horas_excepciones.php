<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';

horas_require_login();
$fid = horas_current_funcionario_id();

$services = horas_allowed_services($pdo, $fid, 'config');

$serviceId = (int)(isset($_GET['service_id']) ? $_GET['service_id'] : 0);
if ($serviceId <= 0 && !empty($services)) $serviceId = (int)$services[0]['id'];

if ($serviceId > 0 && !horas_can_access_service($pdo, $fid, $serviceId, 'config')) {
  http_response_code(403); exit('No autorizado.');
}

$msg=''; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!horas_csrf_check()) $err='CSRF inválido';
  else {
    $action = (string)(isset($_POST['action']) ? $_POST['action'] : '');
    $serviceIdPost = (int)(isset($_POST['service_id']) ? $_POST['service_id'] : 0);

    if ($serviceIdPost<=0 || !horas_can_access_service($pdo, $fid, $serviceIdPost, 'config')) {
      $err='Servicio inválido o sin permisos.';
    } else {
      try {
        if ($action==='upsert') {
          $date = trim((string)(isset($_POST['date_day']) ? $_POST['date_day'] : ''));
          $isClosed = ((int)(isset($_POST['is_closed']) ? $_POST['is_closed'] : 0)===1) ? 1 : 0;
          $capOv = trim((string)(isset($_POST['capacity_override']) ? $_POST['capacity_override'] : ''));
          $note = trim((string)(isset($_POST['note']) ? $_POST['note'] : ''));

          if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new Exception('Fecha inválida.');

          $cap = null;
          if ($capOv !== '') {
            $cap = (int)$capOv;
            if ($cap < 0 || $cap > 50) throw new Exception('Cupos override inválido.');
          }

          $pdo->beginTransaction();
          $st = $pdo->prepare("
            INSERT INTO availability_exceptions(service_id,date_day,is_closed,capacity_override,note)
            VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              is_closed=VALUES(is_closed),
              capacity_override=VALUES(capacity_override),
              note=VALUES(note)
          ");
          $st->execute(array($serviceIdPost,$date,$isClosed,$cap,($note===''?null:$note)));

          if ($isClosed===1) {
            $pdo->prepare("UPDATE slots SET status='closed' WHERE service_id=? AND date_day=?")
                ->execute(array($serviceIdPost,$date));
          } else {
            $pdo->prepare("UPDATE slots SET status='open' WHERE service_id=? AND date_day=? AND (capacity_total - capacity_used) > 0")
                ->execute(array($serviceIdPost,$date));
          }
          $pdo->commit();
          $msg='Excepción guardada.';
        }

        if ($action==='delete') {
          $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
          $q = $pdo->prepare("SELECT date_day,is_closed FROM availability_exceptions WHERE id=? AND service_id=? LIMIT 1");
          $q->execute(array($id,$serviceIdPost));
          $ex = $q->fetch(PDO::FETCH_ASSOC);

          $pdo->beginTransaction();
          $pdo->prepare("DELETE FROM availability_exceptions WHERE id=? AND service_id=?")->execute(array($id,$serviceIdPost));

          if ($ex && (int)$ex['is_closed']===1) {
            $pdo->prepare("UPDATE slots SET status='open' WHERE service_id=? AND date_day=? AND (capacity_total - capacity_used) > 0")
                ->execute(array($serviceIdPost,$ex['date_day']));
          }
          $pdo->commit();
          $msg='Excepción eliminada.';
        }

        $serviceId = $serviceIdPost;
      } catch(Exception $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err=$e->getMessage();
      }
    }
  }
}

$rows=array();
if ($serviceId>0){
  $st = $pdo->prepare("SELECT * FROM availability_exceptions WHERE service_id=? ORDER BY date_day DESC LIMIT 250");
  $st->execute(array($serviceId));
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);
}

$navActive = 'excepciones';
$navTitle  = 'Excepciones';
require __DIR__ . '/../inc/horas_nav.php';
?>

<div class="ph">
  <div class="ph-left">
    <h1>Excepciones por fecha</h1>
    <p>Cierra días completos o define cupos especiales para fechas específicas</p>
  </div>
  <?php if(count($services) > 1): ?>
  <div class="ph-actions">
    <form method="get">
      <div class="field" style="margin:0;min-width:220px;">
        <select name="service_id" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;">
          <?php foreach($services as $s): ?>
            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$serviceId?'selected':''); ?>>
              <?php echo horas_h($s['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if($msg): ?>
<div class="alert alert-ok">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
  <?php echo horas_h($msg); ?>
</div>
<?php endif; ?>
<?php if($err): ?>
<div class="alert alert-err">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  <?php echo horas_h($err); ?>
</div>
<?php endif; ?>

<?php if($serviceId <= 0): ?>
<div class="card"><div class="card-body">
  <div class="empty"><p>No tienes servicios asignados.</p></div>
</div></div>
<?php else: ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">

  <div class="card">
    <div class="card-header">
      <h2>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva excepción
      </h2>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
        <input type="hidden" name="action" value="upsert">
        <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">

        <div class="form-grid form-grid-2">
          <div class="field full">
            <label>Fecha</label>
            <input type="date" name="date_day" required>
          </div>
          <div class="field">
            <label>Cerrar día</label>
            <select name="is_closed">
              <option value="0">No</option>
              <option value="1">Sí — cerrar</option>
            </select>
            <span class="hint">Cierra slots existentes</span>
          </div>
          <div class="field">
            <label>Cupos override</label>
            <input name="capacity_override" type="number" min="0" max="50" placeholder="Vacío = sin cambio">
          </div>
          <div class="field full">
            <label>Nota (opcional)</label>
            <input name="note" maxlength="255" placeholder="Ej: Feriado, jornada especial…">
          </div>
          <div class="full">
            <button class="btn btn-primary" type="submit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              Guardar excepción
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Excepciones registradas
        <span style="font-size:12px;font-weight:400;color:var(--muted);margin-left:8px;"><?php echo count($rows); ?> registro<?php echo count($rows)!==1?'s':''; ?></span>
      </h2>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Cerrado</th>
            <th>Override</th>
            <th>Nota</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><b><?php echo horas_h($r['date_day']); ?></b></td>
            <td>
              <?php if((int)$r['is_closed']===1): ?>
                <span class="pill pill-yes">Cerrado</span>
              <?php else: ?>
                <span class="pill pill-no">Abierto</span>
              <?php endif; ?>
            </td>
            <td><?php echo ($r['capacity_override']===null ? '<span style="color:var(--muted)">–</span>' : (int)$r['capacity_override']); ?></td>
            <td style="color:var(--muted)"><?php echo horas_h($r['note'] ?: ''); ?></td>
            <td>
              <form method="post" onsubmit="return confirm('¿Eliminar esta excepción?');">
                <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($rows)): ?>
          <tr><td colspan="5">
            <div class="empty"><p>Aún no hay excepciones para este servicio.</p></div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php endif; ?>

</div><!-- hn-main -->
</body>
</html>