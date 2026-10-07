<?php
session_start();
// Forzar la zona horaria oficial de Chile
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Medico') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$rut_medico = $_SESSION['rut'];
// Por defecto toma la fecha actual de Chile
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Consulta de citas para el médico y fecha seleccionada
$sql = "SELECT c.id_cita, c.fecha_y_hora, p.Nombre_pac, prev.Nombre_prevision, cm.nombre_cm, cm.comuna, est.tipo_estado 
        FROM Citas c
        JOIN Pacientes p ON c.rut_pac = p.rut_pac
        JOIN Prevision_de_salud prev ON p.id_prevision = prev.id_prevision
        JOIN Centros_medicos cm ON c.id_cm = cm.id_cm
        JOIN Estado est ON c.id_estado = est.id_estado
        WHERE c.rut_med = ? AND DATE(c.fecha_y_hora) = ?
        ORDER BY c.fecha_y_hora ASC";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([$rut_medico, $fecha_filtro]);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agenda Médica - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 950px; margin: auto; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #004d99; color: white; }
        input[type="date"] { padding: 8px; font-size: 1em; }
        .filter-box { background: white; padding: 15px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Mi Agenda Diaria</h2>
        <p>Citas programadas para tu RUT: <strong><?php echo $rut_medico; ?></strong></p>
        
        <div class="filter-box">
            <form method="GET" action="">
                <label><strong>Seleccionar Fecha:</strong></label>
                <input type="date" name="fecha" value="<?php echo $fecha_filtro; ?>" onchange="this.form.submit()">
            </form>
        </div>

        <table>
            <tr>
                <th>Hora</th>
                <th>Paciente</th>
                <th>Previsión</th>
                <th>Centro Médico</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
            <?php if (count($citas) > 0): ?>
                <?php foreach($citas as $c): ?>
                <tr>
                    <td><strong><?php echo date('H:i', strtotime($c['fecha_y_hora'])); ?></strong></td>
                    <td><?php echo $c['Nombre_pac']; ?></td>
                    <td><?php echo $c['Nombre_prevision']; ?></td>
                    <td><?php echo $c['nombre_cm'] . ' (' . $c['comuna'] . ')'; ?></td>
                    <td><?php echo $c['tipo_estado']; ?></td>
                    <td>
                        <a href="registrar_atencion.php?id_cita=<?php echo $c['id_cita']; ?>" style="color: #004d99; font-weight: bold; text-decoration: none;">Atender</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">No hay citas registradas para la fecha seleccionada.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>