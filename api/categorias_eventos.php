<?php
declare(strict_types=1);

/**
 * api/categorias_eventos.php
 * Devuelve la lista de categorías de eventos en formato JSON.
 *
 * Salidas aceptadas
 *  ├─ 200 OK   →  [ { id:int, nombre:string, emoji:string, color:string, ... }, ... ]
 *  └─ Error    →  { "mensaje" : string }
 */

// -----------------------------------------------------------------------------
//  DEPENDENCIAS
// -----------------------------------------------------------------------------

/* 
 * Usamos __DIR__ para que los includes funcionen desde cualquier punto de la raíz. 
 * Si cambias de estructura solo ajusta la ruta relativa.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/EventoController.php';
// Helper opcional para las respuestas JSON (define Responder::success / ::error)
$helper = __DIR__ . '/../utils/responder.php';
if (is_file($helper)) {
    require_once $helper;
} else {
    /**
     * Fallback mínimo si el helper no existe.
     */
    class Responder {
        public static function success(mixed $data, int $code = 200): void {
            http_response_code($code);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        public static function error(string $mensaje, int $code = 500): void {
            http_response_code($code);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['mensaje' => $mensaje], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// -----------------------------------------------------------------------------
//  CABECERAS & CORS
// -----------------------------------------------------------------------------
header('Access-Control-Allow-Origin: *');          // Ajusta o restringe dominios en producción
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Pre‑flight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -----------------------------------------------------------------------------
//  VALIDACIÓN DEL MÉTODO
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error('Método no permitido. Solo se acepta GET.', 405);
}

// -----------------------------------------------------------------------------
//  LÓGICA PRINCIPAL
// -----------------------------------------------------------------------------
try {
    // 1) Conectar DB
    $database = new Database();
    $db = $database->getConnection();

    // 2) Instanciar controlador
    $controller = new EventoController($db);

    // 3) Verificar que exista el método en el controlador
    if (!method_exists($controller, 'obtenerCategoriasEventos')) {
        Responder::error('Función no implementada en EventoController.', 501);
    }

    // 4) Obtener datos
    $categorias = $controller->obtenerCategoriasEventos();

    // 5) Responder OK
    Responder::success($categorias, 200);

} catch (PDOException $e) {
    // Problemas de base de datos (error ya logueado en Database.php)
    Responder::error('Error de conexión a la base de datos.', 503);
} catch (Throwable $e) {
    // Cualquier otro error
    error_log('Error en api/categorias_eventos.php: ' . $e->getMessage());
    $codigo = $e->getCode();
    if ($codigo < 400 || $codigo >= 600) {
        $codigo = 500; // Interno por defecto
    }
    Responder::error('Error interno al obtener las categorías de eventos.', $codigo);
}
