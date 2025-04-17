/* ==========================================================================
   formularioNegocio.js – v2.1 (abril 2025)
   Gestión de alta / edición de negocios con Leaflet y REST API
   --------------------------------------------------------------------------
   • Requiere:  Leaflet (+draw + geocoder)  |  utilidades.js  |  auth.js
   • API REST:
       ▸ GET  api/categorias_negocios.php
       ▸ GET  api/areas_produccion.php
       ▸ POST api/negocios.php        (alta)
       ▸ POST api/negocios.php?id=ID  (_method=PUT → edición)
   ======================================================================== */

   "use strict";

   // ──────────────────────────────────────────────────────────────────────────
   //  Importaciones y constantes globales
   // ──────────────────────────────────────────────────────────────────────────
   import { mostrarNotificacion } from "./utilidades.js";
   import { obtenerUsuarioActual, getAuthHeader } from "./auth.js";
   
   const API = {
     CATEGORIAS: "../../api/categorias_negocios.php",
     AREAS: "../../api/areas_produccion.php",
     NEGOCIOS: "../../api/negocios.php",
     ZONAS: "../../api/zonas_influencia.php",
   };
   
   const REGEX = {
     TELEFONO: /^[0-9+\s()-]{6,}$/,
     EMAIL: /\S+@\S+\.\S+/,
     URL: /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i,
   };
   
   // Referencias DOM y estado interno
   const dom = {};
   const state = {
     idEdicion: null,
     mapaUbicacion: null,
     mapaZona: null,
     marcador: null,
     capaZonas: null,
   };
   
   // ──────────────────────────────────────────────────────────────────────────
   //  Init
   // ──────────────────────────────────────────────────────────────────────────
   document.addEventListener("DOMContentLoaded", () => {
     cacheDOM();
     verificarSesion();
     wireListeners();
     initSelects();
     initMapas();
     checkModoEdicion();
   });
   
   function cacheDOM() {
     dom.form = document.getElementById("form_negocio");
     dom.overlay = document.getElementById("overlay_carga");
     dom.btnGuardar = dom.form.querySelector(".boton-guardar");
     dom.btnCancelar = dom.form.querySelector(".boton-cancelar");
     dom.campos = {
       nombre: dom.form.nombre_comercial,
       lema: dom.form.lema_publicitario,
       categoria: dom.form.id_categoria,
       area: dom.form.id_area_produccion,
       direccion: dom.form.direccion,
       telefono: dom.form.telefono,
       email: dom.form.email,
       web: dom.form.sitio_web,
       lat: dom.form.latitud,
       lon: dom.form.longitud,
       zona: dom.form.zona_influencia_geojson,
     };
   }
   
   function verificarSesion() {
     if (!obtenerUsuarioActual()) {
       mostrarNotificacion("Acceso denegado", "Debes iniciar sesión", "error");
       return setTimeout(() => (window.location.href = "login.php"), 1500);
     }
   }
   
   function wireListeners() {
     dom.form.addEventListener("submit", onSubmit);
     dom.btnCancelar?.addEventListener("click", () => {
       if (confirm("¿Descartar cambios?")) history.back();
     });
     dom.form.addEventListener("input", (e) => validarCampo(e.target));
   }
   
   // ──────────────────────────────────────────────────────────────────────────
   //  Selects dinámicos (categorías / áreas)
   // ──────────────────────────────────────────────────────────────────────────
   async function initSelects() {
     await Promise.all([
       cargarOpciones(dom.campos.categoria, API.CATEGORIAS, "Categorías"),
       cargarOpciones(dom.campos.area, API.AREAS, "áreas de producción", true),
     ]);
   }
   
   /**
    * Pobla un <select> con datos traídos de la API.
    * @param {HTMLSelectElement} select
    * @param {string} url
    * @param {string} etiqueta Nombre genérico para los mensajes
    * @param {boolean} puedeEstarVacio Incluir "opcional" al principio
    */
   async function cargarOpciones(select, url, etiqueta, puedeEstarVacio = false) {
     try {
       const response = await fetch(url);
       if (!response.ok) throw new Error(`Error al cargar ${etiqueta}: ${response.statusText}`);
       const data = await response.json();
   
       // Limpia las opciones existentes
       select.innerHTML = puedeEstarVacio ? '<option value="">Seleccione...</option>' : '';
   
       // Agrega las nuevas opciones
       data.forEach(item => {
         const option = document.createElement('option');
         option.value = item.id;
         option.textContent = item.nombre;
         select.appendChild(option);
       });
     } catch (error) {
       console.error(`Error al cargar opciones para ${etiqueta}:`, error);
       mostrarNotificacion('Error', `No se pudieron cargar las opciones de ${etiqueta}.`, 'error');
     }
   }
   
   // ──────────────────────────────────────────────────────────────────────────
   //  Mapas: ubicación y zona de influencia
   // ──────────────────────────────────────────────────────────────────────────
   function initMapas() {
     // Mapa de ubicación
     state.mapaUbicacion = L.map("mapa_seleccion", { center: [-34.6, -58.38], zoom: 13 });
     L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(state.mapaUbicacion);
   
     L.Control.geocoder({ defaultMarkGeocode: false })
       .on("markgeocode", (e) => setUbicacion(e.geocode.center, e.geocode.properties.display_name))
       .addTo(state.mapaUbicacion);
   
     state.mapaUbicacion.on("click", (e) => geocodeReverse(e.latlng));
   
     // Mapa de zona de influencia
     state.mapaZona = L.map("mapa_zona", { center: [-34.6, -58.38], zoom: 13 });
     L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(state.mapaZona);
   
     state.capaZonas = new L.FeatureGroup().addTo(state.mapaZona);
   
     state.mapaZona.addControl(
       new L.Control.Draw({
         edit: { featureGroup: state.capaZonas },
         draw: {
           polygon: { allowIntersection: false, showArea: true },
           polyline: false,
           rectangle: false,
           circle: false,
           marker: false,
         },
       })
     );
   
     state.mapaZona.on(L.Draw.Event.CREATED, (e) => {
       state.capaZonas.clearLayers();
       state.capaZonas.addLayer(e.layer);
       dom.campos.zona.value = JSON.stringify(e.layer.toGeoJSON().geometry);
     });
     state.mapaZona.on(L.Draw.Event.DELETED, () => (dom.campos.zona.value = ""));
   }
   
   async function geocodeReverse(latlng) {
     const geocoder = L.Control.Geocoder.nominatim();
     geocoder.reverse(latlng, state.mapaUbicacion.getZoom(), (results) => {
       const direccion = results[0]?.name || "";
       setUbicacion(latlng, direccion);
     });
   }
   
   function setUbicacion({ lat, lng }, direccion = "") {
     dom.campos.lat.value = lat.toFixed(6);
     dom.campos.lon.value = lng.toFixed(6);
     if (direccion) dom.campos.direccion.value = direccion;
   
     if (state.marcador) state.marcador.setLatLng([lat, lng]);
     else {
       state.marcador = L.marker([lat, lng], { draggable: true }).addTo(state.mapaUbicacion);
       state.marcador.on("dragend", (e) => geocodeReverse(e.target.getLatLng()));
     }
   }
   
   // ──────────────────────────────────────────────────────────────────────────
   //  Modo edición
   // ──────────────────────────────────────────────────────────────────────────
   async function checkModoEdicion() {
     const id = new URLSearchParams(location.search).get("id");
     if (!id) return;
   
     state.idEdicion = id;
     dom.form.querySelector(".titulo-formulario").textContent = "Editar negocio";
     dom.btnGuardar.innerHTML = "<i class=\"fas fa-save\"></i> Actualizar";
   
     try {
       dom.overlay.classList.add("visible");
       const res = await fetch(`${API.NEGOCIOS}?id=${id}`, { headers: getAuthHeader() });
       if (!res.ok) throw new Error("No encontrado");
       const data = await res.json();
       rellenarFormulario(data);
       await cargarZona(id);
     } catch (err) {
       mostrarNotificacion("Error", "No se pudo cargar el negocio", "error");
       history.back();
     } finally {
       dom.overlay.classList.remove("visible");
     }
   }
   
   function rellenarFormulario(n) {
     dom.campos.nombre.value = n.nombre_comercial;
     dom.campos.lema.value = n.lema_publicitario || "";
     dom.campos.categoria.value = n.id_categoria;
     dom.campos.area.value = n.id_area_produccion || "";
     dom.campos.direccion.value = n.direccion || "";
     dom.campos.telefono.value = n.telefono || "";
     dom.campos.email.value = n.email || "";
     dom.campos.web.value = n.sitio_web || "";
     setUbicacion({ lat: +n.latitud, lng: +n.longitud });
   }
   
   async function cargarZona(id) {
     const res = await fetch(`${API.ZONAS}?id_negocio=${id}`);
     if (!res.ok) return;
     const data = await res.json();
     if (data.features?.length) {
       const layer = L.geoJSON(data.features[0]).getLayers()[0];
       state.capaZonas.addLayer(layer);
       state.mapaZona.fitBounds(layer.getBounds());
       dom.campos.zona.value = JSON.stringify(layer.toGeoJSON().geometry);
     }
   }
   
   // ──────────────────────────────────────────────────────────────────────────
   //  Envío de formulario
   // ──────────────────────────────────────────────────────────────────────────
   async function onSubmit(evt) {
     evt.preventDefault();
     if (!validarFormularioCompleto()) {
       mostrarErroresFormulario();
       return;
     }
   
     dom.overlay.classList.add("visible");
     dom.btnGuardar.disabled = true;
   
     const formData = new FormData(dom.form);
     if (state.idEdicion) formData.append("_method", "PUT");
   
     try {
       const res = await fetch(state.idEdicion ? `${API.NEGOCIOS}?id=${state.idEdicion}` : API.NEGOCIOS, {
         method: "POST",
         body: formData,
         headers: getAuthHeader(false), // sin Content-Type → lo pone el navegador
       });
       const data = await res.json();
       if (!res.ok || !data.success) throw new Error(data.mensaje || res.statusText);
       mostrarNotificacion("Éxito", data.mensaje || "Negocio guardado", "success");
       setTimeout(() => (location.href = "mis_negocios.php"), 1200);
     } catch (err) {
       mostrarNotificacion("Error", err.message, "error");
     } finally {
       dom.overlay.classList.remove("visible");
       dom.btnGuardar.disabled = false;
     }
   }
   
   // ──────────────────────────────────────────────────────────────────────────
   //  Validaciones
   // ──────────────────────────────────────────────────────────────────────────
   function validarCampo(el) {
     if (!el.name) return true;
     const v = el.value.trim();
     let ok = true,
       msg = "";
   
     if (el.required && !v) {
       ok = false;
       msg = "Obligatorio";
     } else if (el === dom.campos.telefono && v && !REGEX.TELEFONO.test(v)) {
       ok = false;
       msg = "Teléfono inválido";
     } else if (el === dom.campos.email && v && !REGEX.EMAIL.test(v)) {
       ok = false;
       msg = "Email inválido";
     } else if (el === dom.campos.web && v && !REGEX.URL.test(v)) {
       ok = false;
       msg = "URL inválida";
     }
   
     const err = el.closest(".grupo-formulario")?.querySelector(".mensaje-error");
     if (err) err.textContent = msg;
     el.classList.toggle("invalido", !ok);
     return ok;
   }
   
   function validarFormularioCompleto() {
     return Array.from(dom.form.querySelectorAll("input, select"))
       .every(input => input.value.trim() !== '');
   }
   
   // Mejora: Agregar validación visual
   function mostrarErroresFormulario() {
     Array.from(dom.form.querySelectorAll("input, select")).forEach(input => {
       if (input.value.trim() === '') {
         input.classList.add('input-error');
       } else {
         input.classList.remove('input-error');
       }
     });
   }
