<?php
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

$profileTab = $profile_tab ?? 'overview';
$cvPath = !empty($estudiante['cv_path']) ? ltrim(str_replace('\\', '/', (string) $estudiante['cv_path']), '/') : '';
$cvFullPath = $cvPath !== '' ? PUBLIC_PATH . $cvPath : '';
$cvFileName = $cvPath !== '' ? basename($cvPath) : '';
$cvUploadedAt = ($cvFullPath !== '' && file_exists($cvFullPath)) ? date('d/m/Y', filemtime($cvFullPath)) : null;

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

$completionChecks = [
    !empty($estudiante['nombre']),
    !empty($estudiante['correo']),
    !empty($estudiante['matricula']),
    !empty($estudiante['area_tecnica']),
    $hasProfilePhoto,
    !empty($estudiante['cv_path']),
];
$completionDone = 0;
foreach ($completionChecks as $completionCheck) {
    if ($completionCheck) {
        $completionDone++;
    }
}
$profileCompletion = (int) round(($completionDone / max(count($completionChecks), 1)) * 100);

$evalTotal = (int) ($evaluaciones_stats['total'] ?? 0);
$evalPendientes = (int) ($evaluaciones_stats['pendientes'] ?? 0);
$evalAprobadas = (int) ($evaluaciones_stats['aprobadas'] ?? 0);
$evalReprobadas = (int) ($evaluaciones_stats['reprobadas'] ?? 0);
$evalAnuladas = (int) ($evaluaciones_stats['anuladas'] ?? 0);
$empresasDisponibles = (int) ($empresas_count['total'] ?? 0);
$isActiveAccount = ($estudiante['estado'] ?? '') === 'activo';
$focusTargetMap = [
    'overview' => 'profileOverviewCard',
    'profile' => 'profileEditCard',
    'photo' => 'profilePhotoCard',
    'cv' => 'profileCvCard',
    'settings' => 'profileSecurityCard',
];
$focusTargetId = $focusTargetMap[$profileTab] ?? 'profileOverviewCard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil - Sistema EPIC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/studentdashboard.css">
    <style>
        .profile-page {
            display: grid;
            gap: 26px;
        }

        .alert {
            padding: 18px 20px;
            border-radius: 16px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            border-left: 4px solid;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        }

        .alert i {
            font-size: 20px;
            margin-top: 2px;
        }

        .alert strong,
        .alert p {
            margin: 0;
        }

        .alert p {
            margin-top: 4px;
            opacity: 0.9;
        }

        .alert-success {
            background: rgba(72, 187, 120, 0.12);
            border-left-color: var(--success);
            color: #276749;
        }

        .alert-error {
            background: rgba(245, 101, 101, 0.12);
            border-left-color: var(--danger);
            color: #c53030;
        }

        .alert-warning {
            background: rgba(237, 137, 54, 0.12);
            border-left-color: var(--warning);
            color: #c05621;
        }

        .alert-info {
            background: rgba(66, 153, 225, 0.12);
            border-left-color: var(--secondary);
            color: #2b6cb0;
        }

        .profile-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.85fr);
            gap: 24px;
            align-items: stretch;
        }

        .profile-hero-card {
            background:
                radial-gradient(circle at top right, rgba(104, 211, 145, 0.15), transparent 30%),
                linear-gradient(135deg, #14345d 0%, #1f4d84 42%, #3e83d6 100%);
            color: var(--white);
            border: none;
            box-shadow: 0 16px 38px rgba(20, 52, 93, 0.24);
        }

        .profile-hero-card::before {
            display: none;
        }

        .profile-hero-body {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 22px;
            align-items: center;
        }

        .profile-hero-avatar {
            width: 110px;
            height: 110px;
            border-radius: 28px;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.34);
            background: rgba(255, 255, 255, 0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 800;
            text-transform: uppercase;
            box-shadow: 0 16px 34px rgba(10, 26, 48, 0.22);
        }

        .profile-hero-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .profile-hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .profile-hero-title {
            font-size: 34px;
            font-weight: 800;
            margin-bottom: 8px;
            font-family: 'Montserrat', sans-serif;
        }

        .profile-hero-copy p {
            margin: 0;
            color: rgba(255, 255, 255, 0.88);
            line-height: 1.7;
            font-size: 15px;
            max-width: 620px;
        }

        .profile-badges,
        .profile-summary-list,
        .profile-inline-actions,
        .profile-subnav,
        .profile-pill-list,
        .profile-doc-actions,
        .profile-note-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .profile-badges {
            margin-top: 18px;
        }

        .profile-pill,
        .profile-subnav-link {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 14px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }

        .profile-pill {
            background: rgba(255, 255, 255, 0.12);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .profile-pill.is-light {
            background: rgba(255, 255, 255, 0.14);
        }

        .profile-pill.is-white {
            background: rgba(255, 255, 255, 0.18);
        }

        .profile-hero-side {
            display: grid;
            gap: 18px;
        }

        .profile-mini-card {
            padding: 28px;
        }

        .profile-mini-card h3,
        .profile-section-card h2,
        .profile-section-card h3,
        .profile-stat-card h3 {
            margin: 0;
        }

        .profile-mini-card p {
            margin: 0;
            color: var(--dark);
            line-height: 1.7;
            opacity: 0.82;
        }

        .profile-progress {
            margin-top: 18px;
        }

        .profile-progress-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .profile-progress-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
        }

        .profile-progress-bar {
            width: 100%;
            height: 12px;
            border-radius: 999px;
            background: rgba(226, 232, 240, 0.92);
            overflow: hidden;
        }

        .profile-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--secondary), var(--success));
        }

        .profile-subnav {
            margin-top: 6px;
        }

        .profile-subnav-link {
            color: var(--primary);
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(26, 54, 93, 0.08);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
        }

        .profile-subnav-link:hover,
        .profile-subnav-link.is-active {
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            color: var(--white);
            border-color: transparent;
            box-shadow: 0 12px 22px rgba(66, 153, 225, 0.22);
        }

        .profile-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
        }

        .profile-stat-card {
            padding: 24px;
        }

        .profile-stat-card::before {
            height: 3px;
        }

        .profile-stat-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .profile-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .profile-stat-icon.blue {
            color: #1d4ed8;
            background: rgba(29, 78, 216, 0.12);
        }

        .profile-stat-icon.green {
            color: #1f7a46;
            background: rgba(72, 187, 120, 0.14);
        }

        .profile-stat-icon.orange {
            color: #c05621;
            background: rgba(237, 137, 54, 0.14);
        }

        .profile-stat-icon.red {
            color: #c53030;
            background: rgba(245, 101, 101, 0.14);
        }

        .profile-stat-value {
            font-size: 34px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 8px;
            font-family: 'Montserrat', sans-serif;
        }

        .profile-stat-copy {
            color: var(--dark);
            font-size: 14px;
            opacity: 0.76;
            line-height: 1.6;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
            gap: 24px;
            align-items: start;
        }

        .profile-main-column,
        .profile-side-column {
            display: grid;
            gap: 24px;
        }

        .profile-section-card {
            padding: 30px;
        }

        .profile-section-card.section-focus {
            border-color: rgba(66, 153, 225, 0.4);
            box-shadow: 0 18px 36px rgba(66, 153, 225, 0.16);
        }

        .profile-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .profile-section-head p {
            margin: 8px 0 0;
            color: var(--dark);
            opacity: 0.78;
            line-height: 1.65;
        }

        .profile-section-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(66, 153, 225, 0.1);
            color: var(--secondary);
        }

        .profile-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .profile-status-badge.is-active {
            background: rgba(72, 187, 120, 0.12);
            color: #2f855a;
        }

        .profile-status-badge.is-warning {
            background: rgba(237, 137, 54, 0.12);
            color: #c05621;
        }

        .profile-form-grid,
        .profile-info-grid,
        .profile-account-grid {
            display: grid;
            gap: 16px;
        }

        .profile-form-grid,
        .profile-account-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .profile-info-grid {
            grid-template-columns: 1fr;
        }

        .profile-field {
            display: grid;
            gap: 8px;
        }

        .profile-field.full {
            grid-column: 1 / -1;
        }

        .profile-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        .profile-input,
        .profile-textarea,
        .profile-file {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: #ffffff;
            color: var(--dark);
            padding: 14px 16px;
            font-size: 15px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .profile-input:focus,
        .profile-textarea:focus,
        .profile-file:focus {
            outline: none;
            border-color: rgba(66, 153, 225, 0.65);
            box-shadow: 0 0 0 4px rgba(66, 153, 225, 0.12);
        }

        .profile-file {
            padding: 12px 14px;
            background: #f8fbff;
        }

        .profile-readonly {
            min-height: 52px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: #f8fbff;
            color: var(--dark);
            padding: 14px 16px;
            font-size: 15px;
            display: flex;
            align-items: center;
        }

        .profile-help {
            font-size: 13px;
            color: var(--dark);
            opacity: 0.72;
            line-height: 1.6;
        }

        .profile-inline-actions {
            margin-top: 18px;
        }

        .profile-action-btn {
            width: auto;
            min-width: 180px;
            padding: 14px 20px;
            text-transform: none;
            letter-spacing: 0;
            font-size: 15px;
        }

        .profile-photo-shell {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        .profile-photo-preview {
            width: 112px;
            height: 112px;
            border-radius: 24px;
            overflow: hidden;
            border: 3px solid rgba(66, 153, 225, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(26, 54, 93, 0.08), rgba(66, 153, 225, 0.12));
            color: var(--primary);
            font-size: 36px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .profile-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .profile-note-list {
            margin-top: 16px;
        }

        .profile-note-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13px;
            background: #f8fbff;
            color: var(--primary);
        }

        .profile-account-list {
            display: grid;
            gap: 14px;
        }

        .profile-account-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.04);
        }

        .profile-account-item span {
            color: var(--dark);
            opacity: 0.74;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .profile-account-item strong {
            color: var(--primary);
            font-size: 15px;
            text-align: right;
            line-height: 1.5;
        }

        .profile-cv-box {
            background: #f8fbff;
            border-radius: 18px;
            padding: 18px;
            border: 1px solid rgba(66, 153, 225, 0.12);
        }

        .profile-cv-box + .profile-cv-box {
            margin-top: 16px;
        }

        .profile-cv-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .profile-cv-name {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            font-weight: 700;
        }

        .profile-doc-actions {
            margin-top: 18px;
        }

        .profile-doc-actions .card-btn {
            width: auto;
            min-width: 160px;
            text-transform: none;
            letter-spacing: 0;
            font-size: 15px;
        }

        .profile-small-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .profile-small-metric {
            border-radius: 18px;
            padding: 16px;
            background: rgba(15, 23, 42, 0.04);
        }

        .profile-small-metric span {
            display: block;
            color: var(--dark);
            opacity: 0.72;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .profile-small-metric strong {
            color: var(--primary);
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
        }

        .profile-feedback-box {
            margin-top: 16px;
            padding: 16px 18px;
            border-radius: 16px;
            background: rgba(72, 187, 120, 0.1);
            color: #276749;
            line-height: 1.7;
        }

        .profile-empty-message {
            margin-top: 16px;
            padding: 15px 16px;
            border-radius: 16px;
            background: rgba(237, 137, 54, 0.1);
            color: #c05621;
            line-height: 1.6;
        }

        @media (max-width: 1200px) {
            .profile-hero,
            .profile-layout,
            .profile-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .profile-hero-body,
            .profile-photo-shell,
            .profile-form-grid,
            .profile-account-grid,
            .profile-small-grid {
                grid-template-columns: 1fr;
            }

            .profile-section-head,
            .profile-account-item,
            .profile-cv-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile-account-item strong {
                text-align: left;
            }

            .profile-inline-actions .card-btn,
            .profile-doc-actions .card-btn {
                width: 100%;
            }

            .profile-hero-avatar,
            .profile-photo-preview {
                width: 96px;
                height: 96px;
                border-radius: 22px;
            }
        }
    </style>
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
                    <p class="sidebar-subtitle">Sistema de Gestion de Pasantias</p>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-title">Navegacion</div>
                <a href="index.php?page=student-dashboard" class="nav-item">
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
                <a href="index.php?page=student-profile" class="nav-item active">
                    <i class="fas fa-user nav-icon"></i>
                    Mi Perfil
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="index.php?page=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar Sesion
                </a>
            </div>
        </aside>

        <main class="main-content">
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
                                Mi curriculum
                            </a>
                            <a href="index.php?page=student-results" class="dropdown-item">
                                <i class="fas fa-chart-bar"></i>
                                Mis evaluaciones
                            </a>
                        </div>

                        <div class="dropdown-footer">
                            <a href="index.php?page=logout" class="dropdown-logout">
                                <i class="fas fa-sign-out-alt"></i>
                                Cerrar sesion
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-wrapper">
                <div class="profile-page">
                    <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'info'); ?>">
                        <i class="fas fa-circle-info"></i>
                        <div>
                            <strong><?php echo htmlspecialchars($_SESSION['flash_message']); ?></strong>
                        </div>
                    </div>
                    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                    <?php endif; ?>

                    <section class="profile-hero">
                        <div class="card profile-hero-card" id="profileOverviewCard">
                            <div class="profile-hero-body">
                                <div class="profile-hero-avatar">
                                    <?php if ($hasProfilePhoto): ?>
                                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre'] ?? 'Usuario'); ?>">
                                    <?php else: ?>
                                    <span><?php echo htmlspecialchars($avatarInitial); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="profile-hero-copy">
                                    <div class="profile-hero-kicker">
                                        <i class="fas fa-id-badge"></i>
                                        Perfil del estudiante
                                    </div>
                                    <h1 class="profile-hero-title"><?php echo htmlspecialchars($estudiante['nombre'] ?? ''); ?></h1>
                                    <p>
                                        Aqui puedes ver tu informacion completa, ajustar tus datos basicos,
                                        cambiar tu contrasena y subir una foto si prefieres usar una imagen en vez
                                        de la inicial de tu nombre.
                                    </p>

                                    <div class="profile-badges">
                                        <span class="profile-pill">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($estudiante['correo'] ?? ''); ?>
                                        </span>
                                        <span class="profile-pill is-light">
                                            <i class="fas fa-graduation-cap"></i>
                                            Matricula <?php echo htmlspecialchars($estudiante['matricula'] ?? ''); ?>
                                        </span>
                                        <span class="profile-pill is-white">
                                            <i class="fas fa-briefcase"></i>
                                            <?php echo htmlspecialchars($estudiante['area_tecnica'] ?? ''); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-hero-side">
                            <section class="card profile-mini-card">
                                <div class="profile-section-head" style="margin-bottom: 0;">
                                    <div>
                                        <h3>Avance del perfil</h3>
                                        <p>Completa lo esencial para verte mejor en el sistema y tener tu perfil listo para postularte.</p>
                                    </div>
                                    <span class="profile-status-badge <?php echo $profileCompletion >= 80 ? 'is-active' : 'is-warning'; ?>">
                                        <i class="fas <?php echo $profileCompletion >= 80 ? 'fa-circle-check' : 'fa-circle-half-stroke'; ?>"></i>
                                        <?php echo $profileCompletion >= 80 ? 'Bien completo' : 'Aun falta'; ?>
                                    </span>
                                </div>

                                <div class="profile-progress">
                                    <div class="profile-progress-head">
                                        <strong>Completado</strong>
                                        <div class="profile-progress-value"><?php echo $profileCompletion; ?>%</div>
                                    </div>
                                    <div class="profile-progress-bar">
                                        <div class="profile-progress-fill" style="width: <?php echo $profileCompletion; ?>%;"></div>
                                    </div>
                                </div>

                                <div class="profile-note-list">
                                    <span class="profile-note-chip">
                                        <i class="fas <?php echo $hasProfilePhoto ? 'fa-image' : 'fa-circle-user'; ?>"></i>
                                        <?php echo $hasProfilePhoto ? 'Foto de perfil lista' : 'Puedes agregar una foto'; ?>
                                    </span>
                                    <span class="profile-note-chip">
                                        <i class="fas <?php echo $tiene_cv ? 'fa-file-pdf' : 'fa-file-circle-xmark'; ?>"></i>
                                        <?php echo $tiene_cv ? 'Curriculum cargado' : 'Curriculum pendiente'; ?>
                                    </span>
                                    <span class="profile-note-chip">
                                        <i class="fas <?php echo $isActiveAccount ? 'fa-shield-check' : 'fa-triangle-exclamation'; ?>"></i>
                                        Cuenta <?php echo $isActiveAccount ? 'activa' : 'con revision'; ?>
                                    </span>
                                </div>
                            </section>

                            <section class="card profile-mini-card">
                                <h3>Accesos rapidos</h3>
                                <p>Salta directo a la parte que quieras revisar o editar.</p>
                                <div class="profile-subnav">
                                    <a href="index.php?page=student-profile" class="profile-subnav-link<?php echo $profileTab === 'overview' ? ' is-active' : ''; ?>">
                                        <i class="fas fa-layer-group"></i>
                                        Resumen
                                    </a>
                                    <a href="index.php?page=student-profile&tab=profile" class="profile-subnav-link<?php echo $profileTab === 'profile' ? ' is-active' : ''; ?>">
                                        <i class="fas fa-pen"></i>
                                        Datos
                                    </a>
                                    <a href="index.php?page=student-profile&tab=photo" class="profile-subnav-link<?php echo $profileTab === 'photo' ? ' is-active' : ''; ?>">
                                        <i class="fas fa-image"></i>
                                        Foto
                                    </a>
                                    <a href="index.php?page=student-profile&tab=cv" class="profile-subnav-link<?php echo $profileTab === 'cv' ? ' is-active' : ''; ?>">
                                        <i class="fas fa-file-pdf"></i>
                                        Curriculum
                                    </a>
                                    <a href="index.php?page=student-profile&tab=settings" class="profile-subnav-link<?php echo $profileTab === 'settings' ? ' is-active' : ''; ?>">
                                        <i class="fas fa-lock"></i>
                                        Seguridad
                                    </a>
                                </div>
                            </section>
                        </div>
                    </section>

                    <section class="profile-stats-grid">
                        <article class="card profile-stat-card">
                            <div class="profile-stat-head">
                                <div>
                                    <h3>Empresas disponibles</h3>
                                </div>
                                <div class="profile-stat-icon blue">
                                    <i class="fas fa-building"></i>
                                </div>
                            </div>
                            <div class="profile-stat-value"><?php echo $empresasDisponibles; ?></div>
                            <div class="profile-stat-copy">Oportunidades activas para tu area tecnica actual.</div>
                        </article>

                        <article class="card profile-stat-card">
                            <div class="profile-stat-head">
                                <div>
                                    <h3>Evaluaciones aprobadas</h3>
                                </div>
                                <div class="profile-stat-icon green">
                                    <i class="fas fa-circle-check"></i>
                                </div>
                            </div>
                            <div class="profile-stat-value"><?php echo $evalAprobadas; ?></div>
                            <div class="profile-stat-copy">Intentos aprobados dentro del sistema.</div>
                        </article>

                        <article class="card profile-stat-card">
                            <div class="profile-stat-head">
                                <div>
                                    <h3>Evaluaciones pendientes</h3>
                                </div>
                                <div class="profile-stat-icon orange">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="profile-stat-value"><?php echo $evalPendientes; ?></div>
                            <div class="profile-stat-copy">Procesos que aun siguen en curso o sin cerrar.</div>
                        </article>

                        <article class="card profile-stat-card">
                            <div class="profile-stat-head">
                                <div>
                                    <h3>Reprobadas o anuladas</h3>
                                </div>
                                <div class="profile-stat-icon red">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </div>
                            </div>
                            <div class="profile-stat-value"><?php echo $evalReprobadas + $evalAnuladas; ?></div>
                            <div class="profile-stat-copy">Intentos a revisar para mejorar tu progreso.</div>
                        </article>
                    </section>

                    <section class="profile-layout">
                        <div class="profile-main-column">
                            <section class="card profile-section-card<?php echo $profileTab === 'profile' ? ' section-focus' : ''; ?>" id="profileEditCard">
                                <div class="profile-section-head">
                                    <div>
                                        <div class="profile-section-label">
                                            <i class="fas fa-user-pen"></i>
                                            Datos personales
                                        </div>
                                        <h2 style="margin-top: 14px;">Editar informacion basica</h2>
                                        <p>Actualiza tu nombre y el correo principal que usas dentro del sistema.</p>
                                    </div>
                                </div>

                                <form action="index.php?page=student-profile&action=update_profile" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" name="tab" value="profile">

                                    <div class="profile-form-grid">
                                        <div class="profile-field">
                                            <label class="profile-label" for="nombre">Nombre completo</label>
                                            <input
                                                type="text"
                                                id="nombre"
                                                name="nombre"
                                                class="profile-input"
                                                value="<?php echo htmlspecialchars($estudiante['nombre'] ?? ''); ?>"
                                                maxlength="100"
                                                required
                                            >
                                        </div>

                                        <div class="profile-field">
                                            <label class="profile-label" for="correo">Correo electronico</label>
                                            <input
                                                type="email"
                                                id="correo"
                                                name="correo"
                                                class="profile-input"
                                                value="<?php echo htmlspecialchars($estudiante['correo'] ?? ''); ?>"
                                                maxlength="150"
                                                required
                                            >
                                        </div>

                                        <div class="profile-field">
                                            <label class="profile-label">Matricula</label>
                                            <div class="profile-readonly"><?php echo htmlspecialchars($estudiante['matricula'] ?? ''); ?></div>
                                            <div class="profile-help">Se genera automaticamente y no se modifica desde aqui.</div>
                                        </div>

                                        <div class="profile-field">
                                            <label class="profile-label">Area tecnica</label>
                                            <div class="profile-readonly"><?php echo htmlspecialchars($estudiante['area_tecnica'] ?? ''); ?></div>
                                            <div class="profile-help">Tu area tecnica define las empresas y evaluaciones que ves.</div>
                                        </div>
                                    </div>

                                    <div class="profile-inline-actions">
                                        <button type="submit" class="card-btn btn-green profile-action-btn">
                                            <i class="fas fa-floppy-disk"></i>
                                            Guardar cambios
                                        </button>
                                    </div>
                                </form>
                            </section>

                            <section class="card profile-section-card<?php echo $profileTab === 'photo' ? ' section-focus' : ''; ?>" id="profilePhotoCard">
                                <div class="profile-section-head">
                                    <div>
                                        <div class="profile-section-label">
                                            <i class="fas fa-image"></i>
                                            Foto de perfil
                                        </div>
                                        <h2 style="margin-top: 14px;">Usa una imagen en vez de la inicial</h2>
                                        <p>Sube una foto si prefieres que tu avatar se vea mas personal en el dashboard y en las demas vistas.</p>
                                    </div>
                                </div>

                                <div class="profile-photo-shell">
                                    <div class="profile-photo-preview" id="profilePhotoPreview">
                                        <?php if ($hasProfilePhoto): ?>
                                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre'] ?? 'Usuario'); ?>" id="profilePhotoPreviewImage">
                                        <?php else: ?>
                                        <span id="profilePhotoPreviewInitial"><?php echo htmlspecialchars($avatarInitial); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <form action="index.php?page=student-profile&action=upload_photo" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                            <input type="hidden" name="tab" value="photo">

                                            <div class="profile-field">
                                                <label class="profile-label" for="profile_photo">Selecciona una imagen</label>
                                                <input type="file" id="profile_photo" name="profile_photo" class="profile-file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                                <div class="profile-help" id="photoFileHelp">Formatos admitidos: JPG, PNG o WEBP. Tamano maximo: 5 MB.</div>
                                            </div>

                                            <div class="profile-inline-actions">
                                                <button type="submit" class="card-btn btn-green profile-action-btn">
                                                    <i class="fas fa-upload"></i>
                                                    Subir foto
                                                </button>
                                            </div>
                                        </form>

                                        <?php if ($hasProfilePhoto): ?>
                                        <form action="index.php?page=student-profile&action=delete_photo" method="POST" style="margin-top: 12px;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                            <input type="hidden" name="tab" value="photo">
                                            <button type="submit" class="card-btn card-btn-danger profile-action-btn" onclick="return confirm('Quieres eliminar tu foto de perfil actual?');">
                                                <i class="fas fa-trash"></i>
                                                Quitar foto
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <div class="profile-note-list">
                                            <span class="profile-note-chip">
                                                <i class="fas fa-camera"></i>
                                                La imagen se vera en el avatar superior y en tu perfil.
                                            </span>
                                            <span class="profile-note-chip">
                                                <i class="fas fa-circle-info"></i>
                                                Si no subes una foto, se usa la inicial de tu nombre.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                            
                            <section class="card profile-section-card<?php echo $profileTab === 'settings' ? ' section-focus' : ''; ?>" id="profileSecurityCard">
                                <div class="profile-section-head">
                                    <div>
                                        <div class="profile-section-label">
                                            <i class="fas fa-lock"></i>
                                            Seguridad
                                        </div>
                                        <h2 style="margin-top: 14px;">Cambiar contrasena</h2>
                                        <p>Usa una clave nueva si quieres reforzar la seguridad de tu cuenta.</p>
                                    </div>
                                </div>

                                <form action="index.php?page=student-profile&action=change_password" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" name="tab" value="settings">

                                    <div class="profile-form-grid">
                                        <div class="profile-field full">
                                            <label class="profile-label" for="current_password">Contrasena actual</label>
                                            <input type="password" id="current_password" name="current_password" class="profile-input" autocomplete="current-password" required>
                                        </div>

                                        <div class="profile-field">
                                            <label class="profile-label" for="new_password">Nueva contrasena</label>
                                            <input type="password" id="new_password" name="new_password" class="profile-input" autocomplete="new-password" required>
                                            <div class="profile-help">Minimo 8 caracteres, con al menos una mayuscula y un numero.</div>
                                        </div>

                                        <div class="profile-field">
                                            <label class="profile-label" for="confirm_password">Confirmar contrasena</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="profile-input" autocomplete="new-password" required>
                                        </div>
                                    </div>

                                    <div class="profile-inline-actions">
                                        <button type="submit" class="card-btn profile-action-btn">
                                            <i class="fas fa-key"></i>
                                            Actualizar contrasena
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </div>

                        <div class="profile-side-column">
                            <section class="card profile-section-card">
                                <div class="profile-section-head">
                                    <div>
                                        <div class="profile-section-label">
                                            <i class="fas fa-address-card"></i>
                                            Cuenta
                                        </div>
                                        <h2 style="margin-top: 14px;">Informacion disponible</h2>
                                        <p>Esto es lo que el sistema conoce hoy de tu perfil.</p>
                                    </div>
                                    <span class="profile-status-badge <?php echo $isActiveAccount ? 'is-active' : 'is-warning'; ?>">
                                        <i class="fas <?php echo $isActiveAccount ? 'fa-circle-check' : 'fa-clock'; ?>"></i>
                                        <?php echo $isActiveAccount ? 'Activa' : 'Pendiente'; ?>
                                    </span>
                                </div>

                                <div class="profile-account-list">
                                    <div class="profile-account-item">
                                        <span>Centro</span>
                                        <strong><?php echo htmlspecialchars($_SESSION['centro_nombre'] ?? 'No asignado'); ?></strong>
                                    </div>
                                    <div class="profile-account-item">
                                        <span>Codigo de centro</span>
                                        <strong><?php echo htmlspecialchars($_SESSION['centro_codigo'] ?? 'N/D'); ?></strong>
                                    </div>
                                    <div class="profile-account-item">
                                        <span>Rol</span>
                                        <strong>Estudiante</strong>
                                    </div>
                                    <div class="profile-account-item">
                                        <span>Miembro desde</span>
                                        <strong><?php echo htmlspecialchars($formatDate($estudiante['usuario_creado_en'] ?? null, false)); ?></strong>
                                    </div>
                                    <div class="profile-account-item">
                                        <span>Evaluaciones registradas</span>
                                        <strong><?php echo $evalTotal; ?></strong>
                                    </div>
                                    <div class="profile-account-item">
                                        <span>Pasantia activa</span>
                                        <strong><?php echo !empty($asignacion_activa['empresa_nombre']) ? htmlspecialchars($asignacion_activa['empresa_nombre']) : 'No asignada'; ?></strong>
                                    </div>
                                </div>
                            </section>

                            <section class="card profile-section-card<?php echo $profileTab === 'cv' ? ' section-focus' : ''; ?>" id="profileCvCard">
                                <div class="profile-section-head">
                                    <div>
                                        <div class="profile-section-label">
                                            <i class="fas fa-file-pdf"></i>
                                            Curriculum
                                        </div>
                                        <h2 style="margin-top: 14px;">Gestiona tu CV</h2>
                                        <p>Desde aqui puedes revisar el archivo cargado, sustituirlo o eliminarlo si necesitas subir otra version.</p>
                                    </div>
                                </div>

                                <div class="profile-cv-box">
                                    <div class="profile-cv-head">
                                        <div class="profile-cv-name">
                                            <i class="fas <?php echo $tiene_cv ? 'fa-file-pdf' : 'fa-file-circle-xmark'; ?>"></i>
                                            <?php echo $tiene_cv ? htmlspecialchars($cvFileName) : 'Aun no has subido tu curriculum'; ?>
                                        </div>
                                        <span class="badge <?php echo $tiene_cv ? 'badge-green' : 'badge-info'; ?>">
                                            <?php echo $tiene_cv ? 'Listo' : 'Pendiente'; ?>
                                        </span>
                                    </div>
                                    <div class="profile-help">
                                        <?php if ($tiene_cv): ?>
                                        Ultima actualizacion detectada: <?php echo htmlspecialchars($cvUploadedAt ?? 'Sin fecha'); ?>.
                                        <?php else: ?>
                                        Sube un archivo PDF para que el sistema te permita postularte a empresas.
                                        <?php endif; ?>
                                    </div>

                                    <div class="profile-doc-actions">
                                        <?php if ($tiene_cv): ?>
                                        <a href="ver-cv.php" target="_blank" class="card-btn card-btn-secondary">
                                            <i class="fas fa-eye"></i>
                                            Ver CV
                                        </a>
                                        <?php endif; ?>

                                        <button type="button" class="card-btn btn-green" id="uploadCVButton" onclick="document.getElementById('uploadCV').click()">
                                            <i class="fas fa-upload"></i>
                                            <?php echo $tiene_cv ? 'Actualizar CV' : 'Subir CV'; ?>
                                        </button>

                                        <?php if ($tiene_cv): ?>
                                        <button type="button" class="card-btn card-btn-danger" id="deleteCVButton">
                                            <i class="fas fa-trash"></i>
                                            Eliminar CV
                                        </button>
                                        <?php endif; ?>
                                    </div>

                                    <input type="file" id="uploadCV" accept=".pdf" style="display: none;">
                                </div>

                                <?php if (!empty($estudiante['comentario_cv_admin'])): ?>
                                <div class="profile-feedback-box">
                                    <strong>Comentario del centro:</strong>
                                    <?php echo htmlspecialchars($estudiante['comentario_cv_admin']); ?>
                                    <?php if (!empty($estudiante['fecha_revision_cv'])): ?>
                                    <div style="margin-top: 8px; font-size: 13px;">
                                        Revisado el <?php echo htmlspecialchars($formatDate($estudiante['fecha_revision_cv'], true)); ?>.
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif (!$tiene_cv): ?>
                                <div class="profile-empty-message">
                                    Todavia no hay un curriculum cargado. Apenas subas uno, tambien podras verlo desde aqui.
                                </div>
                                <?php endif; ?>
                            </section>

                            <section class="card profile-section-card">
                                <div class="profile-section-head">
                                    <div>
                                        <div class="profile-section-label">
                                            <i class="fas fa-chart-line"></i>
                                            Resumen rapido
                                        </div>
                                        <h2 style="margin-top: 14px;">Tus numeros clave</h2>
                                        <p>Un vistazo rapido para saber en que punto esta tu perfil ahora mismo.</p>
                                    </div>
                                </div>

                                <div class="profile-small-grid">
                                    <div class="profile-small-metric">
                                        <span>Perfil</span>
                                        <strong><?php echo $profileCompletion; ?>%</strong>
                                    </div>
                                    <div class="profile-small-metric">
                                        <span>CV listo</span>
                                        <strong><?php echo $tiene_cv ? 'Si' : 'No'; ?></strong>
                                    </div>
                                    <div class="profile-small-metric">
                                        <span>Aprobadas</span>
                                        <strong><?php echo $evalAprobadas; ?></strong>
                                    </div>
                                    <div class="profile-small-metric">
                                        <span>Pendientes</span>
                                        <strong><?php echo $evalPendientes; ?></strong>
                                    </div>
                                </div>

                                <div class="profile-note-list">
                                    <a href="index.php?page=student-companies" class="profile-note-chip" style="text-decoration:none;">
                                        <i class="fas fa-building"></i>
                                        Ver empresas disponibles
                                    </a>
                                    <a href="index.php?page=student-results" class="profile-note-chip" style="text-decoration:none;">
                                        <i class="fas fa-clipboard-check"></i>
                                        Revisar evaluaciones
                                    </a>
                                </div>
                            </section>
                        </div>
                    </section>

                    <footer class="footer">
                        <div class="version">EPIC V2.0</div>
                        <p class="copyright">Sistema de Gestion de Pasantias</p>
                        <p class="copyright">&copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
                    </footer>
                </div>
            </div>
        </main>
    </div>

    <script>
        const uploadCVInput = document.getElementById('uploadCV');
        const uploadCVButton = document.getElementById('uploadCVButton');
        const deleteCVButton = document.getElementById('deleteCVButton');
        const profilePhotoInput = document.getElementById('profile_photo');
        const photoFileHelp = document.getElementById('photoFileHelp');
        const focusTargetId = <?php echo json_encode($focusTargetId, JSON_UNESCAPED_UNICODE); ?>;
        const hasRequestedTab = <?php echo json_encode(isset($_GET['tab']) && $_GET['tab'] !== '', JSON_UNESCAPED_UNICODE); ?>;

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

        profilePhotoInput?.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (!file) {
                photoFileHelp.textContent = 'Formatos admitidos: JPG, PNG o WEBP. Tamano maximo: 5 MB.';
                return;
            }

            photoFileHelp.textContent = 'Archivo seleccionado: ' + file.name;

            if (!file.type.startsWith('image/')) {
                return;
            }

            const preview = document.getElementById('profilePhotoPreview');
            if (!preview) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function(loadEvent) {
                preview.innerHTML = '';
                const image = document.createElement('img');
                image.src = loadEvent.target.result;
                image.alt = 'Vista previa de foto de perfil';
                preview.appendChild(image);
            };
            reader.readAsDataURL(file);
        });

        uploadCVInput?.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (!file) {
                return;
            }

            if (file.type !== 'application/pdf') {
                alert('Solo se permiten archivos PDF');
                this.value = '';
                return;
            }

            const buttonText = uploadCVButton ? uploadCVButton.innerHTML : '';
            if (uploadCVButton) {
                uploadCVButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo CV...';
                uploadCVButton.disabled = true;
            }

            const formData = new FormData();
            formData.append('cv', file);

            fetch('index.php?page=student-profile&action=upload_cv', {
                method: 'POST',
                body: formData
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    alert(data.message);
                    if (data.success) {
                        window.location.href = 'index.php?page=student-profile&tab=cv';
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert('Error al conectar con el servidor');
                })
                .finally(() => {
                    if (uploadCVButton) {
                        uploadCVButton.innerHTML = buttonText;
                        uploadCVButton.disabled = false;
                    }
                    uploadCVInput.value = '';
                });
        });

        deleteCVButton?.addEventListener('click', function() {
            if (!window.confirm('Quieres eliminar tu curriculum actual?')) {
                return;
            }

            const originalText = deleteCVButton.innerHTML;
            deleteCVButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando CV...';
            deleteCVButton.disabled = true;

            fetch('index.php?page=student-profile&action=delete_cv', {
                method: 'POST'
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    alert(data.message);
                    if (data.success) {
                        window.location.href = 'index.php?page=student-profile&tab=cv';
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert('Error al conectar con el servidor');
                })
                .finally(() => {
                    deleteCVButton.innerHTML = originalText;
                    deleteCVButton.disabled = false;
                });
        });

        if (window.innerWidth <= 1200) {
            const menuToggle = document.createElement('button');
            menuToggle.className = 'menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.onclick = function() {
                document.querySelector('.sidebar').classList.toggle('active');
            };
            document.body.appendChild(menuToggle);
        }

        if (hasRequestedTab) {
            window.addEventListener('load', function() {
                const focusTarget = document.getElementById(focusTargetId);
                if (focusTarget) {
                    focusTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.querySelector('.menu-toggle');

            if (window.innerWidth <= 1200 &&
                sidebar &&
                sidebar.classList.contains('active') &&
                !sidebar.contains(event.target) &&
                (!menuToggle || !menuToggle.contains(event.target))) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>
