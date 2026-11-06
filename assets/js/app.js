// Simulador del flujo entrada → pago → salida (solo demostrativo, sin servidor).
(function () {
  const sim = document.querySelector('.sim');
  if (!sim) return;

  const field = (name) => sim.querySelector('[data-field="' + name + '"]');
  const tipoButtons = sim.querySelectorAll('[data-tipo]');

  let visita = null;
  let tipo = 'CARRO';
  let recibo = 1040;

  const hora = () =>
    new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

  function mensaje(texto, tone) {
    const el = field('mensaje');
    el.textContent = texto;
    if (tone) el.dataset.tone = tone;
    else delete el.dataset.tone;
  }

  function render() {
    const estado = field('estado');
    field('entrada').textContent = visita ? visita.entrada : '—';
    field('pago').textContent = visita && visita.pago ? visita.pago : '—';
    field('salida').textContent = visita && visita.salida ? visita.salida : '—';

    if (!visita) {
      estado.textContent = 'SIN VISITA';
      delete estado.dataset.state;
    } else if (visita.salida) {
      estado.textContent = 'CERRADA';
      estado.dataset.state = 'cerrada';
    } else if (visita.pago) {
      estado.textContent = 'EN_CURSO · PAGADO';
      estado.dataset.state = 'pagada';
    } else {
      estado.textContent = 'EN_CURSO';
      estado.dataset.state = 'abierta';
    }

    const bloqueado = Boolean(visita && !visita.salida);
    tipoButtons.forEach((b) => {
      b.disabled = bloqueado;
      b.setAttribute('aria-checked', String(b.dataset.tipo === tipo));
    });
  }

  const acciones = {
    entrada() {
      if (visita && !visita.salida) {
        mensaje('Rechazado: el estudiante ya tiene una visita abierta.', 'err');
        return;
      }
      visita = { tipo: tipo, entrada: hora(), pago: null, salida: null };
      mensaje('Entrada registrada como ' + tipo + ' con la tarifa vigente.', 'ok');
    },
    pago() {
      if (!visita || visita.salida) {
        mensaje('No hay una visita abierta pendiente de pago.', 'err');
        return;
      }
      if (visita.pago) {
        mensaje('Rechazado: esta visita ya tiene un pago válido.', 'err');
        return;
      }
      recibo += 1;
      visita.pago = 'Recibo ' + String(recibo).padStart(6, '0');
      mensaje('Pago confirmado por Caja. Comprobante emitido.', 'ok');
    },
    salida() {
      if (!visita || visita.salida) {
        mensaje('No hay una visita abierta para este estudiante.', 'err');
        return;
      }
      if (!visita.pago) {
        mensaje('Pago pendiente. Diríjase a Caja.', 'err');
        return;
      }
      visita.salida = hora();
      mensaje('Salida autorizada. La visita queda cerrada.', 'ok');
    },
    reiniciar() {
      visita = null;
      mensaje('Intenta autorizar la salida antes de pagar.');
    }
  };

  sim.addEventListener('click', (e) => {
    const tipoBtn = e.target.closest('[data-tipo]');
    if (tipoBtn && !tipoBtn.disabled) {
      tipo = tipoBtn.dataset.tipo;
      render();
      return;
    }
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    acciones[btn.dataset.action]();
    render();
  });

  render();
})();

// Mostrar u ocultar la contraseña en el inicio de sesión.
(function () {
  const toggle = document.querySelector('.pass-toggle');
  if (!toggle) return;

  const input = document.getElementById(toggle.getAttribute('aria-controls'));

  toggle.addEventListener('click', () => {
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    toggle.textContent = visible ? 'Ocultar' : 'Mostrar';
    toggle.setAttribute('aria-pressed', String(visible));
    input.focus();
  });
})();

// Confirmación antes de operaciones sensibles: formularios con data-confirm.
// Sin JavaScript el formulario se envía igual; el servidor valida todo de nuevo.
(function () {
  const dialog = document.getElementById('confirmar');
  const forms = document.querySelectorAll('form[data-confirm]');
  if (!forms.length) return;

  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (form.dataset.confirmado === '1') return;
      e.preventDefault();
      if (!form.reportValidity()) return;

      const texto = form.dataset.confirm;
      if (!dialog || typeof dialog.showModal !== 'function') {
        if (window.confirm(texto)) enviar(form);
        return;
      }
      dialog.querySelector('[data-confirm-texto]').textContent = texto;
      dialog.returnValue = '';
      dialog.showModal();
      dialog.addEventListener('close', function alCerrar() {
        dialog.removeEventListener('close', alCerrar);
        if (dialog.returnValue === 'ok') enviar(form);
      });
    });
  });

  function enviar(form) {
    form.dataset.confirmado = '1';
    form.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = true; });
    form.submit();
  }
})();

// Botones de impresión (comprobante y cierre de caja).
(function () {
  document.querySelectorAll('[data-print]').forEach((b) => {
    b.addEventListener('click', () => window.print());
  });
})();
