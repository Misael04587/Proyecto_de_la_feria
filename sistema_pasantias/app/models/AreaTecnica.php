<?php
// app/models/AreaTecnica.php

class AreaTecnica {
    private const DEFAULT_AREAS = [
        'Gastronomía',
        'Administración',
        'Electricidad',
        'Informática',
    ];

    private const SEED_AREAS = [
        'Gastronomía',
        'Administración',
        'Electricidad',
        'Informática',
        'Enfermería',
        'Contabilidad',
    ];

    private static $booted = false;

    public static function getCatalog() {
        self::ensureSchema();

        $rows = Database::select("
            SELECT nombre
            FROM areas_tecnicas
            WHERE estado = 'activo'
            ORDER BY orden ASC, nombre ASC
        ");

        $areas = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['nombre'] ?? ''));
            if ($label !== '') {
                $areas[] = $label;
            }
        }

        return !empty($areas) ? $areas : self::DEFAULT_AREAS;
    }

    public static function getDefaultAreas() {
        self::ensureSchema();

        $defaults = self::sanitizeAgainstCatalog(self::DEFAULT_AREAS);
        if (!empty($defaults)) {
            return $defaults;
        }

        $catalog = self::getCatalog();
        return !empty($catalog) ? array_slice($catalog, 0, 4) : self::DEFAULT_AREAS;
    }

    public static function sanitizeAreas($areas) {
        self::ensureSchema();

        if (!is_array($areas)) {
            return [];
        }

        return self::sanitizeAgainstCatalog($areas);
    }

    public static function getAreasByCenterId($centerId) {
        self::ensureSchema();

        $centerId = (int) $centerId;
        if ($centerId <= 0) {
            return self::getDefaultAreas();
        }

        $rows = Database::select("
            SELECT area_tecnica
            FROM centro_areas
            WHERE centro_id = ?
            ORDER BY orden ASC, id ASC
        ", [$centerId]);

        $areas = self::sanitizeAgainstCatalog(array_column($rows, 'area_tecnica'));
        return !empty($areas) ? $areas : self::getDefaultAreas();
    }

    public static function getAreasByCenterCode($centerCode) {
        self::ensureSchema();

        $centerCode = trim((string) $centerCode);
        if ($centerCode === '') {
            return self::getDefaultAreas();
        }

        $center = Database::selectOne("
            SELECT id
            FROM centros
            WHERE codigo_unico = ? AND estado = 'activo'
            LIMIT 1
        ", [$centerCode]);

        if (!$center) {
            return self::getDefaultAreas();
        }

        return self::getAreasByCenterId((int) $center['id']);
    }

    public static function saveAreasForCenter($centerId, $areas) {
        self::ensureSchema();

        $centerId = (int) $centerId;
        if ($centerId <= 0) {
            throw new InvalidArgumentException('Centro invalido para guardar areas');
        }

        $areas = self::sanitizeAreas($areas);
        if (empty($areas)) {
            $areas = self::getDefaultAreas();
        }

        if (!Database::execute("DELETE FROM centro_areas WHERE centro_id = ?", [$centerId])) {
            throw new RuntimeException('No se pudieron reiniciar las areas del centro');
        }

        foreach ($areas as $index => $area) {
            self::upsertCatalogArea($area);

            $inserted = Database::insert("
                INSERT INTO centro_areas (centro_id, area_tecnica, orden)
                VALUES (?, ?, ?)
            ", [$centerId, $area, $index + 1]);

            if (!$inserted) {
                throw new RuntimeException('No se pudo guardar la configuracion de areas del centro');
            }
        }

        return $areas;
    }

    public static function addAreaToCenter($centerId, $label) {
        self::ensureSchema();

        $centerId = (int) $centerId;
        if ($centerId <= 0) {
            throw new InvalidArgumentException('Centro invalido para agregar areas');
        }

        $createdCatalog = false;
        $normalizedLabel = self::canonicalizeLabel($label);
        if ($normalizedLabel === '') {
            $normalizedLabel = self::prepareLabel($label);
            if ($normalizedLabel === '') {
                throw new InvalidArgumentException('Escribe un nombre valido para el area tecnica');
            }

            if (self::getLabelLength($normalizedLabel) < 2) {
                throw new InvalidArgumentException('El nombre del area tecnica es demasiado corto');
            }

            if (self::getLabelLength($normalizedLabel) > 100) {
                throw new InvalidArgumentException('El nombre del area tecnica no puede superar 100 caracteres');
            }

            $createdCatalog = self::upsertCatalogArea($normalizedLabel);
        }

        $label = self::canonicalizeLabel($normalizedLabel);
        if ($label === '') {
            throw new RuntimeException('No se pudo normalizar el area tecnica');
        }

        $existing = Database::selectOne("
            SELECT id
            FROM centro_areas
            WHERE centro_id = ? AND area_tecnica = ?
            LIMIT 1
        ", [$centerId, $label]);

        if ($existing) {
            return [
                'label' => $label,
                'assigned' => false,
                'created_catalog' => false,
            ];
        }

        $nextOrder = Database::selectOne("
            SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
            FROM centro_areas
            WHERE centro_id = ?
        ", [$centerId]);

        $inserted = Database::insert("
            INSERT INTO centro_areas (centro_id, area_tecnica, orden)
            VALUES (?, ?, ?)
        ", [$centerId, $label, (int) ($nextOrder['siguiente'] ?? 1)]);

        if (!$inserted) {
            throw new RuntimeException('No se pudo asignar el area tecnica al centro');
        }

        return [
            'label' => $label,
            'assigned' => true,
            'created_catalog' => $createdCatalog,
        ];
    }

    public static function removeAreaFromCenter($centerId, $label) {
        self::ensureSchema();

        $centerId = (int) $centerId;
        if ($centerId <= 0) {
            throw new InvalidArgumentException('Centro invalido para quitar areas');
        }

        $label = self::canonicalizeLabel($label);
        if ($label === '') {
            throw new InvalidArgumentException('Area tecnica invalida');
        }

        $assignedAreas = self::getAreasByCenterId($centerId);
        if (count($assignedAreas) <= 1) {
            throw new RuntimeException('El centro debe conservar al menos un area tecnica activa');
        }

        $usage = self::getUsageForCenterArea($centerId, $label);
        if (
            ($usage['students'] ?? 0) > 0 ||
            ($usage['companies'] ?? 0) > 0 ||
            ($usage['evaluations'] ?? 0) > 0 ||
            ($usage['active_assignments'] ?? 0) > 0
        ) {
            throw new RuntimeException('No puedes quitar un area que ya tiene estudiantes, empresas o historial asociado');
        }

        if (!Database::execute("
            DELETE FROM centro_areas
            WHERE centro_id = ? AND area_tecnica = ?
        ", [$centerId, $label])) {
            throw new RuntimeException('No se pudo quitar el area tecnica del centro');
        }

        self::resequenceCenterAreas($centerId);

        return $label;
    }

    public static function getCatalogAreasNotInCenter($centerId) {
        self::ensureSchema();

        $assignedMap = [];
        foreach (self::getAreasByCenterId($centerId) as $label) {
            $assignedMap[self::normalizeKey($label)] = true;
        }

        $available = [];
        foreach (self::getCatalog() as $label) {
            if (!isset($assignedMap[self::normalizeKey($label)])) {
                $available[] = $label;
            }
        }

        return $available;
    }

    public static function getCenterAreasOverview($centerId) {
        self::ensureSchema();

        $centerId = (int) $centerId;
        $areas = self::getAreasByCenterId($centerId);

        $companies = self::indexRowsByArea(Database::select("
            SELECT area_tecnica, COUNT(*) AS total
            FROM empresas
            WHERE centro_id = ?
            GROUP BY area_tecnica
        ", [$centerId]));

        $students = self::indexRowsByArea(Database::select("
            SELECT area_tecnica, COUNT(*) AS total
            FROM estudiantes
            WHERE centro_id = ?
            GROUP BY area_tecnica
        ", [$centerId]));

        $evaluations = self::indexRowsByArea(Database::select("
            SELECT est.area_tecnica, COUNT(*) AS total
            FROM evaluaciones ev
            JOIN estudiantes est ON est.id = ev.estudiante_id
            WHERE est.centro_id = ?
            GROUP BY est.area_tecnica
        ", [$centerId]));

        $activeAssignments = self::indexRowsByArea(Database::select("
            SELECT est.area_tecnica, COUNT(*) AS total
            FROM asignaciones a
            JOIN estudiantes est ON est.id = a.estudiante_id
            WHERE est.centro_id = ? AND a.estado = 'activa'
            GROUP BY est.area_tecnica
        ", [$centerId]));

        $totalAreas = count($areas);
        $overview = [];

        foreach ($areas as $label) {
            $key = self::normalizeKey($label);
            $companyCount = (int) ($companies[$key]['total'] ?? 0);
            $studentCount = (int) ($students[$key]['total'] ?? 0);
            $evaluationCount = (int) ($evaluations[$key]['total'] ?? 0);
            $activeAssignmentsCount = (int) ($activeAssignments[$key]['total'] ?? 0);

            $reason = '';
            $removable = true;

            if ($totalAreas <= 1) {
                $removable = false;
                $reason = 'El centro necesita conservar al menos un area activa.';
            } elseif ($studentCount > 0 || $companyCount > 0 || $evaluationCount > 0 || $activeAssignmentsCount > 0) {
                $removable = false;
                $reason = 'Tiene registros activos o historicos asociados.';
            }

            $overview[] = [
                'label' => $label,
                'key' => $key,
                'companies' => $companyCount,
                'students' => $studentCount,
                'evaluations' => $evaluationCount,
                'active_assignments' => $activeAssignmentsCount,
                'removable' => $removable,
                'removal_reason' => $reason,
            ];
        }

        return $overview;
    }

    private static function ensureSchema() {
        if (self::$booted) {
            return;
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS areas_tecnicas (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                orden INT NOT NULL DEFAULT 0,
                estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_area_tecnica_slug (slug),
                UNIQUE KEY uniq_area_tecnica_nombre (nombre)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        Database::execute("
            CREATE TABLE IF NOT EXISTS centro_areas (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                centro_id INT NOT NULL,
                area_tecnica VARCHAR(100) NOT NULL,
                orden INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_centro_area (centro_id, area_tecnica),
                KEY idx_centro_area_centro (centro_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::ensureAreasCatalogColumns();
        self::ensureCentroAreasColumns();
        self::seedCatalog();
        self::syncCatalogWithExistingData();
        self::ensureEnumSupport('empresas', 'area_tecnica');
        self::ensureEnumSupport('preguntas', 'area_tecnica');
        self::$booted = true;
    }

    private static function ensureEnumSupport($table, $column) {
        $definition = Database::selectOne("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (!$definition || empty($definition['Type'])) {
            return;
        }

        $type = strtolower((string) $definition['Type']);
        if (strpos($type, 'enum(') !== 0) {
            return;
        }

        $enumList = self::buildEnumList();
        if ($enumList === '') {
            return;
        }

        $missingArea = false;
        foreach (self::getCatalogRows() as $row) {
            $area = (string) ($row['nombre'] ?? '');
            if ($area !== '' && strpos($type, "'" . str_replace("'", "\\'", $area) . "'") === false) {
                $missingArea = true;
                break;
            }
        }

        if (!$missingArea) {
            return;
        }

        Database::execute(sprintf(
            "ALTER TABLE `%s` MODIFY `%s` ENUM(%s) NOT NULL",
            $table,
            $column,
            $enumList
        ));
    }

    private static function ensureAreasCatalogColumns() {
        $slugColumn = Database::selectOne("SHOW COLUMNS FROM areas_tecnicas LIKE 'slug'");
        if (!$slugColumn) {
            Database::execute("ALTER TABLE areas_tecnicas ADD COLUMN slug VARCHAR(120) NOT NULL AFTER nombre");
        }

        $orderColumn = Database::selectOne("SHOW COLUMNS FROM areas_tecnicas LIKE 'orden'");
        if (!$orderColumn) {
            Database::execute("ALTER TABLE areas_tecnicas ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER slug");
        }

        $stateColumn = Database::selectOne("SHOW COLUMNS FROM areas_tecnicas LIKE 'estado'");
        if (!$stateColumn) {
            Database::execute("ALTER TABLE areas_tecnicas ADD COLUMN estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo' AFTER orden");
        }

        $slugIndex = Database::selectOne("SHOW INDEX FROM areas_tecnicas WHERE Key_name = 'uniq_area_tecnica_slug'");
        if (!$slugIndex) {
            Database::execute("ALTER TABLE areas_tecnicas ADD UNIQUE KEY uniq_area_tecnica_slug (slug)");
        }
    }

    private static function ensureCentroAreasColumns() {
        $orderColumn = Database::selectOne("SHOW COLUMNS FROM centro_areas LIKE 'orden'");
        if (!$orderColumn) {
            Database::execute("ALTER TABLE centro_areas ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER area_tecnica");
        }
    }

    private static function seedCatalog() {
        foreach (self::SEED_AREAS as $index => $area) {
            self::upsertCatalogArea($area, $index + 1);
        }
    }

    private static function syncCatalogWithExistingData() {
        $queries = [
            "SELECT DISTINCT area_tecnica AS nombre FROM centro_areas",
            "SELECT DISTINCT area_tecnica AS nombre FROM estudiantes",
            "SELECT DISTINCT area_tecnica AS nombre FROM empresas",
            "SELECT DISTINCT area_tecnica AS nombre FROM preguntas",
        ];

        foreach ($queries as $query) {
            foreach (Database::select($query) as $row) {
                self::upsertCatalogArea($row['nombre'] ?? '');
            }
        }
    }

    private static function upsertCatalogArea($label, $preferredOrder = null) {
        $label = self::prepareLabel($label);
        if ($label === '') {
            return false;
        }

        $slug = self::normalizeKey($label);
        if ($slug === '') {
            return false;
        }

        $existing = Database::selectOne("
            SELECT id, nombre, estado, orden
            FROM areas_tecnicas
            WHERE slug = ?
            LIMIT 1
        ", [$slug]);

        if ($existing) {
            $updates = [];
            $params = [];

            if (($existing['estado'] ?? '') !== 'activo') {
                $updates[] = "estado = 'activo'";
            }

            if ($preferredOrder !== null && (int) ($existing['orden'] ?? 0) === 0) {
                $updates[] = "orden = ?";
                $params[] = (int) $preferredOrder;
            }

            if (!empty($updates)) {
                $params[] = $existing['id'];
                Database::execute("
                    UPDATE areas_tecnicas
                    SET " . implode(', ', $updates) . "
                    WHERE id = ?
                ", $params);
            }

            return false;
        }

        $order = $preferredOrder;
        if ($order === null) {
            $nextOrder = Database::selectOne("
                SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                FROM areas_tecnicas
            ");
            $order = (int) ($nextOrder['siguiente'] ?? 1);
        }

        $inserted = Database::insert("
            INSERT INTO areas_tecnicas (nombre, slug, orden, estado)
            VALUES (?, ?, ?, 'activo')
        ", [$label, $slug, (int) $order]);

        return (bool) $inserted;
    }

    private static function sanitizeAgainstCatalog(array $areas) {
        $normalized = [];
        foreach ($areas as $area) {
            $label = self::canonicalizeLabel($area);
            if ($label !== '' && !in_array($label, $normalized, true)) {
                $normalized[] = $label;
            }
        }

        return $normalized;
    }

    private static function getCatalogRows() {
        return Database::select("
            SELECT nombre, slug, orden
            FROM areas_tecnicas
            WHERE estado = 'activo'
            ORDER BY orden ASC, nombre ASC
        ");
    }

    private static function buildEnumList() {
        $values = [];
        foreach (self::getCatalogRows() as $row) {
            $area = (string) ($row['nombre'] ?? '');
            if ($area !== '') {
                $values[] = "'" . str_replace("'", "\\'", $area) . "'";
            }
        }

        return implode(', ', $values);
    }

    private static function getUsageForCenterArea($centerId, $label) {
        $usage = Database::selectOne("
            SELECT
                (SELECT COUNT(*) FROM estudiantes WHERE centro_id = ? AND area_tecnica = ?) AS students,
                (SELECT COUNT(*) FROM empresas WHERE centro_id = ? AND area_tecnica = ?) AS companies,
                (
                    SELECT COUNT(*)
                    FROM evaluaciones ev
                    JOIN estudiantes est ON est.id = ev.estudiante_id
                    WHERE est.centro_id = ? AND est.area_tecnica = ?
                ) AS evaluations,
                (
                    SELECT COUNT(*)
                    FROM asignaciones a
                    JOIN estudiantes est ON est.id = a.estudiante_id
                    WHERE est.centro_id = ? AND est.area_tecnica = ? AND a.estado = 'activa'
                ) AS active_assignments
        ", [
            $centerId, $label,
            $centerId, $label,
            $centerId, $label,
            $centerId, $label,
        ]);

        return is_array($usage) ? $usage : [
            'students' => 0,
            'companies' => 0,
            'evaluations' => 0,
            'active_assignments' => 0,
        ];
    }

    private static function indexRowsByArea(array $rows) {
        $indexed = [];
        foreach ($rows as $row) {
            $key = self::normalizeKey($row['area_tecnica'] ?? '');
            if ($key !== '') {
                $indexed[$key] = $row;
            }
        }
        return $indexed;
    }

    private static function resequenceCenterAreas($centerId) {
        $rows = Database::select("
            SELECT id
            FROM centro_areas
            WHERE centro_id = ?
            ORDER BY orden ASC, id ASC
        ", [$centerId]);

        foreach ($rows as $index => $row) {
            Database::execute("
                UPDATE centro_areas
                SET orden = ?
                WHERE id = ?
            ", [$index + 1, $row['id']]);
        }
    }

    private static function canonicalizeLabel($value) {
        $normalized = self::normalizeKey($value);
        if ($normalized === '') {
            return '';
        }

        $catalogMap = [];
        foreach (self::getCatalog() as $label) {
            $catalogMap[self::normalizeKey($label)] = $label;
        }

        return $catalogMap[$normalized] ?? '';
    }

    private static function prepareLabel($value) {
        $value = trim((string) strip_tags((string) $value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value);
        if ($value === null) {
            return '';
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords(strtolower($value));
    }

    private static function getLabelLength($value) {
        return function_exists('mb_strlen')
            ? mb_strlen((string) $value, 'UTF-8')
            : strlen((string) $value);
    }

    private static function normalizeKey($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value);
        return $value ?? '';
    }
}
