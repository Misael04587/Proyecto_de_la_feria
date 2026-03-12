<?php
$studentsTotal = (int) ($stats['estudiantes'] ?? 0);
$cvCoverage = $studentsTotal > 0 ? round(((int) ($stats['cv_subidos'] ?? 0) / $studentsTotal) * 100) : 0;
$evaluationsTotal = (int) ($stats['evaluaciones_total'] ?? 0);
$approvalRate = $evaluationsTotal > 0 ? round(((int) ($stats['evaluaciones_aprobadas'] ?? 0) / $evaluationsTotal) * 100) : 0;
?>
<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Cobertura de CV</span>
        <div class="summary-value"><?php echo $cvCoverage; ?>%</div>
        <p class="summary-help"><?php echo (int) ($stats['cv_subidos'] ?? 0); ?> de <?php echo $studentsTotal; ?> estudiantes ya subieron su CV.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Tasa de aprobacion</span>
        <div class="summary-value"><?php echo $approvalRate; ?>%</div>
        <p class="summary-help"><?php echo (int) ($stats['evaluaciones_aprobadas'] ?? 0); ?> evaluaciones aprobadas en total.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Pasantias finalizadas</span>
        <div class="summary-value"><?php echo (int) ($stats['asignaciones_finalizadas'] ?? 0); ?></div>
        <p class="summary-help">Procesos de pasantia ya cerrados por el centro.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Pasantias canceladas</span>
        <div class="summary-value"><?php echo (int) ($stats['asignaciones_canceladas'] ?? 0); ?></div>
        <p class="summary-help">Sirve para detectar incidencias del proceso.</p>
    </article>
</section>

<section class="report-grid">
    <article class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="section-title">Matriz por area tecnica</h2>
                <p class="section-copy">Cruce simple de empresas, estudiantes, evaluaciones y pasantias por area.</p>
            </div>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Empresas</th>
                    <th>Estudiantes</th>
                    <th>Con CV</th>
                    <th>Evaluaciones</th>
                    <th>Aprobadas</th>
                    <th>Activas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($areaReport as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['label']); ?></strong><div class="muted-line"><?php echo (int) $row['slots']; ?> cupos</div></td>
                    <td><?php echo (int) $row['companies']; ?></td>
                    <td><?php echo (int) $row['students']; ?></td>
                    <td><?php echo (int) $row['students_cv']; ?></td>
                    <td><?php echo (int) $row['evaluations']; ?></td>
                    <td><?php echo (int) $row['approved']; ?></td>
                    <td><?php echo (int) $row['internships']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>

    <div class="report-grid" style="grid-template-columns:1fr;gap:20px;">
        <article class="side-card">
            <div class="side-card-header">
                <div>
                    <h2 class="section-title">Pendientes de CV</h2>
                    <p class="section-copy">Estudiantes que aun no completan el documento base para postularse.</p>
                </div>
            </div>
            <?php if (!empty($studentsWithoutCvList)): ?>
            <div class="compact-list">
                <?php foreach ($studentsWithoutCvList as $student): ?>
                <div class="compact-item">
                    <strong><?php echo htmlspecialchars($student['nombre'] ?? 'Sin nombre'); ?></strong>
                    <div class="muted-line"><?php echo htmlspecialchars($student['matricula'] ?? 'Sin matricula'); ?> - <?php echo htmlspecialchars($student['area_tecnica'] ?? 'Sin area'); ?></div>
                    <div class="muted-line">Registrado el <?php echo htmlspecialchars($formatDate($student['created_at'] ?? null)); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">No hay estudiantes pendientes de CV.</div>
            <?php endif; ?>
        </article>

        <article class="side-card">
            <div class="side-card-header">
                <div>
                    <h2 class="section-title">Empresas con mas movimiento</h2>
                    <p class="section-copy">Las empresas con mayor cantidad de pasantias activas.</p>
                </div>
            </div>
            <?php if (!empty($topCompanies)): ?>
            <div class="compact-list">
                <?php foreach ($topCompanies as $company): ?>
                <div class="compact-item">
                    <strong><?php echo htmlspecialchars($company['nombre'] ?? 'Sin nombre'); ?></strong>
                    <div class="muted-line"><?php echo htmlspecialchars($company['area_tecnica'] ?? 'Sin area'); ?></div>
                    <div class="muted-line"><?php echo (int) ($company['activas'] ?? 0); ?> activas de <?php echo (int) ($company['cupos'] ?? 0); ?> cupos</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">No hay empresas registradas para este reporte.</div>
            <?php endif; ?>
        </article>
    </div>
</section>
