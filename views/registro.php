<?php
// registro.php
// Formulario de registro de usuario

require_once '../auth.php';
if (isAuthenticated()) {
    header('Location: perfil.php');
    exit;
}

// Procesar envío (ejemplo simulado)
// if ($_SERVER['REQUEST_METHOD'] === 'POST') { ... }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cuenta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <script src="../app.js"></script>
    <script src="../Validator.js"></script>
    <style>
    .contenedor-registro {
        max-width: 450px;
        margin: 0 auto;
        background-color: #fff;
        border-radius: var(--borde-radio);
        box-shadow: var(--sombra-media);
        padding: 1rem;
    }
    .contenedor-registro h2 {
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
    <div class="contenedor-registro">
        <h2>Crear Cuenta</h2>
        <form id="form_registro" method="POST" action="procesar_registro.php">
            <div class="grupo-campo">
                <label for="nombre">Nombre Completo</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required>
            </div>
            <div class="grupo-campo">
                <label for="email">Email <small>(Se enviará un link de verificación)</small></label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="grupo-campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <div id="indicador_fuerza"></div>
            </div>
            <div class="grupo-campo">
                <label for="pass_confirm">Confirmar Contraseña</label>
                <input type="password" id="pass_confirm" name="pass_confirm" class="form-control" required>
            </div>
            <div class="grupo-campo">
                <label for="telefono">Teléfono (opcional)</label>
                <input type="tel" id="telefono" name="telefono" class="form-control">
            </div>
            <div>
                <label>
                    <input type="checkbox" id="acepto_terminos" name="acepto_terminos" required>
                    Acepto los <a href="terminos.php" target="_blank">Términos y Condiciones</a>
                </label>
            </div>

            <button type="submit" class="boton-guardar" style="width:100%; margin-top:1rem;">
                Registrarme
            </button>

            <p style="margin-top:1rem; text-align:center;">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
            </p>
        </form>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
// Indicador de fuerza de contraseña
const passInput = document.getElementById('password');
const indicadorFuerza = document.getElementById('indicador_fuerza');

passInput.addEventListener('input', function() {
    const valor = passInput.value;
    // Lógica simple de fuerza: longitud + caracteres especiales, etc.
    let fuerza = 0;
    if (valor.length >= 8) fuerza += 1;
    if (/[A-Z]/.test(valor)) fuerza += 1;
    if (/\d/.test(valor)) fuerza += 1;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(valor)) fuerza += 1;

    switch (fuerza) {
        case 0: 
        case 1: indicadorFuerza.style.backgroundColor = 'red'; break;
        case 2: indicadorFuerza.style.backgroundColor = 'orange'; break;
        case 3: indicadorFuerza.style.backgroundColor = 'yellow'; break;
        case 4: indicadorFuerza.style.backgroundColor = 'green'; break;
    }
});

// Validar confirmación
document.getElementById('form_registro').addEventListener('submit', function(e) {
    const pass = passInput.value;
    const confirm = document.getElementById('pass_confirm').value;
    if (pass !== confirm) {
        alert('Las contraseñas no coinciden.');
        e.preventDefault();
    }
});
</script>
</body>
</html>
