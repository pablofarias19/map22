<?php
// verificar.php
// Página para verificar la cuenta de email del usuario

require_once '../auth.php';

// Simular lectura de token
$token = isset($_GET['token']) ? $_GET['token'] : '';
$verificado = false;
$tokenValido = false;
$reenviarPosible = true;

// Lógica simulada para verificar
if ($token !== '') {
    // En la práctica, consulta DB y actualiza estado
    if ($token === 'abc123') {
        $tokenValido = true;
        $verificado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificar Cuenta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <script src="../app.js"></script>
    <style>
    .contenedor-verificar {
        max-width: 500px;
        margin: 0 auto;
        background-color: #fff;
        border-radius: var(--borde-radio);
        box-shadow: var(--sombra-media);
        padding: 1rem;
        text-align: center;
    }
    .animacion-carga {
        width: 40px;
        height: 40px;
        border: 4px solid var(--fondo-medio);
        border-top: 4px solid var(--color-primario);
        border-radius: 50%;
        animation: girar 1s linear infinite;
        margin: 0 auto 1rem;
    }
    @keyframes girar {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
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
    <div class="contenedor-verificar">
        <div class="animacion-carga" id="loader"></div>
        <h2>Verificación de Cuenta</h2>
        <div id="mensaje-resultado">
            <?php if ($tokenValido && $verificado): ?>
                <p>¡Tu cuenta ha sido verificada con éxito!</p>
                <button onclick="window.location.href='login.php'">Iniciar Sesión</button>
            <?php elseif ($tokenValido && !$verificado): ?>
                <p>Procesando verificación... (ejemplo)</p>
            <?php else: ?>
                <p>El enlace de verificación es inválido o ha expirado.</p>
                <?php if ($reenviarPosible): ?>
                    <button onclick="reenviarVerificacion()">Reenviar correo de verificación</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simular una pequeña demora
    setTimeout(() => {
        document.getElementById('loader').style.display = 'none';
    }, 1500);
});

function reenviarVerificacion() {
    alert('Se reenvió el correo de verificación (simulado).');
    // fetch() a tu backend para reenviar
}
</script>
</body>
</html>
