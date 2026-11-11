<?php /** Checkout SIMULADO dentro de UniPark: la persona elige el resultado; el servidor decide si acredita. */ ?>
<header class="page-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('mi-saldo')) ?>">Mi saldo</a> · Pago</p>
    <h1><?= $pago ? e(money($pago['monto'])) : 'Pago no disponible' ?></h1>
    <?php if ($pago): ?><p class="mono">Referencia <?= e($pago['reference']) ?></p><?php endif; ?>
  </div>
</header>

<p class="aviso-simulacion" role="note">
  <strong>Simulación académica.</strong> Esta pantalla reemplaza a la pasarela de pagos en la demostración: no se usa
  ninguna tarjeta ni cuenta bancaria y no se mueve dinero real.
</p>

<?php if ($pago === null): ?>
  <p class="alert alert-err" role="alert">Este pago no se puede procesar: la recarga no es tuya, ya tiene un pago en curso o los datos fueron alterados. No se procesó nada.</p>
  <p><a class="btn btn-line" href="<?= e(url('mi-saldo')) ?>">Volver a Mi saldo</a></p>
<?php else: ?>
  <section class="panel">
    <div class="panel-head"><h2>Resultado del pago</h2></div>
    <p class="muted">Elige qué respuesta da la pasarela simulada. El saldo solo cambia si el servidor verifica la notificación firmada y encuentra el pago aprobado.</p>
    <form method="post" action="<?= e(url('simulador-pago')) ?>" class="sim-opciones">
      <?= csrf_field() ?>
      <?php foreach ($pago['campos'] as $nombre => $valor): ?>
        <input type="hidden" name="<?= e($nombre) ?>" value="<?= e($valor) ?>">
      <?php endforeach; ?>
      <?php foreach ($resultados as $codigo => $texto): ?>
        <button class="btn <?= $codigo === 'APPROVED' ? 'btn-ink' : 'btn-line' ?> btn-block" type="submit" name="resultado" value="<?= e($codigo) ?>"><?= e($texto) ?></button>
      <?php endforeach; ?>
    </form>
  </section>
  <p class="field-hint mt-14">Qué pasa por dentro: se crea una transacción simulada, se envía una notificación firmada al mismo procesador que usaría Wompi y el servidor consulta la transacción antes de acreditar. Una recarga se acredita una sola vez.</p>
<?php endif; ?>
