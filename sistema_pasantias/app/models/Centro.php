<?php
// app/models/Centro.php

class Centro {
    /**
     * Registra un centro y genera un codigo unico valido para login y registro.
     */
    public static function create($nombre) {
        $center = self::createCenterRecord($nombre);
        AreaTecnica::saveAreasForCenter((int) $center['id'], AreaTecnica::getDefaultAreas());
        $center['areas_tecnicas'] = AreaTecnica::getDefaultAreas();
        return $center;
    }

    /**
     * Registra el centro junto a su cuenta administradora.
     */
    public static function createWithAdmin($data) {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $nombreAdmin = trim((string) ($data['nombre_admin'] ?? ''));
        $correoAdmin = trim((string) ($data['correo_admin'] ?? ''));
        $areasTecnicas = AreaTecnica::sanitizeAreas($data['areas_tecnicas'] ?? []);
        $password = (string) ($data['password'] ?? '');

        if ($nombre === '') {
            throw new Exception('El nombre del centro es obligatorio');
        }

        if ($nombreAdmin === '') {
            throw new Exception('El nombre del administrador es obligatorio');
        }

        if ($correoAdmin === '') {
            throw new Exception('El correo del administrador es obligatorio');
        }

        if (empty($areasTecnicas)) {
            throw new Exception('Selecciona al menos un area tecnica para el centro');
        }

        if ($password === '') {
            throw new Exception('La contrasena del administrador es obligatoria');
        }

        $adminRole = Database::selectOne("
            SELECT id
            FROM roles
            WHERE nombre = 'admin_centro'
            LIMIT 1
        ");

        if (!$adminRole) {
            throw new Exception('No se encontro el rol de administrador de centro');
        }

        $existingUser = Database::selectOne("
            SELECT id
            FROM usuarios
            WHERE correo = ?
            LIMIT 1
        ", [$correoAdmin]);

        if ($existingUser) {
            throw new Exception('El correo del administrador ya esta registrado');
        }

        Database::beginTransaction();

        try {
            $center = self::createCenterRecord($nombre);
            $adminDisplayName = self::normalizeAdminDisplayName($nombreAdmin);

            $userId = Database::insert("
                INSERT INTO usuarios (centro_id, rol_id, nombre, correo, password, estado)
                VALUES (?, ?, ?, ?, ?, 'activo')
            ", [
                $center['id'],
                $adminRole['id'],
                $adminDisplayName,
                $correoAdmin,
                Security::hashPassword($password),
            ]);

            if (!$userId) {
                throw new Exception('No se pudo crear la cuenta administradora del centro');
            }

            AreaTecnica::saveAreasForCenter((int) $center['id'], $areasTecnicas);

            Database::commit();

            $center['admin_user_id'] = $userId;
            $center['admin_correo'] = $correoAdmin;
            $center['admin_nombre'] = $adminDisplayName;
            $center['areas_tecnicas'] = $areasTecnicas;

            return $center;
        } catch (Throwable $exception) {
            Database::rollback();
            throw $exception;
        }
    }

    private static function createCenterRecord($nombre) {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw new Exception('El nombre del centro es obligatorio');
        }

        if (strlen($nombre) < 4) {
            throw new Exception('El nombre del centro debe tener al menos 4 caracteres');
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $codigo = self::generateUniqueCode($nombre);

            if (self::codeExists($codigo)) {
                continue;
            }

            $centerId = Database::insert("
                INSERT INTO centros (nombre, codigo_unico, estado)
                VALUES (?, ?, 'activo')
            ", [$nombre, $codigo]);

            if ($centerId) {
                return [
                    'id' => $centerId,
                    'nombre' => $nombre,
                    'codigo_unico' => $codigo
                ];
            }
        }

        throw new Exception('No se pudo generar un codigo unico para el centro. Intenta de nuevo.');
    }

    /**
     * Verifica si un codigo ya existe.
     */
    public static function codeExists($codigo) {
        $query = "SELECT id FROM centros WHERE codigo_unico = ?";
        return (bool) Database::selectOne($query, [$codigo]);
    }

    /**
     * Genera un codigo del tipo ABCD-1F2G-2026.
     */
    private static function generateUniqueCode($nombre) {
        $prefix = self::buildPrefix($nombre);
        $randomBlock = strtoupper(str_pad(base_convert((string) random_int(0, 1679615), 10, 36), 4, '0', STR_PAD_LEFT));
        $year = date('Y');

        return $prefix . '-' . $randomBlock . '-' . $year;
    }

    /**
     * Crea un prefijo de 4 caracteres basado en el nombre del centro.
     */
    private static function buildPrefix($nombre) {
        $normalized = self::normalizeText($nombre);
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $prefix = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $prefix .= substr($word, 0, 1);

            if (strlen($prefix) >= 4) {
                break;
            }
        }

        if (strlen($prefix) < 4) {
            $joined = implode('', $words);
            $prefix .= substr($joined, strlen($prefix), 4 - strlen($prefix));
        }

        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);
        $prefix = strtoupper(substr($prefix, 0, 4));

        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'C');
        }

        return $prefix;
    }

    private static function normalizeAdminDisplayName($nombreAdmin) {
        $baseName = trim($nombreAdmin);
        if (function_exists('mb_substr')) {
            return mb_substr($baseName, 0, 100, 'UTF-8');
        }

        return substr($baseName, 0, 100);
    }

    /**
     * Normaliza el texto para construir el codigo sin tildes ni simbolos.
     */
    private static function normalizeText($text) {
        $map = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ñ' => 'N',
            'á' => 'A', 'à' => 'A', 'â' => 'A', 'ä' => 'A',
            'é' => 'E', 'è' => 'E', 'ê' => 'E', 'ë' => 'E',
            'í' => 'I', 'ì' => 'I', 'î' => 'I', 'ï' => 'I',
            'ó' => 'O', 'ò' => 'O', 'ô' => 'O', 'ö' => 'O',
            'ú' => 'U', 'ù' => 'U', 'û' => 'U', 'ü' => 'U',
            'ñ' => 'N'
        ];

        $text = strtr($text, $map);
        $text = strtoupper($text);
        $text = preg_replace('/[^A-Z0-9 ]/', ' ', $text);

        return trim($text);
    }
}
