<?php
$questionStats = is_array($questionStats ?? null) ? $questionStats : [];
$questionHealth = is_array($questionHealth ?? null) ? $questionHealth : [];
$questions = is_array($questions ?? null) ? $questions : [];
$questionFormData = is_array($questionFormData ?? null) ? $questionFormData : [];

$questionFormData = array_merge([
    'id' => 0,
    'area_tecnica' => '',
    'pregunta' => '',
    'opcion_a' => '',
    'opcion_b' => '',
    'opcion_c' => '',
    'opcion_d' => '',
    'respuesta_correcta' => 'a',
    'estado' => 'activo',
], $questionFormData);

$isEditingQuestion = (int) ($questionFormData['id'] ?? 0) > 0;
$formTitle = $isEditingQuestion ? 'Editar pregunta' : 'Registrar pregunta';
$formCopy = $isEditingQuestion
    ? 'Solo se pueden editar preguntas que todavia no hayan sido usadas en evaluaciones.'
    : 'Carga nuevas preguntas por area tecnica para fortalecer el banco del examen.';
$formIntent = $isEditingQuestion ? 'update_question' : 'create_question';
$formSubmitLabel = $isEditingQuestion ? 'Guardar cambios' : 'Registrar pregunta';
?>
<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Total de preguntas</span>
        <div class="summary-value"><?php echo (int) ($questionStats['total'] ?? 0); ?></div>
        <p class="summary-help">Banco general disponible para las areas de tu centro.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Activas</span>
        <div class="summary-value"><?php echo (int) ($questionStats['activas'] ?? 0); ?></div>
        <p class="summary-help"><?php echo (int) ($questionStats['inactivas'] ?? 0); ?> preguntas estan fuera del pool actual del examen.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Areas listas</span>
        <div class="summary-value"><?php echo (int) ($questionStats['areas_listas'] ?? 0); ?></div>
        <p class="summary-help">Ya alcanzan el minimo de <?php echo (int) Evaluacion::MIN_PREGUNTAS; ?> preguntas activas.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Preguntas usadas</span>
        <div class="summary-value"><?php echo (int) ($questionStats['usadas'] ?? 0); ?></div>
        <p class="summary-help">Estas no se pueden editar ni borrar para no alterar historial.</p>
    </article>
</section>

<section class="admin-split">
    <article class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="section-title"><?php echo htmlspecialchars($formTitle); ?></h2>
                <p class="section-copy"><?php echo htmlspecialchars($formCopy); ?></p>
            </div>
            <span class="pill <?php echo $isEditingQuestion ? 'pill-orange' : 'pill-blue'; ?>">
                <?php echo $isEditingQuestion ? 'Modo edicion' : 'Nuevo registro'; ?>
            </span>
        </div>

        <form method="POST" class="question-form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
            <input type="hidden" name="intent" value="<?php echo htmlspecialchars($formIntent); ?>">
            <?php if ($isEditingQuestion): ?>
            <input type="hidden" name="question_id" value="<?php echo (int) ($questionFormData['id'] ?? 0); ?>">
            <?php endif; ?>

            <div class="filter-group">
                <label for="question_area">Area tecnica</label>
                <select name="area_tecnica" id="question_area" required>
                    <option value="">Selecciona un area</option>
                    <?php foreach ($areaLabels as $areaLabel): ?>
                    <option value="<?php echo htmlspecialchars($areaLabel); ?>"<?php echo ($questionFormData['area_tecnica'] ?? '') === $areaLabel ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($areaLabel); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="question_answer">Respuesta correcta</label>
                <select name="respuesta_correcta" id="question_answer" required>
                    <option value="a"<?php echo ($questionFormData['respuesta_correcta'] ?? 'a') === 'a' ? ' selected' : ''; ?>>Opcion A</option>
                    <option value="b"<?php echo ($questionFormData['respuesta_correcta'] ?? '') === 'b' ? ' selected' : ''; ?>>Opcion B</option>
                    <option value="c"<?php echo ($questionFormData['respuesta_correcta'] ?? '') === 'c' ? ' selected' : ''; ?>>Opcion C</option>
                    <option value="d"<?php echo ($questionFormData['respuesta_correcta'] ?? '') === 'd' ? ' selected' : ''; ?>>Opcion D</option>
                </select>
            </div>

            <div class="filter-group full-width">
                <label for="question_text">Pregunta</label>
                <textarea
                    id="question_text"
                    name="pregunta"
                    rows="5"
                    maxlength="2000"
                    class="question-form-textarea"
                    placeholder="Escribe el enunciado completo de la pregunta."><?php echo htmlspecialchars((string) ($questionFormData['pregunta'] ?? '')); ?></textarea>
            </div>

            <div class="filter-group">
                <label for="option_a">Opcion A</label>
                <input type="text" id="option_a" name="opcion_a" maxlength="255" value="<?php echo htmlspecialchars((string) ($questionFormData['opcion_a'] ?? '')); ?>" required>
            </div>

            <div class="filter-group">
                <label for="option_b">Opcion B</label>
                <input type="text" id="option_b" name="opcion_b" maxlength="255" value="<?php echo htmlspecialchars((string) ($questionFormData['opcion_b'] ?? '')); ?>" required>
            </div>

            <div class="filter-group">
                <label for="option_c">Opcion C</label>
                <input type="text" id="option_c" name="opcion_c" maxlength="255" value="<?php echo htmlspecialchars((string) ($questionFormData['opcion_c'] ?? '')); ?>" required>
            </div>

            <div class="filter-group">
                <label for="option_d">Opcion D</label>
                <input type="text" id="option_d" name="opcion_d" maxlength="255" value="<?php echo htmlspecialchars((string) ($questionFormData['opcion_d'] ?? '')); ?>" required>
            </div>

            <div class="filter-group">
                <label for="question_state">Estado</label>
                <select name="estado" id="question_state" required>
                    <option value="activo"<?php echo ($questionFormData['estado'] ?? 'activo') === 'activo' ? ' selected' : ''; ?>>Activa</option>
                    <option value="inactivo"<?php echo ($questionFormData['estado'] ?? '') === 'inactivo' ? ' selected' : ''; ?>>Inactiva</option>
                </select>
            </div>

            <div class="question-form-actions full-width">
                <button type="submit" class="card-btn question-primary-btn">
                    <i class="fas <?php echo $isEditingQuestion ? 'fa-floppy-disk' : 'fa-circle-plus'; ?>"></i>
                    <?php echo htmlspecialchars($formSubmitLabel); ?>
                </button>

                <?php if ($isEditingQuestion): ?>
                <a href="index.php?page=admin-questions" class="question-secondary-btn">
                    <i class="fas fa-xmark"></i>
                    Cancelar edicion
                </a>
                <?php endif; ?>
            </div>
        </form>
    </article>

    <div class="report-side-stack">
        <article class="side-card">
            <div class="side-card-header">
                <div>
                    <h2 class="section-title">Estado por area</h2>
                    <p class="section-copy">Detecta rapido cuales areas ya pueden sostener examenes completos.</p>
                </div>
            </div>
            <?php if (!empty($questionHealth)): ?>
            <div class="compact-list">
                <?php foreach ($questionHealth as $areaInfo): ?>
                <div class="compact-item">
                    <div class="question-health-head">
                        <strong><?php echo htmlspecialchars($areaInfo['label'] ?? 'Sin area'); ?></strong>
                        <span class="pill <?php echo !empty($areaInfo['ready']) ? 'pill-green' : 'pill-orange'; ?>">
                            <?php echo !empty($areaInfo['ready']) ? 'Lista' : 'Pendiente'; ?>
                        </span>
                    </div>
                    <div class="muted-line"><?php echo (int) ($areaInfo['active_count'] ?? 0); ?> activas / <?php echo (int) ($areaInfo['inactive_count'] ?? 0); ?> inactivas</div>
                    <div class="muted-line"><?php echo (int) ($areaInfo['used_count'] ?? 0); ?> ya fueron usadas en examenes</div>
                    <div class="muted-line">
                        <?php if (!empty($areaInfo['ready'])): ?>
                        Ya alcanza el minimo de <?php echo (int) Evaluacion::MIN_PREGUNTAS; ?> preguntas activas.
                        <?php else: ?>
                        Le faltan <?php echo (int) ($areaInfo['missing_count'] ?? 0); ?> preguntas activas para llegar al minimo.
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">No hay areas activas para evaluar el banco de preguntas.</div>
            <?php endif; ?>
        </article>

        <article class="side-card">
            <div class="side-card-header">
                <div>
                    <h2 class="section-title">Reglas del modulo</h2>
                    <p class="section-copy">El historial del examen se protege desde aqui.</p>
                </div>
            </div>
            <div class="compact-list">
                <div class="compact-item">
                    <strong>Preguntas usadas</strong>
                    <div class="muted-line">Si una pregunta ya aparecio en una evaluacion, no se puede editar ni borrar.</div>
                </div>
                <div class="compact-item">
                    <strong>Activar o desactivar</strong>
                    <div class="muted-line">El examen solo toma preguntas activas, pero las inactivas siguen preservando historial.</div>
                </div>
                <div class="compact-item">
                    <strong>Calidad del banco</strong>
                    <div class="muted-line">Cada area necesita al menos <?php echo (int) Evaluacion::MIN_PREGUNTAS; ?> preguntas activas para poder generar examenes de forma segura.</div>
                </div>
            </div>
        </article>
    </div>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Filtro de preguntas</h2>
            <p class="section-copy">Filtra por area tecnica, estado o si ya fueron usadas en evaluaciones.</p>
        </div>
    </div>
    <form method="GET" class="filter-card">
        <input type="hidden" name="page" value="admin-questions">
        <div class="filter-group">
            <label for="filter_area">Area tecnica</label>
            <select name="area" id="filter_area">
                <option value="">Todas</option>
                <?php foreach ($areaLabels as $areaKey => $areaLabel): ?>
                <option value="<?php echo htmlspecialchars($areaKey); ?>"<?php echo ($filters['area'] ?? '') === $areaLabel ? ' selected' : ''; ?>><?php echo htmlspecialchars($areaLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter_state">Estado</label>
            <select name="estado" id="filter_state">
                <option value="">Todos</option>
                <option value="activo"<?php echo ($filters['estado'] ?? '') === 'activo' ? ' selected' : ''; ?>>Activas</option>
                <option value="inactivo"<?php echo ($filters['estado'] ?? '') === 'inactivo' ? ' selected' : ''; ?>>Inactivas</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter_usage">Uso</label>
            <select name="uso" id="filter_usage">
                <option value="">Todas</option>
                <option value="usadas"<?php echo ($filters['uso'] ?? '') === 'usadas' ? ' selected' : ''; ?>>Usadas</option>
                <option value="sin_uso"<?php echo ($filters['uso'] ?? '') === 'sin_uso' ? ' selected' : ''; ?>>Sin uso</option>
            </select>
        </div>
        <button type="submit" class="card-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="index.php?page=admin-questions" class="question-secondary-btn">
            <i class="fas fa-rotate-left"></i>
            Limpiar
        </a>
    </form>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Listado de preguntas</h2>
            <p class="section-copy">Banco disponible para las areas tecnicas activas del centro.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($questions); ?> resultados</span>
    </div>
    <?php if (!empty($questions)): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Pregunta</th>
                    <th>Respuesta correcta</th>
                    <th>Estado</th>
                    <th>Uso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $question): ?>
                <?php
                $questionPreview = trim((string) ($question['pregunta'] ?? ''));
                if ($questionPreview !== '') {
                    $questionPreview = function_exists('mb_substr')
                        ? mb_substr($questionPreview, 0, 150, 'UTF-8')
                        : substr($questionPreview, 0, 150);

                    if ($questionPreview !== trim((string) ($question['pregunta'] ?? ''))) {
                        $questionPreview .= '...';
                    }
                }

                $correctOption = strtolower((string) ($question['respuesta_correcta'] ?? 'a'));
                $correctField = 'opcion_' . $correctOption;
                $correctLabel = strtoupper($correctOption) . '. ' . (string) ($question[$correctField] ?? 'Opcion no disponible');
                $isUsedQuestion = (int) ($question['usos_total'] ?? 0) > 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($question['area_tecnica'] ?? 'Sin area'); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($questionPreview !== '' ? $questionPreview : 'Sin enunciado'); ?></strong>
                        <div class="muted-line">Creada el <?php echo htmlspecialchars($formatDate($question['created_at'] ?? null, true)); ?></div>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($correctLabel); ?></strong>
                    </td>
                    <td>
                        <span class="pill <?php echo ($question['estado'] ?? 'activo') === 'activo' ? 'pill-green' : 'pill-neutral'; ?>">
                            <?php echo htmlspecialchars(ucfirst($question['estado'] ?? 'activo')); ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo (int) ($question['usos_total'] ?? 0); ?> usos</strong>
                        <div class="muted-line"><?php echo (int) ($question['evaluaciones_total'] ?? 0); ?> evaluaciones</div>
                    </td>
                    <td class="question-actions-cell">
                        <div class="question-inline-actions">
                            <?php if (!$isUsedQuestion): ?>
                            <a href="index.php?page=admin-questions&edit=<?php echo (int) ($question['id'] ?? 0); ?>" class="question-edit-btn">
                                <i class="fas fa-pen"></i>
                                Editar
                            </a>
                            <?php else: ?>
                            <span class="question-static-badge">
                                <i class="fas fa-lock"></i>
                                Bloqueada
                            </span>
                            <?php endif; ?>

                            <form method="POST" action="index.php?page=admin-questions" class="question-toggle-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="intent" value="toggle_question_status">
                                <input type="hidden" name="question_id" value="<?php echo (int) ($question['id'] ?? 0); ?>">
                                <input type="hidden" name="target_state" value="<?php echo ($question['estado'] ?? 'activo') === 'activo' ? 'inactivo' : 'activo'; ?>">
                                <button type="submit" class="question-toggle-btn">
                                    <i class="fas <?php echo ($question['estado'] ?? 'activo') === 'activo' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                    <?php echo ($question['estado'] ?? 'activo') === 'activo' ? 'Desactivar' : 'Activar'; ?>
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="index.php?page=admin-questions"
                                class="question-delete-form"
                                onsubmit="return confirm('Esta accion eliminara la pregunta si no tiene historial asociado. Deseas continuar?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="intent" value="delete_question">
                                <input type="hidden" name="question_id" value="<?php echo (int) ($question['id'] ?? 0); ?>">
                                <button type="submit" class="question-delete-btn" <?php echo $isUsedQuestion ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i>
                                    Eliminar
                                </button>
                            </form>
                        </div>
                        <?php if ($isUsedQuestion): ?>
                        <div class="muted-line question-history-note">Ya fue usada en examenes, por eso queda protegida.</div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">No hay preguntas que coincidan con el filtro aplicado.</div>
    <?php endif; ?>
</section>
