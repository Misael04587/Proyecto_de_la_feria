<section class="summary-strip">
    <article class="summary-card">
        <span class="summary-label">Areas activas</span>
        <div class="summary-value"><?php echo (int) ($areaSummary['assigned'] ?? 0); ?></div>
        <p class="summary-help">Catalogo habilitado ahora mismo para este centro.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Con estudiantes</span>
        <div class="summary-value"><?php echo (int) ($areaSummary['with_students'] ?? 0); ?></div>
        <p class="summary-help">Areas que ya tienen matriculas registradas.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Con empresas</span>
        <div class="summary-value"><?php echo (int) ($areaSummary['with_companies'] ?? 0); ?></div>
        <p class="summary-help">Areas que ya estan siendo usadas por empresas del centro.</p>
    </article>
    <article class="summary-card">
        <span class="summary-label">Se pueden quitar</span>
        <div class="summary-value"><?php echo (int) ($areaSummary['removable'] ?? 0); ?></div>
        <p class="summary-help">Solo se permiten bajas cuando el area no tiene uso asociado.</p>
    </article>
</section>

<section class="admin-split">
    <article class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="section-title">Agregar area tecnica</h2>
                <p class="section-copy">Escribe una nueva area o activa una que ya exista en el catalogo general.</p>
            </div>
        </div>

        <form method="POST" class="area-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
            <input type="hidden" name="intent" value="add">

            <div class="filter-group area-input-group">
                <label for="area_name">Nombre del area</label>
                <input
                    type="text"
                    id="area_name"
                    name="area_name"
                    class="area-text-input"
                    placeholder="Ej: Mecanica automotriz"
                    maxlength="100"
                    required
                >
            </div>

            <button type="submit" class="card-btn area-submit-btn">
                <i class="fas fa-plus"></i>
                Agregar area
            </button>
        </form>

        <?php if (!empty($availableCatalogAreas)): ?>
        <div class="area-suggestions">
            <span class="summary-label">Agregar desde el catalogo</span>
            <div class="area-chip-list">
                <?php foreach ($availableCatalogAreas as $areaOption): ?>
                <form method="POST" class="area-chip-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                    <input type="hidden" name="intent" value="add">
                    <input type="hidden" name="area_name" value="<?php echo htmlspecialchars($areaOption); ?>">
                    <button type="submit" class="area-chip-btn"><?php echo htmlspecialchars($areaOption); ?></button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">Todas las areas del catalogo ya estan activas en este centro.</div>
        <?php endif; ?>
    </article>

    <article class="side-card area-note-card">
        <div class="side-card-header">
            <div>
                <h2 class="section-title">Reglas del modulo</h2>
                <p class="section-copy">El sistema protege la integridad de estudiantes, empresas y reportes.</p>
            </div>
        </div>
        <div class="compact-list">
            <div class="compact-item">
                <strong>Puedes crear areas nuevas</strong>
                <div class="muted-line">Si el nombre no existe todavia, se agrega al catalogo del sistema y queda disponible para tu centro.</div>
            </div>
            <div class="compact-item">
                <strong>La baja tiene validacion</strong>
                <div class="muted-line">No se puede quitar un area que ya tenga estudiantes, empresas, evaluaciones o pasantias activas asociadas.</div>
            </div>
            <div class="compact-item">
                <strong>Importante para examenes</strong>
                <div class="muted-line">Si agregas un area nueva, luego debes cargar preguntas para que los examenes de esa area puedan funcionar.</div>
            </div>
        </div>
    </article>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Areas del centro</h2>
            <p class="section-copy">Gestiona rapidamente las areas habilitadas para registros, empresas y reportes.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($areasOverview ?? []); ?> activas</span>
    </div>

    <?php if (!empty($areasOverview)): ?>
    <div class="area-admin-list">
        <?php foreach ($areasOverview as $area): ?>
        <?php
        $hasUsage = (int) ($area['students'] ?? 0) > 0
            || (int) ($area['companies'] ?? 0) > 0
            || (int) ($area['evaluations'] ?? 0) > 0
            || (int) ($area['active_assignments'] ?? 0) > 0;
        ?>
        <article class="area-admin-item">
            <div class="area-admin-head">
                <div>
                    <h3><?php echo htmlspecialchars($area['label'] ?? 'Sin area'); ?></h3>
                    <p><?php echo $hasUsage ? 'Area con uso dentro del centro.' : 'Area libre para reorganizacion.'; ?></p>
                </div>
                <span class="pill <?php echo $hasUsage ? 'pill-orange' : 'pill-green'; ?>">
                    <?php echo $hasUsage ? 'En uso' : 'Libre'; ?>
                </span>
            </div>

            <div class="area-admin-metrics">
                <div class="metric-box">
                    <span class="summary-label">Estudiantes</span>
                    <strong><?php echo (int) ($area['students'] ?? 0); ?></strong>
                </div>
                <div class="metric-box">
                    <span class="summary-label">Empresas</span>
                    <strong><?php echo (int) ($area['companies'] ?? 0); ?></strong>
                </div>
                <div class="metric-box">
                    <span class="summary-label">Evaluaciones</span>
                    <strong><?php echo (int) ($area['evaluations'] ?? 0); ?></strong>
                </div>
                <div class="metric-box">
                    <span class="summary-label">Pasantias activas</span>
                    <strong><?php echo (int) ($area['active_assignments'] ?? 0); ?></strong>
                </div>
            </div>

            <div class="area-admin-footer">
                <div class="muted-line">
                    <?php echo !empty($area['removable'])
                        ? 'Se puede quitar sin afectar registros existentes.'
                        : htmlspecialchars($area['removal_reason'] ?? 'No se puede quitar ahora mismo.'); ?>
                </div>

                <form method="POST" class="area-remove-form" onsubmit="return confirm('Quieres quitar esta area tecnica del centro?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                    <input type="hidden" name="intent" value="remove">
                    <input type="hidden" name="area_name" value="<?php echo htmlspecialchars($area['label'] ?? ''); ?>">
                    <button
                        type="submit"
                        class="area-remove-btn"
                        <?php echo empty($area['removable']) ? 'disabled' : ''; ?>
                    >
                        <i class="fas fa-trash-can"></i>
                        Quitar
                    </button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">Todavia no hay areas registradas para este centro.</div>
    <?php endif; ?>
</section>
