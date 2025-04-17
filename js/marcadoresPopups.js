/* ==========================================================================
   marcadoresPopups.js – v2.4 (Corregido - Mínimo)
   Gestión de Interacciones (Temporalmente aquí)
   --------------------------------------------------------------------------
   NOTA: La creación de marcadores/popups, modales y compartir
         se maneja ahora directamente en app.js o módulos asociados.
         Este archivo conserva funciones residuales o será eliminado/refactorizado.

   • Variables Globales Requeridas (por registrarInteraccion):
       * API_BASE (definida en app.js)
       * obtenerUsuarioActual() (función global o definida en app.js)
   ======================================================================== */

/* eslint-env browser */
"use strict";

// ------------------ Interacciones API (Función residual) -----------------
// NOTA: Esta función idealmente pertenecería a app.js o a un módulo API.
// Se mantiene aquí temporalmente si es llamada desde app.js,
// pero ahora usa API_BASE definida globalmente por app.js.

/**
 * Registra una interacción del usuario con una entidad.
 * @param {String} tipoEntidad - 'negocio', 'evento', 'oferta', 'encuesta'.
 * @param {Number|String} idEntidad - ID numérico o string de la entidad.
 * @param {String} tipoInteraccion - 'vista', 'click', 'compartido', 'contacto', etc.
 */
async function registrarInteraccion(tipoEntidad, idEntidad, tipoInteraccion) {
    // Dependencia: obtenerUsuarioActual() debe estar definida globalmente o en app.js
    // Dependencia: API_BASE debe estar definida globalmente en app.js
    if (typeof API_BASE === 'undefined') {
        console.error("registrarInteraccion: La constante global API_BASE no está definida.");
        return;
    }

    const idUsuario = typeof window.obtenerUsuarioActual === 'function' && window.obtenerUsuarioActual()
                      ? window.obtenerUsuarioActual().id : null;

    const apiUrl = `${API_BASE}interacciones.php`; // Construye la URL usando API_BASE

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // Añadir cabeceras de autenticación si son necesarias
                // 'Authorization': 'Bearer ' + tuTokenJWT
            },
            body: JSON.stringify({
                id_usuario: idUsuario,
                tipo_entidad: tipoEntidad,
                id_entidad: idEntidad,
                tipo_interaccion: tipoInteraccion
            })
        });

        if (!response.ok) {
            console.warn(`Registro de interacción fallido (${response.status}): ${tipoEntidad} ${idEntidad} - ${tipoInteraccion}`);
        } else {
            console.log(`Interacción registrada: ${tipoEntidad} ${idEntidad} - ${tipoInteraccion}`);
        }
    } catch (error) {
        console.error(`Error de red al registrar interacción para ${tipoEntidad} ${idEntidad}:`, error);
    }
}

// --- FIN DEL CÓDIGO ÚTIL EN marcadoresPopups.js ---
// El resto de funciones (crearMarcador*, crearPopup*, abrirModalDetalles, compartirEntidad, etc.)
// han sido eliminadas porque app.js usa su propia implementación o
// la funcionalidad pertenece a app.js.