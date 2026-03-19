<?php

class Pregunta {
    private static $booted = false;

    public static function ensureSchema() {
        if (self::$booted) {
            return;
        }

        $stateColumn = Database::selectOne("SHOW COLUMNS FROM preguntas LIKE 'estado'");
        if (!$stateColumn) {
            Database::execute("
                ALTER TABLE preguntas
                ADD COLUMN estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo' AFTER respuesta_correcta
            ");
        }

        self::$booted = true;
    }
}
