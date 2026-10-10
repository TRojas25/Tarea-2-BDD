<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$mensaje = "";
$error = "";

$centros = $pdo->query("SELECT * FROM Centros_medicos")->fetchAll(PDO::FETCH_ASSOC);
$especialidades = $pdo->query("SELECT * FROM Especialidades")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rut = trim($_POST['rut']);
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $clave = $_POST['clave'];
    $centros_seleccionados = $_POST['centros'] ?? [];
    $especialidades_seleccionadas = $_POST['especialidades'] ?? [];

    if (!preg_match('/^\d{7,8}-[0-9kK]$/', $rut)) {
        $error = "El RUT debe tener el formato XXXXXXXX-X (Ej: 12000000-1).";
    } elseif (strlen($clave) < 6) {
        $error = "La contraseña debe tener un mínimo de 6 caracteres.";
    } elseif (empty($centros_seleccionados) || empty($especialidades_seleccionadas)) {
        $error = "Debe asignar al menos un centro médico y una especialidad.";
    } else {
        $stmt_check_rut = $pdo->prepare("SELECT COUNT(*) FROM Usuarios WHERE rut = ?");
        $stmt_check_rut->execute([$rut]);
        
        $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM Medicos WHERE Email = ?");
        $stmt_check_email->execute([$email]);

        if ($stmt_check_rut->fetchColumn() > 0) {
            $error = "El RUT ingresado ya se encuentra registrado.";
        } elseif ($stmt_check_email->fetchColumn() > 0) {
            $error = "El correo electrónico ya está asociado a otro médico.";
        } else {
            try {
                $pdo->beginTransaction();

                $sql_med = "INSERT INTO Medicos (rut_med, Nombre_med, Email) VALUES (?, ?, ?)";
                $stmt_med = $pdo->prepare($sql_med);
                $stmt_med->execute([$rut, $nombre, $email]);

                $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                $sql_usu = "INSERT INTO Usuarios (rut, clave, id_rol, activo) VALUES (?, ?, 2, 1)";
                $stmt_usu = $pdo->prepare($sql_usu);
                $stmt_usu->execute([$rut, $clave_hash]);

                $sql_mc = "INSERT INTO Medicos_centro (rut_med, id_cm) VALUES (?, ?)";
                $stmt_mc = $pdo->prepare($sql_mc);
                foreach ($centros_seleccionados as $id_cm) {
                    $stmt_mc->execute([$rut, $id_cm]);
                }

                $sql_me = "INSERT INTO Medico_especialidad (rut_med, id_espec) VALUES (?, ?)";
                $stmt_me = $pdo->prepare($sql_me);
                foreach ($especialidades_seleccionadas as $id_espec) {
                    $stmt_me->execute([$rut, $id_espec]);
                }

                $pdo->commit();
                $mensaje = "¡Médico registrado exitosamente!";
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
    <title>Crear Médico - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 600px; margin: auto; background: white; margin-top: 30px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input, select { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .checkbox-group { border: 1px solid #ccc; padding: 10px; border-radius: 4px; max-height: 150px; overflow-y: auto; margin-bottom: 10px; }
        .checkbox-group label { display: block; margin-bottom: 5px; }
        .checkbox-group input { width: auto; margin-right: 10px; }
        button { background-color: #004d99; color: white; padding: 12px; border: none; cursor: pointer; width: 100%; font-weight: bold; border-radius: 4px; }
        .error { color: #d9534f; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; }
        .success { color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Registrar Nuevo Médico</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje)): ?>
            <div class="success"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>RUT (Formato XXXXXXXX-X):</label>
            <input type="text" name="rut" placeholder="Ej: 12345678-9" required>
            
            <label>Nombre Completo:</label>
            <input type="text" name="nombre" placeholder="Nombre del Médico" required>
            
            <label>Correo Electrónico:</label>
            <input type="email" name="email" placeholder="medico@saludusm.cl" required>
            
            <label>Contraseña (Mínimo 6 caracteres):</label>
            <input type="password" name="clave" required>
            
            <label>Centros Médicos de Atención:</label>
            <div class="checkbox-group">
                <?php foreach($centros as $c): ?>
                    <label><input type="checkbox" name="centros[]" value="<?php echo $c['id_cm']; ?>"> <?php echo $c['nombre_cm'] . ' (' . $c['comuna'] . ')'; ?></label>
                <?php endforeach; ?>
            </div>

            <label>Especialidades:</label>
            <div class="checkbox-group">
                <?php foreach($especialidades as $e): ?>
                    <label><input type="checkbox" name="especialidades[]" value="<?php echo $e['id_espec']; ?>"> <?php echo $e['tipo']; ?></label>
                <?php endforeach; ?>
            </div>
            
            <button type="submit">Guardar Médico</button>
        </form>
    </div>
</body>
</html>