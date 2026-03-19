<?php
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/horas_helpers.php';

$services = $pdo->query("SELECT id,name FROM services WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Solicitar hora</title>

<style>
body {
  font-family: Arial, sans-serif;
  background: #f4f6f9;
  margin: 0;
  padding: 0;
}

.container {
  max-width: 500px;
  margin: 50px auto;
  background: #fff;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

h2, h3 {
  text-align: center;
  margin-bottom: 20px;
}

label {
  font-weight: bold;
  display: block;
  margin-bottom: 5px;
}

select, input {
  width: 100%;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid #ccc;
  margin-bottom: 15px;
  font-size: 14px;
}

select:focus, input:focus {
  border-color: #007bff;
  outline: none;
}

button {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 6px;
  background: #007bff;
  color: #fff;
  font-size: 15px;
  cursor: pointer;
  transition: 0.3s;
}

button:hover {
  background: #0056b3;
}

hr {
  margin: 30px 0;
  border: none;
  border-top: 1px solid #eee;
}
</style>

</head>

<body>

<div class="container">
  <h2>Solicitar hora</h2>

  <form method="get" action="horarios_horas.php">
    <label>Servicio</label>
    <select name="service_id" required>
      <option value="">-- Seleccionar --</option>
      <?php foreach($services as $s): ?>
        <option value="<?= (int)$s['id'] ?>"><?= horas_h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Fecha</label>
    <input type="date" name="date" required min="<?= date('Y-m-d') ?>">

    <button type="submit">Ver horarios disponibles</button>
  </form>

  <hr>

  <h3>Consultar estado</h3>
  <form method="get" action="estado_horas.php">
    <input name="code" required placeholder="C¨®digo de reserva">
    <button type="submit">Consultar</button>
  </form>
</div>

</body>
</html>