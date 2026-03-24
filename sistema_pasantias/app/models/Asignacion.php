<?php

class Asignacion {
    private static $booted = false;

    public static function ensureSchema() {
        if (self::$booted) {
            return;
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS asignaciones (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                estudiante_id INT NOT NULL,
                empresa_id INT NOT NULL,
                fecha_asignacion DATE NOT NULL,
                estado ENUM('activa', 'finalizada', 'cancelada') NOT NULL DEFAULT 'activa',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_empresa (empresa_id),
                KEY idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $companyColumn = Database::selectOne("SHOW COLUMNS FROM asignaciones LIKE 'empresa_id'");
        if (!$companyColumn) {
            Database::execute("
                ALTER TABLE asignaciones
                ADD COLUMN empresa_id INT NOT NULL AFTER estudiante_id
            ");
        }

        $dateColumn = Database::selectOne("SHOW COLUMNS FROM asignaciones LIKE 'fecha_asignacion'");
        if (!$dateColumn) {
            Database::execute("
                ALTER TABLE asignaciones
                ADD COLUMN fecha_asignacion DATE NULL AFTER empresa_id
            ");
            Database::execute("
                UPDATE asignaciones
                SET fecha_asignacion = COALESCE(fecha_asignacion, DATE(created_at), CURDATE())
            ");
        }

        $stateColumn = Database::selectOne("SHOW COLUMNS FROM asignaciones LIKE 'estado'");
        if (!$stateColumn) {
            Database::execute("
                ALTER TABLE asignaciones
                ADD COLUMN estado ENUM('activa', 'finalizada', 'cancelada')
                    NOT NULL DEFAULT 'activa'
                    AFTER fecha_asignacion
            ");
        }

        $createdAtColumn = Database::selectOne("SHOW COLUMNS FROM asignaciones LIKE 'created_at'");
        if (!$createdAtColumn) {
            Database::execute("
                ALTER TABLE asignaciones
                ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    AFTER estado
            ");
            Database::execute("
                UPDATE asignaciones
                SET created_at = COALESCE(created_at, CURRENT_TIMESTAMP)
            ");
        }

        $updatedAtColumn = Database::selectOne("SHOW COLUMNS FROM asignaciones LIKE 'updated_at'");
        if (!$updatedAtColumn) {
            Database::execute("
                ALTER TABLE asignaciones
                ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
                    AFTER created_at
            ");
        }

        $studentIndex = Database::selectOne("SHOW INDEX FROM asignaciones WHERE Key_name = 'estudiante_id'");
        if ($studentIndex && (int) ($studentIndex['Non_unique'] ?? 1) === 0) {
            Database::execute("ALTER TABLE asignaciones DROP INDEX estudiante_id");
        }

        $studentLookupIndex = Database::selectOne("SHOW INDEX FROM asignaciones WHERE Key_name = 'idx_estudiante'");
        if (!$studentLookupIndex) {
            Database::execute("ALTER TABLE asignaciones ADD INDEX idx_estudiante (estudiante_id)");
        }

        $companyLookupIndex = Database::selectOne("SHOW INDEX FROM asignaciones WHERE Key_name = 'idx_empresa'");
        if (!$companyLookupIndex) {
            Database::execute("ALTER TABLE asignaciones ADD INDEX idx_empresa (empresa_id)");
        }

        $stateLookupIndex = Database::selectOne("SHOW INDEX FROM asignaciones WHERE Key_name = 'idx_estado'");
        if (!$stateLookupIndex) {
            Database::execute("ALTER TABLE asignaciones ADD INDEX idx_estado (estado)");
        }

        $pairIndex = Database::selectOne("SHOW INDEX FROM asignaciones WHERE Key_name = 'uniq_asignacion_estudiante_empresa'");
        if (!$pairIndex) {
            Database::execute("
                ALTER TABLE asignaciones
                ADD UNIQUE KEY uniq_asignacion_estudiante_empresa (estudiante_id, empresa_id)
            ");
        }

        self::$booted = true;
    }

    public static function crearDesdeEvaluacion($evaluationId, $centerId) {
        self::ensureSchema();
        Evaluacion::ensureWorkflowSchema();

        $evaluationId = (int) $evaluationId;
        $centerId = (int) $centerId;
        if ($evaluationId <= 0 || $centerId <= 0) {
            throw new InvalidArgumentException('Datos invalidos para asignar la pasantia');
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    ev.id,
                    ev.estado,
                    ev.seguimiento_estado,
                    ev.estudiante_id,
                    ev.empresa_id,
                    est.centro_id,
                    em.nombre AS empresa_nombre,
                    em.cupos
                FROM evaluaciones ev
                JOIN estudiantes est ON est.id = ev.estudiante_id
                JOIN empresas em ON em.id = ev.empresa_id
                WHERE ev.id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$evaluationId]);
            $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$evaluation || (int) ($evaluation['centro_id'] ?? 0) !== $centerId) {
                throw new RuntimeException('No se encontro la evaluacion seleccionada');
            }

            if (($evaluation['estado'] ?? '') !== 'aprobado') {
                throw new RuntimeException('Solo puedes asignar evaluaciones aprobadas');
            }

            if (($evaluation['seguimiento_estado'] ?? 'sin_revisar') !== 'preseleccionado') {
                throw new RuntimeException('Primero debes marcar la evaluacion como preseleccionada');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM asignaciones
                WHERE estudiante_id = ? AND estado = 'activa'
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([(int) $evaluation['estudiante_id']]);
            if ($stmt->fetch()) {
                throw new RuntimeException('El estudiante ya tiene una pasantia activa');
            }

            $stmt = $pdo->prepare("
                SELECT id, estado
                FROM asignaciones
                WHERE estudiante_id = ? AND empresa_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                (int) $evaluation['estudiante_id'],
                (int) $evaluation['empresa_id'],
            ]);
            $existingPair = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingPair) {
                throw new RuntimeException('Ya existe un historial de pasantia para este estudiante con esa empresa');
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total
                FROM asignaciones
                WHERE empresa_id = ? AND estado = 'activa'
                FOR UPDATE
            ");
            $stmt->execute([(int) $evaluation['empresa_id']]);
            $activeAssignments = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));
            if ($activeAssignments >= (int) ($evaluation['cupos'] ?? 0)) {
                throw new RuntimeException('La empresa ya lleno todos sus cupos disponibles');
            }

            $stmt = $pdo->prepare("
                INSERT INTO asignaciones (estudiante_id, empresa_id, fecha_asignacion, estado)
                VALUES (?, ?, CURDATE(), 'activa')
            ");
            $stmt->execute([
                (int) $evaluation['estudiante_id'],
                (int) $evaluation['empresa_id'],
            ]);

            self::syncCompanyState($pdo, (int) $evaluation['empresa_id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function actualizarEstado($assignmentId, $centerId, $targetState) {
        self::ensureSchema();

        $assignmentId = (int) $assignmentId;
        $centerId = (int) $centerId;
        $targetState = trim((string) $targetState);

        if ($assignmentId <= 0 || $centerId <= 0) {
            throw new InvalidArgumentException('Datos invalidos para actualizar la pasantia');
        }

        if (!in_array($targetState, ['activa', 'finalizada', 'cancelada'], true)) {
            throw new InvalidArgumentException('Estado de pasantia no valido');
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    a.id,
                    a.estudiante_id,
                    a.empresa_id,
                    a.estado,
                    est.centro_id,
                    em.nombre AS empresa_nombre
                FROM asignaciones a
                JOIN estudiantes est ON est.id = a.estudiante_id
                JOIN empresas em ON em.id = a.empresa_id
                WHERE a.id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$assignmentId]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment || (int) ($assignment['centro_id'] ?? 0) !== $centerId) {
                throw new RuntimeException('No se encontro la pasantia seleccionada');
            }

            if (($assignment['estado'] ?? '') === $targetState) {
                throw new RuntimeException('La pasantia ya estaba en ese estado');
            }

            if ($targetState === 'activa') {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM asignaciones
                    WHERE estudiante_id = ? AND estado = 'activa' AND id <> ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->execute([
                    (int) $assignment['estudiante_id'],
                    $assignmentId,
                ]);
                if ($stmt->fetch()) {
                    throw new RuntimeException('El estudiante ya tiene otra pasantia activa');
                }

                $stmt = $pdo->prepare("
                    SELECT cupos
                    FROM empresas
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->execute([(int) $assignment['empresa_id']]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$company) {
                    throw new RuntimeException('No se encontro la empresa vinculada a la pasantia');
                }

                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS total
                    FROM asignaciones
                    WHERE empresa_id = ? AND estado = 'activa' AND id <> ?
                    FOR UPDATE
                ");
                $stmt->execute([
                    (int) $assignment['empresa_id'],
                    $assignmentId,
                ]);
                $activeAssignments = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));

                if ($activeAssignments >= (int) ($company['cupos'] ?? 0)) {
                    throw new RuntimeException('La empresa ya lleno todos sus cupos disponibles');
                }
            }

            $stmt = $pdo->prepare("
                UPDATE asignaciones
                SET estado = ?
                WHERE id = ?
            ");
            $stmt->execute([$targetState, $assignmentId]);

            self::syncCompanyState($pdo, (int) $assignment['empresa_id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function recalcularEstadoEmpresa($companyId) {
        self::ensureSchema();

        $pdo = Database::getInstance();
        self::syncCompanyState($pdo, (int) $companyId);
    }

    private static function syncCompanyState(PDO $pdo, $companyId) {
        $stmt = $pdo->prepare("
            SELECT
                e.cupos,
                (
                    SELECT COUNT(*)
                    FROM asignaciones a
                    WHERE a.empresa_id = e.id AND a.estado = 'activa'
                ) AS asignados
            FROM empresas e
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int) $companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            return;
        }

        $state = ((int) ($company['asignados'] ?? 0) >= (int) ($company['cupos'] ?? 0))
            ? 'completo'
            : 'disponible';

        $stmt = $pdo->prepare("
            UPDATE empresas
            SET estado = ?
            WHERE id = ?
        ");
        $stmt->execute([$state, (int) $companyId]);
    }
}
