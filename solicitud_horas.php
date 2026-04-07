<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

$services = $pdo->query("SELECT id, name FROM services WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$pubTitle = 'Solicitar Hora';
require __DIR__ . '/inc/horas_public_head.php';
?>

<div class="pub-wrap">

  <div class="pub-ph">
    <h1>Solicitar hora</h1>
    <p>Selecciona el servicio, la fecha y el horario que prefieras</p>
  </div>

  <!-- ── Tabs ─────────────────────────────────────── -->
  <div class="tabs">
    <button class="tab active" onclick="showTab('reservar', this)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
      Nueva reserva
    </button>
    <button class="tab" onclick="showTab('consultar', this)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Consultar estado
    </button>
  </div>

  <!-- ── Tab: Reservar ──────────────────────────────── -->
  <div id="tab-reservar" class="tab-panel active">
    <div class="card">
      <div class="card-header">
        <h2>¿Qué hora necesitas?</h2>
      </div>
      <div class="card-body">
        <form method="get" action="horarios_horas.php">
          <div class="fgrid fg2" style="margin-bottom:16px;">
            <div class="field">
              <label>Servicio</label>
              <select name="service_id" required>
                <option value="">— Seleccionar —</option>
                <?php foreach($services as $s): ?>
                  <option value="<?php echo (int)$s['id']; ?>"><?php echo horas_h($s['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Fecha</label>
              <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
          <button class="btn btn-primary btn-full btn-lg" type="submit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Ver horarios disponibles
          </button>
        </form>
      </div>
    </div>

    <?php if(!empty($services)): ?>
    <div class="card" style="margin-top:16px;">
      <div class="card-header"><h2>Servicios disponibles</h2></div>
      <div style="padding:8px 0;">
        <?php foreach($services as $s): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 20px;border-bottom:1px solid var(--border);">
          <div style="width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0;"></div>
          <span style="font-size:14px;"><?php echo horas_h($s['name']); ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($services)): ?>
        <div style="padding:20px;text-align:center;color:var(--muted);">No hay servicios disponibles actualmente.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Tab: Consultar ────────────────────────────── -->
  <div id="tab-consultar" class="tab-panel">
    <div class="card">
      <div class="card-header">
        <h2>Consultar estado de reserva</h2>
      </div>
      <div class="card-body">
        <form method="get" action="estado_horas.php">
          <div class="field" style="margin-bottom:14px;">
            <label>Código de reserva</label>
            <input name="code" required placeholder="Ej: AB3K7X9QMP" style="font-family:var(--font-mono);letter-spacing:2px;font-size:16px;text-transform:uppercase;">
          </div>
          <button class="btn btn-primary btn-full btn-lg" type="submit">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Consultar
          </button>
        </form>
      </div>
    </div>
  </div>

</div><!-- pub-wrap -->

<script>
function showTab(name, el) {
  document.querySelectorAll('.tab').forEach(function(t){ t.classList.remove('active'); });
  document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
  el.classList.add('active');
  document.getElementById('tab-' + name).classList.add('active');
}
</script>

</body>
</html>