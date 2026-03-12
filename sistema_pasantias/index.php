<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPIC V2.0 - Sistema de Gestion de Pasantias</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/css/index.css">
</head>
<body>
    <div class="welcome-card">
        <div class="logo-circle">
            <div class="logo-image"></div>
        </div>

        <h1 class="title">Bienvenido al sistema EPIC</h1>
        <p class="subtitle">Sistema de Gestion de Pasantias</p>
        <div class="divider"></div>
        <p class="institution">Politecnico Juan Pablo II - Fe y Alegria</p>

        <div class="btn-group">
            <a href="public/?page=login" class="btn btn-primary">
                <i class="fas fa-right-to-bracket"></i>
                Iniciar sesion
            </a>

            <a href="public/?page=register" class="btn btn-secondary">
                <i class="fas fa-user-plus"></i>
                Registrarse
            </a>

            <a href="public/?page=center-register" class="btn btn-secondary">
                <i class="fas fa-building"></i>
                Registrar centro
            </a>
        </div>

        <div class="footer">
            <div class="system-name">EPIC V2.0</div>
            <p>Sistema Integral de Gestion de Pasantias</p>
            <p>&copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>
