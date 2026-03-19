<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/horas_helpers.php';
horas_require_login();
$fid = horas_current_funcionario_id();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Horas - Panel</title></head>
<body>
  <h2>Panel de Solicitud de Horas</h2>
  <ul>
    <li><a href="horas_config.php">Configuración (reglas de cupos/horario)</a></li>
    <li><a href="horas_excepciones.php">Excepciones por fecha (cierres/cupos especiales)</a></li>
    <li><a href="horas_generar_slots.php">Generar/Publicar horarios (slots)</a></li>
    <li><a href="horas_solicitudes.php">Solicitudes (gestión)</a></li>
  </ul>
</body></html>
