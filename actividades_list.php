<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require_login();

$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

$stmt = $pdo->prepare("
  SELECT ar.id, ar.fecha, ar.hora, ar.titulo, ar.estado, ar.prioridad, ar.referencia,
         ar.unidad_id, ar.direccion_id,
         f.nombres, f.apellidos
  FROM actividad_registro ar
  JOIN funcionarios f ON f.id = ar.funcionario_id
  WHERE ar.fecha = ?
  ORDER BY ar.hora ASC, ar.id DESC
");
$stmt->execute(array($fecha));
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Actividades</title></head>
<body>
  <h2>Actividades del día</h2>
  <p>Usuario: <?php echo htmlspecialchars($_SESSION['nombre']); ?> | <a href="dashboard.php">Volver</a></p>

  <form method="get">
    <label>Fecha: </label>
    <input type="date" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">
    <button type="submit">Ver</button>
    <a href="actividades_form.php">+ Nueva</a>
  </form>

  <table border="1" cellpadding="6" cellspacing="0">
    <tr>
      <th>Hora</th><th>Título</th><th>Estado</th><th>Prioridad</th><th>Ref</th><th>Registró</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php echo htmlspecialchars($r['hora']); ?></td>
        <td><?php echo htmlspecialchars($r['titulo']); ?></td>
        <td><?php echo htmlspecialchars($r['estado']); ?></td>
        <td><?php echo htmlspecialchars($r['prioridad']); ?></td>
        <td><?php echo htmlspecialchars($r['referencia']); ?></td>
        <td><?php echo htmlspecialchars($r['nombres'].' '.$r['apellidos']); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
