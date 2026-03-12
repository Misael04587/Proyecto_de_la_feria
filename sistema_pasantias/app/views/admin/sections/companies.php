<?php
$totalCompanies = (int) ($stats['empresas'] ?? 0);
$totalSlots = (int) ($stats['cupos_totales'] ?? 0);
$activeAssignments = (int) ($stats['asignaciones_activas'] ?? 0);
$freeSlots = max($totalSlots - $activeAssignments, 0);
?>
<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Empresas registradas</span>
        <div class="summary-value"><?php echo $totalCompanies; ?></div>
        <p class="summary-help"><?php echo (int) ($stats['empresas_disponibles'] ?? 0); ?> estan disponibles para pasantias.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Cupos totales</span>
        <div class="summary-value"><?php echo $totalSlots; ?></div>
        <p class="summary-help"><?php echo $freeSlots; ?> cupos siguen libres al dia de hoy.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Asignaciones activas</span>
        <div class="summary-value"><?php echo $activeAssignments; ?></div>
        <p class="summary-help">Cupos ya ocupados por estudiantes aprobados.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Empresas completas</span>
        <div class="summary-value"><?php echo (int) ($stats['empresas_completas'] ?? 0); ?></div>
        <p class="summary-help">Estas empresas ya llenaron todos sus cupos.</p>
    </article>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Filtro de empresas</h2>
            <p class="section-copy">Ajusta el listado por area tecnica o por estado.</p>
        </div>
    </div>
    <form method="GET" class="filter-card">
        <input type="hidden" name="page" value="admin-companies">
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
                <option value="disponible"<?php echo ($filters['estado'] ?? '') === 'disponible' ? ' selected' : ''; ?>>Disponible</option>
                <option value="completo"<?php echo ($filters['estado'] ?? '') === 'completo' ? ' selected' : ''; ?>>Completo</option>
            </select>
        </div>
        <button type="submit" class="card-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="index.php?page=admin-companies" class="secondary-inline-btn" style="width:auto;padding:14px 20px;"><i class="fas fa-rotate-left"></i> Limpiar</a>
    </form>
</section>

<section class="admin-split">
    <article class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="section-title">Listado de empresas</h2>
                <p class="section-copy">Empresas disponibles para el proceso de pasantias del centro.</p>
            </div>
            <span class="pill pill-blue"><?php echo count($companies); ?> resultados</span>
        </div>
        <?php if (!empty($companies)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Area</th>
                    <th>Cupos</th>
                    <th>Evaluaciones</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                <?php
                $assigned = (int) ($company['asignados_actuales'] ?? 0);
                $available = max((int) ($company['cupos'] ?? 0) - $assigned, 0);
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($company['nombre'] ?? 'Sin nombre'); ?></strong>
                        <div class="muted-line"><?php echo htmlspecialchars($company['direccion'] ?? 'Sin direccion'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($company['area_tecnica'] ?? 'Sin area'); ?></td>
                    <td>
                        <strong><?php echo (int) ($company['cupos'] ?? 0); ?></strong>
                        <div class="muted-line"><?php echo $available; ?> libres / <?php echo $assigned; ?> ocupados</div>
                    </td>
                    <td>
                        <strong><?php echo (int) ($company['evaluaciones_total'] ?? 0); ?></strong>
                        <div class="muted-line"><?php echo (int) ($company['evaluaciones_aprobadas'] ?? 0); ?> aprobadas</div>
                    </td>
                    <td>
                        <span class="pill <?php echo ($company['estado'] ?? '') === 'disponible' ? 'pill-green' : 'pill-orange'; ?>">
                            <?php echo htmlspecialchars(ucfirst($company['estado'] ?? 'sin estado')); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">No hay empresas que coincidan con el filtro aplicado.</div>
        <?php endif; ?>
    </article>

    <article class="side-card">
        <div class="side-card-header">
            <div>
                <h2 class="section-title">Resumen por area</h2>
                <p class="section-copy">Cuantas empresas y cupos sostiene cada area tecnica.</p>
            </div>
        </div>
        <?php if (!empty($areaRows)): ?>
        <div class="compact-list">
            <?php foreach ($areaRows as $row): ?>
            <div class="compact-item">
                <strong><?php echo htmlspecialchars($row['area_tecnica'] ?? 'Sin area'); ?></strong>
                <div class="muted-line"><?php echo (int) ($row['total'] ?? 0); ?> empresas</div>
                <div class="muted-line"><?php echo (int) ($row['cupos'] ?? 0); ?> cupos totales</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Todavia no hay datos de empresas para este centro.</div>
        <?php endif; ?>
    </article>
</section>
