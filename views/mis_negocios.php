<?php
// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// mis_negocios.php - Vista para mostrar el listado de negocios del usuario autenticado

// -----------------------------------------------------------------------------
// 1. Comprobación de autenticación
// -----------------------------------------------------------------------------
// Iniciar sesión para gestión de autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar múltiples rutas para archivos de autenticación
$auth_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/mapi2/api/auth.php',
    dirname(dirname(__FILE__)) . '/api/auth.php',
    __DIR__ . '/../api/auth.php'
];

$auth_loaded = false;
foreach ($auth_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $auth_loaded = true;
        break;
    }
}

// Si no se pudo cargar el archivo de autenticación, crear función temporal
if (!$auth_loaded && !function_exists('isAuthenticated')) {
    function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

// Asegurarnos que existe un usuario para propósitos de desarrollo
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ID temporal para pruebas
    $_SESSION['username'] = 'Usuario de prueba';
}

// -----------------------------------------------------------------------------
// 2. Parámetros de búsqueda, filtrado y paginación
// -----------------------------------------------------------------------------
$busqueda    = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$orden       = isset($_GET['orden'])    ? $_GET['orden']         : 'nombre_asc';
$page        = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1; // Asegurar que page sea >= 1
$porPagina   = 6; // Cantidad de negocios por página

// -----------------------------------------------------------------------------
// 3. Obtener datos de negocios mediante el controlador
// -----------------------------------------------------------------------------
// Cargar dependencias necesarias para trabajar con la API
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/NegocioController.php';

// Inicializar variables
$negocios_filtrados = [];
$totalNegocios = 0;
$totalPaginas = 0;
$error = null;

try {
    // Crear instancia de base de datos y controlador
    $db = (new Database())->getConnection();
    $controller = new NegocioController($db);

    // Obtener los negocios del usuario actual
    $user_id = $_SESSION['user_id'];
    $todos_negocios = $controller->obtenerNegociosUsuario($user_id);

    // Aplicar filtro de búsqueda si existe
    if (!empty($busqueda)) {
        $negocios_filtrados = array_filter($todos_negocios, function($negocio) use ($busqueda) {
            $term = strtolower($busqueda);
            $nombre = strtolower($negocio['nombre_comercial'] ?? '');
            $categoria = strtolower($negocio['categoria'] ?? '');
            $direccion = strtolower($negocio['direccion'] ?? '');

            return strpos($nombre, $term) !== false ||
                   strpos($categoria, $term) !== false ||
                   strpos($direccion, $term) !== false;
        });
    } else {
        $negocios_filtrados = $todos_negocios;
    }

    // Aplicar ordenamiento
    usort($negocios_filtrados, function($a, $b) use ($orden) {
        $nombre_a = $a['nombre_comercial'] ?? '';
        $nombre_b = $b['nombre_comercial'] ?? '';

        if ($orden === 'nombre_desc') {
            return strcasecmp($nombre_b, $nombre_a); // Z-A
        } else {
            return strcasecmp($nombre_a, $nombre_b); // A-Z (default)
        }
    });

    // Calcular total y páginas para paginación
    $totalNegocios = count($negocios_filtrados);
    $totalPaginas = ceil($totalNegocios / $porPagina);

    // Aplicar paginación
    $offset = ($page - 1) * $porPagina;
    $negocios_pagina = array_slice($negocios_filtrados, $offset, $porPagina);

    // Debug - Mostrar información
    echo "<!-- Total negocios encontrados: " . $totalNegocios . " -->";
    echo "<!-- Negocios en esta página: " . count($negocios_pagina) . " -->";

} catch (PDOException $e) {
    $error = "Error de base de datos al obtener negocios.";
    error_log("Error PDO en mis_negocios.php: " . $e->getMessage());
} catch (Throwable $e) {
    $error = "Error interno al procesar los negocios.";
    error_log("Error general en mis_negocios.php: " . $e->getMessage());
}

// Manejo de peticiones JSON para el mapa (retornar directamente)
if (isset($_GET['json']) && $_GET['json'] == 1) {
    header('Content-Type: application/json');

    if (!empty($negocios_pagina)) {
        echo json_encode(array_map(function($neg) {
            return [
                'id' => $neg['id'],
                'nombre' => $neg['nombre_comercial'],
                'latitud' => $neg['latitud'],
                'longitud' => $neg['longitud'],
                'descripcion' => $neg['lema_publicitario'] ?? ''
            ];
        }, $negocios_pagina)); // Usar los negocios de la página actual
    } else {
        echo json_encode([]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Negocios</title>

    <!-- Vinculación con el archivo CSS externo -->
    <link rel="stylesheet" href="../css/estilos-mapa-comercial.css">

    <!-- Iconos (asumiendo que usas FontAwesome) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Scripts para funcionalidades específicas -->
    <script src="../js/formularioNegocio.js" defer></script>
    <script src="../js/marcadoresPopups.js" defer></script>

    <style>
        /* Estilos específicos para la vista de "mis_negocios" */
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
    <div class="contenedor-app">
        <!-- Cabecera -->
        <header class="cabecera-app">
            <div class="logo-app">
                <img src="../img/logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/40'">
                <span>Mi App Comercial</span>
            </div>
            <nav class="nav-app">
                <ul class="menu-nav">
                    <li><a href="../index.php">Inicio</a></li>
                    <li><a href="mis_negocios.php" class="activa">Mis Negocios</a></li>
                    <li><a href="mapa.php">Mapa</a></li>
                    <li><a href="perfil.php">Perfil</a></li>
                </ul>
            </nav>
        </header>

        <!-- Contenido principal -->
        <main class="contenedor-app" style="padding: 1rem;">
            <!-- Botón para agregar nuevo negocio -->
            <a href="form_negocio.php" class="btn-agregar-negocio">
                <i class="fas fa-plus"></i> Agregar Nuevo Negocio
            </a>

            <!-- Filtros de búsqueda y ordenamiento -->
            <form action="" method="GET" class="contenedor-filtros" id="form-filtro-busqueda">
                <input type="text" name="busqueda" class="form-control" placeholder="Buscar..."
                    value="<?php echo htmlspecialchars($busqueda); ?>">

                <select name="orden" class="form-control" onchange="document.getElementById('form-filtro-busqueda').submit()">
                    <option value="nombre_asc" <?php if($orden === 'nombre_asc') echo 'selected'; ?>>Nombre (A-Z)</option>
                    <option value="nombre_desc" <?php if($orden === 'nombre_desc') echo 'selected'; ?>>Nombre (Z-A)</option>
                </select>

                <button type="submit" class="boton-buscar" title="Buscar negocios">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </form>

            <!-- Panel para mostrar listado de negocios en formato de tarjetas -->
            <div class="contenedor-cards" id="lista-negocios">
                <?php if ($error): ?>
                    <p class="mensaje-error">Error: <?php echo htmlspecialchars($error); ?></p>
                <?php elseif (empty($negocios_pagina)): ?>
                    <p>No se encontraron negocios con los criterios especificados.</p>
                <?php else: ?>
                    <?php foreach ($negocios_pagina as $negocio): ?>
                        <div class="card-negocio" data-id="<?php echo $negocio['id']; ?>">
                            <img src="<?php echo !empty($negocio['imagen_principal']) ? htmlspecialchars($negocio['imagen_principal']) : 'https://via.placeholder.com/300x200?text=Sin+Imagen'; ?>"
                                alt="Imagen de <?php echo htmlspecialchars($negocio['nombre_comercial']); ?>">
                            <div class="card-body">
                                <h3><?php echo htmlspecialchars($negocio['nombre_comercial']); ?></h3>
                                <p class="categoria"><?php echo htmlspecialchars($negocio['categoria'] ?? 'Sin categoría'); ?></p>
                                <p class="direccion"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($negocio['direccion']); ?></p>
                                <?php if (!empty($negocio['telefono'])): ?>
                                <p class="telefono"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($negocio['telefono']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <button class="btn-editar"
                                        onclick="window.location.href='form_negocio.php?id=<?php echo $negocio['id']; ?>'">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn-ver-mapa" onclick="verEnMapa(<?php echo $negocio['id']; ?>, <?php echo $negocio['latitud']; ?>, <?php echo $negocio['longitud']; ?>)">
                                    <i class="fas fa-map"></i> Mapa
                                </button>
                                <button class="btn-eliminar" onclick="confirmarEliminar(<?php echo $negocio['id']; ?>)">
                                    <i class="fas fa-trash"></i> Eliminar
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

        <!-- Modal de visualización rápida de detalles -->
        <div class="overlay-modal" id="modal-vista-rapida">
            <div class="modal-contenido">
                <button class="cerrar-modal" onclick="cerrarModalVistaRapida()">&times;</button>
                <div id="contenido-modal-rapido">
                    <!-- Se llena dinámicamente con JavaScript -->
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <footer class="pie-app">
            <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
        </footer>
    </div>

    <!-- JavaScript para funcionalidades dinámicas -->
    <script>
        // Confirmación antes de eliminar
        function confirmarEliminar(idNegocio) {
            const confirmar = window.confirm("¿Estás seguro de que deseas eliminar este negocio?");
            if (!confirmar) return;

            // Implementar eliminación a través de la API
            fetch(`../api/negocios.php?id=${idNegocio}`, {
                method: 'DELETE', // O 'POST' si usas _method=DELETE
                headers: {
                    'Content-Type': 'application/json',
                    // Incluir cabecera de autorización si es necesaria
                    // 'Authorization': 'Bearer ' + tuTokenJWT
                }
                // Si usas _method=DELETE con POST:
                // body: JSON.stringify({ _method: 'DELETE' })
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.error || `Error ${res.status}`) });
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Negocio eliminado correctamente.');
                    // Opcional: eliminar el elemento del DOM en lugar de recargar
                    const card = document.querySelector(`.card-negocio[data-id="${idNegocio}"]`);
                    if (card) card.remove();
                    // window.location.reload(); // Recargar la página
                } else {
                    alert(`Error: ${data.error || 'No se pudo eliminar el negocio.'}`);
                }
            })
            .catch(err => {
                console.error("Error en la petición:", err);
                alert(`Ocurrió un error al procesar la solicitud: ${err.message}`);
            });
        }

        // Función para mostrar los datos de un negocio en un modal "vista rápida"
        function mostrarVistaRapida(idNegocio) {
            // Obtener detalles del negocio a través de la API
            fetch(`../api/negocios.php?id=${idNegocio}`)
              .then(res => {
                  if (!res.ok) {
                      return res.json().then(err => { throw new Error(err.error || `Error ${res.status}`) });
                  }
                  return res.json();
              })
              .then(data => {
                const contenidoModal = document.getElementById('contenido-modal-rapido');
                if (!contenidoModal) return;

                // Construir HTML con los detalles (ejemplo básico)
                contenidoModal.innerHTML = `
                    <h2>${data.nombre_comercial || 'Negocio'}</h2>
                    <p><strong>Categoría:</strong> ${data.categoria_nombre || 'N/A'}</p>
                    <p><strong>Dirección:</strong> ${data.direccion || 'N/A'}</p>
                    <p><strong>Teléfono:</strong> ${data.telefono || 'N/A'}</p>
                    <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                    ${data.sitio_web ? `<p><strong>Web:</strong> <a href="${data.sitio_web}" target="_blank">${data.sitio_web}</a></p>` : ''}
                    <p>${data.lema_publicitario || ''}</p>
                    <button onclick="window.location.href='form_negocio.php?id=${idNegocio}'">Editar Negocio</button>
                `;

                document.getElementById('modal-vista-rapida').classList.add('mostrar');
              })
              .catch(err => {
                  console.error("Error al obtener detalles:", err);
                  alert(`No se pudieron cargar los detalles del negocio: ${err.message}`);
              });
        }

        // Función para cerrar el modal
        function cerrarModalVistaRapida() {
            document.getElementById('modal-vista-rapida').classList.remove('mostrar');
        }

        // Función para ver negocio en el mapa (redirige o abre otra vista)
        function verEnMapa(idNegocio, latitud, longitud) {
            if (latitud && longitud) {
                // Si tienes una página de mapa, puedes redirigir a ella con los parámetros
                window.location.href = `mapa.php?id=${idNegocio}&lat=${latitud}&lng=${longitud}`;
                // O alternativamente, abrir en una nueva pestaña
                // window.open(`mapa.php?id=${idNegocio}&lat=${latitud}&lng=${longitud}`, '_blank');
            } else {
                alert("Este negocio no tiene coordenadas geográficas definidas");
            }
        }
    </script>
</body>
</html>
