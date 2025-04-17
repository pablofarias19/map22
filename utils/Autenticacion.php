<?php
/**
 * utils/Autenticacion.php
 * Sistema de autenticación y gestión de sesiones seguras
 */

class Autenticacion {
    private $db;
    private $secretKey;
    private $tokenExpiracion;
    private $activo = 1;

    public function __construct($db) {
        $this->db = $db;
        $this->secretKey = getenv('JWT_SECRET_KEY') ?: 'clave_secreta_muy_insegura_cambiar_urgente'; 
        $this->tokenExpiracion = 86400;
    }

    public function getDbConnection() {
        return $this->db;
    }

    public function registrarUsuario($datos) {
        if (empty($datos['nombre']) || empty($datos['email']) || empty($datos['password'])) {
            error_log("Error en registrarUsuario: Faltan datos requeridos.");
            return false;
        }

        try {
            $sqlVerificar = "SELECT id FROM usuarios WHERE email = :email";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':email', $datos['email']);
            $stmtVerificar->execute();

            if ($stmtVerificar->rowCount() > 0) {
                error_log("Intento de registro con email duplicado: " . $datos['email']);
                return false; 
            }

            $passwordHash = password_hash($datos['password'], PASSWORD_BCRYPT);
            if ($passwordHash === false) throw new Exception("Error al generar hash de contraseña.");

            $tokenVerificacion = bin2hex(random_bytes(32));
            $sql = "INSERT INTO usuarios (nombre, email, password, telefono, token_verificacion, fecha_creacion, activo, verificado) VALUES (:nombre, :email, :password, :telefono, :token_verificacion, NOW(), :activo, 0)";
            $stmt = $this->db->prepare($sql);
            $telefono = $datos['telefono'] ?? null;
            $activoInicial = 1;
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':email', $datos['email']);
            $stmt->bindParam(':password', $passwordHash);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':token_verificacion', $tokenVerificacion);
            $stmt->bindParam(':activo', $activoInicial, PDO::PARAM_INT);
            $stmt->execute();

            $idUsuario = $this->db->lastInsertId();

            if (!$this->enviarCorreoVerificacion($datos['email'], $tokenVerificacion)) {
                error_log("Error al enviar correo de verificación para: " . $datos['email']);
            }

            return [
                'id' => $idUsuario,
                'nombre' => $datos['nombre'],
                'email' => $datos['email'],
                'verificado' => false
            ];
        } catch (Exception $e) {
            error_log("Error en registrarUsuario: " . $e->getMessage());
            return false;
        }
    }

    public function existeValorEnBD($tabla, $columna, $valor) {
        $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$columna} = :valor";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':valor', $valor);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    public function noExisteValorEnBD($tabla, $columna, $valor) {
        return !$this->existeValorEnBD($tabla, $columna, $valor);
    }

    public function iniciarSesion($email, $password) {
        if (empty($email) || empty($password)) return false;

        try {
            $sql = "SELECT id, nombre, email, password, verificado, tipo_usuario, activo FROM usuarios WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() === 0) return false;

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario['activo'] || !$usuario['verificado'] || !password_verify($password, $usuario['password'])) {
                return false;
            }

            $sqlAcceso = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id";
            $stmtAcceso = $this->db->prepare($sqlAcceso);
            $stmtAcceso->bindParam(':id', $usuario['id'], PDO::PARAM_INT);
            $stmtAcceso->execute();

            $token = $this->generarToken($usuario);
            if ($token === false) throw new Exception("Error al generar token JWT.");

            unset($usuario['password']);
            unset($usuario['verificado']); 
            unset($usuario['activo']); 
            $usuario['token'] = $token;

            return $usuario;

        } catch (Exception $e) {
            error_log("Error en iniciarSesion: " . $e->getMessage());
            return false;
        }
    }

    public function isAdmin(): bool {
        try {
            $usuario = $this->obtenerUsuarioDesdeToken();
            if (!$usuario) return false;
            return isset($usuario['tipo_usuario']) && $usuario['tipo_usuario'] === 'admin';
        } catch (Exception $e) {
            error_log("Error en isAdmin: " . $e->getMessage());
            return false;
        }
    }

    private function generarToken($usuario) {
        try {
            $tiempoActual = time();
            $tiempoExpiracion = $tiempoActual + $this->tokenExpiracion;

            $payload = [
                'iss' => 'mapa_comercial_api',
                'aud' => 'mapa_comercial_app',
                'iat' => $tiempoActual,
                'exp' => $tiempoExpiracion,
                'data' => [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'email' => $usuario['email'],
                    'tipo' => $usuario['tipo_usuario'] ?? 'usuario'
                ]
            ];

            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $header_b64 = $this->base64url_encode($header);
            $payload_json = json_encode($payload);
            $payload_b64 = $this->base64url_encode($payload_json);
            $signature_raw = hash_hmac('sha256', $header_b64 . '.' . $payload_b64, $this->secretKey, true);
            $signature_b64 = $this->base64url_encode($signature_raw);

            return $header_b64 . '.' . $payload_b64 . '.' . $signature_b64;

        } catch (Exception $e) {
            error_log("Error en generarToken: " . $e->getMessage());
            return false;
        }
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data) {
        return base64_decode(strtr(str_pad($data, strlen($data) % 4, '=', STR_PAD_RIGHT), '-_', '+/'));
    }

    private function enviarCorreoVerificacion($email, $token) {
        $urlBase = getenv('APP_URL') ?: 'http://localhost/proyecto_geoespacial';
        $url = rtrim($urlBase, '/') . '/verificar.php?token=' . urlencode($token);
        $from = getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@example.com';
        $asunto = 'Verifica tu cuenta';
        $mensaje = "<html><body><h2>Bienvenido</h2><p>Verifica tu cuenta: <a href='{$url}'>Aquí</a></p><p>{$url}</p></body></html>";
        $cabeceras = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: <{$from}>\r\n";

        return mail($email, $asunto, $mensaje, $cabeceras);
    }

    private function enviarCorreoRestablecimiento($email, $nombre, $token) {
        $urlBase = getenv('APP_URL') ?: 'http://localhost/proyecto_geoespacial';
        $url = rtrim($urlBase, '/') . '/restablecer.php?token=' . urlencode($token);
        $from = getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@example.com';
        $asunto = 'Restablecimiento de contraseña';
        $mensaje = "<html><body><h2>Hola {$nombre}</h2><p>Haz clic aquí para restablecer tu contraseña: <a href='{$url}'>Restablecer</a></p><p>{$url}</p></body></html>";
        $cabeceras = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: <{$from}>\r\n";

        return mail($email, $asunto, $mensaje, $cabeceras);
    }

    public function obtenerUsuarioDesdeToken($token = null) {
        return false; // Placeholder para completar
    }
}
?>