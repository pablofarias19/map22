<?php

use PDO;
use PDOException;
use Exception; // Asegúrate de que Exception está disponible

/**
 * EventoController.php
 * Controlador para la gestión de eventos en la aplicación geoespacial.
 */
class EventoController {
    private $db;

    /**
     * Constructor que recibe la conexión a la base de datos.
     * @param PDO $db Conexión PDO a la base de datos.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Obtener eventos cercanos a una ubicación y dentro de un radio.
     *
     * @param float $lat Latitud del centro de búsqueda.
     * @param float $lng Longitud del centro de búsqueda.
     * @param int $radio Radio de búsqueda en metros.
     * @param array $filtros Filtros adicionales (ej: ['categorias' => '1,2', 'fecha_desde' => 'YYYY-MM-DD', 'incluir_pasados' => true]).
     * @param int $limit Número máximo de resultados a devolver.
     * @return array Datos de eventos en formato GeoJSON FeatureCollection.
     */
    public function obtenerEventosCercanos($lat, $lng, $radio, $filtros = [], $limit = 200) {
        // Convertir radio a grados (aproximación para el filtro WHERE inicial).
        $radioGrados = $radio / 111000.0;

        // Preparar parámetros base para la consulta SQL
        $parametros = [
            ':lat' => $lat,
            ':lng' => $lng,
            ':radio_grados' => $radioGrados, // Usado en el WHERE (aproximado)
            ':radio_metros' => $radio,       // Usado en el HAVING (preciso)
            ':activo' => 1,
            ':limit' => (int) $limit // Asegurar que es un entero
        ];

        // Preparar condiciones de filtro dinámicas
        $condicionesFiltro = [];

        // Filtro por categorías
        if (!empty($filtros['categorias'])) {
            $categorias = explode(',', $filtros['categorias']);
            $placeholders = [];
            foreach ($categorias as $i => $cat) {
                $paramName = ":cat" . $i;
                $placeholders[] = $paramName;
                $parametros[$paramName] = trim($cat); // Asumimos IDs o strings seguros
            }
             if (!empty($placeholders)) {
                $condicionesFiltro[] = "e.id_categoria_evento IN (" . implode(", ", $placeholders) . ")";
            }
        }

        // Filtro por fecha de inicio (eventos que terminan después de esta fecha)
        if (!empty($filtros['fecha_desde'])) {
             // Podrías añadir validación de formato de fecha aquí
            $condicionesFiltro[] = "e.fecha_fin >= :fecha_desde";
            $parametros[':fecha_desde'] = $filtros['fecha_desde'];
        }

        // Filtro por fecha de fin (eventos que empiezan antes de esta fecha)
        if (!empty($filtros['fecha_hasta'])) {
            // Podrías añadir validación de formato de fecha aquí
            $condicionesFiltro[] = "e.fecha_inicio <= :fecha_hasta";
            $parametros[':fecha_hasta'] = $filtros['fecha_hasta'];
        }

        // Filtro por eventos futuros (por defecto, a menos que se pida incluir pasados)
        if (empty($filtros['incluir_pasados'])) {
            $condicionesFiltro[] = "e.fecha_fin >= NOW()"; // Filtra eventos que aún no han terminado
        }

        // Construir la parte WHERE de los filtros
        $filtrosSQL = empty($condicionesFiltro) ? "" : "AND " . implode(" AND ", $condicionesFiltro);

        // Consulta SQL usando la función de distancia Haversine
        $sql = "
            SELECT
                e.id,
                e.titulo,
                e.descripcion,
                e.fecha_inicio,
                e.fecha_fin,
                e.direccion,
                e.latitud,
                e.longitud,
                e.imagen_principal,
                e.link_video,
                e.precio,
                e.fecha_creacion,
                c.nombre as categoria_nombre,
                c.icono_emoji as categoria_icono,
                n.id as id_negocio,
                n.nombre_comercial as negocio_nombre,
                -- Fórmula Haversine para calcular distancia en metros
                (6371000 * acos(
                    cos(radians(:lat)) * cos(radians(e.latitud)) *
                    cos(radians(e.longitud) - radians(:lng)) +
                    sin(radians(:lat)) * sin(radians(e.latitud))
                )) as distancia
            FROM
                eventos e
                LEFT JOIN categorias_eventos c ON e.id_categoria_evento = c.id
                LEFT JOIN negocios n ON e.id_negocio = n.id -- Asumiendo que un evento PUEDE estar ligado a un negocio
            WHERE
                e.activo = :activo
                -- Filtro inicial rápido usando 'bounding box'
                AND (e.latitud BETWEEN (:lat - :radio_grados) AND (:lat + :radio_grados))
                AND (e.longitud BETWEEN (:lng - :radio_grados) AND (:lng + :radio_grados))
                {$filtrosSQL}
            HAVING
                -- Filtro preciso usando la distancia calculada en metros
                distancia <= :radio_metros
            ORDER BY
                e.fecha_inicio ASC -- Ordenar por fecha de inicio del evento
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);

            // Bind de los parámetros (incluyendo el límite y radios)
            foreach ($parametros as $key => $value) {
                 // Especificar tipos para parámetros clave
                if ($key == ':limit' || $key == ':activo') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } elseif (in_array($key, [':lat', ':lng', ':radio_grados', ':radio_metros'])) {
                     $stmt->bindValue($key, $value, PDO::PARAM_STR); // PDO trata decimales/floats como strings a menudo
                } elseif (strpos($key, ':fecha_') === 0) { // Parámetros de fecha
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } else {
                    // Para :catX u otros filtros dinámicos (asumimos string/int)
                     $stmt->bindValue($key, $value);
                }
            }

            $stmt->execute();
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear para GeoJSON
            $features = [];
            foreach ($eventos as $evento) {
                 // Asegurarse que lat/lon son floats para JSON
                $lat = (float) $evento['latitud'];
                $lon = (float) $evento['longitud'];

                $features[] = [
                    'type' => 'Feature',
                    'properties' => $evento, // Incluye la distancia calculada
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$lon, $lat] // GeoJSON es [longitud, latitud]
                    ]
                ];
            }

            return [
                'type' => 'FeatureCollection',
                'features' => $features
            ];
        } catch (PDOException $e) {
            error_log("Error en obtenerEventosCercanos: " . $e->getMessage());
            return [
                'type' => 'FeatureCollection',
                'features' => []
            ];
        }
    }

    /**
     * Obtener detalles completos de un evento por su ID.
     *
     * @param int $id ID del evento a buscar.
     * @return array|null Datos completos del evento si se encuentra y está activo, o null si no.
     */
    public function obtenerEventoDetalle($id) {
        $sql = "
            SELECT
                e.*, -- Selecciona todas las columnas de la tabla eventos
                c.nombre as categoria_nombre,
                c.icono_emoji as categoria_icono,
                c.color_distintivo as categoria_color,
                n.id as id_negocio,
                n.nombre_comercial as negocio_nombre
            FROM
                eventos e
                LEFT JOIN categorias_eventos c ON e.id_categoria_evento = c.id
                LEFT JOIN negocios n ON e.id_negocio = n.id
            WHERE
                e.id = :id AND e.activo = 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Enlazar el parámetro :id de forma segura, especificando el tipo INT
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);

            return $evento ?: null; // Retorna el array o null si fetch devuelve false

        } catch (PDOException $e) {
            error_log("Error en obtenerEventoDetalle (ID: {$id}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear un nuevo evento.
     *
     * @param array $datos Datos del evento (ej: ['titulo' => 'Concierto', 'id_categoria_evento' => 1, ...]).
     * @param int $idUsuario ID del usuario propietario/creador del evento.
     * @return int|false ID del evento recién creado o false en caso de error.
     */
    public function crearEvento($datos, $idUsuario) {
        // --- Validación básica inicial ---
        if (empty($datos['titulo']) ||
            empty($datos['id_categoria_evento']) || // Asumiendo que categoría es obligatoria
            empty($datos['fecha_inicio']) ||        // Asumiendo fecha inicio obligatoria
            empty($datos['direccion']) ||           // Asumiendo dirección obligatoria
            !isset($datos['latitud']) ||            // Latitud obligatoria (puede ser 0)
            !isset($datos['longitud'])) {           // Longitud obligatoria (puede ser 0)
            error_log("Error en crearEvento: Faltan datos obligatorios.");
            return false; // Devolver false en lugar de lanzar excepción directamente aquí
        }
        // --- Podrías añadir validaciones más específicas (formato fecha, numérico, etc.) ---

        // Lista de campos permitidos y que pueden venir en $datos
        $camposPermitidos = [
            'id_negocio', 'id_categoria_evento', 'titulo', 'descripcion',
            'fecha_inicio', 'fecha_fin', 'direccion', 'latitud', 'longitud',
            'imagen_principal', 'imagen_secundaria', 'link_video', 'precio', 'aforo'
            // 'activo' se gestiona internamente, 'id_usuario' viene del parámetro
        ];

        $columnasSql = ['id_usuario']; // Siempre incluir el usuario creador
        $placeholdersSql = [':id_usuario'];
        $parametros = [':id_usuario' => $idUsuario];

        foreach ($camposPermitidos as $campo) {
            if (isset($datos[$campo])) { // Solo incluir si el dato fue proporcionado
                 $columnasSql[] = $campo;
                 $placeholder = ':' . $campo;
                 $placeholdersSql[] = $placeholder;
                 // Limpieza básica o conversión de tipo podría ir aquí si es necesario
                 $parametros[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo]; // Permitir strings vacíos como NULL
            }
        }

        // Construir la consulta SQL de forma segura
        $sql = sprintf(
            "INSERT INTO eventos (%s) VALUES (%s)",
            implode(', ', $columnasSql),
            implode(', ', $placeholdersSql)
        );

        try {
            $this->db->beginTransaction(); // Iniciar transacción

            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametros);
            $idEvento = $this->db->lastInsertId();

            $this->db->commit(); // Confirmar transacción
            return (int) $idEvento; // Devolver el ID como entero

        } catch (PDOException $e) {
            $this->db->rollBack(); // Revertir en caso de error
            error_log("Error PDO en crearEvento: " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($parametros));
            return false;
        } catch (Exception $e) { // Capturar otras posibles excepciones
             $this->db->rollBack();
             error_log("Error General en crearEvento: " . $e->getMessage());
             return false;
        }
    }


    /**
     * Actualizar un evento existente.
     *
     * @param int $id ID del evento a actualizar.
     * @param array $datos Nuevos datos del evento. Claves deben coincidir con columnas permitidas.
     * @param int $idUsuario ID del usuario que realiza la actualización (para verificación de permisos).
     * @return bool True si la actualización fue exitosa, False en caso contrario.
     */
    public function actualizarEvento($id, $datos, $idUsuario) {
        // --- Verificar Permisos ---
        // Obtener primero el evento para verificar propietario o negocio asociado
         $sqlVerificar = "SELECT id_usuario, id_negocio FROM eventos WHERE id = :id AND activo = 1";
         try {
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->execute([':id' => $id]);
            $eventoActual = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$eventoActual) {
                throw new Exception("Evento no encontrado o inactivo.");
            }

            $esPropietarioEvento = ($eventoActual['id_usuario'] == $idUsuario);
            $esPropietarioNegocioAsociado = false;

            if (!empty($eventoActual['id_negocio'])) {
                $sqlVerificarNegocio = "SELECT id FROM negocios WHERE id = :id_negocio AND id_usuario = :id_usuario AND activo = 1";
                $stmtVerificarNegocio = $this->db->prepare($sqlVerificarNegocio);
                $stmtVerificarNegocio->execute([
                    ':id_negocio' => $eventoActual['id_negocio'],
                    ':id_usuario' => $idUsuario
                ]);
                if ($stmtVerificarNegocio->rowCount() > 0) {
                    $esPropietarioNegocioAsociado = true;
                }
            }

            // Permitir si es el creador O el propietario del negocio asociado (si existe)
            if (!$esPropietarioEvento && !$esPropietarioNegocioAsociado) {
                 throw new Exception("No tienes permiso para editar este evento.");
            }

        } catch (PDOException $e) {
             error_log("Error PDO verificando permisos en actualizarEvento (ID: {$id}): " . $e->getMessage());
             return false;
        } catch (Exception $e) {
            error_log("Error de permisos en actualizarEvento (ID: {$id}): " . $e->getMessage());
            return false; // Permiso denegado o evento no encontrado
        }
        // --- Fin Verificación Permisos ---


        // --- Preparar Actualización ---
        $camposPermitidos = [
            'id_negocio', 'id_categoria_evento', 'titulo', 'descripcion',
            'fecha_inicio', 'fecha_fin', 'direccion', 'latitud', 'longitud',
            'imagen_principal', 'imagen_secundaria', 'link_video', 'precio', 'aforo'
        ];

        $actualizacionesSql = [];
        $parametros = [':id' => $id]; // ID siempre necesario para el WHERE

        foreach ($camposPermitidos as $campo) {
            // Usar array_key_exists para permitir actualizar a NULL o string vacío si se envía explícitamente
            if (array_key_exists($campo, $datos)) {
                $placeholder = ':' . $campo;
                $actualizacionesSql[] = "{$campo} = {$placeholder}";
                $parametros[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo];
            }
        }

        if (empty($actualizacionesSql)) {
            // No lanzar excepción, simplemente no hay nada que hacer. Podría devolver true.
            // O podrías devolver un código/mensaje específico si prefieres.
            error_log("actualizarEvento (ID: {$id}): No se proporcionaron datos para actualizar.");
            return true; // O false si prefieres indicar que no se hizo nada.
        }

        // Construir la consulta de actualización
        $sqlActualizar = sprintf(
            "UPDATE eventos SET %s WHERE id = :id",
            implode(', ', $actualizacionesSql)
        );
        // --- Fin Preparar Actualización ---

        // --- Ejecutar Actualización ---
        try {
            $this->db->beginTransaction();

            $stmtActualizar = $this->db->prepare($sqlActualizar);
            $stmtActualizar->execute($parametros);

            $this->db->commit();
            return true; // Éxito

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en actualizarEvento (ID: {$id}): " . $e->getMessage() . " SQL: " . $sqlActualizar . " Params: " . json_encode($parametros));
            return false;
        } catch (Exception $e) { // Capturar otras posibles excepciones
             $this->db->rollBack();
             error_log("Error General en actualizarEvento (ID: {$id}): " . $e->getMessage());
             return false;
        }
        // --- Fin Ejecutar Actualización ---
    }

    /**
     * Eliminar un evento (marcarlo como inactivo - Soft Delete).
     *
     * @param int $id ID del evento a eliminar.
     * @param int $idUsuario ID del usuario que realiza la eliminación (para verificación de permisos).
     * @return bool True si la eliminación lógica fue exitosa, False en caso contrario.
     */
    public function eliminarEvento($id, $idUsuario) {
        // Usar la misma lógica de permisos que en actualizarEvento
         $sqlVerificar = "SELECT id_usuario, id_negocio FROM eventos WHERE id = :id AND activo = 1";

        try {
            // --- Verificación de Permisos (igual que en actualizar) ---
             $stmtVerificar = $this->db->prepare($sqlVerificar);
             $stmtVerificar->execute([':id' => $id]);
             $eventoActual = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

             if (!$eventoActual) {
                 throw new Exception("Evento no encontrado o ya inactivo.");
             }

             $esPropietarioEvento = ($eventoActual['id_usuario'] == $idUsuario);
             $esPropietarioNegocioAsociado = false;

             if (!empty($eventoActual['id_negocio'])) {
                 $sqlVerificarNegocio = "SELECT id FROM negocios WHERE id = :id_negocio AND id_usuario = :id_usuario AND activo = 1";
                 $stmtVerificarNegocio = $this->db->prepare($sqlVerificarNegocio);
                 $stmtVerificarNegocio->execute([
                     ':id_negocio' => $eventoActual['id_negocio'],
                     ':id_usuario' => $idUsuario
                 ]);
                 if ($stmtVerificarNegocio->rowCount() > 0) {
                     $esPropietarioNegocioAsociado = true;
                 }
             }

             if (!$esPropietarioEvento && !$esPropietarioNegocioAsociado) {
                  throw new Exception("No tienes permiso para eliminar este evento.");
             }
            // --- Fin Verificación Permisos ---

            // --- Ejecución de Eliminación Lógica dentro de Transacción ---
            $this->db->beginTransaction();

            $sqlEliminar = "UPDATE eventos SET activo = 0 WHERE id = :id";
            $stmtEliminar = $this->db->prepare($sqlEliminar);
            $stmtEliminar->execute([':id' => $id]);

            $this->db->commit();
            return true; // Éxito

        } catch (PDOException $e) {
            // Asegurarse de hacer rollback si la transacción se inició
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error PDO en eliminarEvento (ID: {$id}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
             if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en eliminarEvento (ID: {$id}): " . $e->getMessage());
            return false; // Permiso denegado o evento no encontrado
        }
    }

    /**
     * Obtener listado de eventos creados por un usuario específico.
     *
     * @param int $idUsuario ID del usuario.
     * @return array Lista de eventos activos del usuario, o array vacío en caso de error.
     */
    public function obtenerEventosUsuario($idUsuario) {
        $sql = "
            SELECT
                e.id,
                e.titulo,
                e.fecha_inicio,
                e.fecha_fin,
                e.direccion,
                e.latitud,
                e.longitud,
                e.fecha_creacion,
                c.nombre as categoria_nombre,
                n.nombre_comercial as negocio_nombre
            FROM
                eventos e
                LEFT JOIN categorias_eventos c ON e.id_categoria_evento = c.id
                LEFT JOIN negocios n ON e.id_negocio = n.id
            WHERE
                e.id_usuario = :id_usuario
                AND e.activo = 1 -- Solo eventos activos
            ORDER BY
                e.fecha_inicio DESC -- Más recientes primero
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Especificar el tipo de dato para el ID de usuario
            $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerEventosUsuario (Usuario ID: {$idUsuario}): " . $e->getMessage());
            return []; // Devolver array vacío en caso de error
        }
    }

    /**
     * Obtener listado completo de categorías de eventos disponibles.
     *
     * @return array Lista de categorías, o array vacío en caso de error.
     */
    public function obtenerCategoriasEventos() {
        $sql = "
            SELECT id, nombre, descripcion, icono_emoji, color_distintivo
            FROM categorias_eventos
            ORDER BY nombre ASC -- Orden alfabético por nombre
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerCategoriasEventos: " . $e->getMessage());
            return []; // Devolver array vacío en caso de error
        }
    }
} // Fin de la clase EventoController