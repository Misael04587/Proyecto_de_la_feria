<?php

class Evaluacion {
    public const MAX_PREGUNTAS = 20;
    public const MIN_PREGUNTAS = 15;
    public const DURACION_MINUTOS = 30;
    public const NOTA_APROBACION = 70;
    private static $workflowBooted = false;

    public static function ensureWorkflowSchema() {
        if (self::$workflowBooted) {
            return;
        }

        $stateColumn = Database::selectOne("SHOW COLUMNS FROM evaluaciones LIKE 'seguimiento_estado'");
        if (!$stateColumn) {
            Database::execute("
                ALTER TABLE evaluaciones
                ADD COLUMN seguimiento_estado ENUM('sin_revisar', 'en_revision', 'preseleccionado', 'descartado')
                    NOT NULL DEFAULT 'sin_revisar'
                    AFTER estado
            ");
        }

        $commentColumn = Database::selectOne("SHOW COLUMNS FROM evaluaciones LIKE 'seguimiento_comentario'");
        if (!$commentColumn) {
            Database::execute("
                ALTER TABLE evaluaciones
                ADD COLUMN seguimiento_comentario TEXT NULL
                    AFTER seguimiento_estado
            ");
        }

        $dateColumn = Database::selectOne("SHOW COLUMNS FROM evaluaciones LIKE 'seguimiento_fecha'");
        if (!$dateColumn) {
            Database::execute("
                ALTER TABLE evaluaciones
                ADD COLUMN seguimiento_fecha DATETIME NULL
                    AFTER seguimiento_comentario
            ");
        }

        self::$workflowBooted = true;
    }

    public static function generarExamenUnico($estudiante_id, $empresa_id, $area_tecnica, $num_preguntas = null) {
        Pregunta::ensureSchema();
        self::ensureWorkflowSchema();
        $pdo = Database::getInstance();
        $cantidad_preguntas = self::resolverCantidadPreguntas($area_tecnica, $num_preguntas);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id
                FROM evaluaciones
                WHERE estudiante_id = ? AND empresa_id = ?
                LIMIT 1
            ");
            $stmt->execute([$estudiante_id, $empresa_id]);

            if ($stmt->fetch()) {
                throw new Exception('Ya existe una evaluacion para esta empresa');
            }

            $stmt = $pdo->prepare("
                INSERT INTO evaluaciones (estudiante_id, empresa_id, estado, tiempo_inicio)
                VALUES (?, ?, 'pendiente', NOW())
            ");
            $stmt->execute([$estudiante_id, $empresa_id]);
            $evaluacion_id = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT id
                FROM preguntas
                WHERE area_tecnica = ?
                  AND estado = 'activo'
                ORDER BY RAND()
                LIMIT ?
            ");
            $stmt->bindValue(1, $area_tecnica, PDO::PARAM_STR);
            $stmt->bindValue(2, $cantidad_preguntas, PDO::PARAM_INT);
            $stmt->execute();
            $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($preguntas) < $cantidad_preguntas) {
                throw new Exception('No hay suficientes preguntas disponibles para generar el examen');
            }

            $stmt = $pdo->prepare("
                INSERT INTO evaluacion_preguntas (evaluacion_id, pregunta_id, orden)
                VALUES (?, ?, ?)
            ");

            foreach ($preguntas as $indice => $pregunta) {
                $stmt->execute([$evaluacion_id, $pregunta['id'], $indice + 1]);
            }

            $pdo->commit();
            return $evaluacion_id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function getCantidadPreguntasDisponible($area_tecnica) {
        Pregunta::ensureSchema();
        $resultado = Database::selectOne("
            SELECT COUNT(*) AS total
            FROM preguntas
            WHERE area_tecnica = ?
              AND estado = 'activo'
        ", [$area_tecnica]);

        return (int) ($resultado['total'] ?? 0);
    }

    public static function resolverCantidadPreguntas($area_tecnica, $cantidad_solicitada = null) {
        $cantidad_disponible = self::getCantidadPreguntasDisponible($area_tecnica);
        $cantidad_objetivo = $cantidad_solicitada === null
            ? self::MAX_PREGUNTAS
            : (int) $cantidad_solicitada;

        if ($cantidad_disponible >= $cantidad_objetivo) {
            return $cantidad_objetivo;
        }

        if ($cantidad_disponible >= self::MIN_PREGUNTAS) {
            return $cantidad_disponible;
        }

        throw new Exception('Tu area tecnica aun no tiene suficientes preguntas para abrir el examen');
    }

    public static function getEvaluacionParaEstudiante($evaluacion_id, $estudiante_id) {
        self::ensureWorkflowSchema();

        return Database::selectOne("
            SELECT
                ev.*,
                em.nombre AS empresa_nombre,
                em.area_tecnica,
                em.descripcion AS empresa_descripcion,
                u.nombre AS estudiante_nombre
            FROM evaluaciones ev
            JOIN empresas em ON em.id = ev.empresa_id
            JOIN estudiantes est ON est.id = ev.estudiante_id
            JOIN usuarios u ON u.id = est.usuario_id
            WHERE ev.id = ? AND ev.estudiante_id = ?
            LIMIT 1
        ", [$evaluacion_id, $estudiante_id]);
    }

    public static function getEvaluacionPendientePorEstudiante($estudiante_id) {
        return Database::selectOne("
            SELECT id
            FROM evaluaciones
            WHERE estudiante_id = ? AND estado = 'pendiente'
            ORDER BY created_at DESC
            LIMIT 1
        ", [$estudiante_id]);
    }

    public static function getPreguntasDeEvaluacion($evaluacion_id) {
        return Database::select("
            SELECT
                ep.id AS evaluacion_pregunta_id,
                ep.respuesta_estudiante,
                ep.orden,
                p.id AS pregunta_id,
                p.pregunta,
                p.opcion_a,
                p.opcion_b,
                p.opcion_c,
                p.opcion_d
            FROM evaluacion_preguntas ep
            JOIN preguntas p ON p.id = ep.pregunta_id
            WHERE ep.evaluacion_id = ?
            ORDER BY ep.orden ASC, ep.id ASC
        ", [$evaluacion_id]);
    }

    public static function getTiempoRestante($evaluacion) {
        if (empty($evaluacion['tiempo_inicio']) || ($evaluacion['estado'] ?? '') !== 'pendiente') {
            return 0;
        }

        $inicio = strtotime((string) $evaluacion['tiempo_inicio']);
        if ($inicio === false) {
            return 0;
        }

        $fin = $inicio + (self::DURACION_MINUTOS * 60);
        return max(0, $fin - time());
    }

    public static function estaVencida($evaluacion) {
        return self::getTiempoRestante($evaluacion) <= 0;
    }

    public static function procesarExamen($evaluacion_id, $respuestas, $forzar_estado = null) {
        self::ensureWorkflowSchema();
        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, estado, estudiante_id, empresa_id
                FROM evaluaciones
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$evaluacion_id]);
            $evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$evaluacion) {
                throw new Exception('La evaluacion no existe');
            }

            if ($evaluacion['estado'] !== 'pendiente') {
                $pdo->commit();
                return self::getResumenEvaluacion($evaluacion_id);
            }

            $preguntas = self::getPreguntasParaProcesar($pdo, $evaluacion_id);
            if (empty($preguntas)) {
                throw new Exception('La evaluacion no tiene preguntas asignadas');
            }

            $stmt_actualizar = $pdo->prepare("
                UPDATE evaluacion_preguntas
                SET respuesta_estudiante = ?
                WHERE id = ?
            ");

            $correctas = 0;
            foreach ($preguntas as $pregunta) {
                $respuesta = self::normalizarRespuesta($respuestas[$pregunta['id']] ?? null);
                $stmt_actualizar->execute([$respuesta, $pregunta['id']]);

                if ($respuesta !== null && $respuesta === $pregunta['respuesta_correcta']) {
                    $correctas++;
                }
            }

            $total = count($preguntas);
            $nota = $total > 0 ? round(($correctas / $total) * 100, 2) : 0.0;
            $estado_final = $forzar_estado ?: ($nota >= self::NOTA_APROBACION ? 'aprobado' : 'reprobado');

            if ($estado_final === 'anulado') {
                $nota = 0.0;
            }

            $stmt = $pdo->prepare("
                UPDATE evaluaciones
                SET estado = ?,
                    seguimiento_estado = 'sin_revisar',
                    seguimiento_comentario = NULL,
                    seguimiento_fecha = NULL,
                    nota = ?,
                    tiempo_fin = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$estado_final, $nota, $evaluacion_id]);

            $pdo->commit();
            return self::getResumenEvaluacion($evaluacion_id);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function registrarEventoSeguridad($evaluacion_id, $evento, $detalles = '') {
        Database::execute("
            INSERT INTO logs_seguridad (evaluacion_id, evento, detalles, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ", [
            $evaluacion_id,
            $evento,
            $detalles,
            Security::getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido',
        ]);
    }

    public static function getUltimoEventoSeguridad($evaluacion_id) {
        return Database::selectOne("
            SELECT evento, detalles, created_at
            FROM logs_seguridad
            WHERE evaluacion_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ", [$evaluacion_id]);
    }

    public static function getResumenEvaluacion($evaluacion_id) {
        self::ensureWorkflowSchema();

        $evaluacion = Database::selectOne("
            SELECT
                ev.id,
                ev.estado,
                ev.seguimiento_estado,
                ev.seguimiento_comentario,
                ev.seguimiento_fecha,
                ev.nota,
                ev.tiempo_inicio,
                ev.tiempo_fin,
                em.nombre AS empresa_nombre,
                em.area_tecnica
            FROM evaluaciones ev
            JOIN empresas em ON em.id = ev.empresa_id
            WHERE ev.id = ?
            LIMIT 1
        ", [$evaluacion_id]);

        if (!$evaluacion) {
            return false;
        }

        $estadisticas = Database::selectOne("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN ep.respuesta_estudiante = p.respuesta_correcta THEN 1 ELSE 0 END) AS correctas
            FROM evaluacion_preguntas ep
            JOIN preguntas p ON p.id = ep.pregunta_id
            WHERE ep.evaluacion_id = ?
        ", [$evaluacion_id]);

        $evaluacion['total'] = (int) ($estadisticas['total'] ?? 0);
        $evaluacion['correctas'] = (int) ($estadisticas['correctas'] ?? 0);

        return $evaluacion;
    }

    public static function getHistorialPorEstudiante($estudiante_id) {
        self::ensureWorkflowSchema();

        return Database::select("
            SELECT
                ev.id,
                ev.empresa_id,
                ev.estado,
                ev.seguimiento_estado,
                ev.seguimiento_comentario,
                ev.seguimiento_fecha,
                ev.nota,
                ev.tiempo_inicio,
                ev.tiempo_fin,
                ev.created_at,
                em.nombre AS empresa_nombre,
                em.area_tecnica,
                em.direccion,
                a.id AS asignacion_id,
                a.estado AS asignacion_estado,
                a.fecha_asignacion,
                COUNT(ep.id) AS total_preguntas,
                SUM(CASE WHEN ep.respuesta_estudiante IS NOT NULL THEN 1 ELSE 0 END) AS respondidas,
                SUM(CASE WHEN ep.respuesta_estudiante = p.respuesta_correcta THEN 1 ELSE 0 END) AS correctas,
                SUM(
                    CASE
                        WHEN ep.respuesta_estudiante IS NOT NULL
                         AND ep.respuesta_estudiante <> p.respuesta_correcta
                        THEN 1
                        ELSE 0
                    END
                ) AS incorrectas
            FROM evaluaciones ev
            JOIN empresas em ON em.id = ev.empresa_id
            LEFT JOIN evaluacion_preguntas ep ON ep.evaluacion_id = ev.id
            LEFT JOIN preguntas p ON p.id = ep.pregunta_id
            LEFT JOIN asignaciones a
                ON a.estudiante_id = ev.estudiante_id
               AND a.empresa_id = ev.empresa_id
            WHERE ev.estudiante_id = ?
            GROUP BY
                ev.id,
                ev.empresa_id,
                ev.estado,
                ev.seguimiento_estado,
                ev.seguimiento_comentario,
                ev.seguimiento_fecha,
                ev.nota,
                ev.tiempo_inicio,
                ev.tiempo_fin,
                ev.created_at,
                em.nombre,
                em.area_tecnica,
                em.direccion,
                a.id,
                a.estado,
                a.fecha_asignacion
            ORDER BY
                COALESCE(ev.tiempo_fin, ev.tiempo_inicio, ev.created_at) DESC,
                ev.id DESC
        ", [$estudiante_id]);
    }

    public static function getDetalleRevisionParaEstudiante($evaluacion_id, $estudiante_id) {
        self::ensureWorkflowSchema();

        $resumen = Database::selectOne("
            SELECT
                ev.id,
                ev.empresa_id,
                ev.estado,
                ev.seguimiento_estado,
                ev.seguimiento_comentario,
                ev.seguimiento_fecha,
                ev.nota,
                ev.tiempo_inicio,
                ev.tiempo_fin,
                ev.created_at,
                em.nombre AS empresa_nombre,
                em.area_tecnica,
                em.descripcion AS empresa_descripcion,
                em.direccion,
                a.id AS asignacion_id,
                a.estado AS asignacion_estado,
                a.fecha_asignacion,
                COUNT(ep.id) AS total_preguntas,
                SUM(CASE WHEN ep.respuesta_estudiante IS NOT NULL THEN 1 ELSE 0 END) AS respondidas,
                SUM(CASE WHEN ep.respuesta_estudiante = p.respuesta_correcta THEN 1 ELSE 0 END) AS correctas,
                SUM(
                    CASE
                        WHEN ep.respuesta_estudiante IS NOT NULL
                         AND ep.respuesta_estudiante <> p.respuesta_correcta
                        THEN 1
                        ELSE 0
                    END
                ) AS incorrectas
            FROM evaluaciones ev
            JOIN empresas em ON em.id = ev.empresa_id
            LEFT JOIN evaluacion_preguntas ep ON ep.evaluacion_id = ev.id
            LEFT JOIN preguntas p ON p.id = ep.pregunta_id
            LEFT JOIN asignaciones a
                ON a.estudiante_id = ev.estudiante_id
               AND a.empresa_id = ev.empresa_id
            WHERE ev.id = ? AND ev.estudiante_id = ?
            GROUP BY
                ev.id,
                ev.empresa_id,
                ev.estado,
                ev.seguimiento_estado,
                ev.seguimiento_comentario,
                ev.seguimiento_fecha,
                ev.nota,
                ev.tiempo_inicio,
                ev.tiempo_fin,
                ev.created_at,
                em.nombre,
                em.area_tecnica,
                em.descripcion,
                em.direccion,
                a.id,
                a.estado,
                a.fecha_asignacion
            LIMIT 1
        ", [$evaluacion_id, $estudiante_id]);

        if (!$resumen) {
            return false;
        }

        $resumen['preguntas'] = Database::select("
            SELECT
                ep.id AS evaluacion_pregunta_id,
                ep.orden,
                ep.respuesta_estudiante,
                p.pregunta,
                p.opcion_a,
                p.opcion_b,
                p.opcion_c,
                p.opcion_d,
                p.respuesta_correcta,
                CASE
                    WHEN ep.respuesta_estudiante IS NULL THEN 'sin_respuesta'
                    WHEN ep.respuesta_estudiante = p.respuesta_correcta THEN 'correcta'
                    ELSE 'incorrecta'
                END AS resultado
            FROM evaluacion_preguntas ep
            JOIN preguntas p ON p.id = ep.pregunta_id
            JOIN evaluaciones ev ON ev.id = ep.evaluacion_id
            WHERE ep.evaluacion_id = ? AND ev.estudiante_id = ?
            ORDER BY ep.orden ASC, ep.id ASC
        ", [$evaluacion_id, $estudiante_id]);

        return $resumen;
    }

    public static function saveSeguimiento($evaluacion_id, $centerId, $seguimientoEstado, $comentario = '') {
        self::ensureWorkflowSchema();

        $evaluacion_id = (int) $evaluacion_id;
        $centerId = (int) $centerId;
        $seguimientoEstado = trim((string) $seguimientoEstado);
        $comentario = trim((string) $comentario);

        if ($evaluacion_id <= 0 || $centerId <= 0) {
            throw new InvalidArgumentException('Datos invalidos para revisar la evaluacion');
        }

        if (!in_array($seguimientoEstado, ['sin_revisar', 'en_revision', 'preseleccionado', 'descartado'], true)) {
            throw new InvalidArgumentException('Estado de seguimiento no valido');
        }

        $commentLength = function_exists('mb_strlen')
            ? mb_strlen($comentario, 'UTF-8')
            : strlen($comentario);

        if ($commentLength > 2000) {
            throw new InvalidArgumentException('El comentario de seguimiento no puede superar 2000 caracteres');
        }

        $evaluation = Database::selectOne("
            SELECT ev.id, ev.estado, est.centro_id
            FROM evaluaciones ev
            JOIN estudiantes est ON est.id = ev.estudiante_id
            WHERE ev.id = ?
            LIMIT 1
        ", [$evaluacion_id]);

        if (!$evaluation || (int) ($evaluation['centro_id'] ?? 0) !== $centerId) {
            throw new RuntimeException('No se encontro la evaluacion seleccionada');
        }

        if (($evaluation['estado'] ?? '') !== 'aprobado') {
            throw new RuntimeException('Solo puedes revisar evaluaciones aprobadas');
        }

        $commentValue = $comentario !== '' ? $comentario : null;
        $reviewDate = ($seguimientoEstado === 'sin_revisar' && $commentValue === null)
            ? null
            : date('Y-m-d H:i:s');

        $updated = Database::execute("
            UPDATE evaluaciones
            SET seguimiento_estado = ?,
                seguimiento_comentario = ?,
                seguimiento_fecha = ?
            WHERE id = ?
        ", [
            $seguimientoEstado,
            $commentValue,
            $reviewDate,
            $evaluacion_id,
        ]);

        if (!$updated) {
            throw new RuntimeException('No se pudo guardar el seguimiento de la evaluacion');
        }
    }

    private static function getPreguntasParaProcesar(PDO $pdo, $evaluacion_id) {
        $stmt = $pdo->prepare("
            SELECT ep.id, p.respuesta_correcta
            FROM evaluacion_preguntas ep
            JOIN preguntas p ON p.id = ep.pregunta_id
            WHERE ep.evaluacion_id = ?
            ORDER BY ep.orden ASC, ep.id ASC
        ");
        $stmt->execute([$evaluacion_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function normalizarRespuesta($respuesta) {
        if (!is_string($respuesta)) {
            return null;
        }

        $respuesta = strtolower(trim($respuesta));
        return in_array($respuesta, ['a', 'b', 'c', 'd'], true) ? $respuesta : null;
    }
}
