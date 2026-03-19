<?php

class PasswordReset {
    private const TOKEN_TTL_MINUTES = 60;
    private static $booted = false;

    public static function ensureSchema() {
        if (self::$booted) {
            return;
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_password_reset_token (token_hash),
                KEY idx_password_reset_user (user_id),
                KEY idx_password_reset_expires (expires_at),
                CONSTRAINT fk_password_reset_user
                    FOREIGN KEY (user_id) REFERENCES usuarios(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        self::$booted = true;
        self::cleanupExpired();
    }

    public static function createForUser($userId) {
        self::ensureSchema();

        $userId = (int) $userId;
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuario invalido para recuperacion');
        }

        self::invalidateUserTokens($userId);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_TTL_MINUTES . ' minutes'));

        $resetId = Database::insert("
            INSERT INTO password_resets (user_id, token_hash, expires_at)
            VALUES (?, ?, ?)
        ", [$userId, $tokenHash, $expiresAt]);

        if (!$resetId) {
            throw new RuntimeException('No se pudo generar el enlace de recuperacion');
        }

        return [
            'id' => (int) $resetId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public static function findValidRequest($token) {
        self::ensureSchema();

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        return Database::selectOne("
            SELECT
                pr.id,
                pr.user_id,
                pr.expires_at,
                u.nombre,
                u.correo,
                c.codigo_unico AS centro_codigo,
                c.nombre AS centro_nombre
            FROM password_resets pr
            JOIN usuarios u ON u.id = pr.user_id
            LEFT JOIN centros c ON c.id = u.centro_id
            WHERE pr.token_hash = ?
              AND pr.used_at IS NULL
              AND pr.expires_at >= NOW()
              AND u.estado = 'activo'
            LIMIT 1
        ", [hash('sha256', $token)]);
    }

    public static function markUsed($resetId) {
        self::ensureSchema();

        return Database::execute("
            UPDATE password_resets
            SET used_at = NOW()
            WHERE id = ? AND used_at IS NULL
        ", [(int) $resetId]);
    }

    public static function invalidateUserTokens($userId, $exceptId = 0) {
        self::ensureSchema();

        $userId = (int) $userId;
        $exceptId = (int) $exceptId;

        $query = "
            UPDATE password_resets
            SET used_at = NOW()
            WHERE user_id = ?
              AND used_at IS NULL
        ";
        $params = [$userId];

        if ($exceptId > 0) {
            $query .= " AND id <> ?";
            $params[] = $exceptId;
        }

        return Database::execute($query, $params);
    }

    public static function cleanupExpired() {
        Database::execute("
            DELETE FROM password_resets
            WHERE used_at IS NOT NULL OR expires_at < NOW()
        ");
    }
}
