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
            <p class="section-copy">Informacion clave para saber quien ya esta listo para su proceso de pasantia.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($students); ?> resultados</span>
    </div>
    <?php if (!empty($students)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Estudiante</th>
                <th>Matricula</th>
                <th>CV</th>
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
    <?php else: ?>
    <div class="empty-state">No hay estudiantes que coincidan con el filtro actual.</div>
    <?php endif; ?>
</section>
