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
            ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($evaluation['estudiante_nombre'] ?? 'Sin estudiante'); ?></strong>
                    <div class="muted-line"><?php echo htmlspecialchars($evaluation['matricula'] ?? 'Sin matricula'); ?></div>
                    <div class="muted-line"><?php echo htmlspecialchars($evaluation['area_tecnica'] ?? 'Sin area'); ?></div>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($evaluation['empresa_nombre'] ?? 'Sin empresa'); ?></strong>
                    <div class="muted-line">
                        <?php if (!empty($evaluation['asignacion_estado'])): ?>
                        Pasantia <?php echo htmlspecialchars($evaluation['asignacion_estado']); ?>
                        <?php else: ?>
                        Sin asignacion
                        <?php endif; ?>
                    </div>
                </td>
                <td><span class="pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars(ucfirst($state !== '' ? $state : 'sin estado')); ?></span></td>
                <td><?php echo $evaluation['nota'] !== null ? htmlspecialchars(number_format((float) $evaluation['nota'], 1)) : 'Sin nota'; ?></td>
                <td><?php echo htmlspecialchars($formatDate($evaluation['tiempo_fin'] ?? $evaluation['tiempo_inicio'] ?? $evaluation['created_at'] ?? null, true)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">No hay evaluaciones que coincidan con los filtros aplicados.</div>
    <?php endif; ?>
</section>
