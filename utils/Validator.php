<?php
/**
 * utils/Validator.php
 * Clase para validación de datos de entrada
 */

class Validator {
    // Errores encontrados durante la validación
    private $errores = [];

    /**
     * Validar un conjunto de datos según reglas especificadas
     * @param array $datos Datos a validar (ej. $_POST, $_GET)
     * @param array $reglas Reglas de validación (ej. ['email' => 'required|email', 'edad' => 'required|integer|min:18'])
     * @param PDO $db Conexión a base de datos (opcional, para reglas que requieren verificación en BD)
     * @return array Errores encontrados (vacío si no hay errores). Las claves son los nombres de los campos.
     */
    public function validar($datos, $reglas, $db = null) {
        $this->errores = []; // Reiniciar errores para cada validación

        foreach ($reglas as $campo => $reglasDelCampo) {
            // Separar reglas individuales (ej. 'required|email' -> ['required', 'email'])
            $reglasIndividuales = explode('|', $reglasDelCampo);

            // Obtener el valor del campo de los datos, o null si no existe
            $valorCampo = $datos[$campo] ?? null;

            // Aplicar cada regla al campo
            foreach ($reglasIndividuales as $regla) {
                $this->aplicarRegla($campo, $valorCampo, $regla, $datos, $db);
            }
        }

        return $this->errores;
    }

    /**
     * Aplicar una regla específica a un campo. Modifica $this->errores si la validación falla.
     * @param string $campo Nombre del campo que se está validando.
     * @param mixed $valor Valor del campo a validar.
     * @param string $regla Regla a aplicar (puede contener parámetros, ej. 'min:5').
     * @param array $datos Todos los datos originales (para reglas que requieren comparación, como 'matches').
     * @param PDO $db Conexión a base de datos (opcional, para reglas que requieren verificación en BD)
     */
    private function aplicarRegla($campo, $valor, $regla, $datos, $db = null) {
        // Separar nombre de la regla y parámetros (si existen)
        $parametros = [];
        if (strpos($regla, ':') !== false) {
            list($nombreRegla, $paramsString) = explode(':', $regla, 2);
            $parametros = explode(',', $paramsString);
        } else {
            $nombreRegla = $regla;
        }

        // Saltar validación si el campo no es 'required' y está vacío/null
        // Excepto para reglas que específicamente deben validar campos vacíos (no es el caso aquí)
        if ($nombreRegla !== 'required' && ($valor === null || $valor === '')) {
            return;
        }


        // Aplicar regla según su nombre
        switch ($nombreRegla) {
            case 'required':
                if ($valor === null || $valor === '' || (is_array($valor) && empty($valor))) { // También verificar arrays vacíos
                    $this->addError($campo, 'El campo es obligatorio.');
                }
                break;

            case 'email':
                // Usa filter_var, que es el método estándar y robusto en PHP
                if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($campo, 'El correo electrónico no tiene un formato válido.');
                }
                break;

            case 'min':
                if (isset($parametros[0])) {
                    $min = (int) $parametros[0];
                    if (is_string($valor) && mb_strlen(trim($valor)) < $min) {
                        $this->addError($campo, "El campo debe tener al menos {$min} caracteres.");
                    } elseif (is_numeric($valor) && (float)$valor < $min) {
                        $this->addError($campo, "El valor debe ser como mínimo {$min}.");
                    } elseif (is_array($valor) && count($valor) < $min) {
                         $this->addError($campo, "Debe seleccionar al menos {$min} elementos.");
                    }
                }
                break;

            case 'max':
                 if (isset($parametros[0])) {
                    $max = (int) $parametros[0];
                    if (is_string($valor) && mb_strlen(trim($valor)) > $max) {
                        $this->addError($campo, "El campo no debe exceder los {$max} caracteres.");
                    } elseif (is_numeric($valor) && (float)$valor > $max) {
                        $this->addError($campo, "El valor debe ser como máximo {$max}.");
                    } elseif (is_array($valor) && count($valor) > $max) {
                         $this->addError($campo, "No debe seleccionar más de {$max} elementos.");
                    }
                }
                break;

            case 'numeric':
                if (!is_numeric($valor)) {
                    $this->addError($campo, 'El campo debe ser un valor numérico.');
                }
                break;

            case 'integer':
                if (!filter_var($valor, FILTER_VALIDATE_INT)) {
                    $this->addError($campo, 'El campo debe ser un número entero.');
                }
                break;

            case 'boolean':
                // filter_var con FILTER_VALIDATE_BOOLEAN maneja '1', 'true', 'on', 'yes', '0', 'false', 'off', 'no', '', null
                 if (!filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
                     // Se podría ser más estricto si solo se quieren 0/1 o true/false
                     // if (!in_array($valor, [true, false, 0, 1, '0', '1'], true)) {
                     //     $this->addError($campo, 'El campo debe ser un valor booleano (true/false, 1/0).');
                     // }
                 }
                 break;


            case 'url':
                if (!filter_var($valor, FILTER_VALIDATE_URL)) {
                    $this->addError($campo, 'La URL no tiene un formato válido.');
                }
                break;

            case 'date':
                // Valida si es una fecha interpretable por strtotime, no un formato específico
                if (strtotime($valor) === false) {
                    $this->addError($campo, 'La fecha proporcionada no es válida.');
                }
                // Para formato específico, usar 'date_format:Y-m-d' (no implementado aquí)
                break;

            case 'matches':
                // Compara el valor de este campo con el valor de otro campo
                 if (isset($parametros[0])) {
                    $otroCampo = $parametros[0];
                    $valorOtroCampo = $datos[$otroCampo] ?? null;
                    if ($valor !== $valorOtroCampo) {
                        $this->addError($campo, "El valor no coincide con el campo {$otroCampo}.");
                    }
                }
                break;

            case 'in':
                // Verifica si el valor está en una lista permitida
                 if (!empty($parametros)) {
                    if (!in_array((string)$valor, $parametros, true)) { // Comparación estricta
                        $valoresPermitidos = implode(', ', $parametros);
                        $this->addError($campo, "El valor seleccionado no es válido. Debe ser uno de: {$valoresPermitidos}.");
                    }
                }
                break;

            case 'not_in':
                 // Verifica si el valor NO está en una lista prohibida
                 if (!empty($parametros)) {
                    if (in_array((string)$valor, $parametros, true)) { // Comparación estricta
                        $valoresProhibidos = implode(', ', $parametros);
                        $this->addError($campo, "El valor seleccionado no está permitido ({$valoresProhibidos}).");
                    }
                }
                break;

            case 'alpha':
                // Solo letras (usando modificador u para UTF-8)
                if (!preg_match('/^\pL+$/u', $valor)) {
                    $this->addError($campo, 'El campo solo debe contener letras.');
                }
                break;

            case 'alpha_num':
                 // Letras y números (usando modificador u para UTF-8)
                if (!preg_match('/^[\pL\pN]+$/u', $valor)) {
                    $this->addError($campo, 'El campo solo debe contener letras y números.');
                }
                break;

            case 'alpha_dash':
                 // Letras, números, guiones bajos y guiones (usando modificador u para UTF-8)
                if (!preg_match('/^[\pL\pN_-]+$/u', $valor)) {
                    $this->addError($campo, 'El campo solo debe contener letras, números, guiones (-) y guiones bajos (_).');
                }
                break;

            case 'regex':
                 // Validar contra una expresión regular personalizada
                 if (isset($parametros[0])) {
                    $pattern = $parametros[0]; // El patrón viene como parámetro
                    // Es importante asegurarse que el patrón recibido sea seguro y esté bien formado
                    // Podría necesitar delimitadores si no vienen en el string
                    if (@preg_match($pattern, $valor) === false) {
                        // Si el patrón es inválido, loguear pero no mostrar al usuario
                         error_log("Patrón regex inválido para el campo {$campo}: " . $pattern);
                         $this->addError($campo, 'El formato del campo no es válido (error interno de patrón).');
                    } elseif (!preg_match($pattern, $valor)) {
                        $this->addError($campo, 'El formato del campo no es válido.');
                    }
                }
                break;

            case 'between':
                // Validar que un número o longitud de string esté entre min y max
                 if (isset($parametros[0]) && isset($parametros[1])) {
                    $min = (float) $parametros[0];
                    $max = (float) $parametros[1];

                    if (is_numeric($valor)) {
                        $valorNumerico = (float) $valor;
                        if ($valorNumerico < $min || $valorNumerico > $max) {
                            $this->addError($campo, "El valor debe estar entre {$min} y {$max}.");
                        }
                    } elseif (is_string($valor)) {
                        $longitud = mb_strlen(trim($valor));
                        if ($longitud < $min || $longitud > $max) {
                            $this->addError($campo, "La longitud debe estar entre {$min} y {$max} caracteres.");
                        }
                    } else {
                         // No es ni número ni string, no se puede aplicar 'between' en este contexto
                         $this->addError($campo, "No se puede aplicar la regla 'between' a este tipo de campo.");
                    }
                }
                break;

            case 'unique':
                // Verifica que el valor no exista ya en la base de datos (para inserciones)
                if ($db && isset($parametros[0])) {
                    $tabla = $parametros[0];
                    $columna = $parametros[1] ?? $campo;
                    $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$columna} = :valor";
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':valor', $valor);
                    $stmt->execute();
                    if ($stmt->fetchColumn() > 0) {
                        $this->addError($campo, 'El valor ya está registrado.');
                    }
                } else if (!$db && isset($parametros[0])) {
                    // Sin conexión a BD, advertir pero no bloquear
                    error_log("Se requiere conexión a base de datos para validar 'unique'");
                }
                break;

            case 'exists':
                // Verifica que el valor exista en la base de datos (para relaciones)
                if ($db && isset($parametros[0])) {
                    $tabla = $parametros[0];
                    $columna = $parametros[1] ?? $campo;
                    $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$columna} = :valor";
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':valor', $valor);
                    $stmt->execute();
                    if ($stmt->fetchColumn() == 0) {
                        $this->addError($campo, 'El valor no existe en la base de datos.');
                    }
                } else if (!$db && isset($parametros[0])) {
                    // Sin conexión a BD, advertir pero no bloquear
                    error_log("Se requiere conexión a base de datos para validar 'exists'");
                }
                break;
        }
    }

    /**
     * Añadir un mensaje de error para un campo específico.
     * @param string $campo Nombre del campo.
     * @param string $mensaje Mensaje de error descriptivo.
     */
    private function addError($campo, $mensaje) {
        // Solo añadir el primer error encontrado para un campo específico bajo una regla,
        // O permitir múltiples si se rediseña para agrupar todos los fallos.
        // Este enfoque actual detiene la validación de MÁS reglas para este campo si una ya falló (implícitamente)
        // pero añade el error. Para permitir múltiples errores, habría que ajustar la lógica.
        // Este modelo simple añade el primer error que encuentra por campo.
        if (!isset($this->errores[$campo])) {
            $this->errores[$campo] = []; // Inicializar array si es el primer error para este campo
        }
        // Evitar duplicados del mismo mensaje para el mismo campo
        if (!in_array($mensaje, $this->errores[$campo])) {
             $this->errores[$campo][] = $mensaje;
        }
    }

    /**
     * Sanitizar un conjunto de datos de entrada según filtros definidos.
     * Aplica un filtro de texto básico si no se especifica uno.
     * @param array $datos Datos a sanitizar (ej. $_POST).
     * @param array $filtros Filtros a aplicar por campo (ej. ['email' => 'email', 'comentario' => 'html']).
     * @return array Datos sanitizados.
     */
    public function sanitizar($datos, $filtros) {
        $datosSanitizados = [];

        // Iterar sobre los datos recibidos
        foreach ($datos as $campo => $valor) {
            // Aplicar filtro específico si existe
            if (isset($filtros[$campo])) {
                $datosSanitizados[$campo] = $this->aplicarFiltro($valor, $filtros[$campo]);
            } else {
                // Si no hay filtro específico, aplicar un filtro de texto básico por defecto
                // (htmlspecialchars + trim) que es bueno para salida HTML.
                $datosSanitizados[$campo] = $this->sanitizarTexto($valor);
            }
        }

        // Importante: Asegurarse de que campos que no estaban en los datos originales
        // pero sí en los filtros (si eso fuera posible) no se añadan aquí.
        // Este bucle itera sobre $datos, por lo que solo sanitiza lo que llegó.

        return $datosSanitizados;
    }

    /**
     * Aplicar un filtro de sanitización específico a un valor.
     * @param mixed $valor Valor original.
     * @param string $filtro Nombre del filtro a aplicar ('string', 'email', 'url', 'int', 'float', 'boolean', 'html', 'strip_tags').
     * @return mixed Valor filtrado/sanitizado.
     */
    private function aplicarFiltro($valor, $filtro) {
         // Si el valor es nulo, devolverlo tal cual
         if ($valor === null) {
             return null;
         }

        switch ($filtro) {
            case 'string': // Igual que el default, sanitizarTexto
                return $this->sanitizarTexto($valor);

            case 'email':
                 // Elimina caracteres no válidos para email
                return filter_var($valor, FILTER_SANITIZE_EMAIL);

            case 'url':
                 // Elimina caracteres no válidos para URL
                return filter_var($valor, FILTER_SANITIZE_URL);

            case 'int':
                // Elimina todo excepto dígitos y signo +/-
                return filter_var($valor, FILTER_SANITIZE_NUMBER_INT);

            case 'float':
                 // Elimina todo excepto dígitos, punto decimal, y signo +/-
                return filter_var($valor, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND); // Permitir separador de miles? O quitarlo. Mejor quitar:
                // return filter_var(str_replace(',', '', $valor), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);


            case 'boolean':
                 // Convierte a booleano (true/false)
                 // Considerar que devuelve false para strings vacíos o '0'.
                return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            case 'html': // Sanitización básica de HTML
                // Permitir algunas etiquetas HTML pero eliminar scripts y atributos peligrosos
                // ¡Cuidado! strip_tags es MUY básico y puede no ser suficiente contra XSS avanzados.
                // Considerar HTML Purifier para una sanitización robusta de HTML.
                 if (!is_string($valor)) return $valor; // Solo aplicar a strings
                 return strip_tags($valor, '<p><br><a><b><i><u><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><img><table><thead><tbody><tr><th><td>');
                // Para los 'a', 'img', se deberían sanitizar los atributos href/src adicionalmente.

            case 'strip_tags': // Eliminar TODAS las etiquetas HTML y PHP
                 if (!is_string($valor)) return $valor;
                return strip_tags($valor);

            default: // Default es sanitizarTexto
                return $this->sanitizarTexto($valor);
        }
    }

    /**
     * Sanitizar texto básico: convierte caracteres especiales a entidades HTML y quita espacios extra.
     * Bueno para prevenir XSS cuando el texto se muestra en HTML.
     * @param mixed $texto Texto a sanitizar.
     * @return mixed Texto sanitizado o el valor original si no es string.
     */
    private function sanitizarTexto($texto) {
        if (!is_string($texto)) {
            return $texto; // Devolver tal cual si no es string
        }

        // Convertir caracteres especiales ('<', '>', '&', '"', etc.) a entidades HTML
        $textoSanitizado = htmlspecialchars($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Eliminar espacios en blanco (u otros caracteres) del inicio y final de un string
        $textoSanitizado = trim($textoSanitizado);

        // Opcional: Podrías añadir otras limpiezas aquí si es necesario
        // ej. convertir saltos de línea múltiples a uno solo, etc.

        return $textoSanitizado;
    }

     /**
      * Obtener los errores de la última validación.
      * @return array Array de errores.
      */
     public function getErrores() {
         return $this->errores;
     }

     /**
      * Comprobar si la última validación tuvo errores.
      * @return bool True si hay errores, false si no.
      */
     public function hayErrores() {
         return !empty($this->errores);
     }
}
?>