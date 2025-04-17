<?php
/**
 * config/database.php
 * Clase para manejar la conexión a la base de datos usando PDO.
 * Lee la configuración desde database.config.php.
 */

// Cargar configuración desde el archivo externo
$config_path = __DIR__ . '/database.config.php';
if (!file_exists($config_path)) {
    die('El archivo de configuración de base de datos no existe.');
}

$db_settings = require $config_path;

// Configuración para entorno de desarrollo
$db_config = [
    'host'     => $db_settings['host'],      // Servidor de base de datos
    'dbname'   => $db_settings['db_name'],   // Nombre de la base de datos
    'user'     => $db_settings['username'],  // Usuario de la base de datos
    'password' => $db_settings['password'],  // Contraseña del usuario
    'charset'  => $db_settings['charset'],   // Codificación de caracteres
    'options'  => [                          // Opciones PDO adicionales
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
];

/**
 * Función para obtener una conexión a la base de datos
 * @return PDO Objeto de conexión PDO
 */
function getDbConnection() {
    global $db_config;
    
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    
    try {
        $pdo = new PDO($dsn, $db_config['user'], $db_config['password'], $db_config['options']);
        return $pdo;
    } catch (PDOException $e) {
        // En un entorno de producción, considera registrar este error en lugar de mostrarlo
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        throw new PDOException("Error de conexión a la base de datos. Por favor, contacta al administrador.");
    }
}

class Database {

    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    private string $charset;

    private ?PDO $conn = null;

    public function __construct() {
        $this->loadConfig();
    }

    private function loadConfig(): void {
        $default_host = 'localhost';
        $default_db_name = 'geocomercial';
        $default_username = 'root';
        $default_password = '';
        $default_charset = 'utf8mb4';

        $config = [];
        $configFile = __DIR__ . '/database.config.php';

        if (file_exists($configFile)) {
            $configData = include $configFile;
            if (is_array($configData)) {
                $config = $configData;
                error_log("[DB] Archivo de configuración cargado correctamente desde: $configFile");
            } else {
                error_log("[DB] Error: El archivo '$configFile' no devolvió un array válido.");
            }
        } else {
            error_log("[DB] Error: El archivo de configuración '$configFile' no se encuentra.");
        }

        $this->host = getenv('DB_HOST') ?: ($config['host'] ?? $default_host);
        $this->db_name = getenv('DB_NAME') ?: ($config['db_name'] ?? $default_db_name);
        $this->username = getenv('DB_USER') ?: ($config['username'] ?? $default_username);
        $this->password = getenv('DB_PASSWORD') ?: ($config['password'] ?? $default_password);
        $this->charset = getenv('DB_CHARSET') ?: ($config['charset'] ?? $default_charset);

        error_log("[DB] Configuración cargada: host={$this->host}, db_name={$this->db_name}, username={$this->username}, charset={$this->charset}");

        if (empty($this->db_name) || empty($this->username)) {
            error_log("[DB] Error crítico: Faltan datos esenciales de configuración de la base de datos (db_name, username).");
        }
    }

    public function getConnection(): PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            error_log("[DB] Conexión exitosa con PDO.");
            return $this->conn;
        } catch (PDOException $e) {
            error_log("[DB] Error de conexión PDO: " . $e->getMessage());
            throw new PDOException("Error al conectar con la base de datos. Intente más tarde.");
        }
    }

    public function getDbName(): string {
        return $this->db_name;
    }
}
