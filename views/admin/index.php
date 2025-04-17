<?php
/**
 * admin/index.php – Panel de administración
 * Última revisión automática: 2025-04-16 19:04:01
 *
 * NOTA:
 *  • Esta versión asume la siguiente estructura:
 *        /admin/index.php
 *        /auth.php
 *        /controllers/DashboardController.php  (opc.)
 *  • Los arrays simulados ($stats, $ultimosUsuarios) se mantienen como *dummy*
 *    para no romper la vista. Sustitúyelos por tus controladores/consultas.
 */

declare(strict_types=1);

// --- Autenticación --------------------------------------------------------
require_once __DIR__ . '/../../utils/Autenticacion.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Autenticacion($db);

if (!$auth->isAdmin()) {
    header('Location: ../login.php?error=no_permisos');
    exit;
}

// --- Datos de ejemplo (reemplaza por tu modelo / consulta) ----------------
$stats = [
    'usuarios'   => 120,
    'negocios'   => 45,
    'eventos'    => 20,
    'ofertas'    => 30,
    'encuestas'  => 10
];
$ultimosUsuarios = [
    ['id' => 201, 'nombre' => 'Carlos Reyes', 'fecha_registro' => '2025-01-01'],
    ['id' => 202, 'nombre' => 'María López', 'fecha_registro' => '2025-01-02'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Estilos globales -->
    <link rel="stylesheet" href="../assets/css/estilos-mapa-comercial.css">
    <!-- Leaflet (opcional para mapa de calor) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<header class="cabecera-app">
    <div class="logo-app">
        <img src="../assets/images/logo.png" alt="Logo" width="40" height="40">
        <span>Admin · GeoComercial</span>
    </div>
</header>

<main class="contenedor-app" style="padding:1rem;">
    <div class="admin-container">
        <!-- Panel lateral -->
        <aside class="admin-sidebar">
            <h3>Menú Admin</h3>
            <nav>
                <ul>
                    <li><a class="activa" href="index.php">Dashboard</a></li>
                    <li><a href="usuarios.php">Usuarios</a></li>
                    <li><a href="categorias.php">Categorías</a></li>
                    <li><a href="../perfil.php">Perfil</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Contenido -->
        <section class="admin-content">
            <h2>Resumen</h2>

            <div class="tarjetas-resumen">
                <?php foreach ($stats as $k => $v): ?>
                    <div class="tarjeta">
                        <h3><?= ucfirst($k) ?></h3>
                        <p><?= (int)$v ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3>Tendencia de usuarios</h3>
            <canvas id="graficoActividad" style="max-width:600px;"></canvas>

            <script>
                const ctx = document.getElementById('graficoActividad');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May'],
                        datasets: [{
                            label: 'Usuarios nuevos',
                            data: [5, 15, 7, 20, 13],
                            borderWidth: 2,
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            </script>

            <h3>Últimos usuarios</h3>
            <ul>
                <?php foreach ($ultimosUsuarios as $u): ?>
                    <li><?= htmlspecialchars($u['nombre']) ?> (<?= $u['fecha_registro'] ?>)</li>
                <?php endforeach; ?>
            </ul>

            <h3>Mapa de Calor de Actividad</h3>
            <div id="mapa_calor" style="width:100%; height:300px;"></div>
            <script type="module">
                const map = L.map('mapa_calor').setView([-34.6, -58.38], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                // TODO: agrega Leaflet.heat y alimenta con datos desde tu API
            </script>
        </section>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?= date('Y') ?> · GeoComercial</p>
</footer>
</body>
</html>
