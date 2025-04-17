
<?php
/**
 * admin/categorias.php – Gestión de categorías
 * Última revisión automática: 2025-04-16 19:04:01
 */
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
if (!function_exists('isAdmin') || !isAdmin()) {
    header('Location: ../login.php?error=no_permisos');
    exit;
}

// DEMO data ---------------------------------------------------------------
$categoriasNegocios = [
    ['id'=>1,'nombre'=>'Restaurantes','descripcion'=>'Comida y bebida','emoji'=>'🍽️','color'=>'#FF5733','entidades'=>42],
    ['id'=>2,'nombre'=>'Tiendas','descripcion'=>'Ropa y accesorios','emoji'=>'🛍️','color'=>'#33A8FF','entidades'=>56],
];
$categoriasEventos = [
    ['id'=>1,'nombre'=>'Música','descripcion'=>'Conciertos','emoji'=>'🎸','color'=>'#FF3366','entidades'=>12],
    ['id'=>2,'nombre'=>'Deportivo','descripcion'=>'Carreras','emoji'=>'🏃','color'=>'#33FF66','entidades'=>9],
];
$areasProduccion = [
    ['id'=>1,'nombre'=>'Agricultura','descripcion'=>'Cultivo','emoji'=>'🌾','color'=>'#66FF33','entidades'=>8],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Categorías · Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/estilos-mapa-comercial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://unpkg.com/axios@1.6.7/dist/axios.min.js" defer></script>
</head>
<body>
<header class="cabecera-app">
    <div class="logo-app">
        <img src="../assets/images/logo.png" alt="Logo" width="40" height="40">
        <span>Admin · GeoComercial</span>
    </div>
</header>

<main class="contenedor-app" style="padding:1rem;">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <nav>
                <ul class="menu-nav">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="usuarios.php">Usuarios</a></li>
                    <li><a class="activa" href="categorias.php">Categorías</a></li>
                </ul>
            </nav>
        </aside>

        <section class="admin-content">
            <h2>Categorías de Negocios</h2>
            <table class="tabla-categorias" id="tabla_negocios">
                <thead>
                    <tr>
                        <th>ID</th><th>Nombre</th><th>Descripción</th><th>Emoji</th>
                        <th>Color</th><th>#Entidades</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoriasNegocios as $cat): ?>
                        <tr data-id="<?= $cat['id'] ?>" data-tipo="negocio">
                            <td><?= $cat['id'] ?></td>
                            <td><?= htmlspecialchars($cat['nombre']) ?></td>
                            <td><?= htmlspecialchars($cat['descripcion']) ?></td>
                            <td><?= htmlspecialchars($cat['emoji']) ?></td>
                            <td><span class="color-preview" style="background:<?= $cat['color'] ?>;"></span><?= $cat['color'] ?></td>
                            <td><?= $cat['entidades'] ?></td>
                            <td>
                                <button class="accion-btn editar" title="Editar"><i class="fas fa-pen"></i></button>
                                <button class="accion-btn eliminar" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form id="form_nueva_negocio" class="form-categoria">
                <h4>Nueva categoría de negocio</h4>
                <input type="text" name="nombre" placeholder="Nombre" required>
                <input type="text" name="descripcion" placeholder="Descripción" required>
                <input type="text" name="emoji" placeholder="Emoji" maxlength="5" required>
                <input type="color" name="color" value="#33A8FF" required>
                <button type="submit" class="boton-aplicar">Añadir</button>
            </form>

            <hr>
            <h2>Categorías de Eventos</h2>
            <table class="tabla-categorias" id="tabla_eventos">
                <thead>
                    <tr>
                        <th>ID</th><th>Nombre</th><th>Descripción</th><th>Emoji</th>
                        <th>Color</th><th>#Entidades</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoriasEventos as $cat): ?>
                        <tr data-id="<?= $cat['id'] ?>" data-tipo="evento">
                            <td><?= $cat['id'] ?></td>
                            <td><?= htmlspecialchars($cat['nombre']) ?></td>
                            <td><?= htmlspecialchars($cat['descripcion']) ?></td>
                            <td><?= htmlspecialchars($cat['emoji']) ?></td>
                            <td><span class="color-preview" style="background:<?= $cat['color'] ?>;"></span><?= $cat['color'] ?></td>
                            <td><?= $cat['entidades'] ?></td>
                            <td>
                                <button class="accion-btn editar" title="Editar"><i class="fas fa-pen"></i></button>
                                <button class="accion-btn eliminar" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form id="form_nueva_evento" class="form-categoria">
                <h4>Nueva categoría de evento</h4>
                <input type="text" name="nombre" placeholder="Nombre" required>
                <input type="text" name="descripcion" placeholder="Descripción" required>
                <input type="text" name="emoji" placeholder="Emoji" maxlength="5" required>
                <input type="color" name="color" value="#FF3366" required>
                <button type="submit" class="boton-aplicar">Añadir</button>
            </form>

            <hr>
            <h2>Áreas de Producción</h2>
            <table class="tabla-categorias" id="tabla_areas">
                <thead>
                    <tr>
                        <th>ID</th><th>Nombre</th><th>Descripción</th><th>Emoji</th>
                        <th>Color</th><th>#Entidades</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($areasProduccion as $area): ?>
                        <tr data-id="<?= $area['id'] ?>" data-tipo="area">
                            <td><?= $area['id'] ?></td>
                            <td><?= htmlspecialchars($area['nombre']) ?></td>
                            <td><?= htmlspecialchars($area['descripcion']) ?></td>
                            <td><?= htmlspecialchars($area['emoji']) ?></td>
                            <td><span class="color-preview" style="background:<?= $area['color'] ?>;"></span><?= $area['color'] ?></td>
                            <td><?= $area['entidades'] ?></td>
                            <td>
                                <button class="accion-btn editar" title="Editar"><i class="fas fa-pen"></i></button>
                                <button class="accion-btn eliminar" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form id="form_nueva_area" class="form-categoria">
                <h4>Nueva área de producción</h4>
                <input type="text" name="nombre" placeholder="Nombre" required>
                <input type="text" name="descripcion" placeholder="Descripción" required>
                <input type="text" name="emoji" placeholder="Emoji" maxlength="5" required>
                <input type="color" name="color" value="#66FF33" required>
                <button type="submit" class="boton-aplicar">Añadir</button>
            </form>
        </section>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?= date('Y') ?> · GeoComercial</p>
</footer>

<script>
// Helpers
const qs  = (sel, ctx=document) => ctx.querySelector(sel);
const qsa = (sel, ctx=document) => [...ctx.querySelectorAll(sel)];
</script>
</body>
</html>
