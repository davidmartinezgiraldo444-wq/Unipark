// Caja: muestra la referencia según el medio y calcula el cambio (solo informativo, no se guarda).
(function () {
  const form = document.querySelector('[data-pago-form]');
  if (!form) return;

  const medio = form.querySelector('#medio_pago');
  const referencia = form.querySelector('[data-referencia]');
  const efectivo = form.querySelector('[data-efectivo]');
  const recibido = form.querySelector('#recibido');
  const cambio = form.querySelector('[data-cambio]');
  const montoEl = document.querySelector('[data-monto]');
  const monto = montoEl ? parseFloat(montoEl.dataset.monto) : 0;

  const pesos = (n) => '$ ' + Math.round(n).toLocaleString('es-CO');

  function porMedio() {
    const esEfectivo = medio.value === 'EFECTIVO';
    referencia.hidden = esEfectivo;
    efectivo.hidden = !esEfectivo;
  }

  function calcular() {
    const valor = parseFloat(recibido.value.replace(/[^\d]/g, ''));
    if (!valor) {
      cambio.hidden = true;
      return;
    }
    const diferencia = valor - monto;
    cambio.hidden = false;
    cambio.querySelector('span').textContent = diferencia < 0 ? 'Falta' : 'Cambio';
    cambio.querySelector('strong').textContent = pesos(Math.abs(diferencia));
    if (diferencia < 0) cambio.dataset.falta = '1';
    else delete cambio.dataset.falta;
  }

  medio.addEventListener('change', porMedio);
  recibido.addEventListener('input', calcular);
  porMedio();

  const panel = document.getElementById('cobro');
  if (panel && window.matchMedia('(max-width: 1080px)').matches) {
    panel.scrollIntoView({ block: 'start' });
  }
})();
