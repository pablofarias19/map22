<?php

use PDO;
use PDOException;
use Exception;

/**
 * NegocioController.php
 * Controlador para la gestión de negocios en la aplicación geoespacial.
 */
class NegocioController {
    private $db;
    private $activo = 1; // Estado activo por defecto

    /**
     * Constructor que recibe la conexión a la base de datos.
     * @param PDO $db Conexión PDO a la base de datos.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Obtener listado de categorías de negocios disponibles.
     * @return array Lista de categorías con ID y nombre.
     */
    public function obtenerCategoriasNegocios() {
        try {
            $sql = "SELECT id, nombre, descripcion FROM categorias_negocios WHERE activo = 1 ORDER BY nombre ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerCategoriasNegocios: " . $e->getMessage());
            return []; // Devolver array vacío en caso de error
        }
    }

    /**
     * Obtener negocios cercanos a una ubicación y dentro de un radio.
     *
     * @param float $lat Latitud del centro de búsqueda.
     * @param float $lng Longitud del centro de búsqueda.
     * @param int $radio Radio de búsqueda en metros.
     * @param array $filtros Filtros adicionales (ej: ['categorias' => '1,2', 'areas_produccion' => '3']).
     * @param int $limit Número máximo de resultados a devolver.
     * @return array Datos de negocios en formato GeoJSON FeatureCollection.
     */
    public function obtenerNegociosCercanos($lat, $lng, $radio, $filtros = [], $limit = 200) {
        // Validar y asegurar que el radio es un número positivo
        $radioMetros = max(0, (int)$radio); 
        if ($radioMetros === 0) {
            // Si el radio es 0 o inválido, devolver colección vacía
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        // Convertir radio a grados (aproximación para bounding box)
        $radioGrados = $radioMetros / 111320; // Aproximación más estándar: 1 grado ≈ 111.32 km

        // Preparar condiciones de filtros y parámetros PDO
        $condicionesFiltro = [];
        $parametros = [
            ':lat' => $lat,
            ':lng' => $lng,
            ':radio_grados_lat' => $radioGrados, // Usar radio en grados para el bounding box
            ':radio_grados_lng' => $radioGrados / cos(deg2rad($lat)), // Ajuste de longitud por latitud
            ':radio_metros' => $radioMetros, // Usar radio en metros para el HAVING
            ':activo' => $this->activo,
            ':limit' => (int) $limit // Asegurar que es un entero
        ];

        // Construir filtros dinámicos de forma segura
        if (!empty($filtros['categorias'])) {
            $categorias = explode(',', $filtros['categorias']);
            $placeholders = [];
            foreach ($categorias as $i => $cat) {
                // Validar o limpiar cada categoría si es necesario
                $paramName = ":cat" . $i;
                $placeholders[] = $paramName;
                $parametros[$paramName] = trim($cat); // Asegurarse de quitar espacios
            }
            if (!empty($placeholders)) {
                $condicionesFiltro[] = "n.id_categoria IN (" . implode(", ", $placeholders) . ")";
            }
        }

        if (!empty($filtros['areas_produccion'])) {
            $areas = explode(',', $filtros['areas_produccion']);
            $placeholders = [];
            foreach ($areas as $i => $area) {
                // Validar o limpiar cada área si es necesario
                $paramName = ":area" . $i;
                $placeholders[] = $paramName;
                $parametros[$paramName] = trim($area);
            }
            if (!empty($placeholders)) {
                $condicionesFiltro[] = "n.id_area_produccion IN (" . implode(", ", $placeholders) . ")";
            }
        }

        $filtrosSQL = empty($condicionesFiltro) ? "" : "AND " . implode(" AND ", $condicionesFiltro);

        // Consulta SQL usando Haversine para distancia precisa y bounding box para optimización
        $sql = "
            SELECT 
                n.id, 
                n.nombre_comercial, 
                n.lema_publicitario, 
                n.direccion, 
                n.telefono, 
                n.email, 
                n.sitio_web, 
                n.latitud, 
                n.longitud, 
                n.imagen_principal, 
                n.link_video,
                n.fecha_creacion, 
                c.nombre as categoria,
                a.nombre as area_produccion,
                -- Cálculo de distancia Haversine en metros
                (6371000 * acos(
                    cos(radians(:lat)) * cos(radians(n.latitud)) * 
                    cos(radians(n.longitud) - radians(:lng)) + 
                    sin(radians(:lat)) * sin(radians(n.latitud))
                )) as distancia 
            FROM 
                negocios n
                LEFT JOIN categorias_negocios c ON n.id_categoria = c.id
                LEFT JOIN areas_produccion a ON n.id_area_produccion = a.id
            WHERE 
                n.activo = :activo
                -- Bounding Box (Optimización)
                AND n.latitud BETWEEN (:lat - :radio_grados_lat) AND (:lat + :radio_grados_lat)
                AND n.longitud BETWEEN (:lng - :radio_grados_lng) AND (:lng + :radio_grados_lng)
                {$filtrosSQL}
            HAVING 
                -- Filtro final por distancia precisa (usando placeholder seguro)
                distancia <= :radio_metros 
            ORDER BY 
                distancia ASC
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Bind de parámetros especificando tipos
            foreach ($parametros as $key => $value) {
                if (in_array($key, [':limit', ':activo'])) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } elseif (in_array($key, [':lat', ':lng', ':radio_grados_lat', ':radio_grados_lng', ':radio_metros'])) {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } else {
                    // Para :catX, :areaX 
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            $negocios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear para GeoJSON
            $features = [];
            foreach ($negocios as $negocio) {
                // Asegurarse que lat/lon son floats para JSON
                $lat = (float) $negocio['latitud'];
                $lon = (float) $negocio['longitud'];

                $features[] = [
                    'type' => 'Feature',
                    'properties' => $negocio, // Incluye todos los datos incluyendo distancia
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
            error_log("Error en obtenerNegociosCercanos: " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($parametros, true));
            // Devolver colección vacía en caso de error grave
            return ['type' => 'FeatureCollection', 'features' => []];
        }
    }

    /**
     * Obtener detalles completos de un negocio específico por su ID.
     * Incluye productos, ofertas vigentes y relaciones comerciales.
     * @param int $id ID del negocio.
     * @return array|null Datos completos del negocio o null si no se encuentra/inactivo.
     */
    public function obtenerNegocioDetalle($id) {
        $negocio = null; // Inicializar

        try {
            // 1. Obtener datos principales del negocio
            $sql_negocio = "
                SELECT 
                    n.*, 
                    c.nombre as categoria_nombre,
                    a.nombre as area_produccion_nombre,
                    l.nombre as localidad_nombre,
                    l.provincia,
                    l.pais
                FROM 
                    negocios n
                    LEFT JOIN categorias_negocios c ON n.id_categoria = c.id
                    LEFT JOIN areas_produccion a ON n.id_area_produccion = a.id
                    LEFT JOIN localidades l ON n.id_localidad = l.id
                WHERE 
                    n.id = :id AND n.activo = :activo
                LIMIT 1"; 

            $stmt_negocio = $this->db->prepare($sql_negocio);
            $stmt_negocio->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_negocio->bindParam(':activo', $this->activo, PDO::PARAM_INT);
            $stmt_negocio->execute();
            $negocio = $stmt_negocio->fetch(PDO::FETCH_ASSOC);

            // Si no se encontró el negocio o está inactivo, retornar null
            if (!$negocio) {
                return null;
            }
            
            // Renombrar campos para claridad en el frontend
            $negocio['categoria'] = $negocio['categoria_nombre']; 
            unset($negocio['categoria_nombre']);
            $negocio['area_produccion'] = $negocio['area_produccion_nombre']; 
            unset($negocio['area_produccion_nombre']);
            $negocio['localidad'] = $negocio['localidad_nombre']; 
            unset($negocio['localidad_nombre']);
            
            // 2. Obtener productos asociados
            $sql_productos = "SELECT * FROM productos WHERE id_negocio = :id_negocio AND activo = :activo ORDER BY nombre ASC";
            $stmt_productos = $this->db->prepare($sql_productos);
            $stmt_productos->bindParam(':id_negocio', $id, PDO::PARAM_INT);
            $stmt_productos->bindParam(':activo', $this->activo, PDO::PARAM_INT);
            $stmt_productos->execute();
            $negocio['productos'] = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

            // 3. Obtener ofertas vigentes asociadas
            $sql_ofertas = "
                SELECT * 
                FROM ofertas 
                WHERE id_negocio = :id_negocio 
                  AND activo = :activo 
                  AND fecha_inicio <= CURDATE() 
                  AND fecha_fin >= CURDATE() 
                ORDER BY fecha_fin ASC";
            $stmt_ofertas = $this->db->prepare($sql_ofertas);
            $stmt_ofertas->bindParam(':id_negocio', $id, PDO::PARAM_INT);
            $stmt_ofertas->bindParam(':activo', $this->activo, PDO::PARAM_INT);
            $stmt_ofertas->execute();
            $negocio['ofertas'] = $stmt_ofertas->fetchAll(PDO::FETCH_ASSOC);

            // 4. Obtener relaciones comerciales
            $sql_relaciones = "
                SELECT 
                    r.tipo_relacion, 
                    n2.id, 
                    n2.nombre_comercial as nombre 
                FROM 
                    relaciones r 
                    JOIN negocios n2 ON r.id_negocio_relacionado = n2.id 
                WHERE 
                    r.id_negocio_base = :id_negocio_base 
                    AND n2.activo = :activo 
                    AND r.estado = 'confirmada'
                ORDER BY r.tipo_relacion, n2.nombre_comercial";
            
            $stmt_relaciones = $this->db->prepare($sql_relaciones);
            $stmt_relaciones->bindParam(':id_negocio_base', $id, PDO::PARAM_INT);
            $stmt_relaciones->bindParam(':activo', $this->activo, PDO::PARAM_INT);
            $stmt_relaciones->execute();
            $relaciones_raw = $stmt_relaciones->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar relaciones por tipo
            $negocio['relaciones'] = [];
            foreach ($relaciones_raw as $rel) {
                $tipo = $rel['tipo_relacion']; // Ej: 'proveedor', 'cliente'
                $tipo_plural = $tipo . 'es'; // Simple pluralización, ajustar si es necesario
                if (!isset($negocio['relaciones'][$tipo_plural])) {
                    $negocio['relaciones'][$tipo_plural] = [];
                }
                $negocio['relaciones'][$tipo_plural][] = [
                    'id' => $rel['id'],
                    'nombre' => $rel['nombre']
                ];
            }
            
            // 5. Obtener propiedades si es inmobiliaria
            if ($negocio['categoria'] === 'Inmobiliaria') {
                $sql_propiedades = "SELECT * FROM propiedades WHERE id_inmobiliaria = :id_inmobiliaria AND activo = :activo ORDER BY fecha_publicacion DESC";
                $stmt_propiedades = $this->db->prepare($sql_propiedades);
                $stmt_propiedades->bindParam(':id_inmobiliaria', $id, PDO::PARAM_INT);
                $stmt_propiedades->bindParam(':activo', $this->activo, PDO::PARAM_INT);
                $stmt_propiedades->execute();
                $negocio['propiedades'] = $stmt_propiedades->fetchAll(PDO::FETCH_ASSOC);
            }

            // Retornar el objeto negocio completo con sus datos relacionados
            return $negocio;

        } catch (PDOException $e) {
            error_log("Error en obtenerNegocioDetalle (ID: {$id}): " . $e->getMessage());
            return null; // Retornar null en caso de error
        }
    }

    /**
     * Crear un nuevo negocio.
     *
     * @param array $datos Datos del negocio a crear.
     * @param int $idUsuario ID del usuario creador.
     * @return int|false ID del negocio creado o false en caso de error.
     */
    public function crearNegocio($datos, $idUsuario) {
        // Validar datos básicos
        if (empty($datos['nombre_comercial']) || 
            !isset($datos['latitud']) ||
            !isset($datos['longitud'])) {
            error_log("Error en crearNegocio: Faltan datos obligatorios.");
            return false;
        }

        // Lista de campos permitidos
        $camposPermitidos = [
            'id_categoria', 'id_area_produccion', 'id_localidad', 'nombre_comercial', 
            'lema_publicitario', 'descripcion', 'direccion', 'telefono', 'email', 
            'sitio_web', 'latitud', 'longitud', 'imagen_principal', 'link_video'
        ];

        $columnasSql = ['id_usuario']; // Siempre incluir el usuario creador
        $placeholdersSql = [':id_usuario'];
        $parametros = [':id_usuario' => $idUsuario];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $columnasSql[] = $campo;
                $placeholder = ':' . $campo;
                $placeholdersSql[] = $placeholder;
                $parametros[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo];
            }
        }

        // Añadir activo=1 por defecto
        $columnasSql[] = 'activo';
        $placeholdersSql[] = ':activo';
        $parametros[':activo'] = 1;

        // Construir la consulta SQL
        $sql = sprintf(
            "INSERT INTO negocios (%s) VALUES (%s)",
            implode(', ', $columnasSql),
            implode(', ', $placeholdersSql)
        );

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametros);
            $idNegocio = $this->db->lastInsertId();

            $this->db->commit();
            return (int) $idNegocio;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error en crearNegocio: " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($parametros, true));
            return false;
        }
    }

    /**
     * Actualizar un negocio existente.
     *
     * @param int $id ID del negocio a actualizar.
     * @param array $datos Nuevos datos del negocio.
     * @param int $idUsuario ID del usuario que actualiza (para verificar permisos).
     * @return bool True si la actualización fue exitosa, False en caso contrario.
     */
    public function actualizarNegocio($id, $datos, $idUsuario) {
        $this->db->beginTransaction();

        try {
            // Verificar permisos
            $sqlVerificar = "SELECT id_usuario FROM negocios WHERE id = :id AND activo = 1";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $negocio = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$negocio) {
                throw new Exception("Negocio no encontrado o inactivo.");
            }

            if ($negocio['id_usuario'] != $idUsuario) {
                throw new Exception("No tienes permiso para editar este negocio.");
            }

            // Lista de campos permitidos
            $camposPermitidos = [
                'id_categoria', 'id_area_produccion', 'id_localidad', 'nombre_comercial', 
                'lema_publicitario', 'descripcion', 'direccion', 'telefono', 'email', 
                'sitio_web', 'latitud', 'longitud', 'imagen_principal', 'link_video'
            ];

            $actualizacionesSql = [];
            $parametros = [':id' => $id];

            foreach ($camposPermitidos as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $placeholder = ':' . $campo;
                    $actualizacionesSql[] = "{$campo} = {$placeholder}";
                    $parametros[$placeholder] = ($datos[$campo] === '') ? null : $datos[$campo];
                }
            }

            if (empty($actualizacionesSql)) {
                $this->db->commit();
                return true; // No hay cambios que hacer
            }

            // Construir la consulta de actualización
            $sqlActualizar = sprintf(
                "UPDATE negocios SET %s WHERE id = :id",
                implode(', ', $actualizacionesSql)
            );

            $stmtActualizar = $this->db->prepare($sqlActualizar);
            $stmtActualizar->execute($parametros);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en actualizarNegocio (ID: {$id}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en actualizarNegocio (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un negocio (desactivar - soft delete).
     *
     * @param int $id ID del negocio a eliminar.
     * @param int $idUsuario ID del usuario que elimina (para verificar permisos).
     * @return bool True si la eliminación fue exitosa, False en caso contrario.
     */
    public function eliminarNegocio($id, $idUsuario) {
        $this->db->beginTransaction();

        try {
            // Verificar permisos
            $sqlVerificar = "SELECT id_usuario FROM negocios WHERE id = :id AND activo = 1";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $negocio = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$negocio) {
                throw new Exception("Negocio no encontrado o ya inactivo.");
            }

            if ($negocio['id_usuario'] != $idUsuario) {
                throw new Exception("No tienes permiso para eliminar este negocio.");
            }

            // Eliminar lógicamente (soft delete)
            $sqlEliminar = "UPDATE negocios SET activo = 0 WHERE id = :id";
            $stmtEliminar = $this->db->prepare($sqlEliminar);
            $stmtEliminar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminar->execute();

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en eliminarNegocio (ID: {$id}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en eliminarNegocio (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener negocios creados por un usuario específico.
     *
     * @param int $idUsuario ID del usuario.
     * @return array Lista de negocios del usuario.
     */
    public function obtenerNegociosUsuario($idUsuario) {
        $sql = "
            SELECT
                n.id,
                n.nombre_comercial,
                n.direccion,
                n.latitud,
                n.longitud,
                n.fecha_creacion,
                c.nombre as categoria,
                (SELECT COUNT(*) FROM productos WHERE id_negocio = n.id AND activo = 1) as num_productos,
                (SELECT COUNT(*) FROM ofertas WHERE id_negocio = n.id AND activo = 1 AND fecha_fin >= CURDATE()) as num_ofertas_activas
            FROM
                negocios n
                LEFT JOIN categorias_negocios c ON n.id_categoria = c.id
            WHERE
                n.id_usuario = :id_usuario
                AND n.activo = 1
            ORDER BY
                n.fecha_creacion DESC
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerNegociosUsuario (Usuario ID: {$idUsuario}): " . $e->getMessage());
            return [];
        }
    }
}
?>