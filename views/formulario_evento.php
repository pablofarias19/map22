<?php
// formulario_evento.php
// Formulario para crear/editar un evento en la aplicación geoespacial comercial

require_once '../auth.php'; 
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// -----------------------------------------------------------------------------
// Detectar modo edición/creación
// -----------------------------------------------------------------------------
$modoEdicion = false;
$eventoId    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($eventoId > 0) {
    $modoEdicion = true;
    // Aquí cargarías los datos del evento desde tu DB o API
    // Ejemplo simulado:
    $eventoSimulado = [
        'id'          => $eventoId,
        'titulo'      => 'Concierto de Rock Simulado',
        'categoria'   => '🎸 Música',
        'descripcion' => 'Descripción de evento de rock...',
        'fecha_inicio'=> '2025-05-20T21:00',
        'fecha_fin'   => '2025-05-21T00:00',
        'direccion'   => 'Estadio Central',
        'latitud'     => '-34.603722',
        'longitud'    => '-58.381592',
        'precio'      => 500,
        'aforo'       => 2000,
        'imagen1'     => '', // URL o ruta a la imagen ya subida
        'imagen2'     => '',
        'link_video'  => 'https://www.youtube.com/watch?v=xyz',
        'id_negocio'  => 2, // Ejemplo de vínculo
    ];
} else {
    // Creación
    $eventoSimulado = [
        'id'          => 0,
        'titulo'      => '',
        'categoria'   => '',
        'descripcion' => '',
        'fecha_inicio'=> '',
        'fecha_fin'   => '',
        'direccion'   => '',
        'latitud'     => '',
        'longitud'    => '',
        'precio'      => '',
        'aforo'       => '',
        'imagen1'     => '',
        'imagen2'     => '',
        'link_video'  => '',
        'id_negocio'  => '',
    ];
}

// -----------------------------------------------------------------------------
// Categorías predefinidas con emojis (ajusta a tus necesidades)
// -----------------------------------------------------------------------------
$categoriasPredef = [
    '🎸 Música',
    '🏃‍♂️ Deportivo',
    '💻 Tecnológico',
    '🎨 Arte',
    '🎉 Fiesta',
    '🤝 Social'
];

// -----------------------------------------------------------------------------
// Negocios del usuario (para vincular). Cámbialos por consulta a DB o API
// -----------------------------------------------------------------------------
$negociosUsuario = [
    ['id' => 1, 'nombre' => 'Cafetería Delicias'],
    ['id' => 2, 'nombre' => 'Tienda de Ropa Elegante'],
    ['id' => 3, 'nombre' => 'Tech Solutions'],
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $modoEdicion ? 'Editar Evento' : 'Crear Evento'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Hoja de estilos principal -->
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <!-- Librerías para Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <!-- (Opcional) Editor básico: Summernote, TinyMCE, CKEditor, etc. (o uno propio) -->
    <!-- Ejemplo con Summernote (CDN) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.js"></script>
    <!-- FontAwesome para íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <!-- Script principal de la app (puede contener funciones globales) -->
    <script src="../app.js"></script>
    <!-- Script específico para formularios de evento -->
    <script src="../formularioEvento.js"></script>

    <style>
        /* Estilos adicionales específicos para este formulario */
        .form-evento-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 1rem;
            background-color: #fff;
            border-radius: var(--borde-radio);
            box-shadow: var(--sombra-media);
        }

        .form-cabecera {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .form-cabecera h2 {
            margin: 0;
        }

        .grupo-campo {
            margin-bottom: 1rem;
        }

        .grupo-campo label {
            font-weight: 500;
            display: block;
            margin-bottom: 0.3rem;
        }

        .campo-obligatorio {
            color: var(--color-error);
            margin-left: 0.25rem;
        }

        .mapa-mini {
            width: 100%;
            height: 300px;
            margin-top: 0.5rem;
            border: 1px solid var(--borde-color);
            border-radius: var(--borde-radio);
        }

        /* Previsualización de imágenes */
        .previsualizacion-imagen {
            display: flex;
            gap: 1rem;
        }
        .previsualizacion-imagen img {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border: 1px solid var(--borde-color);
            border-radius: var(--borde-radio);
        }

        .botones-form {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
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
            <li><a href="mis_eventos.php" class="activa">Mis Eventos</a></li>
            <li><a href="mis_ofertas.php">Mis Ofertas</a></li>
            <li><a href="mis_encuestas.php">Mis Encuestas</a></li>
        </ul>
    </nav>
</header>

<main class="contenedor-app">
    <div class="form-evento-container">
        <div class="form-cabecera">
            <h2><?php echo $modoEdicion ? 'Editar Evento' : 'Crear Evento'; ?></h2>
            <span id="estado-form"></span>
        </div>

        <form id="form_evento" enctype="multipart/form-data" method="POST" action="procesar_evento.php">
            <!-- ID oculto (para modo edición) -->
            <input type="hidden" name="id" id="id_evento" value="<?php echo $eventoSimulado['id']; ?>">

            <!-- Título del evento -->
            <div class="grupo-campo">
                <label for="titulo">Título del evento <span class="campo-obligatorio">*</span></label>
                <input type="text" id="titulo" name="titulo" class="form-control" 
                       required maxlength="200" 
                       value="<?php echo htmlspecialchars($eventoSimulado['titulo']); ?>">
                <small class="mensaje-error" id="error_titulo"></small>
            </div>

            <!-- Categoría -->
            <div class="grupo-campo">
                <label for="categoria">Categoría <span class="campo-obligatorio">*</span></label>
                <select id="categoria" name="categoria" class="form-control" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach($categoriasPredef as $cat): ?>
                        <option value="<?php echo $cat; ?>" 
                            <?php if($eventoSimulado['categoria'] === $cat) echo 'selected'; ?>>
                            <?php echo $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="mensaje-error" id="error_categoria"></small>
            </div>

            <!-- Descripción con editor -->
            <div class="grupo-campo">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="4"><?php 
                    echo htmlspecialchars($eventoSimulado['descripcion']); 
                ?></textarea>
            </div>

            <!-- Fechas y horas -->
            <div class="grupo-campo">
                <label for="fecha_inicio">Fecha y hora de inicio <span class="campo-obligatorio">*</span></label>
                <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" class="form-control"
                       required 
                       value="<?php echo $eventoSimulado['fecha_inicio']; ?>">
                <small class="mensaje-error" id="error_fecha_inicio"></small>
            </div>
            <div class="grupo-campo">
                <label for="fecha_fin">Fecha y hora de fin</label>
                <input type="datetime-local" id="fecha_fin" name="fecha_fin" class="form-control"
                       value="<?php echo $eventoSimulado['fecha_fin']; ?>">
                <small class="mensaje-error" id="error_fecha_fin"></small>
            </div>

            <!-- Dirección -->
            <div class="grupo-campo">
                <label for="direccion">Dirección <span class="campo-obligatorio">*</span></label>
                <input type="text" id="direccion" name="direccion" class="form-control" required
                       value="<?php echo htmlspecialchars($eventoSimulado['direccion']); ?>">
                <small class="mensaje-error" id="error_direccion"></small>
            </div>

            <!-- Mapa mini -->
            <div class="grupo-campo">
                <label>Seleccionar ubicación en mapa <span class="campo-obligatorio">*</span></label>
                <div id="mapa_mini" class="mapa-mini">Cargando mapa...</div>
                <input type="hidden" id="latitud" name="latitud" value="<?php echo $eventoSimulado['latitud']; ?>">
                <input type="hidden" id="longitud" name="longitud" value="<?php echo $eventoSimulado['longitud']; ?>">
                <small class="mensaje-error" id="error_ubicacion"></small>
            </div>

            <!-- Precio/costo (con opción "Gratis") -->
            <div class="grupo-campo">
                <label for="precio">Precio/costo</label>
                <div>
                    <input type="number" step="0.01" min="0" id="precio" name="precio" class="form-control" 
                           placeholder="0.00" style="max-width: 200px; display:inline-block;"
                           value="<?php echo htmlspecialchars($eventoSimulado['precio']); ?>">
                    <input type="checkbox" id="es_gratis" name="es_gratis" 
                        <?php if($eventoSimulado['precio'] === '0' || $eventoSimulado['precio'] === 0) echo 'checked'; ?>>
                    <label for="es_gratis">Gratis</label>
                </div>
            </div>

            <!-- Aforo/capacidad -->
            <div class="grupo-campo">
                <label for="aforo">Aforo/Capacidad</label>
                <input type="number" min="0" id="aforo" name="aforo" class="form-control"
                       value="<?php echo htmlspecialchars($eventoSimulado['aforo']); ?>">
            </div>

            <!-- Subida de imágenes (hasta 2) -->
            <div class="grupo-campo">
                <label>Subir imágenes (máx. 2)</label>
                <input type="file" id="imagen1" name="imagen1" accept="image/*">
                <input type="file" id="imagen2" name="imagen2" accept="image/*">
                <div class="previsualizacion-imagen" id="preview_imagenes">
                    <!-- Se llenará por JS con <img> -->
                </div>
            </div>

            <!-- Enlace de video (opcional, validación YouTube) -->
            <div class="grupo-campo">
                <label for="link_video">Enlace de video (YouTube)</label>
                <input type="url" id="link_video" name="link_video" class="form-control"
                       value="<?php echo htmlspecialchars($eventoSimulado['link_video']); ?>">
                <small class="mensaje-error" id="error_link_video"></small>
            </div>

            <!-- Vincular con negocio existente -->
            <div class="grupo-campo">
                <label for="id_negocio">Vincular con negocio</label>
                <select id="id_negocio" name="id_negocio" class="form-control">
                    <option value="">(Ninguno)</option>
                    <?php foreach($negociosUsuario as $neg): ?>
                        <option value="<?php echo $neg['id']; ?>"
                            <?php if($eventoSimulado['id_negocio'] == $neg['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($neg['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Botones -->
            <div class="botones-form">
                <button type="button" id="btn_cancelar" class="boton-cancelar">
                    <i class="fa fa-times"></i> Cancelar
                </button>
                <button type="button" id="btn_vista_previa" class="boton-secundario">
                    <i class="fa fa-eye"></i> Vista Previa
                </button>
                <button type="submit" id="btn_guardar" class="boton-guardar">
                    <i class="fa fa-save"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar editor de descripción (ej. Summernote)
    $('#descripcion').summernote({
        placeholder: 'Describe tu evento...',
        tabsize: 2,
        height: 100
    });

    // Inicializar mapa mini
    inicializarMapaMini(
        'mapa_mini', 
        parseFloat('<?php echo $eventoSimulado['latitud'] ?: -34.603722; ?>'),
        parseFloat('<?php echo $eventoSimulado['longitud'] ?: -58.381592; ?>')
    );

    // Manejo de check "gratis"
    const precioInput = document.getElementById('precio');
    const checkGratis = document.getElementById('es_gratis');
    checkGratis.addEventListener('change', () => {
        if (checkGratis.checked) {
            precioInput.value = '';
            precioInput.disabled = true;
        } else {
            precioInput.disabled = false;
        }
    });
    if (checkGratis.checked) {
        precioInput.disabled = true;
    }

    // Previsualización de imágenes
    const inputImg1 = document.getElementById('imagen1');
    const inputImg2 = document.getElementById('imagen2');
    const previewContainer = document.getElementById('preview_imagenes');

    [inputImg1, inputImg2].forEach(input => {
        input.addEventListener('change', (e) => {
            previewContainer.innerHTML = '';
            [inputImg1.files[0], inputImg2.files[0]].forEach(file => {
                if (!file) return;
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                previewContainer.appendChild(img);
            });
        });
    });

    // Validaciones en tiempo real
    const form = document.getElementById('form_evento');
    form.addEventListener('input', function(e) {
        validarFormularioEvento();
    });

    // Vista previa
    document.getElementById('btn_vista_previa').addEventListener('click', function() {
        alert('Mostrando vista previa del evento...');
        // Lógica para abrir modal o ventana emergente con previsualización
    });

    // Cancelar
    document.getElementById('btn_cancelar').addEventListener('click', function() {
        if (confirm('¿Deseas cancelar? Se perderán los cambios no guardados.')) {
            window.location.href = 'mis_eventos.php';
        }
    });

    // Autoguardado con localStorage (ejemplo)
    iniciarAutoGuardadoEvento();

    // Validar al cargar
    validarFormularioEvento();
});
</script>
</body>
</html>
