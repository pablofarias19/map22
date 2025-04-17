// =============================================================================
// app.js - Versión Corregida y Centralizada (Mayo 2025)
// Orquestador principal del mapa comercial.
// Define funciones globales y maneja la lógica principal.
// =============================================================================

// =============================================================================
// Global Variables & Constants
// =============================================================================

let mapa = null;
let capaZonasInfluencia = null;
let radioActual = 5000;
let posicionActual = null; // User's current LatLng {lat, lng}
let dibujandoZona = false;
let zonaDibujadaTemporal = null;

// Referencias a MarkerClusterGroup
let clusterNegocios = null;
let clusterEventos = null;
let clusterOfertas = null;
let clusterEncuestas = null;

// Referencias a elementos del usuario
let marcadorUsuario = null;
let circuloRadio = null;

// API Base Path (Fuente única y global)
const API_BASE = 'api/';

// Emojis y Colores (Fuente única y global)
// --- ¡¡¡ RELLENA ESTOS OBJETOS CON TUS DATOS REALES !!! ---
const emojiNegocios = {
    'Restaurante': '🍔',
    'Tienda': '🛍️',
    'Servicios': '🛠️',
    'General': '🏪',
    // ... más categorías
};
const emojiEventos = {
    'Concierto': '🎵',
    'Teatro': '🎭',
    'Feria': '🎪',
    'Deportivo': '⚽',
    'General': '📅',
    // ... más categorías
};
const emojiOfertas = '🏷️'; // Simple
const emojiEncuestas = '📋'; // Simple

const coloresEntidades = {
    negocio: {
        'Restaurante': '#FF8C00', // Naranja oscuro
        'Tienda': '#4682B4',     // Azul acero
        'Servicios': '#2E8B57',   // Verde mar
        'General': '#007BFF',     // Azul primario
        // ... más categorías
    },
    evento: {
        'Concierto': '#DC143C',   // Carmesí
        'Teatro': '#8A2BE2',     // Azul violeta
        'Feria': '#FFD700',     // Oro
        'Deportivo': '#32CD32',   // Verde lima
        'General': '#FF69B4',     // Rosa fuerte
         // ... más categorías
    },
    oferta: '#FFD700', // Oro (Consistente con la variable emojiOfertas simple)
    encuesta: '#9400D3' // Violeta oscuro (Consistente con la variable emojiEncuestas simple)
};
// --- FIN DE DATOS PARA RELLENAR ---


// =============================================================================
// Utility Functions (Definidas Globalmente)
// =============================================================================

/**
 * Formatea una cadena de fecha/hora para mostrarla al usuario.
 * @param {string} fechaStr Cadena de fecha (ej: 'YYYY-MM-DD HH:MM:SS')
 * @returns {string} Fecha formateada ('DD/MM/YYYY HH:MM') o mensaje de error.
 */
function formatearFecha(fechaStr) {
    if (!fechaStr) return 'Fecha no disponible';
    try {
        // Intenta manejar diferentes formatos iniciales, incluyendo 'T' o espacio
        const fecha = new Date(fechaStr.replace(' ', 'T'));
        if (isNaN(fecha.getTime())) {
            // Segundo intento si falla el primero (puede que no tenga hora)
            const fechaAlt = new Date(fechaStr);
            if (isNaN(fechaAlt.getTime())) {
                 console.warn("Formato de fecha inválido recibido:", fechaStr);
                 return 'Fecha inválida';
            }
            // Si solo es fecha, muestra solo eso
            if (fechaAlt.getHours() === 0 && fechaAlt.getMinutes() === 0 && fechaAlt.getSeconds() === 0) {
                 return fechaAlt.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }
            // Si tiene hora, mostrarla
            return fechaAlt.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
        }
        // Si la primera conversión fue válida, usarla
        return fecha.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
    } catch (e) {
        console.error("Error formateando fecha:", fechaStr, e);
        return 'Error de fecha';
    }
}

/**
 * Formatea una cadena de fecha/hora para un input type="datetime-local".
 * @param {string} fechaStr Cadena de fecha (ej: 'YYYY-MM-DD HH:MM:SS')
 * @returns {string} Fecha en formato 'YYYY-MM-DDTHH:MM' o ''.
 */
function formatearFechaParaInput(fechaStr) {
    if (!fechaStr) return '';
    try {
        const fecha = new Date(fechaStr.replace(' ', 'T'));
        if (isNaN(fecha.getTime())) return '';
        const pad = (num) => num.toString().padStart(2, '0');
        const year = fecha.getFullYear();
        const month = pad(fecha.getMonth() + 1);
        const day = pad(fecha.getDate());
        const hours = pad(fecha.getHours());
        const minutes = pad(fecha.getMinutes());
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    } catch (e) {
        console.error("Error formateando fecha para input datetime-local:", fechaStr, e);
        return '';
    }
}

/**
 * Formatea una cadena de fecha/hora para un input type="date".
 * @param {string} fechaStr Cadena de fecha (ej: 'YYYY-MM-DD HH:MM:SS' o 'YYYY-MM-DD')
 * @returns {string} Fecha en formato 'YYYY-MM-DD' o ''.
 */
function formatearFechaParaInputDate(fechaStr) {
     if (!fechaStr) return '';
    try {
        // Toma la parte antes del espacio o 'T' si existe
        const datePart = fechaStr.split(/[ T]/)[0];
        const fecha = new Date(datePart + 'T00:00:00'); // Asegura parseo como fecha local
        if (isNaN(fecha.getTime())) return '';
        const pad = (num) => num.toString().padStart(2, '0');
        const year = fecha.getFullYear();
        const month = pad(fecha.getMonth() + 1);
        const day = pad(fecha.getDate());
        return `${year}-${month}-${day}`;
    } catch (e) {
        console.error("Error formateando fecha para input date:", fechaStr, e);
        return '';
    }
}

/**
 * Formatea un número como precio.
 * @param {number|string} valor
 * @returns {string} Precio formateado (ej: "1.250,50") o "Consultar".
 */
function formatearPrecio(valor) {
  const numero = Number(valor);
  if (isNaN(numero)) return 'Consultar';
  try {
      return numero.toLocaleString('es-AR', { // Formato argentino
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
       });
  } catch (e) {
       console.error("Error formateando precio:", e);
       return numero.toFixed(2); // Fallback simple
  }
}


/**
 * Valida la seguridad de una contraseña.
 * @param {string} password
 * @returns {boolean}
 */
function validarPassword(password) {
    const regex = /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.,#^<>()+=\-_\\|/{}[\]~`])[A-Za-z\d@$!%*?&.,#^<>()+=\-_\\|/{}[\]~`]{8,}$/;
    return regex.test(password);
}

/**
 * Extrae el ID de un video de YouTube desde varias URL (ÚNICA VERSIÓN).
 * @param {string|null} url
 * @returns {string|null}
 */
function extraerYouTubeID(url) {
    if (!url) return null;
    let videoId = null;
    try { // Intenta con el parser de URL primero (más robusto)
        const urlObj = new URL(url);
        if (urlObj.hostname === 'youtu.be') {
            videoId = urlObj.pathname.slice(1).split('/')[0]; // Tomar solo el ID
        } else if (urlObj.hostname.includes('youtube.com')) {
            videoId = urlObj.searchParams.get('v');
            if (!videoId && urlObj.pathname.includes('/embed/')) {
                videoId = urlObj.pathname.split('/embed/')[1].split('/')[0];
            } else if (!videoId && urlObj.pathname.includes('/live/')) {
                 videoId = urlObj.pathname.split('/live/')[1].split('?')[0];
            }
        }
        // Validación básica del ID extraído
        if (videoId && /^[a-zA-Z0-9_-]{11}$/.test(videoId)) return videoId;
    } catch (e) {
        // No hacer nada si falla el new URL, probar regex
        console.warn("URL parsing failed for YouTube link, using regex fallback:", url);
    }
    // Regex fallback (cubre algunos casos más, pero menos preciso)
    const regExp = /^.*(?:(?:youtu\.be\/|v\/|vi\/|u\/\w\/|embed\/|live\/|shorts\/)|(?:(?:watch)?\?v(?:i)?=|\&v(?:i)?=))([^#&?]{11}).*/;
    const match = url.match(regExp);
    if (match && match[1] && /^[a-zA-Z0-9_-]{11}$/.test(match[1])) {
        videoId = match[1];
    }
    return videoId || null;
}


/**
 * Sanitiza una cadena para prevenir XSS básico insertándola como textContent.
 * @param {string|null|undefined} str Cadena a sanitizar.
 * @returns {string} Cadena sanitizada o string vacío.
 */
function sanitizeHTML(str) {
    if (str === null || typeof str === 'undefined') return "";
    const temp = document.createElement('div');
    temp.textContent = String(str); // Convierte a string y asigna como texto
    return temp.innerHTML; // Devuelve la representación HTML segura
}

/**
 * Obtiene los datos del usuario actual (ejemplo básico).
 * ¡NECESITA IMPLEMENTACIÓN REAL BASADA EN TU SISTEMA DE AUTH!
 * @returns {object|null} Objeto con {id: userId} o null.
 */
function obtenerUsuarioActual() {
    const token = localStorage.getItem('token_mapa_comercial');
    if (token) {
        // Aquí iría la lógica para decodificar el token (si es JWT)
        // o simplemente devolver un ID si el token existe.
        // ¡¡¡ESTO ES SOLO UN EJEMPLO, NO VERIFICA VALIDEZ!!!
        try {
            // Ejemplo MUY básico si el token fuera solo el ID (¡NO SEGURO!)
            // const userId = parseInt(token);
            // if (!isNaN(userId)) return { id: userId };

            // Ejemplo si fuera JWT (requiere biblioteca como jwt-decode)
            // import { jwtDecode } from "jwt-decode"; // Necesitarías instalarla
            // const decoded = jwtDecode(token);
            // return { id: decoded.sub || decoded.id }; // Ajusta según tu payload

            // Placeholder:
             return { id: 'user123' };
        } catch (e) {
            console.error("Error procesando token:", e);
            // Token inválido o corrupto, tratar como no logueado
            localStorage.removeItem('token_mapa_comercial'); // Limpiar token inválido
            return null;
        }
    }
    return null;
}

/**
 * Obtiene la cabecera de autorización (ejemplo).
 * ¡NECESITA IMPLEMENTACIÓN REAL O PROVENIR DE auth.js!
 * @param {boolean} includeContentType - Si se debe incluir 'Content-Type: application/json'. Default true.
 * @returns {object} Objeto de cabeceras.
 */
function getAuthHeader(includeContentType = true) {
     const token = localStorage.getItem('token_mapa_comercial');
     const headers = {};
     if (includeContentType) {
         headers['Content-Type'] = 'application/json';
     }
     if (token) {
         headers['Authorization'] = `Bearer ${token}`;
     }
     return headers;
 }


// =============================================================================
// Modal Functions (Sistema Único Global)
// =============================================================================

/**
 * Crea y añade al DOM la estructura básica de un modal.
 * @param {string} id ID único para el modal.
 * @param {string} titulo Título del modal (se sanitizará).
 * @param {string} contenido HTML interno para el cuerpo del modal (¡debe ser seguro!).
 */
function crearModal(id, titulo, contenido) {
    // Elimina modal existente con el mismo ID para evitar duplicados
    const modalExistente = document.getElementById(id);
    if (modalExistente) {
        modalExistente.remove();
    }

    const modal = document.createElement('div');
    modal.id = id;
    modal.className = 'modal'; // Clase principal para estilos y ocultar/mostrar
    modal.setAttribute('aria-hidden', 'true'); // Oculto inicialmente para accesibilidad

    // Estructura interna
    modal.innerHTML = `
        <div class="modal-dialogo" role="dialog" aria-modal="true" aria-labelledby="modal-titulo-${id}">
            <div class="modal-contenido">
                <div class="modal-cabecera">
                    <h3 class="modal-titulo" id="modal-titulo-${id}">${sanitizeHTML(titulo)}</h3>
                    <button class="boton-cerrar-modal" aria-label="Cerrar">×</button>
                </div>
                <div class="modal-cuerpo">
                    ${contenido} {/* El contenido ya debe venir sanitizado o ser HTML seguro */}
                </div>
                {/* Opcional: Pie de modal genérico si siempre quieres botones Cancelar/OK
                <div class="modal-pie">
                    <button type="button" class="boton-secundario boton-cancelar-modal">Cancelar</button>
                </div>
                */}
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Listeners básicos para cerrar
    const btnCerrar = modal.querySelector('.boton-cerrar-modal');
    const dialogo = modal.querySelector('.modal-dialogo');

    btnCerrar?.addEventListener('click', () => cerrarModal(id));

    // Cerrar al hacer clic en el fondo (fuera del dialogo)
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            cerrarModal(id);
        }
    });

    // Añadir listener para botones de cancelar DENTRO del contenido si existen
     modal.querySelectorAll('.boton-cancelar-modal').forEach(btn => {
        // Asegurarse de no añadir listeners duplicados si se re-renderiza el contenido
        if (!btn.dataset.listenerAttached) {
             btn.addEventListener('click', () => cerrarModal(id));
             btn.dataset.listenerAttached = 'true';
        }
    });

    // Listener para tecla Escape (se añade al mostrar)
    modal._escapeListener = (e) => {
        if (e.key === 'Escape') {
            cerrarModal(id);
        }
    };
}

/**
 * Muestra un modal previamente creado por su ID.
 * @param {string} id ID del modal a mostrar.
 */
function mostrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex'; // Hacer visible
        document.body.classList.add('modal-abierto'); // Bloquear scroll del body
        modal.setAttribute('aria-hidden', 'false');
        document.addEventListener('keydown', modal._escapeListener); // Activar listener Escape

        // Pequeño delay para permitir transición CSS 'mostrar'
        setTimeout(() => {
            modal.classList.add('mostrar');
            // Enfocar el primer elemento enfocable para accesibilidad
            const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            focusable?.focus();
        }, 10);
    } else {
         console.error(`Modal con ID "${id}" no encontrado para mostrar.`);
    }
}

/**
 * Cierra un modal por su ID.
 * @param {string} id ID del modal a cerrar.
 */
function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('mostrar'); // Inicia transición de salida
        document.body.classList.remove('modal-abierto');
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', modal._escapeListener); // Desactivar listener Escape

        // Eliminar del DOM después de la transición CSS (aprox 300ms)
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}


// =============================================================================
// Notification Functions (Sistema Único Global)
// =============================================================================

/** Inicializa el contenedor de notificaciones si no existe. */
function inicializarNotificaciones() {
    if (!document.getElementById('contenedor_notificaciones')) {
        const contenedor = document.createElement('div');
        contenedor.id = 'contenedor_notificaciones';
        contenedor.setAttribute('aria-live', 'assertive');
        document.body.appendChild(contenedor);
    }
}

/**
 * Muestra un mensaje de notificación temporal.
 * @param {string} titulo Título de la notificación.
 * @param {string} mensaje Mensaje (se sanitizará).
 * @param {'info'|'exito'|'aviso'|'error'} [tipo='info'] Tipo de notificación.
 * @param {number} [duracion=5000] Duración en ms (0 para manual).
 */
function mostrarNotificacion(titulo, mensaje, tipo = 'info', duracion = 5000) {
    const contenedor = document.getElementById('contenedor_notificaciones');
    if (!contenedor) {
        console.error("Contenedor de notificaciones no inicializado.");
        return;
    }
    const id = 'notif_' + Date.now();

    const notificacion = document.createElement('div');
    notificacion.id = id;
    notificacion.className = `notificacion notificacion-${tipo}`;
    notificacion.setAttribute('role', tipo === 'error' ? 'alert' : 'status'); // 'alert' para errores

    // Usar sanitizeHTML para título y mensaje
    notificacion.innerHTML = `
        <div class="notificacion-cabecera">
            <h4>${sanitizeHTML(titulo)}</h4>
            <button class="cerrar-notificacion" aria-label="Cerrar notificación">×</button>
        </div>
        <div class="notificacion-contenido">
            <p>${sanitizeHTML(mensaje)}</p>
        </div>
    `;
    // Insertar al principio para que las nuevas aparezcan arriba
    contenedor.prepend(notificacion);

    // Hacer visible con transición
    setTimeout(() => notificacion.classList.add('visible'), 10);

    let autoCloseTimeout = null;
    if (duracion > 0) {
         autoCloseTimeout = setTimeout(() => cerrarNotificacion(id), duracion);
    }

    // Botón de cierre manual
    notificacion.querySelector('.cerrar-notificacion')?.addEventListener('click', () => {
        if (autoCloseTimeout) clearTimeout(autoCloseTimeout);
        cerrarNotificacion(id);
    });
}

/** Cierra una notificación específica por su ID. */
function cerrarNotificacion(id) {
    const notificacion = document.getElementById(id);
    if (notificacion) {
        notificacion.classList.remove('visible');
        // Eliminar del DOM después de la transición
        setTimeout(() => notificacion.remove(), 300);
    }
}


// =============================================================================
// Map Initialization & Configuration
// =============================================================================

function inicializarMapa() {
  try {
    console.log("Inicializando el mapa...");
    console.log("Configuración inicial del mapa:", {
        center: [-34.60, -58.38],
        zoom: 13,
        minZoom: 3,
        maxZoom: 19
    });

    mapa = L.map('mapa_comercial', {
        center: [-34.60, -58.38],
        zoom: 13,
        minZoom: 3,
        maxZoom: 19,
        zoomControl: false,
        attributionControl: false
    });

    console.log("Mapa inicializado correctamente.");

    L.control.zoom({ position: 'bottomright' }).addTo(mapa);
    console.log("Controles de zoom añadidos.");

    L.control.attribution({
        position: 'bottomleft',
        prefix: '© <a href="https://www.openstreetmap.org/copyright" target="_blank">OSM</a> | <a href="/terminos" target="_blank" class="enlace_terminos">Términos</a>'
    }).addTo(mapa);
    console.log("Control de atribución añadido.");

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mapa);
    console.log("Capa de mapa base añadida.");

    capaZonasInfluencia = L.featureGroup();

    // Configurar Leaflet.pm si está disponible
    if (mapa.pm) {
         mapa.pm.setLang('es');
         mapa.pm.addControls({ position: 'topright', drawMarker: false, drawCircleMarker: false, drawPolyline: false, drawRectangle: false, drawCircle: false, drawText: false, drawPolygon: true, editMode: true, dragMode: false, cutPolygon: true, removalMode: true, rotateMode: false });
         ocultarControlesEdicion();
         mapa.on('pm:create', handlePmCreate);
    } else {
         console.warn("Leaflet.pm no está cargado.");
    }

    configurarClusters();
    añadirControlesPersonalizados();
    añadirGeolocalización();
    // cargarDatosCercanos(mapa.getCenter(), radioActual); // Carga inicial opcional
  } catch (error) {
    console.error("Error al inicializar el mapa:", error);
    mostrarNotificacion('Error', 'No se pudo inicializar el mapa.', 'error');
  }
}

function handlePmCreate(e) {
     if (dibujandoZona && e.shape === 'Polygon' && e.layer) {
        zonaDibujadaTemporal = e.layer;
        mapa.pm.disableDraw('Polygon');
        dibujandoZona = false;
        abrirModalGuardarZona(zonaDibujadaTemporal); // Necesita implementación
     }
}

// Placeholder para la función que abre el modal de guardar zona
function abrirModalGuardarZona(layer) {
    console.log("Zona dibujada, lista para guardar:", layer.toGeoJSON());
    mostrarNotificacion("Zona Dibujada", "Funcionalidad de guardar zona no implementada.", "aviso");
    // Aquí iría la lógica para crear un modal con un input para el nombre
    // y botones para guardar (enviando a API) o descartar (eliminando layer)
    // Ejemplo: crearModal('guardar_zona_modal', 'Guardar Zona', ...)
    // Al guardar, llamar a API: fetch(`${API_BASE}zonas_influencia.php`, {method: 'POST', ... body: {nombre: '...', geojson: layer.toGeoJSON().geometry}})
    // Al descartar: state.mapa.removeLayer(layer);
}

function ocultarControlesEdicion() { /* ... código sin cambios ... */ }
function mostrarControlesEdicion() { /* ... código sin cambios ... */ }
function configurarClusters() { /* ... código sin cambios ... */ }
function createClusterIcon(tipo) { /* ... código sin cambios ... */ }

// =============================================================================
// Map Controls & UI Interaction
// =============================================================================

function añadirControlesPersonalizados() {
    // Control Radio (sin cambios en HTML)
    const controlRadio = L.control({ position: 'topleft' });
    controlRadio.onAdd = function(map) {
        const div = document.createElement('div'); // Definir el elemento div
        div.className = 'control-radio'; // Asignar una clase para estilos
        div.innerHTML = '<label><input type="radio" name="radio" value="opcion1"> Opción 1</label>';
        return div;
    };
    controlRadio.addTo(mapa);

    // Control Filtros (sin cambios en HTML)
    const controlFiltros = L.control({ position: 'topleft' });
    controlFiltros.onAdd = function(map) {
        const div = document.createElement('div'); // Definir el elemento div
        div.className = 'control-filtros'; // Asignar una clase para estilos
        div.innerHTML = '<button>Filtrar</button>';
        return div;
    };
    controlFiltros.addTo(mapa);

    // Control Estadísticas (sin cambios en HTML)
    const controlEstadisticas = L.control({ position: 'topleft' });
    controlEstadisticas.onAdd = function(map) {
        const div = document.createElement('div'); // Definir el elemento div
        div.className = 'control-estadisticas'; // Asignar una clase para estilos
        div.innerHTML = '<span>Estadísticas</span>';
        return div;
    };
    controlEstadisticas.addTo(mapa);

    // Control Usuario (sin cambios en HTML)
    const controlUsuario = L.control({ position: 'topright' });
    controlUsuario.onAdd = function(map) {
        const div = document.createElement('div'); // Definir el elemento div
        div.className = 'control-usuario'; // Asignar una clase para estilos
        div.innerHTML = '<button>Usuario</button>';
        return div;
    };
    controlUsuario.addTo(mapa);
}

function setupControlListeners() { /* ... código sin cambios ... */ }
function togglePanel(idPanel) { /* ... código sin cambios ... */ }

// =============================================================================
// Geolocation
// =============================================================================

function añadirGeolocalización() { /* ... código sin cambios ... */ }
function onLocationFound(e) { /* ... código sin cambios ... */ }
function onLocationError(e) { /* ... código sin cambios ... */ }
function actualizarRadioBusqueda() { /* ... código sin cambios ... */ }

// =============================================================================
// Data Loading & Marker Management (Usando la lógica de app.js)
// =============================================================================

/**
 * Helper para generar contenido de popup (Versión de app.js).
 */
function generarContenidoPopup(entidad) {
    // ... (código sin cambios, usa sanitizeHTML) ...
     let contenido = `<h4>${sanitizeHTML(entidad.nombre || entidad.titulo || 'Detalles')}</h4>`;
    contenido += `<p>${sanitizeHTML(entidad.categoria_nombre || entidad.tipo || '')}</p>`;
    if (entidad.descripcion_corta) { contenido += `<p>${sanitizeHTML(entidad.descripcion_corta)}</p>`; }
    contenido += `<div class="botones-popup">
                    <button class="boton-popup boton-detalles" data-tipo="${sanitizeHTML(entidad.tipo)}" data-id="${sanitizeHTML(entidad.id)}">Ver Detalles</button>
                    <button class="boton-popup boton-compartir" data-tipo="${sanitizeHTML(entidad.tipo)}" data-id="${sanitizeHTML(entidad.id)}" data-nombre="${encodeURIComponent(sanitizeHTML(entidad.nombre || entidad.titulo || ''))}">Compartir</button>`; // Sanitize nombre también
    if (entidad.tipo === 'encuesta') {
         contenido += `<button class="boton-popup boton-responder" data-tipo="encuesta" data-id="${sanitizeHTML(entidad.id)}">Participar</button>`; // Añadir data-tipo
         if (entidad.resultados_publicos || entidad.puede_ver_resultados) {
             contenido += `<button class="boton-popup boton-resultados" data-tipo="encuesta" data-id="${sanitizeHTML(entidad.id)}">Resultados</button>`; // Añadir data-tipo
         }
    }
    contenido += `</div>`;
    return contenido;
}

async function cargarDatosCercanos(latlng, radio) { /* ... código sin cambios (fetch, Promise.all) ... */ }

// --- Specific entity loading functions ---
// Añadido registro de interacción onclick
function cargarNegocios(data) {
    if (!clusterNegocios || !data?.features?.length) { console.log('No hay negocios para cargar.'); return; }
    const marcadores = [];
    data.features.forEach(feature => {
        try {
             const latLng = L.latLng(feature.geometry.coordinates[1], feature.geometry.coordinates[0]);
             const props = feature.properties;
             const categoria = props.categoria_nombre || 'General';
             const emoji = emojiNegocios[categoria] || '🏪';
             const color = coloresEntidades.negocio[categoria] || '#007bff';
             const marcador = L.marker(latLng, {
                 icon: L.divIcon({ className: 'marcador-entidad', html: `<div class="pin" style="background-color:${color};"><span class="emoji">${emoji}</span></div><div class="pulso" style="border-color:${color};"></div>`, iconSize: [30, 42], iconAnchor: [15, 42], popupAnchor: [0, -35] })
             });
             marcador.entidad = { ...props, tipo: 'negocio' };
             marcador.bindPopup(generarContenidoPopup(marcador.entidad), { minWidth: 250 });
             marcador.on('click', () => registrarInteraccion('negocio', props.id, 'click'));
             marcadores.push(marcador);
        } catch (e) { console.error("Error creando marcador negocio:", e, feature); }
    });
    clusterNegocios.addLayers(marcadores);
    console.log(`Cargados ${marcadores.length} negocios.`);
}

function cargarEventos(data) {
    if (!clusterEventos || !data?.features?.length) { console.log('No hay eventos para cargar.'); return; }
    const marcadores = [];
    data.features.forEach(feature => {
         try {
            const latLng = L.latLng(feature.geometry.coordinates[1], feature.geometry.coordinates[0]);
            const props = feature.properties;
            const categoria = props.categoria_nombre || 'General';
            const emoji = emojiEventos[categoria] || '📅';
            const color = coloresEntidades.evento[categoria] || '#FF3366';
            const marcador = L.marker(latLng, {
                icon: L.divIcon({ className: 'marcador-entidad', html: `<div class="pin" style="background-color:${color};"><span class="emoji">${emoji}</span></div><div class="pulso" style="border-color:${color};"></div>`, iconSize: [30, 42], iconAnchor: [15, 42], popupAnchor: [0, -35] })
            });
            marcador.entidad = { ...props, tipo: 'evento' };
            marcador.bindPopup(generarContenidoPopup(marcador.entidad), { minWidth: 250 });
            marcador.on('click', () => registrarInteraccion('evento', props.id, 'click'));
            marcadores.push(marcador);
        } catch (e) { console.error("Error creando marcador evento:", e, feature); }
    });
    clusterEventos.addLayers(marcadores);
    console.log(`Cargados ${marcadores.length} eventos.`);
}

function cargarOfertas(data) {
    if (!clusterOfertas || !data?.features?.length) { console.log('No hay ofertas para cargar.'); return; }
    const marcadores = [];
    data.features.forEach(feature => {
         try {
            const latLng = L.latLng(feature.geometry.coordinates[1], feature.geometry.coordinates[0]);
            const props = feature.properties;
            const emoji = emojiOfertas;
            const color = coloresEntidades.oferta || '#FFD700';
            const marcador = L.marker(latLng, {
                icon: L.divIcon({ className: 'marcador-entidad', html: `<div class="pin" style="background-color:${color};"><span class="emoji">${emoji}</span></div><div class="pulso" style="border-color:${color};"></div>`, iconSize: [30, 42], iconAnchor: [15, 42], popupAnchor: [0, -35] })
            });
            marcador.entidad = { ...props, tipo: 'oferta' };
            marcador.bindPopup(generarContenidoPopup(marcador.entidad), { minWidth: 250 });
            marcador.on('click', () => registrarInteraccion('oferta', props.id, 'click'));
            marcadores.push(marcador);
        } catch (e) { console.error("Error creando marcador oferta:", e, feature); }
    });
    clusterOfertas.addLayers(marcadores);
    console.log(`Cargadas ${marcadores.length} ofertas.`);
}

function cargarEncuestas(data) {
    if (!clusterEncuestas || !data?.features?.length) { console.log('No hay encuestas para cargar.'); return; }
    const marcadores = [];
    data.features.forEach(feature => {
         try {
            const latLng = L.latLng(feature.geometry.coordinates[1], feature.geometry.coordinates[0]);
            const props = feature.properties;
            const emoji = emojiEncuestas;
            const color = coloresEntidades.encuesta || '#9400D3';
            const marcador = L.marker(latLng, {
                icon: L.divIcon({ className: 'marcador-entidad', html: `<div class="pin" style="background-color:${color};"><span class="emoji">${emoji}</span></div><div class="pulso" style="border-color:${color};"></div>`, iconSize: [30, 42], iconAnchor: [15, 42], popupAnchor: [0, -35] })
            });
            marcador.entidad = { ...props, tipo: 'encuesta' };
            marcador.entidad.puede_ver_resultados = props.puede_ver_resultados;
            marcador.bindPopup(generarContenidoPopup(marcador.entidad), { minWidth: 250 });
            marcador.on('click', () => registrarInteraccion('encuesta', props.id, 'click'));
            marcadores.push(marcador);
        } catch (e) { console.error("Error creando marcador encuesta:", e, feature); }
    });
    clusterEncuestas.addLayers(marcadores);
    console.log(`Cargadas ${marcadores.length} encuestas.`);
}

// =============================================================================
// Event Delegation (Popups & Modals)
// =============================================================================

/** Configura listener delegado para botones DENTRO de los popups del mapa. */
function setupPopupButtons() {
    const mapContainer = document.getElementById('mapa_comercial');
    if (!mapContainer || mapContainer.dataset.popupListenerAttached === 'true') return;

    mapContainer.addEventListener('click', (e) => {
        const botonPopup = e.target.closest('.leaflet-popup-content .boton-popup');
        if (botonPopup) {
            e.stopPropagation(); // Evita que el clic cierre el popup o afecte al mapa
            const tipo = botonPopup.dataset.tipo;
            const id = botonPopup.dataset.id;
            const nombre = decodeURIComponent(botonPopup.dataset.nombre || ''); // Para compartir

            if (!tipo || !id) {
                console.warn("Botón popup sin data-tipo o data-id:", botonPopup);
                return;
            }

            // Identificar acción por clase
            if (botonPopup.classList.contains('boton-detalles')) {
                mostrarDetallesEntidad(tipo, id);
                mapa?.closePopup(); // Cerrar popup al ver detalles
            } else if (botonPopup.classList.contains('boton-compartir')) {
                compartirEntidad(tipo, id, nombre); // Usa la de app.js
            } else if (botonPopup.classList.contains('boton-responder')) {
                mostrarFormularioRespuestaEncuesta(id);
                mapa?.closePopup();
            } else if (botonPopup.classList.contains('boton-resultados')) {
                mostrarResultadosEncuesta(id);
                mapa?.closePopup();
            }
        }
    });
    mapContainer.dataset.popupListenerAttached = 'true';
    console.log("Delegación para botones de popups configurada.");
}

/** Configura listener delegado para botones DENTRO de los modales de detalles. */
function setupModalActionButtons() {
     // Escucha en el body, pero filtra por clics dentro de un modal de detalles
     document.body.addEventListener('click', (e) => {
         const botonAccion = e.target.closest('.modal-detalles-entidad .boton-detalles-accion');
         if (botonAccion) {
             // No necesita stopPropagation aquí normalmente

             const modalContenido = botonAccion.closest('.modal-contenido'); // Para obtener tipo/id del modal actual
             const modalId = modalContenido?.closest('.modal')?.id;

             // Acciones comunes basadas en clase
             if (botonAccion.classList.contains('boton-accion-como-llegar')) {
                 const lat = botonAccion.dataset.lat;
                 const lng = botonAccion.dataset.lng;
                 const nombre = botonAccion.dataset.nombre || 'Destino';
                 if (lat && lng) {
                     mostrarComoLlegar(lat, lng, nombre);
                     if (modalId) cerrarModal(modalId); // Cierra el modal después de la acción
                 } else { console.warn("Botón 'Cómo Llegar' sin lat/lng."); }
             } else if (botonAccion.classList.contains('boton-accion-contactar')) {
                  const tipo = botonAccion.dataset.tipo || modalContenido?.dataset.tipo;
                  const id = botonAccion.dataset.id || modalContenido?.dataset.id;
                  if (tipo && id) {
                       abrirModalContacto(tipo, id); // Asume que esta función existe
                  } else { console.warn("Botón 'Contactar' sin tipo/id."); }
             } else if (botonAccion.classList.contains('boton-accion-ver-negocio')) {
                  const id = botonAccion.dataset.id;
                  if (id) {
                       if (modalId) cerrarModal(modalId);
                       // Pequeño delay para asegurar cierre antes de abrir
                       setTimeout(() => mostrarDetallesEntidad('negocio', id), 50);
                  } else { console.warn("Botón 'Ver Negocio' sin id."); }
             } else if (botonAccion.classList.contains('boton-accion-responder')) {
                  const id = botonAccion.dataset.id;
                  if (id) {
                       if (modalId) cerrarModal(modalId);
                       setTimeout(() => mostrarFormularioRespuestaEncuesta(id), 50);
                  } else { console.warn("Botón 'Responder Encuesta' sin id."); }
              } else if (botonAccion.classList.contains('boton-accion-compartir')) {
                    const tipo = botonAccion.dataset.tipo || modalContenido?.dataset.tipo;
                    const id = botonAccion.dataset.id || modalContenido?.dataset.id;
                    const nombre = decodeURIComponent(botonAccion.dataset.nombre || modalContenido?.querySelector('.detalles-titulo')?.textContent || '');
                    if(tipo && id) {
                        compartirEntidad(tipo, id, nombre);
                    } else { console.warn("Botón 'Compartir Modal' sin tipo/id."); }
              }
             // Añadir más acciones si es necesario (ej: .boton-accion-propiedad)
             else if (botonAccion.classList.contains('boton-accion-propiedad')) {
                   const idProp = botonAccion.dataset.id;
                   if (idProp) {
                        abrirModalDetallesPropiedad(idProp); // Asume que esta función existe
                   } else { console.warn("Botón 'Ver Propiedad' sin id."); }
             }
         }

         // Manejar clics en enlaces internos del modal (ej: ver negocio relacionado)
         const enlaceInterno = e.target.closest('.modal-detalles-entidad .boton-ver-detalles-interno');
         if(enlaceInterno) {
              e.preventDefault(); // Prevenir navegación si es un enlace
              e.stopPropagation();
              const tipo = enlaceInterno.dataset.tipo;
              const id = enlaceInterno.dataset.id;
              const modalActual = enlaceInterno.closest('.modal')?.id;

              if(tipo && id) {
                  if(modalActual) cerrarModal(modalActual);
                   setTimeout(() => mostrarDetallesEntidad(tipo, id), 50);
              } else { console.warn("Enlace interno sin tipo/id", enlaceInterno.dataset); }
         }
     });
     console.log("Delegación para botones de acción en modales configurada.");
}

// Placeholder para funciones llamadas por delegación
function abrirModalContacto(tipo, id) { console.warn(`FUNCIONALIDAD FALTANTE: abrirModalContacto(${tipo}, ${id})`); mostrarNotificacion("Próximamente", "Opción de contacto no disponible.", "aviso"); }
function abrirModalDetallesPropiedad(id) { console.warn(`FUNCIONALIDAD FALTANTE: abrirModalDetallesPropiedad(${id})`); mostrarNotificacion("Próximamente", "Detalles de propiedad no disponibles.", "aviso"); }

function destacarMarcador(lat, lng) { /* ... código sin cambios ... */ }

// =============================================================================
// Entity Details Display (Dispatcher)
// =============================================================================

function mostrarDetallesEntidad(tipo, id) {
    mapa?.closePopup();
    // Llama a las funciones de renderizado (asume que están disponibles globalmente desde ventanasDetalles.js)
    switch (tipo) {
        case 'negocio':
             if (typeof renderizarDetallesNegocio === 'function') {
                 // Usa el sistema de modales de app.js
                 crearModal(`detalles-negocio-${id}`, 'Detalles del Negocio', `<div id="cont-negocio-${id}"><div class="cargando-datos">Cargando...</div></div>`);
                 const cont = document.getElementById(`cont-negocio-${id}`);
                 // Fetch data and then render
                 fetch(`${API_BASE}negocios.php?id=${id}&detalles=1`, { headers: getAuthHeader() })
                     .then(res => res.ok ? res.json() : res.json().then(err => Promise.reject(err)))
                     .then(data => {
                          const negocioData = data.negocio || data; // Respuesta flexible
                          if (negocioData && cont) {
                              renderizarDetallesNegocio(negocioData, cont); // Llama a la función global
                          } else { throw new Error(data.mensaje || 'Datos no válidos'); }
                     })
                     .catch(err => {
                          console.error("Error al cargar detalles negocio:", err);
                          if(cont) cont.innerHTML = `<p class="error-carga">Error al cargar: ${err.message}</p>`;
                          mostrarNotificacion("Error", "No se pudo cargar el negocio", "error");
                     });
                 mostrarModal(`detalles-negocio-${id}`);
             } else console.error("Función renderizarDetallesNegocio no encontrada.");
             break;
        case 'evento':
             if (typeof renderizarDetallesEvento === 'function') {
                 crearModal(`detalles-evento-${id}`, 'Detalles del Evento', `<div id="cont-evento-${id}"><div class="cargando-datos">Cargando...</div></div>`);
                 const cont = document.getElementById(`cont-evento-${id}`);
                 fetch(`${API_BASE}eventos.php?id=${id}&detalles=1`, { headers: getAuthHeader() })
                     .then(res => res.ok ? res.json() : res.json().then(err => Promise.reject(err)))
                     .then(data => {
                          const eventoData = data.evento || data;
                          if (eventoData && cont) renderizarDetallesEvento(eventoData, cont);
                          else throw new Error(data.mensaje || 'Datos no válidos');
                      })
                      .catch(err => { if(cont) cont.innerHTML = `<p class="error-carga">Error: ${err.message}</p>`; mostrarNotificacion("Error", "No se pudo cargar el evento", "error"); });
                 mostrarModal(`detalles-evento-${id}`);
             } else console.error("Función renderizarDetallesEvento no encontrada.");
             break;
        case 'oferta':
              if (typeof renderizarDetallesOferta === 'function') {
                 crearModal(`detalles-oferta-${id}`, 'Detalles de la Oferta', `<div id="cont-oferta-${id}"><div class="cargando-datos">Cargando...</div></div>`);
                 const cont = document.getElementById(`cont-oferta-${id}`);
                 fetch(`${API_BASE}ofertas.php?id=${id}&detalles=1`, { headers: getAuthHeader() })
                     .then(res => res.ok ? res.json() : res.json().then(err => Promise.reject(err)))
                     .then(data => {
                          const ofertaData = data.oferta || data;
                          if (ofertaData && cont) renderizarDetallesOferta(ofertaData, cont);
                           else throw new Error(data.mensaje || 'Datos no válidos');
                     })
                     .catch(err => { if(cont) cont.innerHTML = `<p class="error-carga">Error: ${err.message}</p>`; mostrarNotificacion("Error", "No se pudo cargar la oferta", "error"); });
                 mostrarModal(`detalles-oferta-${id}`);
             } else console.error("Función renderizarDetallesOferta no encontrada.");
            break;
        case 'encuesta': // Renderizado genérico, botones de acción en modal se manejan por delegación
             if (typeof renderizarDetallesEncuesta === 'function') {
                  crearModal(`detalles-encuesta-${id}`, 'Detalles de Encuesta', `<div id="cont-encuesta-${id}"><div class="cargando-datos">Cargando...</div></div>`);
                  const cont = document.getElementById(`cont-encuesta-${id}`);
                  fetch(`${API_BASE}encuestas.php?id=${id}&detalles=1`, { headers: getAuthHeader() })
                     .then(res => res.ok ? res.json() : res.json().then(err => Promise.reject(err)))
                     .then(data => {
                          const encuestaData = data.encuesta || data;
                          if (encuestaData && cont) renderizarDetallesEncuesta(encuestaData, cont);
                           else throw new Error(data.mensaje || 'Datos no válidos');
                     })
                     .catch(err => { if(cont) cont.innerHTML = `<p class="error-carga">Error: ${err.message}</p>`; mostrarNotificacion("Error", "No se pudo cargar la encuesta", "error"); });
                 mostrarModal(`detalles-encuesta-${id}`);
             } else console.error("Función renderizarDetallesEncuesta no encontrada.");
             break;
         case 'producto':
             // Usa la función ya definida en app.js
             mostrarDetallesProducto(id);
             break;
        default:
            console.error("Tipo de entidad desconocido para detalles:", tipo);
            mostrarNotificacion('Error', `No se pueden mostrar detalles para el tipo '${tipo}'.`, 'error');
    }
}

function mostrarDetallesProducto(idProducto) { /* ... código sin cambios ... */ }
// Se asume que mostrarDetallesNegocio es llamada desde el dispatcher anterior

// =============================================================================
// Contact & Directions
// =============================================================================
function mostrarComoLlegar(lat, lng, nombreDestino = "Destino") { /* ... código sin cambios ... */ }

// =============================================================================
// Sharing Functions (Versión de app.js)
// =============================================================================
async function compartirEntidad(tipo, id, nombre) { /* ... código sin cambios ... */ }
function mostrarCompartirFallback(texto, url) { /* ... código sin cambios ... */ }

// =============================================================================
// Survey Functions (Implementaciones)
// =============================================================================
function mostrarFormularioRespuestaEncuesta(idEncuesta) { /* ... código sin cambios ... */ }
function crearPreguntaHTML(pregunta, preguntaId) { /* ... código sin cambios ... */ }
function enviarRespuestasEncuesta(event, idEncuesta) { /* ... código sin cambios ... */ }
function mostrarResultadosEncuesta(idEncuesta) { /* ... código sin cambios ... */ }
function generarGraficoResultado(contenedorId, tipoPregunta, datos) { /* ... código sin cambios ... */ }
function generarGraficoBarras(datos) { /* ... código sin cambios ... */ }
function generarGraficoPie(datos) { /* ... código sin cambios ... */ }
function generarGraficoLinea(datos) { /* ... código sin cambios ... */ }
function mostrarRespuestasTexto(respuestas) { /* ... código sin cambios ... */ }
function descargarResultadosEncuesta(idEncuesta, tituloEncuesta) { /* ... código sin cambios ... */ }
function compartirResultadosEncuesta(idEncuesta, tituloEncuesta) { /* ... código sin cambios ... */ }

// =============================================================================
// Authentication & User Profile
// =============================================================================
function comprobarLogin() { /* ... código sin cambios ... */ }
function mostrarInterfazUsuarioLogueado(panel) { /* ... código sin cambios ... */ }
function mostrarInterfazUsuarioNoLogueado(panel) { /* ... código sin cambios ... */ }
function cerrarSesion(event) { /* ... código sin cambios ... */ }
function mostrarFormularioLogin() { console.warn("FUNCIONALIDAD FALTANTE: mostrarFormularioLogin"); crearModal('login_modal', 'Iniciar Sesión', '<p>Formulario de Login no implementado.</p>'); mostrarModal('login_modal'); }
function mostrarFormularioRegistro() { console.warn("FUNCIONALIDAD FALTANTE: mostrarFormularioRegistro"); crearModal('registro_modal', 'Registrarse', '<p>Formulario de Registro no implementado.</p>'); mostrarModal('registro_modal'); }
function mostrarFormularioRecuperarPassword() { console.warn("FUNCIONALIDAD FALTANTE: mostrarFormularioRecuperarPassword"); crearModal('recuperar_modal', 'Recuperar Contraseña', '<p>Formulario de Recuperación no implementado.</p>'); mostrarModal('recuperar_modal'); }
function mostrarPerfil() { console.warn("FUNCIONALIDAD FALTANTE: mostrarPerfil"); crearModal('perfil_modal', 'Mi Perfil', '<p>Vista de Perfil no implementada.</p>'); mostrarModal('perfil_modal'); }

// =============================================================================
// Entity Creation & Management Forms
// =============================================================================
// ASUME que estas funciones existen o son cargadas desde otros scripts
// como formularioNegocio.js (que ahora usa globales)
// Si no, necesitas definirlas o implementarlas aquí.
function mostrarFormularioNegocio(idNegocio = null) {
     console.warn("Asumiendo que mostrarFormularioNegocio es manejado por formularioNegocio.js");
     // Esta función podría redirigir a la página del formulario si es una página separada:
     // window.location.href = `formulario_negocio.php${idNegocio ? '?id=' + idNegocio : ''}`;
     // O si es un componente SPA, cargaría ese componente/vista.
     // Por ahora, mostramos un placeholder.
     const titulo = idNegocio ? 'Editar Negocio' : 'Crear Negocio';
     crearModal('negocio_form_placeholder', titulo, '<p>El formulario de negocio se maneja en otro script/página.</p>');
     mostrarModal('negocio_form_placeholder');
}
function mostrarFormularioEvento(idEvento = null) { /* ... implementación sin cambios ... */ }
async function cargarDatosEvento(idEvento) { /* ... implementación sin cambios ... */ }
function mostrarFormularioOferta(idOferta = null) { /* ... implementación sin cambios ... */ }
async function cargarDatosOferta(idOferta) { /* ... implementación sin cambios ... */ }
function mostrarFormularioEncuesta(idEncuesta = null) { /* ... implementación sin cambios ... */ }
async function cargarDatosEncuesta(idEncuesta) { /* ... implementación sin cambios ... */ }
function inicializarMapaMini(idMapa, idLatInput, idLngInput, initialLat = -34.60, initialLng = -58.38, initialZoom = 13) { /* ... implementación sin cambios ... */ }
async function cargarOpcionesSelect(selectElementId, endpoint, valueField = 'id', textField = 'nombre', addDefaultOption = true, defaultOptionText = 'Seleccione...', filterFn = null) { /* ... implementación sin cambios ... */ }
function cargarCategoriasEventosSelect(selectElementId) { /* ... implementación sin cambios ... */ }
function cargarMisNegociosSelect(selectElementId, addDefaultOption = true, defaultOptionText = 'Seleccione negocio...') { /* ... implementación sin cambios ... */ }
function setupImagePreview(inputId, previewContainerId, currentImageInputId = null) { /* ... implementación sin cambios ... */ }
async function enviarFormularioEntidad(formId, apiUrl, modalId, successCallback = null) { /* ... implementación sin cambios ... */ }

// =============================================================================
// "My Entities" List Management
// =============================================================================
function mostrarMisEntidades(event) { /* ... implementación sin cambios ... */ }
async function cargarMisEntidades() { /* ... implementación sin cambios ... */ }
function configurarBotonesEntidadesLista(contenedor) { /* ... implementación sin cambios ... */ }
async function eliminarEntidad(tipo, id) { /* ... implementación sin cambios ... */ }
function abrirFormularioCrearEntidad(tipo, id = null) { /* ... implementación sin cambios ... */ }
function mostrarMenuCrearEntidad(event) { /* ... implementación sin cambios ... */ }

// =============================================================================
// Filters & Statistics Update
// =============================================================================
function aplicarFiltros() { /* ... implementación sin cambios ... */ }
function filtrarMarcadoresPorCategoria(clusterGroup, categoriasSeleccionadas) { /* ... implementación sin cambios ... */ }
function actualizarEstadisticas() { /* ... implementación sin cambios ... */ }
function actualizarRelacionesRelevantes(bounds) { /* ... implementación sin cambios ... */ }
async function cargarCategorias() { /* ... implementación sin cambios ... */ }
function renderizarOpcionesCategoria(contenedor, categorias, tipoEntidad) { /* ... implementación sin cambios ... */ }

// =============================================================================
// Interaction Registration (Definida aquí)
// =============================================================================
/**
 * Registra una interacción del usuario con una entidad.
 */
async function registrarInteraccion(tipoEntidad, idEntidad, tipoInteraccion) {
  const idUsuario = obtenerUsuarioActual()?.id || null;
  const apiUrl = `${API_BASE}interacciones.php`;

  try {
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...getAuthHeader(false) },
      body: JSON.stringify({
        id_usuario: idUsuario,
        tipo_entidad: tipoEntidad,
        id_entidad: idEntidad,
        tipo_interaccion: tipoInteraccion
      })
    });

    if (!response.ok) {
      console.warn(`Registro fallido (${response.status}): ${tipoEntidad} ${idEntidad} - ${tipoInteraccion}`);
    } else {
      console.log(`Interacción registrada: ${tipoEntidad} ${idEntidad} - ${tipoInteraccion}`);
    }
  } catch (error) {
    console.error(`Error de red al registrar interacción para ${tipoEntidad} ${idEntidad}:`, error);
  }
}


// =============================================================================
// Initialization on DOM Load
// =============================================================================
document.addEventListener('DOMContentLoaded', function() {
    // Inicializaciones básicas
    inicializarMapa();
    inicializarNotificaciones();
    cargarCategorias(); // Carga categorías para filtros
    comprobarLogin(); // Verifica estado de sesión y actualiza UI de usuario

    // Configura listeners para controles del mapa y delegación de popups/modales
    setupControlListeners();
    setupPopupButtons(); // Para botones DENTRO de popups
    setupModalActionButtons(); // Para botones DENTRO de modales de detalles

    // Manejo de Deep Linking
    const params = new URLSearchParams(window.location.search);
    const tipoParam = params.get('tipo');
    const idParam = params.get('id');
    const encuestaParam = params.get('encuesta');
    const verResultadosParam = params.get('verResultados');

    if (tipoParam && idParam) {
        // Pequeño delay para asegurar que el mapa esté listo
        setTimeout(() => {
             // Intenta centrar el mapa y luego muestra detalles
             fetch(`${API_BASE}${tipoParam}s.php?id=${idParam}`) // Endpoint plural simple
                 .then(res => res.ok ? res.json() : Promise.reject('Entidad no encontrada'))
                 .then(data => {
                     // Asume respuesta flexible {entidad: {...}} o {...}
                     const entidad = data.entidad || data.negocio || data.evento || data.oferta || data.encuesta || data;
                     if (entidad && entidad.latitud && entidad.longitud) {
                         mapa.setView([entidad.latitud, entidad.longitud], 17);
                         destacarMarcador(entidad.latitud, entidad.longitud);
                     } else {
                          console.warn("Deep link: No se pudo obtener ubicación para centrar mapa.");
                     }
                     // Muestra los detalles independientemente de si se centró el mapa
                     mostrarDetallesEntidad(tipoParam, idParam);
                 })
                 .catch(err => {
                     console.error("Error en deep link:", err);
                     mostrarNotificacion('Error', 'No se pudo cargar la entidad solicitada.', 'error');
                 });
        }, 500);
    } else if (encuestaParam && verResultadosParam === '1') {
         const encuestaId = encuestaParam;
          setTimeout(() => {
              // Intenta centrar en la encuesta antes de mostrar resultados
              fetch(`${API_BASE}encuestas.php?id=${encuestaId}`)
                  .then(res => res.ok ? res.json() : Promise.reject('Encuesta no encontrada'))
                  .then(data => {
                      const encuesta = data.encuesta || data;
                      if (encuesta && encuesta.latitud && encuesta.longitud) {
                         mapa.setView([encuesta.latitud, encuesta.longitud], 16);
                         destacarMarcador(encuesta.latitud, encuesta.longitud);
                      }
                       mostrarResultadosEncuesta(encuestaId);
                  })
                  .catch(err => {
                      console.error("Error en deep link (resultados encuesta):", err);
                      mostrarNotificacion('Error', 'No se pudo encontrar la encuesta solicitada para centrar.', 'aviso');
                      mostrarResultadosEncuesta(encuestaId); // Intenta mostrar resultados de todos modos
                  });
         }, 500);
    } else {
        // Si no hay deep link, intenta geolocalizar al usuario después de un momento
        console.log("Intentando geolocalización inicial...");
        setTimeout(() => {
             if (!posicionActual) { // Solo si no se encontró antes (p.ej. por deep link)
                 mapa?.locate({ setView: true, maxZoom: 15, enableHighAccuracy: true });
             }
        }, 1500);
    }
});

// =============================================================================
// Funciones de Renderizado de Detalles (Placeholder/Asunción)
// =============================================================================
// Se asume que el script ventanasDetalles.js se carga DESPUÉS de app.js
// y que sus funciones exportadas (renderizarDetallesNegocio, etc.) quedan
// disponibles en el scope global para que mostrarDetallesEntidad las llame.
// Si usaras módulos ES6 de forma estricta, necesitarías importar
// estas funciones aquí arriba.
/*
import {
    renderizarDetallesNegocio,
    renderizarDetallesEvento,
    renderizarDetallesOferta,
    renderizarDetallesEncuesta
} from './ventanasDetalles.js'; // Ejemplo si fuera módulo
*/