<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Paciente') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$rut_paciente = $_SESSION['rut'];

// Consulta completa uniendo las tablas del modelo lógico
$sql = "SELECT c.fecha_y_hora, m.Nombre_med, esp.tipo AS especialidad, cm.nombre_cm, cm.comuna, est.tipo_estado 
        FROM Citas c
        JOIN Medicos m ON c.rut_med = m.rut_med
        JOIN Especialidades esp ON c.id_espec = esp.id_espec
        JOIN Centros_medicos cm ON c.id_cm = cm.id_cm
        JOIN Estado est ON c.id_estado = est.id_estado
        WHERE c.rut_pac = ?
        ORDER BY c.fecha_y_hora ASC";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([$rut_paciente]);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ahora = date('Y-m-d H:i:s');
$proximas = [];
$historicas = [];

// Clasificar dinámicamente entre próximas e históricas
foreach ($citas as $cita) {
    if ($cita['fecha_y_hora'] >= $ahora) {
        $proximas[] = $cita;
    } else {
        $historicas[] = $cita;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mis Citas - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 950px; margin: auto; }
        table { width: 100%; border-collapse: collapse; background: white; margin-bottom: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #004d99; color: white; }
        h3 { color: #004d99; border-bottom: 2px solid #004d99; padding-bottom: 5px; margin-top: 30px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Mis Citas Médicas</h2>
        <p>Revisa el estado de tus horas agendadas y tu historial médico.</p>

        <h3>Próximas Citas</h3>
        <table>
            <tr>
                <th>Fecha y Hora</th>
                <th>Médico</th>
                <th>Especialidad</th>
                <th>Centro Médico</th>
                <th>Estado</th>
            </tr>
            <?php if (count($proximas) > 0): ?>
                <?php foreach($proximas as $c): ?>
                <tr>
                    <td><?php echo date('d-m-Y H:i', strtotime($c['fecha_y_hora'])); ?></td>
                    <td><?php echo $c['Nombre_med']; ?></td>
                    <td><?php echo $c['especialidad']; ?></td>
                    <td><?php echo $c['nombre_cm'] . ' (' . $c['comuna'] . ')'; ?></td>
                    <td><strong><?php echo $c['tipo_estado']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No tienes citas médicas próximas programadas.</td></tr>
            <?php endif; ?>
        </table>

        <h3>Citas Históricas</h3>
        <table>
            <tr>
                <th>Fecha y Hora</th>
                <th>Médico</th>
                <th>Especialidad</th>
                <th>Centro Médico</th>
                <th>Estado</th>
            </tr>
            <?php if (count($historicas) > 0): ?>
                <?php foreach($historicas as $c): ?>
                <tr>
                    <td><?php echo date('d-m-Y H:i', strtotime($c['fecha_y_hora'])); ?></td>
                    <td><?php echo $c['Nombre_med']; ?></td>
                    <td><?php echo $c['especialidad']; ?></td>
                    <td><?php echo $c['nombre_cm'] . ' (' . $c['comuna'] . ')'; ?></td>
                    <td><?php echo $c['tipo_estado']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No tienes registros en tu historial de citas.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>