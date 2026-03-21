<?php
$reportSummary = is_array($reportSummary ?? null) ? $reportSummary : [];
$reportRows = is_array($reportRows ?? null) ? $reportRows : [];
$areaReport = is_array($areaReport ?? null) ? $areaReport : [];
$companyOptions = is_array($companyOptions ?? null) ? $companyOptions : [];
$followUpOptions = is_array($followUpOptions ?? null) ? $followUpOptions : [];
$filters = is_array($filters ?? null) ? $filters : [];
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

$buildReportUrl = function ($export = '') use ($filters) {
    $params = ['page' => 'admin-reports'];

    foreach (['area', 'empresa_id', 'estado', 'seguimiento', 'pasantia', 'fecha_desde', 'fecha_hasta'] as $filterKey) {
        if (!empty($filters[$filterKey])) {
            $params[$filterKey] = $filters[$filterKey];
        }
    }

    if ($export !== '') {
        $params['export'] = $export;
    }

    return 'index.php?' . http_build_query($params);
};
?>
<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Postulaciones filtradas</span>
        <div class="summary-value"><?php echo (int) ($reportSummary['total'] ?? 0); ?></div>
        <p class="summary-help">Registros que cumplen con los filtros actuales.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Aprobadas</span>
        <div class="summary-value"><?php echo (int) ($reportSummary['approved'] ?? 0); ?></div>
        <p class="summary-help">Evaluaciones aprobadas dentro del reporte actual.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Preseleccionadas</span>
        <div class="summary-value"><?php echo (int) ($reportSummary['preselected'] ?? 0); ?></div>
        <p class="summary-help">Candidaturas listas para asignacion.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Con pasantia activa</span>
        <div class="summary-value"><?php echo (int) ($reportSummary['active'] ?? 0); ?></div>
        <p class="summary-help"><?php echo (int) ($reportSummary['assigned'] ?? 0); ?> tienen historial de asignacion.</p>
    </article>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Filtros y exportes</h2>
            <p class="section-copy">Cruza por fecha, area, empresa, estado del examen, seguimiento y pasantia.</p>
        </div>
    </div>
    <form method="GET" class="filter-card">
        <input type="hidden" name="page" value="admin-reports">
        <div class="filter-group">
            <label for="report_area">Area tecnica</label>
            <select name="area" id="report_area">
                <option value="">Todas</option>
                <?php foreach ($areaLabels as $areaKey => $areaLabel): ?>
                <option value="<?php echo htmlspecialchars($areaKey); ?>"<?php echo ($filters['area'] ?? '') === $areaLabel ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($areaLabel); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="report_company">Empresa</label>
            <select name="empresa_id" id="report_company">
                <option value="0">Todas</option>
                <?php foreach ($companyOptions as $companyOption): ?>
                <option value="<?php echo (int) ($companyOption['id'] ?? 0); ?>"<?php echo (int) ($filters['empresa_id'] ?? 0) === (int) ($companyOption['id'] ?? 0) ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($companyOption['nombre'] ?? 'Empresa'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="report_estado">Estado del examen</label>
            <select name="estado" id="report_estado">
                <option value="">Todos</option>
                <option value="pendiente"<?php echo ($filters['estado'] ?? '') === 'pendiente' ? ' selected' : ''; ?>>Pendiente</option>
                <option value="aprobado"<?php echo ($filters['estado'] ?? '') === 'aprobado' ? ' selected' : ''; ?>>Aprobado</option>
                <option value="reprobado"<?php echo ($filters['estado'] ?? '') === 'reprobado' ? ' selected' : ''; ?>>Reprobado</option>
                <option value="anulado"<?php echo ($filters['estado'] ?? '') === 'anulado' ? ' selected' : ''; ?>>Anulado</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="report_seguimiento">Seguimiento</label>
            <select name="seguimiento" id="report_seguimiento">
                <option value="">Todos</option>
                <?php foreach ($followUpOptions as $followUpKey => $followUpLabel): ?>
                <option value="<?php echo htmlspecialchars($followUpKey); ?>"<?php echo ($filters['seguimiento'] ?? '') === $followUpKey ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($followUpLabel); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="report_pasantia">Estado de pasantia</label>
            <select name="pasantia" id="report_pasantia">
                <option value="">Todos</option>
                <option value="activa"<?php echo ($filters['pasantia'] ?? '') === 'activa' ? ' selected' : ''; ?>>Activa</option>
                <option value="finalizada"<?php echo ($filters['pasantia'] ?? '') === 'finalizada' ? ' selected' : ''; ?>>Finalizada</option>
                <option value="cancelada"<?php echo ($filters['pasantia'] ?? '') === 'cancelada' ? ' selected' : ''; ?>>Cancelada</option>
                <option value="sin_pasantia"<?php echo ($filters['pasantia'] ?? '') === 'sin_pasantia' ? ' selected' : ''; ?>>Sin pasantia</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="report_fecha_desde">Fecha desde</label>
            <input type="date" name="fecha_desde" id="report_fecha_desde" value="<?php echo htmlspecialchars((string) ($filters['fecha_desde'] ?? '')); ?>">
        </div>
        <div class="filter-group">
            <label for="report_fecha_hasta">Fecha hasta</label>
            <input type="date" name="fecha_hasta" id="report_fecha_hasta" value="<?php echo htmlspecialchars((string) ($filters['fecha_hasta'] ?? '')); ?>">
        </div>
        <button type="submit" class="card-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="index.php?page=admin-reports" class="question-secondary-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-rotate-left"></i> Limpiar</a>
    </form>

    <div class="report-export-links">
        <a href="<?php echo htmlspecialchars($buildReportUrl('csv')); ?>" class="report-export-btn">
            <i class="fas fa-file-csv"></i> CSV
        </a>
        <a href="<?php echo htmlspecialchars($buildReportUrl('excel')); ?>" class="report-export-btn">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="<?php echo htmlspecialchars($buildReportUrl('pdf')); ?>" class="report-export-btn">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
    </div>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Matriz por area</h2>
            <p class="section-copy">Resumen del comportamiento del proceso sobre el conjunto filtrado.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($areaReport); ?> areas</span>
    </div>
    <?php if (!empty($areaReport)): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Empresas</th>
                    <th>Estudiantes</th>
                    <th>Evaluaciones</th>
                    <th>Aprobadas</th>
                    <th>Preseleccionadas</th>
                    <th>Activas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($areaReport as $areaRow): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($areaRow['label'] ?? 'Sin area'); ?></strong></td>
                    <td><?php echo (int) ($areaRow['companies'] ?? 0); ?></td>
                    <td><?php echo (int) ($areaRow['students'] ?? 0); ?></td>
                    <td><?php echo (int) ($areaRow['evaluations'] ?? 0); ?></td>
                    <td><?php echo (int) ($areaRow['approved'] ?? 0); ?></td>
                    <td><?php echo (int) ($areaRow['preselected'] ?? 0); ?></td>
                    <td><?php echo (int) ($areaRow['internships'] ?? 0); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">No hay datos para construir la matriz con los filtros actuales.</div>
    <?php endif; ?>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Detalle del reporte</h2>
            <p class="section-copy">Cada fila representa una postulacion evaluada dentro del centro.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($reportRows); ?> filas</span>
    </div>
    <?php if (!empty($reportRows)): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Estudiante</th>
                    <th>Empresa</th>
                    <th>Examen</th>
                    <th>Seguimiento</th>
                    <th>Pasantia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $reportRow): ?>
                <?php
                $examClass = ($reportRow['estado'] ?? '') === 'aprobado'
                    ? 'pill-green'
                    : ((($reportRow['estado'] ?? '') === 'pendiente')
                        ? 'pill-orange'
                        : ((($reportRow['estado'] ?? '') === 'reprobado') ? 'pill-red' : 'pill-neutral'));
                $followUpItem = $followUpMeta[$reportRow['seguimiento_estado'] ?? ''] ?? ['label' => 'Sin seguimiento', 'class' => 'pill-neutral'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($formatDate($reportRow['fecha_evaluacion'] ?? null, true)); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($reportRow['estudiante_nombre'] ?? 'Sin estudiante'); ?></strong>
                        <div class="muted-line"><?php echo htmlspecialchars($reportRow['matricula'] ?? 'Sin matricula'); ?></div>
                        <div class="muted-line"><?php echo htmlspecialchars($reportRow['area_tecnica'] ?? 'Sin area'); ?></div>
                    </td>
                    <td><strong><?php echo htmlspecialchars($reportRow['empresa_nombre'] ?? 'Sin empresa'); ?></strong></td>
                    <td>
                        <span class="pill <?php echo $examClass; ?>"><?php echo htmlspecialchars(ucfirst((string) ($reportRow['estado'] ?? 'sin estado'))); ?></span>
                        <div class="muted-line">Nota: <?php echo $reportRow['nota'] !== null ? htmlspecialchars(number_format((float) $reportRow['nota'], 1)) : 'Sin nota'; ?></div>
                    </td>
                    <td>
                        <?php if (($reportRow['estado'] ?? '') === 'aprobado'): ?>
                        <span class="pill <?php echo htmlspecialchars($followUpItem['class']); ?>"><?php echo htmlspecialchars($followUpItem['label']); ?></span>
                        <?php if (!empty($reportRow['seguimiento_comentario'])): ?>
                        <div class="muted-line"><?php echo htmlspecialchars($reportRow['seguimiento_comentario']); ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="pill pill-neutral">No aplica</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($reportRow['asignacion_estado'])): ?>
                        <span class="pill <?php echo ($reportRow['asignacion_estado'] ?? '') === 'activa' ? 'pill-blue' : (($reportRow['asignacion_estado'] ?? '') === 'finalizada' ? 'pill-green' : 'pill-red'); ?>">
                            <?php echo htmlspecialchars(ucfirst((string) ($reportRow['asignacion_estado'] ?? ''))); ?>
                        </span>
                        <?php if (!empty($reportRow['fecha_asignacion'])): ?>
                        <div class="muted-line">Desde <?php echo htmlspecialchars($formatDate($reportRow['fecha_asignacion'] ?? null, false)); ?></div>
                        <?php endif; ?>
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
    <div class="empty-state">No hay postulaciones que coincidan con los filtros del reporte.</div>
    <?php endif; ?>
</section>
