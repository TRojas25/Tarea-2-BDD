<?php
session_start();
if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$mensaje = "";
$error = "";

// PROCESAR FORMULARIO (Crear, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];
    
    try {
        if ($accion === 'crear') {
            $sql = "INSERT INTO Centros_medicos (nombre_cm, comuna, region) VALUES (?, ?, ?)";
            $pdo->prepare($sql)->execute([$_POST['nombre_cm'], $_POST['comuna'], $_POST['region']]);
            $mensaje = "Centro médico creado exitosamente.";
        } 
        elseif ($accion === 'editar') {
            $sql = "UPDATE Centros_medicos SET nombre_cm = ?, comuna = ?, region = ? WHERE id_cm = ?";
            $pdo->prepare($sql)->execute([$_POST['nombre_cm'], $_POST['comuna'], $_POST['region'], $_POST['id_cm']]);
            $mensaje = "Centro médico actualizado.";
        } 
        elseif ($accion === 'eliminar') {
            // Eliminar solo si no tiene dependencias (médicos o citas)
            $sql = "DELETE FROM Centros_medicos WHERE id_cm = ?";
            $pdo->prepare($sql)->execute([$_POST['id_cm']]);
            $mensaje = "Centro médico eliminado.";
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "No se puede eliminar el centro porque tiene médicos o citas asociadas.";
        } else {
            $error = "Error de base de datos: " . $e->getMessage();
        }
    }
}

// OBTENER CENTROS
$centros = $pdo->query("SELECT * FROM Centros_medicos ORDER BY region, nombre_cm")->fetchAll(PDO::FETCH_ASSOC);

// EDICIÓN
$centro_edit = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM Centros_medicos WHERE id_cm = ?");
    $stmt->execute([$_GET['editar']]);
    $centro_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestionar Centros Médicos</title>
    <style>
        body { font-family: Arial; background: #f4f4f9; margin: 0; }
        .container { padding: 20px; max-width: 900px; margin: auto; background: white; margin-top: 20px; border-radius: 5px; }
        input, button { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box;}
        button { background-color: #004d99; color: white; border: none; cursor: pointer; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #004d99; color: white; }
        .btn-sm { width: auto; padding: 5px 10px; font-size: 0.9em; display: inline-block; text-decoration: none; text-align: center; }
        .btn-edit { background-color: #f0ad4e; }
        .btn-danger { background-color: #d9534f; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>Gestión de Centros Médicos</h2>
        <?php if($mensaje) echo "<div class='alert success'>$mensaje</div>"; ?>
        <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

        <div style="background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h3><?php echo $centro_edit ? "Editar Centro" : "Registrar Nuevo Centro"; ?></h3>
            <form method="POST" action="admin_centros.php">
                <input type="hidden" name="accion" value="<?php echo $centro_edit ? 'editar' : 'crear'; ?>">
                <?php if($centro_edit): ?>
                    <input type="hidden" name="id_cm" value="<?php echo $centro_edit['id_cm']; ?>">
                <?php endif; ?>
                
                <input type="text" name="nombre_cm" placeholder="Nombre del Centro" required value="<?php echo $centro_edit['nombre_cm'] ?? ''; ?>">
                <input type="text" name="comuna" placeholder="Comuna" required value="<?php echo $centro_edit['comuna'] ?? ''; ?>">
                <input type="text" name="region" placeholder="Región" required value="<?php echo $centro_edit['region'] ?? ''; ?>">
                <button type="submit"><?php echo $centro_edit ? "Guardar Cambios" : "Crear Centro"; ?></button>
                <?php if($centro_edit): ?>
                    <a href="admin_centros.php" style="display:block; text-align:center; color: #d9534f; margin-top:10px;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <table>
            <tr><th>ID (Cód)</th><th>Nombre</th><th>Comuna</th><th>Región</th><th>Acciones</th></tr>
            <?php foreach($centros as $c): ?>
            <tr>
                <td><?php echo $c['id_cm']; ?></td>
                <td><?php echo htmlspecialchars($c['nombre_cm']); ?></td>
                <td><?php echo htmlspecialchars($c['comuna']); ?></td>
                <td><?php echo htmlspecialchars($c['region']); ?></td>
                <td>
                    <a href="admin_centros.php?editar=<?php echo $c['id_cm']; ?>" class="btn-sm btn-edit" style="color:white;">Editar</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este centro?');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_cm" value="<?php echo $c['id_cm']; ?>">
                        <button type="submit" class="btn-sm btn-danger">Borrar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>