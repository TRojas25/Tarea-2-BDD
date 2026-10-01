<?php
$host = 'localhost';
$dbname = 'SaludUSM'; // Cambia esto si le pusiste otro nombre a tu base de datos
$username = 'root';   // El usuario por defecto en XAMPP es siempre 'root'
$password = '';       // La contraseña por defecto en XAMPP es siempre vacía

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Configuramos PDO para que nos muestre los errores de base de datos si ocurren
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // echo "¡Conexión exitosa a la base de datos!"; // Descomenta esta línea para probar
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>