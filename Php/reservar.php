<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Paciente') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$centros = $pdo->query("SELECT * FROM Centros_medicos")->fetchAll(PDO::FETCH_ASSOC);
$especialidades = $pdo->query("SELECT * FROM Especialidades")->fetchAll(PDO::FETCH_ASSOC);

$medicos_disponibles = [];
$bloques_libres = [];
$mensaje = "";
$error = ""; // Agregamos la variable de error

$id_cm = isset($_POST['id_cm']) ? $_POST['id_cm'] : null;
$id_espec = isset($_POST['id_espec']) ? $_POST['id_espec'] : null;
$rut_med = isset($_POST['rut_med']) ? $_POST['rut_med'] : null;
$fecha = isset($_POST['fecha']) ? $_POST['fecha'] : null;
$hora_seleccionada = isset($_POST['hora_seleccionada']) ? $_POST['hora_seleccionada'] : null;
$comentario = isset($_POST['comentario']) ? $_POST['comentario'] : null;
$confirmar_final = isset($_POST['confirmar_final']) ? $_POST['confirmar_final'] : null;

// 1. Filtrar médicos por centro y especialidad
if ($id_cm && $id_espec) {
    $sql_med = "SELECT DISTINCT m.rut_med, m.Nombre_med 
                FROM Medicos m
                JOIN Medicos_centro mc ON m.rut_med = mc.rut_med
                JOIN Medico_especialidad me ON m.rut_med = me.rut_med
                WHERE mc.id_cm = ? AND me.id_espec = ?";
    $stmt_m = $pdo->prepare($sql_med);
    $stmt_m->execute([$id_cm, $id_espec]);
    $medicos_disponibles = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
}

$fecha_minima = date('Y-m-d', strtotime('+1 day'));

// 2. Si hay médico y fecha seleccionada (y no estamos en el paso de confirmar todavía)
if ($rut_med && $fecha && !$confirmar_final && !$hora_seleccionada) {
    if ($fecha < $fecha_minima) {
        $error = "Error: Solo se permiten reservas desde el día " . $fecha_minima . " en adelante.";
        $fecha = null;
    } else {
        $horarios_teoricos = [
            "09:00:00", "09:30:00", "10:00:00", "10:30:00", 
            "11:00:00", "11:30:00", "12:00:00", "12:30:00", 
            "13:00:00", "13:30:00", "14:00:00", "14:30:00", 
            "15:00:00", "15:30:00", "16:00:00", "16:30:00"
        ];

        $sql_ocupadas = "SELECT TIME(fecha_y_hora) as hora_cita FROM Citas WHERE rut_med = ? AND DATE(fecha_y_hora) = ? AND id_estado IN (SELECT id_estado FROM Estado WHERE tipo_estado IN ('Reservada', 'Confirmada'))";
        $stmt_ocu = $pdo->prepare($sql_ocupadas);
        $stmt_ocu->execute([$rut_med, $fecha]);
        $ocupadas = $stmt_ocu->fetchAll(PDO::FETCH_COLUMN);

        $bloques_libres = array_diff($horarios_teoricos, $ocupadas);
    }
}

// 3. Procesar inserción final cuando el usuario hace clic en "Confirmar Reserva"
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $confirmar_final == '1') {
    $rut_pac = $_SESSION['rut'];
    
    // Corregimos la forma de armar la fecha y hora final uniendo los inputs
    $fecha_y_hora_final = $_POST['fecha'] . ' ' . $_POST['hora_seleccionada']; 
    
    $rut_med = $_POST['rut_med'];
    $id_cm = $_POST['id_cm'];
    $id_espec = $_POST['id_espec'];

    // Obtenemos el ID del estado "Reservada"
    $stmt_est = $pdo->query("SELECT id_estado FROM Estado WHERE tipo_estado = 'Reservada'");
    $id_estado = $stmt_est->fetchColumn();

    // 1. REGLA: El paciente no puede tener dos citas a la misma fecha y hora
    $stmt_paciente = $pdo->prepare("SELECT COUNT(*) FROM Citas WHERE rut_pac = ? AND fecha_y_hora = ? AND id_estado IN (SELECT id_estado FROM Estado WHERE tipo_estado != 'Cancelada')");
    $stmt_paciente->execute([$rut_pac, $fecha_y_hora_final]);
    if ($stmt_paciente->fetchColumn() > 0) {
        $error = "Ya tienes otra cita agendada en esa misma fecha y hora.";
    }

    // 2. REGLA: Sin sobre-agendamiento del médico
    $stmt_medico = $pdo->prepare("SELECT COUNT(*) FROM Citas WHERE rut_med = ? AND fecha_y_hora = ? AND id_estado IN (SELECT id_estado FROM Estado WHERE tipo_estado != 'Cancelada')");
    $stmt_medico->execute([$rut_med, $fecha_y_hora_final]);
    if (empty($error) && $stmt_medico->fetchColumn() > 0) {
        $error = "El médico ya tiene una cita ocupada en ese horario. Alguien más lo reservó primero.";
    }

    // 3. REGLA: El médico debe poseer la especialidad solicitada
    $stmt_espec = $pdo->prepare("SELECT COUNT(*) FROM Medico_especialidad WHERE rut_med = ? AND id_espec = ?");
    $stmt_espec->execute([$rut_med, $id_espec]);
    if (empty($error) && $stmt_espec->fetchColumn() == 0) {
        $error = "El médico seleccionado no posee la especialidad requerida.";
    }

    // 4. REGLA: El médico debe atender en el centro seleccionado
    $stmt_centro = $pdo->prepare("SELECT COUNT(*) FROM Medicos_centro WHERE rut_med = ? AND id_cm = ?");
    $stmt_centro->execute([$rut_med, $id_cm]);
    if (empty($error) && $stmt_centro->fetchColumn() == 0) {
        $error = "El médico seleccionado no atiende en el centro médico elegido.";
    }

    // Ejecutar el INSERT solo si pasó TODAS las validaciones sin errores
    if (empty($error)) {
        $sql_insert = "INSERT INTO Citas (rut_pac, rut_med, id_cm, id_espec, id_estado, fecha_y_hora, comentario) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $pdo->prepare($sql_insert);
        
        if ($stmt_ins->execute([$rut_pac, $rut_med, $id_cm, $id_espec, $id_estado, $fecha_y_hora_final, $comentario])) {
            $mensaje = "¡Cita reservada y confirmada con éxito para el " . date('d-m-Y H:i', strtotime($fecha_y_hora_final)) . "!";
            // Limpiar selecciones
            $id_cm = $id_espec = $rut_med = $fecha = $hora_seleccionada = $confirmar_final = null;
        } else {
            $error = "Error de base de datos al confirmar la reserva.";
        }
    } else {
        // Si hay un error de validación, quitamos la marca de confirmación final para que vuelva a intentar
        $confirmar_final = null;
    }
}

// Obtener nombres descriptivos para mostrar en el resumen de confirmación
$nombre_centro = "";
$nombre_especialidad = "";
$nombre_medico = "";
if ($id_cm) {
    $st = $pdo->prepare("SELECT nombre_cm, comuna FROM Centros_medicos WHERE id_cm = ?");
    $st->execute([$id_cm]);
    $res = $st->fetch();
    if($res) $nombre_centro = $res['nombre_cm'] . " (" . $res['comuna'] . ")";
}
if ($id_espec) {
    $st = $pdo->prepare("SELECT tipo FROM Especialidades WHERE id_espec = ?");
    $st->execute([$id_espec]);
    $res = $st->fetch();
    if($res) $nombre_especialidad = $res['tipo'];
}
if ($rut_med) {
    $st = $pdo->prepare("SELECT Nombre_med FROM Medicos WHERE rut_med = ?");
    $st->execute([$rut_med]);
    $res = $st->fetch();
    if($res) $nombre_medico = $res['Nombre_med'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agendar Hora - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 650px; margin: auto; background: white; margin-top: 30px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        select, input, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { background-color: #004d99; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .alert-success { padding: 12px; background: #d4edda; color: #155724; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .alert-error { padding: 12px; background: #f8d7da; color: #721c24; margin-bottom: 15px; border-radius: 4px; font-weight: bold; border: 1px solid #f5c6cb; }
        .bloque-btn { background: #28a745; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer; border-radius: 4px; width: auto; display: inline-block; }
        .resumen-box { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .btn-cancelar { background: #6c757d; margin-top: 5px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Agendar Hora Médica</h2>
        
        <?php if(!empty($mensaje)): ?>
            <div class="alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- PASO EXTRA: PANTALLA DE PRE-CONFIRMACIÓN -->
        <?php if($hora_seleccionada && !$confirmar_final): ?>
            <div class="resumen-box">
                <h3>Resumen de su Cita (Por favor confirme)</h3>
                <p><strong>Centro Médico:</strong> <?php echo $nombre_centro; ?></p>
                <p><strong>Especialidad:</strong> <?php echo $nombre_especialidad; ?></p>
                <p><strong>Médico:</strong> <?php echo $nombre_medico; ?></p>
                <p><strong>Fecha y Hora:</strong> <?php echo date('d-m-Y', strtotime($fecha)) . ' a las ' . date('H:i', strtotime($hora_seleccionada)); ?></p>
                <?php if($comentario): ?>
                    <p><strong>Comentario:</strong> <?php echo htmlspecialchars($comentario); ?></p>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <!-- Pasar todos los datos ocultos para la inserción final -->
                <input type="hidden" name="id_cm" value="<?php echo $id_cm; ?>">
                <input type="hidden" name="id_espec" value="<?php echo $id_espec; ?>">
                <input type="hidden" name="rut_med" value="<?php echo $rut_med; ?>">
                <input type="hidden" name="fecha" value="<?php echo $fecha; ?>">
                <input type="hidden" name="hora_seleccionada" value="<?php echo $hora_seleccionada; ?>">
                <input type="hidden" name="comentario" value="<?php echo htmlspecialchars($comentario ?? ''); ?>">
                <input type="hidden" name="confirmar_final" value="1">

                <button type="submit" style="background-color: #28a745; font-size: 1.1em;">Sí, Confirmar Reserva</button>
            </form>
            
            <form method="POST" action="">
                <!-- Botón para volver atrás y cambiar la hora o datos -->
                <input type="hidden" name="id_cm" value="<?php echo $id_cm; ?>">
                <input type="hidden" name="id_espec" value="<?php echo $id_espec; ?>">
                <input type="hidden" name="rut_med" value="<?php echo $rut_med; ?>">
                <input type="hidden" name="fecha" value="<?php echo $fecha; ?>">
                <button type="submit" class="btn-cancelar">Volver a elegir hora</button>
            </form>

        <?php else: ?>
            <!-- FORMULARIO PRINCIPAL DE SELECCIÓN DE PASOS -->
            <form method="POST" action="">
                <label>1. Centro Médico:</label>
                <select name="id_cm" required onchange="this.form.submit()">
                    <option value="">-- Seleccione Centro --</option>
                    <?php foreach($centros as $c): ?>
                        <option value="<?php echo $c['id_cm']; ?>" <?php if($id_cm == $c['id_cm']) echo 'selected'; ?>>
                            <?php echo $c['nombre_cm'] . ' (' . $c['comuna'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label>2. Especialidad:</label>
                <select name="id_espec" required onchange="this.form.submit()">
                    <option value="">-- Seleccione Especialidad --</option>
                    <?php foreach($especialidades as $e): ?>
                        <option value="<?php echo $e['id_espec']; ?>" <?php if($id_espec == $e['id_espec']) echo 'selected'; ?>>
                            <?php echo $e['tipo']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if(count($medicos_disponibles) > 0): ?>
                    <label>3. Médico:</label>
                    <select name="rut_med" required onchange="this.form.submit()">
                        <option value="">-- Seleccione Médico --</option>
                        <?php foreach($medicos_disponibles as $m): ?>
                            <option value="<?php echo $m['rut_med']; ?>" <?php if($rut_med == $m['rut_med']) echo 'selected'; ?>>
                                <?php echo $m['Nombre_med']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if($rut_med): ?>
                    <label>4. Fecha de Atención (A partir del <?php echo $fecha_minima; ?>):</label>
                    <input type="date" name="fecha" value="<?php echo $fecha; ?>" min="<?php echo $fecha_minima; ?>" required onchange="this.form.submit()">
                <?php endif; ?>
            </form>

            <?php if($fecha && count($bloques_libres) > 0): ?>
                <hr>
                <h3>Bloques Horarios Disponibles para el <?php echo date('d-m-Y', strtotime($fecha)); ?>:</h3>
                <form method="POST" action="">
                    <input type="hidden" name="id_cm" value="<?php echo $id_cm; ?>">
                    <input type="hidden" name="id_espec" value="<?php echo $id_espec; ?>">
                    <input type="hidden" name="rut_med" value="<?php echo $rut_med; ?>">
                    <input type="hidden" name="fecha" value="<?php echo $fecha; ?>">
                    
                    <label>Comentario (Opcional):</label>
                    <input type="text" name="comentario" placeholder="Motivo de la consulta" value="<?php echo htmlspecialchars($comentario ?? ''); ?>"><br>

                    <p>Haga clic en el bloque horario que desea reservar:</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach($bloques_libres as $hora): ?>
                            <button type="submit" name="hora_seleccionada" value="<?php echo $hora; ?>" class="bloque-btn">
                                <?php echo date('H:i', strtotime($hora)); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php elseif($fecha): ?>
                <p style="color: red; margin-top: 20px;">No hay bloques horarios disponibles para este médico en la fecha seleccionada.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>