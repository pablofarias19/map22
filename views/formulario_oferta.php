<?php
// formulario_oferta.php
// Formulario para crear/editar una oferta

require_once '../auth.php'; 
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Detectar modo edición vs. creación
$modoEdicion = false;
$ofertaId    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ofertaId > 0) {
    $modoEdicion = true;
    // Cargar datos simulados
    $ofertaSimulada = [
        'id'             => $ofertaId,
        'titulo'         => 'Descuento en Ropa de Verano',
        'descripcion'    => 'Aprovecha nuestro super descuento...',
        'precio_normal'  => '1000',
        'precio_oferta'  => '750',
        'fecha_inicio'   => '2025-06-01',
        'fecha_fin'      => '2025-06-15',
        'disponibilidad' => 50,
        'imagen'         => '',
        'id_negocio'     => 2,
        'id_producto'    => '', // Suponiendo que aún no tiene producto
        'latitud'        => '-34.603722',
        'longitud'       => '-58.381592',
    ];
} else {
    // Creación
    $ofertaSimulada = [
        'id'             => 0,
        'titulo'         => '',
        'descripcion'    => '',
        'precio_normal'  => '',
        'precio_oferta'  => '',
        'fecha_inicio'   => '',
        'fecha_fin'      => '',
        'disponibilidad' => '',
        'imagen'         => '',
        'id_negocio'     => '',
        'id_producto'    => '',
        'latitud'        => '',
        'longitud'       => '',
    ];
}

// Negocios del usuario
$negociosUsuario = [
    ['id' => 1, 'nombre' => 'Cafetería Delicias'],
    ['id' => 2, 'nombre' => 'Tienda de Ropa Elegante'],
    ['id' => 3, 'nombre' => 'Tech Solutions'],
];

// Productos de ejemplo, dependerá del negocio seleccionado (lógica real en JS)
$productosSimulados = [
    // Para negocio ID=2 (Tienda de Ropa Elegante), por ejemplo
    [ 'id' => 1, 'nombre' => 'Camisa de verano', 'id_negocio' => 2 ],
    [ 'id' => 2, 'nombre' => 'Pantalón casual', 'id_negocio' => 2 ],
    // ... etc.
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $modoEdicion ? 'Editar Oferta' : 'Crear Oferta'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="../app.js"></script>
    <script src="../formularioOferta.js"></script>

    <style>
        .form-oferta-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 1rem;
            background-color: #fff;
            border-radius: var(--borde-radio);
            box-shadow: var(--sombra-media);
        }
        .grupo-campo {
            margin-bottom: 1rem;
        }
        .mapa-mini {
            width: 100%;
            height: 300px;
            margin-top: 0.5rem;
            border: 1px solid var(--borde-color);
            border-radius: var(--borde-radio);
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
            <li><a href="mis_eventos.php">Mis Eventos</a></li>
            <li><a href="mis_ofertas.php" class="activa">Mis Ofertas</a></li>
            <li><a href="mis_encuestas.php">Mis Encuestas</a></li>
        </ul>
    </nav>
</header>

<main class="contenedor-app">
    <div class="form-oferta-container">
        <h2><?php echo $modoEdicion ? 'Editar Oferta' : 'Crear Oferta'; ?></h2>

        <form id="form_oferta" method="POST" action="procesar_oferta.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $ofertaSimulada['id']; ?>">

            <!-- Título -->
            <div class="grupo-campo">
                <label for="titulo">Título de la oferta <span style="color:red;">*</span></label>
                <input type="text" id="titulo" name="titulo" class="form-control" required
                       value="<?php echo htmlspecialchars($ofertaSimulada['titulo']); ?>">
                <small class="mensaje-error" id="error_titulo"></small>
            </div>

            <!-- Descripción -->
            <div class="grupo-campo">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="4"><?php 
                    echo htmlspecialchars($ofertaSimulada['descripcion']); 
                ?></textarea>
            </div>

            <!-- Precio normal y oferta -->
            <div class="grupo-campo">
                <label>Precio normal (requerido)</label>
                <input type="number" id="precio_normal" name="precio_normal" class="form-control" required step="0.01"
                       value="<?php echo htmlspecialchars($ofertaSimulada['precio_normal']); ?>">

                <label>Precio oferta (requerido)</label>
                <input type="number" id="precio_oferta" name="precio_oferta" class="form-control" required step="0.01"
                       value="<?php echo htmlspecialchars($ofertaSimulada['precio_oferta']); ?>">

                <label>Descuento (%)</label>
                <input type="number" id="descuento" name="descuento" class="form-control" step="0.01" readonly
                       placeholder="Auto-cálculo o manual">
            </div>

            <!-- Fechas de inicio/fin -->
            <div class="grupo-campo">
                <label for="fecha_inicio">Fecha de inicio <span style="color:red;">*</span></label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required
                       value="<?php echo htmlspecialchars($ofertaSimulada['fecha_inicio']); ?>">

                <label for="fecha_fin">Fecha de fin <span style="color:red;">*</span></label>
                <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required
                       value="<?php echo htmlspecialchars($ofertaSimulada['fecha_fin']); ?>">
            </div>

            <!-- Disponibilidad/stock -->
            <div class="grupo-campo">
                <label for="disponibilidad">Disponibilidad/stock</label>
                <input type="number" id="disponibilidad" name="disponibilidad" class="form-control"
                       value="<?php echo htmlspecialchars($ofertaSimulada['disponibilidad']); ?>">
            </div>

            <!-- Imagen principal -->
            <div class="grupo-campo">
                <label>Subir imagen (opcional)</label>
                <input type="file" id="imagen" name="imagen" accept="image/*">
                <div class="previsualizacion-imagen" id="preview_imagen"></div>
            </div>

            <!-- Vincular con negocio y producto -->
            <div class="grupo-campo">
                <label for="id_negocio">Vincular con negocio</label>
                <select id="id_negocio" name="id_negocio" class="form-control">
                    <option value="">(Ninguno)</option>
                    <?php foreach($negociosUsuario as $neg): ?>
                        <option value="<?php echo $neg['id']; ?>"
                            <?php if($ofertaSimulada['id_negocio'] == $neg['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($neg['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grupo-campo">
                <label for="id_producto">Vincular con producto del negocio</label>
                <select id="id_producto" name="id_producto" class="form-control">
                    <option value="">(Ninguno)</option>
                    <?php foreach($productosSimulados as $prod): 
                        if ($prod['id_negocio'] === $ofertaSimulada['id_negocio']): ?>
                            <option value="<?php echo $prod['id']; ?>"
                                <?php if($ofertaSimulada['id_producto'] == $prod['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($prod['nombre']); ?>
                            </option>
                        <?php endif; 
                    endforeach; ?>
                </select>
            </div>

            <!-- Ubicación manual si NO se vincula a negocio -->
            <div class="grupo-campo" id="bloque_ubicacion_manual">
                <label>Ubicación en mapa (si no se vincula a un negocio) </label>
                <div id="mapa_mini" class="mapa-mini">Cargando mapa...</div>
                <input type="hidden" id="latitud" name="latitud" value="<?php echo $ofertaSimulada['latitud']; ?>">
                <input type="hidden" id="longitud" name="longitud" value="<?php echo $ofertaSimulada['longitud']; ?>">
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
    // Inicializar mapa si no hay negocio seleccionado
    inicializarMapaOferta(
        'mapa_mini',
        parseFloat('<?php echo $ofertaSimulada['latitud'] ?: -34.603722; ?>'),
        parseFloat('<?php echo $ofertaSimulada['longitud'] ?: -58.381592; ?>')
    );

    // Mostrar/ocultar bloque ubicación según el negocio
    const selectNegocio = document.getElementById('id_negocio');
    const bloqueUbicacion = document.getElementById('bloque_ubicacion_manual');
    function actualizarVisibilidadUbicacion() {
        if (selectNegocio.value === '') {
            bloqueUbicacion.style.display = 'block';
        } else {
            bloqueUbicacion.style.display = 'none';
        }
    }
    actualizarVisibilidadUbicacion();
    selectNegocio.addEventListener('change', () => {
        actualizarVisibilidadUbicacion();
        // También recargar la lista de productos correspondiente al negocio
        // Ejemplo: fetch a la API => popular #id_producto
    });

    // Cálculo automático de descuento
    const precioNormalInput = document.getElementById('precio_normal');
    const precioOfertaInput = document.getElementById('precio_oferta');
    const descuentoInput = document.getElementById('descuento');

    function recalcularDescuento() {
        const pn = parseFloat(precioNormalInput.value) || 0;
        const po = parseFloat(precioOfertaInput.value) || 0;
        if (pn > 0 && po > 0 && po < pn) {
            const desc = ((pn - po) / pn) * 100;
            descuentoInput.value = desc.toFixed(2);
        } else {
            descuentoInput.value = '';
        }
    }
    precioNormalInput.addEventListener('input', recalcularDescuento);
    precioOfertaInput.addEventListener('input', recalcularDescuento);

    // Previsualización de imagen
    const inputImagen = document.getElementById('imagen');
    const previewImagen = document.getElementById('preview_imagen');
    inputImagen.addEventListener('change', function() {
        previewImagen.innerHTML = '';
        if (inputImagen.files && inputImagen.files[0]) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(inputImagen.files[0]);
            previewImagen.appendChild(img);
        }
    });

    // Cancelar
    document.getElementById('btn_cancelar').addEventListener('click', function() {
        if (confirm('¿Deseas cancelar? Se perderán los cambios no guardados.')) {
            window.location.href = 'mis_ofertas.php';
        }
    });

    // Vista previa
    document.getElementById('btn_vista_previa').addEventListener('click', function() {
        alert('Mostrando vista previa de la oferta...');
    });

    // Validaciones en tiempo real (ejemplo)
    const form = document.getElementById('form_oferta');
    form.addEventListener('input', function(e) {
        validarFormularioOferta();
    });

    // Autoguardado
    iniciarAutoGuardadoOferta();

    // Validar al inicio
    validarFormularioOferta();
});
</script>
</body>
</html>
