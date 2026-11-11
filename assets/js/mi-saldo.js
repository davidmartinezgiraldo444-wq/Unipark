// UniPark · Mi saldo: elegir el valor de la recarga y seguir su estado.
// El navegador solo MUESTRA el estado: la acreditación la decide el servidor con la confirmación de la pasarela.
(function () {
  'use strict';
  const meta = (n) => document.querySelector('meta[name="' + n + '"]')?.content;
  const base = meta('unipark-base');

  // 1) Formulario: un valor sugerido y «Otro valor» se excluyen entre sí.
  const form = document.querySelector('[data-recarga-form]');
  if (form) {
    const otro = form.querySelector('#otro');
    const radios = form.querySelectorAll('input[name="monto"]');
    otro.addEventListener('input', () => {
      otro.value = otro.value.replace(/\D/g, '');
      if (otro.value !== '') radios.forEach((r) => { r.checked = false; });
    });
    radios.forEach((r) => r.addEventListener('change', () => { otro.value = ''; }));
  }

  // 2) Detalle de una recarga sin estado final: consulta cada 5 s (máximo 10 minutos).
  const panel = document.querySelector('[data-recarga]');
  if (!panel || panel.dataset.final === '1' || !base) return;
  const estadoEl = panel.querySelector('[data-recarga-estado]');
  const mensajeEl = panel.querySelector('[data-recarga-mensaje]');
  const saldoEl = panel.querySelector('[data-recarga-saldo]');
  const limite = Date.now() + 10 * 60 * 1000;
  let anterior = null;

  async function consultar() {
    if (Date.now() > limite) return;
    try {
      const r = await fetch(base + 'api/mi-saldo/recargas/' + panel.dataset.id, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (r.status === 401 || r.status === 404) return;
      const d = await r.json();
      if (d.ok) {
        estadoEl.textContent = d.etiqueta;
        saldoEl.textContent = d.saldo_texto;
        // Un cambio de CREADA a otro estado (o a un estado final) recarga la página para mostrar la vista correcta.
        if (anterior !== null && d.estado !== anterior) return location.reload();
        anterior = d.estado;
        if (d.final) return location.reload();
      }
    } catch (e) { /* sin conexión: se reintenta */ }
    setTimeout(consultar, 5000);
  }
  if (mensajeEl) setTimeout(consultar, 3000);
})();
