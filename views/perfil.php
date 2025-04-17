<?php
// perfil.php
// Página de perfil de usuario con edición de datos, cambio de contraseña, estadísticas, notificaciones, etc.

require_once '../auth.php'; // O tu archivo de autenticación real
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Ejemplo simulado de datos del usuario actual
$usuario = [
    'nombre'   => 'Juan Pérez',
    'email'    => 'juan.perez@example.com',
    'telefono' => '+54 9 11 1234-5678'
];

// Ejemplo de estadísticas (en la práctica, consulta tu BD o API)
$estadisticas = [
    'negocios' => 3,
    'eventos'  => 2,
    'ofertas'  => 5,
    'encuestas'=> 1,
];

// Historial de inicios de sesión (simulado)
$historialLogins = [
    ['fecha' => '2025-01-01 10:00', 'ip' => '192.168.1.10'],
    ['fecha' => '2025-01-03 14:30', 'ip' => '190.245.32.11'],
    // ...
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <!-- Librería para gráficos (ejemplo Chart.js) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- FontAwesome (opcional) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- JS principal y validaciones -->
    <script src="../app.js"></script>
    <script src="../Validator.js"></script>
    <style>
    .contenedor-perfil {
        max-width: 900px;
        margin: 0 auto;
        background-color: #fff;
        padding: 1rem;
        border-radius: var(--borde-radio);
        box-shadow: var(--sombra-media);
    }
    .tabs {
        display: flex;
        margin-bottom: 1rem;
        border-bottom: 1px solid var(--borde-color);
    }
    .tab-item {
        padding: 0.5rem 1rem;
        margin-right: 0.5rem;
        cursor: pointer;
    }
    .tab-item.activa {
        border-bottom: 3px solid var(--color-primario);
        font-weight: 500;
    }
    .tab-contenido {
        display: none;
    }
    .tab-contenido.activo {
        display: block;
    }
    /* Panel de usuario */
    .panel-usuario {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    /* Historial de logins */
    .tabla-historial {
        width: 100%;
        border-collapse: collapse;
    }
    .tabla-historial th, .tabla-historial td {
        padding: 0.5rem;
        border: 1px solid var(--borde-color);
    }
    .contenedor-grafico {
        max-width: 600px;
        margin: 1rem auto;
    }
    </style>
</head>
<body>
<header class="cabecera-app">
    <div class="logo-app">
        <img src="https://via.placeholder.com/40" alt="Logo">
        <span>Mi App Comercial</span>
    </div>
    <nav class="nav-app">
        <ul class="menu-nav">
            <li><a href="#">Inicio</a></li>
            <li><a href="mis_negocios.php">Mis Negocios</a></li>
            <li><a href="mis_eventos.php">Mis Eventos</a></li>
            <li><a href="mis_ofertas.php">Mis Ofertas</a></li>
            <li><a href="mis_encuestas.php">Mis Encuestas</a></li>
            <li><a href="perfil.php" class="activa">Perfil</a></li>
        </ul>
    </nav>
</header>

<main class="contenedor-app">
    <div class="contenedor-perfil">
        <h2>Perfil de Usuario</h2>

        <!-- Tabs -->
        <div class="tabs" id="tabsPerfil">
            <div class="tab-item activa" data-tab="tab-info">Información</div>
            <div class="tab-item" data-tab="tab-seguridad">Seguridad</div>
            <div class="tab-item" data-tab="tab-estadisticas">Estadísticas</div>
            <div class="tab-item" data-tab="tab-notificaciones">Notificaciones</div>
            <div class="tab-item" data-tab="tab-privacidad">Privacidad</div>
            <div class="tab-item" data-tab="tab-historial">Historial de Inicios</div>
        </div>

        <!-- Contenido tabs -->
        <!-- 1. Información personal -->
        <div class="tab-contenido activo" id="tab-info">
            <form id="form_info_personal">
                <div class="panel-usuario">
                    <div>
                        <label>Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-control"
                               value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" id="email" name="email" class="form-control" disabled
                               value="<?php echo htmlspecialchars($usuario['email']); ?>">
                    </div>
                    <div>
                        <label>Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control"
                               value="<?php echo htmlspecialchars($usuario['telefono']); ?>">
                    </div>
                    <div style="grid-column: span 2;">
                        <button type="button" class="boton-guardar" onclick="guardarInfoPersonal()">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 2. Seguridad (cambio de contraseña, desactivar cuenta) -->
        <div class="tab-contenido" id="tab-seguridad">
            <form id="form_cambio_pass">
                <h3>Cambio de contraseña</h3>
                <label>Contraseña actual</label>
                <input type="password" id="pass_actual" name="pass_actual" class="form-control" required>

                <label>Nueva contraseña</label>
                <input type="password" id="pass_nueva" name="pass_nueva" class="form-control" required>
                <div id="indicador_seguridad"></div>

                <label>Confirmar nueva contraseña</label>
                <input type="password" id="pass_confirm" name="pass_confirm" class="form-control" required>

                <button type="button" class="boton-guardar" onclick="cambiarContrasena()">
                    Guardar Contraseña
                </button>
            </form>

            <hr>

            <h3>Desactivar cuenta</h3>
            <p>Si desactivas tu cuenta, no podrás acceder hasta reactivarla. ¿Estás seguro?</p>
            <button class="boton-cancelar" onclick="desactivarCuenta()">Desactivar Cuenta</button>
        </div>

        <!-- 3. Estadísticas de actividad -->
        <div class="tab-contenido" id="tab-estadisticas">
            <h3>Estadísticas de Actividad</h3>
            <p>Negocios: <?php echo $estadisticas['negocios']; ?></p>
            <p>Eventos: <?php echo $estadisticas['eventos']; ?></p>
            <p>Ofertas: <?php echo $estadisticas['ofertas']; ?></p>
            <p>Encuestas: <?php echo $estadisticas['encuestas']; ?></p>

            <div class="contenedor-grafico">
                <canvas id="graficoActividad"></canvas>
            </div>
        </div>

        <!-- 4. Notificaciones -->
        <div class="tab-contenido" id="tab-notificaciones">
            <h3>Opciones de Notificación</h3>
            <form id="form_notificaciones">
                <label>
                    <input type="checkbox" name="notif_negocios" checked>
                    Notificar cambios en mis Negocios
                </label>
                <br>
                <label>
                    <input type="checkbox" name="notif_eventos">
                    Notificar recordatorios de Eventos
                </label>
                <br>
                <label>
                    <input type="checkbox" name="notif_ofertas" checked>
                    Notificar ofertas y descuentos
                </label>
                <br>
                <label>
                    <input type="checkbox" name="notif_encuestas">
                    Notificar participaciones en encuestas
                </label>
                <br>
                <button type="button" class="boton-guardar" onclick="guardarNotificaciones()">
                    Guardar Preferencias
                </button>
            </form>
        </div>

        <!-- 5. Privacidad -->
        <div class="tab-contenido" id="tab-privacidad">
            <h3>Opciones de Privacidad</h3>
            <p>Configura quién puede ver tus datos en la plataforma.</p>
            <form id="form_privacidad">
                <label>
                    <input type="checkbox" name="mostrar_email" checked>
                    Mostrar mi email a otros usuarios
                </label>
                <br>
                <label>
                    <input type="checkbox" name="mostrar_telefono">
                    Mostrar mi teléfono a otros usuarios
                </label>
                <br>
                <button type="button" class="boton-guardar" onclick="guardarPrivacidad()">
                    Guardar Configuración
                </button>
            </form>
        </div>

        <!-- 6. Historial de inicios de sesión -->
        <div class="tab-contenido" id="tab-historial">
            <h3>Historial de Inicios de Sesión</h3>
            <table class="tabla-historial">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Dirección IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historialLogins as $login): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($login['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($login['ip']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejo de tabs
    const tabItems = document.querySelectorAll('.tab-item');
    const tabContents = document.querySelectorAll('.tab-contenido');
    tabItems.forEach(item => {
        item.addEventListener('click', () => {
            tabItems.forEach(i => i.classList.remove('activa'));
            item.classList.add('activa');
            const target = item.getAttribute('data-tab');
            tabContents.forEach(c => {
                if (c.id === target) c.classList.add('activo');
                else c.classList.remove('activo');
            });
        });
    });

    // Gráfico de ejemplo con Chart.js en "Estadísticas"
    const ctx = document.getElementById('graficoActividad').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Negocios', 'Eventos', 'Ofertas', 'Encuestas'],
            datasets: [{
                label: 'Cantidad',
                data: [
                    <?php echo $estadisticas['negocios']; ?>,
                    <?php echo $estadisticas['eventos']; ?>,
                    <?php echo $estadisticas['ofertas']; ?>,
                    <?php echo $estadisticas['encuestas']; ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });
});

// Funciones de ejemplo
function guardarInfoPersonal() {
    alert('Guardando información personal...');
    // Aquí harías fetch o AJAX a tu backend
}

function cambiarContrasena() {
    alert('Guardando nueva contraseña...');
    // Validaciones de contraseñas, confirmaciones, etc.
}

function desactivarCuenta() {
    if (confirm('¿Seguro que deseas desactivar tu cuenta?')) {
        alert('Cuenta desactivada (simulado).');
        // fetch a backend => redireccionar, etc.
    }
}

function guardarNotificaciones() {
    alert('Guardando preferencias de notificaciones...');
}

function guardarPrivacidad() {
    alert('Guardando configuraciones de privacidad...');
}
</script>
</body>
</html>
