// UniPark · Cámara del celular y lectura de códigos de barras.
// Usa el lector nativo del navegador (BarcodeDetector, Chrome en Android) y, si no existe
// (Safari en iPhone, Edge/Chrome en Windows, Firefox), la biblioteca ZXing. Las imágenes nunca salen del equipo.
(function () {
  'use strict';

  const ZXING_URL = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';
  // Formatos habituales en carnets (Code 128, Code 39, ITF, EAN, Codabar, QR) y en la cédula (PDF417).
  const FORMATOS = ['code_128', 'code_39', 'code_93', 'codabar', 'itf', 'ean_13', 'ean_8', 'upc_a', 'pdf417', 'qr_code', 'data_matrix'];
  const CLAVE_CAMARA = 'unipark-camara';

  const config = JSON.parse(document.getElementById('unipark-config')?.textContent || '{}');

  // Integridad (SRI): el navegador rechaza el archivo si el CDN entrega algo distinto de lo auditado.
  const SRI = {
    "https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js": "sha384-BzBxP10ZE72aitqj5UMmUsbKFliP/DZqA8Wq+BNNhlIJDGoEd1tpkMYXOg9+n6sB",
    "https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@1.3.1/dist/tf.min.js": "sha384-LBtUZzDp17Y7UgB/KFromf1LnY7o5ls8Oxm4vEAoTM0U+JzaadctX2/buQiNHyQU",
    "https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.5/dist/teachablemachine-image.min.js": "sha384-4Q4fzaRr/bPyBiOertaWuZ8Zn987vAWqtNLbL6h+tf/Y+flpirtyqFTd7q9ELxjS",
    "https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js": "sha384-vE8hbVJ4lezako5rlvE7bY0BVzWlFhZncPlckrqNwcUQpVtgbENTgZ8TBbnPjZre",
    "https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js": "sha384-7qLdgfEQyO9ZQi9ArRHigK+IBto4XPk468jAqc+fnsXaZIcMAhQeLwzggRK7aESl",
    "https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js": "sha384-Kk5SjBOKprEnGfyBWfD2zROFd1Cu8kwOXxG2GIhYPcoDL2rBJS9P8Ud1ZMy4412a",
    "https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js": "sha384-GJqSu7vueQ9qN0E9yLPb3Wtpd7OrgK8KmYzC8T1IysG1bcvxvIO4qtYR/D3A991F"
};

  function cargarScript(url) {
    return new Promise((resolve, reject) => {
      if (document.querySelector('script[src="' + url + '"]')) return resolve();
      const s = document.createElement('script');
      s.src = url;
      s.async = true;
      if (SRI[url]) { s.integrity = SRI[url]; s.crossOrigin = 'anonymous'; }
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('No se pudo cargar ' + url + '. Revisa la conexión a internet.'));
      document.head.appendChild(s);
    });
  }

  function camaraGuardada() {
    try { return localStorage.getItem(CLAVE_CAMARA) || ''; } catch (e) { return ''; }
  }

  /** Cámaras de video disponibles (los nombres solo aparecen después de dar permiso). */
  async function listarCamaras() {
    if (!navigator.mediaDevices?.enumerateDevices) return [];
    const dispositivos = await navigator.mediaDevices.enumerateDevices();
    return dispositivos.filter((d) => d.kind === 'videoinput');
  }

  /**
   * Enciende la cámara. Prefiere la trasera del celular; en un computador usa la elegida o la única.
   * Pide alta resolución y enfoque continuo cuando el equipo lo permite: un código de barras
   * borroso no se puede leer.
   */
  async function encender(video, idCamara) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error('Este navegador no permite usar la cámara. En el celular, abre el sitio con https://');
    }
    apagar(video);
    const id = idCamara ?? camaraGuardada();
    const base = { width: { ideal: 1920 }, height: { ideal: 1080 } };
    let stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: id ? { ...base, deviceId: { exact: id } } : { ...base, facingMode: { ideal: 'environment' } }
      });
    } catch (e) {
      if (!id || e.name === 'NotAllowedError') throw e;
      stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { ...base, facingMode: { ideal: 'environment' } } });
    }
    video.srcObject = stream;
    await video.play();

    const pista = stream.getVideoTracks()[0];
    const ajustes = pista.getSettings ? pista.getSettings() : {};
    const capacidades = pista.getCapabilities ? pista.getCapabilities() : {};
    const avanzadas = [];
    if (Array.isArray(capacidades.focusMode) && capacidades.focusMode.includes('continuous')) avanzadas.push({ focusMode: 'continuous' });
    if (avanzadas.length) { try { await pista.applyConstraints({ advanced: avanzadas }); } catch (e) { /* opcional */ } }

    // Una cámara frontal o una webcam se muestran en espejo para apuntar con naturalidad.
    // La lectura usa la imagen original, así que el espejo no afecta el código.
    const frontal = ajustes.facingMode === 'user' || (!ajustes.facingMode && !/back|rear|trasera|environment/i.test(pista.label));
    video.classList.toggle('espejo', frontal);

    try { if (ajustes.deviceId) localStorage.setItem(CLAVE_CAMARA, ajustes.deviceId); } catch (e) { /* sin almacenamiento */ }
    // enfoque: la cámara permite enfoque automático/continuo. Las webcams de portátil suelen tener foco fijo
    // y no enfocan a menos de ~40 cm: el código de barras sale borroso.
    // null = el navegador no lo informa (por ejemplo Safari en iPhone, que enfoca solo): no se advierte.
    const enfoque = Array.isArray(capacidades.focusMode)
      ? capacidades.focusMode.some((m) => m === 'continuous' || m === 'single-shot')
      : (frontal ? false : null);
    const ancho = ajustes.width || video.videoWidth;
    const alto = ajustes.height || video.videoHeight;
    diag('camara', (pista.label || 'Cámara sin nombre') + ' · ' + ancho + '×' + alto + ' · ' + (frontal ? 'frontal' : 'trasera')
      + (enfoque === true ? ' · autoenfoque' : enfoque === false ? ' · foco fijo' : ' · enfoque no informado'));
    return { stream, deviceId: ajustes.deviceId || '', ancho, alto, frontal, enfoque };
  }

  function apagar(video) {
    const stream = video.srcObject;
    if (stream) stream.getTracks().forEach((t) => t.stop());
    video.srcObject = null;
  }

  /**
   * Lector de códigos. leer(video) analiza un cuadro del video; leerImagen(img) una foto completa.
   * Con ZXing se alternan estrategias en cada cuadro (recorte central o cuadro completo, dos binarizadores
   * y dos escalas): la escala reducida tolera mejor un leve desenfoque.
   */
  async function crearLector() {
    if ('BarcodeDetector' in window) {
      try {
        const soportados = await window.BarcodeDetector.getSupportedFormats();
        const formatos = FORMATOS.filter((f) => soportados.includes(f));
        if (formatos.length) {
          const detector = new window.BarcodeDetector({ formats: formatos });
          const detectar = async (fuente) => {
            const r = await detector.detect(fuente);
            return r.length ? { texto: r[0].rawValue, formato: r[0].format } : null;
          };
          diag('lector', 'BarcodeDetector (nativo del navegador) · ' + formatos.length + ' formatos: ' + formatos.join(', '));
          return { nombre: 'Lector nativo', leer: detectar, leerImagen: detectar };
        }
      } catch (e) { /* se usa ZXing */ }
    }

    await cargarScript(ZXING_URL);
    const Z = window.ZXing;
    const hints = new Map();
    hints.set(Z.DecodeHintType.POSSIBLE_FORMATS, [
      Z.BarcodeFormat.CODE_128, Z.BarcodeFormat.CODE_39, Z.BarcodeFormat.CODE_93, Z.BarcodeFormat.CODABAR,
      Z.BarcodeFormat.ITF, Z.BarcodeFormat.EAN_13, Z.BarcodeFormat.EAN_8, Z.BarcodeFormat.UPC_A,
      Z.BarcodeFormat.PDF_417, Z.BarcodeFormat.QR_CODE, Z.BarcodeFormat.DATA_MATRIX
    ]);
    hints.set(Z.DecodeHintType.TRY_HARDER, true);
    const lector = new Z.MultiFormatReader();
    lector.setHints(hints);
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    // [recorte, escala máxima del ancho, binarizador]
    const ESTRATEGIAS = [
      ['centro', 1280, 'hibrido'], ['centro', 640, 'histograma'], ['completo', 960, 'hibrido'],
      ['centro', 800, 'hibrido'], ['completo', 640, 'histograma'], ['centro', 1280, 'histograma']
    ];
    let turno = 0;

    function decodificar(fuente, w, h, recorte, maxAncho, binarizador, rotar) {
      const cw = recorte === 'centro' ? Math.round(w * 0.9) : w;
      const ch = recorte === 'centro' ? Math.round(h * 0.7) : h;
      const escala = Math.min(1, maxAncho / cw);
      const ancho = Math.round(cw * escala);
      const alto = Math.round(ch * escala);
      canvas.width = rotar ? alto : ancho;
      canvas.height = rotar ? ancho : alto;
      ctx.save();
      if (rotar) { ctx.translate(alto, 0); ctx.rotate(Math.PI / 2); }
      ctx.drawImage(fuente, (w - cw) / 2, (h - ch) / 2, cw, ch, 0, 0, ancho, alto);
      ctx.restore();
      try {
        const lum = new Z.HTMLCanvasElementLuminanceSource(canvas);
        const bin = binarizador === 'hibrido' ? new Z.HybridBinarizer(lum) : new Z.GlobalHistogramBinarizer(lum);
        const r = lector.decode(new Z.BinaryBitmap(bin));
        return { texto: r.getText(), formato: String(Z.BarcodeFormat[r.getBarcodeFormat()] || '') };
      } catch (e) {
        return null; // Ningún código en este intento.
      } finally {
        lector.reset();
      }
    }

    diag('lector', 'ZXing 0.21.3 (biblioteca; el navegador no trae lector nativo)');
    return {
      nombre: 'ZXing',
      async leer(video) {
        const w = video.videoWidth;
        const h = video.videoHeight;
        if (!w || !h) return null;
        const [recorte, maxAncho, bin] = ESTRATEGIAS[turno++ % ESTRATEGIAS.length];
        return decodificar(video, w, h, recorte, maxAncho, bin, false);
      },
      /** Foto del carnet: prueba todas las estrategias y también girada 90° (carnet vertical). */
      async leerImagen(img) {
        const w = img.naturalWidth || img.width;
        const h = img.naturalHeight || img.height;
        for (const rotar of [false, true]) {
          for (const [recorte, maxAncho, bin] of ESTRATEGIAS) {
            const r = decodificar(img, w, h, recorte, Math.max(maxAncho, 1600), bin, rotar);
            if (r) return r;
          }
        }
        return null;
      }
    };
  }

  /** Abre un archivo de imagen (foto tomada con la cámara nativa) como <img> decodificado. */
  async function cargarFoto(archivo) {
    const url = URL.createObjectURL(archivo);
    const img = new Image();
    img.src = url;
    try { await img.decode(); } finally { setTimeout(() => URL.revokeObjectURL(url), 1000); }
    return img;
  }

  async function post(ruta, datos) {
    const cuerpo = new URLSearchParams(datos);
    cuerpo.set('_csrf', config.csrf || '');
    const r = await fetch(config.base + ruta, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      credentials: 'same-origin',
      body: cuerpo
    });
    return leerRespuesta(r);
  }

  async function get(ruta) {
    const r = await fetch(config.base + ruta, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    return leerRespuesta(r);
  }

  async function leerRespuesta(r) {
    let datos = null;
    try { datos = await r.json(); } catch (e) { /* no es JSON */ }
    if (r.status === 401) {
      window.location.href = config.base + 'login?expirado=1';
    }
    if (!datos) datos = { ok: false, error: 'El servidor no respondió como se esperaba (' + r.status + ').' };
    return datos;
  }

  /** Dato para el modo diagnóstico de la demo (assets/js/diagnostico.js). Solo en pantalla: nunca se envía. */
  function diag(clave, valor) {
    try { window.dispatchEvent(new CustomEvent('unipark:diag', { detail: { clave, valor } })); } catch (e) { /* sin diagnóstico */ }
  }

  window.UniCam = { config, cargarScript, encender, apagar, listarCamaras, crearLector, cargarFoto, post, get, diag };
})();
