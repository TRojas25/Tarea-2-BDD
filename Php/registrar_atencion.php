<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Medico') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$id_cita = isset($_GET['id_cita']) ? $_GET['id_cita'] : null;
if (!$id_cita) {
    header("Location: agenda.php");
    exit;
}

// Obtener datos de la cita y el paciente
$sql_cita = "SELECT c.*, p.Nombre_pac, p.rut_pac, prev.Nombre_prevision 
             FROM Citas c
             JOIN Pacientes p ON c.rut_pac = p.rut_pac
             JOIN Prevision_de_salud prev ON p.id_prevision = prev.id_prevision
             WHERE c.id_cita = ? AND c.rut_med = ?";
$stmt = $pdo->prepare($sql_cita);
$stmt->execute([$id_cita, $_SESSION['rut']]);
$cita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
    echo "Cita no encontrada o no autorizada.";
    exit;
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $motivo = $_POST['motivo'];
    $diagnostico_texto = $_POST['diagnostico'];
    $medicamento = $_POST['medicamento'];
    $dosis = $_POST['dosis'];
    $dias = $_POST['dias'];

    try {
        $pdo->beginTransaction();

        // 1. Registrar la atención médica
        $sql_atencion = "INSERT INTO Atenciones (id_cita, motivo) VALUES (?, ?)";
        $stmt_at = $pdo->prepare($sql_atencion);
        $stmt_at->execute([$id_cita, $motivo]);
        $id_atencion = $pdo->lastInsertId();

        // 2. Registrar el diagnóstico asociado
        // (Buscamos si existe un diagnóstico general o insertamos uno descriptivo)
        $sql_diag = "INSERT INTO Diagnosticos (descripcion) VALUES (?)";
        $stmt_d = $pdo->prepare($sql_diag);
        $stmt_d->execute([$diagnostico_texto]);
        $id_diag = $pdo->lastInsertId();

        $sql_atend_diag = "INSERT INTO Diagnosticos_atenciones (id_atencion, id_diag) VALUES (?, ?)";
        $stmt_ad = $pdo->prepare($sql_atend_diag);
        $stmt_ad->execute([$id_atencion, $id_diag]);

        // 3. Registrar receta si se ingresó medicamento
        if (!empty($medicamento)) {
            $sql_receta = "INSERT INTO Recetas (id_atencion, medicamento, dosis, dias_tratamiento) VALUES (?, ?, ?, ?)";
            $stmt_r = $pdo->prepare($sql_receta);
            $stmt_r->execute([$id_atencion, $medicamento, $dosis, $dias]);
        }

        // 4. Actualizar el estado de la cita a 'Atendida' (o el equivalente según tu tabla Estado)
        $stmt_est = $pdo->query("SELECT id_estado FROM Estado WHERE tipo_estado LIKE '%Atendid%' OR tipo_estado LIKE '%Completad%' LIMIT 1");
        $id_estado_nuevo = $stmt_est->fetchColumn();
        if ($id_estado_nuevo) {
            $sql_upd = "UPDATE Citas SET id_estado = ? WHERE id_cita = ?";
            $pdo->prepare($sql_upd)->execute([$id_estado_nuevo, $id_cita]);
        }

        $pdo->commit();
        $mensaje = "¡Atención registrada con éxito!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al registrar la atención: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registrar Atención - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 700px; margin: auto; background: white; margin-top: 30px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input, textarea, button { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; }
        textarea { height: 100px; }
        button { background-color: #004d99; color: white; border: none; cursor: pointer; font-weight: bold; }
        .alert { padding: 10px; background: #d4edda; color: #155724; margin-bottom: 15px; border-radius: 4px; }
        .info-box { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Registrar Atención Médica</h2>
        
        <?php if(!empty($mensaje)): ?>
            <div class="alert"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <div class="info-box">
            <p><strong>Paciente:</strong> <?php echo $cita['Nombre_pac']; ?> (RUT: <?php echo $cita['rut_pac']; ?>)</p>
            <p><strong>Previsión:</strong> <?php echo $cita['Nombre_prevision']; ?></p>
            <p><strong>Fecha de Cita:</strong> <?php echo $cita['fecha_y_hora']; ?></p>
        </div>

        <form method="POST" action="">
            <label>Motivo de Consulta:</label>
            <textarea name="motivo" required placeholder="Describa el motivo de la visita..."></textarea>
            
            <label>Diagnóstico:</label>
            <textarea name="diagnostico" required placeholder="Ingrese el diagnóstico clínico..."></textarea>
            
            <h3>Receta Médica (Opcional)</h3>
            <label>Medicamento:</label>
            <input type="text" name="medicamento" placeholder="Ej: Paracetamol 500mg">
            
            <label>Dosis:</label>
            <input type="text" name="dosis" placeholder="Ej: 1 cada 8 horas">
            
            <label>Días de Tratamiento:</label>
            <input type="number" name="dias" placeholder="Ej: 5">
            
            <button type="submit">Guardar Atención y Finalizar</button>
        </form>
        <br>
        <a href="agenda.php" style="color: #004d99; text-decoration: none;">← Volver a la Agenda</a>
    </div>
</body>
</html>