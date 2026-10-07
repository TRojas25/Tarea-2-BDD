<?php
session_start();
if (!isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$resultados = [];

if ($busqueda !== '') {
    // Consulta optimizada para agrupar especialidades y centros médicos por cada médico
    $sql = "SELECT m.Nombre_med, m.Email,
                   GROUP_CONCAT(DISTINCT e.tipo SEPARATOR ', ') AS especialidades,
                   GROUP_CONCAT(DISTINCT CONCAT(cm.nombre_cm, ' (', cm.comuna, ')') SEPARATOR '<br>') AS centros
            FROM Medicos m
            LEFT JOIN Medico_especialidad me ON m.rut_med = me.rut_med
            LEFT JOIN Especialidades e ON me.id_espec = e.id_espec
            LEFT JOIN Medicos_centro mc ON m.rut_med = mc.rut_med
            LEFT JOIN Centros_medicos cm ON mc.id_cm = cm.id_cm
            WHERE m.Nombre_med LIKE ? OR e.tipo LIKE ?
            GROUP BY m.rut_med, m.Nombre_med, m.Email";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$busqueda%", "%$busqueda%"]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Buscar Médicos - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 900px; margin: auto; }
        .search-box { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        input[type="text"] { width: 75%; padding: 10px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #004d99; color: white; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: top; }
        th { background-color: #004d99; color: white; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <h2>Buscador de Médicos</h2>
        
        <div class="search-box">
            <form method="GET" action="">
                <input type="text" name="q" placeholder="Busca por trozo de nombre (ej: Tomás) o especialidad (ej: Cardiología)" value="<?php echo htmlspecialchars($busqueda); ?>" required>
                <button type="submit">Buscar</button>
            </form>
        </div>

        <?php if ($busqueda !== ''): ?>
            <table>
                <tr>
                    <th>Nombre del Médico</th>
                    <th>Especialidades</th>
                    <th>Centros Médicos de Atribución</th>
                </tr>
                <?php if (count($resultados) > 0): ?>
                    <?php foreach($resultados as $r): ?>
                    <tr>
                        <td><strong><?php echo $r['Nombre_med']; ?></strong><br><small><?php echo $r['Email']; ?></small></td>
                        <td><?php echo $r['especialidades']; ?></td>
                        <td><?php echo $r['centros']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">No se encontraron médicos con ese criterio de búsqueda.</td></tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>