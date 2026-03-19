<?php
// app/controllers/AdminController.php

class AdminController {
    public function dashboard() {
        $context = $this->buildBaseContext('dashboard');
        $centerId = (int) $context['centro']['id'];

        $context['studentsWithoutCv'] = (int) (($this->fetchSingleValue("
            SELECT COUNT(*) AS total
            FROM estudiantes
            WHERE centro_id = ? AND (cv_path IS NULL OR cv_path = '')
        ", [$centerId]))['total'] ?? 0);

        $context['recentStudents'] = Database::select("
            SELECT u.nombre, u.correo, e.matricula, e.area_tecnica, e.cv_path, e.created_at
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.centro_id = ?
            ORDER BY e.created_at DESC, e.id DESC
            LIMIT 5
        ", [$centerId]);

        $context['pendingEvaluations'] = Database::select("
            SELECT
                ev.id,
                ev.estado,
                ev.nota,
                ev.created_at,
                u.nombre AS estudiante_nombre,
                est.matricula,
                em.nombre AS empresa_nombre
            FROM evaluaciones ev
            JOIN estudiantes est ON est.id = ev.estudiante_id
            JOIN usuarios u ON u.id = est.usuario_id
            JOIN empresas em ON em.id = ev.empresa_id
            WHERE est.centro_id = ?
            ORDER BY (ev.estado = 'pendiente') DESC, COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
            LIMIT 5
        ", [$centerId]);

        $this->renderPage('dashboard', $context);
    }

    public function manageCompanies() {
        $context = $this->buildBaseContext('companies');
        $centerId = (int) $context['centro']['id'];
        Evaluacion::ensureWorkflowSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleCompanyManagementAction($centerId);
        }

        $filters = [
            'area' => $this->sanitizeAreaFilter($_GET['area'] ?? '', $centerId),
            'estado' => $this->sanitizeEnumFilter($_GET['estado'] ?? '', ['disponible', 'completo']),
        ];

        $where = ["e.centro_id = ?"];
        $params = [$centerId];
        if ($filters['area'] !== '') {
            $where[] = "e.area_tecnica = ?";
            $params[] = $filters['area'];
        }
        if ($filters['estado'] !== '') {
            $where[] = "e.estado = ?";
            $params[] = $filters['estado'];
        }

        $context['filters'] = $filters;
        $context['csrfToken'] = Security::generateCSRFToken();
        $context['companies'] = Database::select("
            SELECT
                e.*,
                (
                    SELECT COUNT(*)
                    FROM asignaciones a
                    WHERE a.empresa_id = e.id AND a.estado = 'activa'
                ) AS asignados_actuales,
                (
                    SELECT COUNT(*)
                    FROM evaluaciones ev
                    WHERE ev.empresa_id = e.id
                ) AS evaluaciones_total,
                (
                    SELECT COUNT(*)
                    FROM evaluaciones ev
                    WHERE ev.empresa_id = e.id AND ev.estado = 'aprobado'
                ) AS evaluaciones_aprobadas,
                (
                    SELECT COUNT(*)
                    FROM asignaciones a
                    WHERE a.empresa_id = e.id
                ) AS asignaciones_total
            FROM empresas e
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE WHEN e.estado = 'disponible' THEN 0 ELSE 1 END, e.nombre ASC
        ", $params);

        $context['areaRows'] = Database::select("
            SELECT area_tecnica, COUNT(*) AS total, COALESCE(SUM(cupos), 0) AS cupos
            FROM empresas
            WHERE centro_id = ?
            GROUP BY area_tecnica
            ORDER BY area_tecnica ASC
        ", [$centerId]);

        $formData = $this->consumeCompanyFormData();
        if ($formData === null) {
            $editCompanyId = (int) ($_GET['edit'] ?? 0);
            if ($editCompanyId > 0) {
                $companyToEdit = $this->getCompanyForCenter($editCompanyId, $centerId);
                if (!$companyToEdit) {
                    $this->redirectToCompanyManagement('No se encontro la empresa seleccionada', 'error');
                }
                $formData = $this->normalizeCompanyFormData($companyToEdit);
            }
        }

        $context['companyFormData'] = $formData ?: $this->getDefaultCompanyFormData();
        $historyCompanyId = (int) ($_GET['history'] ?? 0);
        $context['selectedCompanyHistory'] = $this->getCompanyForCenter($historyCompanyId, $centerId);
        $context['companyHistoryRows'] = [];

        if (!empty($context['selectedCompanyHistory'])) {
            $context['companyHistoryRows'] = Database::select("
                SELECT
                    ev.id,
                    u.nombre AS estudiante_nombre,
                    est.matricula,
                    est.area_tecnica AS estudiante_area,
                    ev.estado,
                    ev.seguimiento_estado,
                    ev.seguimiento_comentario,
                    ev.seguimiento_fecha,
                    ev.nota,
                    COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) AS fecha_evaluacion,
                    a.estado AS asignacion_estado,
                    a.fecha_asignacion
                FROM evaluaciones ev
                JOIN estudiantes est ON est.id = ev.estudiante_id
                JOIN usuarios u ON u.id = est.usuario_id
                LEFT JOIN asignaciones a
                    ON a.estudiante_id = ev.estudiante_id
                   AND a.empresa_id = ev.empresa_id
                WHERE ev.empresa_id = ? AND est.centro_id = ?
                ORDER BY COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
            ", [$historyCompanyId, $centerId]);
        }

        $this->renderPage('companies', $context);
    }

    public function manageQuestions() {
        $context = $this->buildBaseContext('questions');
        $centerId = (int) $context['centro']['id'];

        Pregunta::ensureSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleQuestionManagementAction($centerId);
        }

        $filters = [
            'area' => $this->sanitizeAreaFilter($_GET['area'] ?? '', $centerId),
            'estado' => $this->sanitizeEnumFilter($_GET['estado'] ?? '', ['activo', 'inactivo']),
            'uso' => $this->sanitizeEnumFilter($_GET['uso'] ?? '', ['usadas', 'sin_uso']),
        ];

        $context['filters'] = $filters;
        $context['csrfToken'] = Security::generateCSRFToken();
        $allowedAreas = array_values($context['areaLabels']);

        if (empty($allowedAreas)) {
            $context['questionStats'] = [
                'total' => 0,
                'activas' => 0,
                'inactivas' => 0,
                'usadas' => 0,
                'areas_listas' => 0,
                'areas_pendientes' => 0,
            ];
            $context['questionHealth'] = [];
            $context['questions'] = [];
            $context['questionFormData'] = $this->getDefaultQuestionFormData();
            $this->renderPage('questions', $context);
            return;
        }

        $areaPlaceholders = $this->buildPlaceholders(count($allowedAreas));
        $where = ["p.area_tecnica IN ($areaPlaceholders)"];
        $params = $allowedAreas;

        if ($filters['area'] !== '') {
            $where[] = "p.area_tecnica = ?";
            $params[] = $filters['area'];
        }

        if ($filters['estado'] !== '') {
            $where[] = "p.estado = ?";
            $params[] = $filters['estado'];
        }

        if ($filters['uso'] === 'usadas') {
            $where[] = "EXISTS (SELECT 1 FROM evaluacion_preguntas ep WHERE ep.pregunta_id = p.id)";
        } elseif ($filters['uso'] === 'sin_uso') {
            $where[] = "NOT EXISTS (SELECT 1 FROM evaluacion_preguntas ep WHERE ep.pregunta_id = p.id)";
        }

        $questionUsageJoin = "
            LEFT JOIN (
                SELECT
                    pregunta_id,
                    COUNT(*) AS usos_total,
                    COUNT(DISTINCT evaluacion_id) AS evaluaciones_total
                FROM evaluacion_preguntas
                GROUP BY pregunta_id
            ) pu ON pu.pregunta_id = p.id
        ";

        $context['questions'] = Database::select("
            SELECT
                p.*,
                COALESCE(pu.usos_total, 0) AS usos_total,
                COALESCE(pu.evaluaciones_total, 0) AS evaluaciones_total
            FROM preguntas p
            $questionUsageJoin
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE WHEN p.estado = 'activo' THEN 0 ELSE 1 END, p.created_at DESC, p.id DESC
        ", $params);

        $questionTotals = Database::selectOne("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN p.estado = 'activo' THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN p.estado = 'inactivo' THEN 1 ELSE 0 END) AS inactivas,
                SUM(CASE WHEN pu.pregunta_id IS NOT NULL THEN 1 ELSE 0 END) AS usadas
            FROM preguntas p
            LEFT JOIN (
                SELECT DISTINCT pregunta_id
                FROM evaluacion_preguntas
            ) pu ON pu.pregunta_id = p.id
            WHERE p.area_tecnica IN ($areaPlaceholders)
        ", $allowedAreas);

        $areaRows = Database::select("
            SELECT
                p.area_tecnica,
                SUM(CASE WHEN p.estado = 'activo' THEN 1 ELSE 0 END) AS activas,
                SUM(CASE WHEN p.estado = 'inactivo' THEN 1 ELSE 0 END) AS inactivas,
                SUM(CASE WHEN pu.pregunta_id IS NOT NULL THEN 1 ELSE 0 END) AS usadas
            FROM preguntas p
            LEFT JOIN (
                SELECT DISTINCT pregunta_id
                FROM evaluacion_preguntas
            ) pu ON pu.pregunta_id = p.id
            WHERE p.area_tecnica IN ($areaPlaceholders)
            GROUP BY p.area_tecnica
            ORDER BY p.area_tecnica ASC
        ", $allowedAreas);

        $areaRowsByKey = $this->keyByArea($areaRows);
        $questionHealth = [];
        $readyAreas = 0;
        $pendingAreas = 0;

        foreach ($context['areaLabels'] as $areaKey => $areaLabel) {
            $row = $areaRowsByKey[$areaKey] ?? [];
            $activeCount = (int) ($row['activas'] ?? 0);
            $inactiveCount = (int) ($row['inactivas'] ?? 0);
            $usedCount = (int) ($row['usadas'] ?? 0);
            $missingCount = max(Evaluacion::MIN_PREGUNTAS - $activeCount, 0);
            $ready = $missingCount === 0;

            if ($ready) {
                $readyAreas++;
            } else {
                $pendingAreas++;
            }

            $questionHealth[] = [
                'label' => $areaLabel,
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
                'used_count' => $usedCount,
                'missing_count' => $missingCount,
                'ready' => $ready,
            ];
        }

        $context['questionStats'] = [
            'total' => (int) ($questionTotals['total'] ?? 0),
            'activas' => (int) ($questionTotals['activas'] ?? 0),
            'inactivas' => (int) ($questionTotals['inactivas'] ?? 0),
            'usadas' => (int) ($questionTotals['usadas'] ?? 0),
            'areas_listas' => $readyAreas,
            'areas_pendientes' => $pendingAreas,
        ];
        $context['questionHealth'] = $questionHealth;

        $formData = $this->consumeQuestionFormData();
        if ($formData === null) {
            $editQuestionId = (int) ($_GET['edit'] ?? 0);
            if ($editQuestionId > 0) {
                $questionToEdit = $this->getQuestionForAdmin($editQuestionId, $centerId);
                if (!$questionToEdit) {
                    $this->redirectToQuestionManagement('No se encontro la pregunta seleccionada', 'error');
                }
                if ($this->isQuestionLockedForEdition($questionToEdit)) {
                    $this->redirectToQuestionManagement('No puedes editar una pregunta que ya fue usada en evaluaciones', 'warning');
                }
                $formData = $this->normalizeQuestionFormData($questionToEdit);
            }
        }

        $context['questionFormData'] = $formData ?: $this->getDefaultQuestionFormData();
        $this->renderPage('questions', $context);
    }

    public function manageStudents() {
        $context = $this->buildBaseContext('students');
        $centerId = (int) $context['centro']['id'];
        Evaluacion::ensureWorkflowSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleStudentManagementAction($centerId);
        }

        $filters = [
            'area' => $this->sanitizeAreaFilter($_GET['area'] ?? '', $centerId),
            'cv' => $this->sanitizeEnumFilter($_GET['cv'] ?? '', ['con_cv', 'sin_cv']),
            'pasantia' => $this->sanitizeEnumFilter($_GET['pasantia'] ?? '', ['activa', 'sin_pasantia']),
        ];

        $where = ["e.centro_id = ?"];
        $params = [$centerId];
        if ($filters['area'] !== '') {
            $where[] = "e.area_tecnica = ?";
            $params[] = $filters['area'];
        }
        if ($filters['cv'] === 'con_cv') {
            $where[] = "(e.cv_path IS NOT NULL AND e.cv_path <> '')";
        } elseif ($filters['cv'] === 'sin_cv') {
            $where[] = "(e.cv_path IS NULL OR e.cv_path = '')";
        }
        if ($filters['pasantia'] === 'activa') {
            $where[] = "EXISTS (SELECT 1 FROM asignaciones a WHERE a.estudiante_id = e.id AND a.estado = 'activa')";
        } elseif ($filters['pasantia'] === 'sin_pasantia') {
            $where[] = "NOT EXISTS (SELECT 1 FROM asignaciones a WHERE a.estudiante_id = e.id AND a.estado = 'activa')";
        }

        $context['filters'] = $filters;
        $context['csrfToken'] = Security::generateCSRFToken();
        $context['students'] = Database::select("
            SELECT
                e.id,
                e.matricula,
                e.area_tecnica,
                e.cv_path,
                e.comentario_cv_admin,
                e.fecha_revision_cv,
                e.created_at,
                u.nombre,
                u.correo,
                u.estado AS usuario_estado,
                (
                    SELECT ev.estado
                    FROM evaluaciones ev
                    WHERE ev.estudiante_id = e.id
                    ORDER BY COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
                    LIMIT 1
                ) AS ultima_evaluacion_estado,
                (
                    SELECT ev.nota
                    FROM evaluaciones ev
                    WHERE ev.estudiante_id = e.id
                    ORDER BY COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
                    LIMIT 1
                ) AS ultima_evaluacion_nota,
                (
                    SELECT ev.seguimiento_estado
                    FROM evaluaciones ev
                    WHERE ev.estudiante_id = e.id
                    ORDER BY COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
                    LIMIT 1
                ) AS ultima_evaluacion_seguimiento_estado,
                (
                    SELECT a.id
                    FROM asignaciones a
                    WHERE a.estudiante_id = e.id AND a.estado = 'activa'
                    ORDER BY a.created_at DESC, a.id DESC
                    LIMIT 1
                ) AS asignacion_activa_id,
                (
                    SELECT a.fecha_asignacion
                    FROM asignaciones a
                    WHERE a.estudiante_id = e.id AND a.estado = 'activa'
                    ORDER BY a.created_at DESC, a.id DESC
                    LIMIT 1
                ) AS asignacion_activa_fecha,
                (
                    SELECT em.nombre
                    FROM asignaciones a
                    JOIN empresas em ON em.id = a.empresa_id
                    WHERE a.estudiante_id = e.id AND a.estado = 'activa'
                    ORDER BY a.created_at DESC, a.id DESC
                    LIMIT 1
                ) AS empresa_activa
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.created_at DESC, e.id DESC
        ", $params);

        $historyStudentId = (int) ($_GET['history'] ?? 0);
        $context['selectedStudentHistory'] = $this->getStudentForCenter($historyStudentId, $centerId);
        $context['studentHistoryRows'] = [];

        if (!empty($context['selectedStudentHistory'])) {
            $context['studentHistoryRows'] = Database::select("
                SELECT
                    ev.id,
                    em.nombre AS empresa_nombre,
                    em.area_tecnica AS empresa_area,
                    ev.estado,
                    ev.seguimiento_estado,
                    ev.seguimiento_comentario,
                    ev.seguimiento_fecha,
                    ev.nota,
                    COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) AS fecha_evaluacion,
                    a.estado AS asignacion_estado,
                    a.fecha_asignacion
                FROM evaluaciones ev
                JOIN empresas em ON em.id = ev.empresa_id
                LEFT JOIN asignaciones a
                    ON a.estudiante_id = ev.estudiante_id
                   AND a.empresa_id = ev.empresa_id
                WHERE ev.estudiante_id = ?
                ORDER BY COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
            ", [$historyStudentId]);
        }

        $this->renderPage('students', $context);
    }

    public function viewEvaluations() {
        $context = $this->buildBaseContext('evaluations');
        $centerId = (int) $context['centro']['id'];
        Evaluacion::ensureWorkflowSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleEvaluationManagementAction($centerId);
        }

        $filters = [
            'area' => $this->sanitizeAreaFilter($_GET['area'] ?? '', $centerId),
            'estado' => $this->sanitizeEnumFilter($_GET['estado'] ?? '', ['pendiente', 'aprobado', 'reprobado', 'anulado']),
            'seguimiento' => $this->sanitizeEnumFilter($_GET['seguimiento'] ?? '', array_keys($this->getEvaluationFollowUpOptions())),
            'empresa_id' => 0,
            'fecha_desde' => $this->sanitizeDateFilter($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => $this->sanitizeDateFilter($_GET['fecha_hasta'] ?? ''),
        ];

        $selectedCompany = $this->getCompanyForCenter((int) ($_GET['empresa_id'] ?? 0), $centerId);
        $filters['empresa_id'] = $selectedCompany ? (int) ($selectedCompany['id'] ?? 0) : 0;

        $where = ["est.centro_id = ?"];
        $params = [$centerId];
        $evaluationDateExpression = "DATE(COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at))";

        if ($filters['area'] !== '') {
            $where[] = "est.area_tecnica = ?";
            $params[] = $filters['area'];
        }
        if ($filters['estado'] !== '') {
            $where[] = "ev.estado = ?";
            $params[] = $filters['estado'];
        }
        if ($filters['seguimiento'] !== '') {
            $where[] = "ev.seguimiento_estado = ?";
            $params[] = $filters['seguimiento'];
        }
        if ($filters['empresa_id'] > 0) {
            $where[] = "ev.empresa_id = ?";
            $params[] = $filters['empresa_id'];
        }
        if ($filters['fecha_desde'] !== '') {
            $where[] = "$evaluationDateExpression >= ?";
            $params[] = $filters['fecha_desde'];
        }
        if ($filters['fecha_hasta'] !== '') {
            $where[] = "$evaluationDateExpression <= ?";
            $params[] = $filters['fecha_hasta'];
        }

        $context['filters'] = $filters;
        $context['csrfToken'] = Security::generateCSRFToken();
        $context['companyOptions'] = $this->getCompanyOptions($centerId);
        $context['followUpOptions'] = $this->getEvaluationFollowUpOptions();
        $context['evaluations'] = Database::select("
            SELECT
                ev.id,
                ev.estado,
                ev.seguimiento_estado,
                ev.seguimiento_comentario,
                ev.seguimiento_fecha,
                ev.nota,
                ev.tiempo_inicio,
                ev.tiempo_fin,
                ev.created_at,
                ev.estudiante_id,
                ev.empresa_id,
                u.nombre AS estudiante_nombre,
                est.matricula,
                est.area_tecnica,
                em.nombre AS empresa_nombre,
                em.cupos AS empresa_cupos,
                (
                    SELECT a.id
                    FROM asignaciones a
                    WHERE a.estudiante_id = ev.estudiante_id AND a.empresa_id = ev.empresa_id
                    ORDER BY a.created_at DESC, a.id DESC
                    LIMIT 1
                ) AS asignacion_id,
                (
                    SELECT a.estado
                    FROM asignaciones a
                    WHERE a.estudiante_id = ev.estudiante_id AND a.empresa_id = ev.empresa_id
                    ORDER BY a.created_at DESC, a.id DESC
                    LIMIT 1
                ) AS asignacion_estado,
                (
                    SELECT COUNT(*)
                    FROM asignaciones a
                    WHERE a.estudiante_id = ev.estudiante_id AND a.estado = 'activa'
                ) AS estudiante_pasantias_activas,
                (
                    SELECT COUNT(*)
                    FROM asignaciones a
                    WHERE a.empresa_id = ev.empresa_id AND a.estado = 'activa'
                ) AS empresa_asignaciones_activas
            FROM evaluaciones ev
            JOIN estudiantes est ON est.id = ev.estudiante_id
            JOIN usuarios u ON u.id = est.usuario_id
            JOIN empresas em ON em.id = ev.empresa_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
        ", $params);

        $this->renderPage('evaluations', $context);
    }

    public function reports() {
        $context = $this->buildBaseContext('reports');
        $centerId = (int) $context['centro']['id'];
        Evaluacion::ensureWorkflowSchema();

        $followUpOptions = $this->getEvaluationFollowUpOptions();
        $filters = [
            'area' => $this->sanitizeAreaFilter($_GET['area'] ?? '', $centerId),
            'empresa_id' => 0,
            'estado' => $this->sanitizeEnumFilter($_GET['estado'] ?? '', ['pendiente', 'aprobado', 'reprobado', 'anulado']),
            'seguimiento' => $this->sanitizeEnumFilter($_GET['seguimiento'] ?? '', array_keys($followUpOptions)),
            'pasantia' => $this->sanitizeEnumFilter($_GET['pasantia'] ?? '', ['activa', 'finalizada', 'cancelada', 'sin_pasantia']),
            'fecha_desde' => $this->sanitizeDateFilter($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => $this->sanitizeDateFilter($_GET['fecha_hasta'] ?? ''),
            'export' => $this->sanitizeEnumFilter($_GET['export'] ?? '', ['csv', 'excel', 'pdf']),
        ];

        $selectedCompany = $this->getCompanyForCenter((int) ($_GET['empresa_id'] ?? 0), $centerId);
        $filters['empresa_id'] = $selectedCompany ? (int) ($selectedCompany['id'] ?? 0) : 0;

        $reportRows = $this->buildReportDataset($centerId, $filters);

        if ($filters['export'] !== '') {
            $this->exportReportDataset($filters['export'], $reportRows, $filters, $centerId);
        }

        $areaBuckets = [];
        foreach ($reportRows as $row) {
            $areaKey = trim((string) ($row['area_tecnica'] ?? ''));
            if ($areaKey === '') {
                continue;
            }

            if (!isset($areaBuckets[$areaKey])) {
                $areaBuckets[$areaKey] = [
                    'label' => $areaKey,
                    'companies' => [],
                    'students' => [],
                    'evaluations' => 0,
                    'approved' => 0,
                    'preselected' => 0,
                    'internships' => 0,
                ];
            }

            $areaBuckets[$areaKey]['evaluations']++;
            $areaBuckets[$areaKey]['companies'][(int) ($row['empresa_id'] ?? 0)] = true;
            $areaBuckets[$areaKey]['students'][(int) ($row['estudiante_id'] ?? 0)] = true;

            if (($row['estado'] ?? '') === 'aprobado') {
                $areaBuckets[$areaKey]['approved']++;
            }

            if (($row['seguimiento_estado'] ?? '') === 'preseleccionado') {
                $areaBuckets[$areaKey]['preselected']++;
            }

            if (($row['asignacion_estado'] ?? '') === 'activa') {
                $areaBuckets[$areaKey]['internships']++;
            }
        }

        $areaReport = [];
        foreach ($areaBuckets as $bucket) {
            $areaReport[] = [
                'label' => $bucket['label'],
                'companies' => count($bucket['companies']),
                'students' => count($bucket['students']),
                'evaluations' => $bucket['evaluations'],
                'approved' => $bucket['approved'],
                'preselected' => $bucket['preselected'],
                'internships' => $bucket['internships'],
            ];
        }

        usort($areaReport, function ($left, $right) {
            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        $context['filters'] = $filters;
        $context['companyOptions'] = $this->getCompanyOptions($centerId);
        $context['followUpOptions'] = $followUpOptions;
        $context['reportRows'] = $reportRows;
        $context['areaReport'] = $areaReport;
        $context['reportSummary'] = [
            'total' => count($reportRows),
            'approved' => count(array_filter($reportRows, function ($row) {
                return ($row['estado'] ?? '') === 'aprobado';
            })),
            'preselected' => count(array_filter($reportRows, function ($row) {
                return ($row['seguimiento_estado'] ?? '') === 'preseleccionado';
            })),
            'assigned' => count(array_filter($reportRows, function ($row) {
                return !empty($row['asignacion_estado']);
            })),
            'active' => count(array_filter($reportRows, function ($row) {
                return ($row['asignacion_estado'] ?? '') === 'activa';
            })),
        ];

        $this->renderPage('reports', $context);
    }

    private function buildReportDataset($centerId, array $filters) {
        $where = ["est.centro_id = ?"];
        $params = [(int) $centerId];
        $dateExpression = "DATE(COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at))";

        if (($filters['area'] ?? '') !== '') {
            $where[] = "est.area_tecnica = ?";
            $params[] = $filters['area'];
        }

        if ((int) ($filters['empresa_id'] ?? 0) > 0) {
            $where[] = "ev.empresa_id = ?";
            $params[] = (int) $filters['empresa_id'];
        }

        if (($filters['estado'] ?? '') !== '') {
            $where[] = "ev.estado = ?";
            $params[] = $filters['estado'];
        }

        if (($filters['seguimiento'] ?? '') !== '') {
            $where[] = "ev.seguimiento_estado = ?";
            $params[] = $filters['seguimiento'];
        }

        if (($filters['pasantia'] ?? '') === 'sin_pasantia') {
            $where[] = "a.id IS NULL";
        } elseif (($filters['pasantia'] ?? '') !== '') {
            $where[] = "a.estado = ?";
            $params[] = $filters['pasantia'];
        }

        if (($filters['fecha_desde'] ?? '') !== '') {
            $where[] = "$dateExpression >= ?";
            $params[] = $filters['fecha_desde'];
        }

        if (($filters['fecha_hasta'] ?? '') !== '') {
            $where[] = "$dateExpression <= ?";
            $params[] = $filters['fecha_hasta'];
        }

        return Database::select("
            SELECT
                ev.id,
                ev.estudiante_id,
                ev.empresa_id,
                ev.estado,
                ev.seguimiento_estado,
                ev.seguimiento_comentario,
                ev.seguimiento_fecha,
                ev.nota,
                COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) AS fecha_evaluacion,
                est.matricula,
                est.area_tecnica,
                u.nombre AS estudiante_nombre,
                em.nombre AS empresa_nombre,
                a.estado AS asignacion_estado,
                a.fecha_asignacion
            FROM evaluaciones ev
            JOIN estudiantes est ON est.id = ev.estudiante_id
            JOIN usuarios u ON u.id = est.usuario_id
            JOIN empresas em ON em.id = ev.empresa_id
            LEFT JOIN asignaciones a
                ON a.estudiante_id = ev.estudiante_id
               AND a.empresa_id = ev.empresa_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC, ev.id DESC
        ", $params);
    }

    private function exportReportDataset($format, array $reportRows, array $filters, $centerId) {
        $headers = ['Fecha', 'Estudiante', 'Matricula', 'Area', 'Empresa', 'Estado examen', 'Seguimiento', 'Nota', 'Pasantia', 'Fecha asignacion', 'Comentario'];
        $followUpOptions = $this->getEvaluationFollowUpOptions();
        $assignmentLabels = [
            '' => 'Sin pasantia',
            'activa' => 'Activa',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
        ];

        $rows = [];
        foreach ($reportRows as $row) {
            $rows[] = [
                $this->formatExportDate($row['fecha_evaluacion'] ?? null),
                $row['estudiante_nombre'] ?? 'Sin estudiante',
                $row['matricula'] ?? 'Sin matricula',
                $row['area_tecnica'] ?? 'Sin area',
                $row['empresa_nombre'] ?? 'Sin empresa',
                ucfirst((string) ($row['estado'] ?? 'sin estado')),
                $followUpOptions[$row['seguimiento_estado'] ?? ''] ?? 'Sin seguimiento',
                $row['nota'] !== null ? number_format((float) $row['nota'], 1) : 'Sin nota',
                $assignmentLabels[$row['asignacion_estado'] ?? ''] ?? ucfirst((string) ($row['asignacion_estado'] ?? '')),
                $this->formatExportDate($row['fecha_asignacion'] ?? null, false),
                trim((string) ($row['seguimiento_comentario'] ?? '')),
            ];
        }

        $fileBase = 'reporte_fase2_' . date('Ymd_His');
        if ($format === 'csv') {
            $this->sendCsvDownload($fileBase . '.csv', $headers, $rows);
        }
        if ($format === 'excel') {
            $this->sendExcelDownload($fileBase . '.xls', $headers, $rows);
        }
        if ($format === 'pdf') {
            $company = $this->getCompanyForCenter((int) ($filters['empresa_id'] ?? 0), $centerId);
            $filterSummary = $this->buildReportFilterSummary($filters, $company);
            $this->sendPdfDownload($fileBase . '.pdf', 'Reporte academico de pasantias', $headers, $rows, $filterSummary);
        }
    }

    private function formatExportDate($value, $withTime = true) {
        if (empty($value)) {
            return 'Sin registrar';
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return 'Sin registrar';
        }

        return date($withTime ? 'd/m/Y h:i A' : 'd/m/Y', $timestamp);
    }

    private function buildReportFilterSummary(array $filters, $company = null) {
        $parts = [];

        if (($filters['area'] ?? '') !== '') {
            $parts[] = 'Area: ' . $filters['area'];
        }
        if (!empty($company['nombre'])) {
            $parts[] = 'Empresa: ' . $company['nombre'];
        }
        if (($filters['estado'] ?? '') !== '') {
            $parts[] = 'Estado examen: ' . ucfirst($filters['estado']);
        }
        if (($filters['seguimiento'] ?? '') !== '') {
            $parts[] = 'Seguimiento: ' . ($this->getEvaluationFollowUpOptions()[$filters['seguimiento']] ?? $filters['seguimiento']);
        }
        if (($filters['pasantia'] ?? '') !== '') {
            $parts[] = 'Pasantia: ' . ucfirst(str_replace('_', ' ', $filters['pasantia']));
        }
        if (($filters['fecha_desde'] ?? '') !== '') {
            $parts[] = 'Desde: ' . $filters['fecha_desde'];
        }
        if (($filters['fecha_hasta'] ?? '') !== '') {
            $parts[] = 'Hasta: ' . $filters['fecha_hasta'];
        }

        return implode(' | ', $parts);
    }

    private function sendCsvDownload($fileName, array $headers, array $rows) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    private function sendExcelDownload($fileName, array $headers, array $rows) {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        echo chr(0xEF) . chr(0xBB) . chr(0xBF);
        echo implode("\t", array_map([$this, 'sanitizeExportCell'], $headers)) . "\n";
        foreach ($rows as $row) {
            echo implode("\t", array_map([$this, 'sanitizeExportCell'], $row)) . "\n";
        }

        exit;
    }

    private function sanitizeExportCell($value) {
        $value = str_replace(["\r", "\n", "\t"], ' ', (string) $value);
        return trim($value);
    }

    private function sendPdfDownload($fileName, $title, array $headers, array $rows, $filterSummary = '') {
        $lines = [
            $title,
            'Generado: ' . date('d/m/Y h:i A'),
        ];

        if ($filterSummary !== '') {
            $lines[] = 'Filtros: ' . $filterSummary;
        }

        $lines[] = '';
        $lines[] = implode(' | ', $headers);
        $lines[] = str_repeat('-', 110);

        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map(function ($value) {
                $value = $this->sanitizeExportCell($value);
                return function_exists('mb_substr')
                    ? mb_substr($value, 0, 28, 'UTF-8')
                    : substr($value, 0, 28);
            }, $row));
        }

        $pdf = $this->buildSimplePdfDocument($lines);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private function buildSimplePdfDocument(array $lines) {
        $pages = array_chunk($lines, 40);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        $pageRefs = [];
        $nextId = 4;

        foreach ($pages as $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageRefs[] = $pageId . ' 0 R';

            $streamLines = ['BT', '/F1 10 Tf', '14 TL', '40 800 Td'];
            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $streamLines[] = 'T*';
                }
                $streamLines[] = '(' . $this->escapePdfText($line) . ') Tj';
            }
            $streamLines[] = 'ET';

            $stream = implode("\n", $streamLines);
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents ' . $contentId . ' 0 R /Resources << /Font << /F1 3 0 R >> >> >>';
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index <= $maxId; $index++) {
            $offset = $offsets[$index] ?? 0;
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPosition . "\n%%EOF";
        return $pdf;
    }

    private function escapePdfText($text) {
        $text = $this->sanitizeExportCell($text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
        return $text;
    }

    public function manageAreas() {
        $context = $this->buildBaseContext('areas');
        $centerId = (int) $context['centro']['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleAreaManagement($centerId);
        }

        $context['csrfToken'] = Security::generateCSRFToken();
        $context['areasOverview'] = AreaTecnica::getCenterAreasOverview($centerId);
        $context['availableCatalogAreas'] = AreaTecnica::getCatalogAreasNotInCenter($centerId);
        $context['areaSummary'] = [
            'assigned' => count($context['areasOverview']),
            'with_students' => count(array_filter($context['areasOverview'], function ($area) {
                return (int) ($area['students'] ?? 0) > 0;
            })),
            'with_companies' => count(array_filter($context['areasOverview'], function ($area) {
                return (int) ($area['companies'] ?? 0) > 0;
            })),
            'removable' => count(array_filter($context['areasOverview'], function ($area) {
                return !empty($area['removable']);
            })),
        ];

        $this->renderPage('areas', $context);
    }

    private function buildBaseContext($section) {
        $this->guardAccess();
        $centro = $this->getCenterOrFail();
        $centerId = (int) $centro['id'];

        return [
            'currentSection' => $section,
            'adminUser' => $this->getAdminUser(),
            'centro' => $centro,
            'stats' => $this->getStats($centerId),
            'areaLabels' => $this->getAreaLabels($centerId),
        ];
    }

    private function renderPage($section, array $context) {
        $sectionView = APP_PATH . 'views/admin/sections/' . $section . '.php';
        if (!file_exists($sectionView)) {
            throw new RuntimeException('Vista admin no encontrada: ' . $section);
        }
        extract($context, EXTR_SKIP);
        require APP_PATH . 'views/admin/layout.php';
    }

    private function getCenterOrFail() {
        $centro = Database::selectOne("
            SELECT id, nombre, codigo_unico, estado, created_at
            FROM centros
            WHERE id = ?
            LIMIT 1
        ", [$_SESSION['centro_id'] ?? 0]);

        if (!$centro) {
            redirect('login', 'No se encontro la informacion del centro', 'error');
        }

        return $centro;
    }

    private function getAdminUser() {
        return Database::selectOne("
            SELECT id, nombre, correo
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ", [$_SESSION['user_id'] ?? 0]) ?: [];
    }

    private function getStats($centerId) {
        $companies = $this->fetchSingleValue("
            SELECT COUNT(*) AS total, SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) AS disponibles, SUM(CASE WHEN estado = 'completo' THEN 1 ELSE 0 END) AS completas, COALESCE(SUM(cupos), 0) AS cupos
            FROM empresas
            WHERE centro_id = ?
        ", [$centerId]);

        $students = $this->fetchSingleValue("
            SELECT COUNT(*) AS total, SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS nuevos, SUM(CASE WHEN cv_path IS NOT NULL AND cv_path <> '' THEN 1 ELSE 0 END) AS con_cv
            FROM estudiantes
            WHERE centro_id = ?
        ", [$centerId]);

        $evaluations = $this->fetchSingleValue("
            SELECT COUNT(*) AS total, SUM(CASE WHEN ev.estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes, SUM(CASE WHEN ev.estado = 'aprobado' THEN 1 ELSE 0 END) AS aprobadas, SUM(CASE WHEN ev.estado = 'reprobado' THEN 1 ELSE 0 END) AS reprobadas, SUM(CASE WHEN ev.estado = 'anulado' THEN 1 ELSE 0 END) AS anuladas, AVG(ev.nota) AS promedio
            FROM evaluaciones ev
            JOIN estudiantes est ON est.id = ev.estudiante_id
            WHERE est.centro_id = ?
        ", [$centerId]);

        $assignments = $this->fetchSingleValue("
            SELECT COUNT(*) AS total, SUM(CASE WHEN a.estado = 'activa' THEN 1 ELSE 0 END) AS activas, SUM(CASE WHEN a.estado = 'finalizada' THEN 1 ELSE 0 END) AS finalizadas, SUM(CASE WHEN a.estado = 'cancelada' THEN 1 ELSE 0 END) AS canceladas
            FROM asignaciones a
            JOIN estudiantes est ON est.id = a.estudiante_id
            WHERE est.centro_id = ?
        ", [$centerId]);

        return [
            'empresas' => (int) ($companies['total'] ?? 0),
            'empresas_disponibles' => (int) ($companies['disponibles'] ?? 0),
            'empresas_completas' => (int) ($companies['completas'] ?? 0),
            'cupos_totales' => (int) ($companies['cupos'] ?? 0),
            'estudiantes' => (int) ($students['total'] ?? 0),
            'estudiantes_nuevos' => (int) ($students['nuevos'] ?? 0),
            'cv_subidos' => (int) ($students['con_cv'] ?? 0),
            'evaluaciones_total' => (int) ($evaluations['total'] ?? 0),
            'evaluaciones_pendientes' => (int) ($evaluations['pendientes'] ?? 0),
            'evaluaciones_aprobadas' => (int) ($evaluations['aprobadas'] ?? 0),
            'evaluaciones_reprobadas' => (int) ($evaluations['reprobadas'] ?? 0),
            'evaluaciones_anuladas' => (int) ($evaluations['anuladas'] ?? 0),
            'evaluaciones_promedio' => $evaluations['promedio'] !== null ? (float) $evaluations['promedio'] : null,
            'asignaciones_total' => (int) ($assignments['total'] ?? 0),
            'asignaciones_activas' => (int) ($assignments['activas'] ?? 0),
            'asignaciones_finalizadas' => (int) ($assignments['finalizadas'] ?? 0),
            'asignaciones_canceladas' => (int) ($assignments['canceladas'] ?? 0),
        ];
    }

    private function getAreaLabels($centerId = null) {
        $areas = $centerId !== null
            ? AreaTecnica::getAreasByCenterId((int) $centerId)
            : AreaTecnica::getCatalog();

        if ($centerId !== null && (int) $centerId > 0) {
            $usedAreas = Database::select("
                SELECT area_tecnica
                FROM empresas
                WHERE centro_id = ?
                UNION
                SELECT area_tecnica
                FROM estudiantes
                WHERE centro_id = ?
                ORDER BY area_tecnica ASC
            ", [(int) $centerId, (int) $centerId]);

            foreach ($usedAreas as $row) {
                $label = trim((string) ($row['area_tecnica'] ?? ''));
                if ($label !== '' && !in_array($label, $areas, true)) {
                    $areas[] = $label;
                }
            }
        }

        $labels = [];
        foreach ($areas as $label) {
            $key = $this->normalizeAreaKey($label);
            if ($key !== '') {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }

    private function keyByArea(array $rows) {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$this->normalizeAreaKey($row['area_tecnica'] ?? '')] = $row;
        }
        return $indexed;
    }

    private function normalizeAreaKey($value) {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^A-Za-z0-9]+/', '', (string) $value);
        return (string) $value;
    }

    private function sanitizeAreaFilter($value, $centerId = null) {
        $key = $this->normalizeAreaKey($value);

        foreach ($this->getAreaLabels($centerId) as $label) {
            if ($this->normalizeAreaKey($label) === $key) {
                return $label;
            }
        }

        return '';
    }

    private function sanitizeEnumFilter($value, array $allowed) {
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitizeDateFilter($value) {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function getCompanyOptions($centerId) {
        return Database::select("
            SELECT id, nombre
            FROM empresas
            WHERE centro_id = ?
            ORDER BY nombre ASC
        ", [(int) $centerId]);
    }

    private function getStudentForCenter($studentId, $centerId) {
        return Database::selectOne("
            SELECT
                e.id,
                e.matricula,
                e.area_tecnica,
                u.nombre,
                u.correo
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.id = ? AND e.centro_id = ?
            LIMIT 1
        ", [(int) $studentId, (int) $centerId]);
    }

    private function getEvaluationFollowUpOptions() {
        return [
            'sin_revisar' => 'Pendiente de revision',
            'en_revision' => 'En revision',
            'preseleccionado' => 'Preseleccionado',
            'descartado' => 'Descartado',
        ];
    }

    private function handleCompanyManagementAction($centerId) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->redirectToCompanyManagement('Token de seguridad invalido', 'error');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));

        try {
            if ($intent === 'create_company') {
                $this->createCompany($centerId);
            }

            if ($intent === 'update_company') {
                $this->updateCompany($centerId);
            }

            if ($intent === 'delete_company') {
                $this->deleteCompany($centerId);
            }

            $this->redirectToCompanyManagement('Accion de empresas no reconocida', 'warning');
        } catch (Throwable $exception) {
            if (in_array($intent, ['create_company', 'update_company'], true)) {
                $companyId = $intent === 'update_company'
                    ? (int) ($_POST['company_id'] ?? 0)
                    : 0;

                $this->storeCompanyFormData(
                    $this->captureCompanyFormData($centerId, $companyId)
                );

                $this->redirectToCompanyManagement(
                    $exception->getMessage(),
                    'error',
                    $companyId > 0 ? $companyId : null
                );
            }

            $this->redirectToCompanyManagement($exception->getMessage(), 'error');
        }
    }

    private function createCompany($centerId) {
        $data = $this->captureCompanyFormData($centerId);
        $this->validateCompanyFormData($data, $centerId);

        $inserted = Database::insert("
            INSERT INTO empresas (
                centro_id,
                nombre,
                direccion,
                area_tecnica,
                cupos,
                descripcion,
                requisitos,
                estado
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            (int) $centerId,
            $data['nombre'],
            $data['direccion'] !== '' ? $data['direccion'] : null,
            $data['area_tecnica'],
            $data['cupos'],
            $data['descripcion'] !== '' ? $data['descripcion'] : null,
            $data['requisitos'] !== '' ? $data['requisitos'] : null,
            $data['estado'],
        ]);

        if (!$inserted) {
            throw new RuntimeException('No se pudo registrar la empresa');
        }

        $this->clearCompanyFormData();
        $this->redirectToCompanyManagement('Empresa registrada correctamente', 'success');
    }

    private function updateCompany($centerId) {
        $companyId = (int) ($_POST['company_id'] ?? 0);
        if ($companyId <= 0) {
            throw new InvalidArgumentException('Selecciona una empresa valida para editar');
        }

        $company = $this->getCompanyForCenter($companyId, $centerId);
        if (!$company) {
            throw new RuntimeException('No se encontro la empresa seleccionada');
        }

        $data = $this->captureCompanyFormData($centerId, $companyId);
        $this->validateCompanyFormData($data, $centerId, $companyId);

        $updated = Database::execute("
            UPDATE empresas
            SET nombre = ?,
                direccion = ?,
                area_tecnica = ?,
                cupos = ?,
                descripcion = ?,
                requisitos = ?,
                estado = ?
            WHERE id = ? AND centro_id = ?
        ", [
            $data['nombre'],
            $data['direccion'] !== '' ? $data['direccion'] : null,
            $data['area_tecnica'],
            $data['cupos'],
            $data['descripcion'] !== '' ? $data['descripcion'] : null,
            $data['requisitos'] !== '' ? $data['requisitos'] : null,
            $data['estado'],
            $companyId,
            (int) $centerId,
        ]);

        if (!$updated) {
            throw new RuntimeException('No se pudo actualizar la empresa');
        }

        $this->clearCompanyFormData();
        $this->redirectToCompanyManagement('Empresa actualizada correctamente', 'success');
    }

    private function deleteCompany($centerId) {
        $companyId = (int) ($_POST['company_id'] ?? 0);
        if ($companyId <= 0) {
            throw new InvalidArgumentException('Selecciona una empresa valida para eliminar');
        }

        $company = $this->getCompanyForCenter($companyId, $centerId);
        if (!$company) {
            throw new RuntimeException('No se encontro la empresa seleccionada');
        }

        $usage = Database::selectOne("
            SELECT
                (SELECT COUNT(*) FROM evaluaciones WHERE empresa_id = ?) AS evaluaciones,
                (SELECT COUNT(*) FROM asignaciones WHERE empresa_id = ?) AS asignaciones
        ", [$companyId, $companyId]);

        if ((int) ($usage['evaluaciones'] ?? 0) > 0 || (int) ($usage['asignaciones'] ?? 0) > 0) {
            throw new RuntimeException('No puedes eliminar una empresa que ya tiene evaluaciones o pasantias asociadas');
        }

        $deleted = Database::execute("
            DELETE FROM empresas
            WHERE id = ? AND centro_id = ?
        ", [$companyId, (int) $centerId]);

        if (!$deleted) {
            throw new RuntimeException('No se pudo eliminar la empresa');
        }

        $this->clearCompanyFormData();
        $this->redirectToCompanyManagement('Empresa eliminada correctamente', 'success');
    }

    private function captureCompanyFormData($centerId, $companyId = 0) {
        $requestedState = $this->sanitizeEnumFilter($_POST['estado'] ?? 'disponible', ['disponible', 'completo']);

        return [
            'id' => (int) $companyId,
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'direccion' => trim((string) ($_POST['direccion'] ?? '')),
            'area_tecnica' => $this->sanitizeAreaFilter($_POST['area_tecnica'] ?? '', $centerId),
            'cupos' => max(0, (int) ($_POST['cupos'] ?? 0)),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'requisitos' => trim((string) ($_POST['requisitos'] ?? '')),
            'estado' => $requestedState !== '' ? $requestedState : 'disponible',
        ];
    }

    private function validateCompanyFormData(array &$data, $centerId, $companyId = 0) {
        if ($data['nombre'] === '') {
            throw new InvalidArgumentException('El nombre de la empresa es obligatorio');
        }

        if ($this->measureTextLength($data['nombre']) < 3) {
            throw new InvalidArgumentException('El nombre de la empresa debe tener al menos 3 caracteres');
        }

        if ($this->measureTextLength($data['nombre']) > 150) {
            throw new InvalidArgumentException('El nombre de la empresa no puede superar 150 caracteres');
        }

        if ($data['area_tecnica'] === '') {
            throw new InvalidArgumentException('Selecciona un area tecnica valida para la empresa');
        }

        if ($data['cupos'] <= 0) {
            throw new InvalidArgumentException('La empresa debe tener al menos 1 cupo disponible');
        }

        if ($data['cupos'] > 999) {
            throw new InvalidArgumentException('Los cupos de la empresa no pueden superar 999');
        }

        if ($this->measureTextLength($data['direccion']) > 200) {
            throw new InvalidArgumentException('La direccion no puede superar 200 caracteres');
        }

        if ($this->measureTextLength($data['descripcion']) > 3000) {
            throw new InvalidArgumentException('La descripcion no puede superar 3000 caracteres');
        }

        if ($this->measureTextLength($data['requisitos']) > 3000) {
            throw new InvalidArgumentException('Los requisitos no pueden superar 3000 caracteres');
        }

        $duplicated = Database::selectOne("
            SELECT id
            FROM empresas
            WHERE centro_id = ? AND nombre = ? AND id <> ?
            LIMIT 1
        ", [(int) $centerId, $data['nombre'], (int) $companyId]);

        if ($duplicated) {
            throw new InvalidArgumentException('Ya existe otra empresa con ese nombre en tu centro');
        }

        if ((int) $companyId > 0) {
            $activeAssignments = Database::selectOne("
                SELECT COUNT(*) AS total
                FROM asignaciones
                WHERE empresa_id = ? AND estado = 'activa'
            ", [(int) $companyId]);

            $activeCount = (int) ($activeAssignments['total'] ?? 0);
            if ($data['cupos'] < $activeCount) {
                throw new RuntimeException('No puedes bajar los cupos por debajo de las pasantias activas actuales');
            }

            if ($activeCount >= $data['cupos']) {
                $data['estado'] = 'completo';
            }
        }
    }

    private function getCompanyForCenter($companyId, $centerId) {
        return Database::selectOne("
            SELECT *
            FROM empresas
            WHERE id = ? AND centro_id = ?
            LIMIT 1
        ", [(int) $companyId, (int) $centerId]);
    }

    private function getDefaultCompanyFormData() {
        return [
            'id' => 0,
            'nombre' => '',
            'direccion' => '',
            'area_tecnica' => '',
            'cupos' => 5,
            'descripcion' => '',
            'requisitos' => '',
            'estado' => 'disponible',
        ];
    }

    private function normalizeCompanyFormData(array $company) {
        return [
            'id' => (int) ($company['id'] ?? 0),
            'nombre' => trim((string) ($company['nombre'] ?? '')),
            'direccion' => trim((string) ($company['direccion'] ?? '')),
            'area_tecnica' => trim((string) ($company['area_tecnica'] ?? '')),
            'cupos' => max(1, (int) ($company['cupos'] ?? 5)),
            'descripcion' => trim((string) ($company['descripcion'] ?? '')),
            'requisitos' => trim((string) ($company['requisitos'] ?? '')),
            'estado' => $this->sanitizeEnumFilter($company['estado'] ?? 'disponible', ['disponible', 'completo']) ?: 'disponible',
        ];
    }

    private function storeCompanyFormData(array $data) {
        $_SESSION['company_form_data'] = $data;
    }

    private function consumeCompanyFormData() {
        $data = $_SESSION['company_form_data'] ?? null;
        unset($_SESSION['company_form_data']);

        return is_array($data) ? $data : null;
    }

    private function clearCompanyFormData() {
        unset($_SESSION['company_form_data']);
    }

    private function redirectToCompanyManagement($message = '', $type = 'success', $editCompanyId = null) {
        if ($message !== '') {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }

        $url = 'index.php?page=admin-companies';
        if ($editCompanyId !== null && (int) $editCompanyId > 0) {
            $url .= '&edit=' . (int) $editCompanyId;
        }

        header('Location: ' . $url);
        exit;
    }

    private function measureTextLength($value) {
        return function_exists('mb_strlen')
            ? mb_strlen((string) $value, 'UTF-8')
            : strlen((string) $value);
    }

    private function handleQuestionManagementAction($centerId) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->redirectToQuestionManagement('Token de seguridad invalido', 'error');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));

        try {
            if ($intent === 'create_question') {
                $this->createQuestion($centerId);
            }

            if ($intent === 'update_question') {
                $this->updateQuestion($centerId);
            }

            if ($intent === 'delete_question') {
                $this->deleteQuestion($centerId);
            }

            if ($intent === 'toggle_question_status') {
                $this->toggleQuestionStatus($centerId);
            }

            $this->redirectToQuestionManagement('Accion de preguntas no reconocida', 'warning');
        } catch (Throwable $exception) {
            if (in_array($intent, ['create_question', 'update_question'], true)) {
                $questionId = $intent === 'update_question'
                    ? (int) ($_POST['question_id'] ?? 0)
                    : 0;

                $this->storeQuestionFormData(
                    $this->captureQuestionFormData($centerId, $questionId)
                );

                $this->redirectToQuestionManagement(
                    $exception->getMessage(),
                    'error',
                    $questionId > 0 ? $questionId : null
                );
            }

            $this->redirectToQuestionManagement($exception->getMessage(), 'error');
        }
    }

    private function createQuestion($centerId) {
        $data = $this->captureQuestionFormData($centerId);
        $this->validateQuestionFormData($data, $centerId);

        $inserted = Database::insert("
            INSERT INTO preguntas (
                area_tecnica,
                pregunta,
                opcion_a,
                opcion_b,
                opcion_c,
                opcion_d,
                respuesta_correcta,
                estado
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['area_tecnica'],
            $data['pregunta'],
            $data['opcion_a'],
            $data['opcion_b'],
            $data['opcion_c'],
            $data['opcion_d'],
            $data['respuesta_correcta'],
            $data['estado'],
        ]);

        if (!$inserted) {
            throw new RuntimeException('No se pudo registrar la pregunta');
        }

        $this->clearQuestionFormData();
        $this->redirectToQuestionManagement('Pregunta registrada correctamente', 'success');
    }

    private function updateQuestion($centerId) {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        if ($questionId <= 0) {
            throw new InvalidArgumentException('Selecciona una pregunta valida para editar');
        }

        $question = $this->getQuestionForAdmin($questionId, $centerId);
        if (!$question) {
            throw new RuntimeException('No se encontro la pregunta seleccionada');
        }

        if ($this->isQuestionLockedForEdition($question)) {
            throw new RuntimeException('No puedes editar una pregunta que ya fue usada en evaluaciones');
        }

        $data = $this->captureQuestionFormData($centerId, $questionId);
        $this->validateQuestionFormData($data, $centerId, $questionId);

        $updated = Database::execute("
            UPDATE preguntas
            SET area_tecnica = ?,
                pregunta = ?,
                opcion_a = ?,
                opcion_b = ?,
                opcion_c = ?,
                opcion_d = ?,
                respuesta_correcta = ?,
                estado = ?
            WHERE id = ?
        ", [
            $data['area_tecnica'],
            $data['pregunta'],
            $data['opcion_a'],
            $data['opcion_b'],
            $data['opcion_c'],
            $data['opcion_d'],
            $data['respuesta_correcta'],
            $data['estado'],
            $questionId,
        ]);

        if (!$updated) {
            throw new RuntimeException('No se pudo actualizar la pregunta');
        }

        $this->clearQuestionFormData();
        $this->redirectToQuestionManagement('Pregunta actualizada correctamente', 'success');
    }

    private function deleteQuestion($centerId) {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        if ($questionId <= 0) {
            throw new InvalidArgumentException('Selecciona una pregunta valida para eliminar');
        }

        $question = $this->getQuestionForAdmin($questionId, $centerId);
        if (!$question) {
            throw new RuntimeException('No se encontro la pregunta seleccionada');
        }

        if ($this->isQuestionLockedForEdition($question)) {
            throw new RuntimeException('No puedes eliminar una pregunta que ya fue usada en evaluaciones');
        }

        $deleted = Database::execute("
            DELETE FROM preguntas
            WHERE id = ?
        ", [$questionId]);

        if (!$deleted) {
            throw new RuntimeException('No se pudo eliminar la pregunta');
        }

        $this->clearQuestionFormData();
        $this->redirectToQuestionManagement('Pregunta eliminada correctamente', 'success');
    }

    private function toggleQuestionStatus($centerId) {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        if ($questionId <= 0) {
            throw new InvalidArgumentException('Selecciona una pregunta valida');
        }

        $question = $this->getQuestionForAdmin($questionId, $centerId);
        if (!$question) {
            throw new RuntimeException('No se encontro la pregunta seleccionada');
        }

        $targetState = $this->sanitizeEnumFilter($_POST['target_state'] ?? '', ['activo', 'inactivo']);
        if ($targetState === '') {
            $targetState = ($question['estado'] ?? 'activo') === 'activo' ? 'inactivo' : 'activo';
        }

        $updated = Database::execute("
            UPDATE preguntas
            SET estado = ?
            WHERE id = ?
        ", [$targetState, $questionId]);

        if (!$updated) {
            throw new RuntimeException('No se pudo cambiar el estado de la pregunta');
        }

        $message = $targetState === 'activo'
            ? 'Pregunta activada correctamente'
            : 'Pregunta desactivada correctamente';

        $this->redirectToQuestionManagement($message, 'success');
    }

    private function captureQuestionFormData($centerId, $questionId = 0) {
        return [
            'id' => (int) $questionId,
            'area_tecnica' => $this->sanitizeAreaFilter($_POST['area_tecnica'] ?? '', $centerId),
            'pregunta' => trim((string) ($_POST['pregunta'] ?? '')),
            'opcion_a' => trim((string) ($_POST['opcion_a'] ?? '')),
            'opcion_b' => trim((string) ($_POST['opcion_b'] ?? '')),
            'opcion_c' => trim((string) ($_POST['opcion_c'] ?? '')),
            'opcion_d' => trim((string) ($_POST['opcion_d'] ?? '')),
            'respuesta_correcta' => $this->sanitizeEnumFilter($_POST['respuesta_correcta'] ?? '', ['a', 'b', 'c', 'd']),
            'estado' => $this->sanitizeEnumFilter($_POST['estado'] ?? 'activo', ['activo', 'inactivo']) ?: 'activo',
        ];
    }

    private function validateQuestionFormData(array $data, $centerId, $questionId = 0) {
        if ($data['area_tecnica'] === '') {
            throw new InvalidArgumentException('Selecciona un area tecnica valida para la pregunta');
        }

        if ($data['pregunta'] === '') {
            throw new InvalidArgumentException('Escribe el enunciado de la pregunta');
        }

        if ($this->measureTextLength($data['pregunta']) < 10) {
            throw new InvalidArgumentException('La pregunta debe tener al menos 10 caracteres');
        }

        if ($this->measureTextLength($data['pregunta']) > 2000) {
            throw new InvalidArgumentException('La pregunta no puede superar 2000 caracteres');
        }

        $options = [
            'opcion_a' => 'A',
            'opcion_b' => 'B',
            'opcion_c' => 'C',
            'opcion_d' => 'D',
        ];
        $normalizedOptions = [];

        foreach ($options as $field => $label) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                throw new InvalidArgumentException('La opcion ' . $label . ' es obligatoria');
            }

            if ($this->measureTextLength($value) > 255) {
                throw new InvalidArgumentException('La opcion ' . $label . ' no puede superar 255 caracteres');
            }

            $normalizedKey = function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);

            if (in_array($normalizedKey, $normalizedOptions, true)) {
                throw new InvalidArgumentException('Las opciones de respuesta deben ser distintas entre si');
            }

            $normalizedOptions[] = $normalizedKey;
        }

        if ($data['respuesta_correcta'] === '') {
            throw new InvalidArgumentException('Selecciona la respuesta correcta de la pregunta');
        }

        $duplicate = Database::selectOne("
            SELECT id
            FROM preguntas
            WHERE area_tecnica = ? AND pregunta = ? AND id <> ?
            LIMIT 1
        ", [$data['area_tecnica'], $data['pregunta'], (int) $questionId]);

        if ($duplicate) {
            throw new InvalidArgumentException('Ya existe una pregunta identica en esa area tecnica');
        }
    }

    private function getQuestionForAdmin($questionId, $centerId) {
        $allowedAreas = AreaTecnica::getAreasByCenterId((int) $centerId);
        if (empty($allowedAreas)) {
            return false;
        }

        $placeholders = $this->buildPlaceholders(count($allowedAreas));
        $params = array_merge([(int) $questionId], array_values($allowedAreas));

        return Database::selectOne("
            SELECT
                p.*,
                (
                    SELECT COUNT(*)
                    FROM evaluacion_preguntas ep
                    WHERE ep.pregunta_id = p.id
                ) AS usos_total,
                (
                    SELECT COUNT(DISTINCT ep.evaluacion_id)
                    FROM evaluacion_preguntas ep
                    WHERE ep.pregunta_id = p.id
                ) AS evaluaciones_total
            FROM preguntas p
            WHERE p.id = ?
              AND p.area_tecnica IN ($placeholders)
            LIMIT 1
        ", $params);
    }

    private function isQuestionLockedForEdition(array $question) {
        return (int) ($question['usos_total'] ?? 0) > 0;
    }

    private function getDefaultQuestionFormData() {
        return [
            'id' => 0,
            'area_tecnica' => '',
            'pregunta' => '',
            'opcion_a' => '',
            'opcion_b' => '',
            'opcion_c' => '',
            'opcion_d' => '',
            'respuesta_correcta' => 'a',
            'estado' => 'activo',
        ];
    }

    private function normalizeQuestionFormData(array $question) {
        return [
            'id' => (int) ($question['id'] ?? 0),
            'area_tecnica' => trim((string) ($question['area_tecnica'] ?? '')),
            'pregunta' => trim((string) ($question['pregunta'] ?? '')),
            'opcion_a' => trim((string) ($question['opcion_a'] ?? '')),
            'opcion_b' => trim((string) ($question['opcion_b'] ?? '')),
            'opcion_c' => trim((string) ($question['opcion_c'] ?? '')),
            'opcion_d' => trim((string) ($question['opcion_d'] ?? '')),
            'respuesta_correcta' => $this->sanitizeEnumFilter($question['respuesta_correcta'] ?? 'a', ['a', 'b', 'c', 'd']) ?: 'a',
            'estado' => $this->sanitizeEnumFilter($question['estado'] ?? 'activo', ['activo', 'inactivo']) ?: 'activo',
        ];
    }

    private function storeQuestionFormData(array $data) {
        $_SESSION['question_form_data'] = $data;
    }

    private function consumeQuestionFormData() {
        $data = $_SESSION['question_form_data'] ?? null;
        unset($_SESSION['question_form_data']);

        return is_array($data) ? $data : null;
    }

    private function clearQuestionFormData() {
        unset($_SESSION['question_form_data']);
    }

    private function redirectToQuestionManagement($message = '', $type = 'success', $editQuestionId = null) {
        if ($message !== '') {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }

        $url = 'index.php?page=admin-questions';
        if ($editQuestionId !== null && (int) $editQuestionId > 0) {
            $url .= '&edit=' . (int) $editQuestionId;
        }

        header('Location: ' . $url);
        exit;
    }

    private function redirectToEvaluations($message = '', $type = 'success') {
        if ($message !== '') {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }

        header('Location: index.php?page=admin-evaluations');
        exit;
    }

    private function handleAreaManagement($centerId) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('admin-areas', 'Token de seguridad invalido', 'error');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));

        try {
            if ($intent === 'add') {
                $result = AreaTecnica::addAreaToCenter($centerId, $_POST['area_name'] ?? '');

                if (empty($result['assigned'])) {
                    redirect('admin-areas', 'Esa area ya estaba activa en tu centro', 'info');
                }

                $message = !empty($result['created_catalog'])
                    ? 'Area tecnica creada y asignada correctamente'
                    : 'Area tecnica agregada al centro correctamente';

                redirect('admin-areas', $message, 'success');
            }

            if ($intent === 'remove') {
                AreaTecnica::removeAreaFromCenter($centerId, $_POST['area_name'] ?? '');
                redirect('admin-areas', 'Area tecnica quitada del centro correctamente', 'success');
            }

            redirect('admin-areas', 'Accion de areas no reconocida', 'warning');
        } catch (Throwable $exception) {
            redirect('admin-areas', $exception->getMessage(), 'error');
        }
    }

    private function handleStudentManagementAction($centerId) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('admin-students', 'Token de seguridad invalido', 'error');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));
        if ($intent === 'delete_student') {
            $this->deleteStudentCompletely($centerId);
        }
        if ($intent === 'delete_all_students') {
            $this->deleteAllStudentsCompletely($centerId);
        }
        if ($intent === 'update_assignment_status') {
            $this->updateStudentAssignmentStatus($centerId);
        }

        if ($intent !== 'save_cv_comment') {
            redirect('admin-students', 'Accion de revision no reconocida', 'warning');
        }

        $studentId = (int) ($_POST['student_id'] ?? 0);
        if ($studentId <= 0) {
            redirect('admin-students', 'Selecciona un estudiante valido', 'error');
        }

        $student = Database::selectOne("
            SELECT id, cv_path
            FROM estudiantes
            WHERE id = ? AND centro_id = ?
            LIMIT 1
        ", [$studentId, $centerId]);

        if (!$student) {
            redirect('admin-students', 'No se encontro el estudiante solicitado', 'error');
        }

        if (empty($student['cv_path'])) {
            redirect('admin-students', 'Este estudiante aun no tiene un CV para revisar', 'warning');
        }

        $comment = trim((string) ($_POST['cv_comment'] ?? ''));
        $commentLength = function_exists('mb_strlen')
            ? mb_strlen($comment, 'UTF-8')
            : strlen($comment);

        if ($commentLength > 1200) {
            redirect('admin-students', 'El comentario no puede superar 1200 caracteres', 'warning');
        }

        if ($comment === '') {
            $updated = Database::execute("
                UPDATE estudiantes
                SET comentario_cv_admin = NULL,
                    fecha_revision_cv = NULL
                WHERE id = ? AND centro_id = ?
            ", [$studentId, $centerId]);

            if (!$updated) {
                redirect('admin-students', 'No se pudo limpiar el comentario del CV', 'error');
            }

            redirect('admin-students', 'Comentario del CV eliminado', 'success');
        }

        $updated = Database::execute("
            UPDATE estudiantes
            SET comentario_cv_admin = ?,
                fecha_revision_cv = NOW()
            WHERE id = ? AND centro_id = ?
        ", [$comment, $studentId, $centerId]);

        if (!$updated) {
            redirect('admin-students', 'No se pudo guardar el comentario del CV', 'error');
        }

        redirect('admin-students', 'Comentario del CV guardado correctamente', 'success');
    }

    private function handleEvaluationManagementAction($centerId) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->redirectToEvaluations('Token de seguridad invalido', 'error');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));
        try {
            if ($intent === 'assign_evaluation') {
                $evaluationId = (int) ($_POST['evaluation_id'] ?? 0);
                if ($evaluationId <= 0) {
                    $this->redirectToEvaluations('Selecciona una evaluacion valida para asignar', 'error');
                }

                Asignacion::crearDesdeEvaluacion($evaluationId, $centerId);
                $this->redirectToEvaluations('Pasantia asignada correctamente', 'success');
            }

            if ($intent === 'save_evaluation_review') {
                $evaluationId = (int) ($_POST['evaluation_id'] ?? 0);
                $followUpState = $this->sanitizeEnumFilter(
                    $_POST['seguimiento_estado'] ?? '',
                    array_keys($this->getEvaluationFollowUpOptions())
                );

                if ($evaluationId <= 0 || $followUpState === '') {
                    $this->redirectToEvaluations('Datos invalidos para guardar el seguimiento', 'error');
                }

                Evaluacion::saveSeguimiento(
                    $evaluationId,
                    $centerId,
                    $followUpState,
                    $_POST['seguimiento_comentario'] ?? ''
                );

                $message = $followUpState === 'preseleccionado'
                    ? 'Evaluacion preseleccionada y lista para asignacion'
                    : 'Seguimiento academico guardado correctamente';

                $this->redirectToEvaluations($message, 'success');
            }
        } catch (Throwable $exception) {
            $this->redirectToEvaluations($exception->getMessage(), 'error');
        }

        $this->redirectToEvaluations('Accion de evaluacion no reconocida', 'warning');
    }

    private function updateStudentAssignmentStatus($centerId) {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $targetState = $this->sanitizeEnumFilter($_POST['target_state'] ?? '', ['finalizada', 'cancelada']);

        if ($assignmentId <= 0 || $targetState === '') {
            redirect('admin-students', 'Datos invalidos para actualizar la pasantia', 'error');
        }

        try {
            Asignacion::actualizarEstado($assignmentId, $centerId, $targetState);
        } catch (Throwable $exception) {
            redirect('admin-students', $exception->getMessage(), 'error');
        }

        $message = $targetState === 'finalizada'
            ? 'Pasantia finalizada correctamente'
            : 'Pasantia cancelada correctamente';

        redirect('admin-students', $message, 'success');
    }

    private function deleteStudentCompletely($centerId) {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        if ($studentId <= 0) {
            redirect('admin-students', 'Selecciona un estudiante valido para eliminar', 'error');
        }

        $student = Database::selectOne("
            SELECT e.id, e.usuario_id, e.cv_path, e.foto_perfil, u.correo
            FROM estudiantes e
            INNER JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.id = ? AND e.centro_id = ?
            LIMIT 1
        ", [$studentId, $centerId]);

        if (!$student) {
            redirect('admin-students', 'No se encontro el estudiante seleccionado', 'error');
        }

        try {
            $this->purgeStudentsFromCenter([$student], $centerId);
        } catch (Throwable $exception) {
            redirect('admin-students', 'No se pudo eliminar el estudiante: ' . $exception->getMessage(), 'error');
        }

        redirect('admin-students', 'Estudiante eliminado completamente del sistema', 'success');
    }

    private function deleteAllStudentsCompletely($centerId) {
        $students = Database::select("
            SELECT e.id, e.usuario_id, e.cv_path, e.foto_perfil, u.correo
            FROM estudiantes e
            INNER JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.centro_id = ?
            ORDER BY e.id ASC
        ", [$centerId]);

        if (empty($students)) {
            redirect('admin-students', 'No hay estudiantes para eliminar en este centro', 'info');
        }

        try {
            $deletedCount = $this->purgeStudentsFromCenter($students, $centerId);
        } catch (Throwable $exception) {
            redirect('admin-students', 'No se pudo eliminar la lista completa: ' . $exception->getMessage(), 'error');
        }

        $message = $deletedCount === 1
            ? 'Se elimino 1 estudiante del centro'
            : 'Se eliminaron ' . $deletedCount . ' estudiantes del centro';

        redirect('admin-students', $message, 'success');
    }

    private function purgeStudentsFromCenter(array $students, $centerId) {
        $studentIds = [];
        $userIds = [];
        $logDetails = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            $userId = (int) ($student['usuario_id'] ?? 0);
            $email = trim((string) ($student['correo'] ?? ''));

            if ($studentId > 0) {
                $studentIds[] = $studentId;
            }
            if ($userId > 0) {
                $userIds[] = $userId;
                $logDetails[] = 'Usuario ' . $userId . ' cerro sesion';
            }
            if ($email !== '') {
                $logDetails[] = 'Intento fallido para: ' . $email;
            }
        }

        $studentIds = array_values(array_unique($studentIds));
        $userIds = array_values(array_unique($userIds));
        $logDetails = array_values(array_unique($logDetails));

        if (empty($studentIds) || empty($userIds)) {
            throw new RuntimeException('No se encontraron ids validos para ejecutar la limpieza');
        }

        $studentPlaceholders = $this->buildPlaceholders(count($studentIds));
        $userPlaceholders = $this->buildPlaceholders(count($userIds));
        $affectedCompanyIds = [];

        $companyRows = Database::select("
            SELECT DISTINCT empresa_id
            FROM asignaciones
            WHERE estudiante_id IN ($studentPlaceholders)
        ", $studentIds);

        foreach ($companyRows as $companyRow) {
            $companyId = (int) ($companyRow['empresa_id'] ?? 0);
            if ($companyId > 0) {
                $affectedCompanyIds[] = $companyId;
            }
        }

        $affectedCompanyIds = array_values(array_unique($affectedCompanyIds));

        Database::beginTransaction();

        try {
            $questionsDeleted = Database::execute("
                DELETE ep
                FROM evaluacion_preguntas ep
                INNER JOIN evaluaciones ev ON ev.id = ep.evaluacion_id
                WHERE ev.estudiante_id IN ($studentPlaceholders)
            ", $studentIds);
            if ($questionsDeleted === false) {
                throw new RuntimeException('No se pudo limpiar el detalle de evaluaciones');
            }

            $logsDeleted = Database::execute("
                DELETE ls
                FROM logs_seguridad ls
                INNER JOIN evaluaciones ev ON ev.id = ls.evaluacion_id
                WHERE ev.estudiante_id IN ($studentPlaceholders)
            ", $studentIds);
            if ($logsDeleted === false) {
                throw new RuntimeException('No se pudo limpiar el historial de seguridad');
            }

            if (!empty($logDetails)) {
                $logPlaceholders = $this->buildPlaceholders(count($logDetails));
                $genericLogsDeleted = Database::execute("
                    DELETE FROM logs_seguridad
                    WHERE detalles IN ($logPlaceholders)
                ", $logDetails);
                if ($genericLogsDeleted === false) {
                    throw new RuntimeException('No se pudo limpiar los logs generales del estudiante');
                }
            }

            $assignmentsDeleted = Database::execute("
                DELETE FROM asignaciones
                WHERE estudiante_id IN ($studentPlaceholders)
            ", $studentIds);
            if ($assignmentsDeleted === false) {
                throw new RuntimeException('No se pudo eliminar las pasantias de los estudiantes');
            }

            $evaluationsDeleted = Database::execute("
                DELETE FROM evaluaciones
                WHERE estudiante_id IN ($studentPlaceholders)
            ", $studentIds);
            if ($evaluationsDeleted === false) {
                throw new RuntimeException('No se pudo eliminar las evaluaciones de los estudiantes');
            }

            $studentParams = array_merge([(int) $centerId], $studentIds);
            $studentDeleted = Database::execute("
                DELETE FROM estudiantes
                WHERE centro_id = ? AND id IN ($studentPlaceholders)
            ", $studentParams);
            if ($studentDeleted === false) {
                throw new RuntimeException('No se pudo eliminar el registro de estudiantes');
            }

            $remainingStudents = Database::selectOne("
                SELECT COUNT(*) AS total
                FROM estudiantes
                WHERE centro_id = ? AND id IN ($studentPlaceholders)
            ", $studentParams);
            if ((int) ($remainingStudents['total'] ?? 0) > 0) {
                throw new RuntimeException('Quedaron estudiantes pendientes por eliminar');
            }

            $userParams = array_merge([(int) $centerId], $userIds);
            $userDeleted = Database::execute("
                DELETE FROM usuarios
                WHERE centro_id = ? AND id IN ($userPlaceholders)
            ", $userParams);
            if ($userDeleted === false) {
                throw new RuntimeException('No se pudo eliminar las cuentas de usuario asociadas');
            }

            $remainingUsers = Database::selectOne("
                SELECT COUNT(*) AS total
                FROM usuarios
                WHERE centro_id = ? AND id IN ($userPlaceholders)
            ", $userParams);
            if ((int) ($remainingUsers['total'] ?? 0) > 0) {
                throw new RuntimeException('Quedaron usuarios pendientes por eliminar');
            }

            Database::commit();
        } catch (Throwable $exception) {
            Database::rollback();
            throw $exception;
        }

        foreach ($students as $student) {
            $this->deleteStudentFile($student['cv_path'] ?? '');
            $this->deleteStudentFile($student['foto_perfil'] ?? '');
        }

        foreach ($affectedCompanyIds as $companyId) {
            Asignacion::recalcularEstadoEmpresa($companyId);
        }

        return count($studentIds);
    }

    private function buildPlaceholders($count) {
        $count = (int) $count;
        if ($count <= 0) {
            throw new RuntimeException('Cantidad de placeholders invalida');
        }

        return implode(', ', array_fill(0, $count, '?'));
    }

    private function deleteStudentFile($storedPath) {
        $storedPath = ltrim(str_replace('\\', '/', (string) $storedPath), '/');
        if ($storedPath === '') {
            return;
        }

        $fullPath = PUBLIC_PATH . $storedPath;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function guardAccess() {
        if (!in_array($_SESSION['rol'] ?? '', ['admin_centro', 'coordinador'], true)) {
            redirect('access-denied');
        }
    }

    private function fetchSingleValue($query, $params = []) {
        $result = Database::selectOne($query, $params);
        return is_array($result) ? $result : [];
    }
}
