<?php
$totalCompanies = (int) ($stats['empresas'] ?? 0);
$totalSlots = (int) ($stats['cupos_totales'] ?? 0);
$activeAssignments = (int) ($stats['asignaciones_activas'] ?? 0);
$freeSlots = max($totalSlots - $activeAssignments, 0);
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

$companyFormData = is_array($companyFormData ?? null) ? $companyFormData : [];
$companyFormData = array_merge([
    'id' => 0,
    'nombre' => '',
    'direccion' => '',
    'area_tecnica' => '',
    'cupos' => 5,
    'descripcion' => '',
    'requisitos' => '',
    'estado' => 'disponible',
], $companyFormData);

$isEditingCompany = (int) ($companyFormData['id'] ?? 0) > 0;
$formTitle = $isEditingCompany ? 'Editar empresa' : 'Registrar empresa';
$formCopy = $isEditingCompany
    ? 'Actualiza los datos de la empresa seleccionada sin salir del panel.'
    : 'Crea una empresa nueva para que pueda recibir postulaciones del centro.';
$formIntent = $isEditingCompany ? 'update_company' : 'create_company';
$formSubmitLabel = $isEditingCompany ? 'Guardar cambios' : 'Registrar empresa';
$followUpMeta = [
    'sin_revisar' => ['label' => 'Pendiente de revision', 'class' => 'pill-orange'],
    'en_revision' => ['label' => 'En revision', 'class' => 'pill-blue'],
    'preseleccionado' => ['label' => 'Preseleccionado', 'class' => 'pill-green'],
    'descartado' => ['label' => 'Descartado', 'class' => 'pill-red'],
];
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

<section class="admin-split">
    <article class="table-card">
        <div class="table-card-header">
            <div>
                <h2 class="section-title"><?php echo htmlspecialchars($formTitle); ?></h2>
                <p class="section-copy"><?php echo htmlspecialchars($formCopy); ?></p>
            </div>
            <span class="pill <?php echo $isEditingCompany ? 'pill-orange' : 'pill-blue'; ?>">
                <?php echo $isEditingCompany ? 'Modo edicion' : 'Nuevo registro'; ?>
            </span>
        </div>

        <form method="POST" class="company-form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
            <input type="hidden" name="intent" value="<?php echo htmlspecialchars($formIntent); ?>">
            <?php if ($isEditingCompany): ?>
            <input type="hidden" name="company_id" value="<?php echo (int) ($companyFormData['id'] ?? 0); ?>">
            <?php endif; ?>

            <div class="filter-group">
                <label for="company_name">Nombre de la empresa</label>
                <input
                    type="text"
                    id="company_name"
                    name="nombre"
                    maxlength="150"
                    value="<?php echo htmlspecialchars((string) ($companyFormData['nombre'] ?? '')); ?>"
                    placeholder="Ej: TechNova Solutions"
                    required
                >
            </div>

            <div class="filter-group">
                <label for="company_area">Area tecnica</label>
                <select name="area_tecnica" id="company_area" required>
                    <option value="">Selecciona un area</option>
                    <?php foreach ($areaLabels as $areaLabel): ?>
                    <option value="<?php echo htmlspecialchars($areaLabel); ?>"<?php echo ($companyFormData['area_tecnica'] ?? '') === $areaLabel ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($areaLabel); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="company_slots">Cupos</label>
                <input
                    type="number"
                    id="company_slots"
                    name="cupos"
                    min="1"
                    max="999"
                    value="<?php echo (int) ($companyFormData['cupos'] ?? 5); ?>"
                    required
                >
            </div>

            <div class="filter-group">
                <label for="company_state">Estado</label>
                <select name="estado" id="company_state" required>
                    <option value="disponible"<?php echo ($companyFormData['estado'] ?? 'disponible') === 'disponible' ? ' selected' : ''; ?>>Disponible</option>
                    <option value="completo"<?php echo ($companyFormData['estado'] ?? '') === 'completo' ? ' selected' : ''; ?>>Completo</option>
                </select>
            </div>

            <div class="filter-group full-width">
                <label for="company_address">Direccion</label>
                <input
                    type="text"
                    id="company_address"
                    name="direccion"
                    maxlength="200"
                    value="<?php echo htmlspecialchars((string) ($companyFormData['direccion'] ?? '')); ?>"
                    placeholder="Ej: Av. Principal #25, Santo Domingo"
                >
            </div>

            <div class="filter-group full-width">
                <label for="company_description">Descripcion</label>
                <textarea
                    id="company_description"
                    name="descripcion"
                    rows="5"
                    maxlength="3000"
                    class="company-form-textarea"
                    placeholder="Describe a que se dedica la empresa y que tipo de experiencia ofrece."><?php echo htmlspecialchars((string) ($companyFormData['descripcion'] ?? '')); ?></textarea>
            </div>

            <div class="filter-group full-width">
                <label for="company_requirements">Requisitos</label>
                <textarea
                    id="company_requirements"
                    name="requisitos"
                    rows="5"
                    maxlength="3000"
                    class="company-form-textarea"
                    placeholder="Ej: manejo de Office, buena comunicacion, puntualidad, conocimientos tecnicos basicos."><?php echo htmlspecialchars((string) ($companyFormData['requisitos'] ?? '')); ?></textarea>
            </div>

            <div class="company-form-actions full-width">
                <button type="submit" class="card-btn company-primary-btn">
                    <i class="fas <?php echo $isEditingCompany ? 'fa-floppy-disk' : 'fa-building-circle-check'; ?>"></i>
                    <?php echo htmlspecialchars($formSubmitLabel); ?>
                </button>

                <?php if ($isEditingCompany): ?>
                <a href="index.php?page=admin-companies" class="company-secondary-btn">
                    <i class="fas fa-xmark"></i>
                    Cancelar edicion
                </a>
                <?php endif; ?>
            </div>
        </form>
    </article>

    <div class="report-side-stack">
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

        <article class="side-card">
            <div class="side-card-header">
                <div>
                    <h2 class="section-title">Reglas del modulo</h2>
                    <p class="section-copy">Estas validaciones mantienen consistente el flujo de pasantias.</p>
                </div>
            </div>
            <div class="compact-list">
                <div class="compact-item">
                    <strong>Area valida</strong>
                    <div class="muted-line">Solo puedes registrar empresas en areas tecnicas activas para tu centro.</div>
                </div>
                <div class="compact-item">
                    <strong>Eliminacion protegida</strong>
                    <div class="muted-line">Una empresa con historial de evaluaciones o pasantias no se puede borrar.</div>
                </div>
                <div class="compact-item">
                    <strong>Cupos coherentes</strong>
                    <div class="muted-line">No se puede bajar la capacidad por debajo de las pasantias activas que ya tiene la empresa.</div>
                </div>
            </div>
        </article>
    </div>
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
        <a href="index.php?page=admin-companies" class="company-secondary-btn">
            <i class="fas fa-rotate-left"></i>
            Limpiar
        </a>
    </form>
</section>

<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Listado de empresas</h2>
            <p class="section-copy">Empresas disponibles para el proceso de pasantias del centro.</p>
        </div>
        <span class="pill pill-blue"><?php echo count($companies); ?> resultados</span>
    </div>
    <?php if (!empty($companies)): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Area</th>
                    <th>Cupos</th>
                    <th>Evaluaciones</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                <?php
                $assigned = (int) ($company['asignados_actuales'] ?? 0);
                $available = max((int) ($company['cupos'] ?? 0) - $assigned, 0);
                $hasHistory = (int) ($company['evaluaciones_total'] ?? 0) > 0 || (int) ($company['asignaciones_total'] ?? 0) > 0;
                $descriptionPreview = trim((string) ($company['descripcion'] ?? ''));
                if ($descriptionPreview !== '') {
                    $descriptionPreview = function_exists('mb_substr')
                        ? mb_substr($descriptionPreview, 0, 140, 'UTF-8')
                        : substr($descriptionPreview, 0, 140);

                    if ($descriptionPreview !== trim((string) ($company['descripcion'] ?? ''))) {
                        $descriptionPreview .= '...';
                    }
                }
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($company['nombre'] ?? 'Sin nombre'); ?></strong>
                        <div class="muted-line"><?php echo htmlspecialchars($company['direccion'] ?? 'Sin direccion'); ?></div>
                        <?php if ($descriptionPreview !== ''): ?>
                        <div class="muted-line"><?php echo htmlspecialchars($descriptionPreview); ?></div>
                        <?php endif; ?>
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
                    <td class="company-actions-cell">
                        <div class="company-inline-actions">
                            <a href="index.php?page=admin-companies&edit=<?php echo (int) ($company['id'] ?? 0); ?>" class="company-edit-btn">
                                <i class="fas fa-pen"></i>
                                Editar
                            </a>
                            <a href="index.php?page=admin-companies&history=<?php echo (int) ($company['id'] ?? 0); ?>" class="company-edit-btn">
                                <i class="fas fa-clock-rotate-left"></i>
                                Historial
                            </a>
                            <form
                                method="POST"
                                action="index.php?page=admin-companies"
                                class="company-delete-form"
                                onsubmit="return confirm('Esta accion eliminara la empresa si no tiene historial asociado. Deseas continuar?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="intent" value="delete_company">
                                <input type="hidden" name="company_id" value="<?php echo (int) ($company['id'] ?? 0); ?>">
                                <button type="submit" class="company-delete-btn" <?php echo $hasHistory ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i>
                                    Eliminar
                                </button>
                            </form>
                        </div>
                        <?php if ($hasHistory): ?>
                        <div class="muted-line company-history-note">Tiene historial asociado y no se puede borrar.</div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">No hay empresas que coincidan con el filtro aplicado.</div>
    <?php endif; ?>
</section>

<?php if (!empty($selectedCompanyHistory)): ?>
<section class="table-card">
    <div class="table-card-header">
        <div>
            <h2 class="section-title">Historial de la empresa</h2>
            <p class="section-copy"><?php echo htmlspecialchars($selectedCompanyHistory['nombre'] ?? 'Empresa'); ?> - <?php echo htmlspecialchars($selectedCompanyHistory['area_tecnica'] ?? 'Sin area'); ?></p>
        </div>
        <a href="index.php?page=admin-companies" class="company-secondary-btn">
            <i class="fas fa-xmark"></i>
            Cerrar historial
        </a>
    </div>
    <?php if (!empty($companyHistoryRows)): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Examen</th>
                    <th>Seguimiento</th>
                    <th>Pasantia</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companyHistoryRows as $historyRow): ?>
                <?php
                $historyFollowUp = $followUpMeta[$historyRow['seguimiento_estado'] ?? ''] ?? ['label' => 'Sin seguimiento', 'class' => 'pill-neutral'];
                $historyExamClass = ($historyRow['estado'] ?? '') === 'aprobado'
                    ? 'pill-green'
                    : ((($historyRow['estado'] ?? '') === 'pendiente')
                        ? 'pill-orange'
                        : ((($historyRow['estado'] ?? '') === 'reprobado') ? 'pill-red' : 'pill-neutral'));
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($historyRow['estudiante_nombre'] ?? 'Sin estudiante'); ?></strong>
                        <div class="muted-line"><?php echo htmlspecialchars($historyRow['matricula'] ?? 'Sin matricula'); ?></div>
                        <div class="muted-line"><?php echo htmlspecialchars($historyRow['estudiante_area'] ?? 'Sin area'); ?></div>
                    </td>
                    <td>
                        <span class="pill <?php echo $historyExamClass; ?>"><?php echo htmlspecialchars(ucfirst((string) ($historyRow['estado'] ?? 'sin estado'))); ?></span>
                        <div class="muted-line">Nota: <?php echo $historyRow['nota'] !== null ? htmlspecialchars(number_format((float) $historyRow['nota'], 1)) : 'Sin nota'; ?></div>
                    </td>
                    <td>
                        <?php if (($historyRow['estado'] ?? '') === 'aprobado'): ?>
                        <span class="pill <?php echo htmlspecialchars($historyFollowUp['class']); ?>"><?php echo htmlspecialchars($historyFollowUp['label']); ?></span>
                        <?php if (!empty($historyRow['seguimiento_comentario'])): ?>
                        <div class="muted-line"><?php echo htmlspecialchars($historyRow['seguimiento_comentario']); ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="pill pill-neutral">No aplica</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($historyRow['asignacion_estado'])): ?>
                        <span class="pill <?php echo ($historyRow['asignacion_estado'] ?? '') === 'activa' ? 'pill-blue' : (($historyRow['asignacion_estado'] ?? '') === 'finalizada' ? 'pill-green' : 'pill-red'); ?>">
                            <?php echo htmlspecialchars(ucfirst((string) ($historyRow['asignacion_estado'] ?? ''))); ?>
                        </span>
                        <?php if (!empty($historyRow['fecha_asignacion'])): ?>
                        <div class="muted-line">Desde <?php echo htmlspecialchars($formatDate($historyRow['fecha_asignacion'], false)); ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="pill pill-neutral">Sin pasantia</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($formatDate($historyRow['fecha_evaluacion'] ?? null, true)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">Esta empresa aun no tiene historial de evaluaciones.</div>
    <?php endif; ?>
</section>
<?php endif; ?>
