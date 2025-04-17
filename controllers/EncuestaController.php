<?php

use PDO;
use PDOException;
use Exception; // Asegúrate de que Exception esté disponible

/**
 * EncuestaController.php
 * Controlador para la gestión de encuestas en la aplicación geoespacial.
 */
class EncuestaController {
    private $db;

    /**
     * Constructor que recibe la conexión a la base de datos.
     * @param PDO $db Conexión PDO a la base de datos.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Obtener encuestas cercanas a una ubicación y dentro de un radio.
     *
     * @param float $lat Latitud del centro de búsqueda.
     * @param float $lng Longitud del centro de búsqueda.
     * @param int $radio Radio de búsqueda en metros.
     * @param array $filtros Filtros adicionales (ej: ['id_negocio' => 5]).
     * @param int $limit Número máximo de resultados a devolver.
     * @return array Datos de encuestas en formato GeoJSON FeatureCollection.
     */
    public function obtenerEncuestasCercanas($lat, $lng, $radio, $filtros = [], $limit = 100) {
        // Convertir radio a grados (aproximación para el filtro WHERE inicial).
        $radioGrados = $radio / 111000.0;

        // Preparar parámetros base para la consulta SQL
        $parametros = [
            ':lat' => $lat,
            ':lng' => $lng,
            ':radio_grados' => $radioGrados, // Usado en el WHERE (aproximado)
            ':radio_metros' => $radio,       // Usado en el HAVING (preciso)
            ':activo' => 1,
            ':fecha_actual' => date('Y-m-d H:i:s'), // Para filtrar encuestas vigentes
            ':limit' => (int) $limit // Asegurar que es un entero
        ];

        // Preparar condiciones de filtro dinámicas
        $condicionesFiltro = [];

        // Por defecto, solo encuestas vigentes
        $condicionesFiltro[] = "(e.fecha_inicio <= :fecha_actual AND (e.fecha_fin IS NULL OR e.fecha_fin >= :fecha_actual))";

        // Filtro por negocio específico
        if (!empty($filtros['id_negocio']) && is_numeric($filtros['id_negocio'])) {
            $condicionesFiltro[] = "e.id_negocio = :id_negocio";
            $parametros[':id_negocio'] = (int) $filtros['id_negocio'];
        }

        // Construir la parte WHERE de los filtros
        $filtrosSQL = empty($condicionesFiltro) ? "" : "AND " . implode(" AND ", $condicionesFiltro);

        // Consulta SQL para encuestas, manejando ubicación y estadísticas
        $sql = "
            SELECT
                e.id,
                e.titulo,
                e.descripcion,
                e.fecha_inicio,
                e.fecha_fin,
                e.imagen,
                COALESCE(e.latitud, n.latitud) as latitud,
                COALESCE(e.longitud, n.longitud) as longitud,
                e.fecha_creacion,
                e.resultados_publicos,
                e.id_negocio,
                n.nombre_comercial as negocio_nombre,
                (SELECT COUNT(*) FROM preguntas_encuesta WHERE id_encuesta = e.id AND activo = 1) as num_preguntas, -- Considerar estado activo de preguntas
                (SELECT COUNT(DISTINCT r.id_usuario) FROM respuestas_encuesta r JOIN preguntas_encuesta p ON r.id_pregunta = p.id WHERE p.id_encuesta = e.id) as num_participantes,
                (6371000 * acos(
                    cos(radians(:lat)) * cos(radians(COALESCE(e.latitud, n.latitud))) *
                    cos(radians(COALESCE(e.longitud, n.longitud)) - radians(:lng)) +
                    sin(radians(:lat)) * sin(radians(COALESCE(e.latitud, n.latitud)))
                )) as distancia
            FROM
                encuestas e
                LEFT JOIN negocios n ON e.id_negocio = n.id
            WHERE
                e.activo = :activo
                AND (
                    (e.latitud IS NOT NULL AND e.longitud IS NOT NULL AND
                     e.latitud BETWEEN (:lat - :radio_grados) AND (:lat + :radio_grados) AND
                     e.longitud BETWEEN (:lng - :radio_grados) AND (:lng + :radio_grados))
                    OR
                    (e.latitud IS NULL AND e.longitud IS NULL AND n.id IS NOT NULL AND
                     n.latitud BETWEEN (:lat - :radio_grados) AND (:lat + :radio_grados) AND
                     n.longitud BETWEEN (:lng - :radio_grados) AND (:lng + :radio_grados))
                )
                {$filtrosSQL}
            HAVING
                -- Filtro preciso usando la distancia calculada y el radio en metros con placeholder
                distancia <= :radio_metros
            ORDER BY
                e.fecha_creacion DESC
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);

            // Bind de los parámetros
            foreach ($parametros as $key => $value) {
                if (in_array($key, [':limit', ':activo', ':id_negocio'])) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } elseif (in_array($key, [':lat', ':lng', ':radio_grados', ':radio_metros'])) {
                     $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } elseif ($key == ':fecha_actual') {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } else {
                     $stmt->bindValue($key, $value);
                }
            }

            $stmt->execute();
            $encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear para GeoJSON
            $features = [];
            foreach ($encuestas as $encuesta) {
                $lat = isset($encuesta['latitud']) ? (float) $encuesta['latitud'] : null;
                $lon = isset($encuesta['longitud']) ? (float) $encuesta['longitud'] : null;

                if ($lat !== null && $lon !== null) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => $encuesta,
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [$lon, $lat]
                        ]
                    ];
                }
            }

            return [
                'type' => 'FeatureCollection',
                'features' => $features
            ];
        } catch (PDOException $e) {
            error_log("Error PDO en obtenerEncuestasCercanas: " . $e->getMessage() . " SQL: " . $sql);
            return [
                'type' => 'FeatureCollection',
                'features' => []
            ];
        }
    }

    /**
     * Obtener detalles completos de una encuesta incluyendo preguntas y opciones.
     * Optimizado para reducir consultas N+1.
     *
     * @param int $id ID de la encuesta.
     * @return array|null Datos completos de la encuesta o null si no existe o error.
     */
    public function obtenerEncuestaDetalle($id) {
        try {
            // 1. Obtener datos principales de la encuesta
            $sqlEncuesta = "
                SELECT
                    e.*,
                    n.nombre_comercial as negocio_nombre,
                    n.direccion as negocio_direccion
                FROM
                    encuestas e
                    LEFT JOIN negocios n ON e.id_negocio = n.id
                WHERE
                    e.id = :id AND e.activo = 1
            ";
            $stmtEncuesta = $this->db->prepare($sqlEncuesta);
            $stmtEncuesta->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEncuesta->execute();
            $encuesta = $stmtEncuesta->fetch(PDO::FETCH_ASSOC);

            if (!$encuesta) {
                return null; // Encuesta no encontrada o inactiva
            }

            // 2. Obtener todas las preguntas de la encuesta
            $sqlPreguntas = "
                SELECT
                    id, texto, tipo, orden, obligatoria
                FROM
                    preguntas_encuesta
                WHERE
                    id_encuesta = :id_encuesta AND activo = 1 -- Considerar estado activo
                ORDER BY
                    orden ASC
            ";
            $stmtPreguntas = $this->db->prepare($sqlPreguntas);
            $stmtPreguntas->bindParam(':id_encuesta', $id, PDO::PARAM_INT);
            $stmtPreguntas->execute();
            $preguntas = $stmtPreguntas->fetchAll(PDO::FETCH_ASSOC);

            // 3. Obtener todas las opciones para todas las preguntas de la encuesta en una sola consulta
            $opcionesPorPregunta = [];
            if (!empty($preguntas)) {
                $preguntaIds = array_column($preguntas, 'id');
                $placeholders = implode(',', array_fill(0, count($preguntaIds), '?'));

                $sqlOpciones = "
                    SELECT
                        id, id_pregunta, texto, orden
                    FROM
                        opciones_pregunta
                    WHERE
                        id_pregunta IN ({$placeholders}) AND activo = 1 -- Considerar estado activo
                    ORDER BY
                        id_pregunta, orden ASC
                ";
                $stmtOpciones = $this->db->prepare($sqlOpciones);
                $stmtOpciones->execute($preguntaIds);
                $allOpciones = $stmtOpciones->fetchAll(PDO::FETCH_ASSOC);

                // Mapear opciones a sus preguntas
                foreach ($allOpciones as $opcion) {
                    $opcionesPorPregunta[$opcion['id_pregunta']][] = $opcion;
                }
            }

            // 4. Añadir las opciones a cada pregunta
            foreach ($preguntas as &$pregunta) { // Usar referencia (&) para modificar el array original
                $pregunta['opciones'] = $opcionesPorPregunta[$pregunta['id']] ?? []; // Asignar opciones o array vacío
            }
            unset($pregunta); // Romper la referencia

            $encuesta['preguntas'] = $preguntas;

            // 5. Obtener estadísticas de participación (consulta separada y eficiente)
            $sqlEstadisticas = "
                SELECT
                    COUNT(DISTINCT r.id_usuario) as num_participantes
                FROM
                    respuestas_encuesta r
                    JOIN preguntas_encuesta p ON r.id_pregunta = p.id
                WHERE
                    p.id_encuesta = :id_encuesta
            ";
            $stmtEstadisticas = $this->db->prepare($sqlEstadisticas);
            $stmtEstadisticas->bindParam(':id_encuesta', $id, PDO::PARAM_INT);
            $stmtEstadisticas->execute();
            $estadisticas = $stmtEstadisticas->fetch(PDO::FETCH_ASSOC);

            $encuesta['estadisticas'] = $estadisticas;

            return $encuesta;

        } catch (PDOException $e) {
            error_log("Error PDO en obtenerEncuestaDetalle (ID: {$id}): " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("Error General en obtenerEncuestaDetalle (ID: {$id}): " . $e->getMessage());
            return null;
        }
    }


    /**
     * Crear una nueva encuesta con sus preguntas y opciones.
     *
     * @param array $datos Datos de la encuesta (incluyendo un array 'preguntas').
     * @param int $idUsuario ID del usuario propietario.
     * @return int|false ID de la encuesta creada o false en caso de error.
     */
    public function crearEncuesta($datos, $idUsuario) {
        // --- Validación inicial ---
        if (empty($datos['titulo']) || empty($datos['fecha_inicio'])) {
            error_log("Error en crearEncuesta: Faltan datos obligatorios (título o fecha inicio).");
            return false;
        }
        if (empty($datos['id_negocio']) && (!isset($datos['latitud']) || !isset($datos['longitud']))) {
             error_log("Error en crearEncuesta: Se requiere id_negocio o coordenadas.");
             return false;
        }
        if (empty($datos['preguntas']) || !is_array($datos['preguntas'])) {
             error_log("Error en crearEncuesta: No se proporcionaron preguntas válidas.");
             return false; // Una encuesta debe tener preguntas
        }
        // --- Más validaciones (formato fechas, tipos, etc.) podrían añadirse ---

        // --- Preparar datos para la tabla 'encuestas' ---
        $camposEncuestaPermitidos = [
            'id_negocio', 'titulo', 'descripcion', 'fecha_inicio', 'fecha_fin',
            'imagen', 'latitud', 'longitud', 'resultados_publicos'
        ];
        $columnasSqlEncuesta = ['id_usuario'];
        $placeholdersSqlEncuesta = [':id_usuario'];
        $paramsEncuesta = [':id_usuario' => $idUsuario];

        foreach ($camposEncuestaPermitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $columnasSqlEncuesta[] = $campo;
                $placeholder = ':' . $campo;
                $placeholdersSqlEncuesta[] = $placeholder;
                $paramsEncuesta[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo];
            }
        }
         // Añadir valor por defecto si no se especifica resultados_publicos
        if (!isset($paramsEncuesta[':resultados_publicos'])) {
             $columnasSqlEncuesta[] = 'resultados_publicos';
             $placeholdersSqlEncuesta[] = ':resultados_publicos';
             $paramsEncuesta[':resultados_publicos'] = 0; // Default a no públicos
        }

        $sqlEncuesta = sprintf(
            "INSERT INTO encuestas (%s) VALUES (%s)",
            implode(', ', $columnasSqlEncuesta),
            implode(', ', $placeholdersSqlEncuesta)
        );
        // --- Fin preparación encuesta ---


        $this->db->beginTransaction(); // Iniciar transacción
        try {
            // Insertar encuesta principal
            $stmtEncuesta = $this->db->prepare($sqlEncuesta);
            $stmtEncuesta->execute($paramsEncuesta);
            $idEncuesta = $this->db->lastInsertId();

            if (!$idEncuesta) {
                 throw new Exception("No se pudo obtener el ID de la encuesta creada.");
            }

            // Insertar preguntas y opciones usando el método helper
            $this->insertarPreguntasYOpciones($idEncuesta, $datos['preguntas']);

            $this->db->commit(); // Confirmar transacción
            return (int) $idEncuesta;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en crearEncuesta: " . $e->getMessage() . " SQL Encuesta: " . $sqlEncuesta . " Params: " . json_encode($paramsEncuesta));
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error General en crearEncuesta: " . $e->getMessage());
            // Podrías querer loguear $datos['preguntas'] aquí si el error proviene de insertarPreguntasYOpciones
            return false;
        }
    }

    /**
     * Método helper para insertar preguntas y sus opciones dentro de una transacción existente.
     * Lanzará una excepción en caso de error para que la transacción principal haga rollback.
     *
     * @param int $idEncuesta ID de la encuesta padre.
     * @param array $preguntas Array de preguntas con sus datos y opciones.
     * @throws PDOException | Exception Si ocurre un error.
     */
    private function insertarPreguntasYOpciones($idEncuesta, $preguntas) {
        $sqlPregunta = "
            INSERT INTO preguntas_encuesta
            (id_encuesta, texto, tipo, orden, obligatoria, activo)
            VALUES
            (:id_encuesta, :texto, :tipo, :orden, :obligatoria, 1) -- Asumir activo=1 por defecto
        ";
        $stmtPregunta = $this->db->prepare($sqlPregunta);

        $sqlOpcion = "
            INSERT INTO opciones_pregunta
            (id_pregunta, texto, orden, activo)
            VALUES
            (:id_pregunta, :texto, :orden, 1) -- Asumir activo=1 por defecto
        ";
        $stmtOpcion = $this->db->prepare($sqlOpcion);

        foreach ($preguntas as $orden => $preguntaData) {
            if (empty($preguntaData['texto']) || empty($preguntaData['tipo'])) {
                throw new Exception("Datos incompletos para la pregunta en orden " . ($orden + 1));
            }

            $obligatoria = isset($preguntaData['obligatoria']) ? (int)(bool)$preguntaData['obligatoria'] : 1;

            $stmtPregunta->bindParam(':id_encuesta', $idEncuesta, PDO::PARAM_INT);
            $stmtPregunta->bindParam(':texto', $preguntaData['texto'], PDO::PARAM_STR);
            $stmtPregunta->bindParam(':tipo', $preguntaData['tipo'], PDO::PARAM_STR);
            $stmtPregunta->bindValue(':orden', $orden + 1, PDO::PARAM_INT); // Usar bindValue para literal
            $stmtPregunta->bindParam(':obligatoria', $obligatoria, PDO::PARAM_INT);
            $stmtPregunta->execute();

            $idPregunta = $this->db->lastInsertId();
            if (!$idPregunta) {
                throw new Exception("No se pudo obtener el ID de la pregunta creada para: " . $preguntaData['texto']);
            }

            // Insertar opciones si corresponde
            if (in_array($preguntaData['tipo'], ['opcion_multiple', 'seleccion_unica']) && !empty($preguntaData['opciones']) && is_array($preguntaData['opciones'])) {
                foreach ($preguntaData['opciones'] as $ordenOpcion => $opcionData) {
                    if (empty($opcionData['texto'])) {
                         throw new Exception("Texto vacío para opción en pregunta: " . $preguntaData['texto']);
                    }

                    $stmtOpcion->bindParam(':id_pregunta', $idPregunta, PDO::PARAM_INT);
                    $stmtOpcion->bindParam(':texto', $opcionData['texto'], PDO::PARAM_STR);
                    $stmtOpcion->bindValue(':orden', $ordenOpcion + 1, PDO::PARAM_INT);
                    $stmtOpcion->execute();
                }
            }
        }
    }


    /**
     * Actualizar una encuesta existente.
     * NOTA: La actualización de preguntas es destructiva (elimina y reinserta) si se pasa 'actualizar_preguntas' = true.
     *
     * @param int $id ID de la encuesta.
     * @param array $datos Nuevos datos (incluyendo opcionalmente 'preguntas' y 'actualizar_preguntas').
     * @param int $idUsuario ID del usuario que actualiza.
     * @return bool Éxito.
     */
    public function actualizarEncuesta($id, $datos, $idUsuario) {
        $this->db->beginTransaction(); // Iniciar transacción

        try {
            // --- Verificar Permisos ---
            $sqlVerificar = "
                SELECT e.id_usuario, e.id_negocio, n.id_usuario as negocio_usuario_id
                FROM encuestas e
                LEFT JOIN negocios n ON e.id_negocio = n.id
                WHERE e.id = :id AND e.activo = 1
            ";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $encuestaActual = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$encuestaActual) {
                throw new Exception("Encuesta no encontrada o inactiva.");
            }

            $esPropietarioEncuesta = ($encuestaActual['id_usuario'] == $idUsuario);
            $esPropietarioNegocio = (!empty($encuestaActual['negocio_usuario_id']) && $encuestaActual['negocio_usuario_id'] == $idUsuario);

            if (!$esPropietarioEncuesta && !$esPropietarioNegocio) {
                 throw new Exception("No tienes permiso para editar esta encuesta.");
            }
            // --- Fin Verificación Permisos ---

            // --- Preparar Actualización Encuesta Principal ---
            $camposEncuestaPermitidos = [
                'id_negocio', 'titulo', 'descripcion', 'fecha_inicio', 'fecha_fin',
                'imagen', 'latitud', 'longitud', 'resultados_publicos'
            ];
            $actualizacionesSql = [];
            $paramsEncuesta = [':id' => $id];

            foreach ($camposEncuestaPermitidos as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $placeholder = ':' . $campo;
                    $actualizacionesSql[] = "{$campo} = {$placeholder}";
                    $paramsEncuesta[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo];
                }
            }
            // --- Fin Preparación Actualización ---

            // --- Ejecutar Actualización Encuesta (si hay cambios) ---
            if (!empty($actualizacionesSql)) {
                $sqlActualizarEncuesta = sprintf(
                    "UPDATE encuestas SET %s WHERE id = :id",
                    implode(', ', $actualizacionesSql)
                );
                $stmtActualizar = $this->db->prepare($sqlActualizarEncuesta);
                $stmtActualizar->execute($paramsEncuesta);
            }
            // --- Fin Ejecución Actualización Encuesta ---

            // --- Actualizar Preguntas (si se solicita) ---
            if (!empty($datos['preguntas']) && !empty($datos['actualizar_preguntas']) && is_array($datos['preguntas'])) {
                // Eliminar preguntas y opciones existentes (CUIDADO: Esto puede borrar respuestas si no hay ON DELETE CASCADE)
                // Sería mejor marcar como inactivas o tener un mecanismo más granular.
                $sqlEliminarOpciones = "DELETE FROM opciones_pregunta WHERE id_pregunta IN (SELECT id FROM preguntas_encuesta WHERE id_encuesta = :id_encuesta)";
                $stmtEliminarOpciones = $this->db->prepare($sqlEliminarOpciones);
                $stmtEliminarOpciones->bindParam(':id_encuesta', $id, PDO::PARAM_INT);
                $stmtEliminarOpciones->execute();

                $sqlEliminarPreguntas = "DELETE FROM preguntas_encuesta WHERE id_encuesta = :id_encuesta";
                $stmtEliminarPreguntas = $this->db->prepare($sqlEliminarPreguntas);
                $stmtEliminarPreguntas->bindParam(':id_encuesta', $id, PDO::PARAM_INT);
                $stmtEliminarPreguntas->execute();

                // Insertar las nuevas preguntas
                $this->insertarPreguntasYOpciones($id, $datos['preguntas']);
            }
            // --- Fin Actualización Preguntas ---

            $this->db->commit(); // Confirmar transacción
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en actualizarEncuesta (ID: {$id}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error General en actualizarEncuesta (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar una encuesta (marcarla como inactiva - Soft Delete).
     *
     * @param int $id ID de la encuesta a eliminar.
     * @param int $idUsuario ID del usuario que realiza la eliminación.
     * @return bool Éxito.
     */
    public function eliminarEncuesta($id, $idUsuario) {
        $this->db->beginTransaction(); // Iniciar transacción

        try {
            // --- Verificar Permisos ---
            $sqlVerificar = "
                SELECT e.id_usuario, e.id_negocio, n.id_usuario as negocio_usuario_id
                FROM encuestas e
                LEFT JOIN negocios n ON e.id_negocio = n.id
                WHERE e.id = :id AND e.activo = 1
            ";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $encuestaActual = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$encuestaActual) {
                throw new Exception("Encuesta no encontrada o ya inactiva.");
            }

            $esPropietarioEncuesta = ($encuestaActual['id_usuario'] == $idUsuario);
            $esPropietarioNegocio = (!empty($encuestaActual['negocio_usuario_id']) && $encuestaActual['negocio_usuario_id'] == $idUsuario);

            if (!$esPropietarioEncuesta && !$esPropietarioNegocio) {
                 throw new Exception("No tienes permiso para eliminar esta encuesta.");
            }
            // --- Fin Verificación Permisos ---

            // --- Ejecución de Eliminación Lógica ---
            // Marcar encuesta como inactiva
            $sqlEliminarEncuesta = "UPDATE encuestas SET activo = 0 WHERE id = :id";
            $stmtEliminarEncuesta = $this->db->prepare($sqlEliminarEncuesta);
            $stmtEliminarEncuesta->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminarEncuesta->execute();

            // Opcional: Marcar preguntas y opciones como inactivas también (mejor que borrar)
            $sqlInactivarPreguntas = "UPDATE preguntas_encuesta SET activo = 0 WHERE id_encuesta = :id_encuesta";
            $stmtInactivarPreguntas = $this->db->prepare($sqlInactivarPreguntas);
            $stmtInactivarPreguntas->bindParam(':id_encuesta', $id, PDO::PARAM_INT);
            $stmtInactivarPreguntas->execute();

            $sqlInactivarOpciones = "UPDATE opciones_pregunta SET activo = 0 WHERE id_pregunta IN (SELECT id FROM preguntas_encuesta WHERE id_encuesta = :id_encuesta)";
            $stmtInactivarOpciones = $this->db->prepare($sqlInactivarOpciones);
             $stmtInactivarOpciones->bindParam(':id_encuesta', $id, PDO::PARAM_INT);
            $stmtInactivarOpciones->execute();
            // --- Fin Ejecución ---

            $this->db->commit(); // Confirmar transacción
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en eliminarEncuesta (ID: {$id}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error General en eliminarEncuesta (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Guardar respuestas a una encuesta.
     *
     * @param int $idEncuesta ID de la encuesta.
     * @param array $respuestas Array asociativo [id_pregunta => respuesta].
     *                        'respuesta' puede ser id_opcion, array de id_opcion, texto, o 1/0.
     * @param int|null $idUsuario ID del usuario (null para anónimo).
     * @return bool Éxito.
     */
    public function guardarRespuestas($idEncuesta, $respuestas, $idUsuario = null) {
        $this->db->beginTransaction(); // Iniciar transacción

        try {
            // --- Verificar Encuesta Activa y Vigente ---
            $sqlVerificarEncuesta = "
                SELECT id, fecha_inicio, fecha_fin, activo
                FROM encuestas
                WHERE id = :id
            ";
            $stmtVerificarEncuesta = $this->db->prepare($sqlVerificarEncuesta);
            $stmtVerificarEncuesta->bindParam(':id', $idEncuesta, PDO::PARAM_INT);
            $stmtVerificarEncuesta->execute();
            $encuesta = $stmtVerificarEncuesta->fetch(PDO::FETCH_ASSOC);

            if (!$encuesta || !$encuesta['activo']) {
                throw new Exception("La encuesta no está disponible.");
            }
            $fechaActual = date('Y-m-d H:i:s');
            if ($encuesta['fecha_inicio'] > $fechaActual) {
                throw new Exception("La encuesta aún no ha comenzado.");
            }
            if ($encuesta['fecha_fin'] && $encuesta['fecha_fin'] < $fechaActual) {
                throw new Exception("La encuesta ha finalizado.");
            }
            // --- Fin Verificación Encuesta ---

            // --- Verificar si ya participó (opcional, pero recomendado) ---
            if ($idUsuario && $this->verificarParticipacion($idEncuesta, $idUsuario)) {
                throw new Exception("Ya has participado en esta encuesta.");
            }
            // --- Fin Verificación Participación ---


            // --- Obtener Preguntas para Validación ---
            $sqlPreguntas = "
                SELECT id, tipo, obligatoria FROM preguntas_encuesta
                WHERE id_encuesta = :id_encuesta AND activo = 1
            ";
            $stmtPreguntas = $this->db->prepare($sqlPreguntas);
            $stmtPreguntas->bindParam(':id_encuesta', $idEncuesta, PDO::PARAM_INT);
            $stmtPreguntas->execute();
            // Crear un mapa de preguntas por ID para fácil acceso
            $preguntasMap = $stmtPreguntas->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP);
            // $preguntasMap tendrá forma [id_pregunta => [[id=>..., tipo=>..., obligatoria=>...]]]

            if (empty($preguntasMap)) {
                 throw new Exception("No se encontraron preguntas activas para esta encuesta.");
            }
            // --- Fin Obtener Preguntas ---


            // --- Validar Respuestas Recibidas ---
            foreach ($preguntasMap as $idPregunta => $preguntaDetailsArray) {
                $preguntaInfo = $preguntaDetailsArray[0]; // Tomar el primer (y único) elemento
                // Verificar si la respuesta existe y no está vacía (considerar 0 como válido)
                $respuestaPresente = isset($respuestas[$idPregunta]) && ($respuestas[$idPregunta] !== '' && $respuestas[$idPregunta] !== null);

                if ($preguntaInfo['obligatoria'] && !$respuestaPresente) {
                    // Podrías buscar el texto de la pregunta para un mejor mensaje de error
                    throw new Exception("Falta respuesta obligatoria para la pregunta ID: {$idPregunta}.");
                }
                // Podría añadirse validación de tipo aquí (ej. si es seleccion_unica, ¿el valor es un ID válido?)
            }
            // --- Fin Validación Respuestas ---


            // --- Preparar Inserción ---
            $sqlRespuesta = "
                INSERT INTO respuestas_encuesta
                (id_pregunta, id_opcion, id_usuario, respuesta_texto, fecha_respuesta)
                VALUES
                (:id_pregunta, :id_opcion, :id_usuario, :respuesta_texto, NOW())
            ";
            $stmtRespuesta = $this->db->prepare($sqlRespuesta);
            // --- Fin Preparación ---


            // --- Guardar Respuestas ---
            foreach ($respuestas as $idPregunta => $respuesta) {
                 // Asegurarse que la pregunta existe en nuestro mapa y obtener su tipo
                if (!isset($preguntasMap[$idPregunta])) {
                    error_log("Intento de guardar respuesta para pregunta inexistente ID: {$idPregunta} en encuesta ID: {$idEncuesta}");
                    continue; // Ignorar respuesta para pregunta no encontrada
                }
                $tipoPregunta = $preguntasMap[$idPregunta][0]['tipo'];

                $idOpcion = null;
                $respuestaTexto = null;

                switch ($tipoPregunta) {
                    case 'opcion_multiple':
                        if (is_array($respuesta)) {
                            foreach ($respuesta as $optId) {
                                if (!empty($optId)) { // Asegurar que no sea vacío
                                    $idOpcion = (int) $optId;
                                    $respuestaTexto = null;
                                    $stmtRespuesta->execute([
                                        ':id_pregunta' => $idPregunta,
                                        ':id_opcion' => $idOpcion,
                                        ':id_usuario' => $idUsuario, // Puede ser NULL
                                        ':respuesta_texto' => $respuestaTexto
                                    ]);
                                }
                            }
                        }
                        continue 2; // Saltar al siguiente item del foreach principal

                    case 'seleccion_unica':
                        if (!empty($respuesta)) {
                            $idOpcion = (int) $respuesta;
                            $respuestaTexto = null;
                        }
                        break;

                    case 'texto_libre':
                    case 'escala': // Guardar escala como texto
                        $respuestaTexto = trim((string) $respuesta);
                        $idOpcion = null;
                         // Permitir respuesta vacía si no es obligatoria
                        if ($respuestaTexto === '' && !$preguntasMap[$idPregunta][0]['obligatoria']) {
                             continue 2; // No guardar respuesta vacía no obligatoria
                        }
                        break;

                    case 'si_no':
                        // Normalizar a '1' o '0' o NULL
                        if ($respuesta === true || $respuesta === 1 || $respuesta === '1' || strtolower($respuesta) === 'si') {
                            $respuestaTexto = '1';
                        } elseif ($respuesta === false || $respuesta === 0 || $respuesta === '0' || strtolower($respuesta) === 'no') {
                            $respuestaTexto = '0';
                        } else {
                            $respuestaTexto = null; // O manejar como error si debe ser sí o sí 1/0
                        }
                        $idOpcion = null;
                         if ($respuestaTexto === null && !$preguntasMap[$idPregunta][0]['obligatoria']) {
                             continue 2; // No guardar respuesta vacía no obligatoria
                         }
                        break;

                    default:
                         error_log("Tipo de pregunta desconocido '{$tipoPregunta}' para ID {$idPregunta}");
                         continue 2; // Saltar tipo desconocido
                }

                // Ejecutar inserción para tipos no múltiples o los que pasaron el switch
                if (isset($idOpcion) || isset($respuestaTexto)) { // Asegurar que haya algo que guardar
                    $stmtRespuesta->execute([
                        ':id_pregunta' => $idPregunta,
                        ':id_opcion' => $idOpcion,
                        ':id_usuario' => $idUsuario, // Puede ser NULL
                        ':respuesta_texto' => $respuestaTexto
                    ]);
                }
            }
            // --- Fin Guardar Respuestas ---

            $this->db->commit(); // Confirmar transacción
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en guardarRespuestas (Encuesta ID: {$idEncuesta}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error General en guardarRespuestas (Encuesta ID: {$idEncuesta}): " . $e->getMessage());
            return false;
        }
    }


    /**
     * Obtener resultados de una encuesta.
     * Optimizado para reducir consultas N+1.
     *
     * @param int $idEncuesta ID de la encuesta.
     * @param int|null $idUsuario ID del usuario que solicita (para verificar permisos).
     * @return array|null Resultados procesados o null si no tiene permiso o error.
     */
    public function obtenerResultadosEncuesta($idEncuesta, $idUsuario = null) {
        try {
            // --- Verificar Permisos y Existencia ---
            $sqlVerificar = "
                SELECT e.resultados_publicos, e.id_usuario, e.id_negocio, n.id_usuario as negocio_usuario_id
                FROM encuestas e
                LEFT JOIN negocios n ON e.id_negocio = n.id
                WHERE e.id = :id AND e.activo = 1
            ";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id', $idEncuesta, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $encuesta = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            $esPropietarioEncuesta = ($encuesta && $encuesta['id_usuario'] == $idUsuario);
            $esPropietarioNegocio = ($encuesta && !empty($encuesta['negocio_usuario_id']) && $encuesta['negocio_usuario_id'] == $idUsuario);
            $puedeVerResultados = ($encuesta && ($encuesta['resultados_publicos'] || $esPropietarioEncuesta || $esPropietarioNegocio));

            if (!$puedeVerResultados) {
                return null; // No tiene permiso o encuesta no encontrada/inactiva
            }
            // --- Fin Permisos ---


            // --- Obtener Preguntas ---
            $sqlPreguntas = "
                SELECT id, texto, tipo, orden FROM preguntas_encuesta
                WHERE id_encuesta = :id_encuesta AND activo = 1 ORDER BY orden
            ";
            $stmtPreguntas = $this->db->prepare($sqlPreguntas);
            $stmtPreguntas->bindParam(':id_encuesta', $idEncuesta, PDO::PARAM_INT);
            $stmtPreguntas->execute();
            $preguntas = $stmtPreguntas->fetchAll(PDO::FETCH_ASSOC);

            if (empty($preguntas)) return ['total_participantes' => 0, 'preguntas' => []]; // Encuesta sin preguntas activas
            // --- Fin Obtener Preguntas ---


            // --- Obtener Opciones Predefinidas (para preguntas de opción) ---
            $preguntaIds = array_column($preguntas, 'id');
            $placeholders = implode(',', array_fill(0, count($preguntaIds), '?'));
            $sqlOpciones = "
                SELECT id, id_pregunta, texto, orden FROM opciones_pregunta
                WHERE id_pregunta IN ({$placeholders}) AND activo = 1 ORDER BY id_pregunta, orden
            ";
            $stmtOpciones = $this->db->prepare($sqlOpciones);
            $stmtOpciones->execute($preguntaIds);
            $allOpciones = $stmtOpciones->fetchAll(PDO::FETCH_ASSOC);

            // Mapear opciones por ID de pregunta
            $opcionesPorPregunta = [];
            foreach ($allOpciones as $opcion) {
                $opcionesPorPregunta[$opcion['id_pregunta']][$opcion['id']] = $opcion; // Indexar por ID de opción
            }
            // --- Fin Obtener Opciones ---


            // --- Obtener TODAS las Respuestas ---
            $sqlRespuestas = "
                SELECT id_pregunta, id_opcion, respuesta_texto, id_usuario
                FROM respuestas_encuesta
                WHERE id_pregunta IN ({$placeholders})
            ";
            $stmtRespuestas = $this->db->prepare($sqlRespuestas);
            $stmtRespuestas->execute($preguntaIds);
            $allRespuestas = $stmtRespuestas->fetchAll(PDO::FETCH_ASSOC);
            // --- Fin Obtener Respuestas ---


            // --- Calcular Participantes Únicos ---
            $participantesUnicos = [];
            foreach ($allRespuestas as $resp) {
                 // Usar un identificador único (id_usuario o una clave si es anónimo, aunque aquí no se maneja explícitamente anónimo único)
                if ($resp['id_usuario'] !== null) {
                    $participantesUnicos[$resp['id_usuario']] = true;
                }
                 // Si se permitieran respuestas totalmente anónimas, contarlas sería más complejo.
            }
            $totalParticipantes = count($participantesUnicos);
            // --- Fin Calcular Participantes ---


            // --- Procesar Resultados por Pregunta ---
            $resultados = [
                'total_participantes' => $totalParticipantes,
                'preguntas' => []
            ];
            $respuestasAgrupadas = [];
             foreach ($allRespuestas as $resp) {
                 $respuestasAgrupadas[$resp['id_pregunta']][] = $resp;
             }

            foreach ($preguntas as $pregunta) {
                $idPregunta = $pregunta['id'];
                $respuestasPreguntaActual = $respuestasAgrupadas[$idPregunta] ?? [];
                $preguntaResultado = [
                    'id' => $idPregunta,
                    'texto' => $pregunta['texto'],
                    'tipo' => $pregunta['tipo'],
                    'resultados' => null // Inicializar
                ];

                switch ($pregunta['tipo']) {
                    case 'opcion_multiple':
                    case 'seleccion_unica':
                        $opcionesConteo = [];
                        // Inicializar conteo para todas las opciones predefinidas
                        $opcionesDefinidas = $opcionesPorPregunta[$idPregunta] ?? [];
                        foreach ($opcionesDefinidas as $optId => $optData) {
                            $opcionesConteo[$optId] = ['id' => $optId, 'texto' => $optData['texto'], 'conteo' => 0];
                        }
                        // Contar respuestas
                        foreach ($respuestasPreguntaActual as $resp) {
                            if (isset($opcionesConteo[$resp['id_opcion']])) {
                                $opcionesConteo[$resp['id_opcion']]['conteo']++;
                            }
                            // Podrías querer contar respuestas a opciones ya no activas si existen
                        }
                        $preguntaResultado['resultados'] = array_values($opcionesConteo); // Devolver como array indexado
                        break;

                    case 'texto_libre':
                        $textos = [];
                        foreach ($respuestasPreguntaActual as $resp) {
                            if (!empty($resp['respuesta_texto'])) {
                                $textos[] = $resp['respuesta_texto'];
                            }
                        }
                        $preguntaResultado['resultados'] = $textos;
                        break;

                    case 'escala':
                        $valores = [];
                        foreach ($respuestasPreguntaActual as $resp) {
                            if (isset($resp['respuesta_texto']) && is_numeric($resp['respuesta_texto'])) {
                                $valores[] = (float)$resp['respuesta_texto'];
                            }
                        }
                        $count = count($valores);
                        $preguntaResultado['resultados'] = [
                            'conteo' => $count,
                            'promedio' => $count > 0 ? round(array_sum($valores) / $count, 2) : null,
                            'minimo' => $count > 0 ? min($valores) : null,
                            'maximo' => $count > 0 ? max($valores) : null,
                            'distribucion' => array_count_values(array_map('strval', $valores)) // Contar ocurrencias de cada valor
                        ];
                        break;

                    case 'si_no':
                        $conteo = ['si' => 0, 'no' => 0];
                        foreach ($respuestasPreguntaActual as $resp) {
                            if ($resp['respuesta_texto'] === '1') {
                                $conteo['si']++;
                            } elseif ($resp['respuesta_texto'] === '0') {
                                $conteo['no']++;
                            }
                        }
                        $preguntaResultado['resultados'] = $conteo;
                        break;
                }
                $resultados['preguntas'][] = $preguntaResultado;
            }
            // --- Fin Procesar Resultados ---

            return $resultados;

        } catch (PDOException $e) {
            error_log("Error PDO en obtenerResultadosEncuesta (ID: {$idEncuesta}): " . $e->getMessage());
            return null;
        } catch (Exception $e) {
             error_log("Error General en obtenerResultadosEncuesta (ID: {$idEncuesta}): " . $e->getMessage());
             return null;
        }
    }


    /**
     * Obtener listado de encuestas creadas por un usuario o asociadas a sus negocios.
     *
     * @param int $idUsuario ID del usuario.
     * @return array Lista de encuestas activas del usuario.
     */
    public function obtenerEncuestasUsuario($idUsuario) {
         // Esta consulta con subqueries es aceptable si no hay millones de encuestas/respuestas
         // Podría optimizarse calculando num_participantes por separado si es necesario.
        $sql = "
            SELECT
                e.id,
                e.titulo,
                e.fecha_inicio,
                e.fecha_fin,
                e.fecha_creacion,
                e.resultados_publicos,
                (SELECT COUNT(*) FROM preguntas_encuesta WHERE id_encuesta = e.id AND activo = 1) as num_preguntas,
                (SELECT COUNT(DISTINCT r.id_usuario) FROM respuestas_encuesta r JOIN preguntas_encuesta p ON r.id_pregunta = p.id WHERE p.id_encuesta = e.id) as num_participantes,
                n.nombre_comercial as negocio_nombre
            FROM
                encuestas e
                LEFT JOIN negocios n ON e.id_negocio = n.id
            WHERE
                (e.id_usuario = :id_usuario_encuesta OR n.id_usuario = :id_usuario_negocio)
                AND e.activo = 1
            ORDER BY
                e.fecha_creacion DESC
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id_usuario_encuesta', $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario_negocio', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerEncuestasUsuario (Usuario ID: {$idUsuario}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar si un usuario ya ha respondido una encuesta específica.
     *
     * @param int $idEncuesta ID de la encuesta.
     * @param int $idUsuario ID del usuario.
     * @return bool True si ya ha respondido, False si no o en caso de error.
     */
    public function verificarParticipacion($idEncuesta, $idUsuario) {
        if (!$idUsuario) { // No se puede verificar para usuarios no logueados
            return false;
        }

        // Una forma eficiente es buscar solo UNA respuesta del usuario para esa encuesta
        $sql = "
            SELECT 1
            FROM respuestas_encuesta r
            JOIN preguntas_encuesta p ON r.id_pregunta = p.id
            WHERE p.id_encuesta = :id_encuesta
              AND r.id_usuario = :id_usuario
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id_encuesta', $idEncuesta, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchColumn() !== false; // fetchColumn devuelve false si no hay filas

        } catch (PDOException $e) {
            error_log("Error en verificarParticipacion (Encuesta: {$idEncuesta}, Usuario: {$idUsuario}): " . $e->getMessage());
            return false; // Asumir que no ha participado en caso de error
        }
    }

} // Fin de la clase EncuestaController