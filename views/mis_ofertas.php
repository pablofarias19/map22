<?php
// mis_ofertas.php
// Vista para mostrar el listado de ofertas del usuario autenticado

require_once '../auth.php'; 
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Parámetros de búsqueda, filtrado y paginación
$busqueda    = isset($_GET['busqueda'])   ? trim($_GET['busqueda'])  : '';
$estado      = isset($_GET['estado'])     ? $_GET['estado']         : ''; // activa/inactiva/vencida
$precioMin   = isset($_GET['precio_min']) ? (float)$_GET['precio_min'] : 0;
$precioMax   = isset($_GET['precio_max']) ? (float)$_GET['precio_max'] : 0;
$fechaDesde  = isset($_GET['fecha_desde'])? $_GET['fecha_desde']     : '';
$fechaHasta  = isset($_GET['fecha_hasta'])? $_GET['fecha_hasta']     : '';
$page        = isset($_GET['page'])       ? (int)$_GET['page']       : 1;
$porPagina   = 6;
$offset      = ($page - 1) * $porPagina;

// Datos simulados (reemplaza con lógica real)
$ofertasSimuladas = [
    [
        'id'            => 1,
        'titulo'        => 'Descuento en Ropa de Invierno',
        'precio_normal' => 1000,
        'precio_oferta' => 800,
        'fecha_inicio'  => '2025-01-01',
        'fecha_fin'     => '2025-01-15',
        'imagen'        => 'https://via.placeholder.com/300x200?text=Oferta+1',
        'activo'        => true
    ],
    [
        'id'            => 2,
        'titulo'        => '2x1 en Cafés Especiales',
        'precio_normal' => 400,
        'precio_oferta' => 200,
        'fecha_inicio'  => '2023-05-01',
        'fecha_fin'     => '2023-05-05',
        'imagen'        => 'https://via.placeholder.com/300x200?text=Oferta+2',
        'activo'        => false
    ],
    [
        'id'            => 3,
        'titulo'        => 'Promo Notebook + Antivirus',
        'precio_normal' => 50000,
        'precio_oferta' => 45000,
        'fecha_inicio'  => '2026-07-01',
        'fecha_fin'     => '2026-07-31',
        'imagen'        => 'https://via.placeholder.com/300x200?text=Oferta+3',
        'activo'        => true
    ],
];

// Filtrar por búsqueda, estado, precio, fechas
$ofertasFiltradas = array_filter($ofertasSimuladas, function($of) use ($busqueda, $estado, $precioMin, $precioMax, $fechaDesde, $fechaHasta) {
    $coincideBusqueda = true;
    if ($busqueda !== '') {
        $coincideBusqueda = (stripos($of['titulo'], $busqueda) !== false);
    }
    $coincideEstado = true;
    if ($estado === 'activa') {
        $coincideEstado = $of['activo'] === true;
    } elseif ($estado === 'inactiva') {
        $coincideEstado = $of['activo'] === false;
    } elseif ($estado === 'vencida') {
        // Consideramos vencida si fecha_fin < hoy
        $coincideEstado = (strtotime($of['fecha_fin']) < time());
    }

    $coincidePrecio = true;
    if ($precioMin > 0 && $of['precio_oferta'] < $precioMin) {
        $coincidePrecio = false;
    }
    if ($precioMax > 0 && $of['precio_oferta'] > $precioMax) {
        $coincidePrecio = false;
    }

    $coincideFecha = true;
    // Filtramos si la oferta "toca" el rango (fecha_inicio <= finBuscado y fecha_fin >= inicioBuscado)
    if ($fechaDesde !== '') {
        $coincideFecha = (strtotime($of['fecha_fin']) >= strtotime($fechaDesde));
    }
    if ($fechaHasta !== '' && $coincideFecha) {
        $coincideFecha = (strtotime($of['fecha_inicio']) <= strtotime($fechaHasta));
    }

    return $coincideBusqueda && $coincideEstado && $coincidePrecio && $coincideFecha;
});

$totalOfertas = count($ofertasFiltradas);
$ofertasFiltradas = array_slice($ofertasFiltradas, $offset, $porPagina);
$totalPaginas = ceil($totalOfertas / $porPagina);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Ofertas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="../app.js"></script>
    <script src="../ventanasDetalles.js"></script>
    <script src="../formularioOferta.js"></script> <!-- Suponiendo un JS para formularios de ofertas -->
    <script src="../marcadoresPopups.js"></script>
    <style>
    /* Estilos específicos para la vista de ofertas */
    .card-oferta .oferta-activa {
        color: var(--color-exito);
        font-weight: bold;
    }
    .card-oferta .oferta-inactiva {
        color: var(--texto-claro);
        font-weight: bold;
    }
    .card-oferta .oferta-vencida {
        color: var(--color-error);
        font-weight: bold;
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

<main class="contenedor-app" style="padding: 1rem;">

    <!-- Botón para agregar nueva oferta -->
    <a href="form_oferta.php" class="btn-agregar-negocio">
        <i class="fa fa-plus"></i> Agregar Nueva Oferta
    </a>

    <!-- Filtros: estado, rango de precios, fechas -->
    <form action="" method="GET" class="contenedor-filtros" id="form-filtro-ofertas">
        <input type="text" name="busqueda" class="form-control" placeholder="Buscar..." 
               value="<?php echo htmlspecialchars($busqueda); ?>" />

        <select name="estado" class="form-control">
            <option value="">(Estado: Todos)</option>
            <option value="activa"   <?php if($estado==='activa') echo 'selected';?>>Activas</option>
            <option value="inactiva" <?php if($estado==='inactiva') echo 'selected';?>>Inactivas</option>
            <option value="vencida"  <?php if($estado==='vencida') echo 'selected';?>>Vencidas</option>
        </select>

        <label>Precio mínimo:</label>
        <input type="number" name="precio_min" class="form-control" step="0.01" 
               value="<?php echo $precioMin > 0 ? $precioMin : ''; ?>">

        <label>Precio máximo:</label>
        <input type="number" name="precio_max" class="form-control" step="0.01" 
               value="<?php echo $precioMax > 0 ? $precioMax : ''; ?>">

        <label>Fecha desde:</label>
        <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fechaDesde);?>">

        <label>Fecha hasta:</label>
        <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fechaHasta);?>">

        <button type="submit" class="boton-buscar">
            <i class="fa fa-search"></i> Buscar
        </button>
    </form>

    <!-- Panel de ofertas -->
    <div class="contenedor-cards" id="lista-ofertas">
        <?php if (empty($ofertasFiltradas)): ?>
            <p>No se encontraron ofertas con los criterios indicados.</p>
        <?php else: ?>
            <?php foreach ($ofertasFiltradas as $of): ?>
                <?php 
                    $inicio = strtotime($of['fecha_inicio']);
                    $fin    = strtotime($of['fecha_fin']);
                    $hoy    = time();
                    
                    // Determinamos estado visual
                    $claseEstado = '';
                    if ($fin < $hoy) {
                        $claseEstado = 'oferta-vencida';
                    } elseif ($of['activo']) {
                        $claseEstado = 'oferta-activa';
                    } else {
                        $claseEstado = 'oferta-inactiva';
                    }
                    $descuento = 0;
                    if ($of['precio_normal'] > 0) {
                        $descuento = round((1 - ($of['precio_oferta'] / $of['precio_normal'])) * 100);
                    }
                ?>
                <div class="card-negocio card-oferta" data-id="<?php echo $of['id']; ?>">
                    <img src="<?php echo htmlspecialchars($of['imagen']); ?>" 
                         alt="Oferta <?php echo htmlspecialchars($of['titulo']); ?>">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($of['titulo']); ?></h3>
                        <p>Precio normal: $<?php echo number_format($of['precio_normal'], 2); ?></p>
                        <p>Precio oferta: $<?php echo number_format($of['precio_oferta'], 2); ?></p>
                        <p>
                            <strong>Descuento:</strong> 
                            <?php echo $descuento; ?>%
                        </p>
                        <p><i class="fa fa-calendar"></i> 
                           <?php echo date('d/m/Y', $inicio); ?> - <?php echo date('d/m/Y', $fin); ?>
                        </p>
                        <span class="<?php echo $claseEstado; ?>">
                            <?php 
                                if ($claseEstado === 'oferta-vencida') echo 'Oferta vencida';
                                elseif ($claseEstado === 'oferta-activa') echo 'Oferta activa';
                                else echo 'Oferta inactiva'; 
                            ?>
                        </span>
                    </div>
                    <div class="card-footer">
                        <button class="btn-editar" 
                                onclick="window.location.href='form_oferta.php?id=<?php echo $of['id']; ?>'">
                            <i class="fa fa-edit"></i> Editar
                        </button>
                        <button class="btn-ver-mapa" onclick="verEnMapaOferta(<?php echo $of['id']; ?>)">
                            <i class="fa fa-map"></i> Mapa
                        </button>
                        <button class="btn-eliminar" onclick="confirmarEliminarOferta(<?php echo $of['id']; ?>)">
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
                  'estado' => $estado,
                  'precio_min' => $precioMin,
                  'precio_max' => $precioMax,
                  'fecha_desde' => $fechaDesde,
                  'fecha_hasta' => $fechaHasta,
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
function confirmarEliminarOferta(idOferta) {
    if (!confirm("¿Seguro que deseas eliminar esta oferta?")) return;
    // Aquí va la lógica real (API, DB, etc.)
    alert("Simulando la eliminación de la oferta ID=" + idOferta);
}

function verEnMapaOferta(idOferta) {
    alert("Ver en mapa: oferta #" + idOferta);
}
</script>
</body>
</html>
