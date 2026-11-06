<header class="page-head no-print">
  <div>
    <p class="eyebrow">Comprobante de pago</p>
    <h1><?= e($pago['consecutivo']) ?></h1>
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
      <span class="mono"><?= e($pago['consecutivo']) ?></span>
      <span><?= e(fecha($pago['pagado_en'], 'd/m/Y H:i:s')) ?></span>
    </div>
  </div>

  <dl class="dl">
    <dt>Nombre</dt><dd><?= e($pago['nombres'] . ' ' . $pago['apellidos']) ?></dd>
    <dt>Documento</dt><dd class="mono"><?= e(mask_doc($pago['documento'])) ?></dd>
    <dt>Tipo de vehículo</dt><dd class="mono"><?= e($pago['tipo_vehiculo']) ?></dd>
    <dt>Tarifa</dt><dd><?= e(categoria_label($pago['categoria'])) ?></dd>
    <dt>Entrada</dt><dd><?= e(fecha($pago['entrada_en'])) ?></dd>
    <dt>Medio de pago</dt><dd><?= e(config('medios_pago')[$pago['medio_pago']] ?? $pago['medio_pago']) ?></dd>
    <?php if ($pago['referencia']): ?><dt>Referencia</dt><dd class="mono"><?= e($pago['referencia']) ?></dd><?php endif; ?>
    <dt>Cajero</dt><dd><?= e($pago['cajero']) ?></dd>
    <dt>Total pagado</dt><dd class="total"><?= e(money($pago['monto'])) ?></dd>
  </dl>

  <?php if ($pago['estado'] === 'ANULADO'): ?>
    <p class="anulado">ANULADO el <?= e(fecha($pago['anulado_en'])) ?> · <?= e($pago['motivo_anulacion']) ?></p>
  <?php endif; ?>

  <p class="receipt-foot">Tarifa fija por visita según vehículo y categoría, sin cobro por tiempo. Corporación Universitaria Lasallista · Parqueadero.</p>
</article>
