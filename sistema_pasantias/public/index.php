<?php
// public/index.php

// 1. Configuración de manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Iniciar sesión
session_name('SISTEMA_PASANTIAS');
session_start();



// 3. Incluir configuración
require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/config/database.php';



// 4. Autoload básico para clases
spl_autoload_register(function ($class_name) {
    $paths = [
        APP_PATH . 'core/',
        APP_PATH . 'models/',
        APP_PATH . 'controllers/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});



// ============================================
// FUNCIONES AUXILIARES
// ============================================

/**
 * Verifica si el usuario está autenticado
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['rol']);
}

/**
 * Verifica si el usuario tiene un rol específico
 */
function hasRole($requiredRole) {
    return isAuthenticated() && $_SESSION['rol'] === $requiredRole;
}

/**
 * Redirige a una página con mensaje opcional
 */
function redirect($page, $message = '', $type = 'success') {
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: index.php?page=" . urlencode($page));
    exit;
}

/**
 * Evita que el navegador reutilice paginas desde cache al usar atras/adelante.
 */
function sendNoCacheHeaders() {
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

/**
 * Obtiene el centro_id del usuario actual
 */
function getUserCenterId() {
    return $_SESSION['centro_id'] ?? null;
}

/**
 * Sanitiza entrada de usuario
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Muestra mensajes flash
 */
/**
 * Muestra mensajes flash con animaciones
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        $icon = '';
        
    
        echo '<div class="flash-message ' . htmlspecialchars($type) . '">';
        echo '<div class="flash-content">';
        echo '<i class="' . $icon . '"></i>';
        echo '<span>' . htmlspecialchars($message) . '</span>';
        echo '</div>';
        echo '<button class="flash-close">&times;</button>';
        echo '</div>';
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}
// ============================================
// MANEJO DE RUTAS (SIN .htaccess)
// ============================================

// Página por defecto
$page = $_GET['page'] ?? 'login';

// Manejar acciones (login?action=process, register?action=process)
$action = $_GET['action'] ?? '';

if ($action === 'process') {
    switch ($page) {
        case 'login':
            $controller = new AuthController();
            $controller->processLogin();
            break;
        case 'register':
            $controller = new AuthController();
            $controller->processRegister();
            break;
        case 'center-register':
            $controller = new CenterController();
            $controller->processRegister();
            break;
    }
}



// Lista de páginas públicas (no requieren login)
$publicPages = ['login', 'register', 'center-register', 'center-areas', 'forgot-password'];

sendNoCacheHeaders();

// Verificar autenticación
if (!in_array($page, $publicPages) && !isAuthenticated()) {
    redirect('login', MSG_LOGIN_REQUIRED, 'error');
}

// Verificar inactividad de sesión


// Actualizar tiempo de actividad
if (isAuthenticated()) {
    $_SESSION['last_activity'] = time();
}

// ============================================
// MANEJO DIRECTO DE ACCIONES DE CV
// ============================================
if (isset($_GET['action']) && in_array($_GET['action'], ['upload_cv', 'delete_cv'], true)) {
    require_once APP_PATH . 'controllers/StudentController.php';
    $controller = new StudentController();

    if ($_GET['action'] === 'upload_cv') {
        $controller->uploadCV();
    }

    if ($_GET['action'] === 'delete_cv') {
        $controller->deleteCV();
    }

    exit;
}

// ============================================
// MAPEO DE RUTAS A CONTROLADORES
// ============================================

$controllerMap = [
    // Autenticación
    'login' => ['AuthController', 'login'],
    'logout' => ['AuthController', 'logout'],
    'register' => ['AuthController', 'register'],
    'center-register' => ['CenterController', 'register'],
    'center-areas' => ['CenterController', 'areas'],
    
    // Estudiantes
    'student-dashboard' => ['StudentController', 'dashboard'],
    'student-profile' => ['StudentController', 'profile'],
    'student-companies' => ['StudentController', 'companies'],
    'student-apply' => ['StudentController', 'apply'],
    'student-exam' => ['StudentController', 'takeExam'],
    'student-results' => ['StudentController', 'results'],
    
    // Administradores de centro
    'admin-dashboard' => ['AdminController', 'dashboard'],
    'admin-companies' => ['AdminController', 'manageCompanies'],
    'admin-students' => ['AdminController', 'manageStudents'],
    'admin-evaluations' => ['AdminController', 'viewEvaluations'],
    'admin-reports' => ['AdminController', 'reports'],
    
    // Super Admin
    'superadmin-dashboard' => ['SuperAdminController', 'dashboard'],
    'superadmin-centers' => ['SuperAdminController', 'manageCenters'],
    'superadmin-users' => ['SuperAdminController', 'manageUsers'],
    
    // Páginas comunes
    'not-found' => ['ErrorController', 'notFound'],
    'access-denied' => ['ErrorController', 'accessDenied'],
];

// ============================================
// EJECUCIÓN DEL CONTROLADOR
// ============================================

try {
    if (isset($controllerMap[$page])) {
        list($controllerName, $method) = $controllerMap[$page];
        
        // Verificar permisos de ruta
        if (!checkRoutePermissions($page, $_SESSION['rol'] ?? 'guest')) {
            redirect('access-denied');
        }
        
        // Instanciar y ejecutar
        $controller = new $controllerName();
        $controller->$method();
    } else {
        // Página no encontrada
        if (class_exists('ErrorController')) {
            $controller = new ErrorController();
            $controller->notFound();
        } else {
            die("Página no encontrada: $page");
        }
    }
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en la aplicación: " . $e->getMessage());
    
    if (class_exists('ErrorController')) {
        $controller = new ErrorController();
        $controller->serverError($e->getMessage());
    } else {
        die("Error del sistema: " . $e->getMessage());
    }
}

// ============================================
// FUNCIÓN DE VERIFICACIÓN DE PERMISOS
// ============================================

function checkRoutePermissions($page, $userRole) {
    $permissions = [
        // Rutas públicas
        'login' => ['guest', 'estudiante', 'coordinador', 'admin_centro', 'super_admin'],
        'register' => ['guest'],
        'center-register' => ['guest'],
        'center-areas' => ['guest', 'estudiante', 'coordinador', 'admin_centro', 'super_admin'],
        'logout' => ['estudiante', 'coordinador', 'admin_centro', 'super_admin'],
        
        // Rutas de estudiantes
        'student-dashboard' => ['estudiante'],
        'student-profile' => ['estudiante'],
        'student-companies' => ['estudiante'],
        'student-apply' => ['estudiante'],
        'student-exam' => ['estudiante'],
        'student-results' => ['estudiante'],
        
        // Rutas de administradores
        'admin-dashboard' => ['coordinador', 'admin_centro'],
        'admin-companies' => ['coordinador', 'admin_centro'],
        'admin-students' => ['coordinador', 'admin_centro'],
        'admin-evaluations' => ['coordinador', 'admin_centro'],
        'admin-reports' => ['coordinador', 'admin_centro'],
        
        // Rutas de super admin
        'superadmin-dashboard' => ['super_admin'],
        'superadmin-centers' => ['super_admin'],
        'superadmin-users' => ['super_admin'],
    ];
    
    // Si la página no está en la lista, permitir acceso (o no, según tu política)
    if (!isset($permissions[$page])) {
        return true; // o false dependiendo de tu política
    }

    
    
    return in_array($userRole, $permissions[$page]);
}
