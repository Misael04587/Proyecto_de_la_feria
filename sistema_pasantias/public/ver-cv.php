<?php
// public/ver-cv.php
require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/Database.php';

session_name('SISTEMA_PASANTIAS');
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!isset($_SESSION['user_id'], $_SESSION['rol'])) {
    header('Location: index.php?page=login');
    exit;
}

$role = (string) ($_SESSION['rol'] ?? '');
$cv = false;
$downloadName = 'curriculum.pdf';

if ($role === 'estudiante') {
    $cv = Database::selectOne("
        SELECT cv_path
        FROM estudiantes
        WHERE usuario_id = ?
        LIMIT 1
    ", [(int) $_SESSION['user_id']]);

    if (empty($cv['cv_path'])) {
        die('No has subido ningun CV.');
    }

    $downloadName = basename((string) $cv['cv_path']);
} elseif (in_array($role, ['admin_centro', 'coordinador'], true)) {
    $studentId = (int) ($_GET['student_id'] ?? 0);
    $centerId = (int) ($_SESSION['centro_id'] ?? 0);

    if ($studentId <= 0 || $centerId <= 0) {
        die('Solicitud invalida.');
    }

    $cv = Database::selectOne("
        SELECT e.cv_path, e.matricula
        FROM estudiantes e
        WHERE e.id = ? AND e.centro_id = ?
        LIMIT 1
    ", [$studentId, $centerId]);

    if (empty($cv['cv_path'])) {
        die('No se encontro CV para este estudiante.');
    }

    $matricula = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($cv['matricula'] ?? 'estudiante'));
    $downloadName = 'CV_' . $matricula . '.pdf';
} else {
    header('Location: index.php?page=access-denied');
    exit;
}

$storedPath = ltrim(str_replace('\\', '/', (string) ($cv['cv_path'] ?? '')), '/');
if ($storedPath === '' || strpos($storedPath, 'uploads/cvs/') !== 0) {
    die('Ruta de CV invalida.');
}

$fullPath = PUBLIC_PATH . $storedPath;
if (!file_exists($fullPath)) {
    die('El archivo no existe en el servidor.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($downloadName) . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
