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

// =====================================================================
// 1. REGLA DE NEGOCIO: AUTO-CANCELAR CITAS VENCIDAS
// =====================================================================
try {
    $id_cancelada = $pdo->query("SELECT id_estado FROM Estado WHERE tipo_estado = 'Cancelada'")->fetchColumn();
    $sql_vencidas = "UPDATE Citas 
                     SET id_estado = ? 
                     WHERE fecha_y_hora < NOW() 
                     AND id_estado IN (SELECT id_estado FROM Estado WHERE tipo_estado IN ('Reservada', 'Confirmada'))";
    $pdo->prepare($sql_vencidas)->execute([$id_cancelada]);
} catch (Exception $e) {
    // Ignorar error silenciosamente para no interrumpir la vista
}

// =====================================================================
// 2. ACCIONES DEL PACIENTE (Cancelar)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rol === 'Paciente' && isset($_POST['cancelar_cita'])) {
    $id_cita = $_POST['id_cita'];
    $stmt_val = $pdo->prepare("SELECT fecha_y_hora, id_estado FROM Citas WHERE id_cita = ? AND rut_pac = ?");
    $stmt_val->execute([$id_cita, $rut]);
    $cita_val = $stmt_val->fetch(PDO::FETCH_ASSOC);

    if ($cita_val) {
        $estado_actual = $pdo->query("SELECT tipo_estado FROM Estado WHERE id_estado = " . $cita_val['id_estado'])->fetchColumn();
        if (strtotime($cita_val['fecha_y_hora']) > time() && in_array(strtolower($estado_actual), ['reservada', 'confirmada'])) {
            $pdo->prepare("UPDATE Citas SET id_estado = ? WHERE id_cita = ?")->execute([$id_cancelada, $id_cita]);
            $mensaje = "Cita cancelada exitosamente.";
        } else {
            $error = "No puedes cancelar una cita pasada o en este estado.";
        }
    }
}

// =====================================================================
// 3. ACCIONES DEL MÉDICO (Cambiar Estado)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rol === 'Medico' && isset($_POST['nuevo_estado'])) {
    $id_cita = $_POST['id_cita_medico'];
    $nombre_nuevo_estado = $_POST['nuevo_estado'];
    
    // Validar que el estado elegido sea uno de los permitidos para el médico
    if (in_array($nombre_nuevo_estado, ['Confirmada', 'Atendida', 'No Asistió'])) {
        // Verificar que la cita le pertenece a este médico
        $stmt_check = $pdo->prepare("SELECT id_cita FROM Citas WHERE id_cita = ? AND rut_med = ?");
        $stmt_check->execute([$id_cita, $rut]);
        if ($stmt_check->fetch()) {
            $id_nuevo_estado = $pdo->prepare("SELECT id_estado FROM Estado WHERE tipo_estado = ?");
            $id_nuevo_estado->execute([$nombre_nuevo_estado]);
            $id_est = $id_nuevo_estado->fetchColumn();
            
            $pdo->prepare("UPDATE Citas SET id_estado = ? WHERE id_cita = ?")->execute([$id_est, $id_cita]);
            $mensaje = "Estado de la cita actualizado a: $nombre_nuevo_estado.";
        }
    }
}

// --- OBTENER DATOS PARA LOS SELECTS DEL FILTRO ---
$centros = $pdo->query("SELECT * FROM Centros_medicos ORDER BY nombre_cm")->fetchAll(PDO::FETCH_ASSOC);
$regiones = $pdo->query("SELECT DISTINCT region FROM Centros_medicos ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);
$especialidades = $pdo->query("SELECT * FROM Especialidades ORDER BY tipo")->fetchAll(PDO::FETCH_ASSOC);
$medicos = $pdo->query("SELECT * FROM Medicos ORDER BY Nombre_med")->fetchAll(PDO::FETCH_ASSOC);
$estados = $pdo->query("SELECT * FROM Estado")->fetchAll(PDO::FETCH_ASSOC);
$previsiones = $pdo->query("SELECT * FROM Prevision_de_salud")->fetchAll(PDO::FETCH_ASSOC);
$pacientes = $pdo->query("SELECT * FROM Pacientes ORDER BY Nombre_pac")->fetchAll(PDO::FETCH_ASSOC);

// --- CONSTRUIR LA CONSULTA DINÁMICA ---
$sql = "SELECT c.id_cita, c.fecha_y_hora, p.Nombre_pac, m.Nombre_med, e.tipo AS especialidad, 
               cm.nombre_cm, cm.region, est.tipo_estado, prev.Nombre_prevision
        FROM Citas c
        JOIN Pacientes p ON c.rut_pac = p.rut_pac
        JOIN Medicos m ON c.rut_med = m.rut_med
        JOIN Especialidades e ON c.id_espec = e.id_espec
        JOIN Centros_medicos cm ON c.id_cm = cm.id_cm
        JOIN Estado est ON c.id_estado = est.id_estado
        JOIN Prevision_de_salud prev ON p.id_prevision = prev.id_prevision
        WHERE 1=1 "; 

$condiciones = [];
$parametros = [];

if ($rol === 'Paciente') { $condiciones[] = "c.rut_pac = ?"; $parametros[] = $rut; } 
elseif ($rol === 'Medico') { $condiciones[] = "c.rut_med = ?"; $parametros[] = $rut; }

if (!empty($_GET['fecha_inicio']) && !empty($_GET['fecha_fin'])) { $condiciones[] = "DATE(c.fecha_y_hora) BETWEEN ? AND ?"; $parametros[] = $_GET['fecha_inicio']; $parametros[] = $_GET['fecha_fin']; }
if (!empty($_GET['id_cm'])) { $condiciones[] = "c.id_cm = ?"; $parametros[] = $_GET['id_cm']; }
if (!empty($_GET['region'])) { $condiciones[] = "cm.region = ?"; $parametros[] = $_GET['region']; }
if (!empty($_GET['id_espec'])) { $condiciones[] = "c.id_espec = ?"; $parametros[] = $_GET['id_espec']; }
if (!empty($_GET['rut_med']) && $rol !== 'Medico') { $condiciones[] = "c.rut_med = ?"; $parametros[] = $_GET['rut_med']; }
if (!empty($_GET['rut_pac']) && $rol !== 'Paciente') { $condiciones[] = "c.rut_pac = ?"; $parametros[] = $_GET['rut_pac']; }
if (!empty($_GET['id_estado'])) { $condiciones[] = "c.id_estado = ?"; $parametros[] = $_GET['id_estado']; }
if (!empty($_GET['id_prevision']) && $rol !== 'Paciente') { $condiciones[] = "p.id_prevision = ?"; $parametros[] = $_GET['id_prevision']; }

if (count($condiciones) > 0) { $sql .= " AND " . implode(" AND ", $condiciones); }
$sql .= " ORDER BY c.fecha_y_hora ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Citas - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 1100px; margin: auto; background: white; margin-top: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; align-items: end;}
        .filtros label { font-size: 0.9em; font-weight: bold; }
        .filtros input, .filtros select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn-filtrar { background-color: #004d99; color: white; padding: 10px; border: none; cursor: pointer; width: 100%; font-weight: bold; border-radius: 4px; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #004d99; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .estado { padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold; color: white; }
        .estado-reservada { background-color: #f0ad4e; }
        .estado-realizada { background-color: #5cb85c; }
        .estado-cancelada { background-color: #d9534f; }
        .btn-accion { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 0.8em; font-weight: bold; }
        .btn-cancelar { background-color: #d9534f; }
        .btn-reprogramar { background-color: #f0ad4e; margin-bottom: 5px;}
        .alert-error { color: #d9534f; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>Buscador de Citas Médicas</h2>
        <?php if (!empty($error)): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if (!empty($mensaje)): ?><div class="alert-success"><?php echo $mensaje; ?></div><?php endif; ?>

        <!-- FORMULARIO DE FILTROS -->
        <form class="filtros" method="GET" action="">
            <div><label>Fecha Inicio:</label><input type="date" name="fecha_inicio" value="<?php echo $_GET['fecha_inicio'] ?? ''; ?>"></div>
            <div><label>Fecha Fin:</label><input type="date" name="fecha_fin" value="<?php echo $_GET['fecha_fin'] ?? ''; ?>"></div>
            
            <?php if ($rol !== 'Paciente'): ?>
            <div>
                <label>Paciente:</label>
                <select name="rut_pac">
                    <option value="">Todos</option>
                    <?php foreach($pacientes as $pac): ?>
                        <option value="<?php echo $pac['rut_pac']; ?>" <?php echo (isset($_GET['rut_pac']) && $_GET['rut_pac'] == $pac['rut_pac']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pac['Nombre_pac']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label>Centro Médico:</label>
                <select name="id_cm">
                    <option value="">Todos</option>
                    <?php foreach($centros as $c): ?>
                        <option value="<?php echo $c['id_cm']; ?>" <?php echo (isset($_GET['id_cm']) && $_GET['id_cm'] == $c['id_cm']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nombre_cm']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($rol !== 'Medico'): ?>
            <div>
                <label>Médico:</label>
                <select name="rut_med">
                    <option value="">Todos</option>
                    <?php foreach($medicos as $m): ?>
                        <option value="<?php echo $m['rut_med']; ?>" <?php echo (isset($_GET['rut_med']) && $_GET['rut_med'] == $m['rut_med']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['Nombre_med']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label>Estado:</label>
                <select name="id_estado">
                    <option value="">Todos</option>
                    <?php foreach($estados as $est): ?>
                        <option value="<?php echo $est['id_estado']; ?>" <?php echo (isset($_GET['id_estado']) && $_GET['id_estado'] == $est['id_estado']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($est['tipo_estado']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div><button type="submit" class="btn-filtrar">Aplicar Filtros</button></div>
            <div><a href="citas.php" style="display:block; text-align:center; padding: 8px; margin-top:5px; color:#d9534f; text-decoration:none; border: 1px solid #d9534f; border-radius: 4px;">Limpiar</a></div>
        </form>

        <!-- TABLA DE RESULTADOS -->
        <table>
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Paciente (Previsión)</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Centro Médico</th>
                    <th>Estado</th>
                    <?php if ($rol === 'Paciente' || $rol === 'Medico') echo "<th>Acciones</th>"; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($resultados) > 0): ?>
                    <?php foreach ($resultados as $row): 
                        $estado_lower = strtolower($row['tipo_estado']);
                        $clase_estado = 'estado ';
                        if ($estado_lower == 'reservada' || $estado_lower == 'confirmada') $clase_estado .= 'estado-reservada';
                        elseif ($estado_lower == 'realizada' || $estado_lower == 'atendida') $clase_estado .= 'estado-realizada';
                        else $clase_estado .= 'estado-cancelada';
                        
                        $es_futura = strtotime($row['fecha_y_hora']) > time();
                        $es_modificable = ($estado_lower == 'reservada' || $estado_lower == 'confirmada');
                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y H:i', strtotime($row['fecha_y_hora'])); ?></td>
                            <td><?php echo htmlspecialchars($row['Nombre_pac']) . " <br><small><i>(" . htmlspecialchars($row['Nombre_prevision']) . ")</i></small>"; ?></td>
                            <td><?php echo htmlspecialchars($row['Nombre_med']); ?></td>
                            <td><?php echo htmlspecialchars($row['especialidad']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_cm']); ?></td>
                            <td><span class="<?php echo $clase_estado; ?>"><?php echo htmlspecialchars($row['tipo_estado']); ?></span></td>
                            
                            <!-- BOTONES PACIENTE -->
                            <?php if ($rol === 'Paciente'): ?>
                            <td>
                                <?php if ($es_futura && $es_modificable): ?>
                                    <a href="reprogramar.php?id_cita=<?php echo $row['id_cita']; ?>" class="btn-accion btn-reprogramar" style="display:inline-block; text-decoration:none; text-align:center;">Reprogramar</a><br>
                                    <form method="POST" onsubmit="return confirm('¿Seguro que deseas cancelar esta cita?');" style="display:inline-block;">
                                        <input type="hidden" name="id_cita" value="<?php echo $row['id_cita']; ?>">
                                        <button type="submit" name="cancelar_cita" class="btn-accion btn-cancelar">Cancelar Cita</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 0.8em; color: gray;">No modificable</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <!-- SELECTOR MÉDICO -->
                            <?php if ($rol === 'Medico'): ?>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="id_cita_medico" value="<?php echo $row['id_cita']; ?>">
                                    <select name="nuevo_estado" onchange="this.form.submit()" style="padding: 5px; font-size: 0.85em;">
                                        <option value="">Cambiar a...</option>
                                        <option value="Confirmada">Confirmada</option>
                                        <option value="Atendida">Atendida</option>
                                        <option value="No Asistió">No Asistió</option>
                                    </select>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?php echo ($rol === 'Administrador') ? '6' : '7'; ?>" style="text-align:center;">No se encontraron citas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>