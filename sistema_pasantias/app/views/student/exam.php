<?php
$foto_perfil = $estudiante['foto_perfil'] ?? '';
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

$questionTotal = count($preguntas ?? []);
$answeredCount = 0;
foreach (($preguntas ?? []) as $preguntaItem) {
    if (!empty($preguntaItem['respuesta_estudiante'])) {
        $answeredCount++;
    }
}

$resultado = $resultado ?? null;
$ultimo_evento_seguridad = $ultimo_evento_seguridad ?? null;
$estadoResultado = $resultado['estado'] ?? ($evaluacion['estado'] ?? '');
$notaResultado = isset($resultado['nota']) ? number_format((float) $resultado['nota'], 2) : null;
$correctasResultado = (int) ($resultado['correctas'] ?? 0);
$totalResultado = (int) ($resultado['total'] ?? 0);
$empresaNombre = $evaluacion['empresa_nombre'] ?? ($resultado['empresa_nombre'] ?? 'Empresa');
$areaTecnica = $evaluacion['area_tecnica'] ?? ($resultado['area_tecnica'] ?? ($estudiante['area_tecnica'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examen tecnico - Sistema EPIC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #07111f;
            --panel: #0f1c2f;
            --line: rgba(255, 255, 255, 0.08);
            --text: #edf2f7;
            --muted: #94a3b8;
            --primary: #5aa9ff;
            --primary-strong: #2676ff;
            --danger: #ff5d73;
            --danger-soft: rgba(255, 93, 115, 0.14);
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(38, 118, 255, 0.22), transparent 32%),
                radial-gradient(circle at top right, rgba(255, 93, 115, 0.2), transparent 28%),
                linear-gradient(180deg, #091321 0%, #050b14 100%);
            color: var(--text);
        }

        .exam-shell {
            max-width: 1240px;
            margin: 0 auto;
            padding: 28px 20px 48px;
        }

        .topbar,
        .brand-block,
        .student-chip,
        .hero-meta,
        .warning-card,
        .submit-bar,
        .result-actions,
        .question-top {
            display: flex;
            align-items: center;
        }

        .topbar,
        .submit-bar,
        .question-top {
            justify-content: space-between;
        }

        .topbar {
            gap: 18px;
            margin-bottom: 26px;
        }

        .brand-block,
        .student-chip,
        .hero-meta,
        .warning-card,
        .result-actions {
            gap: 14px;
        }

        .brand-mark {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(90, 169, 255, 0.22), rgba(255, 93, 115, 0.22));
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18);
            font-size: 24px;
        }

        .brand-copy h1,
        .hero-card h2,
        .result-title,
        .summary-card h3 {
            margin: 0;
        }

        .brand-copy h1 {
            margin-bottom: 6px;
            font-size: 30px;
            line-height: 1;
        }

        .brand-copy p,
        .hero-card p,
        .timer-note,
        .result-text,
        .security-log p,
        .warning-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.65;
        }

        .student-chip,
        .hero-card,
        .timer-card,
        .warning-card,
        .question-card,
        .result-card,
        .summary-card,
        .submit-bar {
            background: rgba(15, 28, 47, 0.84);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
            backdrop-filter: blur(12px);
        }

        .student-chip {
            padding: 12px 16px;
        }

        .avatar,
        .avatar-fallback {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-strong), #7b61ff);
            color: var(--white);
            font-size: 22px;
            font-weight: 800;
        }

        .student-meta strong {
            display: block;
            margin-bottom: 4px;
            font-size: 15px;
        }

        .student-meta span,
        .timer-label,
        .question-status,
        .summary-label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero,
        .result-layout {
            display: grid;
            gap: 20px;
        }

        .hero {
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.65fr);
            margin-bottom: 22px;
        }

        .hero-card,
        .timer-card,
        .result-card,
        .summary-card {
            padding: 24px;
        }

        .hero-card h2 {
            margin-bottom: 12px;
            font-size: 28px;
        }

        .hero-meta {
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .hero-pill,
        .question-badge,
        .result-state {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .hero-pill,
        .question-badge {
            background: rgba(90, 169, 255, 0.12);
            border: 1px solid rgba(90, 169, 255, 0.2);
            color: #bfe1ff;
        }

        .timer-card {
            position: relative;
            overflow: hidden;
        }

        .timer-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 93, 115, 0.14), transparent 60%);
            pointer-events: none;
        }

        .timer-label {
            margin-bottom: 10px;
        }

        .timer-value {
            font-size: 48px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 10px;
        }

        .warning-card,
        .submit-bar,
        .question-card,
        .result-card,
        .summary-card {
            padding: 20px;
        }

        .warning-card {
            margin-bottom: 22px;
            align-items: flex-start;
            background: linear-gradient(180deg, rgba(255, 93, 115, 0.18), rgba(255, 93, 115, 0.08));
        }

        .warning-card i {
            color: var(--danger);
            font-size: 22px;
            margin-top: 2px;
        }

        .warning-card strong,
        .submit-copy strong {
            display: block;
            margin-bottom: 6px;
        }

        .progress-bar {
            width: 100%;
            height: 9px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            overflow: hidden;
            margin-top: 16px;
        }

        .progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--primary), #8dd6ff);
            transition: width 0.2s ease;
        }

        .question-grid,
        .option-list,
        .summary-list {
            display: grid;
            gap: 18px;
        }

        .question-text {
            margin: 0 0 16px;
            font-size: 19px;
            line-height: 1.6;
        }

        .option-card {
            position: relative;
            display: block;
            cursor: pointer;
        }

        .option-card input {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .option-body {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }

        .option-card:hover .option-body {
            transform: translateY(-1px);
            border-color: rgba(90, 169, 255, 0.26);
        }

        .option-card input:checked + .option-body {
            background: rgba(90, 169, 255, 0.14);
            border-color: rgba(90, 169, 255, 0.4);
            box-shadow: 0 0 0 1px rgba(90, 169, 255, 0.12);
        }

        .option-letter {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.08);
        }

        .option-text {
            line-height: 1.55;
        }

        .submit-bar {
            position: sticky;
            bottom: 18px;
            margin-top: 24px;
            gap: 16px;
            background: rgba(5, 11, 20, 0.92);
        }

        .submit-copy span {
            color: var(--muted);
            font-size: 13px;
        }

        .submit-btn,
        .result-btn {
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            border-radius: 16px;
            font-weight: 800;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .submit-btn {
            min-width: 240px;
            padding: 16px 22px;
            background: linear-gradient(135deg, var(--primary), var(--primary-strong));
            color: var(--white);
            box-shadow: 0 16px 30px rgba(38, 118, 255, 0.22);
        }

        .submit-btn:hover,
        .result-btn:hover {
            transform: translateY(-2px);
        }

        .result-layout {
            grid-template-columns: minmax(0, 1fr) minmax(300px, 380px);
        }

        .result-title {
            margin: 18px 0 12px;
            font-size: 34px;
            line-height: 1.1;
        }

        .result-state.aprobado {
            background: rgba(79, 209, 165, 0.14);
            color: #b8ffea;
        }

        .result-state.reprobado {
            background: rgba(246, 173, 85, 0.16);
            color: #ffe2b5;
        }

        .result-state.anulado {
            background: rgba(255, 93, 115, 0.16);
            color: #ffd9df;
        }

        .result-actions {
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .result-btn {
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .result-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-strong));
            color: var(--white);
            border: none;
        }

        .summary-item {
            padding: 14px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .summary-item:first-child {
            border-top: none;
            padding-top: 0;
        }

        .summary-value {
            font-size: 19px;
            font-weight: 800;
        }

        .security-log {
            margin-top: 18px;
            padding: 16px;
            border-radius: 18px;
            background: var(--danger-soft);
            border: 1px solid rgba(255, 93, 115, 0.18);
            color: #ffd9df;
        }

        .security-log strong {
            display: block;
            margin-bottom: 6px;
        }

        @media (max-width: 980px) {
            .hero,
            .result-layout {
                grid-template-columns: 1fr;
            }

            .topbar,
            .submit-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .submit-btn {
                width: 100%;
                min-width: 0;
            }
        }
    </style>
</head>
<body>
    <div class="exam-shell">
        <div class="topbar">
            <div class="brand-block">
                <div class="brand-mark">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="brand-copy">
                    <h1>Examen tecnico</h1>
                    <p>Modo estricto activado para la postulacion a <strong><?php echo htmlspecialchars($empresaNombre); ?></strong>.</p>
                </div>
            </div>

            <div class="student-chip">
                <?php if ($hasProfilePhoto): ?>
                <div class="avatar">
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="<?php echo htmlspecialchars($estudiante['nombre'] ?? 'Usuario'); ?>">
                </div>
                <?php else: ?>
                <div class="avatar-fallback"><?php echo htmlspecialchars($avatarInitial); ?></div>
                <?php endif; ?>
                <div class="student-meta">
                    <strong><?php echo htmlspecialchars($estudiante['nombre'] ?? ''); ?></strong>
                    <span><?php echo htmlspecialchars($areaTecnica); ?></span>
                </div>
            </div>
        </div>

        <?php if ($exam_mode === 'active'): ?>
        <div class="hero">
            <section class="hero-card">
                <h2>Demuestra que estas listo para entrar</h2>
                <p>
                    Tienes <?php echo Evaluacion::DURACION_MINUTOS; ?> minutos para responder <?php echo $questionTotal; ?> preguntas.
                    Cambiar de pestana, minimizar, recargar o cerrar esta pagina anula el examen automaticamente.
                </p>
                <div class="hero-meta">
                    <span class="hero-pill"><i class="fas fa-building"></i> <?php echo htmlspecialchars($empresaNombre); ?></span>
                    <span class="hero-pill"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($areaTecnica); ?></span>
                    <span class="hero-pill"><i class="fas fa-list-check"></i> <span id="answeredCountLabel"><?php echo $answeredCount; ?></span> / <?php echo $questionTotal; ?> respondidas</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: <?php echo $questionTotal > 0 ? round(($answeredCount / $questionTotal) * 100, 2) : 0; ?>%;"></div>
                </div>
            </section>

            <aside class="timer-card">
                <div class="timer-label">Tiempo restante</div>
                <div class="timer-value" id="timerValue">00:00</div>
                <div class="timer-note">
                    Cuando el contador llegue a cero, el examen se enviara automaticamente con las respuestas marcadas.
                </div>
            </aside>
        </div>

        <section class="warning-card">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <strong>Regla de seguridad activa</strong>
                <p>Salir de la pestana, minimizar, recargar o cerrar esta pagina anula el intento y registra el evento.</p>
            </div>
        </section>

        <form
            id="examForm"
            action="index.php?page=student-exam&action=submit&id=<?php echo (int) $evaluacion['id']; ?>"
            method="POST"
            data-exam-id="<?php echo (int) $evaluacion['id']; ?>"
            data-security-url="index.php?page=student-exam&action=security&id=<?php echo (int) $evaluacion['id']; ?>"
            data-result-url="index.php?page=student-exam&id=<?php echo (int) $evaluacion['id']; ?>"
            data-remaining-seconds="<?php echo (int) $tiempo_restante; ?>"
        >
            <input type="hidden" name="evaluacion_id" value="<?php echo (int) $evaluacion['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="question-grid">
                <?php foreach ($preguntas as $index => $pregunta): ?>
                <section class="question-card" data-question-card>
                    <div class="question-top">
                        <span class="question-badge"><i class="fas fa-bolt"></i> Pregunta <?php echo $index + 1; ?></span>
                        <span class="question-status"><?php echo empty($pregunta['respuesta_estudiante']) ? 'Pendiente' : 'Respondida'; ?></span>
                    </div>
                    <p class="question-text"><?php echo htmlspecialchars($pregunta['pregunta']); ?></p>
                    <div class="option-list">
                        <?php foreach (['a', 'b', 'c', 'd'] as $opcion): ?>
                        <?php
                            $field = 'opcion_' . $opcion;
                            $inputId = 'q' . (int) $pregunta['evaluacion_pregunta_id'] . '_' . $opcion;
                            $isChecked = ($pregunta['respuesta_estudiante'] ?? '') === $opcion;
                        ?>
                        <label class="option-card" for="<?php echo $inputId; ?>">
                            <input id="<?php echo $inputId; ?>" type="radio" name="respuestas[<?php echo (int) $pregunta['evaluacion_pregunta_id']; ?>]" value="<?php echo $opcion; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                            <span class="option-body">
                                <span class="option-letter"><?php echo strtoupper($opcion); ?></span>
                                <span class="option-text"><?php echo htmlspecialchars($pregunta[$field]); ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>

            <div class="submit-bar">
                <div class="submit-copy">
                    <strong>Listo para cerrar el examen</strong>
                    <span>Revisa tus respuestas antes de enviarlo. No hay segunda oportunidad.</span>
                </div>
                <button type="submit" class="submit-btn" id="submitExamButton">
                    <i class="fas fa-paper-plane"></i>
                    Enviar examen
                </button>
            </div>
        </form>
        <?php else: ?>
        <?php
            $stateClass = in_array($estadoResultado, ['aprobado', 'reprobado', 'anulado'], true) ? $estadoResultado : 'reprobado';
            $stateLabel = $estadoResultado === 'aprobado' ? 'Aprobado' : ($estadoResultado === 'anulado' ? 'Anulado' : 'Finalizado');
            $resultTitle = $estadoResultado === 'aprobado' ? 'Pasaste el examen' : ($estadoResultado === 'anulado' ? 'El examen fue anulado' : 'El examen no fue aprobado');
            $resultText = $estadoResultado === 'aprobado'
                ? 'Tu resultado ya quedo registrado y la asignacion se genera si todavia hay cupo.'
                : ($estadoResultado === 'anulado'
                    ? 'Se detecto un evento de seguridad durante el examen y el intento quedo cerrado.'
                    : 'Tu intento fue procesado. Revisa el resultado y sigue atento a las empresas disponibles.');
        ?>
        <div class="result-layout">
            <section class="result-card">
                <span class="result-state <?php echo htmlspecialchars($stateClass); ?>">
                    <i class="fas fa-shield-halved"></i>
                    <?php echo htmlspecialchars($stateLabel); ?>
                </span>
                <h2 class="result-title"><?php echo htmlspecialchars($resultTitle); ?></h2>
                <p class="result-text"><?php echo htmlspecialchars($resultText); ?></p>

                <?php if ($ultimo_evento_seguridad && $estadoResultado === 'anulado'): ?>
                <div class="security-log">
                    <strong>Evento registrado</strong>
                    <p><?php echo htmlspecialchars($ultimo_evento_seguridad['detalles'] ?? 'Se detecto una incidencia de seguridad durante el examen.'); ?></p>
                </div>
                <?php endif; ?>

                <div class="result-actions">
                    <a href="index.php?page=student-companies" class="result-btn primary"><i class="fas fa-building"></i> Volver a empresas</a>
                    <a href="index.php?page=student-dashboard" class="result-btn"><i class="fas fa-house"></i> Ir al dashboard</a>
                </div>
            </section>

            <aside class="summary-card">
                <h3>Resumen del intento</h3>
                <div class="summary-list">
                    <div class="summary-item">
                        <span class="summary-label">Empresa</span>
                        <span class="summary-value"><?php echo htmlspecialchars($empresaNombre); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Area tecnica</span>
                        <span class="summary-value"><?php echo htmlspecialchars($areaTecnica); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Nota</span>
                        <span class="summary-value"><?php echo $notaResultado !== null ? htmlspecialchars($notaResultado) . '%' : 'Pendiente'; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Respuestas correctas</span>
                        <span class="summary-value"><?php echo $correctasResultado; ?> / <?php echo $totalResultado; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Estado final</span>
                        <span class="summary-value"><?php echo htmlspecialchars($stateLabel); ?></span>
                    </div>
                </div>
            </aside>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($exam_mode === 'active'): ?>
    <script>
        (function () {
            const examForm = document.getElementById('examForm');
            if (!examForm) {
                return;
            }

            const timerValue = document.getElementById('timerValue');
            const submitButton = document.getElementById('submitExamButton');
            const answeredLabel = document.getElementById('answeredCountLabel');
            const progressFill = document.getElementById('progressFill');
            const questionCards = Array.from(document.querySelectorAll('[data-question-card]'));
            const securityUrl = examForm.dataset.securityUrl;
            const resultUrl = examForm.dataset.resultUrl;
            const csrfToken = examForm.querySelector('input[name="csrf_token"]').value;
            const totalQuestions = questionCards.length;
            let remainingSeconds = parseInt(examForm.dataset.remainingSeconds || '0', 10);
            let isSubmitting = false;
            let isClosedBySecurity = false;
            let securitySent = false;

            function formatTime(totalSeconds) {
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            }

            function updateAnsweredState() {
                let answered = 0;

                questionCards.forEach((card) => {
                    const checked = card.querySelector('input[type="radio"]:checked');
                    const status = card.querySelector('.question-status');

                    if (checked) {
                        answered++;
                        if (status) {
                            status.textContent = 'Respondida';
                        }
                    } else if (status) {
                        status.textContent = 'Pendiente';
                    }
                });

                if (answeredLabel) {
                    answeredLabel.textContent = String(answered);
                }

                if (progressFill && totalQuestions > 0) {
                    progressFill.style.width = ((answered / totalQuestions) * 100).toFixed(2) + '%';
                }
            }

            function setTimerVisualState() {
                if (!timerValue) {
                    return;
                }

                timerValue.textContent = formatTime(Math.max(remainingSeconds, 0));
                if (remainingSeconds <= 300) {
                    timerValue.style.color = '#ff8fa1';
                }
            }

            function submitExamAutomatically() {
                if (isSubmitting || isClosedBySecurity) {
                    return;
                }

                isSubmitting = true;
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-hourglass-end"></i> Cerrando examen...';
                }
                examForm.submit();
            }

            function sendSecurityClose(eventName, detailMessage) {
                if (securitySent || isSubmitting || isClosedBySecurity) {
                    return;
                }

                securitySent = true;
                isClosedBySecurity = true;

                const payload = new URLSearchParams();
                payload.append('csrf_token', csrfToken);
                payload.append('evaluacion_id', examForm.dataset.examId || '');
                payload.append('event', eventName);
                payload.append('details', detailMessage);

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-ban"></i> Examen bloqueado';
                }

                const body = payload.toString();

                if (navigator.sendBeacon) {
                    const blob = new Blob([body], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
                    navigator.sendBeacon(securityUrl, blob);
                    window.location.href = resultUrl;
                    return;
                }

                fetch(securityUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body,
                    keepalive: true
                }).finally(function () {
                    window.location.href = resultUrl;
                });
            }

            examForm.addEventListener('submit', function () {
                isSubmitting = true;
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-paper-plane"></i> Enviando...';
                }
            });

            document.querySelectorAll('input[type="radio"]').forEach((input) => {
                input.addEventListener('change', updateAnsweredState);
            });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    sendSecurityClose('tab_hidden', 'El estudiante salio de la pestana del examen.');
                }
            });

            window.addEventListener('pagehide', function () {
                if (!isSubmitting && !isClosedBySecurity) {
                    sendSecurityClose('page_exit', 'El estudiante cerro, recargo o abandono la pagina del examen.');
                }
            });

            window.addEventListener('keydown', function (event) {
                const key = event.key.toLowerCase();
                const isCtrlOrMeta = event.ctrlKey || event.metaKey;
                const isBlockedCombo = isCtrlOrMeta && ['r', 'u', 'p', 'c', 'v', 'x'].includes(key);
                const isBlockedDevtools = isCtrlOrMeta && event.shiftKey && ['i', 'j', 'c'].includes(key);

                if (event.key === 'F5' || event.key === 'F12' || isBlockedCombo || isBlockedDevtools) {
                    event.preventDefault();
                }
            });

            ['contextmenu', 'copy', 'cut', 'paste'].forEach((eventName) => {
                document.addEventListener(eventName, function (event) {
                    event.preventDefault();
                });
            });

            setTimerVisualState();
            updateAnsweredState();

            const interval = window.setInterval(function () {
                remainingSeconds -= 1;
                setTimerVisualState();

                if (remainingSeconds <= 0) {
                    window.clearInterval(interval);
                    submitExamAutomatically();
                }
            }, 1000);
        })();
    </script>
    <?php endif; ?>
</body>
</html>
