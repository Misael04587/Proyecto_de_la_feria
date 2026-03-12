<?php
// app/views/auth/register.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = $_SESSION['form_errors'] ?? [];
$data = $_SESSION['form_data'] ?? [];
$queryCenterCode = Security::sanitize($_GET['codigo_centro'] ?? '');

if (empty($data['codigo_centro']) && !empty($queryCenterCode)) {
    $data['codigo_centro'] = $queryCenterCode;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/sistema_pasantias/';
$defaultAreas = $defaultAreas ?? AreaTecnica::getDefaultAreas();
$selectedArea = $data['area_tecnica'] ?? '';
$availableAreas = !empty($data['codigo_centro'])
    ? AreaTecnica::getAreasByCenterCode($data['codigo_centro'])
    : $defaultAreas;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema EPIC</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/register.css">
</head>
<body>
    <div class="register-shell">
        <div class="register-card">
            <div class="register-header">
                <div class="logo-circle">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Crear cuenta</h1>
                <p>Registro de estudiantes con matricula generada automaticamente</p>
            </div>

            <form action="index.php?page=register&action=process" method="POST" class="register-form" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'success'; ?>">
                        <i class="fas fa-<?php echo ($_SESSION['flash_type'] ?? 'success') === 'error' ? 'triangle-exclamation' : 'circle-check'; ?>"></i>
                        <span><?php echo htmlspecialchars($_SESSION['flash_message']); ?></span>
                    </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

                <div class="helper-banner">
                    <div>
                        <h3>Necesitas un codigo de centro</h3>
                        <p>Si todavia no lo tienes, registra primero el centro y vuelve aqui con el codigo ya listo.</p>
                    </div>
                    <a href="index.php?page=center-register">
                        <i class="fas fa-building"></i>
                        Registrar centro
                    </a>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="label required" for="codigo_centro">
                            <i class="fas fa-university"></i>
                            Codigo del centro
                        </label>
                        <div class="input-wrap">
                            <i class="icon fas fa-key"></i>
                            <input
                                type="text"
                                id="codigo_centro"
                                name="codigo_centro"
                                class="input <?php echo isset($errors['codigo_centro']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($data['codigo_centro'] ?? ''); ?>"
                                placeholder="ABCD-1F2G-<?php echo date('Y'); ?>"
                                required
                            >
                        </div>
                        <?php if (isset($errors['codigo_centro'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['codigo_centro']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="label" for="matricula_preview">
                            <i class="fas fa-id-card"></i>
                            Matricula
                        </label>
                        <div class="input-wrap">
                            <i class="icon fas fa-wand-magic-sparkles"></i>
                            <div class="readonly-box" id="matricula_preview">Se asignara automaticamente al crear la cuenta</div>
                        </div>
                        <div class="hint">El sistema genera la siguiente matricula disponible para el centro en el ano actual.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="label required" for="nombre">
                        <i class="fas fa-user"></i>
                        Nombre completo
                    </label>
                    <div class="input-wrap">
                        <i class="icon fas fa-user-circle"></i>
                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            class="input <?php echo isset($errors['nombre']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($data['nombre'] ?? ''); ?>"
                            placeholder="Juan Perez Garcia"
                            required
                        >
                    </div>
                    <?php if (isset($errors['nombre'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['nombre']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="label required" for="correo">
                        <i class="fas fa-envelope"></i>
                        Correo electronico
                    </label>
                    <div class="input-wrap">
                        <i class="icon fas fa-at"></i>
                        <input
                            type="email"
                            id="correo"
                            name="correo"
                            class="input <?php echo isset($errors['correo']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($data['correo'] ?? ''); ?>"
                            placeholder="ejemplo@correo.com"
                            required
                        >
                    </div>
                    <?php if (isset($errors['correo'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['correo']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="label required" for="area_tecnica">
                        <i class="fas fa-graduation-cap"></i>
                        Area tecnica
                    </label>
                    <div class="input-wrap">
                        <i class="icon fas fa-briefcase"></i>
                        <select
                            id="area_tecnica"
                            name="area_tecnica"
                            class="select <?php echo isset($errors['area_tecnica']) ? 'error' : ''; ?>"
                            required
                        >
                            <option value="">Selecciona tu area</option>
                            <option value="Gastronomía" <?php echo (($data['area_tecnica'] ?? '') === 'Gastronomía') ? 'selected' : ''; ?>>Gastronomía</option>
                            <option value="Administración" <?php echo (($data['area_tecnica'] ?? '') === 'Administración') ? 'selected' : ''; ?>>Administración</option>
                            <option value="Electricidad" <?php echo (($data['area_tecnica'] ?? '') === 'Electricidad') ? 'selected' : ''; ?>>Electricidad</option>
                            <option value="Informática" <?php echo (($data['area_tecnica'] ?? '') === 'Informática') ? 'selected' : ''; ?>>Informática</option>
                        </select>
                        <i class="select-arrow fas fa-chevron-down"></i>
                    </div>
                    <div class="hint" id="areaHelp">Las areas cambian segun el codigo unico del centro que escribas.</div>
                    <?php if (isset($errors['area_tecnica'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['area_tecnica']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="label required" for="passwordInput">
                            <i class="fas fa-lock"></i>
                            Contrasena
                        </label>
                        <div class="input-wrap">
                            <i class="icon fas fa-key"></i>
                            <input
                                type="password"
                                id="passwordInput"
                                name="password"
                                class="input <?php echo isset($errors['password']) ? 'error' : ''; ?>"
                                placeholder="Minimo 8 caracteres"
                                required
                            >
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['password']); ?></div>
                        <?php endif; ?>
                        <div class="hint">Debe incluir al menos una mayuscula y un numero.</div>
                    </div>

                    <div class="form-group">
                        <label class="label required" for="confirmPasswordInput">
                            <i class="fas fa-lock"></i>
                            Confirmar contrasena
                        </label>
                        <div class="input-wrap">
                            <i class="icon fas fa-key"></i>
                            <input
                                type="password"
                                id="confirmPasswordInput"
                                name="confirm_password"
                                class="input <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>"
                                placeholder="Repite tu contrasena"
                                required
                            >
                            <button type="button" class="toggle-password" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="terms-box">
                    <label class="terms-check">
                        <input type="checkbox" name="accept_terms" id="accept_terms" <?php echo !empty($data['accept_terms']) ? 'checked' : ''; ?>>
                        <span>Acepto los terminos y condiciones del sistema y confirmo que la informacion suministrada es correcta.</span>
                    </label>
                    <?php if (isset($errors['accept_terms'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['accept_terms']); ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Crear mi cuenta</span>
                </button>

                <div class="register-links">
                    <p>Si ya tienes cuenta, entra al sistema. Si aun no existe tu centro, registralo primero.</p>
                    <div class="link-row">
                        <a href="index.php?page=login" class="link-btn primary">
                            <i class="fas fa-right-to-bracket"></i>
                            Iniciar sesion
                        </a>
                        <a href="index.php?page=center-register" class="link-btn secondary">
                            <i class="fas fa-building"></i>
                            Registrar centro
                        </a>
                    </div>
                </div>
            </form>

            <div class="back-link">
                <a href="../index.php">
                    <i class="fas fa-arrow-left"></i>
                    Volver al inicio
                </a>
            </div>

            <div class="register-footer">
                <div class="system-name">EPIC V2.0</div>
                <p>Sistema de Gestion de Pasantias</p>
                <p>&copy; <?php echo date('Y'); ?></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('passwordInput');
            const confirmPasswordInput = document.getElementById('confirmPasswordInput');
            const form = document.getElementById('registerForm');
            const centerCodeInput = document.getElementById('codigo_centro');
            const areaSelect = document.getElementById('area_tecnica');
            const areaHelp = document.getElementById('areaHelp');
            const defaultAreas = <?php echo json_encode(array_values($defaultAreas)); ?>;
            let selectedArea = <?php echo json_encode($selectedArea); ?>;
            let centerLookupTimer = null;

            function wirePasswordToggle(button, input) {
                if (!button || !input) {
                    return;
                }

                button.addEventListener('click', function () {
                    const nextType = input.type === 'password' ? 'text' : 'password';
                    input.type = nextType;
                    this.innerHTML = nextType === 'password'
                        ? '<i class="fas fa-eye"></i>'
                        : '<i class="fas fa-eye-slash"></i>';
                });
            }

            wirePasswordToggle(togglePassword, passwordInput);
            wirePasswordToggle(toggleConfirmPassword, confirmPasswordInput);

            function validatePasswords() {
                if (!passwordInput.value || !confirmPasswordInput.value) {
                    confirmPasswordInput.classList.remove('error');
                    return;
                }

                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.classList.add('error');
                } else {
                    confirmPasswordInput.classList.remove('error');
                }
            }

            function buildAreaOptions(areas) {
                if (!areaSelect) {
                    return;
                }

                const options = ['<option value="">Selecciona tu area</option>'];
                (areas || []).forEach(function (area) {
                    const isSelected = selectedArea && selectedArea === area ? ' selected' : '';
                    options.push('<option value="' + area + '"' + isSelected + '>' + area + '</option>');
                });
                areaSelect.innerHTML = options.join('');
            }

            function updateAreaHelp(message) {
                if (areaHelp) {
                    areaHelp.textContent = message;
                }
            }

            function loadCenterAreas(centerCode) {
                const normalizedCode = (centerCode || '').trim().toUpperCase();
                if (!normalizedCode) {
                    buildAreaOptions(defaultAreas);
                    updateAreaHelp('Las areas cambian segun el codigo unico del centro que escribas.');
                    return;
                }

                updateAreaHelp('Validando codigo del centro y cargando areas...');

                fetch('index.php?page=center-areas&codigo=' + encodeURIComponent(normalizedCode), {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (payload) {
                        const areas = Array.isArray(payload.areas) && payload.areas.length ? payload.areas : defaultAreas;
                        buildAreaOptions(areas);
                        updateAreaHelp(payload.found
                            ? 'Centro detectado: ' + (payload.center_name || 'Centro activo') + '. Areas actualizadas.'
                            : 'No se pudo reconocer el centro. Se muestran las areas por defecto.');
                    })
                    .catch(function () {
                        buildAreaOptions(defaultAreas);
                        updateAreaHelp('No se pudo validar el centro ahora mismo. Se muestran las areas por defecto.');
                    });
            }

            passwordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);
            areaSelect?.addEventListener('change', function () {
                selectedArea = this.value;
            });
            centerCodeInput?.addEventListener('input', function () {
                clearTimeout(centerLookupTimer);
                centerLookupTimer = setTimeout(function () {
                    loadCenterAreas(centerCodeInput.value);
                }, 350);
            });

            form.addEventListener('submit', function (event) {
                const terms = document.getElementById('accept_terms');

                if (!terms.checked) {
                    event.preventDefault();
                    alert('Debes aceptar los terminos y condiciones para continuar.');
                    terms.focus();
                    return;
                }

                if (passwordInput.value !== confirmPasswordInput.value) {
                    event.preventDefault();
                    alert('Las contrasenas no coinciden.');
                    confirmPasswordInput.focus();
                    return;
                }

                const submitBtn = form.querySelector('.submit-btn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Creando cuenta...</span>';
                submitBtn.disabled = true;
            });

            buildAreaOptions(<?php echo json_encode(array_values($availableAreas)); ?>);
            if (centerCodeInput && centerCodeInput.value.trim() !== '') {
                loadCenterAreas(centerCodeInput.value);
            }
        });
    </script>
</body>
</html>
<?php
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>
