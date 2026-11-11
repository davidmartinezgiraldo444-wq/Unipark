// UniPark · Celular de cámara (modo de dos celulares).
// Reconoce de forma continua el vehículo del carril y publica en el servidor SOLO su tipo y la
// confianza (nunca la imagen). El celular del portero toma esa detección al escanear un carnet.
(function () {
  'use strict';

  const { config, encender, post } = window.UniCam;
  const $ = (s) => document.querySelector(s);
  const video = $('[data-video]');
  const ui = {
    estado: $('[data-visor-estado]'), apagado: $('[data-visor-apagado]'), puesto: $('[data-puesto]'),
    ia: $('[data-ia]'), fuente: $('[data-ia-fuente]'), clase: $('[data-ia-clase]'), barra: $('[data-ia-barra]'), nota: $('[data-ia-nota]')
  };

  let ultimoEnvio = 0;
  let ultimoTipo = null;
  let wakeLock = null;

  $('[data-encender]').addEventListener('click', async () => {
    try {
      await encender(video);
    } catch (e) {
      ui.estado.textContent = 'Sin cámara';
      ui.nota.textContent = e.name === 'NotAllowedError' ? 'Permiso de cámara denegado.' : (e.message || 'No se pudo encender la cámara.');
      return;
    }
    ui.apagado.hidden = true;
    mantenerEncendida();
    ui.estado.textContent = 'Cargando modelo…';
    let ia;
    try {
      ia = await window.UniIA.cargar(config.modeloTm);
    } catch (e) {
      ui.estado.textContent = 'IA no disponible';
      ui.nota.textContent = e.message || 'No se pudo cargar el modelo de reconocimiento.';
      return;
    }
    ui.fuente.textContent = ia.fuente;
    ui.estado.textContent = 'Reconociendo';
    window.UniIA.vigilar(video, ia, { umbral: config.umbral, margen: config.margen, estableMs: config.estableMs }, publicar);
  });

  function publicar(r) {
    if (!config.tipos[r.tipo]) r = { ...r, tipo: 'DESCONOCIDO', estable: false, baja: true };
    const etiqueta = r.tipo === 'DESCONOCIDO' ? 'Sin vehículo' : (config.tipos[r.tipo] || r.tipo);
    ui.clase.textContent = etiqueta;
    ui.barra.style.width = Math.round((r.confianza || 0) * 100) + '%';
    ui.ia.dataset.estado = r.estable ? 'estable' : (r.baja ? 'baja' : '');

    // Envía cuando cambia la clase y, mientras se mantenga, cada segundo para que la detección siga vigente.
    // Solo una clase estable y confiable se publica como tal; lo demás va como DESCONOCIDO (el servidor decide).
    const tipo = r.estable ? r.tipo : 'DESCONOCIDO';
    const ahora = Date.now();
    if (tipo !== ultimoTipo || ahora - ultimoEnvio > 1000) {
      ultimoTipo = tipo;
      ultimoEnvio = ahora;
      post('api/camara/deteccion', { puesto: ui.puesto.value, tipo, confianza: (r.confianza || 0).toFixed(4), segunda: (r.segunda || 0).toFixed(4) })
        .then((d) => { ui.estado.textContent = d.ok ? 'Reconociendo · enviado' : (d.error || 'Error al enviar'); })
        .catch(() => { ui.estado.textContent = 'Sin conexión'; });
    }
  }

  // Evita que la pantalla se apague durante la demostración.
  async function mantenerEncendida() {
    try {
      if ('wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen');
    } catch (e) { /* opcional */ }
  }
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && wakeLock !== null) mantenerEncendida();
  });
})();
