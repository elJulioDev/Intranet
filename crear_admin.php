<?php
require __DIR__ . '/inc/db.php';

// Generamos el hash seguro para la contraseña "123"
$hash = password_hash('123', PASSWORD_DEFAULT);

try {
    // Insertamos al usuario ADMIN (tu código convierte 'admin' a 'ADMIN')
    $stmt = $pdo->prepare("INSERT INTO funcionarios (rut, clave_hash, activo, nombres, apellidos, is_superadmin) VALUES ('ADMIN', ?, 1, 'Administrador', 'Sistema', 1)");
    $stmt->execute([$hash]);
    
    echo "<h1>¡Usuario creado con éxito!</h1>";
    echo "<p>RUT: admin</p>";
    echo "<p>Clave: 123</p>";
    echo "<a href='login.php'>Ir al Login</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>