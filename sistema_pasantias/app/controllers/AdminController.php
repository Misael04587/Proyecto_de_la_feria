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
                ) AS evaluaciones_aprobadas
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

        $this->renderPage('companies', $context);
    }

    public function manageStudents() {
        $context = $this->buildBaseContext('students');
        $centerId = (int) $context['centro']['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleStudentCvReview($centerId);
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

        $this->renderPage('students', $context);
    }

    public function viewEvaluations() {
        $context = $this->buildBaseContext('evaluations');
        $centerId = (int) $context['centro']['id'];
        $filters = [
            'area' => $this->sanitizeAreaFilter($_GET['area'] ?? '', $centerId),
            'estado' => $this->sanitizeEnumFilter($_GET['estado'] ?? '', ['pendiente', 'aprobado', 'reprobado', 'anulado']),
        ];

        $where = ["est.centro_id = ?"];
        $params = [$centerId];
        if ($filters['area'] !== '') {
            $where[] = "est.area_tecnica = ?";
            $params[] = $filters['area'];
        }
        if ($filters['estado'] !== '') {
            $where[] = "ev.estado = ?";
            $params[] = $filters['estado'];
        }

        $context['filters'] = $filters;
        $context['evaluations'] = Database::select("
            SELECT
                ev.id,
                ev.estado,
                ev.nota,
                ev.tiempo_inicio,
                ev.tiempo_fin,
                ev.created_at,
                u.nombre AS estudiante_nombre,
                est.matricula,
                est.area_tecnica,
                em.nombre AS empresa_nombre,
                (
                    SELECT a.estado
                    FROM asignaciones a
                    WHERE a.estudiante_id = ev.estudiante_id AND a.empresa_id = ev.empresa_id
                    ORDER BY a.created_at DESC, a.id DESC
                    LIMIT 1
                ) AS asignacion_estado
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
        $areas = $this->getAreaLabels($centerId);

        $companiesByArea = $this->keyByArea(Database::select("
            SELECT area_tecnica, COUNT(*) AS total, COALESCE(SUM(cupos), 0) AS cupos
            FROM empresas
            WHERE centro_id = ?
            GROUP BY area_tecnica
        ", [$centerId]));

        $studentsByArea = $this->keyByArea(Database::select("
            SELECT
                area_tecnica,
                COUNT(*) AS total,
                SUM(CASE WHEN cv_path IS NOT NULL AND cv_path <> '' THEN 1 ELSE 0 END) AS con_cv
            FROM estudiantes
            WHERE centro_id = ?
            GROUP BY area_tecnica
        ", [$centerId]));

        $evaluationsByArea = $this->keyByArea(Database::select("
            SELECT
                est.area_tecnica,
                COUNT(*) AS total,
                SUM(CASE WHEN ev.estado = 'aprobado' THEN 1 ELSE 0 END) AS aprobadas,
                AVG(ev.nota) AS promedio
            FROM evaluaciones ev
            JOIN estudiantes est ON est.id = ev.estudiante_id
            WHERE est.centro_id = ?
            GROUP BY est.area_tecnica
        ", [$centerId]));

        $assignmentsByArea = $this->keyByArea(Database::select("
            SELECT
                est.area_tecnica,
                COUNT(*) AS total,
                SUM(CASE WHEN a.estado = 'activa' THEN 1 ELSE 0 END) AS activas
            FROM asignaciones a
            JOIN estudiantes est ON est.id = a.estudiante_id
            WHERE est.centro_id = ?
            GROUP BY est.area_tecnica
        ", [$centerId]));

        $areaReport = [];
        foreach ($areas as $areaKey => $areaLabel) {
            $areaReport[] = [
                'label' => $areaLabel,
                'companies' => (int) ($companiesByArea[$areaKey]['total'] ?? 0),
                'slots' => (int) ($companiesByArea[$areaKey]['cupos'] ?? 0),
                'students' => (int) ($studentsByArea[$areaKey]['total'] ?? 0),
                'students_cv' => (int) ($studentsByArea[$areaKey]['con_cv'] ?? 0),
                'evaluations' => (int) ($evaluationsByArea[$areaKey]['total'] ?? 0),
                'approved' => (int) ($evaluationsByArea[$areaKey]['aprobadas'] ?? 0),
                'average' => $evaluationsByArea[$areaKey]['promedio'] ?? null,
                'internships' => (int) ($assignmentsByArea[$areaKey]['activas'] ?? 0),
            ];
        }

        $context['areaReport'] = $areaReport;
        $context['studentsWithoutCvList'] = Database::select("
            SELECT u.nombre, e.matricula, e.area_tecnica, e.created_at
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.centro_id = ? AND (e.cv_path IS NULL OR e.cv_path = '')
            ORDER BY e.created_at DESC, e.id DESC
            LIMIT 6
        ", [$centerId]);

        $context['topCompanies'] = Database::select("
            SELECT
                e.nombre,
                e.area_tecnica,
                e.cupos,
                (
                    SELECT COUNT(*)
                    FROM asignaciones a
                    WHERE a.empresa_id = e.id AND a.estado = 'activa'
                ) AS activas
            FROM empresas e
            WHERE e.centro_id = ?
            ORDER BY activas DESC, e.nombre ASC
            LIMIT 6
        ", [$centerId]);

        $this->renderPage('reports', $context);
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

    private function handleStudentCvReview($centerId) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('admin-students', 'Token de seguridad invalido', 'error');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));
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
