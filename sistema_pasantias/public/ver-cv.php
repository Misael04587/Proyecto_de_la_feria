<?php
// public/ver-cv.php
require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/Database.php';

// ✅ MISMO NOMBRE DE SESIÓN QUE EN EL INDEX
session_name('SISTEMA_PASANTIAS');
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SESSION['rol'] !== 'estudiante') {
    header('Location: ../index.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];

// Obtener ruta del CV
$cv = Database::selectOne("
    SELECT cv_path FROM estudiantes WHERE usuario_id = ?
", [$usuario_id]);

if (empty($cv['cv_path'])) {
    die('No has subido ningún CV');
}

$ruta_completa = __DIR__ . '/../public/' . $cv['cv_path'];

if (!file_exists($ruta_completa)) {
    die('El archivo no existe');
}

// Forzar visualización
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($cv['cv_path']) . '"');
header('Content-Length: ' . filesize($ruta_completa));
readfile($ruta_completa);
exit;
