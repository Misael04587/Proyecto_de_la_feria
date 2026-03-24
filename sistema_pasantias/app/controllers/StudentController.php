<?php
// app/controllers/StudentController.php

class StudentController {
    public function __construct() {
        Asignacion::ensureSchema();
    }

    // ============================================
    // MÃ‰TODO PARA SUBIR CV
    // ============================================
    public function uploadCV() {
        header('Content-Type: application/json; charset=UTF-8');

        // Verificar que es estudiante
        if ($_SESSION['rol'] !== 'estudiante') {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        // Verificar que se enviÃ³ un archivo
        if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
            exit;
        }
        
        $archivo = $_FILES['cv'];
        
        // Validar tipo PDF
        if ($archivo['type'] !== 'application/pdf') {
            echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF']);
            exit;
        }
        
        
        // Obtener ID del estudiante
        $estudiante = Database::selectOne("
            SELECT id, cv_path FROM estudiantes WHERE usuario_id = ?
        ", [$_SESSION['user_id']]);
        
        if (!$estudiante) {
            echo json_encode(['success' => false, 'message' => 'Estudiante no encontrado']);
            exit;
        }
        
        // Crear carpeta si no existe
        $carpeta = PUBLIC_PATH . 'uploads/cvs/';
        if (!file_exists($carpeta)) {
            mkdir($carpeta, 0777, true);
        }
        
        // Generar nombre Ãºnico
        $nombre_unico = 'CV_' . $_SESSION['user_id'] . '_' . time() . '.pdf';
        $ruta_completa = $carpeta . $nombre_unico;
        $ruta_bd = 'uploads/cvs/' . $nombre_unico;
        
        // Mover archivo
        if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
            // Actualizar BD
            Database::execute("
                UPDATE estudiantes 
                SET cv_path = ?,
                    comentario_cv_admin = NULL,
                    fecha_revision_cv = NULL
                WHERE usuario_id = ?
            ", [$ruta_bd, $_SESSION['user_id']]);

            if (!empty($estudiante['cv_path']) && $estudiante['cv_path'] !== $ruta_bd) {
                $cv_anterior = PUBLIC_PATH . ltrim(str_replace('\\', '/', $estudiante['cv_path']), '/');
                if (file_exists($cv_anterior)) {
                    unlink($cv_anterior);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'CV subido exitosamente'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar el archivo'
            ]);
        }
        exit;
    }

    /**
     * Elimina el CV del estudiante autenticado.
     */
    public function deleteCV() {
        header('Content-Type: application/json; charset=UTF-8');

        if (($_SESSION['rol'] ?? '') !== 'estudiante') {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
            exit;
        }

        $estudiante = Database::selectOne("
            SELECT cv_path
            FROM estudiantes
            WHERE usuario_id = ?
        ", [$_SESSION['user_id']]);

        if (empty($estudiante['cv_path'])) {
            echo json_encode(['success' => false, 'message' => 'No tienes un CV para eliminar']);
            exit;
        }

        $ruta_archivo = PUBLIC_PATH . ltrim(str_replace('\\', '/', $estudiante['cv_path']), '/');

        Database::execute("
            UPDATE estudiantes
            SET cv_path = NULL,
                comentario_cv_admin = NULL,
                fecha_revision_cv = NULL
            WHERE usuario_id = ?
        ", [$_SESSION['user_id']]);

        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
        }

        echo json_encode([
            'success' => true,
            'message' => 'CV eliminado correctamente'
        ]);
        exit;
    }
    
    // ============================================
    // DASHBOARD
    // ============================================
    public function dashboard() {
        // Verificar que es estudiante
        if ($_SESSION['rol'] !== 'estudiante') {
            redirect('access-denied');
        }

        // Obtener datos del estudiante
        $estudiante = Database::selectOne("
            SELECT e.*, u.nombre, u.correo
            FROM estudiantes e
            JOIN usuarios u ON e.usuario_id = u.id
            WHERE u.id = ?
        ", [$_SESSION['user_id']]);

        // Obtener conteo de empresas del Ã¡rea del estudiante
        $empresas_count = Database::selectOne("
            SELECT COUNT(*) as total
            FROM empresas 
            WHERE centro_id = ? 
            AND area_tecnica = ? 
            AND estado = 'disponible'
        ", [$_SESSION['centro_id'], $estudiante['area_tecnica']]);

        // Obtener estadÃ­sticas de evaluaciones
        $evaluaciones_stats = Database::selectOne("
            SELECT 
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado = 'reprobado' THEN 1 ELSE 0 END) as reprobadas
            FROM evaluaciones e
            JOIN estudiantes est ON e.estudiante_id = est.id
            WHERE est.usuario_id = ?
        ", [$_SESSION['user_id']]);
        
        // Ver si el estudiante ya subio su CV
        $cv_info = Database::selectOne("
            SELECT cv_path FROM estudiantes WHERE usuario_id = ?
        ", [$_SESSION['user_id']]);
        $tiene_cv = !empty($cv_info['cv_path']);

        // Si existe la vista, incluirla
        $viewPath = APP_PATH . 'views/student/dashboard.php';
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            $this->showBasicDashboard();
        }
    }
    
    // ============================================
    // PERFIL
    // ============================================
    public function profile() {
        if ($_SESSION['rol'] !== 'estudiante') {
            redirect('access-denied');
        }

        Security::generateCSRFToken();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->redirectToProfile(
                    $this->resolveProfileTab($_POST['tab'] ?? $_GET['tab'] ?? 'overview'),
                    'Token de seguridad invalido',
                    'error'
                );
            }

            $action = (string) ($_GET['action'] ?? '');

            switch ($action) {
                case 'update_profile':
                    $result = $this->handleProfileUpdate();
                    $this->redirectToProfile('profile', $result['message'], $result['type']);
                    break;
                case 'change_password':
                    $result = $this->handlePasswordChange();
                    $this->redirectToProfile('settings', $result['message'], $result['type']);
                    break;
                case 'upload_photo':
                    $result = $this->handleProfilePhotoUpload();
                    $this->redirectToProfile('photo', $result['message'], $result['type']);
                    break;
                case 'delete_photo':
                    $result = $this->handleProfilePhotoDelete();
                    $this->redirectToProfile('photo', $result['message'], $result['type']);
                    break;
                default:
                    $this->redirectToProfile('overview', 'Accion no valida', 'error');
            }
        }

        $profile_tab = $this->resolveProfileTab($_GET['tab'] ?? 'overview');
        $estudiante = $this->getProfileStudentContext();
        $foto_perfil = $estudiante['foto_perfil'] ?? '';
        $tiene_cv = !empty($estudiante['cv_path']);

        $empresas_count = Database::selectOne("
            SELECT COUNT(*) as total
            FROM empresas
            WHERE centro_id = ?
            AND area_tecnica = ?
            AND estado = 'disponible'
        ", [$_SESSION['centro_id'], $estudiante['area_tecnica']]);

        $evaluaciones_stats = Database::selectOne("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado = 'reprobado' THEN 1 ELSE 0 END) as reprobadas,
                SUM(CASE WHEN estado = 'anulado' THEN 1 ELSE 0 END) as anuladas
            FROM evaluaciones
            WHERE estudiante_id = ?
        ", [(int) $estudiante['estudiante_id']]);

        $asignacion_activa = Database::selectOne("
            SELECT a.id, a.estado, em.nombre as empresa_nombre
            FROM asignaciones a
            JOIN empresas em ON em.id = a.empresa_id
            WHERE a.estudiante_id = ?
            AND a.estado = 'activa'
            ORDER BY a.id DESC
            LIMIT 1
        ", [(int) $estudiante['estudiante_id']]);

        $viewPath = APP_PATH . 'views/student/profile.php';
        if (file_exists($viewPath)) {
            require_once $viewPath;
            return;
        }

        $this->showBasicDashboard();
    }

    private function handleProfileUpdate() {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));

        if ($nombre === '') {
            return ['message' => 'El nombre es obligatorio', 'type' => 'error'];
        }

        if (mb_strlen($nombre, 'UTF-8') < 3) {
            return ['message' => 'El nombre debe tener al menos 3 caracteres', 'type' => 'error'];
        }

        if ($correo === '') {
            return ['message' => 'El correo es obligatorio', 'type' => 'error'];
        }

        if (!Security::isValidEmail($correo)) {
            return ['message' => 'El correo electronico no es valido', 'type' => 'error'];
        }

        $emailInUse = Database::selectOne("
            SELECT id
            FROM usuarios
            WHERE correo = ?
            AND id <> ?
            LIMIT 1
        ", [$correo, $_SESSION['user_id']]);

        if ($emailInUse) {
            return ['message' => 'Ese correo ya esta siendo usado por otra cuenta', 'type' => 'error'];
        }

        $updated = Usuario::update((int) $_SESSION['user_id'], [
            'nombre' => $nombre,
            'correo' => $correo,
        ]);

        if (!$updated) {
            return ['message' => 'No se pudieron guardar los cambios del perfil', 'type' => 'error'];
        }

        $_SESSION['nombre'] = $nombre;

        return ['message' => 'Tus datos del perfil fueron actualizados', 'type' => 'success'];
    }

    private function handlePasswordChange() {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return ['message' => 'Completa todos los campos de seguridad', 'type' => 'error'];
        }

        if (strlen($newPassword) < 8) {
            return ['message' => 'La nueva contrasena debe tener al menos 8 caracteres', 'type' => 'error'];
        }

        if (!preg_match('/[A-Z]/', $newPassword)) {
            return ['message' => 'La nueva contrasena debe incluir al menos una mayuscula', 'type' => 'error'];
        }

        if (!preg_match('/[0-9]/', $newPassword)) {
            return ['message' => 'La nueva contrasena debe incluir al menos un numero', 'type' => 'error'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['message' => 'La confirmacion de la contrasena no coincide', 'type' => 'error'];
        }

        if ($currentPassword === $newPassword) {
            return ['message' => 'La nueva contrasena debe ser distinta a la actual', 'type' => 'error'];
        }

        $changed = Usuario::changePassword((int) $_SESSION['user_id'], $currentPassword, $newPassword);
        if (!$changed) {
            return ['message' => 'La contrasena actual no es correcta', 'type' => 'error'];
        }

        return ['message' => 'Tu contrasena fue actualizada correctamente', 'type' => 'success'];
    }

    private function handleProfilePhotoUpload() {
        if (!isset($_FILES['profile_photo'])) {
            return ['message' => 'Selecciona una imagen para tu perfil', 'type' => 'error'];
        }

        $archivo = $_FILES['profile_photo'];
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['message' => $this->getUploadErrorMessage((int) $archivo['error']), 'type' => 'error'];
        }

        if (($archivo['size'] ?? 0) <= 0) {
            return ['message' => 'La imagen seleccionada no es valida', 'type' => 'error'];
        }

        if (($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['message' => 'La foto de perfil no puede pesar mas de 5 MB', 'type' => 'error'];
        }

        $mime = $this->detectUploadedFileMime($archivo['tmp_name'] ?? '');
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimes[$mime])) {
            return ['message' => 'Solo se permiten imagenes JPG, PNG o WEBP', 'type' => 'error'];
        }

        if (!@getimagesize($archivo['tmp_name'])) {
            return ['message' => 'El archivo seleccionado no es una imagen valida', 'type' => 'error'];
        }

        $estudiante = Database::selectOne("
            SELECT foto_perfil
            FROM estudiantes
            WHERE usuario_id = ?
            LIMIT 1
        ", [$_SESSION['user_id']]);

        if (!$estudiante) {
            return ['message' => 'No se encontro la informacion del estudiante', 'type' => 'error'];
        }

        $directory = PUBLIC_PATH . 'uploads/profile/';
        if (!file_exists($directory) && !mkdir($directory, 0777, true) && !file_exists($directory)) {
            return ['message' => 'No se pudo preparar la carpeta de imagenes', 'type' => 'error'];
        }

        $extension = $allowedMimes[$mime];
        $fileName = 'perfil_' . (int) $_SESSION['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $relativePath = 'uploads/profile/' . $fileName;
        $fullPath = $directory . $fileName;

        if (!move_uploaded_file($archivo['tmp_name'], $fullPath)) {
            return ['message' => 'No se pudo guardar la imagen de perfil', 'type' => 'error'];
        }

        $updated = Database::execute("
            UPDATE estudiantes
            SET foto_perfil = ?
            WHERE usuario_id = ?
        ", [$relativePath, $_SESSION['user_id']]);

        if (!$updated) {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return ['message' => 'No se pudo actualizar la foto de perfil', 'type' => 'error'];
        }

        $previousPhoto = $this->buildPublicFilePath($estudiante['foto_perfil'] ?? '');
        if ($previousPhoto && file_exists($previousPhoto) && $previousPhoto !== $fullPath) {
            unlink($previousPhoto);
        }

        return ['message' => 'Tu foto de perfil fue actualizada', 'type' => 'success'];
    }

    private function handleProfilePhotoDelete() {
        $estudiante = Database::selectOne("
            SELECT foto_perfil
            FROM estudiantes
            WHERE usuario_id = ?
            LIMIT 1
        ", [$_SESSION['user_id']]);

        if (!$estudiante) {
            return ['message' => 'No se encontro la informacion del estudiante', 'type' => 'error'];
        }

        if (empty($estudiante['foto_perfil'])) {
            return ['message' => 'Todavia no tienes una foto de perfil para eliminar', 'type' => 'warning'];
        }

        $updated = Database::execute("
            UPDATE estudiantes
            SET foto_perfil = NULL
            WHERE usuario_id = ?
        ", [$_SESSION['user_id']]);

        if (!$updated) {
            return ['message' => 'No se pudo eliminar la foto de perfil', 'type' => 'error'];
        }

        $photoPath = $this->buildPublicFilePath($estudiante['foto_perfil']);
        if ($photoPath && file_exists($photoPath)) {
            unlink($photoPath);
        }

        return ['message' => 'La foto de perfil fue eliminada', 'type' => 'success'];
    }

    private function resolveProfileTab($tab) {
        $allowedTabs = ['overview', 'profile', 'photo', 'cv', 'settings'];
        $tab = strtolower(trim((string) $tab));

        if (!in_array($tab, $allowedTabs, true)) {
            return 'overview';
        }

        return $tab;
    }

    private function redirectToProfile($tab = 'overview', $message = '', $type = 'success') {
        if ($message !== '') {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }

        header('Location: index.php?page=student-profile&tab=' . urlencode($this->resolveProfileTab($tab)));
        exit;
    }

    private function getProfileStudentContext() {
        $estudiante = Database::selectOne("
            SELECT
                e.id AS estudiante_id,
                e.usuario_id,
                e.centro_id,
                e.matricula,
                e.area_tecnica,
                e.cv_path,
                e.comentario_cv_admin,
                e.fecha_revision_cv,
                e.foto_perfil,
                e.created_at AS estudiante_creado_en,
                u.nombre,
                u.correo,
                u.estado,
                u.created_at AS usuario_creado_en
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.usuario_id = ?
            LIMIT 1
        ", [$_SESSION['user_id']]);

        if (!$estudiante) {
            redirect('student-dashboard', 'No se encontro la informacion del estudiante', 'error');
        }

        return $estudiante;
    }

    private function buildPublicFilePath($storedPath) {
        $storedPath = ltrim(str_replace('\\', '/', (string) $storedPath), '/');
        if ($storedPath === '') {
            return '';
        }

        return PUBLIC_PATH . $storedPath;
    }

    private function detectUploadedFileMime($tmpPath) {
        if ($tmpPath === '' || !file_exists($tmpPath)) {
            return '';
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                return $mime;
            }
        }

        if (function_exists('mime_content_type')) {
            return (string) mime_content_type($tmpPath);
        }

        return '';
    }

    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo supera el tamano permitido por el servidor';
            case UPLOAD_ERR_PARTIAL:
                return 'La carga del archivo no se completo. Intenta nuevamente';
            case UPLOAD_ERR_NO_FILE:
                return 'Selecciona una imagen para tu perfil';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal del servidor';
            case UPLOAD_ERR_CANT_WRITE:
                return 'No se pudo escribir el archivo en el servidor';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extension del servidor bloqueo la carga del archivo';
            default:
                return 'Error al subir el archivo';
        }
    }
  // ============================================
// EMPRESAS DISPONIBLES - CORREGIDO
// ============================================
public function companies() {
    if ($_SESSION['rol'] !== 'estudiante') {
        redirect('access-denied');
    }

    Security::generateCSRFToken();
    $action = $_GET['action'] ?? '';
    $exam_id = (int) ($_GET['exam'] ?? $_POST['evaluacion_id'] ?? 0);

    $cv_data = Database::selectOne("
        SELECT cv_path FROM estudiantes WHERE usuario_id = ?
    ", [$_SESSION['user_id']]);
    $tiene_cv = !empty($cv_data['cv_path']);

    $estudiante = Database::selectOne("
        SELECT e.id as estudiante_id, e.area_tecnica, e.foto_perfil, u.nombre, u.correo
        FROM estudiantes e
        JOIN usuarios u ON e.usuario_id = u.id
        WHERE e.usuario_id = ?
    ", [$_SESSION['user_id']]);

    if (!$estudiante) {
        redirect('student-dashboard', 'No se encontro la informacion del estudiante', 'error');
    }

    $foto_perfil = $estudiante['foto_perfil'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['submit_exam', 'security_exam'], true)) {
        $evaluacion_actual = Evaluacion::getEvaluacionParaEstudiante($exam_id, (int) $estudiante['estudiante_id']);

        if (!$evaluacion_actual) {
            if ($action === 'security_exam') {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontro el examen solicitado'
                ]);
                exit;
            }

            $this->redirectToCompanies(null, 'No se encontro el examen solicitado', 'error');
        }

        if ($action === 'security_exam') {
            $this->handleExamSecurityEvent($evaluacion_actual);
        }

        if ($action === 'submit_exam') {
            $this->processExamSubmission($evaluacion_actual);
        }
    }

    $empresas = Empresa::getDisponibles(
        $_SESSION['centro_id'],
        $estudiante['area_tecnica']
    );

    $evaluacion_pendiente = Database::selectOne("
        SELECT id FROM evaluaciones
        WHERE estudiante_id = ? AND estado = 'pendiente'
    ", [$estudiante['estudiante_id']]);
    $tiene_evaluacion_pendiente = !empty($evaluacion_pendiente);

    $asignado = Database::selectOne("
        SELECT id FROM asignaciones
        WHERE estudiante_id = ? AND estado = 'activa'
    ", [$estudiante['estudiante_id']]);
    $tiene_asignacion = !empty($asignado);

    $evaluaciones_estudiante = Database::select("
        SELECT id, empresa_id, estado, nota
        FROM evaluaciones
        WHERE estudiante_id = ?
    ", [$estudiante['estudiante_id']]);

    $evaluaciones_por_empresa = [];
    foreach ($evaluaciones_estudiante as $evaluacion_item) {
        $evaluaciones_por_empresa[(int) $evaluacion_item['empresa_id']] = $evaluacion_item;
    }

    $exam_modal = null;
    if ($exam_id > 0) {
        $exam_modal = $this->buildExamModalState($estudiante, $exam_id);
        if ($exam_modal === null) {
            $this->redirectToCompanies(null, 'No se encontro el examen solicitado', 'error');
        }
    }

    $viewPath = APP_PATH . 'views/student/companies.php';
    if (file_exists($viewPath)) {
        require_once $viewPath;
    } else {
        echo "Vista de empresas no encontrada";
    }
}
    /**
     * Procesa la postulaciÃ³n del estudiante.
     */
    public function apply() {
        if (($_SESSION['rol'] ?? '') !== 'estudiante') {
            redirect('access-denied');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('student-companies', 'Metodo no permitido', 'error');
        }

        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('student-companies', 'Token de seguridad invalido', 'error');
        }

        $empresa_id = (int) ($_POST['empresa_id'] ?? 0);
        if ($empresa_id <= 0) {
            redirect('student-companies', 'Empresa invalida', 'error');
        }

        $estudiante = Database::selectOne("
            SELECT id, area_tecnica, cv_path
            FROM estudiantes
            WHERE usuario_id = ?
        ", [$_SESSION['user_id']]);

        if (!$estudiante) {
            redirect('student-companies', 'Estudiante no encontrado', 'error');
        }

        if (empty($estudiante['cv_path'])) {
            redirect('student-companies', 'Debes subir tu CV antes de postularte', 'warning');
        }

        $empresa = Empresa::getById($empresa_id);
        if (!$empresa || (int) $empresa['centro_id'] !== (int) ($_SESSION['centro_id'] ?? 0)) {
            redirect('student-companies', 'La empresa seleccionada no es valida', 'error');
        }

        if (($empresa['estado'] ?? '') !== 'disponible') {
            redirect('student-companies', 'Esta empresa no esta disponible para postulacion', 'warning');
        }

        if (Empresa::yaPostulado($estudiante['id'], $empresa_id)) {
            redirect('student-companies', 'Ya te postulaste a esta empresa', 'warning');
        }

        $evaluacion_pendiente = Database::selectOne("
            SELECT id FROM evaluaciones
            WHERE estudiante_id = ? AND estado = 'pendiente'
        ", [$estudiante['id']]);

        if ($evaluacion_pendiente) {
            $this->redirectToCompanies((int) $evaluacion_pendiente['id']);
        }

        $asignacion_activa = Database::selectOne("
            SELECT id FROM asignaciones
            WHERE estudiante_id = ? AND estado = 'activa'
        ", [$estudiante['id']]);

        if ($asignacion_activa) {
            redirect('student-companies', 'Ya tienes una pasantia activa y no puedes postularte a otra empresa', 'warning');
        }

        try {
            $evaluacion_id = Evaluacion::generarExamenUnico(
                $estudiante['id'],
                $empresa_id,
                $estudiante['area_tecnica'],
                Evaluacion::MAX_PREGUNTAS
            );
        } catch (Throwable $exception) {
            redirect('student-companies', $exception->getMessage(), 'error');
        }

        $this->redirectToCompanies((int) $evaluacion_id);
    }

    public function takeExam() {
        if (($_SESSION['rol'] ?? '') !== 'estudiante') {
            redirect('access-denied');
        }

        $evaluacion_id = (int) ($_GET['id'] ?? $_POST['evaluacion_id'] ?? 0);
        if ($evaluacion_id > 0) {
            $this->redirectToCompanies($evaluacion_id);
        }

        $estudiante = $this->getExamStudentContext();
        $evaluacion_pendiente = Evaluacion::getEvaluacionPendientePorEstudiante($estudiante['id']);
        if ($evaluacion_pendiente) {
            $this->redirectToCompanies((int) $evaluacion_pendiente['id']);
        }

        redirect('student-companies', 'No tienes ningun examen pendiente', 'info');
    }
    
    // ============================================
    // RESULTADOS
    // ============================================
    public function results() {
        if ($_SESSION['rol'] !== 'estudiante') {
            redirect('access-denied');
        }

        $estudiante = Database::selectOne("
            SELECT e.id AS estudiante_id, e.matricula, e.area_tecnica, e.foto_perfil, u.nombre, u.correo
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.usuario_id = ?
            LIMIT 1
        ", [$_SESSION['user_id']]);

        if (!$estudiante) {
            redirect('student-dashboard', 'No se encontro la informacion del estudiante', 'error');
        }

        $foto_perfil = $estudiante['foto_perfil'] ?? '';
        $historial_completo = Evaluacion::getHistorialPorEstudiante((int) $estudiante['estudiante_id']);

        $status_filter = strtolower(trim((string) ($_GET['status'] ?? 'todos')));
        $allowed_filters = ['todos', 'aprobado', 'reprobado', 'pendiente', 'anulado', 'asignada'];
        if (!in_array($status_filter, $allowed_filters, true)) {
            $status_filter = 'todos';
        }

        $filter_counts = [
            'todos' => count($historial_completo),
            'aprobado' => 0,
            'reprobado' => 0,
            'pendiente' => 0,
            'anulado' => 0,
            'asignada' => 0,
        ];

        $historial = [];
        foreach ($historial_completo as $evaluacion_item) {
            $estado = (string) ($evaluacion_item['estado'] ?? '');
            if (isset($filter_counts[$estado])) {
                $filter_counts[$estado]++;
            }

            if (!empty($evaluacion_item['asignacion_id'])) {
                $filter_counts['asignada']++;
            }

            $matches_filter = $status_filter === 'todos'
                || $estado === $status_filter
                || ($status_filter === 'asignada' && !empty($evaluacion_item['asignacion_id']));

            if ($matches_filter) {
                $historial[] = $evaluacion_item;
            }
        }

        $selected_evaluation_id = max(0, (int) ($_GET['evaluation'] ?? 0));

        $evaluacion_detalle = null;
        if ($selected_evaluation_id > 0) {
            $evaluacion_detalle = Evaluacion::getDetalleRevisionParaEstudiante(
                $selected_evaluation_id,
                (int) $estudiante['estudiante_id']
            );
        }

        if ($evaluacion_detalle && $status_filter !== 'todos') {
            $detalle_estado = (string) ($evaluacion_detalle['estado'] ?? '');
            $detalle_visible = $detalle_estado === $status_filter
                || ($status_filter === 'asignada' && !empty($evaluacion_detalle['asignacion_id']));

            if (!$detalle_visible) {
                $evaluacion_detalle = null;
            }
        }

        if (!$evaluacion_detalle) {
            $selected_evaluation_id = 0;
        }

        $stats = [
            'total' => count($historial_completo),
            'aprobadas' => $filter_counts['aprobado'],
            'reprobadas' => $filter_counts['reprobado'],
            'pendientes' => $filter_counts['pendiente'],
            'anuladas' => $filter_counts['anulado'],
            'asignadas' => $filter_counts['asignada'],
        ];

        $ultimo_evento_seguridad = null;
        if ($evaluacion_detalle && ($evaluacion_detalle['estado'] ?? '') === 'anulado') {
            $ultimo_evento_seguridad = Evaluacion::getUltimoEventoSeguridad((int) $evaluacion_detalle['id']);
        }

        $viewPath = APP_PATH . 'views/student/results.php';
        if (file_exists($viewPath)) {
            require_once $viewPath;
            return;
        }

        $this->showBasicDashboard();
    }

    private function processExamSubmission($evaluacion) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->redirectToCompanies((int) $evaluacion['id'], 'Token de seguridad invalido', 'error');
        }

        if (($evaluacion['estado'] ?? '') !== 'pendiente') {
            $this->redirectToCompanies((int) $evaluacion['id']);
        }

        if (Evaluacion::estaVencida($evaluacion)) {
            Evaluacion::registrarEventoSeguridad(
                (int) $evaluacion['id'],
                'time_expired',
                'El estudiante intento enviar el examen fuera del tiempo permitido.'
            );
        }

        $respuestas = $_POST['respuestas'] ?? [];
        Evaluacion::procesarExamen((int) $evaluacion['id'], $respuestas);
        $this->redirectToCompanies((int) $evaluacion['id']);
    }

    private function handleExamSecurityEvent($evaluacion) {
        header('Content-Type: application/json; charset=UTF-8');

        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode([
                'success' => false,
                'message' => 'Token de seguridad invalido'
            ]);
            exit;
        }

        if (($evaluacion['estado'] ?? '') !== 'pendiente') {
            echo json_encode([
                'success' => true,
                'redirect' => 'index.php?page=student-companies&exam=' . (int) $evaluacion['id']
            ]);
            exit;
        }

        $evento = trim((string) ($_POST['event'] ?? 'security_violation'));
        $detalles = trim((string) ($_POST['details'] ?? 'Se detecto una salida de la pestana del examen.'));
        $evento = $evento !== '' ? $evento : 'security_violation';
        $detalles = $detalles !== '' ? $detalles : 'Se detecto una salida de la pestana del examen.';

        Evaluacion::registrarEventoSeguridad((int) $evaluacion['id'], $evento, $detalles);
        Evaluacion::procesarExamen((int) $evaluacion['id'], [], 'anulado');

        echo json_encode([
            'success' => true,
            'redirect' => 'index.php?page=student-companies&exam=' . (int) $evaluacion['id']
        ]);
        exit;
    }

    private function buildExamModalState($estudiante, $evaluacion_id) {
        $student_evaluation_id = (int) ($estudiante['estudiante_id'] ?? $estudiante['id'] ?? 0);
        $evaluacion = Evaluacion::getEvaluacionParaEstudiante($evaluacion_id, $student_evaluation_id);

        if (!$evaluacion) {
            return null;
        }

        if (($evaluacion['estado'] ?? '') !== 'pendiente') {
            return [
                'mode' => 'result',
                'evaluacion' => $evaluacion,
                'resultado' => Evaluacion::getResumenEvaluacion($evaluacion_id),
                'ultimo_evento_seguridad' => Evaluacion::getUltimoEventoSeguridad($evaluacion_id),
            ];
        }

        if (Evaluacion::estaVencida($evaluacion)) {
            Evaluacion::registrarEventoSeguridad(
                $evaluacion_id,
                'time_expired',
                'El tiempo del examen se agoto antes del envio final.'
            );

            return [
                'mode' => 'result',
                'evaluacion' => Evaluacion::getEvaluacionParaEstudiante($evaluacion_id, $student_evaluation_id),
                'resultado' => Evaluacion::procesarExamen($evaluacion_id, []),
                'ultimo_evento_seguridad' => Evaluacion::getUltimoEventoSeguridad($evaluacion_id),
            ];
        }

        return [
            'mode' => 'active',
            'evaluacion' => $evaluacion,
            'preguntas' => Evaluacion::getPreguntasDeEvaluacion($evaluacion_id),
            'tiempo_restante' => Evaluacion::getTiempoRestante($evaluacion),
        ];
    }

    private function redirectToCompanies($exam_id = null, $message = '', $type = 'success') {
        if (!empty($message)) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }

        $url = 'index.php?page=student-companies';
        if (!empty($exam_id)) {
            $url .= '&exam=' . (int) $exam_id;
        }

        header('Location: ' . $url);
        exit;
    }

    private function getExamStudentContext() {
        $estudiante = Database::selectOne("
            SELECT e.id, e.area_tecnica, e.foto_perfil, u.nombre, u.correo
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.usuario_id = ?
            LIMIT 1
        ", [$_SESSION['user_id']]);

        if (!$estudiante) {
            redirect('student-companies', 'No se encontro la informacion del estudiante', 'error');
        }

        return $estudiante;
    }
    
    // ============================================
    // DASHBOARD BÃSICO (RESPALDO)
    // ============================================
    private function showBasicDashboard() {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Dashboard del Estudiante</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                :root {
                    --primary-color: #1a365d;
                    --accent-color: #4299e1;
                    --light-color: #f7fafc;
                    --blanco: #ffffff;
                }
                
                body {
                    font-family: 'Arial', sans-serif;
                    background: var(--light-color);
                    padding: 20px;
                    margin: 0;
                }
                
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background: var(--blanco);
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    padding: 30px;
                }
                
                .header {
                    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
                    color: white;
                    padding: 25px;
                    border-radius: 10px;
                    margin-bottom: 30px;
                }
                
                h1 {
                    margin: 0 0 10px 0;
                    font-size: 28px;
                }
                
                .welcome-text {
                    font-size: 16px;
                    opacity: 0.9;
                }
                
                .info-box {
                    background: var(--light-color);
                    border-left: 4px solid var(--accent-color);
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 5px;
                }
                
                .grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 20px;
                    margin-top: 30px;
                }
                
                .card {
                    background: var(--blanco);
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    padding: 25px;
                    text-align: center;
                    transition: transform 0.3s ease;
                    text-decoration: none;
                    color: var(--primary-color);
                    display: block;
                }
                
                .card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                    border-color: var(--accent-color);
                }
                
                .card i {
                    font-size: 36px;
                    color: var(--accent-color);
                    margin-bottom: 15px;
                }
                
                .card h3 {
                    margin: 0 0 10px 0;
                }
                
                .logout-btn {
                    background: #f56565;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    margin-top: 20px;
                    text-decoration: none;
                    display: inline-block;
                }
                
                .logout-btn:hover {
                    background: #e53e3e;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-user-graduate"></i> Dashboard del Estudiante</h1>
                    <p class="welcome-text">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                </div>
                
                <div class="info-box">
                    <h3><i class="fas fa-university"></i> InformaciÃ³n del Centro</h3>
                    <p><strong>Centro:</strong> <?php echo htmlspecialchars($_SESSION['centro_nombre'] ?? 'No asignado'); ?></p>
                    <p><strong>CÃ³digo:</strong> <?php echo htmlspecialchars($_SESSION['centro_codigo'] ?? 'N/A'); ?></p>
                </div>
                
                <h2>Acciones Disponibles</h2>
                <div class="grid">
                    <a href="index.php?page=student-profile" class="card">
                        <i class="fas fa-user-circle"></i>
                        <h3>Mi Perfil</h3>
                        <p>Ver y editar informaciÃ³n personal</p>
                    </a>
                    
                    <a href="index.php?page=student-companies" class="card">
                        <i class="fas fa-building"></i>
                        <h3>Empresas</h3>
                        <p>Ver empresas disponibles</p>
                    </a>
                    
                    <a href="index.php?page=student-results" class="card">
                        <i class="fas fa-chart-bar"></i>
                        <h3>Resultados</h3>
                        <p>Ver resultados de evaluaciones</p>
                    </a>
                    
                    <a href="index.php?page=logout" class="card" style="color: #f56565;">
                        <i class="fas fa-sign-out-alt"></i>
                        <h3>Cerrar SesiÃ³n</h3>
                        <p>Salir del sistema</p>
                    </a>
                </div>
                
                <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                <div style="margin-top: 40px; padding: 20px; background: #f7fafc; border-radius: 10px;">
                    <h3><i class="fas fa-bug"></i> InformaciÃ³n de Debug</h3>
                    <pre style="background: white; padding: 15px; border-radius: 5px;">
<?php 
print_r([
    'user_id' => $_SESSION['user_id'] ?? 'N/A',
    'rol' => $_SESSION['rol'] ?? 'N/A',
    'centro_id' => $_SESSION['centro_id'] ?? 'N/A',
    'centro_nombre' => $_SESSION['centro_nombre'] ?? 'N/A',
    'centro_codigo' => $_SESSION['centro_codigo'] ?? 'N/A'
]);
?>
                    </pre>
                </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
    
    // ============================================
    // PÃGINA EN CONSTRUCCIÃ“N
    // ============================================
    private function showUnderConstruction($title, $backPage) {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $title; ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f7fafc;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                
                .construction-box {
                    text-align: center;
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    max-width: 400px;
                }
                
                h1 {
                    color: #1a365d;
                    margin-bottom: 20px;
                }
                
                p {
                    color: #4a5568;
                    margin-bottom: 30px;
                }
                
                .back-link {
                    display: inline-block;
                    background: #4299e1;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                }
                
                .back-link:hover {
                    background: #3182ce;
                }
            </style>
        </head>
        <body>
            <div class="construction-box">
                <h1><?php echo $title; ?></h1>
                <p><i class="fas fa-tools" style="font-size: 48px; color: #ecc94b;"></i></p>
                <p>Esta secciÃ³n estÃ¡ en construcciÃ³n...</p>
                <a href="index.php?page=<?php echo $backPage; ?>" class="back-link">
                    Volver al Dashboard
                </a>
            </div>
        </body>
        </html>
        <?php
    }
}
