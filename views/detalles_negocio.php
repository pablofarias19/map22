<?php
// detalles_negocio.php
// Muestra la información detallada de un negocio, tomando un ID desde la URL

require_once '../auth.php'; // Ajusta la ruta de tu archivo de autenticación
// Validar y obtener ID
$idNegocio = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idNegocio <= 0) {
    // Manejo de error: ID inválido
    header('Location: mis_negocios.php?error=ID_invalido');
    exit;
}

// Ejemplo de verificación de autenticación (si deseas mostrar diferente contenido a usuarios logueados)
$usuarioLogueado = isAuthenticated();

// Simulación de carga de datos desde BD o API
$negocio = [
    'id'            => $idNegocio,
    'nombre'        => 'Tienda de Ropa Elegante',
    'categoria'     => 'Moda',
    'descripcion'   => 'La mejor selección de prendas para todas las ocasiones.',
    'direccion'     => 'Calle Principal 123, Ciudad XYZ',
    'telefono'      => '+54 9 11 1234-5678',
    'email'         => 'contacto@tiendaropa.com',
    'sitio_web'     => 'https://tiendaropa.com',
    'latitud'       => '-34.603722',
    'longitud'      => '-58.381592',
    'imagenes'      => [
        'https://via.placeholder.com/600x400?text=Imagen+1',
        'https://via.placeholder.com/600x400?text=Imagen+2',
    ],
    'video'         => 'https://www.youtube.com/watch?v=abc123',
    'productos'     => [
        ['nombre' => 'Camisa Formal', 'marca' => 'MarcaX', 'precio' => 2500],
        ['nombre' => 'Pantalón Casual', 'marca' => 'MarcaY', 'precio' => 3000],
    ],
    'ofertas'       => [
        [
            'titulo'         => 'Descuento de Invierno',
            'precio_normal'  => 1000,
            'precio_oferta'  => 800,
            'fecha_inicio'   => '2025-06-01',
            'fecha_fin'      => '2025-06-15',
        ]
    ],
    'categoria_especial' => 'inmobiliaria', // O '' si no es inmobiliaria
    'propiedades'   => [
        ['id' => 1, 'titulo' => 'Departamento en venta', 'precio' => 50000],
        ['id' => 2, 'titulo' => 'Local comercial en alquiler', 'precio' => 7000],
    ],
    'zona_influencia_geojson' => '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[...]},"properties":{}}',
    'relaciones'    => [
        ['id' => 99, 'nombre' => 'Proveedor de telas'],
        ['id' => 88, 'nombre' => 'Distribuidor XY'],
    ],
];
if (!$negocio) {
    // Manejo de error: negocio no encontrado
    header('Location: mis_negocios.php?error=no_encontrado');
    exit;
}

// Metadata para SEO y compartir
$metaTitle = $negocio['nombre'] . ' - Detalles';
$metaDesc  = substr($negocio['descripcion'], 0, 150) . '...'; // ejemplo
$metaURL   = "https://tusitio.com/detalles_negocio.php?id={$idNegocio}"; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta tags para SEO y redes sociales -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($metaURL); ?>">
    <meta property="og:type" content="article">
    <!-- Estilos principales -->
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <!-- JS global -->
    <script src="../app.js"></script>
    <script src="../ventanasDetalles.js"></script>
</head>
<body>
<header class="cabecera-app">
    <div class="logo-app">
        <img src="https://via.placeholder.com/40" alt="Logo">
        <span>Mi App Comercial</span>
    </div>
    <nav class="nav-app">
        <ul class="menu-nav">
            <li><a href="index.php">Inicio</a></li>
            <li><a href="mis_negocios.php">Mis Negocios</a></li>
            <li><a href="mis_eventos.php">Mis Eventos</a></li>
        </ul>
    </nav>
</header>

<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <a href="index.php">Inicio</a> &gt; 
    <a href="mis_negocios.php">Mis Negocios</a> &gt; 
    <span><?php echo htmlspecialchars($negocio['nombre']); ?></span>
</div>

<main class="contenedor-app" style="padding: 1rem;">
    <h1><?php echo htmlspecialchars($negocio['nombre']); ?></h1>
    <p><strong>Categoría:</strong> <?php echo htmlspecialchars($negocio['categoria']); ?></p>
    <p><?php echo nl2br(htmlspecialchars($negocio['descripcion'])); ?></p>

    <!-- Galería de imágenes / Video -->
    <section class="galeria-negocio">
        <?php if (!empty($negocio['video'])): ?>
            <div class="detalles-video-container">
                <!-- Asumimos que tienes una función que extrae el ID de YouTube -->
                <?php 
                // Ejemplo rápido de extracción de ID
                function extraerYouTubeID($url) {
                    preg_match('/(youtu\.be\/|v=)([^&]+)/', $url, $matches);
                    return $matches[2] ?? '';
                }
                $videoId = extraerYouTubeID($negocio['video']);
                ?>
                <iframe 
                    src="https://www.youtube.com/embed/<?php echo $videoId; ?>?modestbranding=1&rel=0" 
                    frameborder="0" allowfullscreen>
                </iframe>
            </div>
        <?php endif; ?>
        <div class="detalles-galeria">
            <?php foreach($negocio['imagenes'] as $img): ?>
                <div class="detalles-imagen-item">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="Imagen de <?php echo htmlspecialchars($negocio['nombre']); ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Información de contacto -->
    <section class="info-contacto">
        <h2>Contacto</h2>
        <p><strong>Dirección:</strong> <?php echo htmlspecialchars($negocio['direccion']); ?></p>
        <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($negocio['telefono']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($negocio['email']); ?></p>
        <?php if (!empty($negocio['sitio_web'])): ?>
            <p><strong>Sitio web:</strong> 
                <a href="<?php echo htmlspecialchars($negocio['sitio_web']); ?>" target="_blank">
                    <?php echo htmlspecialchars($negocio['sitio_web']); ?>
                </a>
            </p>
        <?php endif; ?>
    </section>

    <!-- Mapa de ubicación -->
    <section>
        <h2>Ubicación</h2>
        <div id="mapa_negocio" style="width: 100%; height: 300px; background-color: #eee;"></div>
    </section>

    <!-- Listado de productos -->
    <?php if (!empty($negocio['productos'])): ?>
    <section>
        <h2>Productos disponibles</h2>
        <ul>
            <?php foreach($negocio['productos'] as $prod): ?>
                <li>
                    <?php echo htmlspecialchars($prod['nombre']); ?> (<?php echo htmlspecialchars($prod['marca']); ?>) 
                    - $<?php echo number_format($prod['precio'], 2); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- Ofertas vigentes -->
    <?php if (!empty($negocio['ofertas'])): ?>
    <section>
        <h2>Ofertas vigentes</h2>
        <ul>
            <?php foreach($negocio['ofertas'] as $oferta): ?>
                <li>
                    <strong><?php echo htmlspecialchars($oferta['titulo']); ?></strong> 
                    (<?php echo date('d/m/Y', strtotime($oferta['fecha_inicio'])); ?> - 
                     <?php echo date('d/m/Y', strtotime($oferta['fecha_fin'])); ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- Sección especial para inmobiliarias -->
    <?php if ($negocio['categoria_especial'] === 'inmobiliaria' && !empty($negocio['propiedades'])): ?>
    <section>
        <h2>Propiedades disponibles</h2>
        <!-- Filtros de ejemplo (ficticio) -->
        <form>
            <label>Filtrar por precio máximo:</label>
            <input type="number" name="max_precio">
            <button type="submit">Aplicar</button>
        </form>
        <ul>
            <?php foreach($negocio['propiedades'] as $prop): ?>
                <li><?php echo htmlspecialchars($prop['titulo']); ?> - 
                    $<?php echo number_format($prop['precio'], 2); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- Visualización de zona de influencia (si existe) -->
    <?php if (!empty($negocio['zona_influencia_geojson'])): ?>
    <section>
        <h2>Zona de Influencia</h2>
        <p>A continuación se muestra la zona de influencia aproximada:</p>
        <div id="mapa_zona_influencia" style="width: 100%; height: 300px; background-color: #eee;"></div>
    </section>
    <?php endif; ?>

    <!-- Relaciones con otros negocios -->
    <?php if (!empty($negocio['relaciones'])): ?>
    <section>
        <h2>Relaciones Comerciales</h2>
        <ul>
            <?php foreach($negocio['relaciones'] as $rel): ?>
                <li><?php echo htmlspecialchars($rel['nombre']); ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- Botones de acción -->
    <section>
        <h2>Acciones</h2>
        <button onclick="contactarNegocio()">Contactar</button>
        <button onclick="comoLlegar()">Cómo llegar</button>
        <button onclick="compartirNegocio()">Compartir</button>
    </section>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
// Inicializar mapa principal
const mapa = L.map('mapa_negocio').setView([<?php echo $negocio['latitud']; ?>, <?php echo $negocio['longitud']; ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(mapa);
L.marker([<?php echo $negocio['latitud']; ?>, <?php echo $negocio['longitud']; ?>])
  .addTo(mapa)
  .bindPopup("<?php echo htmlspecialchars($negocio['nombre']); ?>");

// Zona de influencia
<?php if (!empty($negocio['zona_influencia_geojson'])): ?>
const geojsonData = <?php echo $negocio['zona_influencia_geojson']; ?>;
const mapaZona = L.map('mapa_zona_influencia').setView([<?php echo $negocio['latitud']; ?>, <?php echo $negocio['longitud']; ?>], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(mapaZona);
L.geoJSON(geojsonData).addTo(mapaZona);
<?php endif; ?>

// Funciones para botones
function contactarNegocio() {
    alert("Abriendo formulario de contacto (ejemplo)...");
    // Lógica real: abrir modal o redirigir a una página de contacto
}

function comoLlegar() {
    alert("Abrir mapa con indicaciones (ejemplo)...");
    // Podrías integrar con la API de Google Maps o Leaflet Routing Machine
}

function compartirNegocio() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($negocio['nombre']); ?>',
            text: '¡Mira este negocio en Mi App Comercial!',
            url: '<?php echo addslashes($metaURL); ?>'
        }).then(() => console.log('Compartido con éxito'))
          .catch(err => console.error('Error al compartir', err));
    } else {
        alert('Tu navegador no soporta la API de compartir.');
    }
}
</script>
</body>
</html>
