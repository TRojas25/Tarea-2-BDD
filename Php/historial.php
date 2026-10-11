<?php
session_start();
if (!isset($_SESSION['rut'])) {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$rol = $_SESSION['rol'];
$rut_activo = $_SESSION['rut'];
$rut_paciente = null;
$atenciones = [];
$error_seguridad = "";

// Determinar de qué paciente mostraremos el historial y aplicar seguridad
if ($rol === 'Paciente') {
    $rut_paciente = $rut_activo;
} elseif ($rol === 'Medico' && !empty($_GET['rut_pac'])) {
    $rut_paciente = $_GET['rut_pac'];
    
    // Validar que el paciente consultado realmente sea de este médico
    $stmt_seguridad = $pdo->prepare("SELECT COUNT(*) FROM Citas WHERE rut_pac = ? AND rut_med = ?");
    $stmt_seguridad->execute([$rut_paciente, $rut_activo]);
    
    if ($stmt_seguridad->fetchColumn() == 0) {
        $error_seguridad = "Acceso denegado: No puedes consultar el historial de un paciente que no está en tu registro de atención.";
        $rut_paciente = null; 
    }
}

//  Obtener el historial clínico 
if ($rut_paciente) {
    // JOIN con Estado para traer SOLO citas Atendidas/Realizadas
    $sql_historial = "SELECT a.id_atencion, a.motivo, a.observaciones, c.id_cita, c.fecha_y_hora, 
                             e.tipo AS especialidad, m.Nombre_med, p.Nombre_pac, est.tipo_estado
                      FROM Atenciones a
                      JOIN Citas c ON a.id_cita = c.id_cita
                      JOIN Estado est ON c.id_estado = est.id_estado
                      JOIN Especialidades e ON c.id_espec = e.id_espec
                      JOIN Medicos m ON c.rut_med = m.rut_med
                      JOIN Pacientes p ON c.rut_pac = p.rut_pac
                      WHERE c.rut_pac = ? 
                      AND (est.tipo_estado LIKE '%Atendid%' OR est.tipo_estado LIKE '%Realiz%')
                      ORDER BY c.fecha_y_hora DESC, a.id_atencion DESC";
    $stmt_hist = $pdo->prepare($sql_historial);
    $stmt_hist->execute([$rut_paciente]);
    $resultados_brutos = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

    $atenciones_unicas = [];
    foreach ($resultados_brutos as $row) {
        $id_cita_actual = $row['id_cita'];
        
        // Si no hemos procesado esta cita, la agregamos
        if (!isset($atenciones_unicas[$id_cita_actual])) {
            $id_atencion = $row['id_atencion'];
            
            // Buscar Diagnósticos
            $stmt_d = $pdo->prepare("SELECT d.id_diag, d.descripcion FROM Diagnosticos_atenciones da JOIN Diagnosticos d ON da.id_diag = d.id_diag WHERE da.id_atencion = ?");
            $stmt_d->execute([$id_atencion]);
            $row['diagnosticos'] = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

            // Buscar Recetas
            $stmt_r = $pdo->prepare("SELECT * FROM Recetas WHERE id_atencion = ?");
            $stmt_r->execute([$id_atencion]);
            $row['recetas'] = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
            
            // Guardar en el array final
            $atenciones_unicas[$id_cita_actual] = $row;
        }
    }
    // Reindexar el array para usarlo en el HTML
    $atenciones = array_values($atenciones_unicas);
}

// Obtener SOLO los pacientes de ESTE médico
$pacientes_lista = [];
if ($rol === 'Medico') {
    // Hacemos un DISTINCT cruzando Pacientes con Citas para traer solo a los suyos
    $sql_mis_pacientes = "SELECT DISTINCT p.rut_pac, p.Nombre_pac 
                          FROM Pacientes p
                          JOIN Citas c ON p.rut_pac = c.rut_pac
                          WHERE c.rut_med = ?
                          ORDER BY p.Nombre_pac";
    $stmt_pacientes = $pdo->prepare($sql_mis_pacientes);
    $stmt_pacientes->execute([$rut_activo]);
    $pacientes_lista = $stmt_pacientes->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Historial Clínico - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 900px; margin: auto; background: white; margin-top: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .atencion-card { border: 1px solid #004d99; border-radius: 5px; margin-bottom: 20px; overflow: hidden; }
        .atencion-header { background: #004d99; color: white; padding: 10px 15px; display: flex; justify-content: space-between; font-weight: bold; }
        .atencion-body { padding: 15px; background: #fff; }
        .seccion-interna { background: #f9f9f9; padding: 10px; border: 1px solid #ddd; margin-top: 10px; border-radius: 4px; }
        ul { margin: 5px 0; padding-left: 20px; }
        select, button { padding: 10px; margin-right: 10px; font-size: 1em; }
        button { background-color: #004d99; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #f5c6cb;}
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>Historial Clínico</h2>

        <?php if (!empty($error_seguridad)): ?>
            <div class="alert-error"><strong>¡Atención!</strong> <?php echo $error_seguridad; ?></div>
        <?php endif; ?>

        <!-- Buscador exclusivo para el Médico -->
        <?php if ($rol === 'Medico'): ?>
            <form method="GET" style="margin-bottom: 20px; background: #e9ecef; padding: 15px; border-radius: 5px;">
                <label style="font-weight: bold;">Consultar historial de paciente:</label><br>
                <div style="display: flex; margin-top: 10px;">
                    <select name="rut_pac" required style="flex-grow: 1;">
                        <option value="">-- Seleccione a uno de sus pacientes --</option>
                        <?php foreach($pacientes_lista as $p): ?>
                            <option value="<?php echo $p['rut_pac']; ?>" <?php echo ((isset($_GET['rut_pac']) && $_GET['rut_pac'] == $p['rut_pac']) ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($p['Nombre_pac']) . " (RUT: " . $p['rut_pac'] . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Buscar Historial</button>
                </div>
            </form>
        <?php endif; ?>

        <!-- Despliegue del Historial -->
        <?php if ($rut_paciente): ?>
            <?php if (count($atenciones) > 0): ?>
                <h3 style="border-bottom: 2px solid #ccc; padding-bottom: 5px;">
                    Expediente: <?php echo htmlspecialchars($atenciones[0]['Nombre_pac']); ?>
                </h3>

                <?php foreach ($atenciones as $at): ?>
                    <div class="atencion-card">
                        <div class="atencion-header">
                            <span>📅 <?php echo date('d-m-Y H:i', strtotime($at['fecha_y_hora'])); ?></span>
                            <span>🩺 <?php echo htmlspecialchars($at['especialidad']); ?> - Dr(a). <?php echo htmlspecialchars($at['Nombre_med']); ?></span>
                        </div>
                        <div class="atencion-body">
                            <p><strong>Motivo:</strong> <?php echo nl2br(htmlspecialchars($at['motivo'])); ?></p>
                            <p><strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($at['observaciones'])); ?></p>
                            
                            <div class="seccion-interna">
                                <strong>Diagnósticos (CIE-10):</strong>
                                <?php if(count($at['diagnosticos']) > 0): ?>
                                    <ul>
                                        <?php foreach($at['diagnosticos'] as $d): ?>
                                            <li>[<?php echo htmlspecialchars($d['id_diag']); ?>] <?php echo htmlspecialchars($d['descripcion']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span style="color:gray; font-size:0.9em;">Ninguno registrado.</span>
                                <?php endif; ?>
                            </div>

                            <div class="seccion-interna">
                                <strong>Receta Médica:</strong>
                                <?php if(count($at['recetas']) > 0): ?>
                                    <ul>
                                        <?php foreach($at['recetas'] as $r): ?>
                                            <li><?php echo htmlspecialchars($r['medicamento']) . " | Dosis: " . htmlspecialchars($r['dosis']) . " | " . htmlspecialchars($r['dias_tratamiento']) . " días."; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span style="color:gray; font-size:0.9em;">Sin medicamentos recetados.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: gray;">Este paciente no tiene atenciones clínicas previas registradas en el sistema.</p>
            <?php endif; ?>
        <?php elseif ($rol === 'Medico' && empty($error_seguridad)): ?>
            <p style="text-align: center; color: gray;">Seleccione un paciente para ver su historial clínico.</p>
        <?php endif; ?>
    </div>
</body>
</html>