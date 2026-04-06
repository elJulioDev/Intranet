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
        if ($d2 < $d1) throw new Exception('La fecha "Hasta" no puede ser menor que "Desde".');

        $rSt = $pdo->prepare("SELECT weekday,start_time,end_time,slot_minutes,capacity_per_slot
                              FROM availability_rules WHERE service_id=? AND active=1");
        $rSt->execute(array($serviceId));
        $rules = $rSt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rules) throw new Exception('No hay reglas activas para este servicio.');

        $byDay = array();
        foreach($rules as $r){
          $wd = (int)$r['weekday'];
          if (!isset($byDay[$wd])) $byDay[$wd] = array();
          $byDay[$wd][] = $r;
        }

        $eSt = $pdo->prepare("SELECT date_day,is_closed,capacity_override
                              FROM availability_exceptions WHERE service_id=? AND date_day BETWEEN ? AND ?");
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
          $weekday = (int)$curr->format('N');

          if (isset($ex[$dateDay]) && (int)$ex[$dateDay]['is_closed']===1){
            $pdo->prepare("UPDATE slots SET status='closed' WHERE service_id=? AND date_day=?")
                ->execute(array($serviceId,$dateDay));
            $closedDays++;
            $curr->modify('+1 day');
            continue;
          }

          if (empty($byDay[$weekday])){ $curr->modify('+1 day'); continue; }

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
        $msg = 'Generación completada. Slots nuevos creados: <b>'.$created.'</b> · Días cerrados aplicados: <b>'.$closedDays.'</b>';
      } catch(Exception $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
      }
    }
  }
}

$navActive = 'slots';
$navTitle  = 'Generar Slots';
require __DIR__ . '/../inc/horas_nav.php';
?>

<div class="ph">
  <div class="ph-left">
    <h1>Generar / Publicar horarios</h1>
    <p>Crea los bloques disponibles según reglas activas y excepciones configuradas</p>
  </div>
</div>

<?php if($msg): ?>
<div class="alert alert-ok">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
  <span><?php echo $msg; ?></span>
</div>
<?php endif; ?>
<?php if($err): ?>
<div class="alert alert-err">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  <?php echo horas_h($err); ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:400px 1fr;gap:20px;align-items:start;">

  <div class="card">
    <div class="card-header">
      <h2>Parámetros de generación</h2>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="horas_csrf" value="<?php echo horas_h(horas_csrf_token()); ?>">

        <div class="form-grid form-grid-2">
          <div class="field full">
            <label>Servicio</label>
            <select name="service_id" required>
              <option value="">— Seleccionar —</option>
              <?php foreach($services as $s): ?>
                <option value="<?php echo (int)$s['id']; ?>"><?php echo horas_h($s['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Desde</label>
            <input type="date" name="from" required>
          </div>
          <div class="field">
            <label>Hasta</label>
            <input type="date" name="to" required>
          </div>
          <div class="full">
            <button class="btn btn-primary" type="submit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
              Generar slots
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Cómo funciona</h2></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;gap:12px;align-items:flex-start;">
          <div style="width:28px;height:28px;border-radius:8px;background:var(--accent-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <span style="font-weight:700;color:var(--accent);font-size:13px;">1</span>
          </div>
          <div>
            <p style="font-weight:600;font-size:13px;">Configura reglas semanales</p>
            <p style="font-size:12.5px;color:var(--muted);">En Configuración defines los horarios y duración de slots para cada día de la semana.</p>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:flex-start;">
          <div style="width:28px;height:28px;border-radius:8px;background:var(--accent-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <span style="font-weight:700;color:var(--accent);font-size:13px;">2</span>
          </div>
          <div>
            <p style="font-weight:600;font-size:13px;">Registra excepciones</p>
            <p style="font-size:12.5px;color:var(--muted);">En Excepciones puedes cerrar días específicos o cambiar cupos para fechas puntuales.</p>
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:flex-start;">
          <div style="width:28px;height:28px;border-radius:8px;background:var(--accent-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <span style="font-weight:700;color:var(--accent);font-size:13px;">3</span>
          </div>
          <div>
            <p style="font-weight:600;font-size:13px;">Genera semana a semana</p>
            <p style="font-size:12.5px;color:var(--muted);">Genera por rangos cortos. Si un slot ya existe no se duplica (INSERT IGNORE).</p>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

</div><!-- hn-main -->
</body>
</html>