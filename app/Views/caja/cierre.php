<?php $inputFecha = static fn (string $f) => (new DateTimeImmutable($f))->format('Y-m-d\TH:i'); ?>
<header class="page-head">
  <div>
    <p class="eyebrow">Caja</p>
    <h1>Cierre de caja</h1>
    <p>Resumen de los pagos que registraste en el turno. Compara el efectivo contado con el total del sistema; el cierre queda registrado en la auditoría.</p>
  </div>
  <div class="actions">
    <button class="btn btn-line" type="button" data-print>Imprimir</button>
  </div>
</header>

<?php if ($error): ?><p class="alert alert-err" role="alert"><?= e($error) ?></p><div class="panel-note"></div><?php endif; ?>

<div class="grid-main">
  <div class="stack">
    <section class="panel no-print">
      <form class="filters" method="get" action="<?= e(url('caja/cierre')) ?>">
        <div class="field">
          <label for="desde">Inicio del turno</label>
          <input id="desde" name="desde" type="datetime-local" value="<?= e($inputFecha($desde)) ?>" required>
        </div>
        <div class="field">
          <label for="hasta">Fin del turno</label>
          <input id="hasta" name="hasta" type="datetime-local" value="<?= e($inputFecha($hasta)) ?>" required>
        </div>
        <button class="btn btn-line" type="submit">Actualizar</button>
      </form>
    </section>

    <section class="panel panel-flush">
      <div class="panel-head">
        <h2>Transacciones del turno</h2>
        <span class="muted"><?= e(fecha($desde)) ?> – <?= e(fecha($hasta)) ?></span>
      </div>
      <?php if (!$detalle): ?>
        <p class="empty">No registraste pagos en este periodo.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Comprobante</th><th>Hora</th><th>Persona</th><th>Medio</th><th>Estado</th><th class="num">Monto</th></tr></thead>
            <tbody>
            <?php foreach ($detalle as $p): ?>
              <tr<?= $p['estado'] === 'ANULADO' ? ' class="is-off"' : '' ?>>
                <td class="mono"><a href="<?= e(url('caja/recibo/' . $p['id_pago'])) ?>"><?= e($p['consecutivo']) ?></a></td>
                <td><?= e(fecha($p['pagado_en'], 'H:i')) ?></td>
                <td><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></td>
                <td><?= e($medios[$p['medio_pago']] ?? $p['medio_pago']) ?></td>
                <td><?= estado_badge($p['estado']) ?></td>
                <td class="num"><?= e(money($p['monto'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Totales</h2><span class="muted"><?= e($cantidad) ?> pagos válidos</span></div>
      <dl class="dl">
        <?php foreach ($medios as $codigo => $nombre):
            $fila = current(array_filter($resumen, static fn ($r) => $r['medio_pago'] === $codigo)) ?: null; ?>
          <dt><?= e($nombre) ?></dt><dd><?= e(money($fila['total'] ?? 0)) ?></dd>
        <?php endforeach; ?>
        <?php foreach ($medios as $codigo => $nombre):
            $fila = current(array_filter($recargas, static fn ($r) => $r['medio_pago'] === $codigo)) ?: null;
            if ($fila === null) continue; ?>
          <dt>Recargas · <?= e($nombre) ?> (<?= e($fila['cantidad']) ?>)</dt><dd><?= e(money($fila['total'])) ?></dd>
        <?php endforeach; ?>
        <dt>Total registrado</dt><dd class="total"><?= e(money($total)) ?></dd>
      </dl>
      <p class="field-hint mt-10">Incluye pagos de cobros y recargas de saldo recibidos por ti. Los débitos automáticos del saldo no pasan por Caja.</p>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Arqueo de efectivo</h2></div>
      <?php if ($contado !== null): ?>
        <dl class="dl">
          <dt>Efectivo en sistema</dt><dd><?= e(money($efectivo)) ?></dd>
          <dt>Efectivo contado</dt><dd><?= e(money($contado['valor'])) ?></dd>
          <dt>Diferencia</dt><dd class="total"><?= e(($contado['diferencia'] < 0 ? '− ' : '') . money(abs($contado['diferencia']))) ?></dd>
        </dl>
        <p class="block-msg <?= abs($contado['diferencia']) < 0.01 ? 'ok' : 'err' ?>">
          <?= abs($contado['diferencia']) < 0.01
              ? 'La caja cuadra. El cierre quedó registrado.'
              : ($contado['diferencia'] < 0 ? 'Falta efectivo.' : 'Sobra efectivo.') . ' El cierre y la diferencia quedaron registrados.' ?>
        </p>
      <?php else: ?>
        <form method="post" action="<?= e(url('caja/cierre')) ?>" class="stack"
              data-confirm="¿Registrar el cierre de caja con el efectivo contado?">
          <?= csrf_field() ?>
          <input type="hidden" name="desde" value="<?= e($inputFecha($desde)) ?>">
          <input type="hidden" name="hasta" value="<?= e($inputFecha($hasta)) ?>">
          <div class="field">
            <label for="efectivo_contado">Efectivo contado</label>
            <input id="efectivo_contado" name="efectivo_contado" type="text" inputmode="numeric" required autocomplete="off">
            <span class="field-hint">En pesos, por ejemplo 125000 o 125.000. Efectivo en sistema: <?= e(money($efectivo)) ?></span>
          </div>
          <button class="btn btn-ink btn-block" type="submit">Registrar cierre</button>
        </form>
      <?php endif; ?>
    </section>
  </aside>
</div>
