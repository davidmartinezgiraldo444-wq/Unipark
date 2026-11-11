// UniPark · Mi cuenta: cuenta regresiva de la tolerancia, estado en vivo y avisos push en el celular.
(function () {
  'use strict';
  const meta = (n) => document.querySelector('meta[name="' + n + '"]')?.content;
  const base = meta('unipark-base');
  const csrf = meta('csrf-token');
  const $ = (s) => document.querySelector(s);

  // ------------------------------------------------ Tolerancia en vivo
  const tol = $('[data-tolerancia]');
  let segundos = Number(tol?.dataset.segundos || 0);
  const fin = Date.now() + segundos * 1000;
  setInterval(() => {
    if (!tol || segundos <= 0) return;
    segundos = Math.max(0, Math.round((fin - Date.now()) / 1000));
    tol.textContent = segundos > 0 ? String(Math.floor(segundos / 60)).padStart(2, '0') + ':' + String(segundos % 60).padStart(2, '0') : '00:00';
    if (segundos === 0) setTimeout(refrescar, 4000);
  }, 1000);

  async function refrescar() {
    try {
      const r = await fetch(base + 'api/mi-cuenta/estado', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!r.ok) return;
      const d = await r.json();
      const pesos = (v) => '$ ' + Number(v).toLocaleString('es-CO', { maximumFractionDigits: 0 });
      $('[data-saldo]').textContent = pesos(d.saldo);
      $('[data-cobro-hoy]').textContent = d.cobro ? pesos(d.cobro.valor) : '—';
      $('[data-cobro-estado]').textContent = d.cobro ? d.cobro.estado.charAt(0) + d.cobro.estado.slice(1).toLowerCase() : 'Sin cobro';
      const aviso = $('[data-estado-hoy]');
      const titulo = $('[data-estado-titulo]');
      titulo.textContent = d.titulo || '';
      titulo.hidden = !d.titulo;
      $('[data-estado-mensaje]').textContent = d.mensaje;
      $('[data-estado-recargar]').hidden = d.tono !== 'err';
      aviso.className = 'alert estado-hoy alert-' + (d.tono === 'err' ? 'err' : d.tono === 'warn' ? 'warn' : 'ok');
    } catch (e) { /* sin conexión */ }
  }
  setInterval(refrescar, 15000);
  document.addEventListener('unipark:cobros', refrescar);

  // ------------------------------------------------ Notificaciones push
  const caja = $('[data-push]');
  if (!caja) return;
  const estadoTxt = $('[data-push-estado]');
  const bActivar = $('[data-push-activar]');
  const bProbar = $('[data-push-probar]');
  const bDesactivar = $('[data-push-desactivar]');

  const soportado = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  if (!soportado || !window.isSecureContext) {
    estadoTxt.textContent = !window.isSecureContext
      ? 'Los avisos requieren abrir el sitio con https:// (o en localhost).'
      : 'Este navegador no admite notificaciones. En iPhone, agrega el sitio a la pantalla de inicio primero.';
    bActivar.disabled = true;
    return;
  }

  let registro = null;
  navigator.serviceWorker.register(base + 'sw.js', { scope: base }).then(async (r) => {
    registro = r;
    const sub = await r.pushManager.getSubscription();
    pintar(sub);
  }).catch(() => { estadoTxt.textContent = 'No se pudo preparar el servicio de avisos.'; });

  function pintar(sub) {
    const activo = Boolean(sub) && Notification.permission === 'granted';
    bActivar.hidden = activo;
    bDesactivar.hidden = !activo;
    bProbar.hidden = !activo;
    if (Notification.permission === 'denied') {
      estadoTxt.textContent = 'Bloqueaste las notificaciones de este sitio. Actívalas en los ajustes del navegador.';
      bActivar.disabled = true;
    } else if (activo) {
      estadoTxt.textContent = 'Avisos activos en este celular.';
    }
  }

  async function enviar(ruta, datos) {
    const r = await fetch(base + ruta, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: new URLSearchParams({ ...datos, _csrf: csrf })
    });
    return r.json().catch(() => ({ ok: false, error: 'Error del servidor' }));
  }

  bActivar.addEventListener('click', async () => {
    bActivar.disabled = true;
    try {
      const permiso = await Notification.requestPermission();
      if (permiso !== 'granted') { pintar(null); bActivar.disabled = permiso === 'denied'; return; }
      const clave = await fetch(base + 'api/push/clave', { credentials: 'same-origin' }).then((r) => r.json());
      const sub = await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: aBytes(clave.clave) });
      const j = sub.toJSON();
      const d = await enviar('api/push/suscribir', { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth });
      estadoTxt.textContent = d.ok ? d.mensaje : d.error;
      pintar(d.ok ? sub : null);
    } catch (e) {
      estadoTxt.textContent = 'No se pudieron activar los avisos: ' + (e.message || e);
    }
    bActivar.disabled = false;
  });

  bDesactivar.addEventListener('click', async () => {
    const sub = await registro.pushManager.getSubscription();
    if (sub) {
      await enviar('api/push/cancelar', { endpoint: sub.endpoint });
      await sub.unsubscribe();
    }
    estadoTxt.textContent = 'Avisos desactivados en este celular.';
    pintar(null);
  });

  bProbar.addEventListener('click', async () => {
    const d = await enviar('api/push/probar', {});
    estadoTxt.textContent = d.ok ? d.mensaje : d.error;
  });

  function aBytes(b64) {
    const relleno = '='.repeat((4 - (b64.length % 4)) % 4);
    const bin = atob((b64 + relleno).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(bin, (c) => c.charCodeAt(0));
  }
})();
