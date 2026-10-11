<?php
session_start();
if (!isset($_SESSION['rut']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: index.php");
    exit;
}
require 'conexion.php';

$mensaje = "";
$error = "";

// Cargar catálogos
$catalogo_espec = $pdo->query("SELECT * FROM Especialidades ORDER BY tipo")->fetchAll(PDO::FETCH_ASSOC);
$catalogo_centros = $pdo->query("SELECT * FROM Centros_medicos ORDER BY nombre_cm")->fetchAll(PDO::FETCH_ASSOC);

// PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];
    $rut_med = trim($_POST['rut_med']);
    
    if ($accion === 'crear' || $accion === 'editar') {
        $nombre_med = trim($_POST['nombre_med']);
        $email_med = trim($_POST['email']);
        $clave = trim($_POST['clave']); 
        $especialidades_nuevas = $_POST['especialidades'] ?? [];
        $centros_nuevos = $_POST['centros'] ?? [];
        
        if (count($especialidades_nuevas) < 1 || count($especialidades_nuevas) > 3) {
            $error = "El médico debe tener asignadas entre 1 y 3 especialidades.";
        }
        if (empty($error) && count($centros_nuevos) < 1) {
            $error = "El médico debe atender en al menos 1 centro médico.";
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                $stmt_rol = $pdo->query("SELECT id_rol FROM Roles WHERE nombre_rol LIKE '%Medic%' LIMIT 1");
                $id_rol_med = $stmt_rol->fetchColumn();

                if (!$id_rol_med) {
                    throw new Exception("Error: No se encontró el rol 'Medico' en la tabla Roles.");
                }

                if ($accion === 'crear') {
                    $sql_user = "INSERT INTO Usuarios (rut, clave, id_rol, activo) VALUES (?, ?, ?, 1)";
                    $pdo->prepare($sql_user)->execute([$rut_med, $clave, $id_rol_med]);

                    $sql_med = "INSERT INTO Medicos (rut_med, Nombre_med, Email) VALUES (?, ?, ?)";
                    $pdo->prepare($sql_med)->execute([$rut_med, $nombre_med, $email_med]);
                    
                    foreach ($especialidades_nuevas as $id_espec) {
                        $pdo->prepare("INSERT INTO Medico_especialidad (rut_med, id_espec) VALUES (?, ?)")->execute([$rut_med, $id_espec]);
                    }
                    foreach ($centros_nuevos as $id_cm) {
                        $pdo->prepare("INSERT INTO Medicos_centro (rut_med, id_cm) VALUES (?, ?)")->execute([$rut_med, $id_cm]);
                    }
                    $mensaje = "Médico y su cuenta de usuario registrados exitosamente.";

                } elseif ($accion === 'editar') {
                    $pdo->prepare("UPDATE Medicos SET Nombre_med = ?, Email = ? WHERE rut_med = ?")->execute([$nombre_med, $email_med, $rut_med]);

                    if (!empty($clave)) {
                        $pdo->prepare("UPDATE Usuarios SET clave = ? WHERE rut = ?")->execute([$clave, $rut_med]);
                    }

                    $espec_actuales = $pdo->prepare("SELECT id_espec FROM Medico_especialidad WHERE rut_med = ?");
                    $espec_actuales->execute([$rut_med]);
                    $espec_actuales = $espec_actuales->fetchAll(PDO::FETCH_COLUMN);
                    
                    $espec_a_borrar = array_diff($espec_actuales, $especialidades_nuevas);
                    $espec_a_agregar = array_diff($especialidades_nuevas, $espec_actuales);

                    foreach ($espec_a_borrar as $id_espec_borrar) {
                        $stmt_val = $pdo->prepare("SELECT COUNT(*) FROM Citas c JOIN Estado e ON c.id_estado = e.id_estado 
                                                   WHERE c.rut_med = ? AND c.id_espec = ? AND c.fecha_y_hora > NOW() 
                                                   AND e.tipo_estado IN ('Reservada', 'Confirmada')");
                        $stmt_val->execute([$rut_med, $id_espec_borrar]);
                        if ($stmt_val->fetchColumn() > 0) {
                            throw new Exception("No puedes quitar la especialidad seleccionada porque el médico tiene citas futuras agendadas con ella.");
                        }
                        $pdo->prepare("DELETE FROM Medico_especialidad WHERE rut_med = ? AND id_espec = ?")->execute([$rut_med, $id_espec_borrar]);
                    }
                    foreach ($espec_a_agregar as $id_espec_add) {
                        $pdo->prepare("INSERT INTO Medico_especialidad (rut_med, id_espec) VALUES (?, ?)")->execute([$rut_med, $id_espec_add]);
                    }

                    $centros_actuales = $pdo->prepare("SELECT id_cm FROM Medicos_centro WHERE rut_med = ?");
                    $centros_actuales->execute([$rut_med]);
                    $centros_actuales = $centros_actuales->fetchAll(PDO::FETCH_COLUMN);

                    $centros_a_borrar = array_diff($centros_actuales, $centros_nuevos);
                    $centros_a_agregar = array_diff($centros_nuevos, $centros_actuales);

                    foreach ($centros_a_borrar as $id_cm_borrar) {
                        $stmt_val = $pdo->prepare("SELECT COUNT(*) FROM Citas c JOIN Estado e ON c.id_estado = e.id_estado 
                                                   WHERE c.rut_med = ? AND c.id_cm = ? AND c.fecha_y_hora > NOW() 
                                                   AND e.tipo_estado IN ('Reservada', 'Confirmada')");
                        $stmt_val->execute([$rut_med, $id_cm_borrar]);
                        if ($stmt_val->fetchColumn() > 0) {
                            throw new Exception("No puedes quitar el centro médico seleccionado porque el médico tiene citas futuras agendadas en él.");
                        }
                        $pdo->prepare("DELETE FROM Medicos_centro WHERE rut_med = ? AND id_cm = ?")->execute([$rut_med, $id_cm_borrar]);
                    }
                    foreach ($centros_a_agregar as $id_cm_add) {
                        $pdo->prepare("INSERT INTO Medicos_centro (rut_med, id_cm) VALUES (?, ?)")->execute([$rut_med, $id_cm_add]);
                    }

                    $mensaje = "Perfil y asignaciones del médico actualizados correctamente.";
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al procesar: " . $e->getMessage();
            }
        }
    }
    elseif ($accion === 'eliminar') {
        try {
            $stmt_futuras = $pdo->prepare("SELECT COUNT(*) FROM Citas c JOIN Estado e ON c.id_estado = e.id_estado 
                                           WHERE c.rut_med = ? AND c.fecha_y_hora > NOW() 
                                           AND e.tipo_estado IN ('Reservada', 'Confirmada')");
            $stmt_futuras->execute([$rut_med]);
            
            if ($stmt_futuras->fetchColumn() > 0) {
                $error = "No puedes despedir a este médico porque tiene citas futuras pendientes. Debes reasignarlas o cancelarlas primero.";
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM Medicos WHERE rut_med = ?")->execute([$rut_med]);
                $pdo->prepare("DELETE FROM Usuarios WHERE rut = ?")->execute([$rut_med]);
                $pdo->commit();
                $mensaje = "El médico ha sido despedido y su cuenta eliminada exitosamente del sistema.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al intentar eliminar al médico: " . $e->getMessage();
        }
    }
}

$med_edit = null;
$mis_espec = [];
$mis_centros = [];
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM Medicos WHERE rut_med = ?");
    $stmt->execute([$_GET['editar']]);
    $med_edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($med_edit) {
        $stmt_e = $pdo->prepare("SELECT id_espec FROM Medico_especialidad WHERE rut_med = ?");
        $stmt_e->execute([$med_edit['rut_med']]);
        $mis_espec = $stmt_e->fetchAll(PDO::FETCH_COLUMN);

        $stmt_c = $pdo->prepare("SELECT id_cm FROM Medicos_centro WHERE rut_med = ?");
        $stmt_c->execute([$med_edit['rut_med']]);
        $mis_centros = $stmt_c->fetchAll(PDO::FETCH_COLUMN);
    }
}

$medicos_lista = $pdo->query("SELECT * FROM Medicos ORDER BY Nombre_med")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestionar Médicos</title>
    <style>
        body { font-family: Arial; background: #f4f4f9; margin: 0; }
        .container { padding: 20px; max-width: 1000px; margin: auto; background: white; margin-top: 20px; border-radius: 5px; }
        input[type="text"], input[type="email"], input[type="password"] { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        button { background-color: #004d99; color: white; border: none; padding: 10px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 15px; border-radius: 4px;}
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #004d99; color: white; }
        .grid-checkboxes { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 5px; background: white; padding: 10px; border: 1px solid #ccc;}
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .btn-edit { background-color: #f0ad4e; color: white; padding: 6px 10px; text-decoration: none; border-radius: 3px; font-size: 0.9em; font-weight: bold;}
        .btn-danger { background-color: #d9534f; color: white; border: none; padding: 6px 10px; border-radius: 3px; font-size: 0.9em; cursor: pointer; font-weight: bold; }
        .fila-inputs { display: flex; gap: 15px; }
        .col-input { flex: 1; }
        .buscador-input { padding: 10px !important; font-size: 1em; border-radius: 4px; border: 1px solid #aaa; margin-bottom: 10px !important; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>Gestión de Médicos y Asignaciones</h2>
        <?php if($mensaje) echo "<div class='alert success'>$mensaje</div>"; ?>
        <?php if($error) echo "<div class='alert error'><strong>Error:</strong> $error</div>"; ?>

        <div style="background: #e9ecef; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h3><?php echo $med_edit ? "Editar Perfil del Dr. " . htmlspecialchars($med_edit['Nombre_med']) : "Registrar Nuevo Médico"; ?></h3>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="<?php echo $med_edit ? 'editar' : 'crear'; ?>">
                
                <div class="fila-inputs">
                    <div class="col-input">
                        <label>RUT del Médico:</label>
                        <input type="text" name="rut_med" required placeholder="Ej: 12345678-9" value="<?php echo $med_edit['rut_med'] ?? ''; ?>" <?php echo $med_edit ? 'readonly style="background:#ccc;"' : ''; ?>>
                    </div>
                    <div class="col-input">
                        <label>Nombre Completo:</label>
                        <input type="text" name="nombre_med" required placeholder="Ej: Juan Pérez" value="<?php echo $med_edit['Nombre_med'] ?? ''; ?>">
                    </div>
                </div>

                <div class="fila-inputs">
                    <div class="col-input">
                        <label>Correo Electrónico (Email):</label>
                        <input type="email" name="email" required placeholder="medico@saludusm.cl" value="<?php echo $med_edit['Email'] ?? ''; ?>">
                    </div>
                    <div class="col-input">
                        <label>Contraseña <?php echo $med_edit ? '<small>(Dejar en blanco para no cambiarla)</small>' : ''; ?>:</label>
                        <input type="password" name="clave" placeholder="****" <?php echo $med_edit ? '' : 'required'; ?>>
                    </div>
                </div>
                
                <h4 style="margin-bottom: 5px; margin-top: 15px;">Especialidades (Mínimo 1, Máximo 3)</h4>
                <div class="grid-checkboxes">
                    <?php foreach($catalogo_espec as $esp): ?>
                        <label>
                            <input type="checkbox" name="especialidades[]" value="<?php echo $esp['id_espec']; ?>" 
                            <?php echo in_array($esp['id_espec'], $mis_espec) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($esp['tipo']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <h4 style="margin-bottom: 5px; margin-top: 15px;">Centros Médicos donde Atiende (Mínimo 1)</h4>
                <div class="grid-checkboxes">
                    <?php foreach($catalogo_centros as $cm): ?>
                        <label>
                            <input type="checkbox" name="centros[]" value="<?php echo $cm['id_cm']; ?>"
                            <?php echo in_array($cm['id_cm'], $mis_centros) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($cm['nombre_cm']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit"><?php echo $med_edit ? "Guardar Cambios" : "Crear Médico y Asignar"; ?></button>
                <?php if($med_edit): ?>
                    <a href="admin_medicos.php" style="display:block; text-align:center; color: #d9534f; margin-top:15px; text-decoration: none; font-weight: bold;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <h3>Directorio de Médicos</h3>
        <input type="text" id="buscadorMedico" class="buscador-input" placeholder="🔍 Buscar médico por nombre..." onkeyup="filtrarMedicos()">
        
        <table id="tablaMedicos">
            <tr>
                <th>RUT</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Acciones</th>
            </tr>
            <?php if (count($medicos_lista) > 0): ?>
                <?php foreach($medicos_lista as $m): ?>
                <tr class="fila-medico">
                    <td><?php echo htmlspecialchars($m['rut_med']); ?></td>
                    <td class="nombre-medico"><?php echo htmlspecialchars($m['Nombre_med']); ?></td>
                    <td><?php echo htmlspecialchars($m['Email']); ?></td>
                    <td>
                        <a href="admin_medicos.php?editar=<?php echo $m['rut_med']; ?>" class="btn-edit">Gestionar</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas DESPEDIR al médico <?php echo htmlspecialchars($m['Nombre_med']); ?>? Se borrará su acceso al sistema.');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="rut_med" value="<?php echo $m['rut_med']; ?>">
                            <button type="submit" class="btn-danger" style="margin-left: 5px;">Despedir</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align: center;">No hay médicos registrados.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <script>
    function filtrarMedicos() {
        var input = document.getElementById("buscadorMedico");
        var filter = input.value.toUpperCase();
        var table = document.getElementById("tablaMedicos");
        var tr = table.getElementsByClassName("fila-medico");

        for (var i = 0; i < tr.length; i++) {
            var tdNombre = tr[i].getElementsByClassName("nombre-medico")[0];
            if (tdNombre) {
                var txtValue = tdNombre.textContent || tdNombre.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
    </script>
</body>
</html>