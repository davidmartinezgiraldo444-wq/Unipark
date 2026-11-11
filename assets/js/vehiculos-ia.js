// UniPark · Reconocimiento del tipo de vehículo con TensorFlow.js, en el navegador.
//
// 1) Modelo propio exportado de Google Teachable Machine (assets/modelo-vehiculos/), con las clases
//    Motocicleta, Automovil, Bicicleta y Patineta (y opcionalmente Ninguno). Es la opción recomendada.
// 2) Si ese modelo no existe, el modelo preentrenado COCO-SSD (80 clases). Solo se usan car, motorcycle
//    y bicycle. COCO-SSD NO tiene una clase de patineta ni de scooter (su clase "skateboard" es una tabla
//    deportiva), así que con este modelo una patineta da DESCONOCIDO y Portería la clasifica a mano.
//    La patineta se reconoce solo con el modelo propio de Teachable Machine.
//
// Solo se obtiene la CATEGORÍA del vehículo. No se leen placas, marcas ni colores, y las imágenes
// no se guardan ni se envían: al servidor llega únicamente CARRO, MOTO, BICICLETA o PATINETA.
(function () {
  'use strict';

  const URLS = {
    tf1: 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@1.3.1/dist/tf.min.js',
    tm: 'https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.5/dist/teachablemachine-image.min.js',
    tf4: 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js',
    coco: 'https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js'
  };

  // Nombres de clase de Teachable Machine → código de UniPark. Se ignoran tildes y mayúsculas.
  const CLASES_TM = {
    motocicleta: 'MOTO', moto: 'MOTO', motos: 'MOTO',
    automovil: 'CARRO', auto: 'CARRO', carro: 'CARRO', carros: 'CARRO', automoviles: 'CARRO',
    bicicleta: 'BICICLETA', bici: 'BICICLETA', bicicletas: 'BICICLETA',
    // PATINETA = solo scooter (aprobado 07/11/2026): de pie, con manubrio, manual o eléctrico.
    patineta: 'PATINETA', patinetas: 'PATINETA', scooter: 'PATINETA', scooters: 'PATINETA',
    'patineta electrica': 'PATINETA', 'scooter electrico': 'PATINETA', monopatin: 'PATINETA'
    // "Skateboard" / "Tabla" (sin manubrio) NO se mapean: dan DESCONOCIDO y Portería decide.
  };
  // Clases COCO-SSD → código de UniPark. Camiones y buses no se asumen como automóvil, y "skateboard"
  // no se asume como patineta: todo lo demás es DESCONOCIDO (clasificación manual).
  const CLASES_COCO = { car: 'CARRO', motorcycle: 'MOTO', bicycle: 'BICICLETA' };

  const normalizar = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').trim().toLowerCase();

  /** Carga el modelo disponible. @return {Promise<{fuente:string, clasificar:(video)=>Promise<{tipo:string,confianza:number}>}>} */
  async function cargar(modeloTm) {
    const { cargarScript } = window.UniCam;
    if (modeloTm) {
      await cargarScript(URLS.tf1);
      await cargarScript(URLS.tm);
      const modelo = await window.tmImage.load(modeloTm + 'model.json', modeloTm + 'metadata.json');
      const lienzo = document.createElement('canvas');
      lienzo.width = lienzo.height = 224;
      const ctx = lienzo.getContext('2d');
      return {
        fuente: 'Modelo propio (Teachable Machine)',
        async clasificar(video) {
          recorteCuadrado(video, lienzo, ctx);
          const pred = await modelo.predict(lienzo);
          // Cada clase se mapea a un código; las no reconocidas (Ninguno, Skateboard…) cuentan como DESCONOCIDO.
          const porTipo = {};
          for (const p of pred) {
            const tipo = CLASES_TM[normalizar(p.className)] || 'DESCONOCIDO';
            porTipo[tipo] = (porTipo[tipo] || 0) + p.probability;
          }
          const orden = Object.entries(porTipo).sort((a, b) => b[1] - a[1]);
          const [tipo, confianza] = orden[0] || ['DESCONOCIDO', 0];
          // segunda: probabilidad de la siguiente opción; si es cercana, la detección es dudosa.
          return { tipo, confianza, segunda: orden[1] ? orden[1][1] : 0 };
        }
      };
    }

    await cargarScript(URLS.tf4);
    await cargarScript(URLS.coco);
    const modelo = await window.cocoSsd.load({ base: 'lite_mobilenet_v2' });
    return {
      fuente: 'Modelo preentrenado COCO-SSD · patineta: manual',
      async clasificar(video) {
        if (!ancho(video)) return { tipo: 'DESCONOCIDO', confianza: 0 };
        const detecciones = await modelo.detect(video, 10, 0.25);
        const area = ancho(video) * alto(video);
        let mejor = { tipo: 'DESCONOCIDO', confianza: 0, peso: 0 };
        const vistos = [];
        for (const d of detecciones) {
          const tipo = CLASES_COCO[d.class];
          if (!tipo) continue;
          vistos.push({ tipo, score: d.score });
          // Prefiere el vehículo más cercano (más grande) entre los de buena confianza.
          const peso = d.score * Math.sqrt((d.bbox[2] * d.bbox[3]) / area);
          if (peso > mejor.peso) mejor = { tipo, confianza: d.score, peso };
        }
        // Otro tipo de vehículo visible al mismo tiempo (por ejemplo, moto y carro) hace la detección dudosa.
        const segunda = vistos.filter((v) => v.tipo !== mejor.tipo).reduce((m, v) => Math.max(m, v.score), 0);
        return { tipo: mejor.tipo, confianza: mejor.confianza, segunda };
      }
    };
  }

  // Acepta video, imagen o lienzo (las pruebas usan imágenes fijas).
  const ancho = (f) => f.videoWidth || f.naturalWidth || f.width || 0;
  const alto = (f) => f.videoHeight || f.naturalHeight || f.height || 0;

  function recorteCuadrado(video, lienzo, ctx) {
    const w = ancho(video);
    const h = alto(video);
    const lado = Math.min(w, h);
    ctx.drawImage(video, (w - lado) / 2, (h - lado) / 2, lado, lado, 0, 0, lienzo.width, lienzo.height);
  }

  /**
   * Bucle de clasificación con estabilidad. Una detección es CONFIABLE solo si: (1) no es DESCONOCIDO,
   * (2) su confianza supera el umbral, (3) le saca al menos opciones.margen a la segunda opción, y
   * (4) se mantiene igual durante estableMs. Todo lo demás es DUDOSO y queda para Portería.
   * onCambio({tipo, confianza, estable, baja}) en cada cuadro.
   */
  function vigilar(video, ia, opciones, onCambio) {
    let activo = true;
    let candidato = null;
    let desde = 0;
    let aceptado = null;

    (async function bucle() {
      while (activo) {
        let r = { tipo: 'DESCONOCIDO', confianza: 0 };
        const t0 = performance.now();
        try { r = await ia.clasificar(video); } catch (e) { /* cuadro no disponible */ }
        const ms = Math.round(performance.now() - t0);
        if (!activo) break;
        const margen = opciones.margen ?? 0.25;
        const confiable = r.tipo !== 'DESCONOCIDO' && r.confianza >= opciones.umbral && r.confianza - (r.segunda || 0) >= margen;
        const ahora = performance.now();
        if (confiable && candidato === r.tipo) {
          if (!aceptado && ahora - desde >= opciones.estableMs) aceptado = r.tipo;
        } else {
          candidato = confiable ? r.tipo : null;
          desde = ahora;
          if (aceptado && aceptado !== r.tipo) aceptado = null;
        }
        onCambio({ tipo: r.tipo, confianza: r.confianza, segunda: r.segunda || 0, estable: aceptado === r.tipo && confiable, baja: !confiable, ms });
        await new Promise((res) => setTimeout(res, 120));
      }
    })();

    return { detener() { activo = false; } };
  }

  window.UniIA = { cargar, vigilar };
})();
