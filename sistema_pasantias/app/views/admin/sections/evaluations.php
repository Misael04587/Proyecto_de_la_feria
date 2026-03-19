<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Total de evaluaciones</span>
        <div class="summary-value"><?php echo (int) ($stats['evaluaciones_total'] ?? 0); ?></div>
        <p class="summary-help">Historial general de evaluaciones registradas en el centro.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Pendientes</span>
        <div class="summary-value"><?php echo (int) ($stats['evaluaciones_pendientes'] ?? 0); ?></div>
        <p class="summary-help">Las que aun requieren seguimiento o cierre.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Aprobadas</span>
        <div class="summary-value"><?php echo (int) ($stats['evaluaciones_aprobadas'] ?? 0); ?></div>
        <p class="summary-help">Evaluaciones que derivaron en un resultado favorable.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Promedio general</span>
        <div class="summary-value"><?php echo $stats['evaluaciones_promedio'] !== null ? number_format((float) $stats['evaluaciones_promedio'], 1) : '0.0'; ?></div>
        <p class="summary-help">Promedio de nota del centro en todos los intentos registrados.</p>
    </article>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Filtro de evaluaciones</h2>
            <p class="section-copy">Busca por area tecnica o por estado del resultado.</p>
        </div>
    </div>
    <form method="GET" class="filter-card">
        <input type="hidden" name="page" value="admin-evaluations">
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
            <label for="estado">Estado</label>
            <select name="estado" id="estado">
                <option value="">Todos</option>
                <option value="pendiente"<?php echo ($filters['estado'] ?? '') === 'pendiente' ? ' selected' : ''; ?>>Pendiente</option>
                <option value="aprobado"<?php echo ($filters['estado'] ?? '') === 'aprobado' ? ' selected' : ''; ?>>Aprobado</option>
                <option value="reprobado"<?php echo ($filters['estado'] ?? '') === 'reprobado' ? ' selected' : ''; ?>>Reprobado</option>
                <option value="anulado"<?php echo ($filters['estado'] ?? '') === 'anulado' ? ' selected' : ''; ?>>Anulado</option>
            </select>
        </div>
        <button type="submit" class="card-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="index.php?page=admin-evaluations" class="secondary-inline-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-rotate-left"></i> Limpiar</a>
    </form>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Listado de evaluaciones</h2>
            <p class="section-copy">Revisa rapidamente quien evaluo, en que empresa y cual fue el resultado.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($evaluations); ?> resultados</span>
    </div>
    <?php if (!empty($evaluations)): ?>
        <table class="data-table">
        <thead>
            <tr>
                <th>Estudiante</th>
                <th>Empresa</th>
                <th>Resultado</th>
                <th>Nota</th>
                <th>Seguimiento</th>
                <th>Acciones</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($evaluations as $evaluation): ?>
            <?php
            $state = (string) ($evaluation['estado'] ?? '');
            $pillClass = $state === 'aprobado'
                ? 'pill-green'
                : ($state === 'pendiente'
                    ? 'pill-orange'
                    : ($state === 'reprobado' ? 'pill-red' : 'pill-neutral'));
            $hasAssignment = !empty($evaluation['asignacion_id']);
            $studentHasActiveInternship = (int) ($evaluation['estudiante_pasantias_activas'] ?? 0) > 0;
            $companySlots = (int) ($evaluation['empresa_cupos'] ?? 0);
            $companyActiveAssignments = (int) ($evaluation['empresa_asignaciones_activas'] ?? 0);
            $companyHasRoom = $companyActiveAssignments < $companySlots;
            $canAssign = $state === 'aprobado' && !$hasAssignment && !$studentHasActiveInternship && $companyHasRoom;
            ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($evaluation['estudiante_nombre'] ?? 'Sin estudiante'); ?></strong>
                    <div class="muted-line"><?php echo htmlspecialchars($evaluation['matricula'] ?? 'Sin matricula'); ?></div>
                    <div class="muted-line"><?php echo htmlspecialchars($evaluation['area_tecnica'] ?? 'Sin area'); ?></div>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($evaluation['empresa_nombre'] ?? 'Sin empresa'); ?></strong>
                    <div class="muted-line"><?php echo $companyActiveAssignments; ?> / <?php echo $companySlots; ?> cupos ocupados</div>
                </td>
                <td><span class="pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars(ucfirst($state !== '' ? $state : 'sin estado')); ?></span></td>
                <td><?php echo $evaluation['nota'] !== null ? htmlspecialchars(number_format((float) $evaluation['nota'], 1)) : 'Sin nota'; ?></td>
                <td>
                    <?php if (!empty($evaluation['asignacion_estado'])): ?>
                    <span class="pill <?php echo ($evaluation['asignacion_estado'] ?? '') === 'activa' ? 'pill-blue' : (($evaluation['asignacion_estado'] ?? '') === 'finalizada' ? 'pill-green' : 'pill-red'); ?>">
                        <?php echo htmlspecialchars('Pasantia ' . $evaluation['asignacion_estado']); ?>
                    </span>
                    <?php elseif ($state === 'aprobado'): ?>
                    <span class="pill pill-orange">Pendiente de asignacion</span>
                    <div class="muted-line">El examen fue aprobado y espera revision del centro.</div>
                    <?php else: ?>
                    <span class="pill pill-neutral">Sin seguimiento</span>
                    <?php endif; ?>
                </td>
                <td class="evaluation-actions-cell">
                    <?php if ($canAssign): ?>
                    <form method="POST" action="index.php?page=admin-evaluations" class="evaluation-action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                        <input type="hidden" name="intent" value="assign_evaluation">
                        <input type="hidden" name="evaluation_id" value="<?php echo (int) ($evaluation['id'] ?? 0); ?>">
                        <button type="submit" class="evaluation-action-btn">
                            <i class="fas fa-briefcase"></i> Asignar pasantia
                        </button>
                    </form>
                    <?php elseif ($state === 'aprobado' && !$hasAssignment): ?>
                    <div class="evaluation-note">
                        <?php if ($studentHasActiveInternship): ?>
                        El estudiante ya tiene otra pasantia activa.
                        <?php elseif (!$companyHasRoom): ?>
                        La empresa ya completo sus cupos.
                        <?php else: ?>
                        La evaluacion ya esta en revision.
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span class="muted-line">Sin accion disponible</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($formatDate($evaluation['tiempo_fin'] ?? $evaluation['tiempo_inicio'] ?? $evaluation['created_at'] ?? null, true)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">No hay evaluaciones que coincidan con los filtros aplicados.</div>
    <?php endif; ?>
</section>
