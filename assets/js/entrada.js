// Portería: ayudas de usabilidad. El servidor vuelve a validar el documento y el tipo.
(function () {
  const doc = document.getElementById('documento');
  if (doc) {
    // Quita espacios y puntos que suelen copiarse con el documento.
    doc.addEventListener('input', () => {
      const limpio = doc.value.replace(/[\s.]/g, '');
      if (limpio !== doc.value) doc.value = limpio;
    });
    doc.select();
  }

  const form = document.querySelector('[data-entrada-form]');
  if (!form) return;

  const hint = form.querySelector('[data-tipo-hint]');
  const textoOriginal = hint ? hint.textContent : '';

  form.addEventListener('submit', (e) => {
    if (!form.querySelector('input[name="tipo"]:checked')) {
      e.preventDefault();
      if (hint) {
        hint.textContent = 'Selecciona el tipo de vehículo para registrar la entrada.';
        hint.classList.add('field-error');
      }
      const primero = form.querySelector('input[name="tipo"]:not(:disabled)');
      if (primero) primero.focus();
    }
  });

  form.addEventListener('change', () => {
    if (hint) {
      hint.textContent = textoOriginal;
      hint.classList.remove('field-error');
    }
  });
})();
