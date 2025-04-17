<?php
// api/encuestas-respuestas.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/EncuestaController.php';

header('Content-Type: application/json');

// Inicializar la conexión a la base de datos
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error de conexión a la base de datos.']);
    exit;
}

$controller = new EncuestaController($pdo);
$resultado = null;
$httpStatus = 200;

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        // Obtener datos - ¿Vienen como form-data o JSON?
        // Si es form-data (HTML form normal):
        $datosRespuestas = $_POST;
        // Si es JSON enviado en el body (descomentado para permitir ambos formatos):
        if (empty($datosRespuestas)) {
            $json = file_get_contents('php://input');
            $datosRespuestas = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON inválido recibido.", 400);
            }
        }

        if (empty($datosRespuestas)) {
             $httpStatus = 400;
             $resultado = ['exito' => false, 'mensaje' => 'No se recibieron datos de respuesta.'];
        } else {
            $idUsuarioActual = obtenerIdUsuarioAutenticado(); 
            $resultado = $controller->guardarRespuestas($datosRespuestas, $idUsuarioActual);
        }

    } else {
        $httpStatus = 405;
        $resultado = ['exito' => false, 'mensaje' => 'Método no permitido. Use POST.'];
    }

} catch (Throwable $e) {
    error_log("Error fatal guardando respuestas: " . $e->getMessage());
    $httpStatus = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    $resultado = ['exito' => false, 'mensaje' => $e->getMessage()];
}

// Establecer código HTTP y devolver JSON
if (isset($resultado['status'])) {
    $httpStatus = $resultado['status'];
} elseif (!$resultado['exito']) {
    $httpStatus = $httpStatus === 200 ? 500 : $httpStatus;
}
http_response_code($httpStatus);
echo json_encode($resultado);
exit;

// --- Funciones auxiliares ---
function obtenerIdUsuarioAutenticado(): ?int {
    // Obtener token de cabecera Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    
    // Validar el token JWT y extraer el ID de usuario
    try {
        // Requiere la clase de autenticación
        require_once __DIR__ . '/../utils/Autenticacion.php';
        
        global $pdo; // Usar la conexión PDO ya establecida
        $auth = new Autenticacion($pdo);
        $usuario = $auth->obtenerUsuarioDesdeToken($token);
        
        return $usuario ? $usuario['id'] : null;
    } catch (Exception $e) {
        error_log("Error al verificar token: " . $e->getMessage());
        return null;
    }
}
?>