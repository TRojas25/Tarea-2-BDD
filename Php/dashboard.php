<?php
session_start();
// Si no hay sesión iniciada, lo devolvemos al login
if (!isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inicio - SaludUSM</title>
    <style>body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }</style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div style="padding: 20px;">
        <h1>Bienvenido al portal</h1>
        <p>Has ingresado correctamente como <strong><?php echo $_SESSION['rol']; ?></strong>.</p>
        <p>Utiliza el menú superior para navegar por las opciones de tu perfil.</p>
    </div>
</body>
</html>