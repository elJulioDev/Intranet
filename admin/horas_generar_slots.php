<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';

horas_require_login();
$fid = horas_current_funcionario_id();

$services = horas_allowed_services($pdo, $fid, 'config');

$msg=''; $err='';

if ($_SERVER['REQUEST_METHOD']==='POST'){
  if (!horas_csrf_check()) $err='CSRF inválido';
  else {
    $serviceId = (int)(isset($_POST['service_id']) ? $_POST['service_id'] : 0);
    $from = trim((string)(isset($_POST['from']) ? $_POST['from'] : ''));
    $to   = trim((string)(isset($_POST['to']) ? $_POST['to'] : ''));

    if ($serviceId<=0 || !horas_can_access_service($pdo, $fid, $serviceId, 'config')){
      $err='Servicio inválido o sin permisos.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){
      $err='Rango de fechas inválido.';
    } else {
      try {
        $d1 = new DateTime($from);
        $d2 = new DateTime($to);
        if ($d2 < $d1) throw new Exception('Hasta no puede ser menor que Desde.');

        $rSt = $pdo->prepare("SELECT weekday,start_time,end_time,slot_minutes,capacity_per_slot
                              FROM availability_rules
                              WHERE service_id=? AND active=1");
        $rSt->execute(array($serviceId));
        $rules = $rSt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rules) throw new Exception('No hay reglas activas para este servicio.');

        $byDay = array();
        foreach($rules as $r){
          $wd = (int)$r['weekday'];
          if (!isset($byDay[$wd])) $byDay[$wd] = array();
          $byDay[$wd][] = $r;
        }

        // Excepciones en rango
        $eSt = $pdo->prepare("SELECT date_day,is_closed,capacity_override
                              FROM availability_exceptions
                              WHERE service_id=? AND date_day BETWEEN ? AND ?");
        $eSt->execute(array($serviceId,$from,$to));
        $ex = array();
        foreach($eSt->fetchAll(PDO::FETCH_ASSOC) as $e){
          $ex[$e['date_day']] = $e;
        }

        $pdo->beginTransaction();

        $created = 0;
        $closedDays = 0;

        $curr = clone $d1;
        while ($curr <= $d2){
          $dateDay = $curr->format('Y-m-d');
          $weekday = (int)$curr->format('N'); // 1..7

          // Día cerrado por excepción
          if (isset($ex[$dateDay]) && (int)$ex[$dateDay]['is_closed']===1){
            $pdo->prepare("UPDATE slots SET status='closed' WHERE service_id=? AND date_day=?")
                ->execute(array($serviceId,$dateDay));
            $closedDays++;
            $curr->modify('+1 day');
            continue;
          }

          if (empty($byDay[$weekday])){
            $curr->modify('+1 day');
            continue;
          }

          $capOverride = null;
          if (isset($ex[$dateDay]) && $ex[$dateDay]['capacity_override'] !== null){
            $capOverride = (int)$ex[$dateDay]['capacity_override'];
          }

          foreach($byDay[$weekday] as $r){
            $slotMin = (int)$r['slot_minutes'];
            $cap = ($capOverride !== null) ? $capOverride : (int)$r['capacity_per_slot'];

            $start = new DateTime($dateDay.' '.$r['start_time']);
            $end   = new DateTime($dateDay.' '.$r['end_time']);

            while ($start < $end){
              $slotStart = $start->format('H:i:s');
              $next = clone $start;
              $next->modify('+'.$slotMin.' minutes');
              if ($next > $end) break;
              $slotEnd = $next->format('H:i:s');

              $ins = $pdo->prepare("INSERT IGNORE INTO slots(service_id,date_day,start_time,end_time,capacity_total,capacity_used,status)
                                    VALUES(?,?,?,?,?,0,'open')");
              $ins->execute(array($serviceId,$dateDay,$slotStart,$slotEnd,$cap));
              $created += (int)$ins->rowCount();

              $start = $next;
            }
          }

          $curr->modify('+1 day');
        }

        $pdo->commit();
        $msg = 'Slots generados/publicados. Nuevos: '.$created.' · Días cerrados aplicados: '.$closedDays;
      } catch(Exception $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
      }
    }
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8">
<title>Horas | Generar Slots</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
  body{font-family:Arial,sans-serif;margin:18px;color:#111;background:#fafafa;}
  .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;}
  .row{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;}
  .muted{color:#666;}
  a.btn, button.btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#fff;cursor:pointer;}
  a.btn:hover, button.btn:hover{background:#111;color:#fff;}
  .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px;}
  .grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:10px;margin-top:10px;}
  .grid .full{grid-column:1/-1;}
  label{font-size:12px;color:#333;}
  input, select{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;}
  .ok{color:#0a7a2f;}
  .bad{color:#b00020;}
  .hint{font-size:12px;color:#666;margin-top:6px;}
</style>
</head><body>

<div class="row">
  <div class="card" style="min-width:320px;flex:1;">
    <h2 style="margin:0 0 6px 0;">Generar / Publicar horarios (slots)</h2>
    <div class="muted">Crea los bloques disponibles según reglas y excepciones.</div>

    <div class="toolbar">
      <a class="btn" href="horas_dashboard.php">← Volver</a>
    </div>

    <?php if($msg): ?><p class="ok"><b><?php echo horas_h($msg); ?></b></p><?php endif; ?>
    <?php if($err): ?><p class="bad"><b><?php echo horas_h($err); ?></b></p><?php endif; ?>

    <form method="post">
      <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">

      <div class="grid">
        <div>
          <label>Servicio</label>
          <select name="service_id" required>
            <option value="">-- Seleccionar --</option>
            <?php foreach($services as $s): ?>
              <option value="<?php echo (int)$s['id']; ?>"><?php echo horas_h($s['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Desde</label>
          <input type="date" name="from" required>
        </div>

        <div>
          <label>Hasta</label>
          <input type="date" name="to" required>
        </div>

        <div class="full">
          <button class="btn" type="submit">Generar</button>
          <div class="hint">Tip: genera semana a semana. Si ya existe un slot, no se duplica (INSERT IGNORE).</div>
        </div>
      </div>
    </form>
  </div>
</div>

</body></html>
