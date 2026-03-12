<?php
// app/core/Database.php

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                DB_OPTIONS
            );
        } catch(PDOException $e) {
            error_log("Error de conexión a BD: " . $e->getMessage());
            die("Error del sistema. Por favor, intente más tarde.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
    
    /**
     * Ejecuta una consulta SELECT
     */
    public static function select($query, $params = []) {
        try {
            $stmt = self::getInstance()->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en SELECT: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ejecuta una consulta y retorna una sola fila
     */
    public static function selectOne($query, $params = []) {
        try {
            $stmt = self::getInstance()->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en SELECT ONE: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ejecuta INSERT, UPDATE, DELETE
     */
    public static function execute($query, $params = []) {
        try {
            $stmt = self::getInstance()->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error en EXECUTE: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ejecuta INSERT y retorna el ID insertado
     */
    public static function insert($query, $params = []) {
        try {
            $pdo = self::getInstance();
            $stmt = $pdo->prepare($query);
            if ($stmt->execute($params)) {
                return $pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error en INSERT: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Inicia una transacción
     */
    public static function beginTransaction() {
        return self::getInstance()->beginTransaction();
    }
    
    /**
     * Confirma una transacción
     */
    public static function commit() {
        return self::getInstance()->commit();
    }
    
    /**
     * Revierte una transacción
     */
    public static function rollback() {
        return self::getInstance()->rollback();
    }
}