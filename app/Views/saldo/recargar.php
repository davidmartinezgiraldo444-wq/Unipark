<?php
/** Elegir el valor de la recarga. Los valores sugeridos y los límites vienen de config recargas_en_linea. */
$elegido = preg_replace('/\D/', '', (string) $monto);
$sugerido = in_array((int) $elegido, $cfg['valores'], true);
?>
<header class="page-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('mi-saldo')) ?>">Mi saldo</a> · Recargar</p>
    <h1>Recargar saldo</h1>
    <p>Saldo actual: <strong><?= e(money($persona['saldo'])) ?></strong>. Pagarás en la página segura de la pasarela; UniPark no ve ni guarda los datos de tu tarjeta o tu banco.</p>
  </div>
</header>

<?php if (!$enLinea): ?>
  <p class="alert alert-warn" role="status">Las recargas en línea no están habilitadas en este momento. Puedes recargar en Caja.</p>
<?php else: ?>
  <?php if ($error): ?><p class="alert alert-err" role="alert"><?= e($error) ?></p><?php endif; ?>

  <form class="panel" method="post" action="<?= e(url('mi-saldo/recargar')) ?>" data-recarga-form>
    <?= csrf_field() ?>
    <fieldset class="form-section">
      <legend>¿Cuánto quieres recargar?</legend>
      <div class="montos" role="radiogroup">
        <?php foreach ($cfg['valores'] as $v): ?>
          <label class="monto">
            <input type="radio" name="monto" value="<?= e($v) ?>" <?= (int) $elegido === $v ? 'checked' : '' ?>>
            <span><?= e(money($v)) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="field mt-14">
        <label for="otro">Otro valor</label>
        <input id="otro" name="otro" type="text" inputmode="numeric" autocomplete="off" maxlength="9"
               value="<?= !$sugerido && $elegido !== '' ? e($elegido) : '' ?>" placeholder="Por ejemplo 15000">
        <p class="field-hint">Entre <?= e(money($cfg['minimo'])) ?> y <?= e(money($cfg['maximo'])) ?>, en pesos y sin centavos.</p>
      </div>
    </fieldset>

    <fieldset class="form-section">
      <legend>Medio de pago</legend>
      <p class="muted">Eliges el medio en la página de la pasarela. Normalmente se ofrecen:</p>
      <ul class="lista-reglas">
        <?php foreach ($cfg['medios_informativos'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
      </ul>
      <p class="field-hint">Los medios disponibles dependen de la cuenta de la universidad en la pasarela.</p>
    </fieldset>

    <div class="form-actions">
      <button class="btn btn-ink" type="submit">Continuar</button>
      <a class="btn btn-line" href="<?= e(url('mi-saldo')) ?>">Volver</a>
    </div>
  </form>
<?php endif; ?>
