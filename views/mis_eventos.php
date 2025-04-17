<?php
// mis_eventos.php
// Vista para mostrar el listado de eventos del usuario autenticado

require_once '../auth.php'; // Ajusta la ruta si es necesario
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// -----------------------------------------------------------------------------
// Parámetros de búsqueda, filtrado y paginación
// (Valida y ajusta a tu necesidad real)
$busqueda    = isset($_GET['busqueda'])    ? trim($_GET['busqueda']) : '';
$categoria   = isset($_GET['categoria'])   ? $_GET['categoria']      : '';
$estado      = isset($_GET['estado'])      ? $_GET['estado']         : '';
$fechaDesde  = isset($_GET['fecha_desde']) ? $_GET['fecha_desde']    : '';
$fechaHasta  = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta']    : '';
$orden       = isset($_GET['orden'])       ? $_GET['orden']          : 'fecha_asc';
$page        = isset($_GET['page'])        ? (int)$_GET['page']      : 1;
$porPagina   = 6;
$offset      = ($page - 1) * $porPagina;

// -----------------------------------------------------------------------------
// Datos simulados (Reemplaza con la consulta real a tu BD o API)
$eventosSimulados = [
    [
        'id'         => 1,
        'titulo'     => 'Concierto de Rock',
        'categoria'  => 'Música',
        'fecha'      => '2025-05-20 21:00:00',
        'direccion'  => 'Estadio Central',
        'imagen'     => 'https://via.placeholder.com/300x200?text=Evento+1',
        'activo'     => true
    ],
    [
        'id'         => 2,
        'titulo'     => 'Feria de Tecnología',
        'categoria'  => 'Tecnológico',
        'fecha'      => '2023-10-10 09:00:00',
        'direccion'  => 'Centro de Convenciones',
        'imagen'     => 'https://via.placeholder.com/300x200?text=Evento+2',
        'activo'     => false
    ],
    [
        'id'         => 3,
        'titulo'     => 'Maratón Solidaria',
        'categoria'  => 'Deportivo',
        'fecha'      => '2026-02-15 07:00:00',
        'direccion'  => 'Avenida Principal',
        'imagen'     => 'https://via.placeholder.com/300x200?text=Evento+3',
        'activo'     => true
    ],
    // Agrega más eventos si deseas
];

// -----------------------------------------------------------------------------
// Filtrado básico por categoría, estado (activo/inactivo), búsqueda, fecha
// -----------------------------------------------------------------------------
$eventosFiltrados = array_filter($eventosSimulados, function($ev) use ($busqueda, $categoria, $estado, $fechaDesde, $fechaHasta) {
    $coincideBusqueda = true;
    if ($busqueda !== '') {
        $coincideBusqueda = (stripos($ev['titulo'], $busqueda) !== false) 
                         || (stripos($ev['categoria'], $busqueda) !== false)
                         || (stripos($ev['direccion'], $busqueda) !== false);
    }

    $coincideCategoria = true;
    if ($categoria !== '') {
        $coincideCategoria = (stripos($ev['categoria'], $categoria) !== false);
    }

    $coincideEstado = true;
    if ($estado !== '') {
        // "activo" vs "inactivo"
        if ($estado === 'activo') {
            $coincideEstado = $ev['activo'] === true;
        } elseif ($estado === 'inactivo') {
            $coincideEstado = $ev['activo'] === false;
        }
    }

    $coincideFecha = true;
    if ($fechaDesde !== '') {
        $coincideFecha = (strtotime($ev['fecha']) >= strtotime($fechaDesde));
    }
    if ($fechaHasta !== '' && $coincideFecha) {
        $coincideFecha = (strtotime($ev['fecha']) <= strtotime($fechaHasta));
    }

    return $coincideBusqueda && $coincideCategoria && $coincideEstado && $coincideFecha;
});

// -----------------------------------------------------------------------------
// Ordenamiento: fecha_asc o fecha_desc
// -----------------------------------------------------------------------------
usort($eventosFiltrados, function($a, $b) use ($orden) {
    if ($orden === 'fecha_asc') {
        return strtotime($a['fecha']) - strtotime($b['fecha']);
    } else {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    }
});

// Conteo y paginación
$totalEventos = count($eventosFiltrados);
$eventosFiltrados = array_slice($eventosFiltrados, $offset, $porPagina);
$totalPaginas = ceil($totalEventos / $porPagina);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Eventos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="../app.js"></script>
    <script src="../ventanasDetalles.js"></script>
    <script src="../formularioEvento.js"></script> <!-- Suponiendo un JS para formularios de eventos -->
    <script src="../marcadoresPopups.js"></script>
    <style>
    /* Estilos específicos para la vista de eventos */
    .card-evento .evento-pasado {
        color: var(--texto-claro);
        font-weight: bold;
        background-color: #e9ecef;
        padding: 0.25rem 0.5rem;
        border-radius: var(--borde-radio);
    }
    .card-evento .evento-proximo {
        color: var(--color-exito);
        font-weight: bold;
        padding: 0.25rem 0.5rem;
        border: 1px solid var(--color-exito);
        border-radius: var(--borde-radio);
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

<main class="contenedor-app" style="padding: 1rem;">

    <!-- Botón para agregar nuevo evento -->
    <a href="form_evento.php" class="btn-agregar-negocio">
        <i class="fa fa-plus"></i> Agregar Nuevo Evento
    </a>

    <!-- Filtros y orden -->
    <form action="" method="GET" class="contenedor-filtros" id="form-filtro-eventos">
        <input type="text" name="busqueda" class="form-control" placeholder="Buscar..." 
               value="<?php echo htmlspecialchars($busqueda); ?>" />

        <select name="categoria" class="form-control">
            <option value="">Todas las categorías</option>
            <option value="Música" <?php if($categoria==='Música') echo 'selected';?>>Música</option>
            <option value="Tecnológico" <?php if($categoria==='Tecnológico') echo 'selected';?>>Tecnológico</option>
            <option value="Deportivo" <?php if($categoria==='Deportivo') echo 'selected';?>>Deportivo</option>
            <!-- Agrega más según tus categorías -->
        </select>

        <select name="estado" class="form-control">
            <option value="">(Estado: Todos)</option>
            <option value="activo" <?php if($estado==='activo') echo 'selected';?>>Activos</option>
            <option value="inactivo" <?php if($estado==='inactivo') echo 'selected';?>>Inactivos</option>
        </select>

        <label>Fecha desde:</label>
        <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fechaDesde);?>">

        <label>Fecha hasta:</label>
        <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fechaHasta);?>">

        <select name="orden" class="form-control">
            <option value="fecha_asc"  <?php if($orden==='fecha_asc')  echo 'selected';?>>Fecha (asc)</option>
            <option value="fecha_desc" <?php if($orden==='fecha_desc') echo 'selected';?>>Fecha (desc)</option>
        </select>

        <button type="submit" class="boton-buscar">
            <i class="fa fa-search"></i> Buscar
        </button>
    </form>

    <!-- Panel de tarjetas de eventos -->
    <div class="contenedor-cards" id="lista-eventos">
        <?php if (empty($eventosFiltrados)): ?>
            <p>No se encontraron eventos con los filtros indicados.</p>
        <?php else: ?>
            <?php foreach ($eventosFiltrados as $ev): ?>
                <?php
                    $fechaEvento = strtotime($ev['fecha']);
                    $esPasado = $fechaEvento < time();
                ?>
                <div class="card-negocio card-evento" data-id="<?php echo $ev['id']; ?>">
                    <img src="<?php echo htmlspecialchars($ev['imagen']); ?>" 
                         alt="Evento <?php echo htmlspecialchars($ev['titulo']); ?>">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($ev['titulo']); ?></h3>
                        <p class="categoria"><?php echo htmlspecialchars($ev['categoria']); ?></p>
                        <p><i class="fa fa-calendar"></i> 
                           <?php echo date('d/m/Y H:i', $fechaEvento); ?></p>
                        <p><i class="fa fa-map-marker-alt"></i> <?php echo htmlspecialchars($ev['direccion']); ?></p>
                        <?php if ($esPasado): ?>
                            <span class="evento-pasado">Evento pasado</span>
                        <?php else: ?>
                            <span class="evento-proximo">Próximo evento</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <button class="btn-editar" 
                                onclick="window.location.href='form_evento.php?id=<?php echo $ev['id']; ?>'">
                            <i class="fa fa-edit"></i> Editar
                        </button>
                        <button class="btn-ver-mapa" onclick="verEnMapaEvento(<?php echo $ev['id']; ?>)">
                            <i class="fa fa-map"></i> Mapa
                        </button>
                        <button class="btn-eliminar" onclick="confirmarEliminarEvento(<?php echo $ev['id']; ?>)">
                            <i class="fa fa-trash"></i> Eliminar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($totalPaginas > 1): ?>
    <div class="paginacion">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?<?php 
               echo http_build_query([
                  'busqueda' => $busqueda,
                  'categoria' => $categoria,
                  'estado' => $estado,
                  'fecha_desde' => $fechaDesde,
                  'fecha_hasta' => $fechaHasta,
                  'orden' => $orden,
                  'page' => $i
               ]);
            ?>" 
            class="<?php echo ($i === $page) ? 'activa' : ''; ?>">
               <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
function confirmarEliminarEvento(idEvento) {
    if (!confirm("¿Seguro que deseas eliminar este evento?")) return;
    // Lógica real para eliminar (fetch a tu API o acción server-side)
    alert("Simulando la eliminación del evento ID=" + idEvento);
}

function verEnMapaEvento(idEvento) {
    alert("Ver en mapa: evento #" + idEvento);
}
</script>
</body>
</html>
