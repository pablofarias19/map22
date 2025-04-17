// modal.js - Módulo de gestión de modales

/**
 * Crea y añade al DOM la estructura básica de un modal.
 * @param {string} id ID único para el modal.
 * @param {string} titulo Título del modal (se sanitizará).
 * @param {string} contenido HTML interno para el cuerpo del modal (¡debe ser seguro!).
 */
export function crearModal(id, titulo, contenido) {
  try {
    const modalExistente = document.getElementById(id);
    if (modalExistente) modalExistente.remove();

    const modal = document.createElement('div');
    modal.id = id;
    modal.className = 'modal';
    modal.setAttribute('aria-hidden', 'true');

    modal.innerHTML = `
      <div class="modal-dialogo" role="dialog" aria-modal="true" aria-labelledby="modal-titulo-${id}">
        <div class="modal-contenido">
          <div class="modal-cabecera">
            <h3 class="modal-titulo" id="modal-titulo-${id}">${sanitizeHTML(titulo)}</h3>
            <button class="boton-cerrar-modal" aria-label="Cerrar">×</button>
          </div>
          <div class="modal-cuerpo">
            ${contenido}
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    const btnCerrar = modal.querySelector('.boton-cerrar-modal');
    btnCerrar?.addEventListener('click', () => cerrarModal(id));

    modal.addEventListener('click', (e) => {
      if (e.target === modal) cerrarModal(id);
    });
  } catch (error) {
    console.error("Error al crear el modal:", error);
  }
}

/**
 * Muestra un modal previamente creado por su ID.
 * @param {string} id ID del modal a mostrar.
 */
export function mostrarModal(id) {
  try {
    const modal = document.getElementById(id);
    if (!modal) throw new Error(`Modal con ID "${id}" no encontrado.`);

    modal.style.display = 'flex';
    document.body.classList.add('modal-abierto');
    modal.setAttribute('aria-hidden', 'false');

    setTimeout(() => {
      modal.classList.add('mostrar');
      const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      focusable?.focus();
    }, 10);
  } catch (error) {
    console.error("Error al mostrar el modal:", error);
  }
}

/**
 * Cierra un modal por su ID.
 * @param {string} id ID del modal a cerrar.
 */
export function cerrarModal(id) {
  try {
    const modal = document.getElementById(id);
    if (!modal) throw new Error(`Modal con ID "${id}" no encontrado.`);

    modal.classList.remove('mostrar');
    document.body.classList.remove('modal-abierto');
    modal.setAttribute('aria-hidden', 'true');

    setTimeout(() => {
      modal.remove();
    }, 300);
  } catch (error) {
    console.error("Error al cerrar el modal:", error);
  }
}