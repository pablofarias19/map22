<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "✅ Conexión exitosa a la base de datos: " . $db->getDbName();
    error_log("[DB] Prueba OK: Conexión exitosa.");
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
    error_log("[DB] Prueba ERROR: " . $e->getMessage());
}
