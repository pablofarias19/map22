// utilidades.js - Módulo de utilidades generales

/**
 * Formatea una cadena de fecha/hora para mostrarla al usuario.
 * @param {string} fechaStr Cadena de fecha (ej: 'YYYY-MM-DD HH:MM:SS')
 * @returns {string} Fecha formateada ('DD/MM/YYYY HH:MM') o mensaje de error.
 */
export function formatearFecha(fechaStr) {
    // ...implementación existente...
}

/**
 * Formatea una cadena de fecha/hora para un input type="datetime-local".
 * @param {string} fechaStr Cadena de fecha (ej: 'YYYY-MM-DD HH:MM:SS')
 * @returns {string} Fecha en formato 'YYYY-MM-DDTHH:MM' o ''.
 */
export function formatearFechaParaInput(fechaStr) {
    // ...implementación existente...
}

/**
 * Formatea una cadena de fecha/hora para un input type="date".
 * @param {string} fechaStr Cadena de fecha (ej: 'YYYY-MM-DD HH:MM:SS' o 'YYYY-MM-DD')
 * @returns {string} Fecha en formato 'YYYY-MM-DD' o ''.
 */
export function formatearFechaParaInputDate(fechaStr) {
    // ...implementación existente...
}

/**
 * Formatea un número como precio.
 * @param {number|string} valor
 * @returns {string} Precio formateado (ej: "1.250,50") o "Consultar".
 */
export function formatearPrecio(valor) {
    // ...implementación existente...
}

/**
 * Extrae el ID de un video de YouTube desde varias URL (ÚNICA VERSIÓN).
 * @param {string|null} url
 * @returns {string|null}
 */
export function extraerYouTubeID(url) {
    // ...implementación existente...
}

/**
 * Sanitiza una cadena para prevenir XSS básico insertándola como textContent.
 * @param {string|null|undefined} str Cadena a sanitizar.
 * @returns {string} Cadena sanitizada o string vacío.
 */
export function sanitizeHTML(str) {
    // ...implementación existente...
}

/**
 * Valida la seguridad de una contraseña.
 * @param {string} password
 * @returns {boolean}
 */
export function validarPassword(password) {
    // ...implementación existente...
}