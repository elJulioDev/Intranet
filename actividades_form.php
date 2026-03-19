<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/csrf.php';
require_login();

$fid = current_user_id();
$error = '';
$okmsg = '';

$dirs = $pdo->query("SELECT id, nombre FROM direcciones WHERE activo=1 ORDER BY nombre")->fetchAll();
$unis = $pdo->query("SELECT u.id, CONCAT(d.nombre,' / ',u.nombre) AS nombre
                     FROM unidades u JOIN direcciones d ON d.id=u.direccion_id
                     WHERE u.activo=1 AND d.activo=1
                     ORDER BY d.nombre, u.nombre")->fetchAll();
$sist = $pdo->query("SELECT id, nombre FROM sistemas WHERE activo=1 ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $scope = isset($_POST['scope']) ? $_POST['scope'] : 'unidad'; // 'unidad' o 'direccion'
  $unidadId = null;
  $dirId = null;

  if ($scope === 'unidad') {
    $unidadId = (int)($_POST['unidad_id'] ?? 0);
    if ($unidadId <= 0) $error = 'Debe seleccionar una unidad.';
  } else {
    $dirId = (int)($_POST['direccion_id'] ?? 0);
    if ($dirId <= 0) $error = 'Debe seleccionar una dirección.';
  }

  $titulo = trim((string)($_POST['titulo'] ?? ''));
  $detalle = trim((string)($_POST['detalle'] ?? ''));
  $sistemaId = (int)($_POST['sistema_id'] ?? 0);
  $ref = trim((string)($_POST['referencia'] ?? ''));
  $url = trim((string)($_POST['url_evidencia'] ?? ''));
  $fecha = (string)($_POST['fecha'] ?? date('Y-m-d'));
  $hora = (string)($_POST['hora'] ?? date('H:i:00'));
  $estado = (string)($_POST['estado'] ?? 'informada');
  $prioridad = (string)($_POST['prioridad'] ?? 'media');
  $dur = (int)($_POST['duracion_min'] ?? 0);

  if ($titulo === '') $error = 'El título es obligatorio.';

  if ($error === '') {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    $stmt = $pdo->prepare("
      INSERT INTO actividad_registro
      (funcionario_id, direccion_id, unidad_id, titulo, detalle, sistema_id, referencia, url_evidencia,
       fecha, hora, duracion_min, estado, prioridad, ip, user_agent)
      VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(array(
      $fid,
      $dirId ? $dirId : null,
      $unidadId ? $unidadId : null,
      $titulo,
      $detalle !== '' ? $detalle : null,
      $sistemaId > 0 ? $sistemaId : null,
      $ref !== '' ? $ref : null,
      $url !== '' ? $url : null,
      $fecha,
      $hora,
      $dur > 0 ? $dur : null,
      $estado,
      $prioridad,
      $ip,
      $ua
    ));

    $okmsg = 'Actividad registrada.';
  }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Nueva actividad</title></head>
<body>
  <h2>Nueva actividad</h2>
  <p><a href="actividades_list.php">← Volver</a></p>

  <?php if ($error): ?><p style="color:red;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
  <?php if ($okmsg): ?><p style="color:green;"><?php echo htmlspecialchars($okmsg); ?></p><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

    <label>Registrar para:</label><br>
    <label><input type="radio" name="scope" value="unidad" checked> Unidad</label>
    <label><input type="radio" name="scope" value="direccion"> Dirección</label>
    <br><br>

    <div>
      <label>Unidad</label><br>
      <select name="unidad_id">
        <option value="0">-- Seleccione --</option>
        <?php foreach ($unis as $u): ?>
          <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label>Dirección</label><br>
      <select name="direccion_id">
        <option value="0">-- Seleccione --</option>
        <?php foreach ($dirs as $d): ?>
          <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div><label>Título *</label><br><input name="titulo" style="width:420px" required></div>
    <div><label>Detalle</label><br><textarea name="detalle" rows="4" cols="60"></textarea></div>

    <div>
      <label>Sistema</label><br>
      <select name="sistema_id">
        <option value="0">-- (Opcional) --</option>
        <?php foreach ($sist as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div><label>Referencia (ID/Folio)</label><br><input name="referencia"></div>
    <div><label>URL evidencia</label><br><input name="url_evidencia" style="width:420px"></div>

    <div><label>Fecha</label><br><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></div>
    <div><label>Hora</label><br><input type="time" name="hora" value="<?php echo date('H:i'); ?>"></div>
    <div><label>Duración (min)</label><br><input type="number" name="duracion_min" min="0"></div>

    <div>
      <label>Estado</label><br>
      <select name="estado">
        <option value="informada">informada</option>
        <option value="en_proceso">en_proceso</option>
        <option value="finalizada">finalizada</option>
        <option value="bloqueada">bloqueada</option>
      </select>
    </div>

    <div>
      <label>Prioridad</label><br>
      <select name="prioridad">
        <option value="baja">baja</option>
        <option value="media" selected>media</option>
        <option value="alta">alta</option>
        <option value="critica">critica</option>
      </select>
    </div>

    <br>
    <button type="submit">Guardar</button>
  </form>

  <p style="max-width:720px;color:#555;">
    Nota: el trigger en BD exigirá que se informe SOLO unidad o SOLO dirección.
    En UI, puedes mejorar ocultando el selector que no corresponde según el radio.
  </p>
</body>
</html>
