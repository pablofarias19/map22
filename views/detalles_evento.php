<?php
// detalles_evento.php
// Página con detalles completos de un evento

require_once '../auth.php';
// Obtener ID del evento
$idEvento = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idEvento <= 0) {
    header('Location: mis_eventos.php?error=ID_invalido');
    exit;
}

// Simulación de datos
$evento = [
    'id'         => $idEvento,
    'titulo'     => 'Concierto de Rock',
    'categoria'  => '🎸 Música',
    'descripcion'=> 'Gran concierto al aire libre con bandas emergentes y artistas invitados.',
    'fecha_inicio' => '2025-05-20 21:00:00',
    'fecha_fin'    => '2025-05-21 01:00:00',
    'direccion'  => 'Estadio Central, Ciudad XYZ',
    'latitud'    => '-34.603722',
    'longitud'   => '-58.381592',
    'imagenes'   => [
        'https://via.placeholder.com/600x400?text=Evento+1',
        'https://via.placeholder.com/600x400?text=Evento+2',
    ],
    'video'      => 'https://www.youtube.com/watch?v=def456',
    'organizador'=> [
        'nombre' => 'Productora Musical X',
        'negocio_id' => 2 // ID de un negocio vinculado
    ]
];
$metaTitle = $evento['titulo'] . ' - Detalles';
$metaDesc  = substr($evento['descripcion'], 0, 150) . '...';
$metaURL   = "https://tusitio.com/detalles_evento.php?id={$idEvento}";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta tags SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($metaURL); ?>">
    <meta property="og:type" content="article">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script src="../app.js"></script>
</head>
<body>
<header class="cabecera-app">
    <div class="logo-app">
        <img src="https://via.placeholder.com/40" alt="Logo">
        <span>Mi App Comercial</span>
    </div>
</header>

<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <a href="index.php">Inicio</a> &gt; 
    <a href="mis_eventos.php">Mis Eventos</a> &gt; 
    <span><?php echo htmlspecialchars($evento['titulo']); ?></span>
</div>

<main class="contenedor-app" style="padding: 1rem;">
    <h1><?php echo htmlspecialchars($evento['titulo']); ?></h1>
    <p><strong>Categoría:</strong> <?php echo htmlspecialchars($evento['categoria']); ?></p>
    <p><?php echo nl2br(htmlspecialchars($evento['descripcion'])); ?></p>
    <p>
        <strong>Fecha/Hora inicio:</strong> 
        <?php echo date('d/m/Y H:i', strtotime($evento['fecha_inicio'])); ?><br>
        <strong>Fecha/Hora fin:</strong> 
        <?php echo date('d/m/Y H:i', strtotime($evento['fecha_fin'])); ?>
    </p>
    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($evento['direccion']); ?></p>

    <!-- Galería / Video -->
    <?php if (!empty($evento['video'])): ?>
    <section>
        <div class="detalles-video-container">
            <?php 
            function extraerYouTubeID($url) {
                preg_match('/(youtu\.be\/|v=)([^&]+)/', $url, $matches);
                return $matches[2] ?? '';
            }
            $videoId = extraerYouTubeID($evento['video']);
            ?>
            <iframe 
                src="https://www.youtube.com/embed/<?php echo $videoId; ?>?modestbranding=1&rel=0" 
                frameborder="0" 
                allowfullscreen>
            </iframe>
        </div>
    </section>
    <?php endif; ?>

    <section class="detalles-galeria">
        <?php foreach($evento['imagenes'] as $img): ?>
            <div class="detalles-imagen-item">
                <img src="<?php echo htmlspecialchars($img); ?>" alt="Imagen de <?php echo htmlspecialchars($evento['titulo']); ?>">
            </div>
        <?php endforeach; ?>
    </section>

    <!-- Mapa -->
    <section>
        <h2>Ubicación</h2>
        <div id="mapa_evento" style="width:100%; height: 300px;"></div>
    </section>

    <!-- Organizador -->
    <?php if (!empty($evento['organizador'])): ?>
    <section>
        <h3>Organizado por:</h3>
        <p><?php echo htmlspecialchars($evento['organizador']['nombre']); ?></p>
        <?php if ($evento['organizador']['negocio_id']): ?>
            <a href="detalles_negocio.php?id=<?php echo $evento['organizador']['negocio_id']; ?>">
                Ver negocio organizador
            </a>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Botones de acción -->
    <section>
        <h2>Acciones</h2>
        <button onclick="agregarCalendario()">Agregar a Calendario</button>
        <button onclick="comoLlegarEvento()">Cómo llegar</button>
        <button onclick="compartirEvento()">Compartir</button>
    </section>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
const mapEv = L.map('mapa_evento').setView([<?php echo $evento['latitud']; ?>, <?php echo $evento['longitud']; ?>], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(mapEv);
L.marker([<?php echo $evento['latitud']; ?>, <?php echo $evento['longitud']; ?>])
  .addTo(mapEv)
  .bindPopup("<?php echo addslashes($evento['titulo']); ?>");

function agregarCalendario() {
    alert('Ejemplo: crear archivo .ics o integrar API para calendarios...');
}
function comoLlegarEvento() {
    alert('Ejemplo: abrir ruta en Leaflet Routing Machine o Google Maps...');
}
function compartirEvento() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($evento['titulo']); ?>',
            text: 'No te pierdas este evento en Mi App Comercial.',
            url: '<?php echo addslashes($metaURL); ?>'
        });
    } else {
        alert('Compartir no soportado en este navegador.');
    }
}
</script>
</body>
</html>
