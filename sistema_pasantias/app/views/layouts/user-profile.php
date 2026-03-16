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
    width: 70px;
    height: 70px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid rgba(66, 153, 225, 0.95);
    box-shadow: 0 6px 16px rgba(26, 54, 93, 0.18);
    transition: all 0.3s;
    cursor: pointer;
}

.user-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(26, 54, 93, 0.2);
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
    top: 82px;
    right: 0;
    width: 336px;
    background: linear-gradient(180deg, #fbfdff 0%, #f5f8fc 100%);
    border-radius: 18px;
    box-shadow: 0 20px 42px rgba(15, 23, 42, 0.16);
    border: 1px solid #d9e2ee;
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
    padding: 22px 22px 18px;
    border-bottom: 1px solid #d7e0ec;
}

.dropdown-user {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 12px;
}

.dropdown-avatar-container {
    width: 58px;
    height: 58px;
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
    font-weight: 800;
    margin-bottom: 2px;
    font-size: 17px;
}

.dropdown-user-info p {
    color: var(--dark);
    font-size: 14px;
    opacity: 0.86;
}

.dropdown-user-email {
    font-size: 15px;
    color: var(--secondary);
    font-weight: 700;
}

.dropdown-menu {
    padding: 12px 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 14px 22px;
    color: var(--dark);
    text-decoration: none;
    transition: all 0.2s;
    font-size: 15px;
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
    background: #d7e0ec;
    margin: 9px 0;
}

.dropdown-footer {
    padding: 18px 22px;
    border-top: 1px solid #d7e0ec;
}

.dropdown-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 12px 14px;
    background: #f16060;
    color: var(--white);
    border: none;
    border-radius: 11px;
    font-weight: 700;
    font-size: 15px;
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
    text-underline-offset: 2px;
    cursor: pointer;
    transition: all 0.3s;
}

.dropdown-logout:hover {
    background: #e53e3e;
    transform: translateY(-2px);
}

.dropdown-logout i {
    margin-right: 8px;
}

@media (max-width: 768px) {
    .user-avatar {
        width: 62px;
        height: 62px;
    }

    .profile-dropdown {
        width: min(92vw, 336px);
        right: -8px;
    }
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

