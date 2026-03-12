<?php
$cvPendientes = max((int) ($stats['estudiantes'] ?? 0) - (int) ($stats['cv_subidos'] ?? 0), 0);
?>
<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Empresas activas</span>
        <div class="summary-value"><?php echo (int) ($stats['empresas_disponibles'] ?? 0); ?></div>
        <p class="summary-help"><?php echo (int) ($stats['empresas'] ?? 0); ?> empresas registradas en total.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Estudiantes</span>
        <div class="summary-value"><?php echo (int) ($stats['estudiantes'] ?? 0); ?></div>
        <p class="summary-help"><?php echo (int) ($stats['estudiantes_nuevos'] ?? 0); ?> nuevos en los ultimos 30 dias.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Evaluaciones pendientes</span>
        <div class="summary-value"><?php echo (int) ($stats['evaluaciones_pendientes'] ?? 0); ?></div>
        <p class="summary-help"><?php echo (int) ($stats['evaluaciones_total'] ?? 0); ?> evaluaciones creadas hasta ahora.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Pasantias activas</span>
        <div class="summary-value"><?php echo (int) ($stats['asignaciones_activas'] ?? 0); ?></div>
        <p class="summary-help"><?php echo (int) ($stats['asignaciones_finalizadas'] ?? 0); ?> cerradas por el centro.</p>
    </article>
</section>

<section class="module-grid">
    <article class="module-card">
        <div class="module-head">
            <div class="module-icon blue"><i class="fas fa-building"></i></div>
            <div>
                <h2 class="module-title">Empresas</h2>
                <p class="module-subtitle">Mira cupos, ocupacion y estado de cada empresa.</p>
            </div>
        </div>
        <div class="metric-row">
            <div class="metric-box">
                <span class="summary-label">Disponibles</span>
                <strong><?php echo (int) ($stats['empresas_disponibles'] ?? 0); ?></strong>
            </div>
            <div class="metric-box">
                <span class="summary-label">Completas</span>
                <strong><?php echo (int) ($stats['empresas_completas'] ?? 0); ?></strong>
            </div>
        </div>
        <a href="index.php?page=admin-companies" class="card-btn"><i class="fas fa-building"></i> Abrir modulo</a>
    </article>

    <article class="module-card">
        <div class="module-head">
            <div class="module-icon green"><i class="fas fa-users"></i></div>
            <div>
                <h2 class="module-title">Estudiantes</h2>
                <p class="module-subtitle">Control rapido de matriculas, CV y pasantias activas.</p>
            </div>
        </div>
        <div class="metric-row">
            <div class="metric-box">
                <span class="summary-label">Con CV</span>
                <strong><?php echo (int) ($stats['cv_subidos'] ?? 0); ?></strong>
            </div>
            <div class="metric-box">
                <span class="summary-label">Sin CV</span>
                <strong><?php echo $cvPendientes; ?></strong>
            </div>
        </div>
        <a href="index.php?page=admin-students" class="card-btn btn-green"><i class="fas fa-user-graduate"></i> Abrir modulo</a>
    </article>

    <article class="module-card">
        <div class="module-head">
            <div class="module-icon orange"><i class="fas fa-clipboard-check"></i></div>
            <div>
                <h2 class="module-title">Evaluaciones</h2>
                <p class="module-subtitle">Supervisa pendientes, aprobadas y resultados por empresa.</p>
            </div>
        </div>
        <div class="metric-row">
            <div class="metric-box">
                <span class="summary-label">Aprobadas</span>
                <strong><?php echo (int) ($stats['evaluaciones_aprobadas'] ?? 0); ?></strong>
            </div>
            <div class="metric-box">
                <span class="summary-label">Reprobadas</span>
                <strong><?php echo (int) ($stats['evaluaciones_reprobadas'] ?? 0); ?></strong>
            </div>
        </div>
        <a href="index.php?page=admin-evaluations" class="card-btn"><i class="fas fa-chart-line"></i> Abrir modulo</a>
    </article>

    <article class="module-card">
        <div class="module-head">
            <div class="module-icon purple"><i class="fas fa-chart-column"></i></div>
            <div>
                <h2 class="module-title">Reportes</h2>
                <p class="module-subtitle">Lee el rendimiento del centro por area tecnica y estado operativo.</p>
            </div>
        </div>
        <div class="metric-row">
            <div class="metric-box">
                <span class="summary-label">Promedio</span>
                <strong><?php echo $stats['evaluaciones_promedio'] !== null ? number_format((float) $stats['evaluaciones_promedio'], 1) : '0.0'; ?></strong>
            </div>
            <div class="metric-box">
                <span class="summary-label">Cupos totales</span>
                <strong><?php echo (int) ($stats['cupos_totales'] ?? 0); ?></strong>
            </div>
        </div>
        <a href="index.php?page=admin-reports" class="card-btn builder-primary-btn"><i class="fas fa-file-lines"></i> Abrir modulo</a>
    </article>

    <article class="module-card">
        <div class="module-head">
            <div class="module-icon blue"><i class="fas fa-layer-group"></i></div>
            <div>
                <h2 class="module-title">Areas tecnicas</h2>
                <p class="module-subtitle">Agrega nuevas areas o reorganiza las existentes del centro.</p>
            </div>
        </div>
        <div class="metric-row">
            <div class="metric-box">
                <span class="summary-label">Activas</span>
                <strong><?php echo count($areaLabels ?? []); ?></strong>
            </div>
            <div class="metric-box">
                <span class="summary-label">Catalogo</span>
                <strong><?php echo count(AreaTecnica::getCatalog()); ?></strong>
            </div>
        </div>
        <a href="index.php?page=admin-areas" class="card-btn"><i class="fas fa-plus-circle"></i> Gestionar areas</a>
    </article>
</section>

<section class="admin-split">
    <article class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="section-title">Estudiantes recientes</h2>
                <p class="section-copy">Los ultimos registros del centro para que ubiques rapido el movimiento nuevo.</p>
            </div>
            <a href="index.php?page=admin-students" class="pill pill-blue"><i class="fas fa-arrow-right"></i> Ver todos</a>
        </div>
        <?php if (!empty($recentStudents)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Matricula</th>
                    <th>Area</th>
                    <th>CV</th>
                    <th>Registro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentStudents as $student): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($student['nombre'] ?? 'Sin nombre'); ?></strong>
                        <div class="muted-line"><?php echo htmlspecialchars($student['correo'] ?? 'Sin correo'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($student['matricula'] ?? 'Sin matricula'); ?></td>
                    <td><?php echo htmlspecialchars($student['area_tecnica'] ?? 'Sin area'); ?></td>
                    <td>
                        <span class="pill <?php echo !empty($student['cv_path']) ? 'pill-green' : 'pill-red'; ?>">
                            <?php echo !empty($student['cv_path']) ? 'Con CV' : 'Sin CV'; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($formatDate($student['created_at'] ?? null)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Todavia no hay estudiantes registrados en este centro.</div>
        <?php endif; ?>
    </article>

    <article class="side-card">
        <div class="side-card-header">
            <div>
                <h2 class="section-title">Atencion inmediata</h2>
                <p class="section-copy">Lo mas util para revisar primero en un sistema de pasantias.</p>
            </div>
        </div>
        <div class="compact-list">
            <div class="compact-item">
                <strong>Estudiantes sin CV</strong>
                <div class="muted-line"><?php echo (int) $studentsWithoutCv; ?> estudiantes aun no pueden postularse correctamente.</div>
                <div class="muted-line"><a href="index.php?page=admin-students&cv=sin_cv">Ir al filtro de CV pendiente</a></div>
            </div>
            <div class="compact-item">
                <strong>Evaluaciones en espera</strong>
                <div class="muted-line"><?php echo (int) ($stats['evaluaciones_pendientes'] ?? 0); ?> evaluaciones siguen abiertas o pendientes de resultado.</div>
                <div class="muted-line"><a href="index.php?page=admin-evaluations&estado=pendiente">Abrir evaluaciones pendientes</a></div>
            </div>
            <?php if (!empty($pendingEvaluations)): ?>
            <div class="compact-item">
                <strong>Ultima actividad</strong>
                <?php $latest = $pendingEvaluations[0]; ?>
                <div class="muted-line"><?php echo htmlspecialchars($latest['estudiante_nombre'] ?? 'Sin estudiante'); ?> en <?php echo htmlspecialchars($latest['empresa_nombre'] ?? 'Sin empresa'); ?></div>
                <div class="muted-line">Estado: <?php echo htmlspecialchars($latest['estado'] ?? 'Sin estado'); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </article>
</section>
