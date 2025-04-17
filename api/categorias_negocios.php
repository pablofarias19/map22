<?php
declare(strict_types=1);

/**
 * api/categorias_negocios.php
 * Devuelve la lista de categorías de negocios en formato JSON.
 *
 * Salidas posibles
 *  - 200 OK           : [ { id:int, nombre:string, emoji:string, color:string, ... }, ... ]
 *  - 4xx / 5xx + JSON : { "mensaje" : string }
 */

// -----------------------------------------------------------------------------
//  CARGA DE DEPENDENCIAS                                                            
// -----------------------------------------------------------------------------

// __DIR__ garantiza que la ruta sea correcta, sin importar el directorio de trabajo
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/NegocioController.php';
require_once __DIR__ . '/../utils/responder.php';    // Debe exponer Responder::json(), ::error()

use Utils\Responder;   // ↳ utils/responder.php debe declarar el namespace Utils; ajusta si es distinto

// -----------------------------------------------------------------------------
//  CONFIGURACIÓN DE ENCABEZADOS CORS + JSON                                         
// -----------------------------------------------------------------------------
Responder::jsonHeaders(allowedMethods: ['GET', 'OPTIONS']); //  Content‑Type + CORS

// Pre‑flight OPTIONS — termina aquí
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// Sólo aceptamos GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Responder::error('Método no permitido. Use GET.', 405);
}

// -----------------------------------------------------------------------------
//  LÓGICA PRINCIPAL                                                                 
// -----------------------------------------------------------------------------
try {
    // 1. Conectar a la BD
    $db = (new Database())->getConnection();

    // 2. Instanciar el controlador
    $controller = new NegocioController($db);

    // 3. Validar que exista el método requerido
    if (!method_exists($controller, 'obtenerCategoriasNegocios')) {
        // 501 Not Implemented describe mejor que 500 en este caso
        throw new RuntimeException('Método obtenerCategoriasNegocios() no implementado en NegocioController.', 501);
    }

    // 4. Recuperar datos
    $categorias = $controller->obtenerCategoriasNegocios();

    // 5. Responder OK
    Responder::json($categorias); // Devuelve HTTP 200 y JSON

} catch (PDOException $e) {
    error_log('[API categorias_negocios] DB error: ' . $e->getMessage());
    Responder::error('Error de base de datos.', 503);   // Service Unavailable

} catch (Throwable $e) {
    error_log('[API categorias_negocios] ' . $e->getMessage());
    $code = $e->getCode();
    // Garantiza que el código sea HTTP válido (4xx–5xx); si no, fuerza 500
    if ($code < 400 || $code >= 600) { $code = 500; }
    Responder::error('Error interno al obtener las categorías.', $code);
}
