<?php
// app/views/auth/login.php

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/sistema_pasantias/';

// Configuración
$errors = $_SESSION['form_errors'] ?? [];
$data = $_SESSION['form_data'] ?? [];
$queryCenterCode = Security::sanitize($_GET['codigo_centro'] ?? '');
$queryEmail = Security::sanitize($_GET['correo'] ?? '');

if (empty($data['codigo_centro']) && $queryCenterCode !== '') {
    $data['codigo_centro'] = $queryCenterCode;
}

if (empty($data['correo']) && $queryEmail !== '') {
    $data['correo'] = $queryEmail;
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema EPIC</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../public/css/login.css">

</head>
<body>
    <!-- CONTENEDOR PRINCIPAL -->
    <div class="login-container">
        <!-- TARJETA DE LOGIN -->
        <div class="login-card">
            <!-- CABECERA -->
            <div class="login-header">
                <div class="logo-circle">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <h1>INICIAR SESIÓN</h1>
                <p class="login-subtitle">Accede a tu cuenta del sistema</p>
            </div>
            
            <!-- MENSAJES -->
            <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert-message alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?>">
                <i class="fas fa-<?php echo ($_SESSION['flash_type'] ?? 'info') === 'error' ? 'exclamation-circle' : 'info-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($_SESSION['flash_message']); ?></span>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>
            
            <!-- FORMULARIO -->
            <form action="index.php?page=login&action=process" method="POST" class="login-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <!-- Código del Centro -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-university"></i> CÓDIGO DEL CENTRO
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-key"></i>
                        <input type="text" 
                               name="codigo_centro" 
                               class="form-input <?php echo isset($errors['codigo_centro']) ? 'error' : ''; ?>"
                               value="<?php echo htmlspecialchars($data['codigo_centro'] ?? ''); ?>"
                               placeholder="Ej: JP2-6TO-2026"
                               required>
                    </div>
                    <?php if (isset($errors['codigo_centro'])): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($errors['codigo_centro']); ?>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 10px;">
                        <a href="index.php?page=center-register" class="forgot-link">
                            ¿No tienes código de centro? Registrarlo ahora
                        </a>
                    </div>
                </div>
                
                <!-- Correo Electrónico -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i> CORREO ELECTRÓNICO
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-at"></i>
                        <input type="email" 
                               name="correo" 
                               class="form-input <?php echo isset($errors['correo']) ? 'error' : ''; ?>"
                               value="<?php echo htmlspecialchars($data['correo'] ?? ''); ?>"
                               placeholder="ejemplo@correo.com"
                               required>
                    </div>
                    <?php if (isset($errors['correo'])): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($errors['correo']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Contraseña -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> CONTRASEÑA
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-key"></i>
                        <input type="password" 
                               name="password" 
                               class="form-input <?php echo isset($errors['password']) ? 'error' : ''; ?>"
                               placeholder="••••••••"
                               required
                               id="passwordInput">
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($errors['password']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
               <!-- Reemplaza esta sección en el HTML: -->
<div class="form-options">
    <label class="checkbox-container">
        <input type="checkbox" name="remember_me" id="remember_me">
        <span class="checkmark"></span>
        <span class="checkbox-text">Recordar mi sesión</span>
    </label>
    <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
</div>
                <!-- Botón Enviar -->
                <button type="submit" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>ACCEDER AL SISTEMA</span>
                </button>
            </form>
            
            <!-- Enlaces -->
            <div class="login-links">
                <a href="index.php?page=register" class="register-link">
                    <i class="fas fa-user-plus"></i>
                    <span>CREAR NUEVA CUENTA</span>
                </a>
            </div>
            
            <!-- Botón volver al inicio (texto simple) -->
            <div class="back-link">
    <a href="../index.php">
        <i class="fas fa-arrow-left"></i>
        Volver al inicio
    </a>
</div>
            
            <!-- Footer -->
            <div class="login-footer">
                <div class="system-name">EPIC V2.0</div>
                <p class="security-info">
                    <i class="fas fa-shield-alt"></i>
                    Sistema seguro - Encriptación de extremo a extremo
                </p>
                <p class="copyright">© <?php echo date('Y'); ?> Sistema de Gestión de Pasantías</p>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/ocultar contraseña
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('passwordInput');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.type === 'password' ? 'text' : 'password';
                    passwordInput.type = type;
                    this.innerHTML = type === 'password' ? 
                        '<i class="fas fa-eye"></i>' : 
                        '<i class="fas fa-eye-slash"></i>';
                });
            }
            
            // Validación en tiempo real
            const form = document.getElementById('loginForm');
            const inputs = form.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('error');
                    const errorMsg = this.parentElement.parentElement.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.style.display = 'none';
                    }
                });
                
                input.addEventListener('focus', function() {
                    this.parentElement.style.boxShadow = '0 6px 20px rgba(66, 153, 225, 0.2)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.08)';
                });
            });
            
            // Efecto hover en botones
            const buttons = document.querySelectorAll('.submit-btn, .register-link');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Efecto de carga en botón enviar
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('.submit-btn');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span> PROCESANDO...</span>';
                        submitBtn.disabled = true;
                    }
                });
            }
        });

        // Agrega este código al final del script existente

// Efecto de partículas para el logo
function createLogoParticles() {
    const logo = document.querySelector('.logo-circle');
    if (!logo) return;
    
    // Crear partículas alrededor del logo
    for (let i = 0; i < 8; i++) {
        const particle = document.createElement('div');
        particle.className = 'logo-particle';
        particle.style.cssText = `
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--accent-color);
            border-radius: 50%;
            opacity: 0;
            z-index: -1;
        `;
        logo.appendChild(particle);
    }
}

// Animar partículas al hover
document.addEventListener('DOMContentLoaded', function() {
    createLogoParticles();
    
    const logo = document.querySelector('.logo-circle');
    if (logo) {
        logo.addEventListener('mouseenter', function() {
            const particles = this.querySelectorAll('.logo-particle');
            particles.forEach((particle, index) => {
                const angle = (index / particles.length) * Math.PI * 2;
                const distance = 70;
                
                // Posicionar partículas en círculo
                particle.style.left = '50%';
                particle.style.top = '50%';
                particle.style.transform = 'translate(-50%, -50%)';
                particle.style.opacity = '1';
                
                // Animar hacia afuera
                setTimeout(() => {
                    particle.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    particle.style.transform = `translate(-50%, -50%) translate(${Math.cos(angle) * distance}px, ${Math.sin(angle) * distance}px)`;
                    particle.style.opacity = '0';
                }, index * 50);
            });
        });
        
        logo.addEventListener('mouseleave', function() {
            const particles = this.querySelectorAll('.logo-particle');
            particles.forEach(particle => {
                particle.style.transition = 'all 0.3s ease';
                particle.style.opacity = '0';
                particle.style.transform = 'translate(-50%, -50%)';
            });
        });
    }
});
    </script>
</body>
</html>
<?php
// Limpiar errores
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>
