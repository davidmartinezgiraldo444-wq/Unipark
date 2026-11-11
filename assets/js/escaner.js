// UniPark · Punto de acceso (entrada y salida).
//
// 1. La persona escanea su carné o cédula → el servidor valida la identidad y abre un evento.
// 2. La cámara clasifica el vehículo (IA en este celular o en el celular de cámara del puesto).
// 3. El servidor decide: si la clasificación es confiable registra la entrada o verifica la salida;
//    si no, el evento pasa al PANEL DEL PORTERO. Esta pantalla nunca permite elegir el tipo de vehículo.
(function () {
  'use strict';

  const { config, encender, apagar, crearLector, post, get } = window.UniCam;
  const $ = (s) => document.querySelector(s);

  const video = $('[data-video]');
  const visor = $('[data-visor]');
  const ui = {
    estado: $('[data-visor-estado]'), texto: $('[data-visor-texto]'), apagado: $('[data-visor-apagado]'),
    pasoBuscar: $('[data-paso="buscar"]'), pasoEvento: $('[data-paso="evento"]'),
    evTipo: $('[data-ev-tipo]'), chip: $('[data-ev-chip]'),
    nombre: $('[data-p-nombre]'), categoria: $('[data-p-categoria]'), documento: $('[data-p-documento]'),
    aviso: $('[data-p-aviso]'), ia: $('[data-ia]'), iaFuente: $('[data-ia-fuente]'), iaClase: $('[data-ia-clase]'),
    iaConf: $('[data-ia-confianza]'), iaBarra: $('[data-ia-barra]'), iaNota: $('[data-ia-nota]'),
    modo: $('[data-modo]'), consejo: $('[data-consejo]'),
    veredicto: $('[data-veredicto]'), vTitulo: $('[data-v-titulo]'), vTexto: $('[data-v-texto]'), vExtra: $('[data-v-extra]'),
    vDetalle: $('[data-v-detalle]'), vResolver: $('[data-v-resolver]')
  };

  const estado = {
    fase: 'apagada', lector: null, ia: null, vigilancia: null, camara: null, camaraLista: false,
    evento: null, ultimoCodigo: '', ultimoEn: 0, mejorVisto: null, enviando: false
  };

  const modo = () => { const [m, p] = ui.modo.value.split(':'); return { local: m === 'uno', puesto: Number(p || 1) }; };

  // ------------------------------------------------------------------ Cámara

  $('[data-encender]').addEventListener('click', () => iniciarCamara());
  const selCamara = $('[data-camara]');
  selCamara.addEventListener('change', () => iniciarCamara(selCamara.value));

  async function iniciarCamara(idCamara) {
    ui.estado.textContent = 'Encendiendo cámara…';
    try {
      estado.camara = await encender(video, idCamara);
      await pintarCamaras(estado.camara.deviceId);
    } catch (e) {
      ui.estado.textContent = 'Sin cámara';
      ui.consejo.textContent = e.name === 'NotAllowedError'
        ? 'Permiso de cámara denegado. Actívalo en el navegador para escanear.'
        : (e.message || 'No se pudo encender la cámara.');
      ui.consejo.classList.add('text-err');
      return;
    }
    estado.camaraLista = true;
    ui.apagado.hidden = true;
    try {
      estado.lector = await crearLector();
      ui.estado.textContent = 'Lector: ' + estado.lector.nombre + ' · ' + (estado.camara.ancho || '?') + '×' + (estado.camara.alto || '?')
        + (estado.camara.enfoque === true ? ' · autoenfoque' : estado.camara.enfoque === false ? ' · foco fijo' : '');
      if (estado.camara.enfoque === false) {
        ui.consejo.dataset.fijo = '1';
        ui.consejo.textContent = 'Esta cámara no tiene autoenfoque: de cerca el código sale borroso. Usa la cámara de un celular, «Tomar foto del carné», un lector USB/Bluetooth o escribe el número.';
      }
    } catch (e) {
      ui.estado.textContent = 'Lector no disponible';
    }
    faseCarnet();
  }

  async function pintarCamaras(actual) {
    const camaras = await window.UniCam.listarCamaras().catch(() => []);
    selCamara.hidden = camaras.length < 2;
    selCamara.replaceChildren(...camaras.map((c, i) => {
      const o = document.createElement('option');
      o.value = c.deviceId;
      o.textContent = c.label || 'Cámara ' + (i + 1);
      o.selected = c.deviceId === actual;
      return o;
    }));
  }

  // ------------------------------------------------------------------ 1. Identidad

  function faseCarnet() {
    detenerVehiculo();
    estado.fase = 'carnet';
    estado.evento = null;
    visor.dataset.fase = 'carnet';
    ui.texto.textContent = 'Escanea el código de barras de tu carné o cédula';
    ui.pasoBuscar.hidden = false;
    ui.pasoEvento.hidden = true;
    if (estado.camaraLista && estado.lector) leerCodigos();
  }

  let bucle = 0;
  async function leerCodigos() {
    const propio = ++bucle;
    const inicio = Date.now();
    while (estado.fase === 'carnet' && propio === bucle) {
      ui.consejo.classList.toggle('text-err', ui.consejo.dataset.fijo === '1' || Date.now() - inicio > 8000);
      const r = await estado.lector.leer(video).catch(() => null);
      if (r && r.texto && estado.fase === 'carnet' && propio === bucle) {
        const ahora = Date.now();
        if (r.texto !== estado.ultimoCodigo || ahora - estado.ultimoEn > 6000) {
          estado.ultimoCodigo = r.texto;
          estado.ultimoEn = ahora;
          vibrar(40);
          lectura('Código detectado' + (r.formato ? ' · ' + String(r.formato).replace(/_/g, ' ') : '') + '. Validando…', 'info');
          window.UniCam.diag('formato', (r.formato ? String(r.formato).replace(/_/g, ' ') : 'formato no informado') + ' · ' + r.texto.length + ' caracteres · ' + new Date().toLocaleTimeString());
          await identificar(r.texto);
          continue;
        }
      }
      await esperar(90);
    }
  }

  $('[data-manual]').addEventListener('submit', (e) => {
    e.preventDefault();
    const campo = e.target.elements.codigo;
    const valor = campo.value.trim();
    if (valor) window.UniCam.diag('formato', 'Digitación, OCR o lector USB/Bluetooth · ' + valor.length + ' caracteres · ' + new Date().toLocaleTimeString());
    if (valor) identificar(valor).then(() => { campo.value = ''; });
  });

  $('[data-foto]').addEventListener('change', async (e) => {
    const archivo = e.target.files && e.target.files[0];
    e.target.value = '';
    if (!archivo) return;
    ui.estado.textContent = 'Leyendo la foto…';
    try {
      if (!estado.lector) estado.lector = await crearLector();
      const r = await estado.lector.leerImagen(await window.UniCam.cargarFoto(archivo));
      if (r && r.texto) { vibrar(40); await identificar(r.texto); }
      else mostrarVeredicto('info', 'No se encontró el código', 'Toma la foto de cerca, con el código horizontal, nítido y sin reflejos.', '', 3500);
    } catch (err) {
      mostrarVeredicto('info', 'No se pudo leer la foto', err.message || String(err), '', 3500);
    }
  });

  // ------------------------------------------------------------------ Estado de la lectura

  const lecturaEl = $('[data-lectura]');
  /** Línea de estado visible: código detectado, lectura fallida, persona encontrada… (tono: ok | err | info). */
  function lectura(texto, tono) {
    if (!lecturaEl) return;
    lecturaEl.textContent = texto;
    lecturaEl.dataset.tono = tono || 'info';
    lecturaEl.hidden = !texto;
  }

  // ------------------------------------------------------------------ OCR asistido (texto impreso)
  // Para documentos sin un código legible. Tesseract.js (cargado SOLO al pulsar el botón, ~6 MB la primera vez)
  // lee la imagen EN ESTE EQUIPO; la imagen no se envía. El número leído queda en el campo manual para que el
  // portero lo CONFIRME: el OCR nunca identifica a nadie por sí solo.
  const OCR = {
    script: 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js',
    worker: 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/worker.min.js',
    core: 'https://cdn.jsdelivr.net/npm/tesseract.js-core@5.1.1',
    idioma: 'https://cdn.jsdelivr.net/npm/@tesseract.js-data/spa@1.0.0/4.0.0_best_int'
  };
  let ocrWorker = null;
  const botonOcr = $('[data-ocr]');
  const fotoOcr = $('[data-ocr-foto]');

  async function leerTextoImpreso(imagen) {
    botonOcr.disabled = true;
    try {
      if (!ocrWorker) {
        lectura('Cargando el lector de texto (OCR)… la primera vez tarda unos segundos.', 'info');
        await window.UniCam.cargarScript(OCR.script);
        ocrWorker = await window.Tesseract.createWorker('spa', 1, { workerPath: OCR.worker, corePath: OCR.core, langPath: OCR.idioma });
      }
      lectura('Leyendo el texto del documento…', 'info');
      const { data } = await ocrWorker.recognize(imagen);
      // Candidato: la secuencia de 6 a 10 dígitos más larga (con puntos o espacios de miles).
      const candidatos = (String(data.text || '').match(/\d[\d.\s]{4,14}\d/g) || [])
        .map((t) => t.replace(/[.\s]/g, '')).filter((t) => t.length >= 6 && t.length <= 10)
        .sort((a, b) => b.length - a.length);
      const campo = $('[data-manual]').elements.codigo;
      if (!candidatos.length) {
        lectura('OCR: no se encontró un número de documento. Reintenta con más luz o escríbelo.', 'err');
        campo.focus();
        return;
      }
      campo.value = candidatos[0];
      campo.focus();
      lectura('OCR leyó ' + candidatos[0] + '. Verifica que coincida con el documento y pulsa «Continuar».', 'ok');
    } catch (e) {
      lectura('No se pudo usar el OCR: ' + (e.message || e) + ' Escribe el número.', 'err');
    } finally {
      botonOcr.disabled = false;
    }
  }

  botonOcr.addEventListener('click', () => {
    // Con la cámara encendida se lee el cuadro actual; si no, se toma una foto.
    if (estado.camaraLista && video.videoWidth) {
      const c = document.createElement('canvas');
      c.width = video.videoWidth; c.height = video.videoHeight;
      c.getContext('2d').drawImage(video, 0, 0);
      return leerTextoImpreso(c);
    }
    fotoOcr.click();
  });
  fotoOcr.addEventListener('change', async (e) => {
    const archivo = e.target.files && e.target.files[0];
    e.target.value = '';
    if (archivo) leerTextoImpreso(await window.UniCam.cargarFoto(archivo));
  });

  /**
   * Interpreta el texto leído EN ESTE EQUIPO. De la cédula (PDF417) solo se envían el número y el nombre:
   * la fecha de nacimiento, el RH y los demás datos no salen del celular.
   */
  function interpretarDocumento(texto) {
    const limpio = String(texto).replace(/\0/g, ' ').trim();
    const compacto = limpio.replace(/[\s.]/g, '').toUpperCase();
    if (/^[0-9A-Z-]{5,20}$/.test(compacto)) return { codigo: compacto, nombre: '' };
    if (limpio.length >= 150) {
      const numero = limpio.substr(48, 10).replace(/^0+/, '');
      if (/^\d{5,10}$/.test(numero)) {
        const parte = (i) => limpio.substr(i, 23).replace(/[^A-Za-zÁÉÍÓÚÑáéíóúñ ]/g, ' ').trim();
        const nombre = [parte(104), parte(127), parte(58), parte(81)].join(' ').replace(/\s+/g, ' ').trim().toLowerCase()
          .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
        return { codigo: numero, nombre };
      }
    }
    const mrz = compacto.match(/ICCOL0*(\d{5,10})/);
    if (mrz) return { codigo: mrz[1], nombre: '' };
    const numeros = (limpio.match(/\d{6,10}/g) || []).sort((a, b) => b.length - a.length);
    return { codigo: numeros.length ? (numeros[0].replace(/^0+/, '') || numeros[0]) : '', nombre: '' };
  }

  async function identificar(texto) {
    const leido = interpretarDocumento(texto);
    if (!leido.codigo) {
      lectura('Lectura fallida: el código no contiene un documento. Reintenta, usa el OCR o escribe el número.', 'err');
      return mostrarVeredicto('info', 'Código no reconocido', 'No se encontró un documento en el código. Escanea de nuevo o escribe el número.', '', 3000);
    }
    estado.fase = 'identificando';
    ui.estado.textContent = 'Validando identidad…';
    lectura('Validando identidad en UniPark…', 'info');
    const ev = await post('api/acceso/identificar', { codigo: leido.codigo, nombre: leido.nombre, puesto: modo().puesto });
    ui.estado.textContent = estado.lector ? 'Lector: ' + estado.lector.nombre : 'Listo';
    if (ev && ev.ok) {
      lectura(ev.motivo === 'IDENTIDAD_NO_REGISTRADA' ? 'Persona no encontrada: no está registrada. Portería decide el ingreso como visitante.'
        : ev.resultado === 'NO_AUTORIZADA' ? 'Persona encontrada, pero no está habilitada.'
          : 'Persona encontrada: ' + (ev.persona ? ev.persona.nombre : '') + '. Identificando el vehículo…', ev.motivo === 'IDENTIDAD_NO_REGISTRADA' || ev.resultado === 'NO_AUTORIZADA' ? 'err' : 'ok');
    } else {
      lectura('Error de formato o de comunicación: ' + ((ev && ev.error) || 'reintenta.'), 'err');
    }
    atenderEvento(ev);
  }

  // ------------------------------------------------------------------ Estados del evento

  function atenderEvento(ev) {
    if (!ev || !ev.ok) {
      return mostrarVeredicto('err', 'No se pudo continuar', (ev && ev.error) || 'Error de comunicación.', '', 4000);
    }
    estado.evento = ev;
    pintarEvento(ev);
    if (ev.estado === 'EN_CAPTURA') return faseVehiculo(ev);
    if (ev.estado === 'PENDIENTE_PORTERO') return esperarPortero(ev);
    return mostrarResultado(ev);
  }

  function pintarEvento(ev) {
    ui.pasoBuscar.hidden = true;
    ui.pasoEvento.hidden = false;
    ui.evTipo.textContent = ev.tipo === 'SALIDA' ? 'Salida' : 'Ingreso';
    const p = ev.persona || {};
    ui.nombre.textContent = p.nombre || '';
    ui.categoria.textContent = p.categoria_label || '';
    ui.categoria.dataset.cat = p.categoria || '';
    ui.documento.textContent = p.documento || '';
    ui.chip.textContent = ev.motivo === 'IDENTIDAD_NO_REGISTRADA' ? 'Sin registro' : 'Identidad validada';
    ui.aviso.hidden = !(ev.tipo === 'SALIDA' && ev.visita);
    if (ev.tipo === 'SALIDA' && ev.visita) {
      ui.aviso.dataset.tono = 'warn';
      ui.aviso.textContent = 'Ingresó en ' + ev.visita.tipo.toLowerCase() + ' a las ' + ev.visita.entrada + '. Verificando el vehículo de salida.';
    }
  }

  // ------------------------------------------------------------------ 2. Vehículo

  async function faseVehiculo(ev) {
    estado.fase = 'vehiculo';
    estado.mejorVisto = null;
    visor.dataset.fase = 'vehiculo';
    ui.texto.textContent = 'Ubica el vehículo de lado frente a la cámara';
    pintarIA({ tipo: 'DESCONOCIDO', confianza: 0, baja: true });
    const limite = Date.now() + (config.esperaS || 8) * 1000;

    if (!modo().local) {
      ui.iaFuente.textContent = 'Cámara del puesto';
      while (estado.fase === 'vehiculo' && estado.evento && estado.evento.id === ev.id) {
        const final = Date.now() >= limite;
        const r = await post('api/acceso/' + ev.id + '/clasificacion', { fuente: 'PUESTO', final: final ? '1' : '0' });
        if (!r.ok || !r.esperando) return atenderEvento(r);
        ui.iaNota.textContent = 'Esperando la detección del celular de cámara…';
        await esperar(500);
      }
      return;
    }

    if (!estado.camaraLista) {
      ui.iaNota.textContent = 'Cámara apagada: el portero clasificará el vehículo.';
      return enviarClasificacion(ev, { tipo: 'DESCONOCIDO', confianza: 0, segunda: 0 }, true);
    }
    try {
      if (!estado.ia) {
        ui.iaClase.textContent = 'Cargando modelo…';
        const t0 = performance.now();
        window.UniCam.diag('modelo', 'Cargando…');
        estado.ia = await window.UniIA.cargar(config.modeloTm);
        window.UniCam.diag('modelo', estado.ia.fuente + ' · cargó en ' + ((performance.now() - t0) / 1000).toFixed(1) + ' s');
      }
    } catch (e) {
      ui.iaClase.textContent = 'IA no disponible';
      ui.iaNota.textContent = 'No se pudo cargar el modelo. El portero clasificará el vehículo.';
      return enviarClasificacion(ev, { tipo: 'DESCONOCIDO', confianza: 0, segunda: 0 }, true);
    }
    if (estado.fase !== 'vehiculo') return;
    ui.iaFuente.textContent = estado.ia.fuente;
    estado.vigilancia = window.UniIA.vigilar(video, estado.ia, { umbral: config.umbral, margen: config.margen, estableMs: config.estableMs }, (r) => {
      if (estado.fase !== 'vehiculo') return;
      const tipo = config.tipos[r.tipo] ? r.tipo : 'DESCONOCIDO'; // Tipos no habilitados: desconocido.
      const lectura = { ...r, tipo, estable: r.estable && tipo !== 'DESCONOCIDO' };
      pintarIA(lectura);
      window.UniCam.diag('ia', (config.tipos[tipo] || 'Desconocido') + ' · ' + Math.round((r.confianza || 0) * 100) + ' % (segunda ' + Math.round((r.segunda || 0) * 100)
        + ' %) · ' + (lectura.estable ? 'estable: se envía' : r.baja ? 'baja confianza' : 'estabilizando'));
      if (r.ms !== undefined) window.UniCam.diag('ms', r.ms + ' ms por cuadro (umbral ' + Math.round(config.umbral * 100) + ' %, margen ' + Math.round(config.margen * 100) + ' %)');
      if (tipo !== 'DESCONOCIDO' && (!estado.mejorVisto || r.confianza > estado.mejorVisto.confianza)) estado.mejorVisto = { tipo, confianza: r.confianza, segunda: r.segunda || 0 };
      if (lectura.estable) return enviarClasificacion(ev, { tipo, confianza: r.confianza, segunda: r.segunda || 0 }, false);
      if (Date.now() >= limite) {
        // Sin una detección confiable a tiempo: se envía lo mejor visto y el SERVIDOR lo pasa al portero.
        enviarClasificacion(ev, estado.mejorVisto || { tipo: 'DESCONOCIDO', confianza: 0, segunda: 0 }, true);
      }
    });
  }

  async function enviarClasificacion(ev, lectura, final) {
    if (estado.enviando || estado.fase !== 'vehiculo') return;
    estado.enviando = true;
    detenerVehiculo();
    estado.fase = 'enviando';
    const datos = { fuente: 'CELULAR', tipo: lectura.tipo, confianza: (lectura.confianza || 0).toFixed(4), segunda: (lectura.segunda || 0).toFixed(4), final: final ? '1' : '0' };
    if (final && config.capturaPortero) datos.captura = capturar();
    const r = await post('api/acceso/' + ev.id + '/clasificacion', datos);
    estado.enviando = false;
    atenderEvento(r);
  }

  function capturar() {
    try {
      const c = document.createElement('canvas');
      const w = video.videoWidth || 640;
      c.width = 480; c.height = Math.round(480 * (video.videoHeight || 480) / w);
      c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
      return c.toDataURL('image/jpeg', 0.6);
    } catch (e) { return ''; }
  }

  function pintarIA(r) {
    ui.iaClase.textContent = r.tipo === 'DESCONOCIDO' ? 'Desconocido' : (config.tipos[r.tipo] || r.tipo);
    ui.iaConf.textContent = r.confianza ? Math.round(r.confianza * 100) + ' %' : '';
    ui.iaBarra.style.width = Math.round((r.confianza || 0) * 100) + '%';
    ui.ia.dataset.estado = r.estable ? 'estable' : (r.baja ? 'baja' : '');
  }

  // ------------------------------------------------------------------ 3. Portero y resultado

  async function esperarPortero(ev) {
    estado.fase = 'portero';
    detenerVehiculo();
    const texto = ev.motivo === 'IDENTIDAD_NO_REGISTRADA'
      ? 'Tu identidad no está registrada. Un portero decidirá tu ingreso.'
      : ev.motivo === 'VEHICULO_NO_COINCIDE'
        ? 'El vehículo no coincide con el de tu ingreso. Un portero lo está verificando.'
        : ev.por_confirmar
          ? 'Vehículo detectado: ' + ev.por_confirmar + '. Un portero lo está confirmando.'
          : 'No se pudo identificar el vehículo. Un portero lo está verificando.';
    mostrarVeredicto('info', 'Espera un momento', texto, 'Verificación de Portería', 0);
    while (estado.fase === 'portero' && estado.evento && estado.evento.id === ev.id) {
      await esperar(2000);
      const r = await get('api/acceso/' + ev.id);
      if (r.ok && r.estado !== 'PENDIENTE_PORTERO') return mostrarResultado(r);
    }
  }

  function mostrarResultado(ev) {
    estado.fase = 'resultado';
    const r = ev.resultado || ev.estado;
    // El detalle (persona enmascarada, vehículo, horas, tiempo, cobro y pago) lo calcula el servidor.
    const det = { detalle: ev.detalle || [], deuda: !!ev.deuda };
    const extra = !det.detalle.length && ev.vehiculo ? ev.vehiculo + (ev.metodo === 'MANUAL' ? ' · confirmado por Portería' : ' · IA en el dispositivo') : '';
    if (r === 'ENTRADA_REGISTRADA' || r === 'VISITANTE_ADMITIDO') { vibrar([60, 40, 60]); return mostrarVeredicto('ok', 'Ingreso registrado', ev.mensaje, extra, 7000, det); }
    if (r === 'SALIDA_AUTORIZADA') { vibrar([60, 40, 60]); return mostrarVeredicto('ok', 'Salida autorizada', ev.mensaje, extra, 7000, det); }
    if (r === 'SALIDA_INCIDENCIA') return mostrarVeredicto('info', 'Incidencia de cobro', ev.mensaje, 'Portería o Caja la resolverán', 10000, det);
    if (ev.estado === 'EXPIRADO') return mostrarVeredicto('info', 'Sin resolver', ev.mensaje, '', 4000);
    vibrar([300]);
    return mostrarVeredicto('err', r === 'SALIDA_DENEGADA' ? 'Salida denegada' : 'No autorizado', ev.mensaje, extra, r === 'SALIDA_DENEGADA' ? 12000 : 6000, det);
  }

  // ------------------------------------------------------------------ Común

  let cierre = null;
  function mostrarVeredicto(tono, titulo, texto, extra, ms, det) {
    ui.veredicto.dataset.tono = tono;
    ui.vTitulo.textContent = titulo;
    ui.vTexto.textContent = texto || '';
    ui.vExtra.textContent = extra || '';
    const filas = (det && det.detalle) || [];
    ui.vDetalle.replaceChildren(...filas.flatMap(([k, v]) => {
      const dt = document.createElement('dt'); dt.textContent = k;
      const dd = document.createElement('dd'); dd.textContent = v;
      return [dt, dd];
    }));
    ui.vDetalle.hidden = !filas.length;
    ui.vResolver.hidden = !(det && det.deuda);
    ui.veredicto.hidden = false;
    clearTimeout(cierre);
    if (ms) cierre = setTimeout(cerrarVeredicto, ms);
  }
  function cerrarVeredicto() {
    clearTimeout(cierre);
    ui.veredicto.hidden = true;
    faseCarnet();
  }
  $('[data-v-cerrar]').addEventListener('click', cerrarVeredicto);

  function detenerVehiculo() {
    if (estado.vigilancia) estado.vigilancia.detener();
    estado.vigilancia = null;
  }

  const esperar = (ms) => new Promise((r) => setTimeout(r, ms));
  const vibrar = (p) => { try { navigator.vibrate && navigator.vibrate(p); } catch (e) { /* opcional */ } };

  try {
    const guardado = localStorage.getItem('unipark-punto-acceso');
    if (guardado && [...ui.modo.options].some((o) => o.value === guardado)) ui.modo.value = guardado;
  } catch (e) { /* sin almacenamiento */ }
  ui.modo.addEventListener('change', () => { try { localStorage.setItem('unipark-punto-acceso', ui.modo.value); } catch (e) { /* nada */ } });

  window.addEventListener('pagehide', () => apagar(video));
})();
