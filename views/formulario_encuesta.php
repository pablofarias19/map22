<?php
// formulario_encuesta.php
// Formulario para crear/editar encuestas

require_once '../auth.php'; 
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Detectar modo
$modoEdicion = false;
$encuestaId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($encuestaId > 0) {
    $modoEdicion = true;
    // Datos simulados
    $encuestaSimulada = [
        'id'              => $encuestaId,
        'titulo'          => 'Encuesta de Satisfacción',
        'descripcion'     => 'Queremos conocer tu opinión...',
        'fecha_inicio'    => '2025-01-01',
        'fecha_fin'       => '2025-01-15',
        'imagen_portada'  => '',
        'id_negocio'      => 2,
        'latitud'         => '',
        'longitud'        => '',
        'publica_resultados' => true,
        // Preguntas simuladas
        'preguntas' => [
            [
                'id' => 1,
                'tipo' => 'opcion_multiple',
                'texto' => '¿Qué productos te gustan más?',
                'opciones' => ['Producto A', 'Producto B', 'Producto C'],
                'obligatoria' => true
            ],
            [
                'id' => 2,
                'tipo' => 'texto_libre',
                'texto' => '¿Algún comentario adicional?',
                'obligatoria' => false
            ],
        ]
    ];
} else {
    // Creación
    $encuestaSimulada = [
        'id'               => 0,
        'titulo'           => '',
        'descripcion'      => '',
        'fecha_inicio'     => '',
        'fecha_fin'        => '',
        'imagen_portada'   => '',
        'id_negocio'       => '',
        'latitud'          => '',
        'longitud'         => '',
        'publica_resultados' => false,
        'preguntas'        => []
    ];
}

// Negocios del usuario
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
    <title><?php echo $modoEdicion ? 'Editar Encuesta' : 'Crear Encuesta'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <!-- (Opcional) Librería para arrastrar y soltar preguntas (ej. SortableJS) -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="../app.js"></script>
    <script src="../formularioEncuesta.js"></script>

    <style>
        .form-encuesta-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: var(--borde-radio);
            box-shadow: var(--sombra-media);
            padding: 1rem;
        }
        .grupo-campo { margin-bottom: 1rem; }
        .mapa-mini {
            width: 100%;
            height: 250px;
            border: 1px solid var(--borde-color);
            border-radius: var(--borde-radio);
        }
        .lista-preguntas {
            border: 1px solid var(--borde-color);
            padding: 0.5rem;
            border-radius: var(--borde-radio);
            margin-top: 1rem;
        }
        .pregunta-item {
            background-color: var(--fondo-claro);
            border: 1px solid var(--borde-color);
            padding: 0.5rem;
            border-radius: var(--borde-radio);
            margin-bottom: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .pregunta-item .acciones-pregunta {
            display: flex;
            gap: 0.5rem;
        }
        .pregunta-item .acciones-pregunta button {
            font-size: 0.9rem;
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
            <li><a href="mis_eventos.php">Mis Eventos</a></li>
            <li><a href="mis_ofertas.php">Mis Ofertas</a></li>
            <li><a href="mis_encuestas.php" class="activa">Mis Encuestas</a></li>
        </ul>
    </nav>
</header>

<main class="contenedor-app">
    <div class="form-encuesta-container">
        <h2><?php echo $modoEdicion ? 'Editar Encuesta' : 'Crear Encuesta'; ?></h2>

        <form id="form_encuesta" method="POST" action="procesar_encuesta.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $encuestaSimulada['id']; ?>">

            <!-- Título -->
            <div class="grupo-campo">
                <label for="titulo">Título de la encuesta <span style="color:red;">*</span></label>
                <input type="text" id="titulo" name="titulo" class="form-control" required
                       value="<?php echo htmlspecialchars($encuestaSimulada['titulo']); ?>">
            </div>

            <!-- Descripción -->
            <div class="grupo-campo">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="3"><?php 
                    echo htmlspecialchars($encuestaSimulada['descripcion']); 
                ?></textarea>
            </div>

            <!-- Fechas -->
            <div class="grupo-campo">
                <label for="fecha_inicio">Fecha de inicio <span style="color:red;">*</span></label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required
                       value="<?php echo htmlspecialchars($encuestaSimulada['fecha_inicio']); ?>">
            </div>
            <div class="grupo-campo">
                <label for="fecha_fin">Fecha de fin (opcional)</label>
                <input type="date" id="fecha_fin" name="fecha_fin" class="form-control"
                       value="<?php echo htmlspecialchars($encuestaSimulada['fecha_fin']); ?>">
            </div>

            <!-- Imagen portada -->
            <div class="grupo-campo">
                <label>Imagen de portada (opcional)</label>
                <input type="file" id="imagen_portada" name="imagen_portada" accept="image/*">
                <div id="preview_portada"></div>
            </div>

            <!-- Vincular negocio -->
            <div class="grupo-campo">
                <label for="id_negocio">Vincular con negocio</label>
                <select id="id_negocio" name="id_negocio" class="form-control">
                    <option value="">(Ninguno)</option>
                    <?php foreach($negociosUsuario as $neg): ?>
                        <option value="<?php echo $neg['id']; ?>"
                            <?php if($encuestaSimulada['id_negocio'] == $neg['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($neg['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ubicación mini-mapa si no se vincula negocio -->
            <div class="grupo-campo" id="bloque_ubicacion_map">
                <label>Ubicación en mapa</label>
                <div id="mapa_mini" class="mapa-mini"></div>
                <input type="hidden" id="latitud" name="latitud" value="<?php echo $encuestaSimulada['latitud']; ?>">
                <input type="hidden" id="longitud" name="longitud" value="<?php echo $encuestaSimulada['longitud']; ?>">
            </div>

            <!-- Resultados públicos -->
            <div class="grupo-campo">
                <label>
                    <input type="checkbox" id="publica_resultados" name="publica_resultados"
                        <?php if($encuestaSimulada['publica_resultados']) echo 'checked'; ?>>
                    Hacer públicos los resultados
                </label>
            </div>

            <!-- Preguntas dinámicas -->
            <div class="grupo-campo">
                <label>Preguntas de la encuesta</label>
                <div id="lista_preguntas" class="lista-preguntas">
                    <!-- Se genera dinámicamente -->
                </div>
                <button type="button" id="btn_add_pregunta" class="boton-secundario" style="margin-top:1rem;">
                    <i class="fa fa-plus"></i> Agregar Pregunta
                </button>
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
    // Inicializar mapa
    inicializarMapaEncuesta(
        'mapa_mini',
        parseFloat('<?php echo $encuestaSimulada['latitud'] ?: -34.603722; ?>'),
        parseFloat('<?php echo $encuestaSimulada['longitud'] ?: -58.381592; ?>')
    );

    // Mostrar/ocultar bloque de mapa según negocio
    const selectNegocio = document.getElementById('id_negocio');
    const bloqueMapa = document.getElementById('bloque_ubicacion_map');
    function checkNegocio() {
        if (selectNegocio.value === '') {
            bloqueMapa.style.display = 'block';
        } else {
            bloqueMapa.style.display = 'none';
        }
    }
    selectNegocio.addEventListener('change', checkNegocio);
    checkNegocio();

    // Previsualización de imagen portada
    const inputPortada = document.getElementById('imagen_portada');
    const previewPortada = document.getElementById('preview_portada');
    inputPortada.addEventListener('change', function() {
        previewPortada.innerHTML = '';
        if (inputPortada.files && inputPortada.files[0]) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(inputPortada.files[0]);
            img.style.width = '120px';
            img.style.height = '80px';
            img.style.objectFit = 'cover';
            img.style.border = '1px solid #ccc';
            img.style.borderRadius = '4px';
            previewPortada.appendChild(img);
        }
    });

    // Manejo de preguntas dinámicas
    // Se asume que en formularioEncuesta.js tendrás funciones para:
    // - renderizarPreguntas(encuestaSimulada['preguntas'])
    // - agregarPregunta
    // - editarPregunta
    // - eliminarPregunta
    // - reordenarPreguntas con SortableJS
    inicializarPreguntas(<?php echo json_encode($encuestaSimulada['preguntas']); ?>);

    // Botón agregar pregunta
    document.getElementById('btn_add_pregunta').addEventListener('click', function() {
        mostrarModalAgregarPregunta();
    });

    // Cancelar
    document.getElementById('btn_cancelar').addEventListener('click', function() {
        if (confirm('¿Cancelar los cambios?')) {
            window.location.href = 'mis_encuestas.php';
        }
    });

    // Vista previa
    document.getElementById('btn_vista_previa').addEventListener('click', function() {
        alert('Vista previa de la encuesta...');
        // Podrías abrir un modal o similar
    });

    // Validación
    const form = document.getElementById('form_encuesta');
    form.addEventListener('input', function() {
        validarFormularioEncuesta();
    });

    // Autoguardado
    iniciarAutoGuardadoEncuesta();

    // Validar al inicio
    validarFormularioEncuesta();
});
</script>
</body>
</html>
