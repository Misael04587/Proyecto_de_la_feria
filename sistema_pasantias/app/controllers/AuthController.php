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

    public function forgotPassword() {
        PasswordReset::ensureSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->redirectToForgotPassword(
                    trim((string) ($_POST['token'] ?? '')),
                    'Token de seguridad invalido',
                    'error'
                );
            }

            $intent = trim((string) ($_POST['intent'] ?? 'request_reset'));
            if ($intent === 'request_reset') {
                $data = [
                    'codigo_centro' => Security::sanitize($_POST['codigo_centro'] ?? ''),
                    'correo' => Security::sanitize($_POST['correo'] ?? ''),
                ];

                $errorMessage = $this->validatePasswordResetRequest($data);
                if ($errorMessage !== null) {
                    $this->persistForgotPasswordFormData($data);
                    $this->redirectToForgotPassword('', $errorMessage, 'error');
                }

                $user = Database::selectOne("
                    SELECT u.id
                    FROM usuarios u
                    JOIN centros c ON c.id = u.centro_id
                    WHERE u.correo = ?
                      AND c.codigo_unico = ?
                      AND u.estado = 'activo'
                    LIMIT 1
                ", [$data['correo'], $data['codigo_centro']]);
                if ($user) {
                    try {
                        $resetData = PasswordReset::createForUser((int) $user['id']);
                        if (DEBUG_MODE) {
                            $_SESSION['forgot_password_debug_url'] = [
                                'url' => $this->buildResetUrl($resetData['token']),
                                'expires_at' => $resetData['expires_at'],
                            ];
                        }
                    } catch (Throwable $exception) {
                        $this->persistForgotPasswordFormData($data);
                        $this->redirectToForgotPassword('', $exception->getMessage(), 'error');
                    }
                }

                $this->redirectToForgotPassword(
                    '',
                    'Si la cuenta existe, generamos un enlace temporal para restablecer la contrasena.',
                    'success'
                );
            }

            if ($intent === 'reset_password') {
                $token = trim((string) ($_POST['token'] ?? ''));
                $resetRequest = PasswordReset::findValidRequest($token);

                if (!$resetRequest) {
                    $this->redirectToForgotPassword('', 'El enlace de recuperacion no es valido o ya vencio', 'error');
                }

                $newPassword = (string) ($_POST['password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
                $errorMessage = $this->validateRecoveredPassword($newPassword, $confirmPassword);

                if ($errorMessage !== null) {
                    $this->redirectToForgotPassword($token, $errorMessage, 'error');
                }

                try {
                    Database::beginTransaction();

                    if (!Usuario::setPassword((int) $resetRequest['user_id'], $newPassword)) {
                        throw new RuntimeException('No se pudo actualizar la contrasena');
                    }

                    if (!PasswordReset::markUsed((int) $resetRequest['id'])) {
                        throw new RuntimeException('No se pudo cerrar el enlace de recuperacion');
                    }

                    PasswordReset::invalidateUserTokens((int) $resetRequest['user_id'], (int) $resetRequest['id']);
                    Database::commit();
                } catch (Throwable $exception) {
                    if (Database::getInstance()->inTransaction()) {
                        Database::rollback();
                    }

                    $this->redirectToForgotPassword($token, $exception->getMessage(), 'error');
                }

                $_SESSION['flash_message'] = 'Contrasena restablecida correctamente. Ya puedes iniciar sesion.';
                $_SESSION['flash_type'] = 'success';

                $loginUrl = 'index.php?page=login';
                $query = [];

                if (!empty($resetRequest['centro_codigo'])) {
                    $query['codigo_centro'] = $resetRequest['centro_codigo'];
                }
                if (!empty($resetRequest['correo'])) {
                    $query['correo'] = $resetRequest['correo'];
                }
                if (!empty($query)) {
                    $loginUrl .= '&' . http_build_query($query);
                }

                header('Location: ' . $loginUrl);
                exit;
            }

            $this->redirectToForgotPassword('', 'Accion de recuperacion no reconocida', 'warning');
        }

        $resetToken = trim((string) ($_GET['token'] ?? ''));
        $resetRequest = null;
        $forgotPasswordMode = 'request';

        if ($resetToken !== '') {
            $resetRequest = PasswordReset::findValidRequest($resetToken);
            if ($resetRequest) {
                $forgotPasswordMode = 'reset';
            } else {
                $_SESSION['flash_message'] = 'El enlace de recuperacion no es valido o ya vencio';
                $_SESSION['flash_type'] = 'error';
            }
        }

        $csrfToken = Security::generateCSRFToken();
        $formData = $this->consumeForgotPasswordFormData();
        $debugResetUrl = $_SESSION['forgot_password_debug_url'] ?? null;
        unset($_SESSION['forgot_password_debug_url']);

        require_once APP_PATH . 'views/auth/forgot-password.php';
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

        $allowedAreas = AreaTecnica::getDefaultAreas();
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

    private function validatePasswordResetRequest(array $data) {
        if (empty($data['codigo_centro'])) {
            return 'El codigo de centro es obligatorio';
        }

        if (!Security::isValidCenterCode($data['codigo_centro'])) {
            return 'Formato de codigo de centro invalido';
        }

        if (!Usuario::centerExists($data['codigo_centro'])) {
            return 'Codigo de centro invalido o centro inactivo';
        }

        if (empty($data['correo'])) {
            return 'El correo es obligatorio';
        }

        if (!Security::isValidEmail($data['correo'])) {
            return 'Correo electronico invalido';
        }

        return null;
    }

    private function validateRecoveredPassword($password, $confirmPassword) {
        if ($password === '' || $confirmPassword === '') {
            return 'Completa ambos campos de contrasena';
        }

        if (strlen($password) < 8) {
            return 'La contrasena debe tener al menos 8 caracteres';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'La contrasena debe contener al menos una mayuscula';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'La contrasena debe contener al menos un numero';
        }

        if ($password !== $confirmPassword) {
            return 'Las contrasenas no coinciden';
        }

        return null;
    }

    /**
     * Quita datos sensibles antes de devolver el formulario.
     */
    private function getSafeRegistrationData($data) {
        unset($data['password'], $data['confirm_password']);
        return $data;
    }

    private function buildResetUrl($token) {
        return BASE_URL . 'index.php?page=forgot-password&token=' . urlencode((string) $token);
    }

    private function redirectToForgotPassword($token = '', $message = '', $type = 'info') {
        if ($message !== '') {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }

        $url = 'index.php?page=forgot-password';
        if (trim((string) $token) !== '') {
            $url .= '&token=' . urlencode((string) $token);
        }

        header('Location: ' . $url);
        exit;
    }

    private function persistForgotPasswordFormData(array $data) {
        $_SESSION['forgot_password_form_data'] = $data;
    }

    private function consumeForgotPasswordFormData() {
        $data = $_SESSION['forgot_password_form_data'] ?? [];
        unset($_SESSION['forgot_password_form_data']);

        return is_array($data) ? $data : [];
    }
}
