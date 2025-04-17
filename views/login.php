<?php
// login.php
// Formulario de inicio de sesión

require_once '../auth.php';
if (isAuthenticated()) {
    header('Location: perfil.php'); // Si ya está autenticado, redirigir
    exit;
}

// Procesar envío (ejemplo simulado)
// if ($_SERVER['REQUEST_METHOD'] === 'POST') { ... }

?>
<!DOCTYPE html>
<html lang="es"><?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Requerir dependencias (ajusta las rutas según tu estructura)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Autenticacion.php';
require_once __DIR__ . '/../utils/Validator.php';

// --- Configuración de Encabezados ---
// Permitir acceso desde cualquier origen (ajustar para producción)
header('Access-Control-Allow-Origin: *');
// Métodos HTTP permitidos
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// Encabezados permitidos (incluyendo Authorization para JWT)
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// Tipo de contenido de la respuesta
header('Content-Type: application/json; charset=UTF-8');

// --- Inicialización ---
$database = null;
$db = null;
$auth = null;

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Autenticacion($db); // Asume que Autenticacion recibe la conexión PDO
} catch (PDOException $e) {
    // Error crítico al conectar con la base de datos
    // El error ya fue logueado por la clase Database
    responderError('Error interno del servidor al conectar con la base de datos.', 503); // 503 Service Unavailable
} catch (Exception $e) {
     // Otro error durante la inicialización
     error_log("Error de inicialización en auth.php: " . $e->getMessage());
     responderError('Error interno del servidor durante la inicialización.', 500);
}


// --- Manejo de Petición ---

// Obtener el método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Manejar solicitud preflight CORS (OPTIONS)
if ($metodo === 'OPTIONS') {
    http_response_code(200); // OK
    exit(0);
}

// Obtener acción solicitada (si existe)
$accion = $_GET['accion'] ?? ''; // Usar ?? para evitar notice si no existe

// Enrutador principal
try {
    switch ($metodo) {
        case 'POST':
            // Leer datos del cuerpo (asumiendo JSON)
            $datos = json_decode(file_get_contents('php://input'), true);
            // Verificar si json_decode falló o no es un array
             if (json_last_error() !== JSON_ERROR_NONE && $metodo === 'POST') {
                 // Lanzar error si se esperaba un cuerpo JSON válido
                 throw new Exception('Datos JSON inválidos en la solicitud.', 400);
             }

            switch ($accion) {
                case 'registro':
                    manejarRegistro($auth, $datos);
                    break;
                case 'login':
                    manejarLogin($auth, $datos);
                    break;
                case 'recuperar_password':
                    manejarRecuperarPassword($auth, $datos);
                    break;
                case 'cambiar_password':
                    manejarCambiarPassword($auth, $datos);
                    break;
                default:
                    responderError('Acción POST no válida.', 400);
                    break;
            }
            break;

        case 'GET':
            switch ($accion) {
                case 'verificar': // Verificar token de sesión
                    verificarToken($auth);
                    break;
                case 'activar': // Activar cuenta con token GET
                    activarCuenta($auth);
                    break;
                default:
                    responderError('Acción GET no válida.', 400);
                    break;
            }
            break;

        // Podrías añadir casos para PUT, DELETE si fueran necesarios aquí

        default:
            responderError('Método HTTP no permitido.', 405); // 405 Method Not Allowed
            break;
    }
} catch (Exception $e) {
    // Capturar excepciones lanzadas intencionalmente con código (ej. validación)
    // o excepciones inesperadas durante el procesamiento.
    error_log("Error procesando petición en auth.php: " . $e->getMessage());
    $codigoError = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500; // Usar código de excepción si es válido, si no 500
    responderError($e->getMessage(), $codigoError);
}


// --- Funciones Manejadoras ---

/**
 * Manejar solicitud de registro de usuario.
 * @param Autenticacion $auth Instancia de Autenticacion.
 * @param array|null $datos Datos decodificados del cuerpo JSON.
 */
function manejarRegistro(Autenticacion $auth, ?array $datos): void {
    if ($datos === null) {
        responderError('No se recibieron datos para el registro.', 400);
        return;
    }

    // Validar datos
    $validator = new Validator();
    $reglasValidacion = [
        'nombre' => 'required|min:3|max:100',
        'email' => 'required|email|max:100|unique:usuarios,email', // Añadir unique si Validator lo soporta
        'password' => 'required|min:8|max:100',
        'confirmar_password' => 'required|matches:password',
        'telefono' => 'max:50' // Ajusta longitud si es necesario
    ];
    $errores = $validator->validar($datos, $reglasValidacion, $auth->getDbConnection()); // Pasar DB si 'unique' la necesita

    if (!empty($errores)) {
        responderError('Datos de registro inválidos.', 400, ['errores' => $errores]);
        return;
    }

    // Intentar registro (Autenticacion debe manejar el hashing y la creación de token de activación)
    $resultado = $auth->registrarUsuario($datos);

    if ($resultado) {
        responder([
            'success' => true,
            'mensaje' => 'Registro exitoso. Se ha enviado un correo para activar tu cuenta.'
        ]);
    } else {
        // Asumir que Autenticacion podría haber fallado por otras razones (ej. envío de email)
        responderError('No se pudo completar el registro. Inténtalo de nuevo más tarde.', 500);
    }
}

/**
 * Manejar solicitud de inicio de sesión.
 * @param Autenticacion $auth Instancia de Autenticacion.
 * @param array|null $datos Datos decodificados del cuerpo JSON.
 */
function manejarLogin(Autenticacion $auth, ?array $datos): void {
    if ($datos === null || empty($datos['email']) || empty($datos['password'])) {
        responderError('Se requiere correo electrónico y contraseña.', 400);
        return;
    }

    // Intentar login (Autenticacion debe verificar hash, estado activo, y generar JWT)
    $resultado = $auth->iniciarSesion($datos['email'], $datos['password']);

    if ($resultado && isset($resultado['token'])) {
        responder([
            'success' => true,
            'token' => $resultado['token'], // El token JWT
            'usuario' => [ // Datos básicos del usuario para el frontend
                'id' => $resultado['id'],
                'nombre' => $resultado['nombre'],
                'email' => $resultado['email'],
                'tipo' => $resultado['tipo_usuario'] ?? 'usuario' // Tipo/rol del usuario
            ]
        ]);
    } else {
        // Error genérico para no diferenciar entre usuario no existe, inactivo o contraseña incorrecta
        responderError('Credenciales inválidas o cuenta no activada.', 401); // 401 Unauthorized
    }
}

/**
 * Manejar solicitud de recuperación de contraseña.
 * @param Autenticacion $auth Instancia de Autenticacion.
 * @param array|null $datos Datos decodificados del cuerpo JSON.
 */
function manejarRecuperarPassword(Autenticacion $auth, ?array $datos): void {
    if ($datos === null || empty($datos['email'])) {
        responderError('Se requiere correo electrónico.', 400);
        return;
    }

    // Validar formato email
    $validator = new Validator();
    $errores = $validator->validar($datos, ['email' => 'required|email']);
    if (!empty($errores)) {
        responderError('Formato de correo electrónico inválido.', 400, ['errores' => $errores]);
        return;
    }

    // Solicitar restablecimiento (Autenticacion debe generar token y enviar email)
    $auth->solicitarRestablecimientoPassword($datos['email']);

    // Siempre responder éxito para seguridad (no revelar existencia de emails)
    responder([
        'success' => true,
        'mensaje' => 'Si el correo electrónico está registrado, recibirás instrucciones para restablecer tu contraseña en breve.'
    ]);
}

/**
 * Manejar solicitud de cambio de contraseña (con token o contraseña actual).
 * @param Autenticacion $auth Instancia de Autenticacion.
 * @param array|null $datos Datos decodificados del cuerpo JSON.
 */
function manejarCambiarPassword(Autenticacion $auth, ?array $datos): void {
    if ($datos === null) {
        responderError('No se recibieron datos.', 400);
        return;
    }

    $validator = new Validator();

    if (isset($datos['token'])) {
        // --- Cambio con Token de Restablecimiento ---
        $reglas = [
            'token' => 'required',
            'nueva_password' => 'required|min:8|max:100',
            'confirmar_password' => 'required|matches:nueva_password'
        ];
        $errores = $validator->validar($datos, $reglas);
        if (!empty($errores)) {
            responderError('Datos inválidos para cambiar contraseña con token.', 400, ['errores' => $errores]);
            return;
        }

        // Cambiar usando el token (Autenticacion debe validar token y actualizar hash)
        $resultado = $auth->cambiarPasswordConToken($datos['token'], $datos['nueva_password']);
        if ($resultado) {
            responder(['success' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
        } else {
            responderError('No se pudo cambiar la contraseña. El token puede ser inválido o haber expirado.', 400);
        }

    } else {
        // --- Cambio con Contraseña Actual (Usuario Autenticado) ---
         $reglas = [
            'password_actual' => 'required',
            'nueva_password' => 'required|min:8|max:100',
            'confirmar_password' => 'required|matches:nueva_password'
        ];
        $errores = $validator->validar($datos, $reglas);
        if (!empty($errores)) {
            responderError('Datos inválidos para cambiar contraseña.', 400, ['errores' => $errores]);
            return;
        }

        // Verificar autenticación actual (requiere token JWT en cabecera Authorization)
        $usuario = $auth->obtenerUsuarioDesdeToken();
        if (!$usuario) {
            responderError('Autenticación requerida para cambiar la contraseña.', 401);
            return;
        }

        // Cambiar usando ID y contraseña actual (Autenticacion debe verificar pwd actual y actualizar hash)
        $resultado = $auth->cambiarPassword($usuario['id'], $datos['password_actual'], $datos['nueva_password']);
        if ($resultado) {
            responder(['success' => true, 'mensaje' => 'Contraseña actualizada correctamente.']);
        } else {
            // Podría ser contraseña actual incorrecta u otro error
            responderError('La contraseña actual no es correcta.', 400);
        }
    }
}

/**
 * Verificar el token JWT enviado en la cabecera Authorization.
 * @param Autenticacion $auth Instancia de Autenticacion.
 */
function verificarToken(Autenticacion $auth): void {
    $usuario = $auth->obtenerUsuarioDesdeToken(); // Autenticacion debe manejar la lectura/validación del token

    if ($usuario) {
        // Devolver datos básicos del usuario si el token es válido
        responder([
            'success' => true,
            'usuario' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'tipo' => $usuario['tipo_usuario'] ?? 'usuario'
            ]
        ]);
    } else {
        responderError('Token de sesión inválido o expirado.', 401);
    }
}

/**
 * Activar cuenta de usuario usando un token recibido por GET.
 * @param Autenticacion $auth Instancia de Autenticacion.
 */
function activarCuenta(Autenticacion $auth): void {
    $token = $_GET['token'] ?? ''; // Obtener token de la URL

    if (empty($token)) {
        responderError('Token de activación requerido.', 400);
        return;
    }

    // Verificar y activar (Autenticacion debe manejar la lógica del token de activación)
    $resultado = $auth->verificarUsuario($token);

    if ($resultado) {
        responder([
            'success' => true,
            'mensaje' => 'Tu cuenta ha sido activada correctamente. Ya puedes iniciar sesión.'
        ]);
        // Opcional: Redirigir a la página de login con un mensaje
        // header('Location: /login.php?activacion=exitosa'); exit;
    } else {
        responderError('El token de activación es inválido, ha expirado o la cuenta ya está activa.', 400);
         // Opcional: Redirigir a una página de error
        // header('Location: /activacion_error.php'); exit;
    }
}


// --- Funciones Helper para Respuesta ---

/**
 * Envía una respuesta JSON exitosa y termina el script.
 * @param array $datos Datos a incluir en la respuesta.
 */
function responder(array $datos): void {
    // Asegurar que 'success' esté presente si no se incluyó
    if (!isset($datos['success'])) {
        $datos = array_merge(['success' => true], $datos);
    }
    http_response_code(200); // OK
    echo json_encode($datos);
    exit;
}

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
    echo json_encode($respuesta);
    exit;
}
?>
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../estilos-mapa-comercial.css">
    <script src="../app.js"></script>
    <script src="../Validator.js"></script>
    <style>
    .contenedor-login {
        max-width: 400px;
        margin: 0 auto;
        background-color: #fff;
        border-radius: var(--borde-radio);
        box-shadow: var(--sombra-media);
        padding: 1rem;
    }
    .contenedor-login h2 {
        text-align: center;
    }
    .opciones-extra {
        display: flex;
        justify-content: space-between;
        margin-top: 0.5rem;
    }
    </style>
</head>
<body>

<header class="cabecera-app">
    <div class="logo-app">
        <img src="https://via.placeholder.com/40" alt="Logo">
        <span>Mi App Comercial</span>
    </div>
</header>

<main class="contenedor-app">
    <div class="contenedor-login">
        <h2>Iniciar Sesión</h2>
        <form id="form_login" method="POST" action="procesar_login.php">
            <div class="grupo-campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="grupo-campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <button type="button" onclick="togglePassword('password')">Mostrar</button>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="recordarme">
                    Recordarme
                </label>
            </div>
            <div class="opciones-extra">
                <a href="restablecer_password.php">Olvidé mi contraseña</a>
                <a href="registro.php">Registrarme</a>
            </div>
            <button type="submit" class="boton-guardar" style="width:100%; margin-top:1rem;">
                Iniciar Sesión
            </button>
        </form>
    </div>
</main>

<footer class="pie-app">
    <p>&copy; <?php echo date('Y'); ?> - Mi Aplicación Comercial</p>
</footer>

<script>
function togglePassword(campoId) {
    const campo = document.getElementById(campoId);
    campo.type = (campo.type === 'password') ? 'text' : 'password';
}

// Validación en tiempo real
document.getElementById('form_login').addEventListener('input', function() {
    // Ejemplo: validarEmail(), validarPassword()
});
</script>
</body>
</html>
