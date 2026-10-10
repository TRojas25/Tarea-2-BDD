<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Paciente') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$rut = $_SESSION['rut'];
$mensaje = "";
$error = "";

// 1. Validar que venga el ID de la cita
if (!isset($_GET['id_cita'])) {
    die("ID de cita no proporcionado.");
}
$id_cita = $_GET['id_cita'];

// 2. Obtener datos de la cita actual y validar que pertenezca al paciente
$sql_cita = "SELECT c.*, m.Nombre_med, cm.nombre_cm, est.tipo_estado 
             FROM Citas c 
             JOIN Medicos m ON c.rut_med = m.rut_med 
             JOIN Centros_medicos cm ON c.id_cm = cm.id_cm
             JOIN Estado est ON c.id_estado = est.id_estado
             WHERE c.id_cita = ? AND c.rut_pac = ?";
$stmt = $pdo->prepare($sql_cita);
$stmt->execute([$id_cita, $rut]);
$cita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
    die("Cita no encontrada o no tienes permisos para verla.");
}

// Reglas de negocio: Solo modificable si es futura y Reservada/Confirmada
$es_futura = strtotime($cita['fecha_y_hora']) > time();
$estado_lower = strtolower($cita['tipo_estado']);
$es_modificable = ($estado_lower == 'reservada' || $estado_lower == 'confirmada');

if (!$es_futura || !$es_modificable) {
    die("Esta cita no cumple las condiciones para ser reprogramada.");
}

// 3. Procesar la actualización si se envió el formulario final
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_reprogramacion'])) {
    $nueva_fecha_hora = $_POST['fecha'] . ' ' . $_POST['hora'];
    
    // Validación final de choque de horas (Concurrencia)
    $stmt_choque = $pdo->prepare("SELECT COUNT(*) FROM Citas WHERE rut_med = ? AND fecha_y_hora = ? AND id_estado IN (SELECT id_estado FROM Estado WHERE tipo_estado IN ('Reservada', 'Confirmada'))");
    $stmt_choque->execute([$cita['rut_med'], $nueva_fecha_hora]);
    
    if ($stmt_choque->fetchColumn() > 0) {
        $error = "Lo sentimos, alguien más acaba de tomar ese horario. Elige otro.";
    } elseif (strtotime($nueva_fecha_hora) <= time()) {
        $error = "Debes elegir una hora en el futuro.";
    } else {
        $pdo->prepare("UPDATE Citas SET fecha_y_hora = ? WHERE id_cita = ?")->execute([$nueva_fecha_hora, $id_cita]);
        $mensaje = "Cita reprogramada exitosamente para el " . date('d-m-Y H:i', strtotime($nueva_fecha_hora));
        $cita['fecha_y_hora'] = $nueva_fecha_hora; // Actualizar para la vista
    }
}

// 4. Lógica de Horas Disponibles (Si se seleccionó una fecha)
$fecha_seleccionada = $_POST['fecha'] ?? date('Y-m-d', strtotime('+1 day'));
$horas_disponibles = [];

// Definimos los bloques de atención del centro médico (Ej: 09:00 a 17:00)
$bloques_posibles = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

// Consultar qué horas ya tiene ocupadas el médico ese día (Reservadas o Confirmadas)
$sql_ocupadas = "SELECT TIME(fecha_y_hora) as hora_ocupada 
                 FROM Citas 
                 WHERE rut_med = ? 
                 AND DATE(fecha_y_hora) = ? 
                 AND id_estado IN (SELECT id_estado FROM Estado WHERE tipo_estado IN ('Reservada', 'Confirmada'))";
$stmt_oc = $pdo->prepare($sql_ocupadas);
$stmt_oc->execute([$cita['rut_med'], $fecha_seleccionada]);
$horas_bd = $stmt_oc->fetchAll(PDO::FETCH_COLUMN);

// Limpiar el formato de BD (09:00:00 -> 09:00)
$horas_ocupadas = array_map(function($h) { return substr($h, 0, 5); }, $horas_bd);

// Las horas disponibles son las posibles menos las ocupadas
$horas_disponibles = array_diff($bloques_posibles, $horas_ocupadas);

// Si la fecha elegida es HOY, quitar las horas que ya pasaron
if ($fecha_seleccionada == date('Y-m-d')) {
    $hora_actual = date('H:i');
    $horas_disponibles = array_filter($horas_disponibles, function($h) use ($hora_actual) {
        return $h > $hora_actual;
    });
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reprogramar Cita - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 600px; margin: auto; background: white; margin-top: 30px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .info-box { background: #e9ecef; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        input[type="date"], select { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #f0ad4e; color: white; padding: 12px; border: none; cursor: pointer; width: 100%; font-weight: bold; border-radius: 4px; margin-top: 10px; }
        .btn-secundario { background-color: #6c757d; }
        .alert-error { color: #d9534f; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Reprogramar Cita</h2>
        
        <?php if (!empty($error)): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if (!empty($mensaje)): ?><div class="alert-success"><?php echo $mensaje; ?></div><?php endif; ?>

        <div class="info-box">
            <strong>Médico:</strong> <?php echo htmlspecialchars($cita['Nombre_med']); ?><br>
            <strong>Centro Médico:</strong> <?php echo htmlspecialchars($cita['nombre_cm']); ?><br>
            <strong>Cita Actual:</strong> <?php echo date('d-m-Y H:i', strtotime($cita['fecha_y_hora'])); ?>
        </div>

        <?php if (empty($mensaje)): ?>
            <!-- PASO 1: Elegir el día -->
            <form method="POST" action="">
                <label>1. Selecciona la nueva fecha:</label>
                <input type="date" name="fecha" value="<?php echo $fecha_seleccionada; ?>" min="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            </form>

            <!-- PASO 2: Elegir la hora (Solo muestra si hay disponibles) -->
            <form method="POST" action="">
                <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                
                <label>2. Horarios disponibles para el <?php echo date('d-m-Y', strtotime($fecha_seleccionada)); ?>:</label>
                <?php if (count($horas_disponibles) > 0): ?>
                    <select name="hora" required>
                        <option value="">Selecciona una hora...</option>
                        <?php foreach($horas_disponibles as $hora): ?>
                            <option value="<?php echo $hora; ?>"><?php echo $hora; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="confirmar_reprogramacion">Confirmar Reprogramación</button>
                <?php else: ?>
                    <div class="alert-error" style="margin-top: 10px;">El médico no tiene horas disponibles este día. Por favor elige otra fecha.</div>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <a href="citas.php" style="display:block; text-align:center; margin-top:20px; color:#004d99; text-decoration:none; font-weight:bold;">Volver a Mis Citas</a>
    </div>
</body>
</html>