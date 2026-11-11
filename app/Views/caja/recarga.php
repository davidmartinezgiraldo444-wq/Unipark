<header class="page-head no-print">
  <div>
    <p class="eyebrow">Comprobante de recarga</p>
    <h1><?= e($mov['consecutivo']) ?></h1>
  </div>
  <div class="actions">
    <button class="btn btn-ink" type="button" data-print>Imprimir</button>
    <?php if (can('PAGO_REGISTRAR')): ?><a class="btn btn-line" href="<?= e(url('caja')) ?>">Volver a Caja</a><?php endif; ?>
  </div>
</header>

<article class="receipt">
  <div class="receipt-head">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">P</span>
      <span class="brand-name">UniPark</span>
    </div>
    <div class="receipt-num">
      <span class="mono"><?= e($mov['consecutivo']) ?></span>
      <span><?= e(fecha($mov['creado_en'], 'd/m/Y H:i:s')) ?></span>
    </div>
  </div>

  <dl class="dl">
    <dt>Nombre</dt><dd><?= e($mov['nombres'] . ' ' . $mov['apellidos']) ?></dd>
    <dt>Documento</dt><dd class="mono"><?= e(mask_doc($mov['documento'])) ?></dd>
    <dt>Medio de pago</dt><dd><?= e(config('medios_pago')[$mov['medio_pago']] ?? $mov['medio_pago']) ?></dd>
    <?php if ($mov['referencia']): ?><dt>Referencia</dt><dd class="mono"><?= e($mov['referencia']) ?></dd><?php endif; ?>
    <dt>Cajero</dt><dd><?= e($mov['cajero']) ?></dd>
    <dt>Saldo anterior</dt><dd><?= e(money($mov['saldo_anterior'])) ?></dd>
    <dt>Recarga</dt><dd class="total"><?= e(money($mov['monto'])) ?></dd>
    <dt>Saldo después de la recarga</dt><dd><?= e(money($mov['saldo_nuevo'])) ?></dd>
  </dl>

  <p class="receipt-foot">El saldo se usa para pagar automáticamente el cobro diario del parqueadero. UniPark · Parqueadero.</p>
</article>
