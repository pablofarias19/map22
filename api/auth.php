<?php
// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Dependencias ---
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Autenticacion.php';
require_once __DIR__ . '/../utils/Validator.php';

// --- CORS y encabezados HTTP ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// --- Preflight (OPTIONS) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Inicialización de clases ---
try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Autenticacion($db);
} catch (PDOException $e) {
    responderError('Error interno al conectar con la base de datos.', 503);
} catch (Exception $e) {
    error_log("Error en auth.php: " . $e->getMessage());
    responderError('Error interno durante la inicialización.', 500);
}

// --- Enrutamiento general ---
$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

try {
    switch ($metodo) {
        case 'POST':
            $datos = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Datos JSON inválidos.', 400);
            }

            switch ($accion) {
                case 'registro': manejarRegistro($auth, $datos); break;
                case 'login': manejarLogin($auth, $datos); break;
                case 'recuperar_password': manejarRecuperarPassword($auth, $datos); break;
                case 'cambiar_password': manejarCambiarPassword($auth, $datos); break;
                default: responderError('Acción POST no válida.', 400); break;
            }
            break;

        case 'GET':
            switch ($accion) {
                case 'verificar': verificarToken($auth); break;
                case 'activar': activarCuenta($auth); break;
                default: responderError('Acción GET no válida.', 400); break;
            }
            break;

        default:
            responderError('Método HTTP no permitido.', 405);
    }
} catch (Exception $e) {
    error_log("Error en auth.php: " . $e->getMessage());
    $codigo = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    responderError($e->getMessage(), $codigo);
}


// --- Funciones manejadoras ---

function manejarRegistro(Autenticacion $auth, ?array $datos): void {
    if (!$datos) responderError('Faltan datos para el registro.', 400);

    $validator = new Validator();
    $reglas = [
        'nombre' => 'required|min:3|max:100',
        'email' => 'required|email|max:100|unique:usuarios,email',
        'password' => 'required|min:8|max:100',
        'confirmar_password' => 'required|matches:password',
        'telefono' => 'max:50'
    ];
    $errores = $validator->validar($datos, $reglas, $auth->getDbConnection());

    if ($errores) {
        responderError('Datos inválidos.', 400, ['errores' => $errores]);
    }

    if ($auth->registrarUsuario($datos)) {
        responder(['success' => true, 'mensaje' => 'Registro exitoso. Revisa tu correo para activar la cuenta.']);
    } else {
        responderError('Error en el registro. Intenta más tarde.', 500);
    }
}

function manejarLogin(Autenticacion $auth, ?array $datos): void {
    if (!$datos || empty($datos['email']) || empty($datos['password'])) {
        responderError('Email y contraseña requeridos.', 400);
    }

    $resultado = $auth->iniciarSesion($datos['email'], $datos['password']);

    if ($resultado && isset($resultado['token'])) {
        responder([
            'success' => true,
            'token' => $resultado['token'],
            'usuario' => [
                'id' => $resultado['id'],
                'nombre' => $resultado['nombre'],
                'email' => $resultado['email'],
                'tipo' => $resultado['tipo_usuario'] ?? 'usuario'
            ]
        ]);
    } else {
        responderError('Credenciales inválidas o cuenta inactiva.', 401);
    }
}

function manejarRecuperarPassword(Autenticacion $auth, ?array $datos): void {
    if (!$datos || empty($datos['email'])) {
        responderError('Email requerido.', 400);
    }

    $validator = new Validator();
    $errores = $validator->validar($datos, ['email' => 'required|email']);
    if ($errores) responderError('Email inválido.', 400, ['errores' => $errores]);

    $auth->solicitarRestablecimientoPassword($datos['email']);
    responder(['success' => true, 'mensaje' => 'Si el email existe, recibirás instrucciones.']);
}

function manejarCambiarPassword(Autenticacion $auth, ?array $datos): void {
    if (!$datos) responderError('Datos requeridos.', 400);

    $validator = new Validator();

    if (isset($datos['token'])) {
        $reglas = [
            'token' => 'required',
            'nueva_password' => 'required|min:8|max:100',
            'confirmar_password' => 'required|matches:nueva_password'
        ];
        $errores = $validator->validar($datos, $reglas);
        if ($errores) responderError('Datos inválidos.', 400, ['errores' => $errores]);

        if ($auth->cambiarPasswordConToken($datos['token'], $datos['nueva_password'])) {
            responder(['success' => true, 'mensaje' => 'Contraseña actualizada.']);
        } else {
            responderError('Token inválido o expirado.', 400);
        }
    } else {
        $reglas = [
            'password_actual' => 'required',
            'nueva_password' => 'required|min:8|max:100',
            'confirmar_password' => 'required|matches:nueva_password'
        ];
        $errores = $validator->validar($datos, $reglas);
        if ($errores) responderError('Datos inválidos.', 400, ['errores' => $errores]);

        $usuario = $auth->obtenerUsuarioDesdeToken();
        if (!$usuario) responderError('Autenticación requerida.', 401);

        if ($auth->cambiarPassword($usuario['id'], $datos['password_actual'], $datos['nueva_password'])) {
            responder(['success' => true, 'mensaje' => 'Contraseña actualizada.']);
        } else {
            responderError('Contraseña actual incorrecta.', 400);
        }
    }
}

function verificarToken(Autenticacion $auth): void {
    $usuario = $auth->obtenerUsuarioDesdeToken();
    if ($usuario) {
        responder(['success' => true, 'usuario' => $usuario]);
    } else {
        responderError('Token inválido o expirado.', 401);
    }
}

function activarCuenta(Autenticacion $auth): void {
    $token = $_GET['token'] ?? '';
    if (!$token) responderError('Token requerido.', 400);

    if ($auth->verificarUsuario($token)) {
        responder(['success' => true, 'mensaje' => 'Cuenta activada. Ya puedes iniciar sesión.']);
    } else {
        responderError('Token inválido o expirado.', 400);
    }
}

function responder(array $datos): void {
    if (!isset($datos['success'])) $datos['success'] = true;
    http_response_code(200);
    echo json_encode($datos);
    exit;
}

function responderError(string $mensaje, int $codigo = 400, array $extras = []): void {
    http_response_code($codigo);
    $respuesta = ['success' => false, 'error' => $mensaje];
    if (!empty($extras)) $respuesta = array_merge($respuesta, $extras);
    echo json_encode($respuesta);
    exit;
}
?>
