<?php
// Script para visualizar las relaciones entre tablas en tu base de datos

// Configuración de base de datos
require_once '../config/database.php';

// Conexión a la base de datos
try {
    $conn = new PDO("mysql:host={$db_config['host']};dbname={$db_config['dbname']}", $db_config['user'], $db_config['password']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Consultar todas las tablas
    $stmt_tables = $conn->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        ORDER BY table_name
    ");
    $tablas = $stmt_tables->fetchAll(PDO::FETCH_COLUMN);
    
    // Consultar relaciones (claves foráneas)
    $stmt_relations = $conn->query("
        SELECT 
            table_name AS tabla_origen,
            column_name AS columna_origen,
            referenced_table_name AS tabla_destino,
            referenced_column_name AS columna_destino
        FROM 
            information_schema.key_column_usage
        WHERE 
            table_schema = DATABASE() 
            AND referenced_table_name IS NOT NULL
        ORDER BY 
            table_name, column_name
    ");
    $relaciones = $stmt_relations->fetchAll(PDO::FETCH_ASSOC);
    
    // Estructura de cada tabla
    $estructuras = [];
    foreach ($tablas as $tabla) {
        $stmt_estructura = $conn->query("DESCRIBE `$tabla`");
        $estructuras[$tabla] = $stmt_estructura->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estructura de Base de Datos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .relacion { margin-bottom: 5px; }
        .diagrama { margin: 30px 0; padding: 20px; border: 1px solid #ddd; background: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Estructura de Base de Datos</h1>
    
    <h2>Lista de Tablas</h2>
    <ul>
        <?php foreach ($tablas as $tabla): ?>
            <li><a href="#<?= htmlspecialchars($tabla) ?>"><?= htmlspecialchars($tabla) ?></a></li>
        <?php endforeach; ?>
    </ul>
    
    <h2>Relaciones entre Tablas</h2>
    <?php if (empty($relaciones)): ?>
        <p>No se encontraron relaciones definidas mediante claves foráneas.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($relaciones as $rel): ?>
                <li class="relacion">
                    <strong><?= htmlspecialchars($rel['tabla_origen']) ?></strong>.<em><?= htmlspecialchars($rel['columna_origen']) ?></em>
                    → 
                    <strong><?= htmlspecialchars($rel['tabla_destino']) ?></strong>.<em><?= htmlspecialchars($rel['columna_destino']) ?></em>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <h2>Estructura Detallada por Tabla</h2>
    <?php foreach ($estructuras as $tabla => $campos): ?>
        <h3 id="<?= htmlspecialchars($tabla) ?>"><?= htmlspecialchars($tabla) ?></h3>
        <table>
            <tr>
                <th>Campo</th>
                <th>Tipo</th>
                <th>Nulo</th>
                <th>Clave</th>
                <th>Predeterminado</th>
                <th>Extra</th>
            </tr>
            <?php foreach ($campos as $campo): ?>
                <tr>
                    <td><?= htmlspecialchars($campo['Field']) ?></td>
                    <td><?= htmlspecialchars($campo['Type']) ?></td>
                    <td><?= htmlspecialchars($campo['Null']) ?></td>
                    <td><?= htmlspecialchars($campo['Key']) ?></td>
                    <td><?= htmlspecialchars($campo['Default'] ?? '') ?></td>
                    <td><?= htmlspecialchars($campo['Extra']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
    
    <div class="diagrama">
        <h2>Diagrama de Relaciones Simplificado</h2>
        <p>Para un diagrama visual completo, considera usar herramientas como phpMyAdmin, MySQL Workbench o dbdiagram.io.</p>
        <pre>
<?php
    // Crear un diagrama de texto simple
    $diagramText = "";
    foreach ($relaciones as $rel) {
        $diagramText .= "{$rel['tabla_origen']} ({$rel['columna_origen']}) → {$rel['tabla_destino']} ({$rel['columna_destino']})\n";
    }
    echo htmlspecialchars($diagramText);
?>
        </pre>
    </div>
</body>
</html>
