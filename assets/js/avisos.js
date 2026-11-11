// UniPark · Avisos de Portería en tiempo real (solo el rol con CLASIFICACION_RESOLVER).
// Muestra los eventos de acceso que la IA no pudo resolver y permite al portero elegir el tipo de vehículo,
// admitir a un visitante o rechazar. El servidor valida el permiso y que el evento siga pendiente.
(function () {
  'use strict';
  const raiz = document.querySelector('[data-avisos]');
  if (!raiz) return;
  const meta = (n) => document.querySelector('meta[name="' + n + '"]')?.content;
  const base = meta('unipark-base');
  const csrf = meta('csrf-token');
  const lista = raiz.querySelector('[data-avisos-lista]');
  const contador = raiz.querySelector('[data-avisos-contador]');
  const actividad = raiz.querySelector('[data-avisos-actividad]');
  let ultimaActividad = '';
  const tituloOriginal = document.title;
  let ultimaFirma = '';
  let ocupado = false;

  const ICONOS = {
    CARRO: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11l1.6-4.2A2 2 0 0 1 8.5 5.5h7a2 2 0 0 1 1.9 1.3L19 11m-14 0h14v5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H8v1a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-5zm2.5 2.2h.01M16.5 13.2h.01"/></svg>',
    MOTO: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5.5" cy="16" r="3"/><circle cx="18.5" cy="16" r="3"/><path d="M5.5 16l4-6h5l4 6M12 10l-1.5-3H8m6.5 3l1.5-2.5h2"/></svg>',
    BICICLETA: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5.5" cy="16.5" r="3.5"/><circle cx="18.5" cy="16.5" r="3.5"/><path d="M5.5 16.5L9 9h6l3.5 7.5M12 16.5L9 9m4-3h3l-1 3"/></svg>',
    PATINETA: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M6 18h12M15 18L13 5h3"/></svg>'
  };
  const ALERTA = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.2v.3"/></svg>';

  const TITULOS = {
    VEHICULO_NO_CLASIFICADO: 'No se pudo identificar el vehículo',
    VEHICULO_NO_COINCIDE: 'El vehículo no coincide con el ingreso',
    IDENTIDAD_NO_REGISTRADA: 'Identidad no registrada'
  };

  function elemento(tag, clase, texto) {
    const el = document.createElement(tag);
    if (clase) el.className = clase;
    if (texto !== undefined) el.textContent = texto;
    return el;
  }

  function tarjeta(ev, tipos) {
    const t = elemento('article', 'aviso-portero');
    t.dataset.id = ev.id;
    const cab = elemento('div', 'aviso-cab');
    const icono = elemento('span', 'aviso-icono');
    icono.innerHTML = ALERTA;
    const tit = elemento('div');
    if (ev.confirmar) t.classList.add('aviso-confirmar');
    tit.append(elemento('h3', '', ev.confirmar ? 'Confirmar vehículo' : (TITULOS[ev.motivo] || 'Requiere verificación')), elemento('p', 'aviso-detalle', ev.mensaje || ''));
    cab.append(icono, tit, elemento('span', 'aviso-hora mono', ev.creado));
    t.append(cab);

    const datos = elemento('dl', 'aviso-datos');
    const fila = (k, v) => { if (!v) return; datos.append(elemento('dt', '', k), elemento('dd', '', v)); };
    fila('Evento', (ev.evento === 'SALIDA' ? 'Salida' : 'Ingreso') + ' · ' + ev.identidad);
    fila('Persona', ev.persona + ' · ' + ev.categoria + ' · ' + ev.documento);
    fila('Ingreso registrado', ev.ingreso);
    fila('Sugerencia de la IA', ev.sugerencia ? ev.sugerencia + (ev.confianza !== null ? ' (' + ev.confianza + ' %)' : '') : null);
    fila('Punto de acceso', ev.puesto);
    t.append(datos);

    if (ev.captura) {
      const img = elemento('img', 'aviso-captura');
      img.src = ev.captura;
      img.alt = 'Captura del vehículo';
      t.append(img);
    }

    // La IA lo identificó: el portero verifica que sea correcto y confirma, o elige otro tipo abajo.
    if (ev.confirmar) {
      const tipoIa = tipos.find((tp) => tp.codigo === ev.confirmar);
      const ok = elemento('button', 'btn btn-ok aviso-confirmar-btn');
      ok.type = 'button';
      ok.innerHTML = ICONOS[ev.confirmar] || '';
      ok.append(elemento('span', '', 'Confirmar: ' + (tipoIa ? tipoIa.etiqueta : ev.sugerencia)));
      ok.addEventListener('click', () => resolver(ev.id, 'CLASIFICAR', ev.confirmar, t));
      t.append(ok);
    }
    t.append(elemento('p', 'aviso-pregunta', ev.motivo === 'IDENTIDAD_NO_REGISTRADA'
      ? 'Admitir como visitante (sin cobro) en:' : (ev.confirmar ? '¿No es correcto? Seleccione el vehículo:' : 'Seleccione el vehículo detectado')));
    const botones = elemento('div', 'aviso-tipos');
    tipos.forEach((tp) => {
      const b = elemento('button', 'aviso-tipo');
      b.type = 'button';
      b.innerHTML = ICONOS[tp.codigo] || '';
      b.append(elemento('span', '', tp.etiqueta));
      b.addEventListener('click', () => resolver(ev.id, ev.motivo === 'IDENTIDAD_NO_REGISTRADA' ? 'ADMITIR' : 'CLASIFICAR', tp.codigo, t));
      botones.append(b);
    });
    t.append(botones);
    const rechazar = elemento('button', 'btn btn-line btn-sm mt-12', ev.evento === 'SALIDA' ? 'No coincide · mantener la visita abierta' : 'Rechazar el ingreso');
    rechazar.type = 'button';
    rechazar.addEventListener('click', () => resolver(ev.id, 'RECHAZAR', '', t));
    t.append(rechazar);
    t.append(elemento('p', 'aviso-resultado', ''));
    return t;
  }

  async function resolver(id, accion, tipo, tarjetaEl) {
    if (ocupado) return;
    ocupado = true;
    tarjetaEl.querySelectorAll('button').forEach((b) => { b.disabled = true; });
    try {
      const r = await fetch(base + 'api/porteria/eventos/' + id + '/resolver', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
        body: new URLSearchParams({ _csrf: csrf, accion, tipo })
      });
      const d = await r.json();
      const salida = tarjetaEl.querySelector('.aviso-resultado');
      salida.textContent = d.ok ? (d.ya_resuelto ? 'Otro portero ya lo resolvió. ' : '') + (d.mensaje || 'Resuelto.') : (d.error || 'No se pudo resolver.');
      salida.dataset.tono = d.ok ? 'ok' : 'err';
      if (!d.ok) tarjetaEl.querySelectorAll('button').forEach((b) => { b.disabled = false; });
      else setTimeout(cargar, 1500);
    } catch (e) {
      tarjetaEl.querySelectorAll('button').forEach((b) => { b.disabled = false; });
    }
    ocupado = false;
  }

  /**
   * Actividad en tiempo real, SOLO LECTURA: quién se está identificando y qué se resolvió solo. No tiene botones:
   * el portero solo elige el vehículo en las alertas de arriba, que aparecen cuando la IA no lo identificó.
   */
  const ESTADOS = {
    EN_CAPTURA: ['Identificando vehículo…', 'proceso'],
    ENTRADA_REGISTRADA: ['Ingreso registrado', 'ok'],
    VISITANTE_ADMITIDO: ['Visitante admitido', 'ok'],
    SALIDA_AUTORIZADA: ['Salida autorizada', 'ok'],
    SALIDA_DENEGADA: ['Salida denegada', 'err'],
    SALIDA_INCIDENCIA: ['Incidencia de cobro', 'err'],
    RECHAZADO_PORTERIA: ['Rechazado por Portería', 'err'],
    NO_AUTORIZADA: ['Persona no autorizada', 'err'],
    EXPIRADO: ['Sin resolver a tiempo', 'muted']
  };
  function pintarActividad(items) {
    if (!actividad) return;
    const firma = items.map((a) => a.id + a.estado + (a.resultado || '')).join('|');
    if (firma === ultimaActividad) return;
    ultimaActividad = firma;
    if (!items.length) {
      actividad.replaceChildren(elemento('li', 'muted', 'Sin actividad en los últimos minutos.'));
      return;
    }
    actividad.replaceChildren(...items.map((a) => {
      const [texto, tono] = ESTADOS[a.estado === 'EN_CAPTURA' ? 'EN_CAPTURA' : (a.resultado || a.estado)] || [a.resultado || a.estado, 'muted'];
      const li = elemento('li', 'actividad-fila');
      li.dataset.tono = tono;
      li.append(
        elemento('span', 'actividad-hora mono', a.hora),
        elemento('span', 'actividad-estado', texto),
        elemento('span', 'actividad-persona', a.persona + ' · ' + a.categoria + ' · ' + a.documento),
        elemento('span', 'actividad-detalle', [
          a.evento === 'SALIDA' ? 'Salida' : 'Ingreso',
          a.vehiculo ? a.vehiculo + (a.metodo ? ' · ' + a.metodo : '') : null,
          a.puesto
        ].filter(Boolean).join(' · '))
      );
      return li;
    }));
  }

  let reloj = null;
  async function cargar() {
    try {
      const r = await fetch(base + 'api/porteria/eventos', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (r.status === 401 || r.status === 403) { clearInterval(reloj); raiz.hidden = true; return; }
      const d = await r.json();
      if (!d.ok) return;
      pintarActividad(d.actividad || []);
      const firma = d.eventos.map((e) => e.id).join(',');
      contador.textContent = d.eventos.length
        ? 'Requiere intervención' + (d.eventos.length > 1 ? ' (' + d.eventos.length + ')' : '')
        : 'Sin pendientes';
      contador.dataset.estado = d.eventos.length ? 'alerta' : '';
      document.title = d.eventos.length ? '(' + d.eventos.length + ') ' + tituloOriginal : tituloOriginal;
      if (firma === ultimaFirma || ocupado) return; // Sin cambios: no se redibuja (no se duplican tarjetas).
      if (d.eventos.length > ultimaFirma.split(',').filter(Boolean).length) { try { navigator.vibrate && navigator.vibrate(150); } catch (e) { /* opcional */ } }
      ultimaFirma = firma;
      lista.replaceChildren(...(d.eventos.length ? d.eventos.map((e) => tarjeta(e, d.tipos)) : [elemento('p', 'muted', 'No hay eventos pendientes. Los ingresos y salidas confiables se registran solos.')]));
    } catch (e) { /* sin conexión: reintenta en el siguiente ciclo */ }
  }
  cargar();
  reloj = setInterval(cargar, 3000);
})();
