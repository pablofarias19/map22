<?php
/**
 * utils/responder.php
 * Funciones helper para enviar respuestas JSON estandarizadas desde la API.
 */

if (!function_exists('responder')) {
    /**
     * Envía una respuesta JSON exitosa (código 200 OK) y termina el script.
     * @param mixed $datos Datos a incluir en la respuesta. Si es array, se asegura 'success' => true.
     * @param int $codigoHttp Código de estado HTTP (por defecto 200).
     */
    function responder($datos, int $codigoHttp = 200): void {
        if (is_array($datos) && !isset($datos['success'])) {
            // Añadir 'success' = true por defecto a arrays si no existe
             $datos = array_merge(['success' => true], $datos);
        } elseif (!is_array($datos)) {
             // Si no es un array, envolverlo (ej. para devolver una simple lista)
             $datos = ['success' => true, 'data' => $datos];
        }
        // Si ya tiene 'success' (ej. viniendo de un controlador que ya lo puso), no se toca

        http_response_code($codigoHttp);
        // Asegurar que el Content-Type ya esté establecido o establecerlo aquí si es necesario
        if (!headers_sent()) {
             header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($datos);
        exit;
    }
}

if (!function_exists('responderError')) {
    /**
     * Envía una respuesta JSON de error, establece el código HTTP y termina el script.
     * @param string $mensaje Mensaje de error principal.
     * @param int $codigo Código de estado HTTP (ej. 400, 401, 404, 500).
     * @param array $detalles Detalles adicionales (ej. errores de validación).
     */
    function responderError(string $mensaje, int $codigo = 400, array $detalles = []): void {
        http_response_code($codigo);
        $respuesta = ['success' => false, 'error' => $mensaje];
        if (!empty($detalles)) {
            $respuesta['detalles'] = $detalles;
        }
         // Asegurar que el Content-Type ya esté establecido o establecerlo aquí si es necesario
        if (!headers_sent()) {
             header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($respuesta);
        exit;
    }
}

?>