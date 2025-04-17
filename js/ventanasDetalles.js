// ventanasDetalles.js – Versión mejorada
// =============================================================
// Renderizado de ventanas modales para negocios, eventos, ofertas y encuestas.
//  • Diseño robusto con validaciones, manejo de errores y notificaciones.
//  • Se expone una API mínima (export) para ser utilizada desde otros módulos.
//  • Requiere utilidades.js y otros módulos indicados más abajo.
// =============================================================

/*
┌──────────────────────────────────────────────────────────────────────────────┐
│  DEPENDENCIAS (import obligatorios)                                         │
└──────────────────────────────────────────────────────────────────────────────┘
*/
import {
    extraerYoutubeID,
    formatearPrecio,
    mostrarNotificacion,
  } from "./utilidades.js";
  
  // Estas utilidades arrojan notificaciones/peticiones de UI a nivel global.
  import {
    cerrarModal,
    abrirModalDetalles,
    abrirModalContacto,
    abrirModalDetallesPropiedad,
  } from "./modal.js";
  
  import { mostrarRutaEnMapa } from "./mapa.js";
  
  /*
  ┌──────────────────────────────────────────────────────────────────────────────┐
  │  HELPERS INTERNOS                                                            │
  └──────────────────────────────────────────────────────────────────────────────┘
  */
  const _noop = () => {};
  
  /**
   * Genera código HTML seguro eliminando null/undefined.
   * @param {string|undefined|null} value
   * @returns {string}
   */
  function _safe(value) {
    return value ?? "";
  }
  
  /**
   * Devuelve un bloque <iframe> con el vídeo de YouTube o cadena vacía si falla.
   * @param {string} url
   * @param {string} title
   */
  function _buildYoutubeIframe(url, title = "Video") {
    try {
      const videoId = extraerYoutubeID(url);
      if (!videoId) throw new Error("ID de video no válido");
      return `<iframe src="https://www.youtube.com/embed/${videoId}" title="${_safe(title)}" frameborder="0" allowfullscreen></iframe>`;
    } catch (err) {
      console.error("Error al construir iframe de YouTube:", err);
      return "<p>Video no disponible</p>";
    }
  }
  
  /**
   * Devuelve un bloque <img> con fallback de alt.
   */
  function _buildImg(src, alt = "Imagen") {
    if (!src) {
      console.warn("Fuente de imagen no proporcionada");
      return `<img src="" alt="${_safe(alt)}">`;
    }
    return `<img src="${_safe(src)}" alt="${_safe(alt)}">`;
  }
  
  /*
  ┌──────────────────────────────────────────────────────────────────────────────┐
  │  RENDER NEGOCIO                                                             │
  └──────────────────────────────────────────────────────────────────────────────┘
  */
  export function renderizarDetallesNegocio(negocio, contenedor) {
    try {
      if (!negocio || !contenedor) throw new Error("Datos o contenedor no válidos");
      contenedor.innerHTML = `
        <h2>${_safe(negocio.nombre)}</h2>
        <p>${_safe(negocio.descripcion)}</p>
        <div>${_buildImg(negocio.imagen, negocio.nombre)}</div>
      `;
    } catch (err) {
      console.error("Error al renderizar detalles del negocio:", err);
      contenedor.innerHTML = "<p>Error al cargar los detalles del negocio.</p>";
    }
  }
  
  /*
  ┌──────────────────────────────────────────────────────────────────────────────┐
  │  INMOBILIARIA (propiedades, filtros)                                         │
  └──────────────────────────────────────────────────────────────────────────────┘
  */
  function _renderBloqueInmobiliaria(negocio, contenedor) {
    const contProp = document.createElement("div");
    contProp.className = "detalles-seccion detalles-propiedades-inmobiliaria";
    contProp.innerHTML = `
      <h3 class="detalles-subtitulo">Propiedades disponibles</h3>
      <div class="filtros-propiedades">
        <select class="selector-tipo-propiedad" aria-label="Tipo">
          <option value="todos">Todos</option>
          <option value="casa">Casa</option>
          <option value="departamento">Departamento</option>
          <option value="terreno">Terreno</option>
          <option value="local">Local</option>
          <option value="oficina">Oficina</option>
        </select>
        <select class="selector-operacion-propiedad" aria-label="Operación">
          <option value="todos">Todas</option>
          <option value="venta">Venta</option>
          <option value="alquiler">Alquiler</option>
          <option value="alquiler_temporal">Alquiler temporal</option>
        </select>
        <div class="rango-precio">
          <label for="slider-precio-inm">Precio máx.:</label>
          <input type="range" id="slider-precio-inm" class="slider-precio" min="0" step="50000">
          <div class="valor-rango-precio">$<span class="monto-precio-max"></span></div>
        </div>
      </div>
      <div class="lista-propiedades"></div>`;
  
    const acciones = contenedor.querySelector(".detalles-acciones") || contenedor;
    acciones.parentNode.insertBefore(contProp, acciones);
  
    renderizarPropiedadesInmobiliaria(negocio.propiedades, contProp.querySelector(".lista-propiedades"));
    configurarFiltrosPropiedades(contProp, negocio.propiedades, contProp.querySelector(".lista-propiedades"));
  }
  
  export function renderizarPropiedadesInmobiliaria(propiedades, contenedor) {
    contenedor.innerHTML = "";
    if (!Array.isArray(propiedades) || !propiedades.length) {
      contenedor.innerHTML = "<p class=\"sin-propiedades\">No hay propiedades.</p>";
      return;
    }
    propiedades.forEach(p => {
      const div = document.createElement("div");
      div.className = "item-propiedad";
      div.dataset.tipo = p.tipo || "otro";
      div.dataset.operacion = p.operacion || "venta";
      div.dataset.precio = p.precio || 0;
      div.innerHTML = `
        <div class="propiedad-imagen">
          ${p.imagen ? _buildImg(p.imagen, p.titulo) : '<div class="sin-imagen">Sin imagen</div>'}
          <div class="propiedad-etiqueta-operacion">${p.operacion || "Venta"}</div>
        </div>
        <div class="propiedad-info">
          <h4 class="propiedad-titulo">${_safe(p.titulo)}</h4>
          <p class="propiedad-ubicacion"><i class="fas fa-map-marker-alt"></i> ${_safe(p.ubicacion) || "Sin ubicación"}</p>
          ${p.precio !== undefined ? `<p class="propiedad-precio">$${formatearPrecio(p.precio)}</p>` : ""}
          <p class="propiedad-caracteristicas">
            ${p.superficie ? `<span><i class="fas fa-ruler-combined"></i> ${p.superficie} m²</span>` : ""}
            ${p.ambientes ? `<span><i class="fas fa-vector-square"></i> ${p.ambientes} amb.</span>` : ""}
            ${p.dormitorios ? `<span><i class="fas fa-bed"></i> ${p.dormitorios} dorm.</span>` : ""}
            ${p.banos ? `<span><i class="fas fa-bath"></i> ${p.banos} baños</span>` : ""}
          </p>
          <button class="boton-ver-propiedad" data-id="${p.id}">Ver detalles</button>
        </div>`;
      div.querySelector(".boton-ver-propiedad").addEventListener("click", e => {
        abrirModalDetallesPropiedad?.(e.currentTarget.dataset.id);
      });
      contenedor.appendChild(div);
    });
  }
  
  export function configurarFiltrosPropiedades(wrapper, todas, destino) {
    const tipoSel = wrapper.querySelector(".selector-tipo-propiedad");
    const opSel = wrapper.querySelector(".selector-operacion-propiedad");
    const slider = wrapper.querySelector(".slider-precio");
    const spanMax = wrapper.querySelector(".monto-precio-max");
  
    const max = todas.reduce((m, p) => Math.max(m, p.precio || 0), 0) || 1000000;
    slider.max = max;
    slider.value = max;
    spanMax.textContent = formatearPrecio(max);
  
    const filtrar = () => {
      const t = tipoSel.value;
      const o = opSel.value;
      const pMax = +slider.value;
      spanMax.textContent = formatearPrecio(pMax);
      const filtradas = todas.filter(p =>
        (t === "todos" || p.tipo === t) &&
        (o === "todos" || p.operacion === o) &&
        (p.precio || 0) <= pMax);
      renderizarPropiedadesInmobiliaria(filtradas, destino);
    };
  
    tipoSel.addEventListener("change", filtrar);
    opSel.addEventListener("change", filtrar);
    slider.addEventListener("input", filtrar);
  }
  
  /*
  ┌──────────────────────────────────────────────────────────────────────────────┐
  │  RENDER EVENTO / OFERTA / ENCUESTA (abreviados)                              │
  └──────────────────────────────────────────────────────────────────────────────┘
  */
  export function renderizarDetallesEvento(evento, contenedor) {
    _renderGenericoEventoOfertaEncuesta("evento", evento, contenedor);
  }
  export function renderizarDetallesOferta(oferta, contenedor) {
    _renderGenericoEventoOfertaEncuesta("oferta", oferta, contenedor);
  }
  export function renderizarDetallesEncuesta(encuesta, contenedor) {
    _renderGenericoEventoOfertaEncuesta("encuesta", encuesta, contenedor);
  }
  
  /**
   * Versión simplificada y unificada para eventos, ofertas y encuestas.
   * Genera la interfaz básica y registra eventos comunes (cómo llegar, ver negocio, etc.).
   */
  function _renderGenericoEventoOfertaEncuesta(tipo, data, contenedor) {
    try {
      if (!data || typeof data !== "object" || !(contenedor instanceof HTMLElement)) {
        throw new Error("Datos/Contenedor inválidos para " + tipo);
      }
  
      const fechaInicio = data.fecha_inicio ? new Date(data.fecha_inicio) : null;
      const fechaFin = data.fecha_fin ? new Date(data.fecha_fin) : null;
      const fmt = { year: "numeric", month: "long", day: "numeric" };
  
      // Media principal
      let mediaHTML = "";
      if (data.link_video) mediaHTML = _buildYoutubeIframe(data.link_video, data.titulo);
      if (!mediaHTML && data.imagen) mediaHTML = `<div class="detalles-imagen-container">${_buildImg(data.imagen, data.titulo)}</div>`;
  
      // Cabecera genérica
      const cabeceraHTML = `
        <div class="detalles-info-cabecera">
          <h2 class="detalles-titulo">${_safe(data.titulo) || tipo.toUpperCase()}</h2>
          ${data.categoria ? `<p class="detalles-categoria">${data.categoria}</p>` : ""}
        </div>`;
  
      // Fechas / descripción / precio / organizador
      let cuerpoHTML = "";
      if (tipo === "evento") {
        cuerpoHTML += `
          <div class="detalles-seccion detalles-fecha-lugar">
            <h3 class="detalles-subtitulo">Cuándo y dónde</h3>
            <p><i class="far fa-calendar-alt"></i> ${fechaInicio?.toLocaleDateString("es-ES", fmt)} ${fechaFin ? " - " + fechaFin.toLocaleDateString("es-ES", fmt) : ""}</p>
            <p><i class="fas fa-map-marker-alt"></i> ${_safe(data.direccion) || "Ubicación no especificada"}</p>
          </div>`;
        cuerpoHTML += data.descripcion ? `<div class="detalles-seccion"><h3 class="detalles-subtitulo">Descripción</h3><p>${data.descripcion}</p></div>` : "";
        if (data.precio) {
          cuerpoHTML += `<div class="detalles-seccion"><h3 class="detalles-subtitulo">Entrada</h3><p>${data.precio}</p>${data.link_entradas ? `<a class="boton-comprar-entradas" href="${data.link_entradas}" target="_blank" rel="noopener">Comprar</a>` : ""}</div>`;
        }
      } else if (tipo === "oferta") {
        const pct = data.porcentaje_descuento ?? Math.round(((data.precio_normal - data.precio_oferta) / data.precio_normal) * 100);
        cuerpoHTML += `<div class="detalles-seccion detalles-precios-oferta"><h3 class="detalles-subtitulo">Precio</h3><p class="precio-oferta-grande">$${formatearPrecio(data.precio_oferta)}</p>${data.precio_normal ? `<p class="precio-normal-tachado">$${formatearPrecio(data.precio_normal)}</p>` : ""}${pct ? `<span class="detalles-oferta-descuento">-${pct}%</span>` : ""}</div>`;
        cuerpoHTML += `<div class="detalles-seccion detalles-validez-oferta"><h3 class="detalles-subtitulo">Validez</h3><p><i class="far fa-calendar-alt"></i> ${fechaInicio?.toLocaleDateString("es-ES", fmt)} – ${fechaFin?.toLocaleDateString("es-ES", fmt)}</p></div>`;
        cuerpoHTML += data.descripcion ? `<div class="detalles-seccion"><h3 class="detalles-subtitulo">Descripción</h3><p>${data.descripcion}</p></div>` : "";
      } else if (tipo === "encuesta") {
        cuerpoHTML += `<div class="detalles-seccion detalles-validez-encuesta"><h3 class="detalles-subtitulo">Disponibilidad</h3><p><i class="far fa-calendar-alt"></i> ${fechaInicio?.toLocaleDateString("es-ES", fmt)} – ${fechaFin ? fechaFin.toLocaleDateString("es-ES", fmt) : "sin fecha límite"}</p></div>`;
        cuerpoHTML += data.descripcion ? `<div class="detalles-seccion"><h3 class="detalles-subtitulo">Descripción</h3><p>${data.descripcion}</p></div>` : "";
        if (data.recompensa) cuerpoHTML += `<div class="detalles-seccion"><h3 class="detalles-subtitulo">Recompensa</h3><p><i class="fas fa-gift"></i> ${data.recompensa}</p></div>`;
      }
  
      // Acciones comunes
      const accionesHTML = `
        <div class="detalles-acciones">
          ${data.latitud && data.longitud ? `<button class="boton-detalles-accion boton-como-llegar" data-lat="${data.latitud}" data-lng="${data.longitud}"><i class="fas fa-directions"></i> Cómo llegar</button>` : ""}
          ${data.id_negocio ? `<button class="boton-detalles-accion boton-ver-negocio-asociado" data-id="${data.id_negocio}"><i class="fas fa-store"></i> Ver negocio</button>` : ""}
          ${tipo === "encuesta" ? `<button class="boton-detalles-accion boton-participar-encuesta" data-id="${data.id}"><i class="fas fa-poll-h"></i> Participar</button>` : ""}
        </div>`;
  
      contenedor.innerHTML = `<div class="detalles-entidad detalles-${tipo}">${mediaHTML}<div class="detalles-contenido">${cabeceraHTML}${cuerpoHTML}${accionesHTML}</div></div>`;
  
      // Eventos comunes
      contenedor.querySelector(".boton-como-llegar")?.addEventListener("click", e => {
        const { lat, lng } = e.currentTarget.dataset;
        mostrarRutaEnMapa?.(lat, lng);
        cerrarModal?.(contenedor.closest(".modal-detalles-entidad"));
      });
  
      contenedor.querySelector(".boton-ver-negocio-asociado")?.addEventListener("click", e => {
        cerrarModal?.(contenedor.closest(".modal-detalles-entidad"));
        abrirModalDetalles?.("negocio", e.currentTarget.dataset.id);
      });
  
      if (tipo === "encuesta") {
        contenedor.querySelector(".boton-participar-encuesta")?.addEventListener("click", e => {
          console.log("TODO: mostrar formulario encuesta", e.currentTarget.dataset.id);
        });
      }
    } catch (err) {
      console.error(`[ventanasDetalles] Error renderizarDetalles(${tipo})`, err);
      mostrarNotificacion?.("Error", `No se pudo cargar el ${tipo}`, "error");
    }
  }
