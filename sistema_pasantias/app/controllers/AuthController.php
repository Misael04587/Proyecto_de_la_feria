<?php
// app/controllers/AuthController.php

class AuthController {
    /**
     * Muestra formulario de login.
     */
    public function login() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['rol'])) {
            $this->redirectByRole();
            return;
        }

        $csrfToken = Security::generateCSRFToken();
        require_once APP_PATH . 'views/auth/login.php';
    }

    /**
     * Redirige al index principal.
     */
    public function welcome() {
        header('Location: ../../index.php');
        exit;
    }

    /**
     * Procesa el login.
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('login', 'Metodo no permitido', 'error');
        }

        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('login', 'Token de seguridad invalido', 'error');
        }

        $centerCode = Security::sanitize($_POST['codigo_centro'] ?? '');
        $email = Security::sanitize($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($centerCode) || empty($email) || empty($password)) {
            redirect('login', 'Todos los campos son obligatorios', 'error');
        }

        $center = Usuario::centerExists($centerCode);
        if (!$center) {
            Security::logFailedLogin($email, Security::getClientIP());
            redirect('login', 'Codigo de centro invalido o centro inactivo', 'error');
        }

        $user = Usuario::findByEmailAndCenter($email, $centerCode);

        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            Security::logFailedLogin($email, Security::getClientIP());
            redirect('login', 'Correo o contrasena incorrectos', 'error');
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['rol'] = $user['rol_nombre'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['centro_id'] = $user['centro_id'] ?? null;
        $_SESSION['centro_nombre'] = $user['centro_nombre'] ?? null;
        $_SESSION['centro_codigo'] = $centerCode;
        $_SESSION['last_activity'] = time();

        $this->redirectByRole();
    }

    /**
     * Muestra formulario de registro de estudiantes.
     */
    public function register() {
        if (isset($_SESSION['user_id'])) {
            $this->redirectByRole();
            return;
        }

        $csrfToken = Security::generateCSRFToken();
        $defaultAreas = AreaTecnica::getDefaultAreas();
        require_once APP_PATH . 'views/auth/register.php';
    }

    /**
     * Procesa el registro de estudiantes.
     */
    public function processRegister() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('register', 'Metodo no permitido', 'error');
        }

        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('register', 'Token de seguridad invalido', 'error');
        }

        $data = [
            'codigo_centro' => Security::sanitize($_POST['codigo_centro'] ?? ''),
            'nombre' => Security::sanitize($_POST['nombre'] ?? ''),
            'correo' => Security::sanitize($_POST['correo'] ?? ''),
            'area_tecnica' => $_POST['area_tecnica'] ?? '',
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'accept_terms' => isset($_POST['accept_terms'])
        ];

        $errors = $this->validateRegistration($data);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $this->getSafeRegistrationData($data);
            redirect('register');
        }

        try {
            $result = Usuario::createStudent($data);

            session_regenerate_id(true);

            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['rol'] = 'estudiante';
            $_SESSION['nombre'] = $data['nombre'];
            $_SESSION['centro_id'] = $result['center_id'];
            $_SESSION['centro_nombre'] = $result['center_name'];
            $_SESSION['centro_codigo'] = $result['center_code'];
            $_SESSION['matricula'] = $result['matricula'];
            $_SESSION['last_activity'] = time();

            redirect('student-dashboard', 'Registro exitoso. Tu matricula es ' . $result['matricula'], 'success');
        } catch (Exception $e) {
            $_SESSION['form_data'] = $this->getSafeRegistrationData($data);
            redirect('register', $e->getMessage(), 'error');
        }
    }

    /**
     * Cierra sesion.
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $query = "INSERT INTO logs_seguridad (evento, detalles, ip_address)
                      VALUES ('logout', ?, ?)";
            Database::execute($query, [
                "Usuario {$_SESSION['user_id']} cerro sesion",
                Security::getClientIP()
            ]);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? true
            );
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Clear-Site-Data: "cache"');
        }

        session_destroy();
        session_start();
        session_regenerate_id(true);

        $_SESSION['flash_message'] = 'Sesion cerrada correctamente';
        $_SESSION['flash_type'] = 'success';

        header('Location: index.php?page=login');
        exit;
    }

    /**
     * Redirige segun el rol del usuario.
     */
    private function redirectByRole() {
        switch ($_SESSION['rol']) {
            case 'super_admin':
                redirect('superadmin-dashboard');
                break;
            case 'admin_centro':
            case 'coordinador':
                redirect('admin-dashboard');
                break;
            case 'estudiante':
                redirect('student-dashboard');
                break;
            default:
                redirect('login');
        }
    }

    /**
     * Valida datos del registro de estudiantes.
     */
    private function validateRegistration($data) {
        $errors = [];

        if (empty($data['codigo_centro'])) {
            $errors['codigo_centro'] = 'El codigo de centro es obligatorio';
        } elseif (!Security::isValidCenterCode($data['codigo_centro'])) {
            $errors['codigo_centro'] = 'Formato de codigo de centro invalido';
        }

        if (empty($data['nombre'])) {
            $errors['nombre'] = 'El nombre es obligatorio';
        } elseif (strlen($data['nombre']) < 3) {
            $errors['nombre'] = 'El nombre debe tener al menos 3 caracteres';
        }

        if (empty($data['correo'])) {
            $errors['correo'] = 'El correo es obligatorio';
        } elseif (!Security::isValidEmail($data['correo'])) {
            $errors['correo'] = 'Correo electronico invalido';
        }

        $allowedAreas = ['Gastronomía', 'Administración', 'Electricidad', 'Informática'];
        if (!empty($data['codigo_centro']) && Security::isValidCenterCode($data['codigo_centro'])) {
            $center = Usuario::centerExists($data['codigo_centro']);
            if (!$center) {
                $errors['codigo_centro'] = 'Codigo de centro invalido o centro inactivo';
            } else {
                $allowedAreas = AreaTecnica::getAreasByCenterId((int) $center['id']);
            }
        }

        if (empty($data['area_tecnica']) || !in_array($data['area_tecnica'], $allowedAreas, true)) {
            $errors['area_tecnica'] = 'Selecciona un area tecnica valida';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'La contrasena es obligatoria';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'La contrasena debe tener al menos 8 caracteres';
        } elseif (!preg_match('/[A-Z]/', $data['password'])) {
            $errors['password'] = 'La contrasena debe contener al menos una mayuscula';
        } elseif (!preg_match('/[0-9]/', $data['password'])) {
            $errors['password'] = 'La contrasena debe contener al menos un numero';
        }

        if ($data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = 'Las contrasenas no coinciden';
        }

        if (empty($data['accept_terms'])) {
            $errors['accept_terms'] = 'Debes aceptar los terminos y condiciones';
        }

        return $errors;
    }

    /**
     * Quita datos sensibles antes de devolver el formulario.
     */
    private function getSafeRegistrationData($data) {
        unset($data['password'], $data['confirm_password']);
        return $data;
    }
}
