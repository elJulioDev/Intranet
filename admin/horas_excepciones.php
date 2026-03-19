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

          // Cierre/apertura inmediata de slots ya generados
          if ($isClosed===1) {
            $pdo->prepare("UPDATE slots SET status='closed' WHERE service_id=? AND date_day=?")
                ->execute(array($serviceIdPost,$date));
          } else {
            // Reabre slots solo si tienen cupo y no están manualmente cerrados por capacidad 0
            $pdo->prepare("UPDATE slots
                           SET status='open'
                           WHERE service_id=? AND date_day=? AND (capacity_total - capacity_used) > 0")
                ->execute(array($serviceIdPost,$date));
          }

          $pdo->commit();
          $msg='Excepción guardada.';
        }

        if ($action==='delete') {
          $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
          // Para borrar, obtenemos la fecha y luego reabrimos slots (si corresponde)
          $q = $pdo->prepare("SELECT date_day,is_closed FROM availability_exceptions WHERE id=? AND service_id=? LIMIT 1");
          $q->execute(array($id,$serviceIdPost));
          $ex = $q->fetch(PDO::FETCH_ASSOC);

          $pdo->beginTransaction();
          $pdo->prepare("DELETE FROM availability_exceptions WHERE id=? AND service_id=?")->execute(array($id,$serviceIdPost));

          if ($ex && (int)$ex['is_closed']===1) {
            $pdo->prepare("UPDATE slots
                           SET status='open'
                           WHERE service_id=? AND date_day=? AND (capacity_total - capacity_used) > 0")
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
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8">
<title>Horas | Excepciones</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
  body{font-family:Arial,sans-serif;margin:18px;color:#111;background:#fafafa;}
  .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;}
  .row{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;}
  .muted{color:#666;}
  a.btn, button.btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;cursor:pointer;}
  a.btn:hover, button.btn:hover{background:#111;color:#fff;}
  .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px;}
  .grid{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:10px;margin-top:10px;}
  .grid .full{grid-column:1/-1;}
  label{font-size:12px;color:#333;}
  input, select{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;}
  table{width:100%;border-collapse:collapse;margin-top:12px;}
  th,td{border:1px solid #ddd;padding:8px;vertical-align:top;text-align:left;}
  th{background:#f6f6f6;}
  .ok{color:#0a7a2f;}
  .bad{color:#b00020;}
  .hint{font-size:12px;color:#666;margin-top:6px;}
</style>
</head><body>

<div class="row">
  <div class="card" style="min-width:320px;flex:1;">
    <h2 style="margin:0 0 6px 0;">Excepciones por fecha</h2>
    <div class="muted">Cierra días completos o define cupos especiales por fecha.</div>

    <div class="toolbar">
      <a class="btn" href="horas_dashboard.php">← Volver</a>
    </div>

    <?php if($msg): ?><p class="ok"><b><?php echo horas_h($msg); ?></b></p><?php endif; ?>
    <?php if($err): ?><p class="bad"><b><?php echo horas_h($err); ?></b></p><?php endif; ?>

    <form method="get" class="toolbar">
      <label style="min-width:80px;">Servicio</label>
      <select name="service_id" onchange="this.form.submit()">
        <?php foreach($services as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$serviceId?'selected':''); ?>>
            <?php echo horas_h($s['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if($serviceId<=0): ?>
      <p class="muted" style="margin-top:12px;">No tienes servicios asignados.</p>
    <?php else: ?>

      <h3 style="margin:14px 0 6px 0;">Nueva excepción / actualizar</h3>

      <form method="post">
        <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
        <input type="hidden" name="action" value="upsert">
        <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">

        <div class="grid">
          <div>
            <label>Fecha</label>
            <input type="date" name="date_day" required>
          </div>

          <div>
            <label>Cerrar día</label>
            <select name="is_closed">
              <option value="0">No</option>
              <option value="1">Sí</option>
            </select>
            <div class="hint">Si “Sí”, se cierran slots existentes.</div>
          </div>

          <div>
            <label>Cupos override</label>
            <input name="capacity_override" type="number" min="0" max="50" placeholder="vacío = no cambia">
            <div class="hint">Si pones número, se usa al generar.</div>
          </div>

          <div class="full">
            <label>Nota (opcional)</label>
            <input name="note" maxlength="255" placeholder="Ej: feriado / jornada especial">
          </div>

          <div class="full">
            <button class="btn" type="submit">Guardar excepción</button>
          </div>
        </div>
      </form>

      <h3 style="margin:16px 0 6px 0;">Excepciones existentes</h3>

      <table>
        <tr>
          <th style="width:140px;">Fecha</th>
          <th style="width:90px;">Cerrado</th>
          <th style="width:140px;">Override</th>
          <th>Nota</th>
          <th style="width:120px;">Acción</th>
        </tr>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo horas_h($r['date_day']); ?></td>
            <td><?php echo ((int)$r['is_closed']===1?'Sí':'No'); ?></td>
            <td><?php echo ($r['capacity_override']===null?'-':(int)$r['capacity_override']); ?></td>
            <td><?php echo horas_h($r['note']); ?></td>
            <td>
              <form method="post" onsubmit="return confirm('¿Eliminar excepción?');">
                <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="service_id" value="<?php echo (int)$serviceId; ?>">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button class="btn" type="submit">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?>
          <tr><td colspan="5" class="muted">Aún no hay excepciones.</td></tr>
        <?php endif; ?>
      </table>

    <?php endif; ?>
  </div>
</div>

</body></html>
