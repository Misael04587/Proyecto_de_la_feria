<?php
$studentsWithCv = (int) ($stats['cv_subidos'] ?? 0);
$studentsTotal = (int) ($stats['estudiantes'] ?? 0);
$studentsWithoutCv = max($studentsTotal - $studentsWithCv, 0);
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
$followUpMeta = [
    'sin_revisar' => ['label' => 'Pendiente de revision', 'class' => 'pill-orange'],
    'en_revision' => ['label' => 'En revision', 'class' => 'pill-blue'],
    'preseleccionado' => ['label' => 'Preseleccionado', 'class' => 'pill-green'],
    'descartado' => ['label' => 'Descartado', 'class' => 'pill-red'],
];
?>
<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Estudiantes</span>
        <div class="summary-value"><?php echo $studentsTotal; ?></div>
        <p class="summary-help"><?php echo (int) ($stats['estudiantes_nuevos'] ?? 0); ?> registros nuevos durante el ultimo mes.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Con CV</span>
        <div class="summary-value"><?php echo $studentsWithCv; ?></div>
        <p class="summary-help">Listos para postularse a empresas.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Sin CV</span>
        <div class="summary-value"><?php echo $studentsWithoutCv; ?></div>
        <p class="summary-help">Todavia requieren completar su perfil.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Pasantias activas</span>
        <div class="summary-value"><?php echo (int) ($stats['asignaciones_activas'] ?? 0); ?></div>
        <p class="summary-help">Estudiantes ya ubicados en una empresa.</p>
    </article>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Filtro de estudiantes</h2>
            <p class="section-copy">Filtra por area, CV o estado de pasantia.</p>
        </div>
    </div>
    <form method="GET" class="filter-card">
        <input type="hidden" name="page" value="admin-students">
        <div class="filter-group">
            <label for="area">Area tecnica</label>
            <select name="area" id="area">
                <option value="">Todas</option>
                <?php foreach ($areaLabels as $areaKey => $areaLabel): ?>
                <option value="<?php echo htmlspecialchars($areaKey); ?>"<?php echo ($filters['area'] ?? '') === $areaLabel ? ' selected' : ''; ?>><?php echo htmlspecialchars($areaLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="cv">CV</label>
            <select name="cv" id="cv">
                <option value="">Todos</option>
                <option value="con_cv"<?php echo ($filters['cv'] ?? '') === 'con_cv' ? ' selected' : ''; ?>>Con CV</option>
                <option value="sin_cv"<?php echo ($filters['cv'] ?? '') === 'sin_cv' ? ' selected' : ''; ?>>Sin CV</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="pasantia">Pasantia</label>
            <select name="pasantia" id="pasantia">
                <option value="">Todos</option>
                <option value="activa"<?php echo ($filters['pasantia'] ?? '') === 'activa' ? ' selected' : ''; ?>>Activa</option>
                <option value="sin_pasantia"<?php echo ($filters['pasantia'] ?? '') === 'sin_pasantia' ? ' selected' : ''; ?>>Sin pasantia</option>
            </select>
        </div>
        <button type="submit" class="card-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="index.php?page=admin-students" class="secondary-inline-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-rotate-left"></i> Limpiar</a>
        <button
            type="submit"
            form="delete-all-students-form"
            class="student-delete-all-btn"
            style="width:auto;padding:14px 20px;">
            <i class="fas fa-trash"></i> Eliminar todos
        </button>
    </form>
    <form
        method="POST"
        action="index.php?page=admin-students"
        id="delete-all-students-form"
        class="student-bulk-delete-form"
        onsubmit="return confirm('Esta accion eliminara a todos los estudiantes del centro, junto con sus usuarios, CV, evaluaciones y pasantias. Deseas continuar?');">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
        <input type="hidden" name="intent" value="delete_all_students">
    </form>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Listado de estudiantes</h2>
            <p class="section-copy">Revisa CV, deja comentarios de correccion y valida el estado general de cada estudiante.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($students); ?> resultados</span>
    </div>
    <?php if (!empty($students)): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Matricula</th>
                    <th>CV</th>
                    <th>Revision CV</th>
                    <th>Ultima evaluacion</th>
                    <th>Pasantia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <?php
                $evaluationState = (string) ($student['ultima_evaluacion_estado'] ?? '');
                $evaluationPill = $evaluationState === 'aprobado'
                    ? 'pill-green'
                    : ($evaluationState === 'pendiente'
                        ? 'pill-orange'
                        : ($evaluationState === 'reprobado' ? 'pill-red' : 'pill-neutral'));
                $followUpState = (string) ($student['ultima_evaluacion_seguimiento_estado'] ?? '');
                $followUpItem = $followUpMeta[$followUpState] ?? null;

                $reviewDateLabel = 'Sin revision';
                if (!empty($student['fecha_revision_cv'])) {
                    $reviewTimestamp = strtotime((string) $student['fecha_revision_cv']);
                    if ($reviewTimestamp !== false) {
                        $reviewDateLabel = date('d/m/Y h:i A', $reviewTimestamp);
                    }
                }
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($student['nombre'] ?? 'Sin nombre'); ?></strong>
                        <div class="muted-line"><?php echo htmlspecialchars($student['correo'] ?? 'Sin correo'); ?></div>
                        <div class="muted-line"><?php echo htmlspecialchars($student['area_tecnica'] ?? 'Sin area'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($student['matricula'] ?? 'Sin matricula'); ?></td>
                    <td>
                        <span class="pill <?php echo !empty($student['cv_path']) ? 'pill-green' : 'pill-red'; ?>">
                            <?php echo !empty($student['cv_path']) ? 'Con CV' : 'Sin CV'; ?>
                        </span>
                        <?php if (!empty($student['cv_path'])): ?>
                        <a href="ver-cv.php?student_id=<?php echo (int) ($student['id'] ?? 0); ?>" target="_blank" class="cv-inline-link">
                            <i class="fas fa-eye"></i> Ver CV
                        </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($student['cv_path'])): ?>
                        <form method="POST" action="index.php?page=admin-students" class="cv-review-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="intent" value="save_cv_comment">
                            <input type="hidden" name="student_id" value="<?php echo (int) ($student['id'] ?? 0); ?>">
                            <textarea
                                name="cv_comment"
                                rows="3"
                                maxlength="1200"
                                class="cv-review-textarea"
                                placeholder="Ejemplo: corrige el formato, agrega experiencia tecnica o mejora la presentacion."><?php echo htmlspecialchars((string) ($student['comentario_cv_admin'] ?? '')); ?></textarea>
                            <div class="muted-line">Ultima revision: <?php echo htmlspecialchars($reviewDateLabel); ?></div>
                            <button type="submit" class="cv-review-submit">
                                <i class="fas fa-floppy-disk"></i> Guardar comentario
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="pill pill-neutral">Sin CV para revisar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($evaluationState !== ''): ?>
                        <span class="pill <?php echo $evaluationPill; ?>"><?php echo htmlspecialchars(ucfirst($evaluationState)); ?></span>
                        <div class="muted-line">Nota: <?php echo $student['ultima_evaluacion_nota'] !== null ? htmlspecialchars(number_format((float) $student['ultima_evaluacion_nota'], 1)) : 'Sin nota'; ?></div>
                        <?php if ($evaluationState === 'aprobado' && $followUpItem): ?>
                        <span class="pill <?php echo htmlspecialchars($followUpItem['class']); ?>" style="margin-top: 8px;">
                            <?php echo htmlspecialchars($followUpItem['label']); ?>
                        </span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="pill pill-neutral">Sin evaluacion</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($student['empresa_activa'])): ?>
                        <span class="pill pill-blue">Activa</span>
                        <div class="muted-line"><?php echo htmlspecialchars($student['empresa_activa']); ?></div>
                        <div class="muted-line">
                            Desde <?php echo htmlspecialchars($formatDate($student['asignacion_activa_fecha'] ?? null, false)); ?>
                        </div>
                        <?php if (!empty($student['asignacion_activa_id'])): ?>
                        <div class="student-inline-actions" style="margin-top: 12px;">
                            <form method="POST" action="index.php?page=admin-students" class="student-inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="intent" value="update_assignment_status">
                                <input type="hidden" name="assignment_id" value="<?php echo (int) ($student['asignacion_activa_id'] ?? 0); ?>">
                                <input type="hidden" name="target_state" value="finalizada">
                                <button type="submit" class="student-inline-btn">
                                    <i class="fas fa-flag-checkered"></i> Finalizar
                                </button>
                            </form>
                            <form
                                method="POST"
                                action="index.php?page=admin-students"
                                class="student-inline-form"
                                onsubmit="return confirm('Esta accion cancelara la pasantia activa del estudiante. Deseas continuar?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="intent" value="update_assignment_status">
                                <input type="hidden" name="assignment_id" value="<?php echo (int) ($student['asignacion_activa_id'] ?? 0); ?>">
                                <input type="hidden" name="target_state" value="cancelada">
                                <button type="submit" class="student-inline-btn danger">
                                    <i class="fas fa-ban"></i> Cancelar
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="pill pill-neutral">Sin pasantia</span>
                        <?php endif; ?>
                    </td>
                    <td class="student-actions-cell">
                        <a href="index.php?page=admin-students&history=<?php echo (int) ($student['id'] ?? 0); ?>" class="company-edit-btn" style="margin-bottom: 10px;">
                            <i class="fas fa-clock-rotate-left"></i> Historial
                        </a>
                        <form
                            method="POST"
                            action="index.php?page=admin-students"
                            class="student-delete-form"
                            onsubmit="return confirm('Esta accion eliminara al estudiante, su usuario, CV, evaluaciones y pasantias. ¿Deseas continuar?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="intent" value="delete_student">
                            <input type="hidden" name="student_id" value="<?php echo (int) ($student['id'] ?? 0); ?>">
                            <button type="submit" class="student-delete-btn">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">No hay estudiantes que coincidan con el filtro actual.</div>
    <?php endif; ?>
</section>

<?php if (!empty($selectedStudentHistory)): ?>
<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Historial del estudiante</h2>
            <p class="section-copy">
                <?php echo htmlspecialchars($selectedStudentHistory['nombre'] ?? 'Estudiante'); ?>
                - <?php echo htmlspecialchars($selectedStudentHistory['matricula'] ?? 'Sin matricula'); ?>
            </p>
        </div>
        <a href="index.php?page=admin-students" class="secondary-inline-btn" style="width:auto;padding:12px 18px;">
            <i class="fas fa-xmark"></i> Cerrar historial
        </a>
    </div>
    <?php if (!empty($studentHistoryRows)): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Examen</th>
                    <th>Seguimiento</th>
                    <th>Pasantia</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentHistoryRows as $historyRow): ?>
                <?php
                $historyFollowUp = $followUpMeta[$historyRow['seguimiento_estado'] ?? ''] ?? ['label' => 'Sin seguimiento', 'class' => 'pill-neutral'];
                $historyExamClass = ($historyRow['estado'] ?? '') === 'aprobado'
                    ? 'pill-green'
                    : ((($historyRow['estado'] ?? '') === 'pendiente')
                        ? 'pill-orange'
                        : ((($historyRow['estado'] ?? '') === 'reprobado') ? 'pill-red' : 'pill-neutral'));
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($historyRow['empresa_nombre'] ?? 'Empresa'); ?></strong>
                        <div class="muted-line"><?php echo htmlspecialchars($historyRow['empresa_area'] ?? 'Sin area'); ?></div>
                    </td>
                    <td>
                        <span class="pill <?php echo $historyExamClass; ?>"><?php echo htmlspecialchars(ucfirst((string) ($historyRow['estado'] ?? 'sin estado'))); ?></span>
                        <div class="muted-line">Nota: <?php echo $historyRow['nota'] !== null ? htmlspecialchars(number_format((float) $historyRow['nota'], 1)) : 'Sin nota'; ?></div>
                    </td>
                    <td>
                        <?php if (($historyRow['estado'] ?? '') === 'aprobado'): ?>
                        <span class="pill <?php echo htmlspecialchars($historyFollowUp['class']); ?>"><?php echo htmlspecialchars($historyFollowUp['label']); ?></span>
                        <?php if (!empty($historyRow['seguimiento_comentario'])): ?>
                        <div class="muted-line"><?php echo htmlspecialchars($historyRow['seguimiento_comentario']); ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="pill pill-neutral">No aplica</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($historyRow['asignacion_estado'])): ?>
                        <span class="pill <?php echo ($historyRow['asignacion_estado'] ?? '') === 'activa' ? 'pill-blue' : (($historyRow['asignacion_estado'] ?? '') === 'finalizada' ? 'pill-green' : 'pill-red'); ?>">
                            <?php echo htmlspecialchars(ucfirst((string) ($historyRow['asignacion_estado'] ?? ''))); ?>
                        </span>
                        <?php if (!empty($historyRow['fecha_asignacion'])): ?>
                        <div class="muted-line">Desde <?php echo htmlspecialchars($formatDate($historyRow['fecha_asignacion'], false)); ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="pill pill-neutral">Sin pasantia</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($formatDate($historyRow['fecha_evaluacion'] ?? null, true)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">Este estudiante aun no tiene evaluaciones registradas.</div>
    <?php endif; ?>
</section>
<?php endif; ?>
