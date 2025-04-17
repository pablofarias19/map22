<?php
// mis_negocios.php
// Vista para mostrar el listado de negocios del usuario autenticado

// -----------------------------------------------------------------------------
// 1. Comprobación de autenticación
//    Asume que tienes un archivo auth.php o similar con una función isAuthenticated()
//    y que manejas la sesión o tokens. Ajusta según tu lógica de autenticación real.
// -----------------------------------------------------------------------------
require_once '../auth.php'; // Ajusta la ruta si es necesario

if (!isAuthenticated()) {
    header('Location: ../login.php'); // Redirige si no está logueado
    exit;
}

// -----------------------------------------------------------------------------
// (Opcional) Lógica de obtención de datos:
// Aquí puedes obtener los negocios desde tu base de datos o un endpoint de la API.
// Si usas una API, harías algo como fetch o cURL para obtener la lista de negocios.
// A modo de ejemplo, supongamos que tenemos un arreglo $negocios con los datos.
// -----------------------------------------------------------------------------

// Parámetros de búsqueda, filtrado y paginación
// Podrías aplicar validación/limpieza real de estos valores.
$busqueda    = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$orden       = isset($_GET['orden'])    ? $_GET['orden']         : 'nombre_asc';
$page        = isset($_GET['page'])     ? (int)$_GET['page']     : 1;
$porPagina   = 6; // Cantidad de negocios por página
$offset      = ($page - 1) * $porPagina;

// Carga de datos de ejemplo (REEMPLAZA con tu lógica real)
$negociosSimulados = [
    [
        'id'          => 1,
        'nombre'      => 'Tienda de Ropa Elegante',
        'categoria'   => 'Moda',
        'direccion'   => 'Calle Principal 123',
        'imagen'      => 'https://via.placeholder.com/300x200?text=Negocio+1',
        'descripcion' => 'Las mejores prendas de la temporada.'
    ],
    [
        'id'          => 2,
        'nombre'      => 'Cafetería Delicias',
        'categoria'   => 'Restaurante',
        'direccion'   => 'Avenida Cafetal 456',
        'imagen'      => 'https://via.placeholder.com/300x200?text=Negocio+2',
        'descripcion' => 'Café de especialidad y repostería casera.'
    ],
    [
        'id'          => 3,
        'nombre'      => 'Tech Solutions',
        'categoria'   => 'Tecnología',
        'direccion'   => 'Boulevard Innovación 789',
        'imagen'      => 'https://via.placeholder.com/300x200?text=Negocio+3',
        'descripcion' => 'Servicios de informática y consultoría.'
    ],
    // ... Agrega más negocios simulados si deseas
];

// Simulación de filtrado por búsqueda
if ($busqueda !== '') {
    $negociosSimulados = array_filter($negociosSimulados, function($neg) use ($busqueda) {
        return (stripos($neg['nombre'], $busqueda) !== false) 
            || (stripos($neg['categoria'], $busqueda) !== false)
            || (stripos($neg['direccion'], $busqueda) !== false);
    });
}

// Simulación de ordenamiento simple
switch ($orden) {
    case 'nombre_asc':
        usort($negociosSimulados, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        break;
    case 'nombre_desc':
        usort($negociosSimulados, fn($a, $b) => strcmp($b['nombre'], $a['nombre']));
        break;
    // Puedes añadir más criterios de orden si quieres (por categoría, fecha, etc.)
}

// Conteo total y paginación simulada
$totalNegocios = count($negociosSimulados);
$negociosSimulados = array_slice($negociosSimulados, $offset, $porPagina);
$totalPaginas  = ceil($totalNegocios / $porPagina);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Negocios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- -------------------------------------------------------------------------
         2. Vincular la hoja de estilos principal (estilos-mapa-comercial.css)
         Ajusta la ruta según tu estructura de archivos.
         ------------------------------------------------------------------------- -->
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    
    <!-- (Opcional) Fuente de iconos, por ejemplo Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <!-- Scripts JavaScript principales (ajusta según tu proyecto) -->
    <script src="../app.js"></script>
    <script src="../ventanasDetalles.js"></script>
    <script src="../formularioNegocio.js"></script>
    <script src="../marcadoresPopups.js"></script>
    
    <!-- -------------------------------------------------------------------------
         3. Estilos internos opcionales o ajustes específicos para este archivo
         ------------------------------------------------------------------------- -->
    <style>
        /* Puedes añadir ajustes específicos para la vista de "mis_negocios" aquí */

        .contenedor-filtros {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }

        .contenedor-filtros .form-control {
            padding: 0.5rem;
            border: 1px solid var(--borde-color);
            border-radius: var(--borde-radio);
            outline: none;
            max-width: 200px;
        }

        .contenedor-filtros select.form-control {
            cursor: pointer;
        }

        .boton-buscar {
            padding: 0.5rem 1rem;
            background-color: var(--color-primario);
            color: white;
            border: none;
            border-radius: var(--borde-radio);
        }
        .boton-buscar:hover {
            background-color: var(--color-secundario);
        }

        .contenedor-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .card-negocio {
            background-color: white;
            border: 1px solid var(--borde-color);
            border-radius: var(--borde-radio);
            box-shadow: var(--sombra-suave);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-negocio img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }

        .card-body {
            padding: 1rem;
            flex: 1;
        }
        .card-body h3 {
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        .card-body p {
            margin-bottom: 0.3rem;
            color: var(--texto-medio);
        }
        .card-body .categoria {
            font-weight: 500;
            color: var(--texto-oscuro);
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: var(--fondo-oscuro);
        }

        .card-footer button {
            border: none;
            background: none;
            cursor: pointer;
            color: var(--texto-oscuro);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .card-footer button i {
            font-size: 1rem;
        }
        .card-footer button:hover {
            color: var(--color-primario);
        }

        .btn-agregar-negocio {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.6rem 1rem;
            background-color: var(--color-acento);
            color: white;
            border-radius: var(--borde-radio);
            text-decoration: none;
        }
        .btn-agregar-negocio:hover {
            background-color: var(--color-primario);
        }

        /* Paginación */
        .paginacion {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }
        .paginacion a {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border: 1px solid var(--borde-color);
            border-radius: var(--borde-radio);
            color: var(--texto-oscuro);
        }
        .paginacion a:hover {
            background-color: var(--fondo-medio);
        }
        .paginacion a.activa {
            background-color: var(--color-primario);
            color: white;
            border-color: var(--color-primario);
        }

        /* Modal de vista rápida */
        .overlay-modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }
        .overlay-modal .modal-contenido {
            background-color: white;
            border-radius: var(--borde-radio);
            padding: 1rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        .overlay-modal .modal-contenido h2 {
            margin-bottom: 0.5rem;
        }
        .overlay-modal .cerrar-modal {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
        }
        .overlay-modal.mostrar {
            display: flex;
        }
    </style>
</head>
<body>

<!-- -------------------------------------------------------------------------
     4. Cabecera (header) de la aplicación
     ------------------------------------------------------------------------- -->
<header class="cabecera-app">
    <div class="logo-app">
        <img src="https://via.placeholder.com/40" alt="Logo">
        <span>Mi App Comercial</span>
    </div>
    <nav class="nav-app">
        <ul class="menu-nav">
            <li><a href="#">Inicio</a></li>
            <li><a href="mis_negocios.php" class="activa">Mis Negocios</a></li>
            <li><a href="#">Mapa</a></li>
            <li><a href="#">Perfil</a></li>
        </ul>
    </nav>
</header>

<!-- -------------------------------------------------------------------------
     5. Contenido principal
     ------------------------------------------------------------------------- -->
<main class="contenedor-app" style="padding: 1rem;">

    <!-- Botón para agregar nuevo negocio -->
    <a href="form_negocio.php" class="btn-agregar-negocio">
        <i class="fa fa-plus"></i> Agregar Nuevo Negocio
    </a>

    <!-- Filtros de búsqueda y ordenamiento -->
    <form action="" method="GET" class="contenedor-filtros" id="form-filtro-busqueda">
        <input type="text" name="busqueda" class="form-control" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
        
        <select name="orden" class="form-control" onchange="document.getElementById('form-filtro-busqueda').submit()">
            <option value="nombre_asc"  <?php if($orden === 'nombre_asc') echo 'selected'; ?>>Nombre (A-Z)</option>
            <option value="nombre_desc" <?php if($orden === 'nombre_desc') echo 'selected'; ?>>Nombre (Z-A)</option>
            <!-- Agrega más opciones de orden si lo deseas -->
        </select>

        <button type="submit" class="boton-buscar" title="Buscar negocios">
            <i class="fa fa-search"></i> Buscar
        </button>
    </form>

    <!-- Panel para mostrar listado de negocios en formato de tarjetas -->
    <div class="contenedor-cards" id="lista-negocios">
        <?php if (empty($negociosSimulados)): ?>
            <p>No se encontraron negocios con los criterios especificados.</p>
        <?php else: ?>
            <?php foreach ($negociosSimulados as $negocio): ?>
                <div class="card-negocio" data-id="<?php echo $negocio['id']; ?>">
                    <img src="<?php echo htmlspecialchars($negocio['imagen']); ?>" alt="Imagen de <?php echo htmlspecialchars($negocio['nombre']); ?>">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($negocio['nombre']); ?></h3>
                        <p class="categoria"><?php echo htmlspecialchars($negocio['categoria']); ?></p>
                        <p class="direccion"><i class="fa fa-map-marker-alt"></i> <?php echo htmlspecialchars($negocio['direccion']); ?></p>
                    </div>
                    <div class="card-footer">
                        <button class="btn-editar" 
                                onclick="window.location.href='form_negocio.php?id=<?php echo $negocio['id']; ?>'">
                            <i class="fa fa-edit"></i> Editar
                        </button>
                        <button class="btn-ver-mapa" onclick="verEnMapa(<?php echo $negocio['id']; ?>)">
                            <i class="fa fa-map"></i> Mapa
                        </button>
                        <button class="btn-eliminar" onclick="confirmarEliminar(<?php echo $negocio['id']; ?>)">
                            <i class="fa fa-trash"></i> Eliminar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación de resultados -->
    <?php if ($totalPaginas > 1): ?>
    <div class="paginacion">
        <?php for ($i=1; $i <= $totalPaginas; $i++): ?>
            <a 
               href="?busqueda=<?php echo urlencode($busqueda); ?>&orden=<?php echo urlencode($orden); ?>&page=<?php echo $i; ?>"
               class="<?php echo ($i === $page) ? 'activa' : ''; ?>">
               <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>

<!-- -------------------------------------------------------------------------
     6. Modal de visualización rápida de detalles
     ------------------------------------------------------------------------- -->
<div class="overlay-modal" id="modal-vista-rapida">
    <div class="modal-contenido">
        <button class="cerrar-modal" onclick="cerrarModalVistaRapida()">&times;</button>
        <div id="contenido-modal-rapido">
            <!-- Se llena dinámicamente con JavaScript -->
        </div>
    </div>
</div>

<!-- -------------------------------------------------------------------------
     7. Pie de página (footer)
     ------------------------------------------------------------------------- -->
<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<!-- -------------------------------------------------------------------------
     8. JavaScript para funcionalidades dinámicas
     ------------------------------------------------------------------------- -->
<script>
// Ejemplo de autenticación (depende de tu auth.php real)
function isAuthenticated() {
    // Supongamos que devuelves true/false según el estado de la sesión
    // Este código es meramente ilustrativo, ya que la verificación real está en PHP.
    return true;
}

// Confirmación antes de eliminar
function confirmarEliminar(idNegocio) {
    const confirmar = window.confirm("¿Estás seguro de que deseas eliminar este negocio?");
    if (!confirmar) return;
    
    // Aquí iría la llamada a la API o acción en el servidor para eliminar
    // Por ejemplo:
    /*
    fetch(`../api/negocios.php?id=${idNegocio}`, {
        method: 'DELETE'
    })
    .then(res => res.json())
    .then(data => {
        if (data.exito) {
            alert('Negocio eliminado correctamente.');
            // Recargar o redireccionar
            window.location.reload();
        } else {
            alert('Ocurrió un error al eliminar el negocio.');
        }
    })
    .catch(err => console.error(err));
    */
   alert(`Simulando la eliminación del negocio con ID = ${idNegocio}`);
}

// Función para mostrar los datos de un negocio en un modal “vista rápida”
function mostrarVistaRapida(idNegocio) {
    // Aquí podrías llamar a la API para obtener detalles del negocio
    // Ejemplo simplificado:
    // fetch(`../api/negocios.php?id=${idNegocio}`)
    //   .then(res => res.json())
    //   .then(data => { /* ...llenar modal... */ });
    
    const contenidoModal = document.getElementById('contenido-modal-rapido');
    if (!contenidoModal) return;

    // Contenido simulado, en la práctica usarías la respuesta de tu API
    contenidoModal.innerHTML = `
        <h2>Detalles del Negocio #${idNegocio}</h2>
        <p>Aquí se mostrarían todos los detalles relevantes.</p>
        <button onclick="alert('Navegar a edición...');">Editar Negocio</button>
    `;
    
    document.getElementById('modal-vista-rapida').classList.add('mostrar');
}

// Función para cerrar el modal
function cerrarModalVistaRapida() {
    document.getElementById('modal-vista-rapida').classList.remove('mostrar');
}

// Función para ver negocio en el mapa (redirige o abre otra vista)
function verEnMapa(idNegocio) {
    // Podrías redirigir a la pantalla principal del mapa con parámetros
    // Ej. map.php?negocio=ID
    // O llamar a una función que abra un modal con el mapa centrado en el negocio
    alert(`Ver en mapa: negocio #${idNegocio}`);
}
</script>

</body>
</html>
