<?php
// app/views/layouts/user-profile.php

if (!isset($estudiante) && isset($_SESSION['user_id'])) {
    $estudiante = Database::selectOne("
        SELECT e.*, u.nombre, u.correo
        FROM estudiantes e
        JOIN usuarios u ON e.usuario_id = u.id
        WHERE u.id = ?
    ", [$_SESSION['user_id']]);
}

if (!isset($foto_perfil) && isset($estudiante)) {
    $foto_perfil = $estudiante['foto_perfil'] ?? '';
}

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
$avatarPath = ltrim(str_replace('\\', '/', (string) ($foto_perfil ?? '')), '/');
$hasProfilePhoto = $avatarPath !== '' && file_exists(PUBLIC_PATH . $avatarPath);
?>

<div class="top-bar">
    <div class="user-profile-top">
        <div class="user-avatar<?php echo $hasProfilePhoto ? '' : ' avatar-fallback'; ?>" onclick="toggleProfileDropdown()">
            <?php if ($hasProfilePhoto): ?>
            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre'] ?? 'Usuario'); ?>">
            <?php else: ?>
            <span class="avatar-initial"><?php echo htmlspecialchars($avatarInitial); ?></span>
            <?php endif; ?>
        </div>

        <div class="profile-dropdown" id="profileDropdown">
            <div class="dropdown-header">
                <div class="dropdown-user">
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

<style>
.user-profile-top {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-left: auto;
    position: relative;
}

.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid var(--secondary);
    box-shadow: 0 3px 10px rgba(66, 153, 225, 0.2);
    transition: all 0.3s;
    cursor: pointer;
}

.user-avatar:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(66, 153, 225, 0.3);
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: var(--white);
}

.user-avatar.avatar-fallback {
    border-color: transparent;
}

.avatar-initial {
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
    text-transform: uppercase;
}

.profile-dropdown {
    position: absolute;
    top: 70px;
    right: 0;
    width: 280px;
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    border: 1px solid var(--gray-200);
    z-index: 1000;
    display: none;
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.profile-dropdown.active {
    display: block;
}

.dropdown-header {
    padding: 20px;
    border-bottom: 1px solid var(--gray-200);
}

.dropdown-user {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.dropdown-avatar-container {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--secondary);
    flex-shrink: 0;
}

.dropdown-avatar-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dropdown-avatar-container.avatar-fallback {
    border-color: transparent;
}

.dropdown-user-info h4 {
    color: var(--primary);
    font-weight: 700;
    margin-bottom: 3px;
    font-size: 16px;
}

.dropdown-user-info p {
    color: var(--dark);
    font-size: 13px;
    opacity: 0.8;
}

.dropdown-user-email {
    font-size: 14px;
    color: var(--secondary);
    font-weight: 500;
}

.dropdown-menu {
    padding: 10px 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: var(--dark);
    text-decoration: none;
    transition: all 0.2s;
    font-size: 14px;
}

.dropdown-item:hover {
    background: var(--light);
    color: var(--primary);
}

.dropdown-item i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
    color: var(--secondary);
}

.dropdown-divider {
    height: 1px;
    background: var(--gray-200);
    margin: 8px 0;
}

.dropdown-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--gray-200);
}

.dropdown-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 10px;
    background: var(--danger);
    color: var(--white);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.dropdown-logout:hover {
    background: #e53e3e;
    transform: translateY(-2px);
}

.dropdown-logout i {
    margin-right: 8px;
}
</style>

<script>
function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    if (!dropdown) {
        return;
    }

    dropdown.classList.toggle('active');
}

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
</script>
