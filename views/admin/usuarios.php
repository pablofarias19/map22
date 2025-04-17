
<?php
/**
 * admin/usuarios.php – Gestión de usuarios
 * Última revisión automática: 2025-04-16 19:04:01
 */
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
if (!function_exists('isAdmin') || !isAdmin()) {
    header('Location: ../login.php?error=no_permisos');
    exit;
}

// Dummy data (reemplaza por tu modelo/BD)
$usuarios = [
    [ 'id'=>1, 'nombre'=>'Juan Pérez', 'email'=>'juan@example.com','fecha_registro'=>'2025-01-01','ultimo_acceso'=>'2025-01-10','estado'=>'activo','tipo'=>'usuario' ],
    [ 'id'=>2, 'nombre'=>'Ana Gómez', 'email'=>'ana@example.com','fecha_registro'=>'2025-01-02','ultimo_acceso'=>'2025-01-09','estado'=>'activo','tipo'=>'comerciante' ],
    [ 'id'=>3, 'nombre'=>'Carlos Admin', 'email'=>'admin@example.com','fecha_registro'=>'2025-01-03','ultimo_acceso'=>'2025-01-11','estado'=>'activo','tipo'=>'admin' ],
];
$statsUsuarios = ['total'=>count($usuarios), 'activos'=>2, 'inactivos'=>0, 'administradores'=>1];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios · Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/estilos-mapa-comercial.css">
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
    <div class="admin-container">
        <aside class="admin-sidebar">
            <h3>Menú Admin</h3>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a class="activa" href="usuarios.php">Usuarios</a></li>
                    <li><a href="categorias.php">Categorías</a></li>
                </ul>
            </nav>
        </aside>

        <section class="admin-content">
            <h2>Gestión de Usuarios</h2>
            <p>Totales: <?= $statsUsuarios['total'] ?> | Activos: <?= $statsUsuarios['activos'] ?> | Admins: <?= $statsUsuarios['administradores'] ?></p>

            <form id="frmFiltro" style="display:flex;gap:.5rem;">
                <input type="text" name="q" placeholder="Nombre o email">
                <select name="estado">
                    <option value="">Estado</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
                <select name="tipo">
                    <option value="">Tipo</option>
                    <option value="usuario">Usuario</option>
                    <option value="comerciante">Comerciante</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit">Filtrar</button>
            </form>

            <div style="margin:1rem 0;">
                <button id="btnExportCsv">Exportar CSV</button>
                <button id="btnExportXls">Exportar Excel</button>
            </div>

            <table class="tabla-usuarios">
                <thead>
                    <tr>
                        <th>ID</th><th>Nombre</th><th>Email</th><th>F. Registro</th>
                        <th>Último Acceso</th><th>Estado</th><th>Tipo</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr data-id="<?= $u['id'] ?>">
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= $u['fecha_registro'] ?></td>
                            <td><?= $u['ultimo_acceso'] ?></td>
                            <td><?= $u['estado'] ?></td>
                            <td><?= $u['tipo'] ?></td>
                            <td>
                                <button class="ver">👁️</button>
                                <button class="toggle">↩️</button>
                                <button class="tipo">🛠️</button>
                                <button class="eliminar">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?= date('Y') ?> · GeoComercial</p>
</footer>

<script>
document.getElementById('btnExportCsv').addEventListener('click', () => {
    window.location.href = '../api/usuarios.php?format=csv';
});
</script>
</body>
</html>
