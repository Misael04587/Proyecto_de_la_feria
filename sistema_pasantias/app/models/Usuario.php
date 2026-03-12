<?php
// app/models/Usuario.php

class Usuario {
    /**
     * Busca usuario por correo y centro.
     */
    public static function findByEmailAndCenter($email, $centerCode) {
        $query = "
            SELECT u.*, r.nombre as rol_nombre, c.id as centro_id, c.nombre as centro_nombre
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            LEFT JOIN centros c ON u.centro_id = c.id
            WHERE u.correo = ?
            AND (u.centro_id IS NULL OR c.codigo_unico = ?)
            AND u.estado = 'activo'
        ";

        return Database::selectOne($query, [$email, $centerCode]);
    }

    /**
     * Verifica si un codigo de centro existe y esta activo.
     */
    public static function centerExists($centerCode) {
        $query = "SELECT id, nombre, codigo_unico FROM centros WHERE codigo_unico = ? AND estado = 'activo'";
        return Database::selectOne($query, [$centerCode]);
    }

    /**
     * Crea un nuevo usuario estudiante con matricula automatica.
     */
    public static function createStudent($data) {
        $center = self::centerExists($data['codigo_centro']);
        if (!$center) {
            throw new Exception('Codigo de centro invalido o centro inactivo');
        }

        $allowedAreas = AreaTecnica::getAreasByCenterId((int) $center['id']);
        if (empty($data['area_tecnica']) || !in_array($data['area_tecnica'], $allowedAreas, true)) {
            throw new Exception('El area tecnica seleccionada no pertenece al centro indicado');
        }

        $query = "SELECT id FROM usuarios WHERE correo = ?";
        $existing = Database::selectOne($query, [$data['correo']]);

        if ($existing) {
            throw new Exception('El correo ya esta registrado');
        }

        Database::beginTransaction();

        try {
            $matricula = self::generateNextMatricula($center['id']);

            $userId = Database::insert("
                INSERT INTO usuarios (centro_id, rol_id, nombre, correo, password, estado)
                VALUES (?, ?, ?, ?, ?, 'activo')
            ", [
                $center['id'],
                1,
                $data['nombre'],
                $data['correo'],
                Security::hashPassword($data['password'])
            ]);

            if (!$userId) {
                throw new Exception('Error al crear usuario');
            }

            $studentId = Database::insert("
                INSERT INTO estudiantes (usuario_id, centro_id, matricula, area_tecnica)
                VALUES (?, ?, ?, ?)
            ", [
                $userId,
                $center['id'],
                $matricula,
                $data['area_tecnica']
            ]);

            if (!$studentId) {
                throw new Exception('Error al crear estudiante');
            }

            Database::commit();

            return [
                'user_id' => $userId,
                'student_id' => $studentId,
                'center_id' => $center['id'],
                'center_name' => $center['nombre'],
                'center_code' => $center['codigo_unico'],
                'matricula' => $matricula
            ];
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Genera la siguiente matricula del centro en el ano actual.
     */
    private static function generateNextMatricula($centerId) {
        $year = date('Y');
        $likePattern = $year . '-%';

        $query = "
            SELECT matricula
            FROM estudiantes
            WHERE centro_id = ?
            AND matricula LIKE ?
            ORDER BY matricula DESC
            LIMIT 1
        ";

        $lastStudent = Database::selectOne($query, [$centerId, $likePattern]);
        $sequence = 1;

        if (!empty($lastStudent['matricula'])) {
            $parts = explode('-', $lastStudent['matricula']);
            $sequence = ((int) ($parts[1] ?? 0)) + 1;
        }

        if ($sequence > 999) {
            throw new Exception('No hay mas matriculas disponibles para este centro en el ano actual');
        }

        return sprintf('%s-%03d', $year, $sequence);
    }

    /**
     * Actualiza datos de usuario.
     */
    public static function update($userId, $data) {
        $allowedFields = ['nombre', 'correo'];
        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields, true)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $userId;
        $query = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?";

        return Database::execute($query, $params);
    }

    /**
     * Cambia contrasena.
     */
    public static function changePassword($userId, $currentPassword, $newPassword) {
        $user = Database::selectOne("SELECT password FROM usuarios WHERE id = ?", [$userId]);

        if (!$user || !Security::verifyPassword($currentPassword, $user['password'])) {
            return false;
        }

        $newHash = Security::hashPassword($newPassword);
        return Database::execute(
            "UPDATE usuarios SET password = ? WHERE id = ?",
            [$newHash, $userId]
        );
    }

    /**
     * Verifica si usuario tiene CV subido.
     */
    public static function hasCV($userId) {
        $query = "
            SELECT e.cv_path
            FROM estudiantes e
            JOIN usuarios u ON e.usuario_id = u.id
            WHERE u.id = ? AND e.cv_path IS NOT NULL
        ";

        $result = Database::selectOne($query, [$userId]);
        return !empty($result['cv_path']);
    }
}
