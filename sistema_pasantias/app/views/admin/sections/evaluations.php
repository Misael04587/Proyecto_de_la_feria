<?php
$followUpOptions = is_array($followUpOptions ?? null) ? $followUpOptions : [];
$companyOptions = is_array($companyOptions ?? null) ? $companyOptions : [];
$followUpMeta = [
    'sin_revisar' => ['label' => 'Pendiente de revision', 'class' => 'pill-orange'],
    'en_revision' => ['label' => 'En revision', 'class' => 'pill-blue'],
    'preseleccionado' => ['label' => 'Preseleccionado', 'class' => 'pill-green'],
    'descartado' => ['label' => 'Descartado', 'class' => 'pill-red'],
];
$getFollowUpMeta = function ($state) use ($followUpMeta) {
    return $followUpMeta[$state] ?? ['label' => 'Sin seguimiento', 'class' => 'pill-neutral'];
};
?>
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
        <div class="filter-group">
            <label for="seguimiento">Seguimiento</label>
            <select name="seguimiento" id="seguimiento">
                <option value="">Todos</option>
                <?php foreach ($followUpOptions as $followUpKey => $followUpLabel): ?>
                <option value="<?php echo htmlspecialchars($followUpKey); ?>"<?php echo ($filters['seguimiento'] ?? '') === $followUpKey ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($followUpLabel); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="empresa_id">Empresa</label>
            <select name="empresa_id" id="empresa_id">
                <option value="0">Todas</option>
                <?php foreach ($companyOptions as $companyOption): ?>
                <option value="<?php echo (int) ($companyOption['id'] ?? 0); ?>"<?php echo (int) ($filters['empresa_id'] ?? 0) === (int) ($companyOption['id'] ?? 0) ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($companyOption['nombre'] ?? 'Empresa'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="fecha_desde">Fecha desde</label>
            <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo htmlspecialchars((string) ($filters['fecha_desde'] ?? '')); ?>">
        </div>
        <div class="filter-group">
            <label for="fecha_hasta">Fecha hasta</label>
            <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo htmlspecialchars((string) ($filters['fecha_hasta'] ?? '')); ?>">
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
            $followUpState = (string) ($evaluation['seguimiento_estado'] ?? 'sin_revisar');
            $followUpItem = $getFollowUpMeta($followUpState);
            $hasAssignment = !empty($evaluation['asignacion_id']);
            $studentHasActiveInternship = (int) ($evaluation['estudiante_pasantias_activas'] ?? 0) > 0;
            $companySlots = (int) ($evaluation['empresa_cupos'] ?? 0);
            $companyActiveAssignments = (int) ($evaluation['empresa_asignaciones_activas'] ?? 0);
            $companyHasRoom = $companyActiveAssignments < $companySlots;
            $canAssign = $state === 'aprobado'
                && $followUpState === 'preseleccionado'
                && !$hasAssignment
                && !$studentHasActiveInternship
                && $companyHasRoom;
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
                    <span class="pill <?php echo htmlspecialchars($followUpItem['class']); ?>">
                        <?php echo htmlspecialchars($followUpItem['label']); ?>
                    </span>
                    <?php if (!empty($evaluation['seguimiento_comentario'])): ?>
                    <div class="muted-line"><?php echo htmlspecialchars($evaluation['seguimiento_comentario']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($evaluation['seguimiento_fecha'])): ?>
                    <div class="muted-line">Actualizado el <?php echo htmlspecialchars($formatDate($evaluation['seguimiento_fecha'], true)); ?></div>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="pill pill-neutral">Sin seguimiento</span>
                    <?php endif; ?>
                </td>
                <td class="evaluation-actions-cell">
                    <?php if ($state === 'aprobado'): ?>
                    <details class="evaluation-review-card">
                        <summary class="evaluation-review-summary">
                            <i class="fas fa-user-check"></i> Revisar candidatura
                        </summary>
                        <form method="POST" action="index.php?page=admin-evaluations" class="evaluation-review-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="intent" value="save_evaluation_review">
                            <input type="hidden" name="evaluation_id" value="<?php echo (int) ($evaluation['id'] ?? 0); ?>">
                            <select name="seguimiento_estado" required>
                                <?php foreach ($followUpOptions as $followUpKey => $followUpLabel): ?>
                                <option value="<?php echo htmlspecialchars($followUpKey); ?>"<?php echo $followUpState === $followUpKey ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($followUpLabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <textarea
                                name="seguimiento_comentario"
                                rows="4"
                                maxlength="2000"
                                placeholder="Anota observaciones del coordinador sobre el proceso del estudiante."><?php echo htmlspecialchars((string) ($evaluation['seguimiento_comentario'] ?? '')); ?></textarea>
                            <button type="submit" class="evaluation-action-btn">
                                <i class="fas fa-floppy-disk"></i> Guardar seguimiento
                            </button>
                        </form>
                    </details>

                    <?php if ($canAssign): ?>
                    <form method="POST" action="index.php?page=admin-evaluations" class="evaluation-action-form" style="margin-top: 10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                        <input type="hidden" name="intent" value="assign_evaluation">
                        <input type="hidden" name="evaluation_id" value="<?php echo (int) ($evaluation['id'] ?? 0); ?>">
                        <button type="submit" class="evaluation-action-btn">
                            <i class="fas fa-briefcase"></i> Asignar pasantia
                        </button>
                    </form>
                    <?php elseif (!$hasAssignment): ?>
                    <div class="evaluation-note" style="margin-top: 10px;">
                        <?php if ($followUpState !== 'preseleccionado'): ?>
                        Debes dejarla como preseleccionada antes de asignarla.
                        <?php elseif ($studentHasActiveInternship): ?>
                        El estudiante ya tiene otra pasantia activa.
                        <?php elseif (!$companyHasRoom): ?>
                        La empresa ya completo sus cupos.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="muted-line">Solo aplica seguimiento a evaluaciones aprobadas.</span>
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
