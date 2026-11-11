// UniPark · Latido del cobro automático.
// Mientras una pantalla de Portería, Caja o Mi cuenta esté abierta, pide al servidor que procese
// las tolerancias de 15 minutos que ya vencieron. Así el cobro queda listo antes de la salida
// aunque el hosting no tenga cron. El cron (scripts/cobros.php) sigue siendo la vía principal.
(function () {
  'use strict';
  const meta = (n) => document.querySelector('meta[name="' + n + '"]')?.content;
  const base = meta('unipark-base');
  const csrf = meta('csrf-token');
  if (!base || !csrf) return;

  // En la portería (escáner) el latido es más frecuente: así el cobro está listo antes de la salida.
  const INTERVALO = Number(meta('unipark-latido')) || 20000;
  async function latir() {
    if (document.hidden) return;
    try {
      const r = await fetch(base + 'api/cobros/procesar', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
        body: new URLSearchParams({ _csrf: csrf })
      });
      // Sin sesión o sin permiso: se deja de insistir (evita registros repetidos).
      if (r.status === 401 || r.status === 403) { clearInterval(reloj); return; }
      if (r.ok) {
        const d = await r.json();
        if (d.procesadas > 0) document.dispatchEvent(new CustomEvent('unipark:cobros', { detail: d }));
      }
    } catch (e) { /* sin conexión: se reintenta en el siguiente latido */ }
  }
  setTimeout(latir, 1500);
  const reloj = setInterval(latir, INTERVALO);
})();
