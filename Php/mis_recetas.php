<?php
session_start();
if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Paciente') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$rut_paciente = $_SESSION['rut'];

// Consultar las recetas asociadas al paciente conectado
$sql = "SELECT r.medicamento, r.dosis, r.dias_tratamiento, a.motivo, c.fecha_y_hora, m.Nombre_med 
        FROM Recetas r
        JOIN Atenciones a ON r.id_atencion = a.id_atencion
        JOIN Citas c ON a.id_cita = c.id_cita
        JOIN Medicos m ON c.rut_med = m.rut_med
        WHERE c.rut_pac = ?
        ORDER BY c.fecha_y_hora DESC";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([$rut_paciente]);
$recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mis Recetas - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 950px; margin: auto; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #004d99; color: white; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Mis Recetas Médicas</h2>
        <p>Listado de medicamentos recetados en tus atenciones médicas anteriores.</p>
        
        <table>
            <tr>
                <th>Fecha Cita</th>
                <th>Médico Tratante</th>
                <th>Medicamento</th>
                <th>Dosis</th>
                <th>Tratamiento</th>
            </tr>
            <?php if (count($recetas) > 0): ?>
                <?php foreach($recetas as $rec): ?>
                <tr>
                    <td><?php echo $rec['fecha_y_hora']; ?></td>
                    <td><?php echo $rec['Nombre_med']; ?></td>
                    <td><strong><?php echo $rec['medicamento']; ?></strong></td>
                    <td><?php echo $rec['dosis']; ?></td>
                    <td><?php echo $rec['dias_tratamiento']; ?> días</td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No tienes recetas médicas registradas actualmente.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>