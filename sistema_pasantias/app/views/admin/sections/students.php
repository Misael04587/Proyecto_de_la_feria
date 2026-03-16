<?php
$studentsWithCv = (int) ($stats['cv_subidos'] ?? 0);
$studentsTotal = (int) ($stats['estudiantes'] ?? 0);
$studentsWithoutCv = max($studentsTotal - $studentsWithCv, 0);
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
                            <div class="muted-line">Si lo dejas vacio y guardas, se elimina el comentario.</div>
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
                        <?php else: ?>
                        <span class="pill pill-neutral">Sin evaluacion</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($student['empresa_activa'])): ?>
                        <span class="pill pill-blue">Activa</span>
                        <div class="muted-line"><?php echo htmlspecialchars($student['empresa_activa']); ?></div>
                        <?php else: ?>
                        <span class="pill pill-neutral">Sin pasantia</span>
                        <?php endif; ?>
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
