// UniPark · Dibuja los códigos de los carnés ficticios (bwip-js: Code 128, QR y PDF417) y el carné digital de
// Mi cuenta (JsBarcode, Code 128). Los códigos se dibujan sin el número legible debajo (privacidad).
(function () {
  'use strict';
  const tinta = '16181d';

  // Hoja de carnés de prueba (bwip-js).
  if (window.bwipjs && typeof window.bwipjs.toCanvas === 'function') {
    document.querySelectorAll('[data-bcid]').forEach((el) => {
      const bcid = el.dataset.bcid;
      // bwip-js rechaza opciones con valor undefined: solo se pasan las que aplican a cada formato.
      const opciones = { bcid, text: el.dataset.texto, scale: bcid === 'qrcode' ? 3 : 2, includetext: false,
        barcolor: tinta, backgroundcolor: 'ffffff', paddingwidth: 4, paddingheight: 4 };
      if (bcid === 'code128') opciones.height = 12;
      if (bcid === 'pdf417') { opciones.columns = 6; opciones.rowmult = 3; }
      try {
        window.bwipjs.toCanvas(el, opciones);
      } catch (e) {
        el.replaceWith(document.createTextNode('No se pudo dibujar el código (' + bcid + ').'));
      }
    });
  }

  // Carné digital (Mi cuenta).
  if (typeof window.JsBarcode === 'function') {
    document.querySelectorAll('[data-barcode]').forEach((el) => {
      try {
        window.JsBarcode(el, el.dataset.barcode, {
          format: 'CODE128', height: 64, width: 2, margin: 8, fontSize: 14,
          font: 'IBM Plex Mono', background: '#ffffff', lineColor: '#16181d'
        });
      } catch (e) {
        el.replaceWith(document.createTextNode(el.dataset.barcode));
      }
    });
  }
  document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
})();
