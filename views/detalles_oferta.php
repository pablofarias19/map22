<?php
// detalles_oferta.php
// Página con detalles completos de una oferta

require_once '../auth.php';
$idOferta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idOferta <= 0) {
    header('Location: mis_ofertas.php?error=ID_invalido');
    exit;
}

// Datos simulados
$oferta = [
    'id'             => $idOferta,
    'titulo'         => 'Descuento en Ropa de Verano',
    'descripcion'    => 'Aprovecha nuestro super descuento en prendas de temporada...',
    'precio_normal'  => 1000,
    'precio_oferta'  => 750,
    'fecha_inicio'   => '2025-06-01',
    'fecha_fin'      => '2025-06-15',
    'disponibilidad' => 50,
    'imagen'         => 'https://via.placeholder.com/600x400?text=Oferta+Verano',
    'latitud'        => '-34.603722',
    'longitud'       => '-58.381592',
    'negocio'        => [
        'id'     => 2,
        'nombre' => 'Tienda de Ropa Elegante'
    ],
    'producto'       => [
        'nombre' => 'Camisa de verano',
        'marca'  => 'MarcaX'
    ]
];
$metaTitle = $oferta['titulo'] . ' - Oferta';
$metaDesc  = substr($oferta['descripcion'], 0, 150) . '...';
$metaURL   = "https://tusitio.com/detalles_oferta.php?id={$idOferta}";

// Calcular tiempo restante con JavaScript (cuenta regresiva)
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
    <a href="mis_ofertas.php">Mis Ofertas</a> &gt; 
    <span><?php echo htmlspecialchars($oferta['titulo']); ?></span>
</div>

<main class="contenedor-app" style="padding: 1rem;">
    <h1><?php echo htmlspecialchars($oferta['titulo']); ?></h1>
    <p><?php echo nl2br(htmlspecialchars($oferta['descripcion'])); ?></p>
    <div>
        <strong>Precio normal:</strong> 
        $<?php echo number_format($oferta['precio_normal'], 2); ?><br>
        <strong>Precio oferta:</strong> 
        $<?php echo number_format($oferta['precio_oferta'], 2); ?><br>
        <strong>Descuento:</strong>
        <?php
        $desc = 0;
        if ($oferta['precio_normal'] > 0) {
            $desc = round(100 - (($oferta['precio_oferta'] / $oferta['precio_normal']) * 100));
        }
        echo $desc . '%';
        ?>
    </div>
    <p>
        <strong>Vigencia:</strong> 
        <?php echo date('d/m/Y', strtotime($oferta['fecha_inicio'])); ?> -
        <?php echo date('d/m/Y', strtotime($oferta['fecha_fin'])); ?>
    </p>
    <p id="cuenta_regresiva"></p>
    <p><strong>Disponibilidad:</strong> <?php echo (int)$oferta['disponibilidad']; ?> unidades</p>

    <section>
        <h2>Imagen de la Oferta</h2>
        <img src="<?php echo htmlspecialchars($oferta['imagen']); ?>" 
             alt="Imagen de <?php echo htmlspecialchars($oferta['titulo']); ?>" 
             style="max-width: 100%; height: auto;">
    </section>

    <!-- Mapa -->
    <section>
        <h2>Ubicación</h2>
        <div id="mapa_oferta" style="width: 100%; height: 300px;"></div>
    </section>

    <!-- Información del negocio -->
    <?php if (!empty($oferta['negocio'])): ?>
    <section>
        <h2>Información del Negocio</h2>
        <p><a href="detalles_negocio.php?id=<?php echo $oferta['negocio']['id']; ?>">
            <?php echo htmlspecialchars($oferta['negocio']['nombre']); ?>
        </a></p>
    </section>
    <?php endif; ?>

    <!-- Detalles del producto -->
    <?php if (!empty($oferta['producto'])): ?>
    <section>
        <h2>Producto</h2>
        <p>
            <?php echo htmlspecialchars($oferta['producto']['nombre']); ?> 
            (<?php echo htmlspecialchars($oferta['producto']['marca']); ?>)
        </p>
    </section>
    <?php endif; ?>

    <!-- Botones de acción -->
    <section>
        <h2>Acciones</h2>
        <button onclick="contactarOferta()">Contactar</button>
        <button onclick="comoLlegarOferta()">Cómo llegar</button>
        <button onclick="compartirOferta()">Compartir</button>
    </section>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
const mapOf = L.map('mapa_oferta').setView([<?php echo $oferta['latitud']; ?>, <?php echo $oferta['longitud']; ?>], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}).addTo(mapOf);
L.marker([<?php echo $oferta['latitud']; ?>, <?php echo $oferta['longitud']; ?>]).addTo(mapOf);

function contactarOferta() {
    alert('Formulario de contacto al negocio...');
}
function comoLlegarOferta() {
    alert('Ruta en Google Maps / Leaflet...');
}
function compartirOferta() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($oferta['titulo']); ?>',
            text: 'Revisa esta oferta disponible ahora mismo.',
            url: '<?php echo addslashes($metaURL); ?>'
        });
    } else {
        alert('Compartir no soportado.');
    }
}

// Cuenta regresiva
(function(){
    const fechaFin = new Date('<?php echo $oferta['fecha_fin']; ?>T23:59:59');
    const cuentaElem = document.getElementById('cuenta_regresiva');

    function actualizarCuentaRegresiva() {
        const ahora = new Date();
        const dif = fechaFin - ahora;
        if (dif <= 0) {
            cuentaElem.textContent = 'Oferta finalizada';
            return;
        }
        const dias = Math.floor(dif/(1000*60*60*24));
        const horas = Math.floor((dif%(1000*60*60*24))/(1000*60*60));
        const minutos = Math.floor((dif%(1000*60*60))/(1000*60));
        cuentaElem.textContent = `Tiempo restante: ${dias}d ${horas}h ${minutos}m`;
    }

    actualizarCuentaRegresiva();
    setInterval(actualizarCuentaRegresiva, 60000); // Actualiza cada minuto
})();
</script>
</body>
</html>
