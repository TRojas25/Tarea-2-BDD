<?php
session_start();
date_default_timezone_set('America/Santiago');
require 'conexion.php';

$mensaje = "";
$error = "";

$previsiones = $pdo->query("SELECT * FROM Prevision_de_salud")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rut = trim($_POST['rut']);
    $clave = $_POST['clave'];
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $id_prevision = $_POST['id_prevision'];

    if (!preg_match('/^\d{7,8}-[0-9kK]$/', $rut)) {
        $error = "El RUT debe tener el formato XXXXXXXX-X (Ej: 12345678-9).";
    } 
    elseif (strlen($clave) < 6) {
        $error = "La contraseña debe tener un mínimo de 6 caracteres.";
    } 
    else {
        $stmt_check_rut = $pdo->prepare("SELECT COUNT(*) FROM Usuarios WHERE rut = ?");
        $stmt_check_rut->execute([$rut]);
        
        $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM Pacientes WHERE Email = ?");
        $stmt_check_email->execute([$email]);

        if ($stmt_check_rut->fetchColumn() > 0) {
            $error = "El RUT ingresado ya se encuentra registrado en el sistema.";
        } elseif ($stmt_check_email->fetchColumn() > 0) {
            $error = "El correo electrónico ya está asociado a otro paciente.";
        } else {
            try {
                $pdo->beginTransaction();

                $sql_pac = "INSERT INTO Pacientes (rut_pac, Nombre_pac, Email, Telefono, Direccion, id_prevision) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_pac = $pdo->prepare($sql_pac);
                $stmt_pac->execute([$rut, $nombre, $email, $telefono, $direccion, $id_prevision]);

                $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                $sql_usu = "INSERT INTO Usuarios (rut, clave, id_rol, activo) VALUES (?, ?, 3, 1)";
                $stmt_usu = $pdo->prepare($sql_usu);
                $stmt_usu->execute([$rut, $clave_hash]);

                $pdo->commit();
                $mensaje = "¡Registro exitoso! Ya puedes iniciar sesión con tu cuenta.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al procesar el registro: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registro de Pacientes - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        input, select { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #004d99; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        button:hover { background: #003366; }
        .error { color: #d9534f; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; }
        .success { color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #004d99; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="text-align: center; color: #004d99; margin-top: 0;">Registro de Pacientes</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje)): ?>
            <div class="success"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>RUT (Formato XXXXXXXX-X):</label>
            <input type="text" name="rut" placeholder="Ej: 12345678-9" required value="<?php echo htmlspecialchars($_POST['rut'] ?? ''); ?>">
            
            <label>Nombre Completo:</label>
            <input type="text" name="nombre" placeholder="Nombre y Apellido" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
            
            <label>Correo Electrónico:</label>
            <input type="email" name="email" placeholder="correo@ejemplo.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            
            <label>Contraseña (Mínimo 6 caracteres):</label>
            <input type="password" name="clave" placeholder="••••••••" required>
            
            <label>Teléfono:</label>
            <input type="text" name="telefono" placeholder="+569..." value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
            
            <label>Dirección:</label>
            <input type="text" name="direccion" placeholder="Calle, Número, Comuna" value="<?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?>">
            
            <label>Previsión de Salud:</label>
            <select name="id_prevision" required>
                <option value="">-- Seleccione Previsión --</option>
                <?php foreach($previsiones as $p): ?>
                    <option value="<?php echo $p['id_prevision']; ?>"><?php echo $p['Nombre_prevision']; ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit">Registrarse</button>
        </form>

        <a href="index.php" class="back-link">← Volver al Inicio de Sesión</a>
    </div>
</body>
</html>