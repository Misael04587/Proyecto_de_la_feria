<?php
// app/controllers/ErrorController.php

class ErrorController {
    
    public function notFound() {
        http_response_code(404);
        echo "<h1>404 - Página no encontrada</h1>";
        echo "<p>La página que buscas no existe.</p>";
        echo "<a href='index.php?page=login'>Volver al inicio</a>";
        exit;
    }
    
    public function accessDenied() {
        http_response_code(403);
        echo "<h1>403 - Acceso denegado</h1>";
        echo "<p>No tienes permisos para acceder a esta página.</p>";
        echo "<a href='index.php?page=login'>Volver al inicio</a>";
        exit;
    }
    
    public function serverError($message = '') {
        http_response_code(500);
        echo "<h1>500 - Error del servidor</h1>";
        echo "<p>Ocurrió un error en el sistema.</p>";
        if ($message && DEBUG_MODE) {
            echo "<p><strong>Detalles:</strong> " . htmlspecialchars($message) . "</p>";
        }
        echo "<a href='index.php?page=login'>Volver al inicio</a>";
        exit;
    }
}