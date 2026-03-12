<?php
// app/views/student/dashboard.php

// Verificar que es estudiante
if ($_SESSION['rol'] !== 'estudiante') {
    redirect('login', 'Acceso denegado', 'error');
}

// Obtener datos del estudiante
$estudiante = Database::selectOne("
    SELECT e.*, u.nombre, u.correo
    FROM estudiantes e
    JOIN usuarios u ON e.usuario_id = u.id
    WHERE u.id = ?
", [$_SESSION['user_id']]);

// Obtener foto de perfil del estudiante
$foto_perfil = $estudiante['foto_perfil'] ?? '';
$avatarInitial = function_exists('mb_substr')
    ? mb_substr(trim($estudiante['nombre']), 0, 1, 'UTF-8')
    : substr(trim($estudiante['nombre']), 0, 1);
$avatarInitial = function_exists('mb_strtoupper')
    ? mb_strtoupper($avatarInitial, 'UTF-8')
    : strtoupper($avatarInitial);
$avatarPath = ltrim(str_replace('\\', '/', $foto_perfil), '/');
$hasProfilePhoto = !empty($avatarPath) && file_exists(PUBLIC_PATH . $avatarPath);
$cvPath = !empty($cv_info['cv_path']) ? ltrim(str_replace('\\', '/', $cv_info['cv_path']), '/') : '';
$cvFullPath = !empty($cvPath) ? PUBLIC_PATH . $cvPath : '';
$cvFileName = !empty($cvPath) ? basename($cvPath) : '';
$cvUploadedAt = (!empty($cvFullPath) && file_exists($cvFullPath)) ? date('d/m/Y', filemtime($cvFullPath)) : null;
$canvaResumeUrl = 'https://www.canva.com/create/resumes/';

// Obtener conteo de empresas del área del estudiante
$empresas_count = Database::selectOne("
    SELECT COUNT(*) as total
    FROM empresas 
    WHERE centro_id = ? 
    AND area_tecnica = ? 
    AND estado = 'disponible'
", [$_SESSION['centro_id'], $estudiante['area_tecnica']]);

// Obtener estadísticas de evaluaciones
$evaluaciones_stats = Database::selectOne("
    SELECT 
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as en_progreso,
        SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN estado = 'reprobado' THEN 1 ELSE 0 END) as reprobadas
    FROM evaluaciones e
    JOIN estudiantes est ON e.estudiante_id = est.id
    WHERE est.usuario_id = ?
", [$_SESSION['user_id']]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema EPIC</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/studentdashboard.css">
   
</head>
<body>
    <div class="app-container">
        <!-- SIDEBAR - AZUL QUE LLEGA HASTA ARRIBA -->
        <aside class="sidebar">
            <!-- =========================================== -->
            <!-- HEADER DEL SIDEBAR CON LOGO - COMO LA IMAGEN -->
            <!-- =========================================== -->
            <div class="sidebar-header">
                <!-- Logo horizontal del sistema (arriba) -->
                <div class="system-logo-top">
                    <!-- Aquí va la imagen horizontal del logo del sistema -->
                    <img src="../EPIC.png" alt="Sistema EPIC" class="logo-horizontal">
                </div>
                <div class="ariel"></div>
                
                <!-- Logo redondo más abajo -->
                <div class="logo-circle-container">
                    <div class="logo-circle">
                        <img src="../nojodas.png.jpeg" alt="Sistema EPIC">
                    </div>
                    <h2 class="sidebar-title">Sistema EPIC</h2>
                    <p class="sidebar-subtitle">Sistema de Gestión de Pasantías</p>
                    
                </div>
            </div>

            <!-- Menú navegación -->
            <nav class="nav-menu">
                <div class="nav-title">Navegación</div>
                <a href="index.php?page=student-dashboard" class="nav-item active">
                    <i class="fas fa-home nav-icon"></i>
                    Inicio
                </a>
                <a href="index.php?page=student-companies" class="nav-item">
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

            <!-- BOTÓN CERRAR SESIÓN -->
            <div class="sidebar-footer">
                <a href="index.php?page=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar Sesión
                </a>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <!-- TOP BAR - SIN INPUT DE BÚSQUEDA -->
            <div class="top-bar">
<!-- =========================================== -->
<div class="user-profile-top">
<!-- UNA SOLA IMAGEN - ESTA ES LA ÚNICA VEZ QUE SE ESCRIBE -->
    <div class="user-avatar<?php echo $hasProfilePhoto ? '' : ' avatar-fallback'; ?>" onclick="toggleProfileDropdown()">
        <?php if ($hasProfilePhoto): ?>
        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre']); ?>">
        <?php else: ?>
        <span class="avatar-initial"><?php echo htmlspecialchars($avatarInitial); ?></span>
        <?php endif; ?>
    </div>

    <!-- Dropdown del perfil - AQUÍ NO SE ESCRIBE OTRA IMAGEN, SE REUTILIZA -->
    <div class="profile-dropdown" id="profileDropdown">
        <div class="dropdown-header">
            <div class="dropdown-user">
                <!-- ✅ En su lugar, ponemos la imagen usando el mismo elemento de arriba -->
                <div class="dropdown-avatar-container<?php echo $hasProfilePhoto ? '' : ' avatar-fallback'; ?>">
                    <?php if ($hasProfilePhoto): ?>
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre']); ?>">
                    <?php else: ?>
                    <span class="avatar-initial"><?php echo htmlspecialchars($avatarInitial); ?></span>
                    <?php endif; ?>
                </div>
                <div class="dropdown-user-info">
                    <h4><?php echo htmlspecialchars($estudiante['nombre']); ?></h4>
                    <p><?php echo htmlspecialchars($estudiante['area_tecnica']); ?></p>
                </div>
            </div>
                            <div class="dropdown-user-email">
                                <?php echo htmlspecialchars($estudiante['correo']); ?>
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
                            <a href="index.php?page=student-profile&tab=settings" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                Configuración
                            </a>
                            
                            <div class="dropdown-divider"></div>
                            
                            <a href="help.php" class="dropdown-item">
                                <i class="fas fa-question-circle"></i>
                                Ayuda
                            </a>
                            <a href="privacy.php" class="dropdown-item">
                                <i class="fas fa-shield-alt"></i>
                                Privacidad
                            </a>
                        </div>
                        
                        <div class="dropdown-footer">
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
                <!-- Bienvenida -->
                <div class="welcome-section">
                    <h1 class="welcome-title">¡Bienvenido de nuevo, <?php echo htmlspecialchars(explode(' ', $estudiante['nombre'])[0]); ?>!</h1>
                    <p class="welcome-text">Tu matricula es <?php echo htmlspecialchars($estudiante['matricula']); ?> y puedes gestionar tus pasantias desde aqui.</p>
                </div>

                <!-- GRID DE 4 TARJETAS -->
                <div class="cards-grid">
                    <!-- Tarjeta 1: Empresas Disponibles -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon icon-blue">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Empresas Disponibles</h3>
                                <p class="card-subtitle"><?php echo htmlspecialchars($empresas_count['total'] ?? 0); ?> empresas según tu área de <?php echo htmlspecialchars($estudiante['area_tecnica']); ?></p>
                            </div>
                        </div>
                        <div class="card-content">
                            <p class="card-description">Encuentra oportunidades de pasantías en empresas afines a tu especialidad técnica.</p>
                        </div>
                        <a href="index.php?page=student-companies" class="card-btn">
                            <i class="fas fa-search"></i> Ver Empleos
                        </a>
                    </div>
                     <!-- Tarjeta 2: Mi Currículum -->
<!-- Tarjeta 2: Mi Currículum -->
<div class="card cv-card">
    <div class="card-header">
        <div class="card-icon icon-green">
            <i class="fas fa-file-pdf"></i>
        </div>
        <div>
            <h3 class="card-title">Mi Curriculum</h3>
            <p class="card-subtitle"><?php echo $tiene_cv ? 'CV listo para tu proceso' : 'CV no subido'; ?></p>
        </div>
    </div>
    <div class="card-content">
        <?php if ($tiene_cv): ?>
        <div class="cv-state-line cv-state-success">
            Tu curriculum ya esta cargado y listo para postularte a empresas.
        </div>
        <div class="cv-info cv-info-panel">
            <div class="cv-file">
                <div class="cv-name">
                    <i class="fas fa-file-pdf"></i>
                    <?php echo htmlspecialchars($cvFileName); ?>
                </div>
                <span class="badge badge-green">Subido</span>
            </div>
            <div class="cv-date">
                <i class="fas fa-calendar-alt"></i>
                Actualizado el <?php echo htmlspecialchars($cvUploadedAt ?? date('d/m/Y')); ?>
            </div>
            <div class="cv-helper">
                Puedes abrirlo, reemplazarlo con una version nueva o eliminarlo si quieres subir otro desde cero.
            </div>
        </div>
        <?php else: ?>
        <div class="cv-state-line cv-state-warning">
            Asi, debes subir tu CV para postularte a empresas
        </div>
        <div class="cv-requirements">
            <h4>Requisitos del CV:</h4>
            <ul class="cv-requirements-list">
                <li>Formato PDF (maximo 5MB)</li>
                <li>Incluir foto profesional</li>
                <li>Destacar habilidades tecnicas</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-actions cv-card-actions">
        <?php if ($tiene_cv): ?>
        <a href="ver-cv.php" target="_blank" class="card-btn card-btn-secondary">
            <i class="fas fa-eye"></i> Ver CV
        </a>
        <?php endif; ?>
        <button
            type="button"
            class="card-btn btn-green<?php echo $tiene_cv ? '' : ' full-width'; ?>"
            id="uploadCVButton"
            onclick="document.getElementById('uploadCV').click()">
            <i class="fas fa-upload"></i>
            <?php echo $tiene_cv ? 'Actualizar CV' : 'Subir CV (Obligatorio)'; ?>
        </button>
        <?php if ($tiene_cv): ?>
        <button type="button" class="card-btn card-btn-danger full-width" id="deleteCVButton">
            <i class="fas fa-trash"></i> Eliminar CV
        </button>
        <?php endif; ?>
    </div>
    <input type="file" id="uploadCV" accept=".pdf" style="display: none;">
</div>

                    <!-- Tarjeta 3: Mis Evaluaciones - COMO LA IMAGEN -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon icon-orange">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Mis Evaluaciones</h3>
                                <p class="card-subtitle">Seguimiento de evaluaciones</p>
                            </div>
                        </div>
                        <div class="card-content">
                            <!-- Estadísticas como en la imagen -->
                            <div class="evaluation-stats">
                                <div class="stat-item">
                                    <div class="stat-number en-progreso"><?php echo $evaluaciones_stats['en_progreso'] ?? 0; ?></div>
                                    <div class="stat-label">En Progreso</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number aprobadas"><?php echo $evaluaciones_stats['aprobadas'] ?? 0; ?></div>
                                    <div class="stat-label">Aprobadas</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number reprobadas"><?php echo $evaluaciones_stats['reprobadas'] ?? 0; ?></div>
                                    <div class="stat-label">Reprobadas</div>
                                </div>
                            </div>
                        </div>
                        <a href="index.php?page=student-results" class="card-btn">
                            <i class="fas fa-eye"></i> Ver Evaluaciones
                        </a>
                    </div>

                    <!-- Tarjeta 4: Horas de Pasantías - MÁS PEQUEÑA -->
                    <div class="card builder-card">
                        <div class="card-header">
                            <div class="card-icon icon-purple">
                                <i class="fas fa-pen-ruler"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Disena tu Curriculum</h3>
                                <p class="card-subtitle">Crea un CV profesional gratis</p>
                            </div>
                        </div>
                        <div class="card-content">
                            <p class="builder-description">
                                Disena un curriculum impactante con plantillas profesionales de Canva. Rapido, facil y gratis.
                            </p>

                            <div class="builder-pill-list">
                                <span class="builder-pill">
                                    <i class="fas fa-palette"></i>
                                    Plantillas
                                </span>
                                <span class="builder-pill">
                                    <i class="fas fa-cloud-arrow-down"></i>
                                    Descarga PDF
                                </span>
                                <span class="builder-pill">
                                    <i class="fas fa-clock"></i>
                                    5 minutos
                                </span>
                            </div>
                        </div>
                        <a href="https://www.canva.com/s/templates?query=curriculum" target="_blank" rel="noopener noreferrer" class="card-btn builder-primary-btn">
                            <i class="fas fa-pen-ruler"></i> Crear en Canva
                        </a>
                    </div>
                </div>

                <!-- Footer -->
                <footer class="footer">
                    <div class="version">EPIC V2.0</div>
                    <p class="copyright">Sistema de Gestión de Pasantías</p>
                    <p class="copyright">&copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
                </footer>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script>
        // Función para subir CV
        const uploadCVInput = document.getElementById('uploadCV');
        const uploadCVButton = document.getElementById('uploadCVButton');
        const deleteCVButton = document.getElementById('deleteCVButton');
        uploadCVInput?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            return;
            if (false && file) {
                
                // Aquí deberías implementar la subida real al servidor
                alert('Simulando subida de CV... En producción se enviaría al servidor.');
            }
        });

        // Toggle sidebar en móvil
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // ===========================================
        // DROPDOWN DEL PERFIL
        // ===========================================
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.querySelector('.user-avatar');
            
            if (dropdown && dropdown.classList.contains('active') && 
                !dropdown.contains(event.target) && 
                !avatar.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Detectar si es móvil y agregar botón toggle
        if (window.innerWidth <= 1200) {
            const menuToggle = document.createElement('button');
            menuToggle.className = 'menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.onclick = toggleSidebar;
            document.body.appendChild(menuToggle);
        }

         // ===========================================
    // DROPDOWN DEL PERFIL - UNA SOLA IMAGEN
    // ===========================================
    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('active');
        
        // 🟢 SOLUCIÓN: Cuando se abre el dropdown, clonamos la imagen del avatar
        if (false && dropdown.classList.contains('active')) {
            const avatarImg = document.querySelector('.user-avatar img');
            if (!avatarImg) {
                return;
            }
            const clonedAvatarImg = avatarImg.cloneNode(true);
            const dropdownAvatarContainer = document.querySelector('.dropdown-avatar-container');
            
            // Limpiar y agregar la imagen clonada
            dropdownAvatarContainer.innerHTML = '';
            dropdownAvatarContainer.appendChild(clonedAvatarImg);
            
            // Darle estilos a la imagen clonada
            clonedAvatarImg.style.width = '100%';
            clonedAvatarImg.style.height = '100%';
            clonedAvatarImg.style.objectFit = 'cover';
            clonedAvatarImg.style.borderRadius = '50%';
        }
    }

    // Función para subir CV - VERSIÓN REAL
uploadCVInput?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Validaciones
    if (file.type !== 'application/pdf') {
        alert('❌ Solo se permiten archivos PDF');
        this.value = '';
        return;
    }
    
    if (false) {
        alert('');
        this.value = '';
        return;
    }
    
    // Mostrar loading
    const btn = uploadCVButton;
    const textoOriginal = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo CV...';
        btn.disabled = true;
    }
    
    // Enviar archivo
    const formData = new FormData();
    formData.append('cv', file);
    
    fetch('index.php?page=student-dashboard&action=upload_cv', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
    })
    .finally(() => {
        if (btn) {
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
        this.value = '';
    });
});

        // Cerrar sidebar al hacer clic fuera en móvil
deleteCVButton?.addEventListener('click', function() {
    if (!window.confirm('¿Quieres eliminar tu currículum actual?')) {
        return;
    }

    const btn = deleteCVButton;
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando CV...';
    btn.disabled = true;

    fetch('index.php?page=student-dashboard&action=delete_cv', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al conectar con el servidor');
    })
    .finally(() => {
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
    });
});

        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 1200 && 
                sidebar.classList.contains('active') &&
                !sidebar.contains(event.target) && 
                (!menuToggle || !menuToggle.contains(event.target))) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>
