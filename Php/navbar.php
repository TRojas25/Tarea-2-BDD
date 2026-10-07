<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<nav style="background: #004d99; padding: 15px; color: white;">
    <b style="font-size: 1.2em;">SaludUSM</b> | 
    <a href="dashboard.php" style="color: white; text-decoration: none; margin: 0 15px;">Inicio</a>
    <a href="buscar.php" style="color: white; text-decoration: none; margin: 0 15px;">🔍 Buscar Médicos</a>

    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador'): ?>
        <a href="admin.php" style="color: white; text-decoration: none; margin: 0 15px;">Panel de Gestión</a>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Medico'): ?>
        <a href="agenda.php" style="color: white; text-decoration: none; margin: 0 15px;">Mi Agenda</a>
        
    <?php elseif (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Paciente'): ?>
        <a href="reservar.php" style="color: white; text-decoration: none; margin: 0 15px;">Reservar Cita</a>
        <a href="mis_recetas.php" style="color: white; text-decoration: none; margin: 0 15px;">Mis Recetas</a>
        <a href="mis_citas.php" style="color: white; text-decoration: none; margin: 0 15px;">Mis Citas</a>
    <?php endif; ?>
    
    <span style="float: right;">
        Usuario: <?php echo $_SESSION['rut']; ?> | 
        <a href="logout.php" style="color: #ffcccc; text-decoration: none;">Cerrar Sesión</a>
    </span>
</nav>