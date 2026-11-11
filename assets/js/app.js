// Simulador del cobro diario (solo demostrativo, sin servidor): identidad → vehículo → tolerancia de
// 15 minutos → cobro del día con el saldo → salida que solo verifica. Tarifas de ejemplo de estudiante.
(function () {
  const sim = document.querySelector('.sim');
  if (!sim) return;

  const field = (name) => sim.querySelector('[data-field="' + name + '"]');
  const tipoButtons = sim.querySelectorAll('[data-tipo]');
  const TARIFA = { CARRO: 2000, MOTO: 1000, BICICLETA: 0 };
  const NOMBRE = { CARRO: 'automóvil', MOTO: 'motocicleta', BICICLETA: 'bicicleta' };
  const SALDO_INICIAL = 1500;

  let visita = null;
  let tipo = 'CARRO';
  let saldo = SALDO_INICIAL;
  let cobradoHoy = 0; // el día vale la tarifa más alta usada

  const pesos = (n) => '$ ' + n.toLocaleString('es-CO');
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
    field('pago').textContent = visita && visita.cobro ? visita.cobro : '—';
    field('salida').textContent = visita && visita.salida ? visita.salida : '—';
    field('saldo').textContent = pesos(saldo);

    if (!visita) {
      estado.textContent = 'SIN VISITA';
      delete estado.dataset.state;
    } else if (visita.salida) {
      estado.textContent = 'CERRADA';
      estado.dataset.state = 'cerrada';
    } else if (visita.pendiente) {
      estado.textContent = 'EN_CURSO · PENDIENTE';
      estado.dataset.state = 'abierta';
    } else if (visita.cobro) {
      estado.textContent = 'EN_CURSO · AL DÍA';
      estado.dataset.state = 'pagada';
    } else {
      estado.textContent = 'EN_CURSO · TOLERANCIA';
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
        mensaje('Rechazado: la persona ya tiene una visita abierta.', 'err');
        return;
      }
      visita = { tipo: tipo, entrada: hora(), cobro: null, pendiente: false, salida: null };
      mensaje('Carné validado; la cámara detectó ' + NOMBRE[tipo] + '. Empiezan 15 minutos de tolerancia.', 'ok');
    },
    pago() {
      if (!visita || visita.salida) {
        mensaje('No hay una visita abierta.', 'err');
        return;
      }
      if (visita.cobro) {
        mensaje('El cobro de esta visita ya se procesó: no se cobra dos veces.', 'err');
        return;
      }
      const diferencia = Math.max(0, TARIFA[visita.tipo] - cobradoHoy);
      if (TARIFA[visita.tipo] === 0) {
        visita.cobro = 'Sin tarifa';
        mensaje('Bicicleta: se registra, sin cobro.', 'ok');
      } else if (diferencia === 0) {
        visita.cobro = 'Ya pagó hoy';
        mensaje('Hoy ya se cobró una tarifa igual o mayor: no se cobra de nuevo.', 'ok');
      } else if (saldo >= diferencia) {
        saldo -= diferencia;
        cobradoHoy += diferencia;
        visita.cobro = 'Débito ' + pesos(diferencia);
        mensaje('Pasaron 15 minutos: se descontó ' + pesos(diferencia) + ' del saldo.', 'ok');
      } else {
        visita.cobro = 'Pendiente ' + pesos(diferencia);
        visita.pendiente = true;
        mensaje('Saldo insuficiente: el cobro queda pendiente y se avisa al celular. Debe pagar en Caja.', 'err');
      }
    },
    salida() {
      if (!visita || visita.salida) {
        mensaje('No hay una visita abierta para esta persona.', 'err');
        return;
      }
      if (visita.pendiente) {
        mensaje('Pago pendiente. Diríjase a Caja.', 'err');
        return;
      }
      visita.salida = hora();
      mensaje(visita.cobro ? 'Salida autorizada: el día está pagado.' : 'Salió antes de 15 minutos: no se cobra.', 'ok');
    },
    reiniciar() {
      visita = null;
      saldo = SALDO_INICIAL;
      cobradoHoy = 0;
      mensaje('Prueba carro (no alcanza el saldo) y moto (sí alcanza).');
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
