<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

// 1. Estadísticas por Centro Médico usando 'id_cm' correctamente
$sql_centros = "SELECT cm.id_cm, cm.nombre_cm, cm.comuna, 
                COUNT(c.id_cita) AS total_citas,
                SUM(CASE WHEN est.tipo_estado = 'No Asistió' THEN 1 ELSE 0 END) AS inasistencias
                FROM Centros_medicos cm
                LEFT JOIN Citas c ON cm.id_cm = c.id_cm
                LEFT JOIN Estado est ON c.id_estado = est.id_estado
                GROUP BY cm.id_cm, cm.nombre_cm, cm.comuna";
$centros = $pdo->query($sql_centros)->fetchAll(PDO::FETCH_ASSOC);

// 2. Top 5 Diagnósticos más frecuentes de la red
$sql_diag = "SELECT d.descripcion, COUNT(da.id_diag) AS frecuencia
             FROM Diagnosticos_atenciones da
             JOIN Diagnosticos d ON da.id_diag = d.id_diag
             GROUP BY d.id_diag, d.descripcion
             ORDER BY frecuencia DESC
             LIMIT 5";
$diagnosticos = $pdo->query($sql_diag)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Panel de Gestión - SaludUSM</title>
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
        <h2>Panel de Gestión - Administrador</h2>
        <p>Resumen estadístico de la red de centros médicos.</p>

        <h3>Estadísticas por Centro Médico</h3>
        <table>
            <tr>
                <th>Centro Médico</th>
                <th>Comuna</th>
                <th>Total de Citas</th>
                <th>% Inasistencia</th>
            </tr>
            <?php if (count($centros) > 0): ?>
                <?php foreach($centros as $cen): 
                    $total = $cen['total_citas'];
                    $inasistencias = $cen['inasistencias'];
                    $porcentaje = ($total > 0) ? ($inasistencias / $total) * 100 : 0;
                ?>
                <tr>
                    <td><?php echo $cen['nombre_cm']; ?></td>
                    <td><?php echo $cen['comuna']; ?></td>
                    <td><?php echo $total; ?></td>
                    <td><strong><?php echo number_format($porcentaje, 1); ?>%</strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No hay datos de centros médicos registrados.</td></tr>
            <?php endif; ?>
        </table>

        <h3>Top 5 Diagnósticos Más Frecuentes de la Red</h3>
        <table>
            <tr>
                <th>Descripción del Diagnóstico</th>
                <th>Frecuencia (Casos Registrados)</th>
            </tr>
            <?php if (count($diagnosticos) > 0): ?>
                <?php foreach($diagnosticos as $d): ?>
                <tr>
                    <td><?php echo $d['descripcion']; ?></td>
                    <td><strong><?php echo $d['frecuencia']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="2">Aún no hay diagnósticos registrados en el sistema.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>