// UniPark · Modo diagnóstico del escáner (SOLO en la copia de demostración).
// Muestra lo que el navegador del celular puede hacer y lo que está pasando: contexto seguro, cámara, lector de
// códigos, formato leído, modelo de IA, confianza y tiempo de inferencia. Todo se calcula y se queda en esta pantalla:
// nada se envía al servidor ni se guarda. El contenido de los códigos nunca se muestra (solo formato y longitud).
(function () {
  'use strict';
  const panel = document.querySelector('[data-diagnostico]');
  if (!panel) return;
  const poner = (clave, valor, tono) => {
    const dd = panel.querySelector('[data-diag="' + clave + '"]');
    if (!dd) return;
    dd.textContent = valor;
    if (tono) dd.dataset.tono = tono; else delete dd.dataset.tono;
  };

  // Los datos llegan de camara.js, vehiculos-ia.js y escaner.js (UniCam.diag).
  window.addEventListener('unipark:diag', (e) => poner(e.detail.clave, String(e.detail.valor)));

  const seguro = window.isSecureContext === true;
  const camara = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  poner('seguro', seguro
    ? 'Sí · la cámara está permitida' + (camara ? '' : ', pero este navegador no expone la cámara')
    : 'No · el navegador bloquea la cámara: abre la demo con https:// (scripts/demo-celular.bat)', seguro && camara ? 'ok' : 'err');
  poner('origen', location.origin);
  const ua = navigator.userAgent;
  const so = /Android [\d.]+/.exec(ua) || /(iPhone|iPad); CPU [^)]*OS [\d_]+/.exec(ua) || /Windows NT [\d.]+|Mac OS X [\d_]+/.exec(ua);
  const nav = /(EdgA?|CriOS|FxiOS|Firefox|SamsungBrowser|Chrome|Version)\/[\d.]+/.exec(ua);
  poner('navegador', (nav ? nav[0].replace('Version', 'Safari') : 'Navegador') + ' · ' + (so ? so[0].replace(/_/g, '.') : 'sistema no identificado')
    + ' · BarcodeDetector: ' + ('BarcodeDetector' in window ? 'sí' : 'no (se usará ZXing)'));
})();
