<?php
// app/views/student/companies.php

$exam_modal = $exam_modal ?? null;
$foto_perfil = $estudiante['foto_perfil'] ?? ($foto_perfil ?? '');
$avatarName = trim((string) ($estudiante['nombre'] ?? 'U'));
if ($avatarName === '') {
    $avatarName = 'U';
}

$avatarInitial = function_exists('mb_substr')
    ? mb_substr($avatarName, 0, 1, 'UTF-8')
    : substr($avatarName, 0, 1);
$avatarInitial = function_exists('mb_strtoupper')
    ? mb_strtoupper($avatarInitial, 'UTF-8')
    : strtoupper($avatarInitial);
$avatarPath = ltrim(str_replace('\\', '/', (string) $foto_perfil), '/');
$hasProfilePhoto = $avatarPath !== '' && file_exists(PUBLIC_PATH . $avatarPath);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresas Disponibles - Sistema EPIC</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/companies.css">
</head>
<body<?php echo $exam_modal ? ' class="modal-open"' : ''; ?>>
    <div class="app-container">
        <!-- ============================================
             SIDEBAR - IDÉNTICO AL DASHBOARD
        ============================================ -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="system-logo-top">
                    <img src="../EPIC.png" alt="Sistema EPIC" class="logo-horizontal">
                </div>
                <div class="logo-circle-container">
                    <div class="logo-circle">
                        <img src="../nojodas.png.jpeg" alt="Sistema EPIC">
                    </div>
                    <h2 class="sidebar-title">Sistema EPIC</h2>
                    <p class="sidebar-subtitle">Sistema de Gestión de Pasantías</p>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-title">Navegación</div>
                <a href="index.php?page=student-dashboard" class="nav-item">
                    <i class="fas fa-home nav-icon"></i>
                    Inicio
                </a>
                <a href="index.php?page=student-companies" class="nav-item active">
                    <i class="fas fa-building nav-icon"></i>
                    Empresas Disponibles
                </a>
                <a href="index.php?page=student-results" class="nav-item">
                    <i class="fas fa-clipboard-check nav-icon"></i>
                    Mis Evaluaciones
                </a>
                <a href="index.php?page=student-profile" class="nav-item">
                    <i class="fas fa-user nav-icon"></i>
                    Mi Perfil
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="index.php?page=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar Sesión
                </a>
            </div>
        </aside>

        <!-- ============================================
             MAIN CONTENT
        ============================================ -->
        <main class="main-content">
           <!-- TOP BAR CON PERFIL FUNCIONAL - MISMA FOTO QUE DASHBOARD -->
<div class="top-bar">
    <div class="user-profile-top">
        <!-- ✅ MISMA FOTO DEL ESTUDIANTE -->
        <div class="user-avatar<?php echo $hasProfilePhoto ? '' : ' avatar-fallback'; ?>" onclick="toggleProfileDropdown()">
            <?php if ($hasProfilePhoto): ?>
            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre'] ?? 'Usuario'); ?>">
            <?php else: ?>
            <span class="avatar-initial"><?php echo htmlspecialchars($avatarInitial); ?></span>
            <?php endif; ?>
        </div>

        <!-- DROPDOWN DEL PERFIL -->
        <div class="profile-dropdown" id="profileDropdown">
            <div class="dropdown-header">
                <div class="dropdown-user">
                    <!-- ✅ MISMA FOTO CLONADA -->
                    <div class="dropdown-avatar-container<?php echo $hasProfilePhoto ? '' : ' avatar-fallback'; ?>">
                        <?php if ($hasProfilePhoto): ?>
                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre'] ?? 'Usuario'); ?>">
                        <?php else: ?>
                        <span class="avatar-initial"><?php echo htmlspecialchars($avatarInitial); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-user-info">
                        <h4><?php echo htmlspecialchars($estudiante['nombre'] ?? ''); ?></h4>
                        <p><?php echo htmlspecialchars($estudiante['area_tecnica'] ?? ''); ?></p>
                    </div>
                </div>
                
                <div class="dropdown-user-email">
                    <?php echo htmlspecialchars($estudiante['correo'] ?? ''); ?>
                </div>
            </div>
            
            <div class="dropdown-menu">
                <a href="index.php?page=student-profile" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    Mi perfil
                </a>
                <a href="index.php?page=student-profile&tab=cv" class="dropdown-item">
                    <i class="fas fa-file-pdf"></i>
                    Mi currículum
                </a>
                <a href="index.php?page=student-results" class="dropdown-item">
                    <i class="fas fa-chart-bar"></i>
                    Mis evaluaciones
                </a>
                
                <div class="dropdown-divider"></div>
                
                <a href="index.php?page=logout" class="dropdown-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</div>

            <!-- CONTENIDO -->
            <div class="content-wrapper">
                <div class="page-header">
                    <h1 class="page-title">Empresas Disponibles</h1>
                    <p class="page-description">
                        <?php echo count($empresas); ?> empresas disponibles en tu área de <strong><?php echo htmlspecialchars($estudiante['area_tecnica'] ?? ''); ?></strong>
                    </p>
                </div>

                <!-- ALERTAS -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?>">
                    <i class="fas fa-info-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['flash_message']); ?></strong>
                    </div>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

                <?php if (!$tiene_cv): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-file-circle-xmark" style="font-size: 24px;"></i>
                    <div>
                        <strong>Puedes ver las empresas, pero no postularte todavía</strong>
                        <p>Sube tu currículum desde el dashboard para habilitar la postulación.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($tiene_evaluacion_pendiente): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-clock" style="font-size: 24px;"></i>
                    <div>
                        <strong>Tienes una evaluación pendiente</strong>
                        <p>Completa tu evaluación actual antes de postularte a otra empresa.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($tiene_asignacion): ?>
                <div class="alert alert-info">
                    <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong>Ya estás asignado a una empresa</strong>
                        <p>No puedes postularte a más empresas mientras tengas una pasantía activa.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- GRID DE EMPRESAS -->
                <div class="empresas-grid">
                    <?php if (empty($empresas)): ?>
                    <div class="no-empresas">
                        <i class="fas fa-building"></i>
                        <h3>No hay empresas disponibles</h3>
                        <p>Por el momento no hay empresas disponibles en tu área técnica.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($empresas as $empresa): ?>
                        <div class="empresa-card">
                            <?php $evaluacionEmpresa = $evaluaciones_por_empresa[(int) $empresa['id']] ?? null; ?>
                            <div class="empresa-header">
                                <div class="empresa-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div>
                                    <h3 class="empresa-nombre"><?php echo htmlspecialchars($empresa['nombre']); ?></h3>
                                    <span class="empresa-area"><?php echo htmlspecialchars($empresa['area_tecnica']); ?></span>
                                </div>
                            </div>
                            
                            <p class="empresa-descripcion">
                                <?php echo htmlspecialchars($empresa['descripcion'] ?? 'Sin descripción disponible'); ?>
                            </p>
                            
                            <div class="empresa-detalles">
                                <?php if (!empty($empresa['direccion'])): ?>
                                <div class="detalle-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($empresa['direccion']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cupos-info">
                                <span class="cupos-badge <?php echo ($empresa['cupos_disponibles'] ?? 0) <= 2 ? 'cupos-badge-warning' : ''; ?>">
                                    <i class="fas fa-users"></i>
                                    <?php echo $empresa['cupos_disponibles'] ?? 0; ?> cupos disponibles
                                </span>
                                <span style="color: var(--dark); opacity: 0.7; font-size: 14px;">
                                    Total: <?php echo $empresa['cupos']; ?> cupos
                                </span>
                            </div>

                            <?php if (!$tiene_cv): ?>
                                <button class="btn-postular" disabled>
                                    <i class="fas fa-file-upload"></i> Sube tu CV para postularte
                                </button>
                            <?php elseif (!empty($evaluacionEmpresa) && ($evaluacionEmpresa['estado'] ?? '') === 'pendiente'): ?>
                                <a href="index.php?page=student-companies&exam=<?php echo (int) $evaluacionEmpresa['id']; ?>" class="btn-postular">
                                    <i class="fas fa-play-circle"></i> Continuar examen
                                </a>
                            <?php elseif (!empty($evaluacionEmpresa)): ?>
                                <button class="btn-postular" disabled>
                                    <i class="fas fa-circle-check"></i>
                                    <?php echo ucfirst(htmlspecialchars($evaluacionEmpresa['estado'])); ?>
                                </button>
                            <?php elseif ($tiene_evaluacion_pendiente || $tiene_asignacion): ?>
                                <button class="btn-postular" disabled>
                                    <i class="fas fa-ban"></i> No disponible
                                </button>
                            <?php else: ?>
                                <form action="index.php?page=student-apply&action=process" method="POST">
                                    <input type="hidden" name="empresa_id" value="<?php echo $empresa['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button type="submit" class="btn-postular">
                                        <i class="fas fa-paper-plane"></i> Postularse
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- FOOTER -->
                <footer class="footer">
                    <div class="version">EPIC V2.0</div>
                    <p class="copyright">Sistema Integral de Gestión de Pasantías</p>
                    <p class="copyright">&copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
                </footer>
            </div>
        </main>
    </div>

    <?php if ($exam_modal): ?>
        <?php require APP_PATH . 'views/student/partials/company-exam-modal.php'; ?>
    <?php endif; ?>

    <!-- SCRIPTS -->
    <script>
        // ============================================
        // DROPDOWN DEL PERFIL - FUNCIONAL
        // ============================================
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            if (!dropdown) {
                return;
            }

            dropdown.classList.toggle('active');
        }

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.querySelector('.user-avatar');

            if (!dropdown || !avatar) {
                return;
            }

            if (dropdown.classList.contains('active') && 
                !dropdown.contains(event.target) && 
                !avatar.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Toggle sidebar en móvil
        if (window.innerWidth <= 1200) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.createElement('button');
            menuToggle.className = 'menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.style.cssText = `
                position: fixed;
                top: 25px;
                left: 25px;
                z-index: 1001;
                background: var(--primary);
                color: white;
                border: none;
                width: 50px;
                height: 50px;
                border-radius: 10px;
                cursor: pointer;
                font-size: 22px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            `;
            menuToggle.onclick = function() {
                sidebar.classList.toggle('active');
                sidebar.style.transform = sidebar.classList.contains('active') ? 'translateX(0)' : 'translateX(-100%)';
            };
            document.body.appendChild(menuToggle);
        }
    </script>
</body>
</html>
