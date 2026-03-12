<?php
// app/models/Empresa.php

class Empresa {
    
    /**
     * Obtener empresas disponibles por centro y área técnica
     */
    public static function getDisponibles($centro_id, $area_tecnica) {
        $query = "
            SELECT 
                e.*,
                (SELECT COUNT(*) FROM asignaciones a 
                 WHERE a.empresa_id = e.id AND a.estado = 'activa') as asignados_actuales,
                (e.cupos - (SELECT COUNT(*) FROM asignaciones a 
                 WHERE a.empresa_id = e.id AND a.estado = 'activa')) as cupos_disponibles
            FROM empresas e
            WHERE e.centro_id = ? 
                AND e.area_tecnica = ?
                AND e.estado = 'disponible'
                AND e.cupos > (
                    SELECT COUNT(*) FROM asignaciones a 
                    WHERE a.empresa_id = e.id AND a.estado = 'activa'
                )
            ORDER BY e.nombre ASC
        ";
        
        return Database::select($query, [$centro_id, $area_tecnica]);
    }
    
    /**
     * Obtener una empresa por ID
     */
    public static function getById($id) {
        return Database::selectOne("
            SELECT * FROM empresas WHERE id = ?
        ", [$id]);
    }
    
    /**
     * Verificar si estudiante ya se postuló a esta empresa
     */
    public static function yaPostulado($estudiante_id, $empresa_id) {
        $result = Database::selectOne("
            SELECT id FROM evaluaciones 
            WHERE estudiante_id = ? AND empresa_id = ?
        ", [$estudiante_id, $empresa_id]);
        
        return !empty($result);
    }
}