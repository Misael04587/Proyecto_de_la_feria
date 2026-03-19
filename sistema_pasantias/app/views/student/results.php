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

$selectedEvaluationId = (int) ($selected_evaluation_id ?? 0);
$historial = $historial ?? [];
$stats = $stats ?? [];
$statusFilter = $status_filter ?? 'todos';
$filterCounts = $filter_counts ?? [];
$evaluacionDetalle = $evaluacion_detalle ?? null;
$ultimoEventoSeguridad = $ultimo_evento_seguridad ?? null;

$formatDate = function ($value, $withTime = true) {
    if (empty($value)) {
        return 'Sin registrar';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return 'Sin registrar';
    }

    return date($withTime ? 'd/m/Y h:i A' : 'd/m/Y', $timestamp);
};

$stateMeta = [
    'aprobado' => ['label' => 'Aprobado', 'class' => 'state-aprobado', 'icon' => 'fa-circle-check'],
    'reprobado' => ['label' => 'Reprobado', 'class' => 'state-reprobado', 'icon' => 'fa-circle-xmark'],
    'pendiente' => ['label' => 'Pendiente', 'class' => 'state-pendiente', 'icon' => 'fa-clock'],
    'anulado' => ['label' => 'Anulado', 'class' => 'state-anulado', 'icon' => 'fa-shield-halved'],
];

$reviewMeta = [
    'correcta' => ['label' => 'Correcta', 'class' => 'review-correcta', 'icon' => 'fa-check'],
    'incorrecta' => ['label' => 'Incorrecta', 'class' => 'review-incorrecta', 'icon' => 'fa-xmark'],
    'sin_respuesta' => ['label' => 'Sin respuesta', 'class' => 'review-sin-respuesta', 'icon' => 'fa-minus'],
];

$getStateMeta = function ($estado) use ($stateMeta) {
    return $stateMeta[$estado] ?? ['label' => ucfirst((string) $estado), 'class' => 'state-reprobado', 'icon' => 'fa-circle-question'];
};

$getPostulationMeta = function ($evaluacion) {
    if (!empty($evaluacion['asignacion_id'])) {
        $estadoAsignacion = (string) ($evaluacion['asignacion_estado'] ?? 'activa');
        if ($estadoAsignacion === 'finalizada') {
            return ['label' => 'Aplicaste y completaste la pasantia', 'class' => 'post-aprobada', 'icon' => 'fa-flag-checkered'];
        }

        if ($estadoAsignacion === 'cancelada') {
            return ['label' => 'Aplicaste, pero la asignacion fue cancelada', 'class' => 'post-anulada', 'icon' => 'fa-ban'];
        }

        return ['label' => 'Aplicaste y quedaste seleccionado', 'class' => 'post-aprobada', 'icon' => 'fa-award'];
    }

    $estado = (string) ($evaluacion['estado'] ?? '');
    if ($estado === 'pendiente') {
        return ['label' => 'Aplicacion en proceso', 'class' => 'post-pendiente', 'icon' => 'fa-hourglass-half'];
    }

    if ($estado === 'aprobado') {
        $seguimiento = (string) ($evaluacion['seguimiento_estado'] ?? 'sin_revisar');

        if ($seguimiento === 'preseleccionado') {
            return ['label' => 'Aprobaste y quedaste preseleccionado', 'class' => 'post-aprobada', 'icon' => 'fa-award'];
        }

        if ($seguimiento === 'en_revision') {
            return ['label' => 'Aprobaste y tu expediente esta en revision', 'class' => 'post-pendiente', 'icon' => 'fa-hourglass-half'];
        }

        if ($seguimiento === 'descartado') {
            return ['label' => 'Aprobaste el examen, pero coordinacion descarto la postulacion', 'class' => 'post-anulada', 'icon' => 'fa-ban'];
        }

        return ['label' => 'Aprobaste el examen y quedaste pendiente de revision', 'class' => 'post-info', 'icon' => 'fa-star'];
    }

    if ($estado === 'anulado') {
        return ['label' => 'Aplicacion anulada por seguridad', 'class' => 'post-anulada', 'icon' => 'fa-shield-halved'];
    }

    return ['label' => 'Aplicaste, pero no quedaste', 'class' => 'post-reprobada', 'icon' => 'fa-circle-xmark'];
};

$getOptionText = function ($pregunta, $opcion) {
    if (empty($opcion)) {
        return 'Sin respuesta';
    }

    $field = 'opcion_' . strtolower((string) $opcion);
    return $pregunta[$field] ?? 'Opcion no disponible';
};

$formatOptionLabel = function ($pregunta, $opcion) use ($getOptionText) {
    if (empty($opcion)) {
        return 'Sin respuesta';
    }

    return strtoupper((string) $opcion) . '. ' . $getOptionText($pregunta, $opcion);
};

$buildFilterUrl = function ($filterKey) {
    if ($filterKey === 'todos') {
        return 'index.php?page=student-results';
    }

    return 'index.php?page=student-results&status=' . urlencode((string) $filterKey);
};

$closeDetailUrl = $buildFilterUrl($statusFilter);
$reviewQuestions = $evaluacionDetalle['preguntas'] ?? [];
$reviewQuestionCount = count($reviewQuestions);
$reviewStartIndex = 0;
foreach ($reviewQuestions as $questionIndex => $questionItem) {
    if (($questionItem['resultado'] ?? '') !== 'correcta') {
        $reviewStartIndex = $questionIndex;
        break;
    }
}

$preguntasFalladas = [];
if ($evaluacionDetalle) {
    foreach (($evaluacionDetalle['preguntas'] ?? []) as $preguntaItem) {
        if (($preguntaItem['resultado'] ?? '') !== 'correcta') {
            $preguntasFalladas[] = $preguntaItem;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis evaluaciones - Sistema EPIC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/companies.css">
    <style>
        body.modal-open { overflow: hidden; }
        .results-page { display: grid; gap: 24px; }
        .results-hero, .stats-grid, .results-layout, .metric-grid, .review-list, .history-list, .mini-stats, .filter-list, .review-question-nav, .review-option-list { display: grid; gap: 18px; }
        .results-hero, .panel, .empty-state, .history-card, .detail-card, .detail-box, .review-card, .metric-card, .stat-card {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid rgba(26, 54, 93, 0.08);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }
        .results-hero {
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            padding: 28px;
            color: var(--white);
            background: linear-gradient(135deg, #14345d 0%, #3478d9 52%, #68a9ff 100%);
        }
        .results-hero h1, .panel h2, .detail-card h2, .detail-box h3, .empty-state h3 { margin: 0; }
        .results-hero h1 { font-size: 34px; margin-bottom: 8px; }
        .results-hero p, .panel p, .detail-box p, .empty-state p, .history-meta { margin: 0; line-height: 1.7; }
        .results-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 22px;
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid rgba(26, 54, 93, 0.08);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }
        .results-toolbar h2 { margin: 0 0 6px; color: var(--primary); font-size: 21px; }
        .results-toolbar p { margin: 0; color: #64748b; }
        .hero-chip, .badge-pill {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; font-weight: 700; font-size: 13px;
        }
        .hero-chip { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.28); }
        .filter-list {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
        .filter-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 18px;
            text-decoration: none;
            color: var(--primary);
            background: rgba(15, 23, 42, 0.03);
            border: 1px solid rgba(26, 54, 93, 0.08);
            transition: .18s ease;
        }
        .filter-chip:hover {
            transform: translateY(-1px);
            border-color: rgba(66,153,225,.22);
            background: rgba(66,153,225,.05);
        }
        .filter-chip.active {
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            color: var(--white);
            border-color: transparent;
            box-shadow: 0 14px 24px rgba(66,153,225,.24);
        }
        .filter-chip-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }
        .filter-chip-count {
            min-width: 32px;
            height: 32px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.75);
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
        }
        .filter-chip.active .filter-chip-count {
            background: rgba(255,255,255,.18);
            color: var(--white);
        }
        .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .stat-card, .panel, .detail-card { padding: 22px; }
        .stat-head, .panel-head, .history-head, .detail-head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 14px;
        }
        .stat-icon, .panel-count {
            width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800;
        }
        .stat-icon.total { background: rgba(29, 78, 216, 0.12); color: #1d4ed8; }
        .stat-icon.ok { background: rgba(72, 187, 120, 0.12); color: #166534; }
        .stat-icon.fail { background: rgba(245, 101, 101, 0.12); color: #b91c1c; }
        .stat-icon.assign { background: rgba(124, 58, 237, 0.12); color: #6d28d9; }
        .stat-value, .metric-value { color: var(--primary); font-size: 32px; font-weight: 800; line-height: 1; margin: 14px 0 6px; }
        .stat-copy, .metric-label, .detail-subtitle, .review-help { color: #64748b; font-size: 14px; }
        .results-layout { grid-template-columns: 1fr; align-items: start; }
        .history-list { margin-top: 18px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .history-card { display: block; padding: 18px; text-decoration: none; color: inherit; transition: .18s ease; }
        .history-card:hover, .history-card.active { transform: translateY(-2px); border-color: rgba(66,153,225,.22); box-shadow: 0 16px 30px rgba(66,153,225,.14); }
        .history-card.active { background: linear-gradient(180deg, #fff 0%, #eef6ff 100%); }
        .history-title { color: var(--primary); font-size: 20px; font-weight: 800; margin-bottom: 4px; }
        .history-meta { color: #64748b; font-size: 14px; }
        .badge-row, .metric-grid { display: grid; gap: 12px; }
        .badge-row { grid-template-columns: repeat(auto-fit, minmax(190px, max-content)); margin: 14px 0; }
        .badge-pill.state-aprobado, .badge-pill.post-aprobada, .badge-pill.review-correcta { background: rgba(72,187,120,.12); color: #166534; }
        .badge-pill.state-reprobado, .badge-pill.post-reprobada, .badge-pill.review-incorrecta { background: rgba(245,101,101,.12); color: #b91c1c; }
        .badge-pill.state-pendiente, .badge-pill.post-pendiente { background: rgba(237,137,54,.14); color: #c05621; }
        .badge-pill.state-anulado, .badge-pill.post-anulada { background: rgba(124,58,237,.12); color: #6d28d9; }
        .badge-pill.post-info, .badge-pill.review-sin-respuesta { background: rgba(66,153,225,.14); color: #1d4ed8; }
        .mini-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .metric-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .mini-stat, .metric-card { padding: 14px 16px; border-radius: 18px; background: rgba(15, 23, 42, 0.04); }
        .mini-label, .metric-label { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
        .mini-value { color: var(--primary); font-size: 19px; font-weight: 800; }
        .detail-card { display: none; }
        .detail-title { color: var(--primary); font-size: 30px; margin-bottom: 6px; }
        .detail-kicker { color: #64748b; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; font-size: 12px; margin-bottom: 8px; }
        .detail-score { min-width: 150px; padding: 18px; border-radius: 22px; text-align: center; color: var(--white); background: linear-gradient(135deg, #14345d, #4597e8); }
        .detail-score small { display: block; opacity: .82; text-transform: uppercase; letter-spacing: .07em; margin-bottom: 10px; }
        .detail-score strong { font-size: 40px; line-height: 1; }
        .detail-box { padding: 18px; }
        .review-card { padding: 18px; }
        .review-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
        .review-question { color: var(--dark); line-height: 1.7; margin-bottom: 14px; }
        .answer-grid { display: grid; gap: 10px; }
        .answer-box { background: rgba(15, 23, 42, 0.04); border-radius: 16px; padding: 14px; }
        .answer-box strong { display: block; color: var(--primary); font-size: 12px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .security-box { background: rgba(124,58,237,.08); border-color: rgba(124,58,237,.16); }
        .empty-state { padding: 42px 28px; text-align: center; }
        .empty-icon { width: 82px; height: 82px; margin: 0 auto 18px; border-radius: 26px; display: flex; align-items: center; justify-content: center; font-size: 34px; background: rgba(66,153,225,.12); color: var(--secondary); }
        .action-row { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
        .action-btn { display: inline-flex; align-items: center; gap: 10px; padding: 14px 18px; border-radius: 16px; text-decoration: none; font-weight: 700; }
        .action-btn.primary { background: linear-gradient(135deg, #1d4ed8, #60a5fa); color: var(--white); }
        .action-btn.secondary { background: rgba(15, 23, 42, 0.05); color: var(--primary); }
        .detail-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 3500;
            background: rgba(6, 16, 30, 0.66);
            backdrop-filter: blur(10px);
        }
        body.modal-open .detail-card {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3600;
            width: min(1120px, calc(100vw - 24px));
            max-height: calc(100vh - 40px);
            overflow: auto;
            display: grid;
            gap: 18px;
            padding: 22px;
            background:
                radial-gradient(circle at top left, rgba(90, 169, 255, 0.18), transparent 30%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .detail-modal-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .detail-close-btn {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--primary);
            background: rgba(15, 23, 42, 0.05);
            border: 1px solid rgba(26, 54, 93, 0.08);
        }
        .detail-close-btn:hover {
            background: rgba(66,153,225,.08);
        }
        .review-guide-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }
        .review-guide-head p {
            margin: 0;
            color: #64748b;
        }
        .review-question-nav {
            grid-template-columns: repeat(auto-fit, minmax(58px, 1fr));
        }
        .review-question-tab {
            border: none;
            border-radius: 16px;
            padding: 14px 10px;
            font-weight: 800;
            cursor: pointer;
            transition: .18s ease;
            background: rgba(15, 23, 42, 0.05);
            color: var(--primary);
        }
        .review-question-tab:hover {
            transform: translateY(-1px);
        }
        .review-question-tab.active {
            box-shadow: 0 14px 20px rgba(66,153,225,.18);
            outline: 2px solid rgba(66,153,225,.25);
        }
        .review-question-tab.review-correcta { background: rgba(72,187,120,.12); color: #166534; }
        .review-question-tab.review-incorrecta { background: rgba(245,101,101,.12); color: #b91c1c; }
        .review-question-tab.review-sin-respuesta { background: rgba(66,153,225,.14); color: #1d4ed8; }
        .review-question-tab.active.review-correcta { background: linear-gradient(135deg, #15803d, #4ade80); color: #fff; }
        .review-question-tab.active.review-incorrecta { background: linear-gradient(135deg, #dc2626, #fb7185); color: #fff; }
        .review-question-tab.active.review-sin-respuesta { background: linear-gradient(135deg, #1d4ed8, #60a5fa); color: #fff; }
        .review-question-stage {
            position: relative;
            min-height: 430px;
        }
        .review-question-slide {
            display: none;
            padding: 22px;
            border-radius: 22px;
            border: 1px solid rgba(26, 54, 93, 0.08);
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }
        .review-question-slide.active {
            display: block;
        }
        .review-option-list {
            margin-top: 18px;
        }
        .review-option-row {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(26, 54, 93, 0.08);
            background: rgba(15, 23, 42, 0.03);
        }
        .review-option-row.is-selected {
            border-color: rgba(66,153,225,.26);
            background: rgba(66,153,225,.08);
        }
        .review-option-row.is-correct {
            border-color: rgba(72,187,120,.24);
            background: rgba(72,187,120,.08);
        }
        .review-option-row.is-selected-wrong {
            border-color: rgba(245,101,101,.28);
            background: rgba(245,101,101,.08);
        }
        .review-option-main, .review-option-tags, .review-pager {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .review-option-main {
            justify-content: space-between;
        }
        .review-option-label {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--dark);
            line-height: 1.7;
        }
        .review-option-letter {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: var(--primary);
            background: rgba(15, 23, 42, 0.06);
            flex-shrink: 0;
        }
        .review-mini-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .review-mini-tag.correct {
            background: rgba(72,187,120,.14);
            color: #166534;
        }
        .review-mini-tag.selected {
            background: rgba(66,153,225,.14);
            color: #1d4ed8;
        }
        .review-mini-tag.wrong {
            background: rgba(245,101,101,.14);
            color: #b91c1c;
        }
        .review-pager {
            justify-content: space-between;
            margin-top: 16px;
        }
        .review-pager-btn {
            border: none;
            border-radius: 16px;
            padding: 13px 18px;
            font-weight: 800;
            cursor: pointer;
            background: rgba(15, 23, 42, 0.05);
            color: var(--primary);
        }
        .review-pager-btn.primary {
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            color: #fff;
        }
        .review-pager-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }
        @media (max-width: 1200px) { .stats-grid, .mini-stats, .metric-grid, .filter-list { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 768px) {
            .results-hero, .stats-grid, .mini-stats, .metric-grid, .filter-list, .history-list { grid-template-columns: 1fr; }
            .results-hero, .detail-head, .panel-head, .history-head, .results-toolbar, .review-guide-head, .detail-modal-top { display: grid; }
            .detail-title { font-size: 25px; }
            body.modal-open .detail-card { top: 10px; width: calc(100vw - 16px); max-height: calc(100vh - 20px); padding: 16px; }
        }
    </style>
</head>
<body<?php echo $evaluacionDetalle ? ' class="modal-open"' : ''; ?>>
    <div class="app-container">
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
                    <p class="sidebar-subtitle">Sistema de Gestion de Pasantias</p>
                </div>
            </div>

            <nav class="nav-menu">
                <div class="nav-title">Navegacion</div>
                <a href="index.php?page=student-dashboard" class="nav-item"><i class="fas fa-home nav-icon"></i>Inicio</a>
                <a href="index.php?page=student-companies" class="nav-item"><i class="fas fa-building nav-icon"></i>Empresas disponibles</a>
                <a href="index.php?page=student-results" class="nav-item active"><i class="fas fa-clipboard-check nav-icon"></i>Mis evaluaciones</a>
                <a href="index.php?page=student-profile" class="nav-item"><i class="fas fa-user nav-icon"></i>Mi perfil</a>
            </nav>

            <div class="sidebar-footer">
                <a href="index.php?page=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i>Cerrar sesion</a>
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
                            <div class="dropdown-user-email"><?php echo htmlspecialchars($estudiante['correo'] ?? ''); ?></div>
                        </div>

                        <div class="dropdown-menu">
                            <a href="index.php?page=student-dashboard" class="dropdown-item"><i class="fas fa-house"></i>Dashboard</a>
                            <a href="index.php?page=student-companies" class="dropdown-item"><i class="fas fa-building"></i>Empresas disponibles</a>
                            <a href="index.php?page=student-results" class="dropdown-item"><i class="fas fa-chart-bar"></i>Mis evaluaciones</a>
                            <div class="dropdown-divider"></div>
                            <a href="index.php?page=logout" class="dropdown-logout"><i class="fas fa-sign-out-alt"></i>Cerrar sesion</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-wrapper results-page">
                <section class="results-hero">
                    <div>
                        <h1>Mis evaluaciones</h1>
                        <p>Revisa cada examen que has hecho, mira si la postulacion avanzo y detecta exactamente en que preguntas fallaste.</p>
                    </div>
                    <div class="hero-chip">
                        <i class="fas fa-id-card"></i>
                        Matricula <?php echo htmlspecialchars($estudiante['matricula'] ?? ''); ?>
                    </div>
                </section>

                <section class="stats-grid">
                    <article class="stat-card">
                        <div class="stat-head">
                            <div>
                                <strong>Total de intentos</strong>
                                <div class="stat-copy">Evaluaciones registradas en tu cuenta.</div>
                            </div>
                            <div class="stat-icon total"><i class="fas fa-layer-group"></i></div>
                        </div>
                        <div class="stat-value"><?php echo (int) ($stats['total'] ?? 0); ?></div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-head">
                            <div>
                                <strong>Aprobadas</strong>
                                <div class="stat-copy">Intentos que superaron la nota minima.</div>
                            </div>
                            <div class="stat-icon ok"><i class="fas fa-circle-check"></i></div>
                        </div>
                        <div class="stat-value"><?php echo (int) ($stats['aprobadas'] ?? 0); ?></div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-head">
                            <div>
                                <strong>No superadas</strong>
                                <div class="stat-copy">Suma de reprobadas y anuladas.</div>
                            </div>
                            <div class="stat-icon fail"><i class="fas fa-triangle-exclamation"></i></div>
                        </div>
                        <div class="stat-value"><?php echo (int) (($stats['reprobadas'] ?? 0) + ($stats['anuladas'] ?? 0)); ?></div>
                    </article>

                    <article class="stat-card">
                        <div class="stat-head">
                            <div>
                                <strong>Asignadas</strong>
                                <div class="stat-copy">Postulaciones que terminaron en asignacion.</div>
                            </div>
                            <div class="stat-icon assign"><i class="fas fa-award"></i></div>
                        </div>
                        <div class="stat-value"><?php echo (int) ($stats['asignadas'] ?? 0); ?></div>
                    </article>
                </section>

                <?php if (($stats['total'] ?? 0) === 0): ?>
                <section class="empty-state">
                    <div class="empty-icon"><i class="fas fa-clipboard-list"></i></div>
                    <h3>Aun no tienes evaluaciones</h3>
                    <p>Cuando completes un examen de postulacion, aqui veras tu nota, el estado de la empresa y el detalle de las preguntas.</p>
                    <div class="action-row">
                        <a href="index.php?page=student-companies" class="action-btn primary"><i class="fas fa-building"></i>Ver empresas</a>
                        <a href="index.php?page=student-dashboard" class="action-btn secondary"><i class="fas fa-house"></i>Volver al dashboard</a>
                    </div>
                </section>
                <?php else: ?>
                <section class="results-toolbar">
                    <div>
                        <h2>Filtra tus resultados</h2>
                        <p>Elige una categoria y deja visible solo lo que quieras revisar.</p>
                    </div>
                    <div class="hero-chip" style="background: rgba(66,153,225,.08); border-color: rgba(66,153,225,.12); color: var(--primary);">
                        <i class="fas fa-filter"></i>
                        Mostrando <?php echo count($historial); ?> de <?php echo (int) ($stats['total'] ?? 0); ?>
                    </div>
                </section>

                <section class="filter-list">
                    <a href="<?php echo htmlspecialchars($buildFilterUrl('todos')); ?>" class="filter-chip<?php echo $statusFilter === 'todos' ? ' active' : ''; ?>">
                        <span class="filter-chip-label"><i class="fas fa-layer-group"></i>Todos</span>
                        <span class="filter-chip-count"><?php echo (int) ($filterCounts['todos'] ?? 0); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars($buildFilterUrl('aprobado')); ?>" class="filter-chip<?php echo $statusFilter === 'aprobado' ? ' active' : ''; ?>">
                        <span class="filter-chip-label"><i class="fas fa-circle-check"></i>Aprobadas</span>
                        <span class="filter-chip-count"><?php echo (int) ($filterCounts['aprobado'] ?? 0); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars($buildFilterUrl('reprobado')); ?>" class="filter-chip<?php echo $statusFilter === 'reprobado' ? ' active' : ''; ?>">
                        <span class="filter-chip-label"><i class="fas fa-circle-xmark"></i>Reprobadas</span>
                        <span class="filter-chip-count"><?php echo (int) ($filterCounts['reprobado'] ?? 0); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars($buildFilterUrl('pendiente')); ?>" class="filter-chip<?php echo $statusFilter === 'pendiente' ? ' active' : ''; ?>">
                        <span class="filter-chip-label"><i class="fas fa-clock"></i>Pendientes</span>
                        <span class="filter-chip-count"><?php echo (int) ($filterCounts['pendiente'] ?? 0); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars($buildFilterUrl('anulado')); ?>" class="filter-chip<?php echo $statusFilter === 'anulado' ? ' active' : ''; ?>">
                        <span class="filter-chip-label"><i class="fas fa-shield-halved"></i>Anuladas</span>
                        <span class="filter-chip-count"><?php echo (int) ($filterCounts['anulado'] ?? 0); ?></span>
                    </a>
                    <a href="<?php echo htmlspecialchars($buildFilterUrl('asignada')); ?>" class="filter-chip<?php echo $statusFilter === 'asignada' ? ' active' : ''; ?>">
                        <span class="filter-chip-label"><i class="fas fa-award"></i>Asignadas</span>
                        <span class="filter-chip-count"><?php echo (int) ($filterCounts['asignada'] ?? 0); ?></span>
                    </a>
                </section>

                <?php if (empty($historial)): ?>
                <section class="empty-state">
                    <div class="empty-icon"><i class="fas fa-filter-circle-xmark"></i></div>
                    <h3>No hay evaluaciones en este filtro</h3>
                    <p>Prueba con otra categoria o vuelve a ver todas tus evaluaciones.</p>
                    <div class="action-row">
                        <a href="index.php?page=student-results" class="action-btn primary"><i class="fas fa-layer-group"></i>Ver todas</a>
                        <a href="index.php?page=student-companies" class="action-btn secondary"><i class="fas fa-building"></i>Ir a empresas</a>
                    </div>
                </section>
                <?php else: ?>
                <section class="results-layout">
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <h2>Lista de evaluaciones</h2>
                                <p>Haz clic en una tarjeta para ver que paso y en que necesitas mejorar.</p>
                            </div>
                            <div class="panel-count"><?php echo count($historial); ?></div>
                        </div>

                        <div class="history-list">
                            <?php foreach ($historial as $evaluacionItem): ?>
                            <?php
                            $isActive = (int) $evaluacionItem['id'] === $selectedEvaluationId;
                            $statusInfo = $getStateMeta((string) ($evaluacionItem['estado'] ?? ''));
                            $postulationInfo = $getPostulationMeta($evaluacionItem);
                            $historyUrl = 'index.php?page=student-results&evaluation=' . (int) $evaluacionItem['id'];
                            if ($statusFilter !== 'todos') {
                                $historyUrl .= '&status=' . urlencode((string) $statusFilter);
                            }
                            ?>
                            <a href="<?php echo htmlspecialchars($historyUrl); ?>" class="history-card<?php echo $isActive ? ' active' : ''; ?>">
                                <div class="history-head">
                                    <div>
                                        <div class="history-title"><?php echo htmlspecialchars($evaluacionItem['empresa_nombre'] ?? 'Empresa'); ?></div>
                                        <div class="history-meta">
                                            <?php echo htmlspecialchars($evaluacionItem['area_tecnica'] ?? ''); ?>
                                            <?php if (!empty($evaluacionItem['direccion'])): ?>
                                                <?php echo ' - ' . htmlspecialchars($evaluacionItem['direccion']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right" style="color: #94a3b8;"></i>
                                </div>

                                <div class="badge-row">
                                    <span class="badge-pill <?php echo htmlspecialchars($statusInfo['class']); ?>">
                                        <i class="fas <?php echo htmlspecialchars($statusInfo['icon']); ?>"></i>
                                        <?php echo htmlspecialchars($statusInfo['label']); ?>
                                    </span>
                                    <span class="badge-pill <?php echo htmlspecialchars($postulationInfo['class']); ?>">
                                        <i class="fas <?php echo htmlspecialchars($postulationInfo['icon']); ?>"></i>
                                        <?php echo htmlspecialchars($postulationInfo['label']); ?>
                                    </span>
                                </div>

                                <div class="mini-stats">
                                    <div class="mini-stat">
                                        <div class="mini-label">Nota</div>
                                        <div class="mini-value"><?php echo $evaluacionItem['nota'] !== null ? number_format((float) $evaluacionItem['nota'], 2) . '%' : 'Pendiente'; ?></div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-label">Correctas</div>
                                        <div class="mini-value"><?php echo (int) ($evaluacionItem['correctas'] ?? 0); ?>/<?php echo (int) ($evaluacionItem['total_preguntas'] ?? 0); ?></div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-label">Fecha</div>
                                        <div class="mini-value" style="font-size:16px;"><?php echo htmlspecialchars($formatDate($evaluacionItem['tiempo_fin'] ?? $evaluacionItem['created_at'] ?? null, false)); ?></div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($evaluacionDetalle): ?>
                    <a href="<?php echo htmlspecialchars($closeDetailUrl); ?>" class="detail-modal-backdrop" aria-label="Cerrar detalle"></a>
                    <?php endif; ?>

                    <div class="detail-card">
                        <?php if ($evaluacionDetalle): ?>
                        <?php
                        $detailState = $getStateMeta((string) ($evaluacionDetalle['estado'] ?? ''));
                        $detailPostulation = $getPostulationMeta($evaluacionDetalle);
                        $sinRespuestaCount = max(0, (int) ($evaluacionDetalle['total_preguntas'] ?? 0) - (int) ($evaluacionDetalle['respondidas'] ?? 0));
                        ?>
                        <div class="detail-modal-top">
                            <div class="detail-kicker">Revision guiada del examen</div>
                            <a href="<?php echo htmlspecialchars($closeDetailUrl); ?>" class="detail-close-btn" id="closeReviewModal" aria-label="Cerrar revision">
                                <i class="fas fa-xmark"></i>
                            </a>
                        </div>
                        <div class="detail-head">
                            <div>
                                <h2 class="detail-title"><?php echo htmlspecialchars($evaluacionDetalle['empresa_nombre'] ?? 'Empresa'); ?></h2>
                                <p class="detail-subtitle">Aqui ves el resultado del examen y la situacion de tu proceso con esta empresa.</p>
                                <div class="badge-row">
                                    <span class="badge-pill <?php echo htmlspecialchars($detailState['class']); ?>">
                                        <i class="fas <?php echo htmlspecialchars($detailState['icon']); ?>"></i>
                                        <?php echo htmlspecialchars($detailState['label']); ?>
                                    </span>
                                    <span class="badge-pill <?php echo htmlspecialchars($detailPostulation['class']); ?>">
                                        <i class="fas <?php echo htmlspecialchars($detailPostulation['icon']); ?>"></i>
                                        <?php echo htmlspecialchars($detailPostulation['label']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="detail-score">
                                <small>Nota final</small>
                                <strong><?php echo $evaluacionDetalle['nota'] !== null ? number_format((float) $evaluacionDetalle['nota'], 0) . '%' : '--'; ?></strong>
                            </div>
                        </div>

                        <div class="metric-grid">
                            <div class="metric-card">
                                <div class="metric-label">Correctas</div>
                                <div class="metric-value"><?php echo (int) ($evaluacionDetalle['correctas'] ?? 0); ?></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Incorrectas</div>
                                <div class="metric-value"><?php echo (int) ($evaluacionDetalle['incorrectas'] ?? 0); ?></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Sin responder</div>
                                <div class="metric-value"><?php echo $sinRespuestaCount; ?></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Fecha de cierre</div>
                                <div class="metric-value" style="font-size:16px;"><?php echo htmlspecialchars($formatDate($evaluacionDetalle['tiempo_fin'] ?? $evaluacionDetalle['created_at'] ?? null, true)); ?></div>
                            </div>
                        </div>

                        <div class="detail-box">
                            <h3>Que paso con tu postulacion</h3>
                            <p>
                                <?php echo htmlspecialchars($detailPostulation['label']); ?>.
                                <?php if (!empty($evaluacionDetalle['fecha_asignacion'])): ?>
                                    La asignacion quedo registrada el <?php echo htmlspecialchars($formatDate($evaluacionDetalle['fecha_asignacion'], false)); ?>.
                                <?php elseif (($evaluacionDetalle['estado'] ?? '') === 'aprobado'): ?>
                                    Tu aprobacion quedo lista y ahora el centro debe revisar si te asigna a esta empresa.
                                    <?php if (!empty($evaluacionDetalle['seguimiento_comentario'])): ?>
                                        Comentario del coordinador: <?php echo htmlspecialchars($evaluacionDetalle['seguimiento_comentario']); ?>.
                                    <?php endif; ?>
                                <?php elseif (($evaluacionDetalle['estado'] ?? '') === 'reprobado'): ?>
                                    Necesitas mejorar el examen para avanzar a la empresa.
                                <?php elseif (($evaluacionDetalle['estado'] ?? '') === 'pendiente'): ?>
                                    Este intento todavia no se ha cerrado.
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if ($ultimoEventoSeguridad): ?>
                        <div class="detail-box security-box">
                            <h3>Evento de seguridad</h3>
                            <p><?php echo htmlspecialchars($ultimoEventoSeguridad['detalles'] ?? 'Se detecto una incidencia de seguridad durante la evaluacion.'); ?></p>
                            <p>Registrado el <?php echo htmlspecialchars($formatDate($ultimoEventoSeguridad['created_at'] ?? null, true)); ?>.</p>
                        </div>
                        <?php endif; ?>

                        <div class="detail-box">
                            <h3>Lo que necesitas mejorar</h3>
                            <?php if (($evaluacionDetalle['estado'] ?? '') === 'pendiente'): ?>
                            <p>Primero debes terminar este examen para que el sistema calcule tus errores y aciertos.</p>
                            <?php else: ?>
                            <div class="review-guide-head">
                                <div>
                                    <p class="review-help">Usa los numeros para moverte entre preguntas. Cada una te muestra las 4 opciones, la que elegiste y la correcta.</p>
                                </div>
                                <div class="badge-pill <?php echo empty($preguntasFalladas) ? 'review-correcta' : 'review-incorrecta'; ?>">
                                    <i class="fas <?php echo empty($preguntasFalladas) ? 'fa-circle-check' : 'fa-circle-info'; ?>"></i>
                                    <?php echo empty($preguntasFalladas) ? 'Examen perfecto' : count($preguntasFalladas) . ' preguntas por revisar'; ?>
                                </div>
                            </div>

                            <?php if (!empty($reviewQuestions)): ?>
                            <div class="review-question-nav" id="reviewQuestionNav">
                                <?php foreach ($reviewQuestions as $questionIndex => $preguntaItem): ?>
                                <?php $reviewInfo = $reviewMeta[$preguntaItem['resultado']] ?? $reviewMeta['incorrecta']; ?>
                                <button
                                    type="button"
                                    class="review-question-tab <?php echo htmlspecialchars($reviewInfo['class']); ?><?php echo $questionIndex === $reviewStartIndex ? ' active' : ''; ?>"
                                    data-review-tab="<?php echo (int) $questionIndex; ?>"
                                >
                                    <?php echo (int) $preguntaItem['orden']; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="review-question-stage">
                                <?php foreach ($reviewQuestions as $questionIndex => $preguntaItem): ?>
                                <?php
                                $reviewInfo = $reviewMeta[$preguntaItem['resultado']] ?? $reviewMeta['incorrecta'];
                                $selectedOption = strtolower((string) ($preguntaItem['respuesta_estudiante'] ?? ''));
                                $correctOption = strtolower((string) ($preguntaItem['respuesta_correcta'] ?? ''));
                                ?>
                                <article class="review-question-slide<?php echo $questionIndex === $reviewStartIndex ? ' active' : ''; ?>" data-review-slide="<?php echo (int) $questionIndex; ?>">
                                    <div class="review-head">
                                        <strong>Pregunta <?php echo (int) $preguntaItem['orden']; ?> de <?php echo $reviewQuestionCount; ?></strong>
                                        <span class="badge-pill <?php echo htmlspecialchars($reviewInfo['class']); ?>">
                                            <i class="fas <?php echo htmlspecialchars($reviewInfo['icon']); ?>"></i>
                                            <?php echo htmlspecialchars($reviewInfo['label']); ?>
                                        </span>
                                    </div>
                                    <div class="review-question"><?php echo htmlspecialchars($preguntaItem['pregunta'] ?? ''); ?></div>

                                    <div class="review-option-list">
                                        <?php foreach (['a', 'b', 'c', 'd'] as $opcion): ?>
                                        <?php
                                        $optionClasses = ['review-option-row'];
                                        if ($opcion === $selectedOption) {
                                            $optionClasses[] = 'is-selected';
                                        }
                                        if ($opcion === $correctOption) {
                                            $optionClasses[] = 'is-correct';
                                        }
                                        if ($opcion === $selectedOption && $selectedOption !== $correctOption) {
                                            $optionClasses[] = 'is-selected-wrong';
                                        }
                                        $optionField = 'opcion_' . $opcion;
                                        ?>
                                        <div class="<?php echo htmlspecialchars(implode(' ', $optionClasses)); ?>">
                                            <div class="review-option-main">
                                                <div class="review-option-label">
                                                    <span class="review-option-letter"><?php echo strtoupper($opcion); ?></span>
                                                    <span><?php echo htmlspecialchars($preguntaItem[$optionField] ?? 'Opcion no disponible'); ?></span>
                                                </div>
                                                <div class="review-option-tags">
                                                    <?php if ($opcion === $selectedOption): ?>
                                                    <span class="review-mini-tag <?php echo $selectedOption === $correctOption ? 'correct' : 'selected'; ?>">
                                                        <i class="fas fa-hand-pointer"></i>
                                                        Tu respuesta
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($opcion === $correctOption): ?>
                                                    <span class="review-mini-tag correct">
                                                        <i class="fas fa-check"></i>
                                                        Correcta
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($opcion === $selectedOption && $selectedOption !== $correctOption): ?>
                                                    <span class="review-mini-tag wrong">
                                                        <i class="fas fa-xmark"></i>
                                                        Debias marcar otra
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </article>
                                <?php endforeach; ?>
                            </div>
                            <div class="review-pager">
                                <button type="button" class="review-pager-btn" id="reviewPrevBtn">Anterior</button>
                                <button type="button" class="review-pager-btn primary" id="reviewNextBtn">Siguiente</button>
                            </div>
                            <?php else: ?>
                            <p>No hay preguntas disponibles para mostrar en esta revision.</p>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-magnifying-glass"></i></div>
                            <h3>No se encontro ese intento</h3>
                            <p>Selecciona una evaluacion valida del historial para ver su detalle completo.</p>
                            <div class="action-row">
                                <a href="index.php?page=student-results" class="action-btn primary"><i class="fas fa-rotate-right"></i>Recargar historial</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>
                <?php endif; ?>

                <footer class="footer">
                    <div class="version">EPIC V2.0</div>
                    <p class="copyright">Sistema Integral de Gestion de Pasantias</p>
                    <p class="copyright">&copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
                </footer>
            </div>
        </main>
    </div>

    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown) {
                dropdown.classList.toggle('active');
            }
        }

        document.addEventListener('click', function (event) {
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

        (function () {
            const tabs = Array.from(document.querySelectorAll('[data-review-tab]'));
            const slides = Array.from(document.querySelectorAll('[data-review-slide]'));
            const prevBtn = document.getElementById('reviewPrevBtn');
            const nextBtn = document.getElementById('reviewNextBtn');
            const closeHref = <?php echo json_encode($closeDetailUrl, JSON_UNESCAPED_UNICODE); ?>;

            if (!tabs.length || !slides.length) {
                if (document.body.classList.contains('modal-open')) {
                    window.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            window.location.href = closeHref;
                        }
                    });
                }
                return;
            }

            let activeIndex = tabs.findIndex((tab) => tab.classList.contains('active'));
            if (activeIndex < 0) {
                activeIndex = 0;
            }

            function paintActive(index) {
                activeIndex = index;

                tabs.forEach(function (tab, tabIndex) {
                    tab.classList.toggle('active', tabIndex === activeIndex);
                });

                slides.forEach(function (slide, slideIndex) {
                    slide.classList.toggle('active', slideIndex === activeIndex);
                });

                if (prevBtn) {
                    prevBtn.disabled = activeIndex === 0;
                }

                if (nextBtn) {
                    nextBtn.textContent = activeIndex === tabs.length - 1 ? 'Listo' : 'Siguiente';
                }
            }

            tabs.forEach(function (tab, tabIndex) {
                tab.addEventListener('click', function () {
                    paintActive(tabIndex);
                });
            });

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    if (activeIndex > 0) {
                        paintActive(activeIndex - 1);
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    if (activeIndex < tabs.length - 1) {
                        paintActive(activeIndex + 1);
                        return;
                    }

                    window.location.href = closeHref;
                });
            }

            if (document.body.classList.contains('modal-open')) {
                window.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        window.location.href = closeHref;
                    }
                });
            }

            paintActive(activeIndex);
        })();

        if (window.innerWidth <= 1200) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.createElement('button');
            menuToggle.className = 'menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.style.cssText = 'position:fixed;top:25px;left:25px;z-index:1001;background:var(--primary);color:white;border:none;width:50px;height:50px;border-radius:10px;cursor:pointer;font-size:22px;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
            menuToggle.onclick = function () {
                sidebar.classList.toggle('active');
                sidebar.style.transform = sidebar.classList.contains('active') ? 'translateX(0)' : 'translateX(-100%)';
            };
            document.body.appendChild(menuToggle);
        }
    </script>
</body>
</html>
