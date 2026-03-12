<?php
$meta = [
    'dashboard' => [
        'title' => 'Panel Admin - Sistema EPIC',
        'heading' => 'Panel del centro',
        'description' => 'Un inicio mas limpio para supervisar pasantias, estudiantes y evaluaciones sin llenar la portada de cosas secundarias.',
    ],
    'companies' => [
        'title' => 'Empresas - Sistema EPIC',
        'heading' => 'Empresas del centro',
        'description' => 'Consulta cupos, ocupacion y estado actual de las empresas vinculadas a tu centro.',
    ],
    'students' => [
        'title' => 'Estudiantes - Sistema EPIC',
        'heading' => 'Estudiantes registrados',
        'description' => 'Revisa matriculas, CV y estado general de los estudiantes del centro.',
    ],
    'evaluations' => [
        'title' => 'Evaluaciones - Sistema EPIC',
        'heading' => 'Evaluaciones del centro',
        'description' => 'Monitorea evaluaciones pendientes, notas y resultados por estudiante y empresa.',
    ],
    'reports' => [
        'title' => 'Reportes - Sistema EPIC',
        'heading' => 'Reportes del centro',
        'description' => 'Lee indicadores utiles del sistema de pasantias por area tecnica y estado operativo.',
    ],
];

$pageMeta = $meta[$currentSection] ?? $meta['dashboard'];
$adminName = trim((string) ($adminUser['nombre'] ?? ($_SESSION['nombre'] ?? 'Administrador')));
if ($adminName === '') {
    $adminName = 'Administrador';
}
$adminEmail = trim((string) ($adminUser['correo'] ?? ''));
$adminInitial = function_exists('mb_substr') ? mb_substr($adminName, 0, 1, 'UTF-8') : substr($adminName, 0, 1);
$adminInitial = function_exists('mb_strtoupper') ? mb_strtoupper($adminInitial, 'UTF-8') : strtoupper($adminInitial);
$welcomeName = explode(' ', $adminName)[0];

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$formatDate = function ($value, $withTime = false) {
    if (empty($value)) {
        return 'Sin registro';
    }
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return 'Sin registro';
    }
    return date($withTime ? 'd/m/Y h:i A' : 'd/m/Y', $timestamp);
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageMeta['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/studentdashboard.css">
    <link rel="stylesheet" href="../public/css/admin.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="system-logo-top">
                    <img src="../EPIC.png" alt="Sistema EPIC" class="logo-horizontal">
                </div>
                <div class="ariel"></div>
                <div class="logo-circle-container">
                    <div class="logo-circle">
                        <img src="../nojodas.png.jpeg" alt="Sistema EPIC">
                    </div>
                    <h2 class="sidebar-title">Sistema EPIC</h2>
                    <p class="sidebar-subtitle">Gestion administrativa de pasantias</p>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-title">Navegacion</div>
                <a href="index.php?page=admin-dashboard" class="nav-item<?php echo $currentSection === 'dashboard' ? ' active' : ''; ?>"><i class="fas fa-home nav-icon"></i>Inicio</a>
                <a href="index.php?page=admin-companies" class="nav-item<?php echo $currentSection === 'companies' ? ' active' : ''; ?>"><i class="fas fa-building nav-icon"></i>Empresas</a>
                <a href="index.php?page=admin-students" class="nav-item<?php echo $currentSection === 'students' ? ' active' : ''; ?>"><i class="fas fa-users nav-icon"></i>Estudiantes</a>
                <a href="index.php?page=admin-evaluations" class="nav-item<?php echo $currentSection === 'evaluations' ? ' active' : ''; ?>"><i class="fas fa-clipboard-check nav-icon"></i>Evaluaciones</a>
                <a href="index.php?page=admin-reports" class="nav-item<?php echo $currentSection === 'reports' ? ' active' : ''; ?>"><i class="fas fa-chart-column nav-icon"></i>Reportes</a>
            </nav>

            <div class="sidebar-footer">
                <a href="index.php?page=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar sesion
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div class="admin-topbar-copy">
                    <span class="admin-topbar-label"><?php echo htmlspecialchars($pageMeta['heading']); ?></span>
                    <strong><?php echo htmlspecialchars($centro['nombre'] ?? 'Centro'); ?></strong>
                </div>

                <div class="user-profile-top">
                    <div class="user-avatar avatar-fallback" onclick="toggleProfileDropdown()">
                        <span class="avatar-initial"><?php echo htmlspecialchars($adminInitial); ?></span>
                    </div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-user">
                                <div class="dropdown-avatar-container avatar-fallback">
                                    <span class="avatar-initial"><?php echo htmlspecialchars($adminInitial); ?></span>
                                </div>
                                <div class="dropdown-user-info">
                                    <h4><?php echo htmlspecialchars($adminName); ?></h4>
                                    <p>Administrador del centro</p>
                                </div>
                            </div>
                            <?php if ($adminEmail !== ''): ?>
                            <div class="dropdown-user-email"><?php echo htmlspecialchars($adminEmail); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-menu">
                            <a href="index.php?page=admin-dashboard" class="dropdown-item"><i class="fas fa-home"></i>Panel principal</a>
                            <a href="index.php?page=admin-companies" class="dropdown-item"><i class="fas fa-building"></i>Empresas</a>
                            <a href="index.php?page=admin-students" class="dropdown-item"><i class="fas fa-users"></i>Estudiantes</a>
                            <a href="index.php?page=admin-evaluations" class="dropdown-item"><i class="fas fa-clipboard-check"></i>Evaluaciones</a>
                            <a href="index.php?page=admin-reports" class="dropdown-item"><i class="fas fa-chart-line"></i>Reportes</a>
                        </div>
                        <div class="dropdown-footer">
                            <a href="index.php?page=logout" class="dropdown-logout"><i class="fas fa-sign-out-alt"></i>Cerrar sesion</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-wrapper admin-page">
                <?php if ($flashMessage !== ''): ?>
                <div class="admin-alert admin-alert-<?php echo htmlspecialchars($flashType); ?>">
                    <i class="fas <?php echo $flashType === 'success' ? 'fa-circle-check' : ($flashType === 'error' ? 'fa-circle-xmark' : ($flashType === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info')); ?>"></i>
                    <div><?php echo htmlspecialchars($flashMessage); ?></div>
                </div>
                <?php endif; ?>

                <section class="admin-hero-panel">
                    <div>
                        <span class="hero-kicker">Centro <?php echo htmlspecialchars($centro['codigo_unico'] ?? ''); ?></span>
                        <h1><?php echo htmlspecialchars($pageMeta['heading']); ?></h1>
                        <p><?php echo htmlspecialchars($pageMeta['description']); ?></p>
                    </div>
                    <div class="hero-badge-list">
                        <span class="hero-badge"><i class="fas fa-user-shield"></i><?php echo htmlspecialchars($welcomeName); ?></span>
                        <span class="hero-badge"><i class="fas fa-circle-check"></i><?php echo htmlspecialchars(ucfirst($centro['estado'] ?? 'activo')); ?></span>
                        <span class="hero-badge"><i class="fas fa-calendar"></i><?php echo htmlspecialchars($formatDate($centro['created_at'] ?? null)); ?></span>
                    </div>
                </section>

                <?php require $sectionView; ?>

                <footer class="footer admin-footer">
                    <div class="version">EPIC V2.0</div>
                    <p class="copyright">Sistema de gestion de pasantias</p>
                    <p class="copyright">&copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
                </footer>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('active');
        }

        function toggleProfileDropdown() {
            document.getElementById('profileDropdown')?.classList.toggle('active');
        }

        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.querySelector('.user-avatar');
            if (dropdown && dropdown.classList.contains('active') && avatar && !dropdown.contains(event.target) && !avatar.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        if (window.innerWidth <= 1200) {
            const menuToggle = document.createElement('button');
            menuToggle.className = 'menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.onclick = toggleSidebar;
            document.body.appendChild(menuToggle);
        }

        document.addEventListener('click', function (event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 1200 && sidebar && sidebar.classList.contains('active') && !sidebar.contains(event.target) && (!menuToggle || !menuToggle.contains(event.target))) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>
