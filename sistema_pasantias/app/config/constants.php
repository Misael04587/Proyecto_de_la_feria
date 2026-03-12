<?php
define('APP_TIMEZONE', 'America/Santo_Domingo');

date_default_timezone_set(APP_TIMEZONE);
// app/config/constants.php

// ============================================
// CONFIGURACIÓN DEL SISTEMA
// ============================================

// Base URL (IMPORTANTE: Sin .htaccess, usaremos index.php?page=...)
define('BASE_URL', 'http://localhost/sistema_pasantias/public/');

// Rutas físicas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__)))); // C:\mi_xampp\htdocs\sistema_pasantias
define('APP_PATH', ROOT_PATH . '/app/');
define('PUBLIC_PATH', ROOT_PATH . '/public/');
define('UPLOAD_PATH', PUBLIC_PATH . 'uploads/');
define('DEBUG_MODE', true); // Cambiar a false en producción

// Configuración de sesión
define('SESSION_NAME', 'SISTEMA_PASANTIAS');
define('SESSION_LIFETIME', 3600); // 1 hora en segundos
define('SESSION_INACTIVITY', 1800); // 30 minutos de inactividad

// Configuración de archivos
define('MAX_UPLOAD_SIZE', 0); // Sin limite a nivel de aplicacion; depende de php.ini
define('ALLOWED_FILE_TYPES', ['application/pdf']);
define('MAX_FILE_NAME_LENGTH', 100);

// Configuración de evaluaciones
define('EXAM_TIME_LIMIT', 1800); // 30 minutos en segundos
define('MIN_PASSING_SCORE', 70); // 70% para aprobar
define('QUESTIONS_PER_EXAM', 10);

// Estados del sistema
define('STATUS_ACTIVE', 'activo');
define('STATUS_INACTIVE', 'inactivo');
define('STATUS_BLOCKED', 'bloqueado');

// Roles del sistema
define('ROLE_STUDENT', 'estudiante');
define('ROLE_COORDINATOR', 'coordinador');
define('ROLE_CENTER_ADMIN', 'admin_centro');
define('ROLE_SUPER_ADMIN', 'super_admin');

// ============================================
// MENSAJES DEL SISTEMA
// ============================================

define('MSG_LOGIN_REQUIRED', 'Debes iniciar sesión para acceder a esta página');
define('MSG_UNAUTHORIZED', 'No tienes permisos para acceder a esta sección');
define('MSG_CENTER_CODE_INVALID', 'Código de centro inválido o centro inactivo');
define('MSG_CREDENTIALS_INVALID', 'contraseña incorrecta');
define('MSG_SESSION_EXPIRED', 'Tu sesión ha expirado, por favor inicia sesión nuevamente');
