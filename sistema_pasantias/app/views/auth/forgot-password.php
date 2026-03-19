<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$forgotPasswordMode = $forgotPasswordMode ?? 'request';
$formData = $formData ?? [];
$resetToken = $resetToken ?? '';
$resetRequest = $resetRequest ?? null;
$debugResetUrl = $debugResetUrl ?? null;

$formatDate = function ($value) {
    if (empty($value)) {
        return 'Sin fecha';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return 'Sin fecha';
    }

    return date('d/m/Y h:i A', $timestamp);
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contrasena - Sistema EPIC</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/login.css">
    <style>
        .helper-box,
        .debug-box {
            margin-bottom: 18px;
            padding: 16px 18px;
            border-radius: 16px;
            line-height: 1.6;
        }

        .helper-box {
            background: rgba(66, 153, 225, 0.12);
            border: 1px solid rgba(66, 153, 225, 0.18);
            color: #1a365d;
        }

        .debug-box {
            background: rgba(72, 187, 120, 0.12);
            border: 1px solid rgba(72, 187, 120, 0.18);
            color: #22543d;
        }

        .helper-box strong,
        .debug-box strong {
            display: block;
            margin-bottom: 6px;
        }

        .helper-box p,
        .debug-box p {
            margin: 0;
        }

        .debug-link {
            display: block;
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.82);
            color: #1a365d;
            font-size: 13px;
            word-break: break-all;
            text-decoration: none;
        }

        .debug-link:hover {
            text-decoration: underline;
        }

        .field-note {
            margin-top: 8px;
            color: #4a5568;
            font-size: 12px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-circle">
                    <i class="fas fa-key"></i>
                </div>
                <h1>RECUPERAR ACCESO</h1>
                <p class="login-subtitle">
                    <?php echo $forgotPasswordMode === 'reset'
                        ? 'Crea una nueva contrasena para volver a entrar al sistema'
                        : 'Solicita un enlace temporal para restablecer tu contrasena'; ?>
                </p>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert-message alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?>">
                <i class="fas fa-<?php echo ($_SESSION['flash_type'] ?? 'info') === 'error' ? 'exclamation-circle' : 'info-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($_SESSION['flash_message']); ?></span>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>

            <?php if ($forgotPasswordMode === 'reset' && $resetRequest): ?>
            <div class="helper-box">
                <strong>Enlace valido</strong>
                <p><?php echo htmlspecialchars($resetRequest['correo'] ?? 'Cuenta'); ?></p>
                <p><?php echo htmlspecialchars($resetRequest['centro_codigo'] ?? 'Centro no disponible'); ?></p>
                <p>Expira el <?php echo htmlspecialchars($formatDate($resetRequest['expires_at'] ?? null)); ?>.</p>
            </div>

            <form action="index.php?page=forgot-password" method="POST" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                <input type="hidden" name="intent" value="reset_password">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($resetToken); ?>">

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> NUEVA CONTRASENA
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-key"></i>
                        <input
                            type="password"
                            name="password"
                            class="form-input"
                            placeholder="Minimo 8 caracteres"
                            required>
                    </div>
                    <div class="field-note">Debe incluir al menos una mayuscula y un numero.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-shield-halved"></i> CONFIRMAR CONTRASENA
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-check"></i>
                        <input
                            type="password"
                            name="confirm_password"
                            class="form-input"
                            placeholder="Repite la nueva contrasena"
                            required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-rotate"></i>
                    <span>RESTABLECER CONTRASENA</span>
                </button>
            </form>
            <?php else: ?>
            <div class="helper-box">
                <strong>Como funciona</strong>
                <p>Indica el codigo del centro y tu correo. Si la cuenta existe, el sistema genera un enlace temporal para recuperar el acceso.</p>
            </div>

            <?php if (DEBUG_MODE && !empty($debugResetUrl['url'])): ?>
            <div class="debug-box">
                <strong>Modo debug activo</strong>
                <p>Como todavia no hay envio de correos, el enlace se muestra aqui para pruebas locales.</p>
                <a href="<?php echo htmlspecialchars($debugResetUrl['url']); ?>" class="debug-link"><?php echo htmlspecialchars($debugResetUrl['url']); ?></a>
                <p style="margin-top: 10px;">Expira el <?php echo htmlspecialchars($formatDate($debugResetUrl['expires_at'] ?? null)); ?>.</p>
            </div>
            <?php endif; ?>

            <form action="index.php?page=forgot-password" method="POST" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                <input type="hidden" name="intent" value="request_reset">

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-university"></i> CODIGO DEL CENTRO
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-key"></i>
                        <input
                            type="text"
                            name="codigo_centro"
                            class="form-input"
                            value="<?php echo htmlspecialchars($formData['codigo_centro'] ?? ''); ?>"
                            placeholder="Ej: JP2-6TO-2026"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i> CORREO ELECTRONICO
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-at"></i>
                        <input
                            type="email"
                            name="correo"
                            class="form-input"
                            value="<?php echo htmlspecialchars($formData['correo'] ?? ''); ?>"
                            placeholder="ejemplo@correo.com"
                            required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    <span>GENERAR ENLACE</span>
                </button>
            </form>
            <?php endif; ?>

            <div class="login-links">
                <a href="index.php?page=login" class="register-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>VOLVER AL LOGIN</span>
                </a>
            </div>

            <div class="login-footer">
                <div class="system-name">EPIC V2.0</div>
                <p class="security-info">
                    <i class="fas fa-shield-alt"></i>
                    Recuperacion segura por enlace temporal
                </p>
                <p class="copyright">&copy; <?php echo date('Y'); ?> Sistema de Gestion de Pasantias</p>
            </div>
        </div>
    </div>
</body>
</html>
