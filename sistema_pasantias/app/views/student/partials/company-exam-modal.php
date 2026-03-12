<?php
$examMode = $exam_modal['mode'] ?? 'active';
$examEvaluacion = $exam_modal['evaluacion'] ?? [];
$examPreguntas = $exam_modal['preguntas'] ?? [];
$examResultado = $exam_modal['resultado'] ?? null;
$examSecurityEvent = $exam_modal['ultimo_evento_seguridad'] ?? null;
$examTimeRemaining = (int) ($exam_modal['tiempo_restante'] ?? 0);
$examCompanyName = $examEvaluacion['empresa_nombre'] ?? ($examResultado['empresa_nombre'] ?? 'Empresa');
$examArea = $examEvaluacion['area_tecnica'] ?? ($examResultado['area_tecnica'] ?? ($estudiante['area_tecnica'] ?? ''));
$examId = (int) ($examEvaluacion['id'] ?? ($examResultado['id'] ?? 0));
$examQuestionTotal = count($examPreguntas);
$examAnsweredCount = 0;

foreach ($examPreguntas as $examPregunta) {
    if (!empty($examPregunta['respuesta_estudiante'])) {
        $examAnsweredCount++;
    }
}

$resultState = $examResultado['estado'] ?? ($examEvaluacion['estado'] ?? '');
$resultLabel = $resultState === 'aprobado'
    ? 'Aprobado'
    : ($resultState === 'anulado' ? 'Anulado' : 'Finalizado');
?>
<style>
    .modal-open {
        overflow: hidden;
    }

    .exam-modal {
        position: fixed;
        inset: 0;
        z-index: 5000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .exam-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(7, 17, 31, 0.72);
        backdrop-filter: blur(8px);
    }

    .exam-modal-dialog {
        position: relative;
        width: min(1100px, calc(100vw - 24px));
        max-height: calc(100vh - 24px);
        overflow: auto;
        border-radius: 28px;
        background:
            radial-gradient(circle at top left, rgba(38, 118, 255, 0.24), transparent 32%),
            radial-gradient(circle at top right, rgba(255, 93, 115, 0.20), transparent 28%),
            linear-gradient(180deg, #091321 0%, #050b14 100%);
        color: #edf2f7;
        box-shadow: 0 28px 60px rgba(0, 0, 0, 0.34);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .exam-modal-inner {
        padding: 26px;
    }

    .exam-header,
    .exam-company,
    .exam-warning,
    .exam-submit-row,
    .exam-result-actions,
    .exam-question-head {
        display: flex;
        align-items: center;
    }

    .exam-header,
    .exam-submit-row,
    .exam-question-head {
        justify-content: space-between;
    }

    .exam-header {
        gap: 16px;
        margin-bottom: 22px;
    }

    .exam-brand {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .exam-brand-icon,
    .exam-close-btn {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .exam-brand-icon {
        background: linear-gradient(135deg, rgba(90, 169, 255, 0.22), rgba(255, 93, 115, 0.22));
        font-size: 22px;
    }

    .exam-brand-copy h2,
    .exam-result-title,
    .exam-summary h3 {
        margin: 0;
    }

    .exam-brand-copy h2 {
        font-size: 30px;
        margin-bottom: 6px;
    }

    .exam-brand-copy p,
    .exam-warning p,
    .exam-submit-copy span,
    .exam-result-text,
    .exam-security-box p {
        margin: 0;
        color: #b4c0d1;
        line-height: 1.6;
    }

    .exam-close-btn {
        background: rgba(255, 255, 255, 0.04);
        color: #edf2f7;
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease;
    }

    .exam-close-btn:hover {
        transform: translateY(-1px);
        background: rgba(255, 255, 255, 0.08);
    }

    .exam-hero,
    .exam-result-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.65fr);
        gap: 18px;
        margin-bottom: 20px;
    }

    .exam-card,
    .exam-timer,
    .exam-warning,
    .exam-question,
    .exam-result-card,
    .exam-summary {
        background: rgba(15, 28, 47, 0.84);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
    }

    .exam-card,
    .exam-timer,
    .exam-warning,
    .exam-question,
    .exam-result-card,
    .exam-summary {
        padding: 22px;
    }

    .exam-company {
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .exam-pill,
    .exam-state-badge,
    .exam-question-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 13px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
    }

    .exam-pill,
    .exam-question-badge {
        background: rgba(90, 169, 255, 0.12);
        color: #cbe8ff;
        border: 1px solid rgba(90, 169, 255, 0.22);
    }

    .exam-progress {
        height: 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        overflow: hidden;
        margin-top: 18px;
    }

    .exam-progress-bar {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #5aa9ff, #89d6ff);
        transition: width 0.2s ease;
    }

    .exam-timer-label,
    .exam-question-status,
    .exam-summary-label {
        color: #9fb0c8;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .exam-timer-value {
        font-size: 46px;
        font-weight: 900;
        line-height: 1;
        margin: 12px 0;
    }

    .exam-warning {
        gap: 14px;
        align-items: flex-start;
        background: linear-gradient(180deg, rgba(255, 93, 115, 0.18), rgba(255, 93, 115, 0.08));
        margin-bottom: 20px;
    }

    .exam-warning i {
        color: #ff98a7;
        font-size: 22px;
        margin-top: 2px;
    }

    .exam-question-list,
    .exam-options,
    .exam-summary-list {
        display: grid;
        gap: 18px;
    }

    .exam-question-text {
        margin: 0 0 16px;
        font-size: 19px;
        line-height: 1.6;
    }

    .exam-option {
        position: relative;
        display: block;
        cursor: pointer;
    }

    .exam-option input {
        position: absolute;
        inset: 0;
        opacity: 0;
        pointer-events: none;
    }

    .exam-option-body {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        padding: 15px 17px;
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.03);
        transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
    }

    .exam-option:hover .exam-option-body {
        transform: translateY(-1px);
        border-color: rgba(90, 169, 255, 0.26);
    }

    .exam-option input:checked + .exam-option-body {
        background: rgba(90, 169, 255, 0.14);
        border-color: rgba(90, 169, 255, 0.40);
    }

    .exam-option-letter {
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

    .exam-submit-row {
        position: sticky;
        bottom: 0;
        margin-top: 22px;
        gap: 16px;
        background: rgba(5, 11, 20, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        padding: 16px 18px;
    }

    .exam-submit-copy strong,
    .exam-security-box strong {
        display: block;
        margin-bottom: 6px;
    }

    .exam-submit-btn,
    .exam-result-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border-radius: 16px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.18s ease;
    }

    .exam-submit-btn {
        min-width: 230px;
        border: none;
        padding: 15px 20px;
        background: linear-gradient(135deg, #5aa9ff, #2676ff);
        color: #ffffff;
    }

    .exam-result-actions {
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 22px;
    }

    .exam-result-btn {
        padding: 14px 17px;
        background: rgba(255, 255, 255, 0.06);
        color: #edf2f7;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .exam-result-btn.primary {
        background: linear-gradient(135deg, #5aa9ff, #2676ff);
        border: none;
    }

    .exam-state-badge.aprobado {
        background: rgba(72, 187, 120, 0.16);
        color: #c9ffe0;
    }

    .exam-state-badge.reprobado {
        background: rgba(246, 173, 85, 0.18);
        color: #ffe0b5;
    }

    .exam-state-badge.anulado {
        background: rgba(255, 93, 115, 0.18);
        color: #ffd4dc;
    }

    .exam-result-title {
        margin: 18px 0 12px;
        font-size: 34px;
        line-height: 1.1;
    }

    .exam-summary-item {
        padding: 14px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .exam-summary-item:first-child {
        padding-top: 0;
        border-top: none;
    }

    .exam-summary-value {
        font-size: 19px;
        font-weight: 800;
    }

    .exam-security-box {
        margin-top: 18px;
        padding: 16px;
        border-radius: 18px;
        background: rgba(255, 93, 115, 0.14);
        border: 1px solid rgba(255, 93, 115, 0.18);
    }

    @media (max-width: 960px) {
        .exam-hero,
        .exam-result-grid {
            grid-template-columns: 1fr;
        }

        .exam-header,
        .exam-submit-row {
            flex-direction: column;
            align-items: stretch;
        }

        .exam-submit-btn {
            width: 100%;
            min-width: 0;
        }
    }
</style>

<div class="exam-modal" id="companyExamModal" data-mode="<?php echo htmlspecialchars($examMode); ?>">
    <div class="exam-modal-backdrop" id="companyExamBackdrop"></div>
    <div class="exam-modal-dialog">
        <div class="exam-modal-inner">
            <div class="exam-header">
                <div class="exam-brand">
                    <div class="exam-brand-icon">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div class="exam-brand-copy">
                        <h2>Examen tecnico</h2>
                        <p><?php echo $examMode === 'active' ? 'El examen se abre aqui mismo, sin salir de empresas disponibles.' : 'Tu intento quedo registrado en esta misma pantalla.'; ?></p>
                    </div>
                </div>

                <?php if ($examMode === 'active'): ?>
                <button type="button" class="exam-close-btn" id="closeActiveExamBtn" aria-label="Cerrar examen">
                    <i class="fas fa-xmark"></i>
                </button>
                <?php else: ?>
                <a href="index.php?page=student-companies" class="exam-close-btn" aria-label="Cerrar resultado">
                    <i class="fas fa-xmark"></i>
                </a>
                <?php endif; ?>
            </div>

            <?php if ($examMode === 'active'): ?>
            <div class="exam-hero">
                <section class="exam-card">
                    <h3 style="margin: 0 0 12px; font-size: 28px;">Demuestra que estas listo para entrar</h3>
                    <p>Tienes <?php echo Evaluacion::DURACION_MINUTOS; ?> minutos para responder <?php echo $examQuestionTotal; ?> preguntas. Si sales de esta pestaña o intentas cerrar el modal, el examen se anula.</p>
                    <div class="exam-company">
                        <span class="exam-pill"><i class="fas fa-building"></i> <?php echo htmlspecialchars($examCompanyName); ?></span>
                        <span class="exam-pill"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($examArea); ?></span>
                        <span class="exam-pill"><i class="fas fa-list-check"></i> <span id="examAnsweredCount"><?php echo $examAnsweredCount; ?></span> / <?php echo $examQuestionTotal; ?> respondidas</span>
                    </div>
                    <div class="exam-progress">
                        <div class="exam-progress-bar" id="examProgressBar" style="width: <?php echo $examQuestionTotal > 0 ? round(($examAnsweredCount / $examQuestionTotal) * 100, 2) : 0; ?>%;"></div>
                    </div>
                </section>

                <aside class="exam-timer">
                    <div class="exam-timer-label">Tiempo restante</div>
                    <div class="exam-timer-value" id="examTimerValue">00:00</div>
                    <div class="exam-brand-copy">
                        <p>Cuando el tiempo llegue a cero, el examen se enviara automaticamente con las respuestas marcadas.</p>
                    </div>
                </aside>
            </div>

            <section class="exam-warning">
                <i class="fas fa-triangle-exclamation"></i>
                <div>
                    <strong>Modo estricto activo</strong>
                    <p>No cambies de pestaña, no recargues la pagina y no cierres esta ventana. Cualquiera de esas acciones anula el intento.</p>
                </div>
            </section>

            <form
                id="companyExamForm"
                action="index.php?page=student-companies&action=submit_exam&exam=<?php echo $examId; ?>"
                method="POST"
                data-exam-id="<?php echo $examId; ?>"
                data-security-url="index.php?page=student-companies&action=security_exam&exam=<?php echo $examId; ?>"
                data-result-url="index.php?page=student-companies&exam=<?php echo $examId; ?>"
                data-remaining-seconds="<?php echo $examTimeRemaining; ?>"
            >
                <input type="hidden" name="evaluacion_id" value="<?php echo $examId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                <div class="exam-question-list">
                    <?php foreach ($examPreguntas as $index => $examPregunta): ?>
                    <section class="exam-question" data-exam-question>
                        <div class="exam-question-head">
                            <span class="exam-question-badge"><i class="fas fa-bolt"></i> Pregunta <?php echo $index + 1; ?></span>
                            <span class="exam-question-status"><?php echo empty($examPregunta['respuesta_estudiante']) ? 'Pendiente' : 'Respondida'; ?></span>
                        </div>
                        <p class="exam-question-text"><?php echo htmlspecialchars($examPregunta['pregunta']); ?></p>
                        <div class="exam-options">
                            <?php foreach (['a', 'b', 'c', 'd'] as $opcion): ?>
                            <?php
                                $field = 'opcion_' . $opcion;
                                $inputId = 'exam_q' . (int) $examPregunta['evaluacion_pregunta_id'] . '_' . $opcion;
                                $isChecked = ($examPregunta['respuesta_estudiante'] ?? '') === $opcion;
                            ?>
                            <label class="exam-option" for="<?php echo $inputId; ?>">
                                <input id="<?php echo $inputId; ?>" type="radio" name="respuestas[<?php echo (int) $examPregunta['evaluacion_pregunta_id']; ?>]" value="<?php echo $opcion; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                <span class="exam-option-body">
                                    <span class="exam-option-letter"><?php echo strtoupper($opcion); ?></span>
                                    <span><?php echo htmlspecialchars($examPregunta[$field]); ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                </div>

                <div class="exam-submit-row">
                    <div class="exam-submit-copy">
                        <strong>Listo para cerrar el examen</strong>
                        <span>En cuanto lo envies, el sistema califica el intento y muestra el resultado aqui mismo.</span>
                    </div>
                    <button type="submit" class="exam-submit-btn" id="submitCompanyExamBtn">
                        <i class="fas fa-paper-plane"></i>
                        Enviar examen
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="exam-result-grid">
                <section class="exam-result-card">
                    <span class="exam-state-badge <?php echo htmlspecialchars(in_array($resultState, ['aprobado', 'reprobado', 'anulado'], true) ? $resultState : 'reprobado'); ?>">
                        <i class="fas fa-shield-halved"></i>
                        <?php echo htmlspecialchars($resultLabel); ?>
                    </span>
                    <h3 class="exam-result-title">
                        <?php
                        echo $resultState === 'aprobado'
                            ? 'Pasaste el examen'
                            : ($resultState === 'anulado' ? 'El examen fue anulado' : 'El examen no fue aprobado');
                        ?>
                    </h3>
                    <p class="exam-result-text">
                        <?php
                        echo $resultState === 'aprobado'
                            ? 'Tu resultado ya quedo registrado y la asignacion se hace automaticamente si la empresa sigue con cupo.'
                            : ($resultState === 'anulado'
                                ? 'Se detecto una salida del entorno controlado y el intento quedo anulado.'
                                : 'Tu intento fue procesado. Puedes volver al listado de empresas cuando quieras.');
                        ?>
                    </p>

                    <?php if ($examSecurityEvent && $resultState === 'anulado'): ?>
                    <div class="exam-security-box">
                        <strong>Evento de seguridad</strong>
                        <p><?php echo htmlspecialchars($examSecurityEvent['detalles'] ?? 'Se detecto una incidencia de seguridad durante el examen.'); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="exam-result-actions">
                        <a href="index.php?page=student-companies" class="exam-result-btn primary"><i class="fas fa-building"></i> Volver a empresas</a>
                        <a href="index.php?page=student-dashboard" class="exam-result-btn"><i class="fas fa-house"></i> Ir al dashboard</a>
                    </div>
                </section>

                <aside class="exam-summary">
                    <h3>Resumen del intento</h3>
                    <div class="exam-summary-list">
                        <div class="exam-summary-item">
                            <span class="exam-summary-label">Empresa</span>
                            <div class="exam-summary-value"><?php echo htmlspecialchars($examCompanyName); ?></div>
                        </div>
                        <div class="exam-summary-item">
                            <span class="exam-summary-label">Area tecnica</span>
                            <div class="exam-summary-value"><?php echo htmlspecialchars($examArea); ?></div>
                        </div>
                        <div class="exam-summary-item">
                            <span class="exam-summary-label">Nota</span>
                            <div class="exam-summary-value"><?php echo isset($examResultado['nota']) ? number_format((float) $examResultado['nota'], 2) . '%' : 'Pendiente'; ?></div>
                        </div>
                        <div class="exam-summary-item">
                            <span class="exam-summary-label">Respuestas correctas</span>
                            <div class="exam-summary-value"><?php echo (int) ($examResultado['correctas'] ?? 0); ?> / <?php echo (int) ($examResultado['total'] ?? 0); ?></div>
                        </div>
                    </div>
                </aside>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($examMode === 'active'): ?>
<script>
    (function () {
        const modal = document.getElementById('companyExamModal');
        const form = document.getElementById('companyExamForm');
        const timerValue = document.getElementById('examTimerValue');
        const progressBar = document.getElementById('examProgressBar');
        const answeredCount = document.getElementById('examAnsweredCount');
        const submitButton = document.getElementById('submitCompanyExamBtn');
        const closeButton = document.getElementById('closeActiveExamBtn');
        const backdrop = document.getElementById('companyExamBackdrop');

        if (!modal || !form) {
            return;
        }

        const securityUrl = form.dataset.securityUrl;
        const resultUrl = form.dataset.resultUrl;
        const csrfToken = form.querySelector('input[name=\"csrf_token\"]').value;
        const questionCards = Array.from(document.querySelectorAll('[data-exam-question]'));
        const totalQuestions = questionCards.length;
        let remainingSeconds = parseInt(form.dataset.remainingSeconds || '0', 10);
        let securitySent = false;
        let isSubmitting = false;

        function submitExam() {
            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class=\"fas fa-paper-plane\"></i> Enviando...';
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        }

        function formatTime(totalSeconds) {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }

        function updateAnsweredState() {
            let answered = 0;

            questionCards.forEach((card) => {
                const checked = card.querySelector('input[type=\"radio\"]:checked');
                const status = card.querySelector('.exam-question-status');

                if (checked) {
                    answered++;
                    if (status) {
                        status.textContent = 'Respondida';
                    }
                } else if (status) {
                    status.textContent = 'Pendiente';
                }
            });

            if (answeredCount) {
                answeredCount.textContent = String(answered);
            }

            if (progressBar && totalQuestions > 0) {
                progressBar.style.width = ((answered / totalQuestions) * 100).toFixed(2) + '%';
            }
        }

        function updateTimer() {
            if (!timerValue) {
                return;
            }

            timerValue.textContent = formatTime(Math.max(remainingSeconds, 0));
            if (remainingSeconds <= 300) {
                timerValue.style.color = '#ff98a7';
            }
        }

        function sendSecurityClose(eventName, details) {
            if (securitySent || isSubmitting) {
                return;
            }

            securitySent = true;

            const payload = new URLSearchParams();
            payload.append('csrf_token', csrfToken);
            payload.append('evaluacion_id', form.dataset.examId || '');
            payload.append('event', eventName);
            payload.append('details', details);

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class=\"fas fa-ban\"></i> Examen bloqueado';
            }

            const body = payload.toString();
            if (navigator.sendBeacon) {
                navigator.sendBeacon(securityUrl, new Blob([body], { type: 'application/x-www-form-urlencoded;charset=UTF-8' }));
                window.location.href = resultUrl;
                return;
            }

            fetch(securityUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body,
                keepalive: true
            }).finally(function () {
                window.location.href = resultUrl;
            });
        }

        document.body.classList.add('modal-open');
        updateTimer();
        updateAnsweredState();

        document.querySelectorAll('#companyExamForm input[type=\"radio\"]').forEach((input) => {
            input.addEventListener('change', updateAnsweredState);
        });

        form.addEventListener('submit', function () {
            isSubmitting = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class=\"fas fa-paper-plane\"></i> Enviando...';
            }
        });

        if (closeButton) {
            closeButton.addEventListener('click', function () {
                sendSecurityClose('modal_closed', 'El estudiante cerro la ventana emergente del examen.');
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                sendSecurityClose('modal_backdrop', 'El estudiante intento salir del examen desde la ventana emergente.');
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                sendSecurityClose('tab_hidden', 'El estudiante salio de la pestana del examen.');
            }
        });

        window.addEventListener('pagehide', function () {
            if (!isSubmitting) {
                sendSecurityClose('page_exit', 'El estudiante cerro, recargo o abandono la pagina del examen.');
            }
        });

        window.addEventListener('keydown', function (event) {
            const key = event.key.toLowerCase();
            const isCtrlOrMeta = event.ctrlKey || event.metaKey;
            const isBlockedCombo = isCtrlOrMeta && ['r', 'u', 'p', 'c', 'v', 'x'].includes(key);
            const isBlockedDevtools = isCtrlOrMeta && event.shiftKey && ['i', 'j', 'c'].includes(key);

            if (event.key === 'Escape') {
                event.preventDefault();
                sendSecurityClose('escape_key', 'El estudiante intento cerrar el modal con la tecla Escape.');
                return;
            }

            if (event.key === 'F5' || event.key === 'F12' || isBlockedCombo || isBlockedDevtools) {
                event.preventDefault();
            }
        });

        ['contextmenu', 'copy', 'cut', 'paste'].forEach((eventName) => {
            document.addEventListener(eventName, function (event) {
                event.preventDefault();
            });
        });

        const interval = window.setInterval(function () {
            remainingSeconds -= 1;
            updateTimer();

            if (remainingSeconds <= 0) {
                window.clearInterval(interval);
                submitExam();
            }
        }, 1000);
    })();
</script>
<?php endif; ?>
