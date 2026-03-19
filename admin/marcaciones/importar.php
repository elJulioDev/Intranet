<?php
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require_login();

if (!function_exists('is_superadmin') || !is_superadmin()) {
  http_response_code(403);
  exit('Acceso denegado.');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Importar marcaciones</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,sans-serif;margin:18px;color:#111}
    .card{border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;max-width:720px}
    .muted{color:#666}
    .btn{display:inline-block;padding:10px 12px;border:1px solid #111;border-radius:8px;text-decoration:none}
    .btn:hover{background:#111;color:#fff}
  </style>
</head>
<body>
  <div class="card">
    <h2 style="margin:0 0 8px 0;">⏱️ Importar marcaciones</h2>
    <p class="muted" style="margin-top:0;">
      Sube un archivo CSV/TXT exportado desde Excel. El sistema detecta TAB/;/, automáticamente.
    </p>

    <form action="procesar_import.php" method="post" enctype="multipart/form-data">
      <div style="margin:10px 0;">
        <input type="file" name="archivo" required>
      </div>
      <button class="btn" type="submit">Procesar archivo</button>
      <a class="btn" href="index.php">Volver</a>
    </form>
  </div>
</body>
</html>
