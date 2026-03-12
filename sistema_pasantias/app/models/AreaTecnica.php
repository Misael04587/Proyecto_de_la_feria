<?php
// app/models/AreaTecnica.php

class AreaTecnica {
    private const DEFAULT_AREAS = [
        'Gastronomía',
        'Administración',
        'Electricidad',
        'Informática',
    ];

    private const CATALOG = [
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
        return self::CATALOG;
    }

    public static function getDefaultAreas() {
        self::ensureSchema();
        return self::DEFAULT_AREAS;
    }

    public static function sanitizeAreas($areas) {
        self::ensureSchema();

        if (!is_array($areas)) {
            return [];
        }

        $normalized = [];
        foreach ($areas as $area) {
            $label = self::canonicalizeLabel($area);
            if ($label !== '' && !in_array($label, $normalized, true)) {
                $normalized[] = $label;
            }
        }

        return $normalized;
    }

    public static function getAreasByCenterId($centerId) {
        self::ensureSchema();

        $centerId = (int) $centerId;
        if ($centerId <= 0) {
            return self::DEFAULT_AREAS;
        }

        $rows = Database::select("
            SELECT area_tecnica
            FROM centro_areas
            WHERE centro_id = ?
            ORDER BY orden ASC, id ASC
        ", [$centerId]);

        $areas = [];
        foreach ($rows as $row) {
            $label = self::canonicalizeLabel($row['area_tecnica'] ?? '');
            if ($label !== '' && !in_array($label, $areas, true)) {
                $areas[] = $label;
            }
        }

        return !empty($areas) ? $areas : self::DEFAULT_AREAS;
    }

    public static function getAreasByCenterCode($centerCode) {
        self::ensureSchema();

        $centerCode = trim((string) $centerCode);
        if ($centerCode === '') {
            return self::DEFAULT_AREAS;
        }

        $center = Database::selectOne("
            SELECT id
            FROM centros
            WHERE codigo_unico = ? AND estado = 'activo'
            LIMIT 1
        ", [$centerCode]);

        if (!$center) {
            return self::DEFAULT_AREAS;
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
            $areas = self::DEFAULT_AREAS;
        }

        if (!Database::execute("DELETE FROM centro_areas WHERE centro_id = ?", [$centerId])) {
            throw new RuntimeException('No se pudieron reiniciar las areas del centro');
        }

        foreach ($areas as $index => $area) {
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

    private static function ensureSchema() {
        if (self::$booted) {
            return;
        }

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

        self::ensureCentroAreasColumns();
        self::ensureEnumSupport('empresas', 'area_tecnica');
        self::ensureEnumSupport('preguntas', 'area_tecnica');
        self::$booted = true;
    }

    private static function ensureEnumSupport($table, $column) {
        $definition = Database::selectOne("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (!$definition || empty($definition['Type'])) {
            return;
        }

        $type = (string) $definition['Type'];
        $missingArea = false;
        foreach (self::CATALOG as $area) {
            if (strpos($type, "'" . str_replace("'", "\\'", $area) . "'") === false) {
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
            self::buildEnumList()
        ));
    }

    private static function ensureCentroAreasColumns() {
        $ordenColumn = Database::selectOne("SHOW COLUMNS FROM centro_areas LIKE 'orden'");
        if (!$ordenColumn) {
            Database::execute("ALTER TABLE centro_areas ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER area_tecnica");
        }
    }

    private static function buildEnumList() {
        $values = [];
        foreach (self::CATALOG as $area) {
            $values[] = "'" . str_replace("'", "\\'", $area) . "'";
        }
        return implode(', ', $values);
    }

    private static function canonicalizeLabel($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $map = [];
        foreach (self::CATALOG as $label) {
            $map[self::normalizeKey($label)] = $label;
        }

        $normalized = self::normalizeKey($value);
        return $map[$normalized] ?? '';
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
        return $value;
    }
}
