<?php
// app/config/database.php

// ============================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_pasantias');
define('DB_USER', 'root');
define('DB_PASS', ''); // Vacío por defecto en XAMPP
define('DB_CHARSET', 'utf8mb4');

// Opciones de PDO
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

// ============================================
// FUNCIÓN DE CONEXIÓN (si la necesitas directamente)
// ============================================

function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        } catch (PDOException $e) {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    return $pdo;
}