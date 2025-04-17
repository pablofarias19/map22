<?php

use PDO;
use PDOException;
use Exception; // Asegúrate de que Exception está disponible

/**
 * OfertaController.php
 * Controlador para la gestión de ofertas en la aplicación geoespacial.
 */
class OfertaController {
    private $db;

    /**
     * Constructor que recibe la conexión a la base de datos.
     * @param PDO $db Conexión PDO a la base de datos.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Obtener ofertas cercanas a una ubicación y dentro de un radio.
     *
     * @param float $lat Latitud del centro de búsqueda.
     * @param float $lng Longitud del centro de búsqueda.
     * @param int $radio Radio de búsqueda en metros.
     * @param array $filtros Filtros adicionales (ej: ['precio_max' => 50, 'descuento_min' => 10, 'id_negocio' => 5]).
     * @param int $limit Número máximo de resultados a devolver.
     * @return array Datos de ofertas en formato GeoJSON FeatureCollection.
     */
    public function obtenerOfertasCercanas($lat, $lng, $radio, $filtros = [], $limit = 200) {
        // Convertir radio a grados (aproximación para el filtro WHERE inicial).
        $radioGrados = $radio / 111000.0;

        // Preparar parámetros base para la consulta SQL
        $parametros = [
            ':lat' => $lat,
            ':lng' => $lng,
            ':radio_grados' => $radioGrados, // Usado en el WHERE (aproximado)
            ':radio_metros' => $radio,       // Usado en el HAVING (preciso)
            ':activo' => 1,
            ':fecha_actual' => date('Y-m-d H:i:s'), // Para filtrar ofertas vigentes
            ':limit' => (int) $limit // Asegurar que es un entero
        ];

        // Preparar condiciones de filtro dinámicas
        $condicionesFiltro = [];

        // Por defecto, solo ofertas vigentes (que iniciaron antes o ahora y terminan ahora o después)
        $condicionesFiltro[] = "o.fecha_inicio <= :fecha_actual AND o.fecha_fin >= :fecha_actual";

        // Filtro por precio máximo
        if (!empty($filtros['precio_max']) && is_numeric($filtros['precio_max'])) {
            $condicionesFiltro[] = "o.precio_oferta <= :precio_max";
            $parametros[':precio_max'] = $filtros['precio_max'];
        }

        // Filtro por descuento mínimo
        if (!empty($filtros['descuento_min']) && is_numeric($filtros['descuento_min'])) {
             // Asegurar que el descuento sea un valor razonable (ej. 0-100) si es necesario
            $condicionesFiltro[] = "o.porcentaje_descuento >= :descuento_min";
            $parametros[':descuento_min'] = $filtros['descuento_min'];
        }

        // Filtro por negocio específico
        if (!empty($filtros['id_negocio']) && is_numeric($filtros['id_negocio'])) {
            $condicionesFiltro[] = "o.id_negocio = :id_negocio";
            $parametros[':id_negocio'] = (int) $filtros['id_negocio'];
        }

        // Construir la parte WHERE de los filtros
        $filtrosSQL = empty($condicionesFiltro) ? "" : "AND " . implode(" AND ", $condicionesFiltro);

        // Consulta SQL para ofertas, manejando ubicación de oferta u heredada de negocio
        $sql = "
            SELECT
                o.id,
                o.titulo,
                o.descripcion,
                o.precio_normal,
                o.precio_oferta,
                o.porcentaje_descuento,
                o.fecha_inicio,
                o.fecha_fin,
                o.disponibilidad,
                o.imagen,
                -- Usa la latitud/longitud de la oferta si existe, si no, la del negocio
                COALESCE(o.latitud, n.latitud) as latitud,
                COALESCE(o.longitud, n.longitud) as longitud,
                o.fecha_creacion,
                o.id_negocio,
                n.nombre_comercial as negocio_nombre,
                p.id as id_producto,
                p.nombre as producto_nombre,
                -- Cálculo de distancia usando la ubicación determinada por COALESCE
                (6371000 * acos(
                    cos(radians(:lat)) * cos(radians(COALESCE(o.latitud, n.latitud))) *
                    cos(radians(COALESCE(o.longitud, n.longitud)) - radians(:lng)) +
                    sin(radians(:lat)) * sin(radians(COALESCE(o.latitud, n.latitud)))
                )) as distancia
            FROM
                ofertas o
                LEFT JOIN negocios n ON o.id_negocio = n.id
                LEFT JOIN productos p ON o.id_producto = p.id
            WHERE
                o.activo = :activo
                -- Condición de ubicación: Verifica el bounding box para la ubicación de la oferta O la del negocio
                AND (
                    (o.latitud IS NOT NULL AND o.longitud IS NOT NULL AND
                     o.latitud BETWEEN (:lat - :radio_grados) AND (:lat + :radio_grados) AND
                     o.longitud BETWEEN (:lng - :radio_grados) AND (:lng + :radio_grados))
                    OR
                    (o.latitud IS NULL AND o.longitud IS NULL AND n.id IS NOT NULL AND -- Asegura que haya un negocio asociado
                     n.latitud BETWEEN (:lat - :radio_grados) AND (:lat + :radio_grados) AND
                     n.longitud BETWEEN (:lng - :radio_grados) AND (:lng + :radio_grados))
                )
                {$filtrosSQL}
            HAVING
                -- Filtro preciso usando la distancia calculada y el radio en metros
                distancia <= :radio_metros
            ORDER BY
                o.porcentaje_descuento DESC, -- Prioriza mayor descuento
                o.fecha_fin ASC            -- Luego las que terminan antes
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);

            // Bind de los parámetros
            foreach ($parametros as $key => $value) {
                // Especificar tipos para parámetros clave
                if (in_array($key, [':limit', ':activo', ':id_negocio'])) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } elseif (in_array($key, [':lat', ':lng', ':radio_grados', ':radio_metros', ':precio_max', ':descuento_min'])) {
                     $stmt->bindValue($key, $value, PDO::PARAM_STR); // PDO trata decimales/floats como strings a menudo
                } elseif ($key == ':fecha_actual') {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } else {
                     // Para otros filtros dinámicos (asumimos string/int)
                     $stmt->bindValue($key, $value);
                }
            }

            $stmt->execute();
            $ofertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear para GeoJSON
            $features = [];
            foreach ($ofertas as $oferta) {
                // Asegurarse que lat/lon son floats para JSON
                $lat = isset($oferta['latitud']) ? (float) $oferta['latitud'] : null;
                $lon = isset($oferta['longitud']) ? (float) $oferta['longitud'] : null;

                 // Solo incluir en GeoJSON si tenemos coordenadas válidas
                if ($lat !== null && $lon !== null) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => $oferta, // Incluye la distancia calculada
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [$lon, $lat] // GeoJSON es [longitud, latitud]
                        ]
                    ];
                }
                // Podrías decidir qué hacer si una oferta no tiene ubicación ni negocio asociado con ubicación
            }

            return [
                'type' => 'FeatureCollection',
                'features' => $features
            ];
        } catch (PDOException $e) {
            error_log("Error PDO en obtenerOfertasCercanas: " . $e->getMessage() . " SQL: " . $sql); // Loguear SQL ayuda a depurar
            return [
                'type' => 'FeatureCollection',
                'features' => []
            ];
        }
    }

    /**
     * Obtener detalles completos de una oferta por su ID.
     *
     * @param int $id ID de la oferta a buscar.
     * @return array|null Datos completos de la oferta si se encuentra y está activa, o null si no.
     */
    public function obtenerOfertaDetalle($id) {
        $sql = "
            SELECT
                o.*, -- Selecciona todas las columnas de la tabla ofertas
                n.nombre_comercial as negocio_nombre,
                n.direccion as negocio_direccion,
                n.latitud as negocio_latitud,
                n.longitud as negocio_longitud,
                p.nombre as producto_nombre,
                p.marca as producto_marca,
                p.designacion_tecnica as producto_designacion
            FROM
                ofertas o
                LEFT JOIN negocios n ON o.id_negocio = n.id
                LEFT JOIN productos p ON o.id_producto = p.id
            WHERE
                o.id = :id AND o.activo = 1 -- Asegura que la oferta exista y esté activa
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Enlazar el parámetro :id de forma segura, especificando el tipo INT
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oferta = $stmt->fetch(PDO::FETCH_ASSOC);

            return $oferta ?: null; // Retorna el array o null si fetch devuelve false

        } catch (PDOException $e) {
            error_log("Error en obtenerOfertaDetalle (ID: {$id}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear una nueva oferta.
     *
     * @param array $datos Datos de la oferta (ej: ['titulo' => 'Oferta Especial', 'id_negocio' => 1, ...]).
     * @param int $idUsuario ID del usuario propietario/creador de la oferta.
     * @return int|false ID de la oferta recién creada o false en caso de error.
     */
    public function crearOferta($datos, $idUsuario) {
        // --- Validación básica inicial ---
        if (empty($datos['titulo']) ||
            empty($datos['fecha_inicio']) || // Fecha inicio obligatoria
            empty($datos['fecha_fin'])) {    // Fecha fin obligatoria
            error_log("Error en crearOferta: Faltan datos obligatorios (título, fecha inicio o fecha fin).");
            return false;
        }
        // Asegurar ubicación: o ID de negocio o coordenadas propias
        if (empty($datos['id_negocio']) && (!isset($datos['latitud']) || !isset($datos['longitud']))) {
             error_log("Error en crearOferta: Se requiere id_negocio o coordenadas (latitud, longitud).");
             return false;
        }
        // --- Podrías añadir validaciones más específicas (formato fecha, numérico, existencia ID negocio/producto) ---

        // --- Calcular porcentaje de descuento (con protección división por cero) ---
        if (!isset($datos['porcentaje_descuento']) && !empty($datos['precio_normal']) && isset($datos['precio_oferta'])) {
             if ($datos['precio_normal'] != 0) { // Evitar división por cero
                $descuento = round((1 - ($datos['precio_oferta'] / $datos['precio_normal'])) * 100);
                // Asegurar que el descuento calculado esté en un rango razonable (ej. 0-100)
                $datos['porcentaje_descuento'] = max(0, min(100, $descuento));
             } else {
                 // Si precio normal es 0, el descuento no tiene sentido o es 100% si el precio oferta también es 0
                 $datos['porcentaje_descuento'] = ($datos['precio_oferta'] == 0) ? 100 : null; // O manejar como error
             }
        }
        // --- Fin Cálculo Descuento ---

        // Lista de campos permitidos y que pueden venir en $datos
        $camposPermitidos = [
            'id_negocio', 'id_producto', 'titulo', 'descripcion',
            'precio_normal', 'precio_oferta', 'porcentaje_descuento',
            'fecha_inicio', 'fecha_fin', 'disponibilidad', 'imagen',
            'latitud', 'longitud'
            // 'activo' se gestiona internamente (default 1 en DB o set aquí), 'id_usuario' viene del parámetro
        ];

        $columnasSql = ['id_usuario']; // Siempre incluir el usuario creador
        $placeholdersSql = [':id_usuario'];
        $parametros = [':id_usuario' => $idUsuario];

        foreach ($camposPermitidos as $campo) {
            // Usar array_key_exists para incluir explícitamente NULL si se envía
            if (array_key_exists($campo, $datos)) {
                 $columnasSql[] = $campo;
                 $placeholder = ':' . $campo;
                 $placeholdersSql[] = $placeholder;
                 // Convertir string vacío a NULL podría ser útil dependiendo de la DB
                 $parametros[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo];
            }
        }

        // Construir la consulta SQL de forma segura
        $sql = sprintf(
            "INSERT INTO ofertas (%s) VALUES (%s)",
            implode(', ', $columnasSql),
            implode(', ', $placeholdersSql)
        );

        try {
            $this->db->beginTransaction(); // Iniciar transacción

            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametros);
            $idOferta = $this->db->lastInsertId();

            $this->db->commit(); // Confirmar transacción
            return (int) $idOferta; // Devolver el ID como entero

        } catch (PDOException $e) {
            $this->db->rollBack(); // Revertir en caso de error
            error_log("Error PDO en crearOferta: " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($parametros));
            return false;
        } catch (Exception $e) { // Capturar otras posibles excepciones
             $this->db->rollBack();
             error_log("Error General en crearOferta: " . $e->getMessage());
             return false;
        }
    }


    /**
     * Actualizar una oferta existente.
     *
     * @param int $id ID de la oferta a actualizar.
     * @param array $datos Nuevos datos de la oferta. Claves deben coincidir con columnas permitidas.
     * @param int $idUsuario ID del usuario que realiza la actualización (para verificación de permisos).
     * @return bool True si la actualización fue exitosa, False en caso contrario.
     */
    public function actualizarOferta($id, $datos, $idUsuario) {
        $this->db->beginTransaction(); // Iniciar transacción al principio

        try {
            // --- Verificar Permisos ---
            // Obtener la oferta y la información del propietario del negocio asociado
            $sqlVerificar = "
                SELECT o.id_usuario, o.id_negocio, n.id_usuario as negocio_usuario_id
                FROM ofertas o
                LEFT JOIN negocios n ON o.id_negocio = n.id
                WHERE o.id = :id AND o.activo = 1
            ";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->execute([':id' => $id]);
            $ofertaActual = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$ofertaActual) {
                throw new Exception("Oferta no encontrada o inactiva.");
            }

            $esPropietarioOferta = ($ofertaActual['id_usuario'] == $idUsuario);
            $esPropietarioNegocioAsociado = (!empty($ofertaActual['negocio_usuario_id']) && $ofertaActual['negocio_usuario_id'] == $idUsuario);

            // Permitir si es el creador de la oferta O el propietario del negocio asociado
            if (!$esPropietarioOferta && !$esPropietarioNegocioAsociado) {
                 throw new Exception("No tienes permiso para editar esta oferta.");
            }
            // --- Fin Verificación Permisos ---


            // --- Calcular porcentaje de descuento si aplica (con protección división por cero) ---
            if (!isset($datos['porcentaje_descuento']) && isset($datos['precio_normal']) && isset($datos['precio_oferta'])) {
                if ($datos['precio_normal'] != 0) {
                    $descuento = round((1 - ($datos['precio_oferta'] / $datos['precio_normal'])) * 100);
                    $datos['porcentaje_descuento'] = max(0, min(100, $descuento));
                } else {
                     $datos['porcentaje_descuento'] = ($datos['precio_oferta'] == 0) ? 100 : null;
                }
            }
            // --- Fin Cálculo Descuento ---


            // --- Preparar Actualización ---
            $camposPermitidos = [
                'id_negocio', 'id_producto', 'titulo', 'descripcion',
                'precio_normal', 'precio_oferta', 'porcentaje_descuento',
                'fecha_inicio', 'fecha_fin', 'disponibilidad', 'imagen',
                'latitud', 'longitud'
                // 'activo' se gestiona con eliminarOferta
            ];

            $actualizacionesSql = [];
            $parametros = [':id' => $id]; // ID siempre necesario para el WHERE

            foreach ($camposPermitidos as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $placeholder = ':' . $campo;
                    $actualizacionesSql[] = "{$campo} = {$placeholder}";
                    $parametros[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo];
                }
            }

            if (empty($actualizacionesSql)) {
                 // No hay error, pero no hay nada que hacer. Commit vacío está bien.
                 error_log("actualizarOferta (ID: {$id}): No se proporcionaron datos para actualizar.");
                 $this->db->commit();
                 return true;
            }

            // Construir la consulta de actualización
            $sqlActualizar = sprintf(
                "UPDATE ofertas SET %s WHERE id = :id",
                implode(', ', $actualizacionesSql)
            );
            // --- Fin Preparar Actualización ---


            // --- Ejecutar Actualización ---
            $stmtActualizar = $this->db->prepare($sqlActualizar);
            $stmtActualizar->execute($parametros);

            $this->db->commit(); // Confirmar transacción
            return true; // Éxito

        } catch (PDOException $e) {
            $this->db->rollBack(); // Revertir en caso de error PDO
            error_log("Error PDO en actualizarOferta (ID: {$id}): " . $e->getMessage() . (isset($sqlActualizar) ? " SQL: " . $sqlActualizar : "") . " Params: " . json_encode($parametros ?? []));
            return false;
        } catch (Exception $e) { // Capturar otras excepciones (permisos, etc.)
             $this->db->rollBack(); // Revertir en caso de error general
             error_log("Error General en actualizarOferta (ID: {$id}): " . $e->getMessage());
             return false;
        }
        // --- Fin Ejecutar Actualización ---
    }

    /**
     * Eliminar una oferta (marcarla como inactiva - Soft Delete).
     *
     * @param int $id ID de la oferta a eliminar.
     * @param int $idUsuario ID del usuario que realiza la eliminación (para verificación de permisos).
     * @return bool True si la eliminación lógica fue exitosa, False en caso contrario.
     */
    public function eliminarOferta($id, $idUsuario) {
        $this->db->beginTransaction(); // Iniciar transacción

        try {
            // --- Verificar Permisos (igual que en actualizar) ---
            $sqlVerificar = "
                SELECT o.id_usuario, o.id_negocio, n.id_usuario as negocio_usuario_id
                FROM ofertas o
                LEFT JOIN negocios n ON o.id_negocio = n.id
                WHERE o.id = :id AND o.activo = 1
            ";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->execute([':id' => $id]);
            $ofertaActual = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$ofertaActual) {
                throw new Exception("Oferta no encontrada o ya inactiva.");
            }

            $esPropietarioOferta = ($ofertaActual['id_usuario'] == $idUsuario);
            $esPropietarioNegocioAsociado = (!empty($ofertaActual['negocio_usuario_id']) && $ofertaActual['negocio_usuario_id'] == $idUsuario);

            if (!$esPropietarioOferta && !$esPropietarioNegocioAsociado) {
                 throw new Exception("No tienes permiso para eliminar esta oferta.");
            }
            // --- Fin Verificación Permisos ---


            // --- Ejecución de Eliminación Lógica ---
            $sqlEliminar = "UPDATE ofertas SET activo = 0 WHERE id = :id";
            $stmtEliminar = $this->db->prepare($sqlEliminar);
            // Usar bindParam para el ID aquí es buena práctica
            $stmtEliminar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminar->execute();

            $this->db->commit(); // Confirmar transacción
            return true; // Éxito

        } catch (PDOException $e) {
            $this->db->rollBack(); // Revertir en caso de error PDO
            error_log("Error PDO en eliminarOferta (ID: {$id}): " . $e->getMessage());
            return false;
        } catch (Exception $e) { // Capturar otras excepciones (permisos, etc.)
             $this->db->rollBack(); // Revertir en caso de error general
             error_log("Error General en eliminarOferta (ID: {$id}): " . $e->getMessage());
             return false;
        }
    }

    /**
     * Obtener listado de ofertas creadas por un usuario o asociadas a sus negocios.
     *
     * @param int $idUsuario ID del usuario.
     * @return array Lista de ofertas activas del usuario, o array vacío en caso de error.
     */
    public function obtenerOfertasUsuario($idUsuario) {
        $sql = "
            SELECT
                o.id,
                o.titulo,
                o.precio_normal,
                o.precio_oferta,
                o.porcentaje_descuento,
                o.fecha_inicio,
                o.fecha_fin,
                o.disponibilidad,
                o.fecha_creacion,
                n.nombre_comercial as negocio_nombre,
                p.nombre as producto_nombre
            FROM
                ofertas o
                LEFT JOIN negocios n ON o.id_negocio = n.id
                LEFT JOIN productos p ON o.id_producto = p.id
            WHERE
                -- La oferta fue creada por el usuario O está asociada a un negocio del usuario
                (o.id_usuario = :id_usuario_oferta OR n.id_usuario = :id_usuario_negocio)
                AND o.activo = 1 -- Solo ofertas activas
            ORDER BY
                o.fecha_creacion DESC -- Más recientes primero
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Enlazar el ID de usuario a ambos placeholders
            $stmt->bindParam(':id_usuario_oferta', $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario_negocio', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerOfertasUsuario (Usuario ID: {$idUsuario}): " . $e->getMessage());
            return []; // Devolver array vacío en caso de error
        }
    }

    /**
     * Obtener listado de productos activos de un negocio específico (útil para crear ofertas).
     *
     * @param int $idNegocio ID del negocio.
     * @return array Lista de productos, o array vacío en caso de error.
     */
    public function obtenerProductosNegocio($idNegocio) {
        $sql = "
            SELECT
                id, nombre, marca, descripcion, precio, stock, imagen
            FROM
                productos
            WHERE
                id_negocio = :id_negocio
                AND activo = 1 -- Solo productos activos
            ORDER BY
                nombre ASC
        ";

        try {
            $stmt = $this->db->prepare($sql);
             // Especificar el tipo de dato para el ID de negocio
            $stmt->bindParam(':id_negocio', $idNegocio, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerProductosNegocio (Negocio ID: {$idNegocio}): " . $e->getMessage());
            return []; // Devolver array vacío en caso de error
        }
    }
} // Fin de la clase OfertaController