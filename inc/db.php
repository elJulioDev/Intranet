<?php
// C:\xampp\htdocs\coltauco\inc\db.php

$host = '127.0.0.1'; // o 'localhost'
$db   = 'coltauco_web'; // Cambia esto si tu base de datos se llama diferente en phpMyAdmin
$user = 'root'; // Usuario por defecto en XAMPP
$pass = '';     // Contraseña por defecto en XAMPP (vacía)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Muestra errores de SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     // Se crea la variable $pdo que necesita banner_mantenedor.php
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Si hay un error de conexión, se detiene la ejecución y muestra el mensaje
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>