<?php
// detalles_encuesta.php
// Página con detalles completos de una encuesta, con formulario de respuesta si está activa

require_once '../../auth.php';
$idEncuesta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idEncuesta <= 0) {
    header('Location: mis_encuestas.php?error=ID_invalido');
    exit;
}

// Datos simulados
$encuesta = [
    'id'                => $idEncuesta,
    'titulo'            => 'Encuesta de Satisfacción',
    'descripcion'       => 'Queremos conocer tu opinión sobre nuestros servicios.',
    'fecha_inicio'      => '2025-01-01',
    'fecha_fin'         => '2025-01-10',
    'activa'            => true,
    'publica_resultados'=> true,
    'latitud'           => '-34.603722',
    'longitud'          => '-58.381592',
    'negocio'           => [
        'id'     => 2,
        'nombre' => 'Tienda de Ropa Elegante'
    ],
    'preguntas' => [
        [
            'id' => 101,
            'tipo' => 'opcion_multiple',
            'texto' => '¿Qué prenda te gustó más?',
            'opciones' => ['Camisas', 'Pantalones', 'Accesorios'],
            'obligatoria' => true
        ],
        [
            'id' => 102,
            'tipo' => 'texto_libre',
            'texto' => '¿Algún comentario adicional?',
            'opciones' => [],
            'obligatoria' => false
        ]
    ],
    // Simulación de resultados
    'resultados' => [
        101 => [ 'Camisas' => 10, 'Pantalones' => 15, 'Accesorios' => 5 ],
        102 => [ 'respuestas' => 8 ]
    ]
];

$metaTitle = $encuesta['titulo'] . ' - Encuesta';
$metaDesc  = substr($encuesta['descripcion'], 0, 150) . '...';
$metaURL   = "https://tusitio.com/detalles_encuesta.php?id={$idEncuesta}";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($metaURL); ?>">
    <meta property="og:type" content="article">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <!-- Chart.js para visualizar resultados -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <a href="mis_encuestas.php">Mis Encuestas</a> &gt; 
    <span><?php echo htmlspecialchars($encuesta['titulo']); ?></span>
</div>

<main class="contenedor-app" style="padding:1rem;">
    <h1><?php echo htmlspecialchars($encuesta['titulo']); ?></h1>
    <p><?php echo nl2br(htmlspecialchars($encuesta['descripcion'])); ?></p>
    <p>
        <strong>Fecha de inicio:</strong> <?php echo date('d/m/Y', strtotime($encuesta['fecha_inicio'])); ?><br>
        <strong>Fecha de fin:</strong> <?php echo date('d/m/Y', strtotime($encuesta['fecha_fin'])); ?>
    </p>

    <!-- Mapa de ubicación -->
    <?php if ( !empty($encuesta['latitud']) && !empty($encuesta['longitud']) ): ?>
    <section>
        <h2>Ubicación</h2>
        <div id="mapa_encuesta" style="width:100%; height:300px;"></div>
    </section>
    <?php endif; ?>

    <!-- Vinculado a negocio -->
    <?php if (!empty($encuesta['negocio'])): ?>
    <section>
        <h2>Vinculado al Negocio</h2>
        <p>
            <a href="detalles_negocio.php?id=<?php echo $encuesta['negocio']['id']; ?>">
                <?php echo htmlspecialchars($encuesta['negocio']['nombre']); ?>
            </a>
        </p>
    </section>
    <?php endif; ?>

    <!-- Formulario para responder (si está activa) -->
    <?php if ($encuesta['activa']): ?>
    <section>
        <h2>Responder Encuesta</h2>
        <form id="form_encuesta_respuesta">
            <?php echo generarPreguntasHTML($encuesta['preguntas']); ?>
            <button type="button" onclick="enviarRespuesta()">Enviar Respuesta</button>
        </form>
    </section>
    <?php else: ?>
        <p>Esta encuesta ya no está activa.</p>
    <?php endif; ?>

    <!-- Visualización de resultados (si publica_resultados o si el usuario tiene permiso) -->
    <?php if ($encuesta['publica_resultados'] && !empty($encuesta['resultados'])): ?>
    <section>
        <h2>Resultados</h2>
        <?php foreach($encuesta['preguntas'] as $preg): ?>
            <?php if (!empty($encuesta['resultados'][$preg['id']]) && $preg['tipo'] === 'opcion_multiple'): ?>
                <div style="margin-bottom:2rem;">
                    <h3><?php echo htmlspecialchars($preg['texto']); ?></h3>
                    <canvas id="chart_<?php echo $preg['id']; ?>" width="400" height="200"></canvas>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- Botón para compartir -->
    <section>
        <h2>Acciones</h2>
        <button onclick="compartirEncuesta()">Compartir</button>
    </section>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
// Mapa
<?php if (!empty($encuesta['latitud']) && !empty($encuesta['longitud'])): ?>
const mapEn = L.map('mapa_encuesta').setView([<?php echo $encuesta['latitud']; ?>, <?php echo $encuesta['longitud']; ?>], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}).addTo(mapEn);
L.marker([<?php echo $encuesta['latitud']; ?>, <?php echo $encuesta['longitud']; ?>]).addTo(mapEn);
<?php endif; ?>

function compartirEncuesta() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($encuesta['titulo']); ?>',
            text: 'Participa en esta encuesta.',
            url: '<?php echo addslashes($metaURL); ?>'
        });
    } else {
        alert('Compartir no soportado.');
    }
}

function enviarRespuesta() {
    alert('Guardando respuesta (ejemplo)...');
    // fetch() a tu backend para registrar la respuesta
}

// Renderizar gráficos con Chart.js
<?php if ($encuesta['publica_resultados'] && !empty($encuesta['resultados'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach($encuesta['preguntas'] as $preg): ?>
        <?php if ($preg['tipo'] === 'opcion_multiple' && !empty($encuesta['resultados'][$preg['id']])): 
            $dataRes = $encuesta['resultados'][$preg['id']];
            $labels = array_keys($dataRes);
            $values = array_values($dataRes);
        ?>
        new Chart(document.getElementById('chart_<?php echo $preg['id']; ?>'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Respuestas',
                    data: <?php echo json_encode($values); ?>,
                    backgroundColor: 'rgba(54,162,235,0.6)'
                }]
            },
            options: { responsive: true }
        });
        <?php endif; ?>
    <?php endforeach; ?>
});
<?php endif; ?>
</script>

<?php
// Mejoras directas en el archivo detalles_encuesta.php

// Generar preguntas directamente en PHP
function generarPreguntasHTML($preguntas) {
    $html = '';
    foreach ($preguntas as $index => $pregunta) {
        $numero = $index + 1;
        $html .= "<div class='pregunta-encuesta' data-id='{$pregunta['id']}' data-tipo='{$pregunta['tipo']}'>";
        $html .= "<div class='pregunta-cabecera'>";
        $html .= "<span class='pregunta-numero'>{$numero}</span>";
        $html .= "<h4 class='pregunta-texto'>{$pregunta['texto']}</h4>";
        if ($pregunta['obligatoria']) {
            $html .= "<span class='pregunta-obligatoria' title='Pregunta obligatoria'>*</span>";
        }
        $html .= "</div><div class='pregunta-contenido'>";

        switch ($pregunta['tipo']) {
            case 'opcion_multiple':
                $html .= generarContenidoOpcionMultiple($pregunta);
                break;
            case 'seleccion_unica':
                $html .= generarContenidoSeleccionUnica($pregunta);
                break;
            case 'texto_libre':
                $html .= generarContenidoTextoLibre($pregunta);
                break;
            case 'escala':
                $html .= generarContenidoEscala($pregunta);
                break;
            case 'si_no':
                $html .= generarContenidoSiNo($pregunta);
                break;
            default:
                $html .= "<p class='error-tipo-pregunta'>Tipo de pregunta no soportado: {$pregunta['tipo']}</p>";
        }

        $html .= "</div><div class='pregunta-error-mensaje' id='error-pregunta-{$pregunta['id']}' role='alert' aria-live='assertive'></div></div>";
    }
    return $html;
}

function generarContenidoOpcionMultiple($pregunta) {
    if (empty($pregunta['opciones'])) {
        return "<p class='sin-opciones'>No hay opciones disponibles para esta pregunta.</p>";
    }

    $html = "<div class='opciones-multiple' role='group'>";
    foreach ($pregunta['opciones'] as $opcion) {
        $inputId = "opcion_{$pregunta['id']}_{$opcion['id']}";
        $html .= "<div class='opcion-item'>";
        $html .= "<input type='checkbox' id='{$inputId}' name='pregunta_{$pregunta['id']}[]' value='{$opcion['id']}'>";
        $html .= "<label for='{$inputId}'>{$opcion['texto']}</label>";
        $html .= "</div>";
    }
    $html .= "</div>";
    return $html;
}

function generarContenidoSeleccionUnica($pregunta) {
    if (empty($pregunta['opciones'])) {
        return "<p class='sin-opciones'>No hay opciones disponibles para esta pregunta.</p>";
    }

    $html = "<div class='opciones-unica' role='radiogroup'>";
    foreach ($pregunta['opciones'] as $opcion) {
        $inputId = "opcion_{$pregunta['id']}_{$opcion['id']}";
        $html .= "<div class='opcion-item'>";
        $html .= "<input type='radio' id='{$inputId}' name='pregunta_{$pregunta['id']}' value='{$opcion['id']}'>";
        $html .= "<label for='{$inputId}'>{$opcion['texto']}</label>";
        $html .= "</div>";
    }
    $html .= "</div>";
    return $html;
}

function generarContenidoTextoLibre($pregunta) {
    $inputId = "texto_{$pregunta['id']}";
    return "<div class='opcion-texto-libre'>" .
           "<textarea id='{$inputId}' name='pregunta_{$pregunta['id']}' rows='3' placeholder='Escribe tu respuesta aquí...'></textarea>" .
           "</div>";
}

function generarContenidoEscala($pregunta) {
    $minValor = $pregunta['min_valor'] ?? 1;
    $maxValor = $pregunta['max_valor'] ?? 5;
    $etiquetaMin = $pregunta['etiqueta_min'] ?? 'Nada de acuerdo';
    $etiquetaMax = $pregunta['etiqueta_max'] ?? 'Totalmente de acuerdo';

    $html = "<div class='opcion-escala' role='radiogroup'>";
    $html .= "<div class='escala-etiquetas'>";
    $html .= "<span class='escala-etiqueta-min'>{$etiquetaMin}</span>";
    $html .= "<span class='escala-etiqueta-max'>{$etiquetaMax}</span>";
    $html .= "</div><div class='escala-valores'>";

    for ($i = $minValor; $i <= $maxValor; $i++) {
        $inputId = "escala_{$pregunta['id']}_{$i}";
        $html .= "<div class='escala-item'>";
        $html .= "<input type='radio' id='{$inputId}' name='pregunta_{$pregunta['id']}' value='{$i}'>";
        $html .= "<label for='{$inputId}'>{$i}</label>";
        $html .= "</div>";
    }

    $html .= "</div></div>";
    return $html;
}

function generarContenidoSiNo($pregunta) {
    $inputIdSi = "opcion_{$pregunta['id']}_si";
    $inputIdNo = "opcion_{$pregunta['id']}_no";
    return "<div class='opciones-si-no' role='radiogroup'>" .
           "<div class='opcion-item'>" .
           "<input type='radio' id='{$inputIdSi}' name='pregunta_{$pregunta['id']}' value='si'>" .
           "<label for='{$inputIdSi}'>Sí</label>" .
           "</div><div class='opcion-item'>" .
           "<input type='radio' id='{$inputIdNo}' name='pregunta_{$pregunta['id']}' value='no'>" .
           "<label for='{$inputIdNo}'>No</label>" .
           "</div></div>";
}

// Ejemplo de uso
$preguntas = [
    [
        'id' => 1,
        'tipo' => 'opcion_multiple',
        'texto' => '¿Qué colores prefieres?',
        'obligatoria' => true,
        'opciones' => [
            ['id' => 1, 'texto' => 'Rojo'],
            ['id' => 2, 'texto' => 'Azul'],
            ['id' => 3, 'texto' => 'Verde']
        ]
    ],
    [
        'id' => 2,
        'tipo' => 'texto_libre',
        'texto' => 'Describe tu color favorito.',
        'obligatoria' => false
    ]
];

// Generar el HTML de las preguntas
echo generarPreguntasHTML($preguntas);
?>
</body>
</html>
