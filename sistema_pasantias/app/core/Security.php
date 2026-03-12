<?php
// app/core/Security.php

class Security {
    
    /**
     * Genera hash de contraseña
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    /**
     * Verifica contraseña
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Genera token CSRF
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida token CSRF
     */
    public static function validateCSRFToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitiza entrada de usuario
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Valida email
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Valida matrícula (ejemplo: 2023-001)
     */
    public static function isValidMatricula($matricula) {
        return preg_match('/^\d{4}-\d{3}$/', $matricula);
    }
    
    /**
     * Valida código de centro (ejemplo: JP2-6TO-2026)
     */
    public static function isValidCenterCode($code) {
        return preg_match('/^[A-Z0-9]{3,10}-[A-Z0-9]{3,10}-\d{4}$/', $code);
    }
    
    /**
     * Registra intento de login fallido
     */
    public static function logFailedLogin($email, $ip) {
        $query = "INSERT INTO logs_seguridad (evento, detalles, ip_address) 
                  VALUES ('login_failed', ?, ?)";
        Database::execute($query, [
            "Intento fallido para: $email",
            $ip
        ]);
    }
    
    /**
     * Obtiene IP del cliente
     */
    public static function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        return $ip;
    }
}