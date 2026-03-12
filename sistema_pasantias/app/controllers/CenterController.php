<?php
// app/controllers/CenterController.php

class CenterController {
    /**
     * Muestra el formulario de registro de centros.
     */
    public function register() {
        if (isset($_SESSION['user_id'])) {
            redirect('access-denied');
        }

        $csrfToken = Security::generateCSRFToken();
        $areasCatalog = AreaTecnica::getCatalog();
        $defaultAreas = AreaTecnica::getDefaultAreas();
        require_once APP_PATH . 'views/center/register.php';
    }

    public function areas() {
        $centerCode = strtoupper(trim((string) ($_GET['codigo'] ?? '')));
        $defaultAreas = AreaTecnica::getDefaultAreas();

        header('Content-Type: application/json; charset=UTF-8');

        if ($centerCode === '' || !Security::isValidCenterCode($centerCode)) {
            echo json_encode([
                'success' => false,
                'found' => false,
                'areas' => $defaultAreas,
                'message' => 'Codigo de centro invalido',
            ]);
            exit;
        }

        $center = Usuario::centerExists($centerCode);
        if (!$center) {
            echo json_encode([
                'success' => false,
                'found' => false,
                'areas' => $defaultAreas,
                'message' => 'Centro no encontrado',
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'found' => true,
            'center_name' => $center['nombre'] ?? '',
            'areas' => AreaTecnica::getAreasByCenterId((int) $center['id']),
        ]);
        exit;
    }

    /**
     * Procesa el registro de un centro.
     */
    public function processRegister() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('center-register', 'Metodo no permitido', 'error');
        }

        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            redirect('center-register', 'Token de seguridad invalido', 'error');
        }

        $data = [
            'nombre' => Security::sanitize($_POST['nombre'] ?? ''),
            'nombre_admin' => Security::sanitize($_POST['nombre_admin'] ?? ''),
            'correo_admin' => Security::sanitize($_POST['correo_admin'] ?? ''),
            'areas_tecnicas' => AreaTecnica::sanitizeAreas($_POST['areas_tecnicas'] ?? []),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'accept_terms' => isset($_POST['accept_terms']),
        ];
        $errors = $this->validateCenterRegistration($data);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $this->getSafeCenterData($data);
            redirect('center-register');
        }

        try {
            $center = Centro::createWithAdmin($data);

            $_SESSION['created_center'] = $center;
            redirect('center-register', 'Centro registrado correctamente', 'success');
        } catch (Exception $e) {
            $_SESSION['form_data'] = $this->getSafeCenterData($data);
            redirect('center-register', $e->getMessage(), 'error');
        }
    }

    private function validateCenterRegistration($data) {
        $errors = [];

        if (empty($data['nombre'])) {
            $errors['nombre'] = 'El nombre del centro es obligatorio';
        } elseif (strlen($data['nombre']) < 4) {
            $errors['nombre'] = 'El nombre del centro debe tener al menos 4 caracteres';
        }

        if (empty($data['nombre_admin'])) {
            $errors['nombre_admin'] = 'El nombre del administrador es obligatorio';
        } elseif (strlen($data['nombre_admin']) < 3) {
            $errors['nombre_admin'] = 'El nombre del administrador debe tener al menos 3 caracteres';
        } elseif (strlen($data['nombre_admin']) > 100) {
            $errors['nombre_admin'] = 'El nombre del administrador es demasiado largo';
        }

        if (empty($data['correo_admin'])) {
            $errors['correo_admin'] = 'El correo del administrador es obligatorio';
        } elseif (!Security::isValidEmail($data['correo_admin'])) {
            $errors['correo_admin'] = 'Correo electronico invalido';
        } elseif (Database::selectOne("SELECT id FROM usuarios WHERE correo = ? LIMIT 1", [$data['correo_admin']])) {
            $errors['correo_admin'] = 'Ese correo ya esta registrado';
        }

        if (empty($data['areas_tecnicas'])) {
            $errors['areas_tecnicas'] = 'Selecciona al menos un area tecnica para el centro';
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

        if (empty($data['confirm_password'])) {
            $errors['confirm_password'] = 'Debes confirmar la contrasena';
        } elseif (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
            $errors['confirm_password'] = 'Las contrasenas no coinciden';
        }

        if (empty($data['accept_terms'])) {
            $errors['accept_terms'] = 'Debes aceptar los terminos y condiciones';
        }

        return $errors;
    }

    private function getSafeCenterData($data) {
        unset($data['password'], $data['confirm_password']);
        return $data;
    }
}
