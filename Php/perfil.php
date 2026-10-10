<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$rut = $_SESSION['rut'];
$rol = $_SESSION['rol'];
$mensaje = "";
$error = "";

//  Lógica para eliminar cuenta (Exclusivo Pacientes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_cuenta']) && $rol === 'Paciente') {
    try {
        $pdo->beginTransaction();

        //  Buscar el ID del estado "Cancelada"
        $stmt_est = $pdo->query("SELECT id_estado FROM Estado WHERE tipo_estado = 'Cancelada'");
        $id_cancelada = $stmt_est->fetchColumn();

        // Cambiar el estado a "Cancelada" SOLO para las citas futuras del paciente
        if ($id_cancelada) {
            $sql_cancelar = "UPDATE Citas SET id_estado = ? WHERE rut_pac = ? AND fecha_y_hora > NOW()";
            $pdo->prepare($sql_cancelar)->execute([$id_cancelada, $rut]);
        }

        //  Eliminar el registro de Usuarios (quitar el acceso al portal)
        // No se borra de 'Pacientes' para mantener la integridad del historial histórico.
        $stmt_usu = $pdo->prepare("DELETE FROM Usuarios WHERE rut = ?");
        $stmt_usu->execute([$rut]);
        
        $pdo->commit();
        session_destroy();
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al eliminar la cuenta: " . $e->getMessage();
    }
}

// Lógica para actualizar datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $nueva_clave = $_POST['clave'];
    
    try {
        $pdo->beginTransaction();

        // Si escribió una clave nueva, la actualizamos
        if (!empty($nueva_clave)) {
            $clave_hash = password_hash($nueva_clave, PASSWORD_DEFAULT);
            $stmt_u = $pdo->prepare("UPDATE Usuarios SET clave = ? WHERE rut = ?");
            $stmt_u->execute([$clave_hash, $rut]);
        }

        // Actualizar datos específicos según el rol
        if ($rol === 'Paciente') {
            $nombre = trim($_POST['nombre']);
            $telefono = trim($_POST['telefono']);
            $id_prevision = $_POST['id_prevision'];
            
            $stmt_p = $pdo->prepare("UPDATE Pacientes SET Nombre_pac = ?, telefono_de_contacto = ?, id_prevision = ? WHERE rut_pac = ?");
            $stmt_p->execute([$nombre, $telefono, $id_prevision, $rut]);
            
        } elseif ($rol === 'Medico') {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            
            $stmt_m = $pdo->prepare("UPDATE Medicos SET Nombre_med = ?, Email = ? WHERE rut_med = ?");
            $stmt_m->execute([$nombre, $email, $rut]);
        }

        $pdo->commit();
        $mensaje = "¡Tus datos han sido actualizados exitosamente!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al actualizar el perfil: " . $e->getMessage();
    }
}

//  Cargar los datos actuales para mostrarlos en los inputs
$datos = [];
$previsiones = [];
if ($rol === 'Paciente') {
    $stmt = $pdo->prepare("SELECT * FROM Pacientes WHERE rut_pac = ?");
    $stmt->execute([$rut]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);
    $previsiones = $pdo->query("SELECT * FROM Prevision_de_salud")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($rol === 'Medico') {
    $stmt = $pdo->prepare("SELECT * FROM Medicos WHERE rut_med = ?");
    $stmt->execute([$rut]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mi Perfil - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 600px; margin: auto; background: white; margin-top: 30px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input, select { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #004d99; color: white; padding: 12px; border: none; cursor: pointer; width: 100%; font-weight: bold; border-radius: 4px; margin-top: 10px;}
        .btn-danger { background-color: #d9534f; margin-top: 25px; }
        .alert-error { color: #d9534f; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Mi Perfil (<?php echo $rol; ?>)</h2>
        <p><strong>RUT:</strong> <?php echo $rut; ?></p>
        
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($mensaje)): ?>
            <div class="alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Campos específicos para PACIENTE -->
            <?php if ($rol === 'Paciente' && $datos): ?>
                <label>Nombre Completo:</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($datos['Nombre_pac']); ?>" required>
                
                <label>Teléfono de Contacto:</label>
                <input type="text" name="telefono" value="<?php echo htmlspecialchars($datos['telefono_de_contacto']); ?>" required>
                
                <label>Previsión de Salud:</label>
                <select name="id_prevision" required>
                    <?php foreach($previsiones as $p): ?>
                        <option value="<?php echo $p['id_prevision']; ?>" <?php if($datos['id_prevision'] == $p['id_prevision']) echo 'selected'; ?>>
                            <?php echo $p['Nombre_prevision']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <!-- Campos específicos para MÉDICO -->
            <?php elseif ($rol === 'Medico' && $datos): ?>
                <label>Nombre Completo:</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($datos['Nombre_med']); ?>" required>
                
                <label>Correo Electrónico:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($datos['Email']); ?>" required>
            
            <!-- Rol ADMINISTRADOR -->
            <?php elseif ($rol === 'Administrador'): ?>
                <p>Eres un usuario Administrador. Tu cuenta de gestión no contiene datos médicos modificables adicionales.</p>
            <?php endif; ?>
            
            <hr style="margin: 20px 0;">
            <label>Nueva Contraseña (Opcional - dejar en blanco para mantener actual):</label>
            <input type="password" name="clave" placeholder="••••••••">
            
            <button type="submit" name="actualizar_perfil">Guardar Cambios</button>
        </form>

        <!-- Botón de Eliminar Cuenta SOLO para Pacientes -->
        <?php if ($rol === 'Paciente'): ?>
            <form method="POST" action="" onsubmit="return confirm('¿Estás seguro de que deseas eliminar tu cuenta permanentemente? Perderás el acceso al portal.');">
                <button type="submit" name="eliminar_cuenta" class="btn-danger">Eliminar Mi Cuenta</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>