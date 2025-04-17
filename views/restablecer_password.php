<?php
// restablecer_password.php
// Página para restablecer contraseña (dos pasos)

require_once '../auth.php';
if (isAuthenticated()) {
    header('Location: perfil.php');
    exit;
}

$paso = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$token = isset($_GET['token']) ? $_GET['token'] : '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <script src="../app.js"></script>
    <script src="../Validator.js"></script>
    <style>
    .contenedor-reset {
        max-width: 400px;
        margin: 0 auto;
        background-color: #fff;
        border-radius: var(--borde-radio);
        box-shadow: var(--sombra-media);
        padding: 1rem;
    }
    .contenedor-reset h2 {
        text-align: center;
    }
    #indicador_fuerza {
        height: 5px;
        background-color: #ccc;
        margin-top: 0.3rem;
    }
    </style>
</head>
<body>
<header class="cabecera-app">
    <div class="logo-app">
        <img src="https://via.placeholder.com/40" alt="Logo">
        <span>Mi App Comercial</span>
    </div>
</header>

<main class="contenedor-app">
    <div class="contenedor-reset">
        <?php if ($paso === 1): ?>
            <h2>Restablecer Contraseña</h2>
            <p>Ingresa tu email para enviarte un enlace de restablecimiento.</p>
            <form id="form_reset_1" method="POST" action="procesar_reset.php">
                <div class="grupo-campo">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <button type="submit" class="boton-guardar" style="width:100%; margin-top:1rem;">
                    Enviar Enlace
                </button>
            </form>
            <p style="text-align:center; margin-top:1rem;">
                <a href="login.php">Volver a Iniciar Sesión</a>
            </p>

        <?php elseif ($paso === 2 && $token !== ''): ?>
            <h2>Nueva Contraseña</h2>
            <?php
            // Validar token (ejemplo simulado). En la práctica, consulta DB.
            $tokenValido = true; // supongamos que es válido
            if (!$tokenValido): 
            ?>
                <p>El enlace es inválido o ha expirado.</p>
                <a href="restablecer_password.php">Solicitar nuevo enlace</a>
            <?php else: ?>
                <form id="form_reset_2" method="POST" action="procesar_reset.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="grupo-campo">
                        <label for="password">Nueva Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div id="indicador_fuerza"></div>
                    </div>
                    <div class="grupo-campo">
                        <label for="pass_confirm">Confirmar Contraseña</label>
                        <input type="password" id="pass_confirm" name="pass_confirm" class="form-control" required>
                    </div>
                    <button type="submit" class="boton-guardar" style="width:100%; margin-top:1rem;">
                        Guardar Contraseña
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <!-- Paso desconocido o falta token -->
            <p>Parámetros de solicitud inválidos.</p>
            <p><a href="restablecer_password.php">Regresar</a></p>
        <?php endif; ?>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
if (document.getElementById('password')) {
    const passInput = document.getElementById('password');
    const indicador = document.getElementById('indicador_fuerza');
    passInput.addEventListener('input', function() {
        let valor = passInput.value;
        let fuerza = 0;
        if (valor.length >= 8) fuerza++;
        if (/[A-Z]/.test(valor)) fuerza++;
        if (/\d/.test(valor)) fuerza++;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(valor)) fuerza++;
        switch (fuerza) {
            case 0:
            case 1: indicador.style.backgroundColor = 'red'; break;
            case 2: indicador.style.backgroundColor = 'orange'; break;
            case 3: indicador.style.backgroundColor = 'yellow'; break;
            case 4: indicador.style.backgroundColor = 'green'; break;
        }
    });

    // Confirmación de contraseña
    document.getElementById('form_reset_2').addEventListener('submit', function(e) {
        const confirm = document.getElementById('pass_confirm').value;
        if (passInput.value !== confirm) {
            alert('Las contraseñas no coinciden.');
            e.preventDefault();
        }
    });
}
</script>
</body>
</html>
