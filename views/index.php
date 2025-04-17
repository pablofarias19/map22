<?php
// admin/index.php
// Panel de administración: dashboard con estadísticas y gráficas

// -----------------------------------------------------------------------------
// Verificación de archivo de autenticación
$authPath = __DIR__ . '/../../api/auth.php';
if (!file_exists($authPath)) {
    die('Archivo de autenticación no encontrado');
}
require_once $authPath;

// Redirecciona si el usuario no es admin
if (!function_exists('isAdmin') || !isAdmin()) {
    header('Location: ../login.php?error=no_permisos');
    exit;
}

// -----------------------------------------------------------------------------
// 1. Obtener datos de estadísticas (reales o de prueba)
// -----------------------------------------------------------------------------
// Reemplazá por consultas reales a la base de datos si es necesario
$stats = [
    'usuarios'   => 120,
    'negocios'   => 45,
    'eventos'    => 20,
    'ofertas'    => 30,
    'encuestas'  => 10,
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>

    <link rel="stylesheet" href="../../css/estilos-mapa-comercial.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
    <script defer src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script defer src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --borde-radio: 0.75rem;
            --sombra-media: 0 2px 8px rgba(0,0,0,.12);
            --fondo-medio: #f5f7fb;
            --borde-color: #e1e7f0;
        }
        .admin-grid {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 1.25rem;
        }
        .admin-sidebar {
            background:#fff;
            border-radius:var(--borde-radio);
            box-shadow:var(--sombra-media);
            padding:1rem;
        }
        .admin-content {
            background:#fff;
            border-radius:var(--borde-radio);
            box-shadow:var(--sombra-media);
            padding:1rem;
        }
        .tarjetas-resumen{
            display:flex;
            gap:1rem;
            flex-wrap:wrap;
            margin-bottom:1.5rem;
        }
        .tarjeta{
            flex:1 1 120px;
            padding:1rem;
            border-radius:var(--borde-radio);
            text-align:center;
        }
        .tarjeta.usuarios   { background:#e0f7fa; }
        .tarjeta.negocios   { background:#f1f8e9; }
        .tarjeta.eventos    { background:#fff3e0; }
        .tarjeta.ofertas    { background:#ede7f6; }
        .tarjeta.encuestas  { background:#fce4ec; }
        .tarjeta h3{margin:0 0 0.25rem;font-size:1rem;}
        .tarjeta p{margin:0;font-size:1.5rem;font-weight:700;}
        @media (max-width:800px){
            .admin-grid{grid-template-columns:1fr;}
            .admin-sidebar{order:2;}
            .admin-content{order:1;}
        }
    </style>
</head>
<body>
<header class="cabecera-app">
    <div class="logo-app">
        <img src="../../images/logo.png" alt="Logo" width="40" height="40">
        <span>Admin · GeoComercial</span>
    </div>
</header>

<main class="contenedor-app" style="padding:1rem;">
    <div class="admin-grid">
        <aside class="admin-sidebar">
            <h3>Menú&nbsp;Admin</h3>
            <nav>
                <ul class="menu-nav" style="display:flex;flex-direction:column;gap:0.25rem;">
                    <li><a href="index.php" class="activa">Dashboard</a></li>
                    <li><a href="usuarios.php">Usuarios</a></li>
                    <li><a href="categorias.php">Categorías</a></li>
                    <li><a href="../perfil.php">Volver a Perfil</a></li>
                    <li><a href="../logout.php">Cerrar Sesión</a></li>
                </ul>
            </nav>
        </aside>

        <section class="admin-content">
            <h2>Dashboard</h2>

            <div class="tarjetas-resumen">
                <?php foreach ($stats as $k=>$v): ?>
                    <div class="tarjeta <?= htmlspecialchars($k) ?>">
                        <h3><?= ucfirst($k) ?></h3>
                        <p><?= (int)$v ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="max-width:600px;margin-bottom:1.5rem;">
                <canvas id="graficoActividad" width="600" height="300" aria-label="Usuarios nuevos" role="img"></canvas>
            </div>

            <h3>Últimos usuarios registrados</h3>
            <ul>
                <?php foreach($ultimosUsuarios as $u): ?>
                    <li><?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['fecha_registro']) ?>)</li>
                <?php endforeach; ?>
            </ul>

            <h3>Mapa de calor de actividad</h3>
            <div id="mapa_calor" style="width:100%;height:300px;background:#eaeff7;border-radius:var(--borde-radio);"></div>
        </section>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?= date('Y'); ?> · Panel de Administración GeoComercial</p>
</footer>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('graficoActividad').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Ene','Feb','Mar','Abr','May'],
            datasets: [{
                label: 'Usuarios nuevos',
                data: [5,15,7,20,13],
                borderColor: '#007bff',
                backgroundColor: 'transparent',
                tension: .3
            }]
        },
        options: { responsive:true, maintainAspectRatio:false }
    });

    const mapHeat = L.map('mapa_calor').setView([-34.603722, -58.381592], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(mapHeat);
    // TODO: agregar puntos reales al heatmap:
    // const heat = L.heatLayer([[lat, lng, intensidad], ...]).addTo(mapHeat);
});
</script>
</body>
</html>
