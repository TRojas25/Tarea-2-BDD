<?php
session_start();
require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rut = trim($_POST['rut']);
    $clave = trim($_POST['clave']);

    $query = "SELECT u.rut, u.clave, r.nombre_rol 
              FROM Usuarios u 
              JOIN Roles r ON u.id_rol = r.id_rol 
              WHERE u.rut = :rut";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute(['rut' => $rut]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $clave === $usuario['clave']) {
        $_SESSION['rut'] = $usuario['rut'];
        $_SESSION['rol'] = $usuario['nombre_rol'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "RUT o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; margin-top: 100px; }
        .login-box { border: 1px solid #ccc; padding: 20px; border-radius: 5px; width: 300px; }
        input, button { width: 100%; margin: 10px 0; padding: 8px; box-sizing: border-box; }
        button { background-color: #004d99; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Ingreso SaludUSM</h2>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="POST" action="">
            <label>RUT (Ej: 12000000-0):</label>
            <input type="text" name="rut" required>
            
            <label>Contraseña:</label>
            <input type="password" name="clave" required>
            
            <button type="submit">Entrar</button>
        </form>
        <p style="text-align: center; margin-top: 15px;">
        ¿No tienes cuenta? <a href="registro.php" style="color: #004d99; font-weight: bold;">Regístrate aquí (Solo Pacientes)</a>
        </p>
    </div>
</body>
</html>