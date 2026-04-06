<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';
horas_require_login();
$fid = horas_current_funcionario_id();

$navActive = 'dashboard';
$navTitle  = 'Panel';
require __DIR__ . '/../inc/horas_nav.php';
?>

<div class="ph">
  <div class="ph-left">
    <h1>Panel de Horas</h1>
    <p>Sistema de gestión de solicitud de horas</p>
  </div>
</div>

<div class="tile-grid">

  <a href="horas_config.php" class="tile">
    <div class="tile-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
    </div>
    <h3>Configuración</h3>
    <p>Define reglas semanales de horario y cupos por servicio.</p>
    <span class="tile-arrow">→</span>
  </a>

  <a href="horas_excepciones.php" class="tile">
    <div class="tile-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="10" y1="14" x2="14" y2="18"/><line x1="14" y1="14" x2="10" y2="18"/></svg>
    </div>
    <h3>Excepciones</h3>
    <p>Cierra días completos o define cupos especiales por fecha.</p>
    <span class="tile-arrow">→</span>
  </a>

  <a href="horas_generar_slots.php" class="tile">
    <div class="tile-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
    </div>
    <h3>Generar Slots</h3>
    <p>Crea los bloques disponibles según las reglas configuradas.</p>
    <span class="tile-arrow">→</span>
  </a>

  <a href="horas_solicitudes.php" class="tile">
    <div class="tile-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    </div>
    <h3>Solicitudes</h3>
    <p>Revisa, confirma, cancela o gestiona solicitudes de horas.</p>
    <span class="tile-arrow">→</span>
  </a>

</div>

</div><!-- hn-main -->
</body>
</html>