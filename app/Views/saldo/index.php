<?php
/** Mi saldo: saldo prepago, pendiente, recargas en línea, movimientos y cobros. Solo datos propios. */
$nombresMov = ['RECARGA' => 'Recarga', 'DEBITO' => 'Cobro de parqueadero', 'REINTEGRO' => 'Reintegro', 'REVERSO_RECARGA' => 'Reverso de recarga'];
?>
<header class="page-head">
  <div>
    <p class="eyebrow">Mi saldo UniPark</p>
    <h1><?= e(money($persona['saldo'])) ?></h1>
    <p>Tu saldo es prepago: tú decides cuándo y cuánto recargar. El parqueadero se descuenta solo de este saldo; UniPark nunca cobra tu tarjeta ni tu cuenta bancaria.</p>
  </div>
  <?php if ($enLinea): ?>
    <div class="actions"><a class="btn btn-yellow" href="<?= e(url('mi-saldo/recargar')) ?>">Recargar saldo</a></div>
  <?php endif; ?>
</header>

<div class="kpis">
  <div class="kpi">
    <span class="kpi-label">Saldo disponible</span>
    <span class="kpi-value"><?= e(money($persona['saldo'])) ?></span>
    <span class="kpi-note"><?= $enLinea ? 'Recarga en línea o en Caja' : 'Recargas en Caja' ?></span>
  </div>
  <div class="kpi <?= $pendiente > 0 ? 'kpi-warn' : '' ?>">
    <span class="kpi-label">Saldo pendiente</span>
    <span class="kpi-value"><?= e($pendiente > 0 ? money($pendiente) : '—') ?></span>
    <span class="kpi-note"><?= $pendiente > 0 ? count($pendientes) . ' cobro(s) por pagar' : 'Sin cobros pendientes' ?></span>
  </div>
</div>

<?php if ($pendiente > 0): ?>
  <p class="alert alert-err" role="status">
    Tienes <?= e(money($pendiente)) ?> pendientes de pago. Cuando tu saldo alcance, el cobro se paga solo.
    <?php if ($enLinea): ?><a href="<?= e(url('mi-saldo/recargar?monto=' . (int) ceil($pendiente))) ?>">Recargar ahora</a> o paga en Caja.<?php else: ?>Paga en Caja antes de salir.<?php endif; ?>
  </p>
<?php elseif (!$enLinea): ?>
  <p class="alert alert-warn" role="status">Las recargas en línea no están habilitadas en este momento. Puedes recargar en Caja.</p>
<?php endif; ?>

<div class="grid-main mt-24">
  <div class="stack">
    <section class="panel panel-flush">
      <div class="panel-head pad-head"><h2>Recargas en línea</h2></div>
      <?php if (!$recargas): ?>
        <p class="empty">Aún no has hecho recargas en línea.</p>
      <?php else: ?>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Fecha</th><th>Referencia</th><th class="num">Valor</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($recargas as $r): ?>
              <tr>
                <td class="nowrap"><?= e(fecha($r['creada_en'])) ?></td>
                <td class="mono"><a href="<?= e(url('mi-saldo/recargas/' . $r['id_recarga'])) ?>"><?= e($r['referencia']) ?></a>
                  <?php if ($r['id_transaccion']): ?><span class="sub">Transacción <?= e($r['id_transaccion']) ?></span><?php endif; ?></td>
                <td class="num"><?= e(money($r['monto'])) ?></td>
                <td><span class="badge" data-state="<?= e(!empty($r['reembolsada_en']) ? 'anulada' : (['APROBADA' => 'pagada', 'CREADA' => 'abierta', 'PENDIENTE' => 'abierta'][$r['estado']] ?? 'anulada')) ?>"><?= e(recarga_fila_label($r)) ?></span>
                  <?php if ($r['consecutivo']): ?><span class="sub mono"><?= e($r['consecutivo']) ?></span><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel panel-flush">
      <div class="panel-head pad-head"><h2>Movimientos de saldo</h2></div>
      <?php if (!$movimientos): ?>
        <p class="empty">Sin movimientos todavía.</p>
      <?php else: ?>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Fecha y hora</th><th>Movimiento</th><th class="num">Valor</th><th class="num">Saldo</th></tr></thead>
            <tbody>
            <?php foreach ($movimientos as $m): ?>
              <tr>
                <td class="nowrap"><?= e(fecha($m['creado_en'])) ?></td>
                <td><?= e($nombresMov[$m['tipo']] ?? $m['tipo']) ?>
                  <span class="sub"><?= e(trim(($m['medio_pago'] ? medio_label($m['medio_pago']) : '') . ($m['consecutivo'] ? ' · ' . $m['consecutivo'] : ''), ' ·')) ?></span>
                  <?php if ($m['referencia'] && $m['medio_pago'] === 'PASARELA'): ?><span class="sub mono"><?= e($m['referencia']) ?></span><?php endif; ?></td>
                <td class="num"><?= e((in_array($m['tipo'], ['DEBITO', 'REVERSO_RECARGA'], true) ? '− ' : '+ ') . money($m['monto'])) ?></td>
                <td class="num"><?= e(money($m['saldo_nuevo'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="panel panel-flush">
      <div class="panel-head pad-head"><h2>Cobros de parqueadero</h2></div>
      <?php if (!$cobros): ?>
        <p class="empty">Aún no tienes cobros.</p>
      <?php else: ?>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Fecha</th><th class="num">Valor</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($cobros as $c): ?>
              <tr>
                <td class="nowrap"><?= e(fecha($c['fecha'], 'd/m/Y')) ?><span class="sub"><?= e(tipo_label($c['tipo_vehiculo'])) ?><?= $c['concepto'] === 'DIFERENCIA' ? ' · diferencia' : '' ?></span></td>
                <td class="num"><?= e(money($c['valor'])) ?></td>
                <td><?= estado_badge($c['estado']) ?><?php if ($c['medio_pago']): ?><span class="sub"><?= e(medio_label($c['medio_pago'])) ?></span><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    <p class="field-hint">Si sales antes de <?= e(minutos_texto((int) config('tolerancia_minutos'))) ?> no se cobra. La salida de la portería solo verifica el pago: no se cobra en la salida.</p>
  </aside>
</div>
