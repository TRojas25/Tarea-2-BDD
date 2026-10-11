<?php
session_start();
date_default_timezone_set('America/Santiago');

if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Medico') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$id_cita = $_GET['id_cita'] ?? null;
if (!$id_cita) {
    die("ID de cita no proporcionado.");
}

// VALIDACIÓN: La cita debe ser del médico y estar "Atendida"
$sql_cita = "SELECT c.*, p.Nombre_pac, p.rut_pac, est.tipo_estado 
             FROM Citas c
             JOIN Pacientes p ON c.rut_pac = p.rut_pac
             JOIN Estado est ON c.id_estado = est.id_estado
             WHERE c.id_cita = ? AND c.rut_med = ?";
$stmt = $pdo->prepare($sql_cita);
$stmt->execute([$id_cita, $_SESSION['rut']]);
$cita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
    die("Cita no encontrada o no tienes permisos sobre ella.");
}
if (strtolower($cita['tipo_estado']) !== 'atendida') {
    die("Error: Solo se puede registrar o modificar una atención si la cita está en estado 'Atendida'. Actualmente está en estado: " . $cita['tipo_estado']);
}

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];

    try {
        // Guardar o Actualizar la Atención (Motivo y Observaciones)
        if ($accion === 'guardar_atencion') {
            $motivo = trim($_POST['motivo']);
            $observaciones = trim($_POST['observaciones']);
            
            $stmt_check = $pdo->prepare("SELECT id_atencion FROM Atenciones WHERE id_cita = ?");
            $stmt_check->execute([$id_cita]);
            $atencion_existente = $stmt_check->fetchColumn();

            if ($atencion_existente) {
                $pdo->prepare("UPDATE Atenciones SET motivo = ?, observaciones = ? WHERE id_atencion = ?")->execute([$motivo, $observaciones, $atencion_existente]);
                $mensaje = "Atención clínica actualizada correctamente.";
            } else {
                $pdo->prepare("INSERT INTO Atenciones (id_cita, motivo, observaciones) VALUES (?, ?, ?)")->execute([$id_cita, $motivo, $observaciones]);
                $mensaje = "Atención clínica registrada. Ahora puedes agregar diagnósticos y recetas.";
            }
        } 
        // Lógica de Diagnósticos
        elseif ($accion === 'agregar_diag') {
            $id_atencion = $_POST['id_atencion'];
            $id_diag = $_POST['id_diag'];
            
            $check = $pdo->prepare("SELECT COUNT(*) FROM Diagnosticos_atenciones WHERE id_atencion = ? AND id_diag = ?");
            $check->execute([$id_atencion, $id_diag]);
            if ($check->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO Diagnosticos_atenciones (id_atencion, id_diag) VALUES (?, ?)")->execute([$id_atencion, $id_diag]);
                $mensaje = "Diagnóstico agregado a la atención.";
            } else {
                $error = "Este diagnóstico ya fue agregado previamente.";
            }
        }
        elseif ($accion === 'editar_diag') {
            $id_atencion = $_POST['id_atencion'];
            $id_diag_old = $_POST['id_diag_old'];
            $id_diag_new = $_POST['id_diag'];

            if ($id_diag_old === $id_diag_new) {
                $mensaje = "No se realizaron cambios en el diagnóstico.";
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM Diagnosticos_atenciones WHERE id_atencion = ? AND id_diag = ?");
                $check->execute([$id_atencion, $id_diag_new]);
                if ($check->fetchColumn() == 0) {
                    $pdo->prepare("DELETE FROM Diagnosticos_atenciones WHERE id_atencion = ? AND id_diag = ?")->execute([$id_atencion, $id_diag_old]);
                    $pdo->prepare("INSERT INTO Diagnosticos_atenciones (id_atencion, id_diag) VALUES (?, ?)")->execute([$id_atencion, $id_diag_new]);
                    $mensaje = "Diagnóstico actualizado correctamente.";
                } else {
                    $error = "El nuevo diagnóstico seleccionado ya está en la lista de esta atención.";
                }
            }
        }
        elseif ($accion === 'eliminar_diag') {
            $pdo->prepare("DELETE FROM Diagnosticos_atenciones WHERE id_atencion = ? AND id_diag = ?")->execute([$_POST['id_atencion'], $_POST['id_diag']]);
            $mensaje = "Diagnóstico removido.";
        }
        // Lógica de Recetas
        elseif ($accion === 'agregar_receta') {
            $id_atencion = $_POST['id_atencion'];
            $pdo->prepare("INSERT INTO Recetas (id_atencion, medicamento, dosis, dias_tratamiento) VALUES (?, ?, ?, ?)")
                ->execute([$id_atencion, trim($_POST['medicamento']), trim($_POST['dosis']), $_POST['dias']]);
            $mensaje = "Línea de receta agregada.";
        }
        elseif ($accion === 'editar_receta') {
            $pdo->prepare("UPDATE Recetas SET medicamento = ?, dosis = ?, dias_tratamiento = ? WHERE id_recetas = ?")
                ->execute([trim($_POST['medicamento']), trim($_POST['dosis']), $_POST['dias'], $_POST['id_recetas']]);
            $mensaje = "Línea de receta actualizada.";
        }
        elseif ($accion === 'eliminar_receta') {
            $pdo->prepare("DELETE FROM Recetas WHERE id_recetas = ?")->execute([$_POST['id_recetas']]);
            $mensaje = "Línea de receta eliminada.";
        }
    } catch (Exception $e) {
        $error = "Error en la operación: " . $e->getMessage();
    }
}

// CARGAR DATOS ACTUALES DE LA ATENCIÓN 
$stmt_at = $pdo->prepare("SELECT * FROM Atenciones WHERE id_cita = ?");
$stmt_at->execute([$id_cita]);
$atencion = $stmt_at->fetch(PDO::FETCH_ASSOC);

$diagnosticos_actuales = [];
$recetas_actuales = [];
if ($atencion) {
    $stmt_da = $pdo->prepare("SELECT da.id_diag, d.descripcion FROM Diagnosticos_atenciones da JOIN Diagnosticos d ON da.id_diag = d.id_diag WHERE da.id_atencion = ?");
    $stmt_da->execute([$atencion['id_atencion']]);
    $diagnosticos_actuales = $stmt_da->fetchAll(PDO::FETCH_ASSOC);

    $stmt_re = $pdo->prepare("SELECT * FROM Recetas WHERE id_atencion = ?");
    $stmt_re->execute([$atencion['id_atencion']]);
    $recetas_actuales = $stmt_re->fetchAll(PDO::FETCH_ASSOC);
}
$catalogo_diag = $pdo->query("SELECT * FROM Diagnosticos ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);

// EDICION
$edit_diag = $_GET['editar_diag'] ?? null; // Trae el id_diag antiguo
$edit_receta = null;
if (isset($_GET['editar_receta'])) {
    $stmt_ed_rec = $pdo->prepare("SELECT * FROM Recetas WHERE id_recetas = ?");
    $stmt_ed_rec->execute([$_GET['editar_receta']]);
    $edit_receta = $stmt_ed_rec->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Atención - SaludUSM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f4f9; }
        .container { padding: 20px; max-width: 800px; margin: auto; background: white; margin-top: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .box { border: 1px solid #ccc; padding: 15px; border-radius: 5px; margin-bottom: 20px; background: #fafafa; }
        input, textarea, select { width: 100%; padding: 8px; margin: 5px 0 15px 0; box-sizing: border-box; }
        button { background-color: #004d99; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .btn-danger { background-color: #d9534f; padding: 5px 10px; font-size: 0.85em; border:none; color:white; cursor:pointer; border-radius:3px; }
        .btn-edit { background-color: #f0ad4e; padding: 5px 10px; font-size: 0.85em; margin-right: 5px; color:white; text-decoration:none; border-radius:3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; background: white;}
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #e9ecef; }
        .alert-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .cancel-link { background: #6c757d; color: white; padding: 10px; text-decoration: none; border-radius: 4px; font-size: 0.9em; font-weight: bold;}
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>Ficha de Atención Clínica</h2>
        <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cita['Nombre_pac']); ?> | <strong>Cita:</strong> <?php echo date('d-m-Y H:i', strtotime($cita['fecha_y_hora'])); ?></p>
        
        <?php if($mensaje): ?><div class="alert-success"><?php echo $mensaje; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>

        <!-- FORMULARIO PRINCIPAL DE ATENCIÓN -->
        <div class="box">
            <h3>1. Datos de la Consulta</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_atencion">
                
                <label>Motivo de Consulta:</label>
                <textarea name="motivo" required><?php echo htmlspecialchars($atencion['motivo'] ?? ''); ?></textarea>
                
                <label>Observaciones Clínicas:</label>
                <textarea name="observaciones" required><?php echo htmlspecialchars($atencion['observaciones'] ?? ''); ?></textarea>
                
                <button type="submit"><?php echo $atencion ? 'Actualizar Atención' : 'Crear Atención para habilitar Diagnósticos'; ?></button>
            </form>
        </div>

        <?php if($atencion): ?>
            <!-- GESTIÓN DE DIAGNÓSTICOS -->
            <div class="box" id="caja_diagnosticos">
                <h3>2. Diagnósticos (CIE-10)</h3>
                <?php if (count($diagnosticos_actuales) > 0): ?>
                    <table>
                        <tr><th>Código</th><th>Descripción</th><th style="width: 140px;">Acción</th></tr>
                        <?php foreach($diagnosticos_actuales as $d): ?>
                            <tr <?php echo ($edit_diag == $d['id_diag']) ? 'style="background-color: #fff3cd;"' : ''; ?>>
                                <td><?php echo htmlspecialchars($d['id_diag']); ?></td>
                                <td><?php echo htmlspecialchars($d['descripcion']); ?></td>
                                <td style="display: flex;">
                                    <a href="atencion.php?id_cita=<?php echo $id_cita; ?>&editar_diag=<?php echo $d['id_diag']; ?>#caja_diagnosticos" class="btn-edit">Editar</a>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar diagnóstico?');">
                                        <input type="hidden" name="accion" value="eliminar_diag">
                                        <input type="hidden" name="id_atencion" value="<?php echo $atencion['id_atencion']; ?>">
                                        <input type="hidden" name="id_diag" value="<?php echo $d['id_diag']; ?>">
                                        <button type="submit" class="btn-danger">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p style="color:gray;">No hay diagnósticos registrados.</p>
                <?php endif; ?>

                <form method="POST" style="display:flex; gap:10px; align-items:flex-end;">
                    <input type="hidden" name="accion" value="<?php echo $edit_diag ? 'editar_diag' : 'agregar_diag'; ?>">
                    <input type="hidden" name="id_atencion" value="<?php echo $atencion['id_atencion']; ?>">
                    
                    <?php if($edit_diag): ?>
                        <input type="hidden" name="id_diag_old" value="<?php echo $edit_diag; ?>">
                    <?php endif; ?>

                    <div style="flex-grow:1;">
                        <select name="id_diag" required style="margin:0;">
                            <option value="">-- <?php echo $edit_diag ? 'Seleccione el nuevo diagnóstico' : 'Agregar nuevo diagnóstico'; ?> --</option>
                            <?php foreach($catalogo_diag as $cat): ?>
                                <option value="<?php echo $cat['id_diag']; ?>" <?php echo ($edit_diag == $cat['id_diag']) ? 'selected' : ''; ?>>
                                    [<?php echo $cat['id_diag']; ?>] <?php echo htmlspecialchars($cat['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" style="background-color:<?php echo $edit_diag ? '#f0ad4e' : '#28a745'; ?>; margin:0;">
                        <?php echo $edit_diag ? 'Guardar Cambios' : '+ Agregar'; ?>
                    </button>
                    <?php if($edit_diag): ?>
                        <a href="atencion.php?id_cita=<?php echo $id_cita; ?>#caja_diagnosticos" class="cancel-link">Cancelar</a>
                    <?php endif; ?>
                </form>
            </div>

            <!--  GESTIÓN DE RECETAS -->
            <div class="box" id="caja_recetas">
                <h3>3. Receta Médica</h3>
                <?php if (count($recetas_actuales) > 0): ?>
                    <table>
                        <tr><th>Medicamento</th><th>Dosis</th><th>Días</th><th style="width: 140px;">Acción</th></tr>
                        <?php foreach($recetas_actuales as $r): ?>
                            <tr <?php echo ($edit_receta && $edit_receta['id_recetas'] == $r['id_recetas']) ? 'style="background-color: #fff3cd;"' : ''; ?>>
                                <td><?php echo htmlspecialchars($r['medicamento']); ?></td>
                                <td><?php echo htmlspecialchars($r['dosis']); ?></td>
                                <td><?php echo htmlspecialchars($r['dias_tratamiento']); ?></td>
                                <td style="display: flex;">
                                    <a href="atencion.php?id_cita=<?php echo $id_cita; ?>&editar_receta=<?php echo $r['id_recetas']; ?>#caja_recetas" class="btn-edit">Editar</a>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar medicamento de la receta?');">
                                        <input type="hidden" name="accion" value="eliminar_receta">
                                        <input type="hidden" name="id_recetas" value="<?php echo $r['id_recetas']; ?>">
                                        <button type="submit" class="btn-danger">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p style="color:gray;">No hay medicamentos recetados.</p>
                <?php endif; ?>

                <form method="POST" style="display:flex; gap:10px; align-items:flex-end;">
                    <input type="hidden" name="accion" value="<?php echo $edit_receta ? 'editar_receta' : 'agregar_receta'; ?>">
                    <input type="hidden" name="id_atencion" value="<?php echo $atencion['id_atencion']; ?>">
                    
                    <?php if($edit_receta): ?>
                        <input type="hidden" name="id_recetas" value="<?php echo $edit_receta['id_recetas']; ?>">
                    <?php endif; ?>

                    <div style="flex:2;">
                        <input type="text" name="medicamento" placeholder="Medicamento" required style="margin:0;" value="<?php echo $edit_receta ? htmlspecialchars($edit_receta['medicamento']) : ''; ?>">
                    </div>
                    <div style="flex:2;">
                        <input type="text" name="dosis" placeholder="Dosis (Ej: 1 c/8 hrs)" required style="margin:0;" value="<?php echo $edit_receta ? htmlspecialchars($edit_receta['dosis']) : ''; ?>">
                    </div>
                    <div style="flex:1;">
                        <input type="number" name="dias" placeholder="Días" required style="margin:0;" value="<?php echo $edit_receta ? htmlspecialchars($edit_receta['dias_tratamiento']) : ''; ?>">
                    </div>
                    
                    <button type="submit" style="background-color:<?php echo $edit_receta ? '#f0ad4e' : '#28a745'; ?>; margin:0;">
                        <?php echo $edit_receta ? 'Guardar Cambios' : '+ Añadir'; ?>
                    </button>
                    <?php if($edit_receta): ?>
                        <a href="atencion.php?id_cita=<?php echo $id_cita; ?>#caja_recetas" class="cancel-link">Cancelar</a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <a href="citas.php" style="color: #004d99; text-decoration: none; font-weight: bold;">← Volver al Buscador de Citas</a>
        </div>
    </div>
</body>
</html>