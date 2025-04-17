<?php

use PDO;
use PDOException;
use Exception; // Asegúrate de que Exception está disponible

/**
 * ZonaInfluenciaController.php
 * Controlador para la gestión de zonas de influencia en la aplicación geoespacial.
 */
class ZonaInfluenciaController {
    private $db;

    /**
     * Constructor que recibe la conexión a la base de datos.
     * @param PDO $db Conexión PDO a la base de datos.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Obtener zonas de influencia activas de un negocio.
     *
     * @param int $idNegocio ID del negocio.
     * @return array Zonas de influencia en formato GeoJSON FeatureCollection.
     */
    public function obtenerZonasNegocio($idNegocio) {
        $sql = "
            SELECT
                id,
                id_negocio,
                nombre,
                descripcion,
                -- Obtener la geometría como texto GeoJSON directamente de la BD
                ST_AsGeoJSON(poligono) as geometria_geojson
            FROM
                zonas_influencia
            WHERE
                id_negocio = :id_negocio
                AND activo = 1 -- Solo zonas activas
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id_negocio', $idNegocio, PDO::PARAM_INT);
            $stmt->execute();
            $zonas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear para GeoJSON FeatureCollection
            $features = [];
            foreach ($zonas as $zona) {
                // Decodificar el string GeoJSON de la geometría
                $geometria = isset($zona['geometria_geojson']) ? json_decode($zona['geometria_geojson'], true) : null;

                // Solo añadir si la geometría es válida
                if ($geometria) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $zona['id'],
                            'id_negocio' => $zona['id_negocio'],
                            'nombre' => $zona['nombre'],
                            'descripcion' => $zona['descripcion']
                            // No incluir geometria_geojson en las propiedades
                        ],
                        'geometry' => $geometria
                    ];
                }
            }

            return [
                'type' => 'FeatureCollection',
                'features' => $features
            ];
        } catch (PDOException $e) {
            error_log("Error PDO en obtenerZonasNegocio (Negocio ID: {$idNegocio}): " . $e->getMessage());
            return ['type' => 'FeatureCollection', 'features' => []];
        } catch (Exception $e) { // Captura json_decode errors etc.
            error_log("Error General en obtenerZonasNegocio (Negocio ID: {$idNegocio}): " . $e->getMessage());
            return ['type' => 'FeatureCollection', 'features' => []];
        }
    }

    /**
     * Crear una nueva zona de influencia para un negocio.
     *
     * @param int $idNegocio ID del negocio al que pertenece la zona.
     * @param array $datos Datos de la zona (['geometria' => GeoJSONObject, 'nombre' => ..., 'descripcion' => ...]).
     * @param int $idUsuario ID del usuario que realiza la creación (para verificar permisos).
     * @return int|false ID de la zona creada o false en caso de error.
     */
    public function crearZona($idNegocio, $datos, $idUsuario) {
        // Iniciar transacción al principio
        $this->db->beginTransaction();

        try {
            // --- Verificar Permiso ---
            $sqlVerificar = "SELECT id_usuario FROM negocios WHERE id = :id_negocio AND activo = 1";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id_negocio', $idNegocio, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $negocio = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$negocio || $negocio['id_usuario'] != $idUsuario) {
                throw new Exception("Negocio no encontrado o no tienes permiso para añadir zonas.");
            }
            // --- Fin Permiso ---

            // --- Validar Datos ---
            if (empty($datos['geometria']) || !is_array($datos['geometria'])) { // Asume que la geometría viene como array/objeto PHP
                throw new Exception("La geometría GeoJSON (como array/objeto PHP) es obligatoria.");
            }
            // Podrías añadir validación más estricta del formato GeoJSON aquí si es necesario
            // --- Fin Validación ---


            // --- Preparar Inserción ---
            // Convertir el array/objeto PHP de GeoJSON a string JSON
            $geometriaJsonString = json_encode($datos['geometria']);
            if ($geometriaJsonString === false) {
                 throw new Exception("Error al codificar la geometría a JSON: " . json_last_error_msg());
            }

            $nombre = $datos['nombre'] ?? 'Zona de influencia'; // Valor por defecto
            $descripcion = $datos['descripcion'] ?? '';     // Valor por defecto

            $sqlInsert = "
                INSERT INTO zonas_influencia
                (id_negocio, nombre, descripcion, poligono, activo) -- Asume activo=1 por defecto
                VALUES
                (:id_negocio, :nombre, :descripcion, ST_GeomFromGeoJSON(:geometria, 1, 0), 1) -- Usar SRID 0 si no se especifica
            ";
            $stmtInsert = $this->db->prepare($sqlInsert);

            $stmtInsert->bindParam(':id_negocio', $idNegocio, PDO::PARAM_INT);
            $stmtInsert->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmtInsert->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $stmtInsert->bindParam(':geometria', $geometriaJsonString, PDO::PARAM_STR);
            // --- Fin Preparación ---

            // --- Ejecutar Inserción ---
            $stmtInsert->execute();
            $idZona = $this->db->lastInsertId();

            if (!$idZona) {
                 throw new Exception("No se pudo obtener el ID de la zona creada.");
            }
            // --- Fin Ejecución ---

            $this->db->commit(); // Confirmar transacción
            return (int) $idZona;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en crearZona (Negocio ID: {$idNegocio}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error General en crearZona (Negocio ID: {$idNegocio}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar una zona de influencia existente.
     *
     * @param int $id ID de la zona a actualizar.
     * @param array $datos Nuevos datos (['nombre' => ..., 'descripcion' => ..., 'geometria' => GeoJSONObject]).
     * @param int $idUsuario ID del usuario que realiza la actualización (para verificar permisos).
     * @return bool True si la actualización fue exitosa, False en caso contrario.
     */
    public function actualizarZona($id, $datos, $idUsuario) {
        $this->db->beginTransaction(); // Iniciar transacción

        try {
            // --- Verificar Permiso y Existencia ---
            $sqlVerificar = "
                SELECT z.id, n.id_usuario
                FROM zonas_influencia z
                JOIN negocios n ON z.id_negocio = n.id
                WHERE z.id = :id AND z.activo = 1 -- Solo actualizar zonas activas
            ";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $zona = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$zona) {
                 throw new Exception("Zona no encontrada o inactiva.");
            }
            if ($zona['id_usuario'] != $idUsuario) {
                throw new Exception("No tienes permiso para editar esta zona.");
            }
            // --- Fin Permiso ---

            // --- Preparar Actualizaciones ---
            $actualizacionesSql = [];
            $parametros = [':id' => $id]; // ID siempre necesario para el WHERE

            if (array_key_exists('nombre', $datos)) {
                $actualizacionesSql[] = "nombre = :nombre";
                $parametros[':nombre'] = $datos['nombre'];
            }
            if (array_key_exists('descripcion', $datos)) {
                $actualizacionesSql[] = "descripcion = :descripcion";
                $parametros[':descripcion'] = $datos['descripcion'] ?? ''; // Permitir vaciar descripción
            }
            if (array_key_exists('geometria', $datos)) {
                 if (empty($datos['geometria']) || !is_array($datos['geometria'])) {
                    throw new Exception("La geometría GeoJSON proporcionada no es válida.");
                 }
                 $geometriaJsonString = json_encode($datos['geometria']);
                  if ($geometriaJsonString === false) {
                     throw new Exception("Error al codificar la geometría a JSON: " . json_last_error_msg());
                 }
                $actualizacionesSql[] = "poligono = ST_GeomFromGeoJSON(:geometria, 1, 0)";
                $parametros[':geometria'] = $geometriaJsonString;
            }

            if (empty($actualizacionesSql)) {
                 // No hay error, pero no hay nada que hacer. Commit vacío está bien.
                 error_log("actualizarZona (ID: {$id}): No se proporcionaron datos válidos para actualizar.");
                 $this->db->commit();
                 return true;
            }
            // --- Fin Preparación ---

            // --- Ejecutar Actualización ---
            $sqlActualizar = sprintf(
                "UPDATE zonas_influencia SET %s WHERE id = :id",
                implode(', ', $actualizacionesSql)
            );
            $stmtActualizar = $this->db->prepare($sqlActualizar);

            // Bind de parámetros (usando bindValue para tipos explícitos)
             foreach ($parametros as $key => $value) {
                 if ($key == ':id') {
                     $stmtActualizar->bindValue($key, $value, PDO::PARAM_INT);
                 } elseif ($key == ':geometria') {
                     $stmtActualizar->bindValue($key, $value, PDO::PARAM_STR);
                 } else { // :nombre, :descripcion
                     $stmtActualizar->bindValue($key, $value, PDO::PARAM_STR);
                 }
             }

            $stmtActualizar->execute();
             // --- Fin Ejecución ---

            $this->db->commit(); // Confirmar transacción
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en actualizarZona (ID: {$id}): " . $e->getMessage() . (isset($sqlActualizar) ? " SQL: " . $sqlActualizar : "") . " Params: " . json_encode($parametros ?? []));
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error General en actualizarZona (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar una zona de influencia (marcarla como inactiva - Soft Delete).
     * REQUIERE que la tabla 'zonas_influencia' tenga una columna 'activo' TINYINT(1).
     *
     * @param int $id ID de la zona a eliminar.
     * @param int $idUsuario ID del usuario que realiza la eliminación.
     * @return bool Éxito.
     */
    public function eliminarZona($id, $idUsuario) {
        $this->db->beginTransaction(); // Iniciar transacción

        try {
            // --- Verificar Permiso y Existencia ---
            $sqlVerificar = "
                SELECT z.id, n.id_usuario
                FROM zonas_influencia z
                JOIN negocios n ON z.id_negocio = n.id
                WHERE z.id = :id AND z.activo = 1 -- Solo eliminar zonas activas
            ";
            $stmtVerificar = $this->db->prepare($sqlVerificar);
            $stmtVerificar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $zona = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$zona) {
                throw new Exception("Zona no encontrada o ya inactiva.");
            }
            if ($zona['id_usuario'] != $idUsuario) {
                throw new Exception("No tienes permiso para eliminar esta zona.");
            }
            // --- Fin Permiso ---

            // --- Ejecución de Eliminación Lógica ---
            $sqlEliminar = "UPDATE zonas_influencia SET activo = 0 WHERE id = :id";
            $stmtEliminar = $this->db->prepare($sqlEliminar);
            $stmtEliminar->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtEliminar->execute();
            // --- Fin Ejecución ---

            $this->db->commit(); // Confirmar transacción
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error PDO en eliminarZona (ID: {$id}): " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error General en eliminarZona (ID: {$id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar negocios activos dentro de una zona de influencia activa.
     *
     * @param int $idZona ID de la zona.
     * @return array Lista de negocios encontrados.
     */
    public function buscarNegociosEnZona($idZona) {
        // Usar JOIN explícito para mayor claridad
        $sql = "
            SELECT
                n.id,
                n.nombre_comercial,
                n.direccion,
                n.latitud,
                n.longitud,
                c.nombre as categoria_nombre,
                a.nombre as area_produccion_nombre
            FROM
                zonas_influencia z
            JOIN negocios n ON ST_Contains(z.poligono, ST_Point(n.longitud, n.latitud))
                                -- Asumiendo que negocios.longitud y latitud son las coords
                                -- y que ST_Point espera (longitud, latitud)
            LEFT JOIN categorias_negocios c ON n.id_categoria = c.id
            LEFT JOIN areas_produccion a ON n.id_area_produccion = a.id
            WHERE
                z.id = :id_zona
                AND z.activo = 1 -- Zona activa
                AND n.activo = 1 -- Negocio activo
        ";
        // Nota: ST_Point(long, lat) es común, pero verifica el orden esperado por tu BD/SRID

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id_zona', $idZona, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error PDO en buscarNegociosEnZona (Zona ID: {$idZona}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar zonas activas que contienen un punto específico.
     *
     * @param float $lat Latitud del punto.
     * @param float $lng Longitud del punto.
     * @return array Lista de zonas que contienen el punto.
     */
    public function buscarZonasPorPunto($lat, $lng) {
        $sql = "
            SELECT
                z.id,
                z.id_negocio,
                z.nombre,
                z.descripcion,
                n.nombre_comercial as negocio_nombre,
                n.id_categoria as negocio_id_categoria,
                c.nombre as categoria_nombre
            FROM
                zonas_influencia z
            JOIN negocios n ON z.id_negocio = n.id
            LEFT JOIN categorias_negocios c ON n.id_categoria = c.id
            WHERE
                z.activo = 1 -- Zona activa
                AND n.activo = 1 -- Negocio asociado activo
                AND ST_Contains(z.poligono, ST_Point(:lng, :lat))
                -- Verifica el orden de ST_Point(long, lat)
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Usar bindParam para lat/lng (PDO puede tratarlos como string)
            $stmt->bindParam(':lng', $lng, PDO::PARAM_STR);
            $stmt->bindParam(':lat', $lat, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error PDO en buscarZonasPorPunto (Lat: {$lat}, Lng: {$lng}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar zonas activas que se intersectan con una zona activa específica.
     *
     * @param int $idZona ID de la zona de referencia.
     * @return array Lista de zonas intersectadas (excluyendo la zona de referencia).
     */
    public function buscarZonasIntersectadas($idZona) {
        // Usar JOINs explícitos y alias claros
        $sql = "
            SELECT
                z_intersected.id,
                z_intersected.id_negocio,
                z_intersected.nombre,
                z_intersected.descripcion,
                n.nombre_comercial as negocio_nombre,
                n.id_categoria as negocio_id_categoria,
                c.nombre as categoria_nombre
            FROM
                zonas_influencia z_reference
            JOIN zonas_influencia z_intersected ON ST_Intersects(z_reference.poligono, z_intersected.poligono)
            JOIN negocios n ON z_intersected.id_negocio = n.id
            LEFT JOIN categorias_negocios c ON n.id_categoria = c.id
            WHERE
                z_reference.id = :id_zona
                AND z_reference.activo = 1 -- Zona de referencia activa
                AND z_intersected.id != z_reference.id -- Excluirse a sí misma
                AND z_intersected.activo = 1 -- Zona intersectada activa
                AND n.activo = 1 -- Negocio asociado activo
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id_zona', $idZona, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error PDO en buscarZonasIntersectadas (Zona ID: {$idZona}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcular área de una zona en metros cuadrados.
     * ADVERTENCIA: La precisión depende del SRID de la columna 'poligono' y las capacidades de la BD.
     * Este método asume que ST_Area(geom, TRUE) devuelve área geodésica en m^2 (MySQL 8+, PostGIS).
     * Si no es el caso, este cálculo será incorrecto.
     *
     * @param int $idZona ID de la zona.
     * @return float|null Área en metros cuadrados o null en caso de error o si no se puede calcular.
     */
    public function calcularAreaZona($idZona) {
        // Preferir ST_Area con el argumento TRUE si la BD lo soporta para cálculo geodésico
        // Consultar la documentación de TU base de datos específica.
        $sql = "
            SELECT ST_Area(poligono, TRUE) as area_m2
            FROM zonas_influencia
            WHERE id = :id_zona AND activo = 1
        ";
        // SI TU BD no soporta ST_Area(..., TRUE) o devuelve grados cuadrados:
        // $sql = "SELECT ST_Area(poligono) as area_grados FROM ...";
        // Y tendrías que hacer una conversión MUY APROXIMADA o usar otra técnica.

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id_zona', $idZona, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar si se obtuvo un resultado y si el área es numérica
            if ($resultado && isset($resultado['area_m2']) && is_numeric($resultado['area_m2'])) {
                return (float) $resultado['area_m2'];
            } else {
                 // Si la consulta devuelve grados u otra cosa, manejar aquí
                 error_log("No se pudo calcular el área para Zona ID: {$idZona} o el resultado no fue numérico.");
                 return null;
            }

        } catch (PDOException $e) {
            error_log("Error PDO en calcularAreaZona (Zona ID: {$idZona}): " . $e->getMessage());
            return null;
        }
    }

} // Fin de la clase ZonaInfluenciaController