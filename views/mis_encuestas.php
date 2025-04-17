<?php
// mis_encuestas.php
// Vista para mostrar el listado de encuestas del usuario autenticado

require_once '../auth.php'; 
if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Parámetros de filtrado y paginación
$busqueda     = isset($_GET['busqueda'])     ? trim($_GET['busqueda']) : '';
$estado       = isset($_GET['estado'])       ? $_GET['estado']         : ''; // activo/finalizado
$fechaDesde   = isset($_GET['fecha_desde'])  ? $_GET['fecha_desde']    : '';
$fechaHasta   = isset($_GET['fecha_hasta'])  ? $_GET['fecha_hasta']    : '';
$page         = isset($_GET['page'])         ? (int)$_GET['page']      : 1;
$porPagina    = 6;
$offset       = ($page - 1) * $porPagina;

// Datos simulados
$encuestasSimuladas = [
    [
        'id'              => 1,
        'titulo'          => 'Encuesta de Satisfacción',
        'fecha_creacion'  => '2025-01-01 10:00:00',
        'fecha_limite'    => '2025-01-10 23:59:59',
        'num_preguntas'   => 5,
        'num_respuestas'  => 12,
        'activa'          => true
    ],
    [
        'id'              => 2,
        'titulo'          => 'Encuesta sobre Productos Nuevos',
        'fecha_creacion'  => '2023-08-15 09:00:00',
        'fecha_limite'    => '2023-08-30 23:59:59',
        'num_preguntas'   => 8,
        'num_respuestas'  => 0,
        'activa'          => false
    ],
    [
        'id'              => 3,
        'titulo'          => 'Estudio de Mercado Juvenil',
        'fecha_creacion'  => '2026-02-01 00:00:00',
        'fecha_limite'    => '2026-02-20 23:59:59',
        'num_preguntas'   => 10,
        'num_respuestas'  => 50,
        'activa'          => true
    ],
];

// Filtrado
$encuestasFiltradas = array_filter($encuestasSimuladas, function($enc) use ($busqueda, $estado, $fechaDesde, $fechaHasta) {
    $coincideBusqueda = true;
    if ($busqueda !== '') {
        $coincideBusqueda = (stripos($enc['titulo'], $busqueda) !== false);
    }

    $coincideEstado = true;
    if ($estado === 'activo') {
        $coincideEstado = $enc['activa'] === true;
    } elseif ($estado === 'finalizado') {
        $coincideEstado = $enc['activa'] === false;
    }

    $coincideFecha = true;
    // Filtramos por rango si se desea (puedes adaptar a tu lógica)
    $fechaCreacion = strtotime($enc['fecha_creacion']);
    if ($fechaDesde !== '') {
        $coincideFecha = ($fechaCreacion >= strtotime($fechaDesde));
    }
    if ($fechaHasta !== '' && $coincideFecha) {
        $coincideFecha = ($fechaCreacion <= strtotime($fechaHasta));
    }

    return $coincideBusqueda && $coincideEstado && $coincideFecha;
});

$totalEncuestas = count($encuestasFiltradas);
$encuestasFiltradas = array_slice($encuestasFiltradas, $offset, $porPagina);
$totalPaginas = ceil($totalEncuestas / $porPagina);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Encuestas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <!-- Librería para gráficos (opcional Chart.js o la que prefieras) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../app.js"></script>
    <script src="../ventanasDetalles.js"></script>
    <script src="../formularioEncuesta.js"></script> <!-- Asumiendo un JS para formularios de encuestas -->
    <script src="../marcadoresPopups.js"></script>
    <style>
    /* Estilos específicos para la vista de encuestas */
    .card-encuesta .encuesta-activa {
        color: var(--color-exito);
        font-weight: bold;
    }
    .card-encuesta .encuesta-finalizada {
        color: var(--color-error);
        font-weight: bold;
    }
    /* Contenedor gráfico */
    .grafico-participacion {
        max-width: 600px;
        margin: 1rem auto;
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

<main class="contenedor-app" style="padding: 1rem;">

    <!-- Botón para agregar nueva encuesta -->
    <a href="form_encuesta.php" class="btn-agregar-negocio">
        <i class="fa fa-plus"></i> Agregar Nueva Encuesta
    </a>

    <!-- Filtros -->
    <form action="" method="GET" class="contenedor-filtros" id="form-filtro-encuestas">
        <input type="text" name="busqueda" class="form-control" placeholder="Buscar..." 
               value="<?php echo htmlspecialchars($busqueda); ?>" />

        <select name="estado" class="form-control">
            <option value="">(Estado: Todos)</option>
            <option value="activo" <?php if($estado==='activo') echo 'selected';?>>Activas</option>
            <option value="finalizado" <?php if($estado==='finalizado') echo 'selected';?>>Finalizadas</option>
        </select>

        <label>Fecha desde:</label>
        <input type="date" name="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fechaDesde);?>">

        <label>Fecha hasta:</label>
        <input type="date" name="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fechaHasta);?>">

        <button type="submit" class="boton-buscar">
            <i class="fa fa-search"></i> Buscar
        </button>
    </form>

    <!-- Listado de encuestas -->
    <div class="contenedor-cards" id="lista-encuestas">
        <?php if (empty($encuestasFiltradas)): ?>
            <p>No se encontraron encuestas con los filtros especificados.</p>
        <?php else: ?>
            <?php foreach ($encuestasFiltradas as $enc): ?>
                <?php 
                    $fechaCreac = strtotime($enc['fecha_creacion']);
                    $fechaLim   = strtotime($enc['fecha_limite']);
                    $claseEstado = $enc['activa'] ? 'encuesta-activa' : 'encuesta-finalizada';
                ?>
                <div class="card-negocio card-encuesta" data-id="<?php echo $enc['id']; ?>">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($enc['titulo']); ?></h3>
                        <p><strong>Fecha creación:</strong> 
                           <?php echo date('d/m/Y H:i', $fechaCreac); ?></p>
                        <p><strong>Fecha límite:</strong> 
                           <?php echo date('d/m/Y H:i', $fechaLim); ?></p>
                        <p><strong>Preguntas:</strong> 
                           <?php echo $enc['num_preguntas']; ?></p>
                        <p><strong>Respuestas:</strong> 
                           <?php echo $enc['num_respuestas']; ?></p>
                        <span class="<?php echo $claseEstado; ?>">
                            <?php echo $enc['activa'] ? 'Encuesta activa' : 'Encuesta finalizada'; ?>
                        </span>
                    </div>
                    <div class="card-footer">
                        <button class="btn-editar" 
                                onclick="window.location.href='form_encuesta.php?id=<?php echo $enc['id']; ?>'">
                            <i class="fa fa-edit"></i> Editar
                        </button>
                        <button class="btn-ver-resultados" onclick="verResultados(<?php echo $enc['id']; ?>)">
                            <i class="fa fa-chart-bar"></i> Resultados
                        </button>
                        <button class="btn-ver-mapa" onclick="verEnMapaEncuesta(<?php echo $enc['id']; ?>)">
                            <i class="fa fa-map"></i> Mapa
                        </button>
                        <button class="btn-eliminar" onclick="confirmarEliminarEncuesta(<?php echo $enc['id']; ?>)">
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

    <!-- Gráfico resumido de participación (ejemplo simple) -->
    <div class="grafico-participacion">
        <h2>Resumen de Participación</h2>
        <canvas id="participacionChart"></canvas>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
// Ejemplo simple con Chart.js para el gráfico de participación
const ctx = document.getElementById('participacionChart').getContext('2d');
// Supongamos que tomamos las encuestas filtradas y graficamos sus respuestas
const labels = <?php echo json_encode(array_column($encuestasFiltradas, 'titulo')); ?>;
const dataRespuestas = <?php echo json_encode(array_column($encuestasFiltradas, 'num_respuestas')); ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Respuestas recibidas',
            data: dataRespuestas,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

function confirmarEliminarEncuesta(idEncuesta) {
    if (!confirm("¿Seguro que deseas eliminar esta encuesta?")) return;
    alert("Simulando la eliminación de la encuesta ID=" + idEncuesta);
}

function verResultados(idEncuesta) {
    alert("Mostrando resultados de la encuesta #" + idEncuesta);
}

function verEnMapaEncuesta(idEncuesta) {
    alert("Ver en mapa: encuesta #" + idEncuesta);
}
</script>
</body>
</html>
