<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = $_SESSION['form_errors'] ?? [];
$data = $_SESSION['form_data'] ?? [];
$createdCenter = $_SESSION['created_center'] ?? null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$areasCatalog = $areasCatalog ?? AreaTecnica::getCatalog();
$defaultAreas = $defaultAreas ?? AreaTecnica::getDefaultAreas();
$selectedAreas = $data['areas_tecnicas'] ?? $defaultAreas;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Centro - Sistema EPIC</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/register.css">
    <style>
        .logo-circle {
            background: linear-gradient(135deg, var(--accent), #63b3ed);
            box-shadow: 0 12px 28px rgba(66, 153, 225, 0.32);
        }

        .helper-banner {
            align-items: flex-start;
        }

        .helper-banner strong {
            color: var(--primary);
            display: block;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .success-card {
            background: linear-gradient(135deg, #f0fff4, #ebf8ff);
            border: 1px solid #bee3f8;
            border-radius: 22px;
            padding: 22px;
            margin-bottom: 26px;
        }

        .success-card h3 {
            color: var(--primary);
            font-size: 21px;
            margin-bottom: 8px;
        }

        .success-card p {
            color: var(--dark);
            font-size: 14px;
            line-height: 1.7;
            opacity: 0.82;
            margin-bottom: 18px;
        }

        .success-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .metric-box {
            border-radius: 18px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(190, 227, 248, 0.9);
        }

        .metric-box span {
            display: block;
            color: #718096;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .metric-box strong {
            color: var(--primary);
            font-size: 16px;
            line-height: 1.5;
            word-break: break-word;
        }

        .generated-code {
            padding: 18px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: var(--white);
            font-family: 'Montserrat', sans-serif;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-align: center;
            margin-bottom: 18px;
        }

        .success-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .success-actions .link-btn {
            flex: 1;
            min-width: 220px;
        }

        .readonly-box.preview-ready {
            color: var(--primary);
            font-weight: 700;
        }

        .areas-panel {
            background: rgba(247, 250, 252, 0.95);
            border: 1px solid rgba(203, 213, 224, 0.9);
            border-radius: 20px;
            padding: 18px;
        }

        .areas-panel h3 {
            color: var(--primary);
            font-size: 18px;
            margin-bottom: 8px;
        }

        .areas-panel p {
            color: var(--dark);
            opacity: 0.78;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .areas-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .area-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 16px;
            background: var(--white);
            border: 1px solid rgba(66, 153, 225, 0.16);
        }

        .area-option input {
            width: 18px;
            height: 18px;
        }

        .area-option span {
            color: var(--primary);
            font-weight: 600;
        }

        @media (max-width: 640px) {
            .success-metrics {
                grid-template-columns: 1fr;
            }

            .areas-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-shell">
        <div class="register-card">
            <div class="register-header">
                <div class="logo-circle">
                    <i class="fas fa-building-shield"></i>
                </div>
                <h1>Registrar centro</h1>
                <p>Crea el centro y su cuenta administradora con codigo unico automatico</p>
            </div>

            <form action="index.php?page=center-register&action=process" method="POST" class="register-form" id="centerRegisterForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'success'; ?>">
                        <i class="fas fa-<?php echo ($_SESSION['flash_type'] ?? 'success') === 'error' ? 'triangle-exclamation' : 'circle-check'; ?>"></i>
                        <span><?php echo htmlspecialchars($_SESSION['flash_message']); ?></span>
                    </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

                <div class="helper-banner">
                    <div>
                        <strong>Alta de centro + administrador</strong>
                        <p>Se genera el codigo unico del centro y tambien queda lista la cuenta del administrador para entrar al sistema.</p>
                    </div>
                    <a href="index.php?page=login">
                        <i class="fas fa-right-to-bracket"></i>
                        Ir a login
                    </a>
                </div>

                <?php if ($createdCenter): ?>
                    <div class="success-card">
                        <h3><?php echo htmlspecialchars($createdCenter['nombre']); ?></h3>
                        <p>El centro y su cuenta administradora quedaron creados. Guarda este codigo porque sera necesario para iniciar sesion y registrar estudiantes.</p>

                        <div class="generated-code"><?php echo htmlspecialchars($createdCenter['codigo_unico']); ?></div>

                        <div class="success-metrics">
                            <div class="metric-box">
                                <span>Centro</span>
                                <strong><?php echo htmlspecialchars($createdCenter['nombre']); ?></strong>
                            </div>
                            <div class="metric-box">
                                <span>Administrador</span>
                                <strong><?php echo htmlspecialchars($createdCenter['admin_nombre'] ?? ''); ?></strong>
                            </div>
                            <div class="metric-box">
                                <span>Correo administrador</span>
                                <strong><?php echo htmlspecialchars($createdCenter['admin_correo'] ?? ''); ?></strong>
                            </div>
                            <div class="metric-box">
                                <span>Acceso inicial</span>
                                <strong>Codigo de centro + correo + contrasena</strong>
                            </div>
                            <div class="metric-box" style="grid-column: 1 / -1;">
                                <span>Areas tecnicas</span>
                                <strong><?php echo htmlspecialchars(implode(', ', $createdCenter['areas_tecnicas'] ?? [])); ?></strong>
                            </div>
                        </div>

                        <div class="success-actions">
                            <a href="index.php?page=login&codigo_centro=<?php echo urlencode($createdCenter['codigo_unico']); ?>&correo=<?php echo urlencode($createdCenter['admin_correo'] ?? ''); ?>" class="link-btn primary">
                                <i class="fas fa-user-shield"></i>
                                Entrar con administrador
                            </a>
                            <a href="index.php?page=register&codigo_centro=<?php echo urlencode($createdCenter['codigo_unico']); ?>" class="link-btn secondary">
                                <i class="fas fa-user-plus"></i>
                                Registrar estudiante
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="label required" for="nombre">
                            <i class="fas fa-school"></i>
                            Nombre del centro
                        </label>
                        <div class="input-wrap">
                            <i class="icon fas fa-pen"></i>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                class="input <?php echo isset($errors['nombre']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($data['nombre'] ?? ''); ?>"
                                placeholder="Ej: Politecnico Juan Pablo II"
                                required
                            >
                        </div>
                        <?php if (isset($errors['nombre'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['nombre']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="label" for="codigo_preview">
                            <i class="fas fa-key"></i>
                            Codigo unico
                        </label>
                        <div class="input-wrap">
                            <i class="icon fas fa-wand-magic-sparkles"></i>
                            <div class="readonly-box" id="codigo_preview">SEGE-XXXX-<?php echo date('Y'); ?></div>
                        </div>
                        <div class="hint">Vista previa automatica. El codigo final se genera y valida al guardar.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="label required" for="nombre_admin">
                        <i class="fas fa-user-shield"></i>
                        Nombre del administrador
                    </label>
                    <div class="input-wrap">
                        <i class="icon fas fa-id-badge"></i>
                        <input
                            type="text"
                            id="nombre_admin"
                            name="nombre_admin"
                            class="input <?php echo isset($errors['nombre_admin']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($data['nombre_admin'] ?? ''); ?>"
                            placeholder="Ej: Maria Fernanda Gomez"
                            required
                        >
                    </div>
                    <?php if (isset($errors['nombre_admin'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['nombre_admin']); ?></div>
                    <?php endif; ?>
                    <div class="hint">Este nombre se mostrara dentro del panel administrativo del centro.</div>
                </div>

                <div class="form-group">
                    <label class="label required" for="correo_admin">
                        <i class="fas fa-envelope"></i>
                        Correo del administrador
                    </label>
                    <div class="input-wrap">
                        <i class="icon fas fa-at"></i>
                        <input
                            type="email"
                            id="correo_admin"
                            name="correo_admin"
                            class="input <?php echo isset($errors['correo_admin']) ? 'error' : ''; ?>"
                            value="<?php echo htmlspecialchars($data['correo_admin'] ?? ''); ?>"
                            placeholder="admin@centro.edu.do"
                            required
                        >
                    </div>
                    <?php if (isset($errors['correo_admin'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['correo_admin']); ?></div>
                    <?php endif; ?>
                    <div class="hint">Este correo sera el usuario principal del panel administrativo del centro.</div>
                </div>

                <div class="form-group">
                    <div class="areas-panel">
                        <h3>Areas tecnicas del centro</h3>
                        <p>Por defecto vienen las 4 areas actuales del sistema, pero puedes quitar o agregar segun la escuela que estes registrando.</p>
                        <div class="areas-grid">
                            <?php foreach ($areasCatalog as $areaOption): ?>
                            <label class="area-option">
                                <input
                                    type="checkbox"
                                    name="areas_tecnicas[]"
                                    value="<?php echo htmlspecialchars($areaOption); ?>"
                                    <?php echo in_array($areaOption, $selectedAreas, true) ? 'checked' : ''; ?>
                                >
                                <span><?php echo htmlspecialchars($areaOption); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (isset($errors['areas_tecnicas'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['areas_tecnicas']); ?></div>
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
                        <span>Acepto los terminos y condiciones del sistema y confirmo que estoy autorizado para registrar este centro y su cuenta administradora.</span>
                    </label>
                    <?php if (isset($errors['accept_terms'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['accept_terms']); ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-building-circle-check"></i>
                    <span>Crear centro y administrador</span>
                </button>

                <div class="register-links">
                    <p>Si el centro ya existe, puedes entrar al sistema o registrar estudiantes usando su codigo.</p>
                    <div class="link-row">
                        <a href="index.php?page=login" class="link-btn primary">
                            <i class="fas fa-right-to-bracket"></i>
                            Iniciar sesion
                        </a>
                        <a href="index.php?page=register" class="link-btn secondary">
                            <i class="fas fa-user-graduate"></i>
                            Registro estudiante
                        </a>
                    </div>
                </div>
            </form>

            <div class="back-link" style="margin-top:-1%">
                <a href="../index.php">
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
            const centerNameInput = document.getElementById('nombre');
            const codePreview = document.getElementById('codigo_preview');
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('passwordInput');
            const confirmPasswordInput = document.getElementById('confirmPasswordInput');
            const form = document.getElementById('centerRegisterForm');
            const previewYear = <?php echo json_encode(date('Y')); ?>;

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

            function buildCodePreview(name) {
                const normalized = (name || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toUpperCase()
                    .replace(/[^A-Z0-9 ]/g, ' ')
                    .trim();

                if (!normalized) {
                    return 'SEGE-XXXX-' + previewYear;
                }

                const words = normalized.split(/\s+/).filter(Boolean);
                let prefix = '';

                words.forEach(function (word) {
                    if (prefix.length < 4 && word) {
                        prefix += word.charAt(0);
                    }
                });

                if (prefix.length < 4) {
                    const joined = words.join('');
                    prefix += joined.slice(prefix.length, 4);
                }

                prefix = prefix.replace(/[^A-Z0-9]/g, '').slice(0, 4);
                if (prefix.length < 3) {
                    prefix = prefix.padEnd(3, 'C');
                }

                return prefix + '-XXXX-' + previewYear;
            }

            function paintCodePreview() {
                if (!codePreview) {
                    return;
                }

                const preview = buildCodePreview(centerNameInput ? centerNameInput.value : '');
                codePreview.textContent = preview;
                codePreview.classList.toggle('preview-ready', !!(centerNameInput && centerNameInput.value.trim()));
            }

            function validatePasswords() {
                if (!passwordInput || !confirmPasswordInput) {
                    return true;
                }

                if (!passwordInput.value || !confirmPasswordInput.value) {
                    confirmPasswordInput.classList.remove('error');
                    return true;
                }

                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.classList.add('error');
                    return false;
                }

                confirmPasswordInput.classList.remove('error');
                return true;
            }

            wirePasswordToggle(togglePassword, passwordInput);
            wirePasswordToggle(toggleConfirmPassword, confirmPasswordInput);

            centerNameInput?.addEventListener('input', paintCodePreview);
            passwordInput?.addEventListener('input', validatePasswords);
            confirmPasswordInput?.addEventListener('input', validatePasswords);

            form?.addEventListener('submit', function (event) {
                const terms = document.getElementById('accept_terms');

                if (terms && !terms.checked) {
                    event.preventDefault();
                    alert('Debes aceptar los terminos y condiciones para continuar.');
                    terms.focus();
                    return;
                }

                if (!validatePasswords()) {
                    event.preventDefault();
                    alert('Las contrasenas no coinciden.');
                    confirmPasswordInput.focus();
                    return;
                }

                const submitBtn = form.querySelector('.submit-btn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Creando centro...</span>';
                    submitBtn.disabled = true;
                }
            });

            paintCodePreview();
        });
    </script>
</body>
</html>
<?php
unset($_SESSION['form_errors'], $_SESSION['form_data'], $_SESSION['created_center']);
?>
