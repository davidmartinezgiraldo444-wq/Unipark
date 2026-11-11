<?php
/** Historial financiero de la persona: saldo, cobros diarios (con anulación) y movimientos (con reverso). */
$nombresMov = ['RECARGA' => 'Recarga en Caja', 'DEBITO' => 'Débito automático', 'REINTEGRO' => 'Reintegro', 'REVERSO_RECARGA' => 'Reverso de recarga'];
$minMotivo = (int) config('motivo_min_longitud');
?>
<?php if ($estudiante['categoria'] !== 'VISITANTE'): ?>
<div class="kpis mt-24">
  <div class="kpi"><span class="kpi-label">Saldo</span><span class="kpi-value"><?= e(money($estudiante['saldo'])) ?></span></div>
  <div class="kpi"><span class="kpi-label">Programa o dependencia</span><span class="kpi-value kpi-texto"><?= e($estudiante['programa'] ?: '—') ?></span>
    <span class="kpi-note"><?= $estudiante['origen'] === 'INSTITUCIONAL' ? 'Base institucional' . ($estudiante['sincronizado_en'] ? ' · ' . e(fecha($estudiante['sincronizado_en'])) : '') : 'Registro manual' ?></span></div>
</div>
<?php endif; ?>

<section class="panel panel-flush mt-24">
  <div class="panel-head pad-head"><h2>Cobros diarios</h2></div>
  <?php if (!$cobros): ?>
    <p class="empty">Sin cobros diarios.</p>
  <?php else: ?>
    <div class="table-wrap sin-borde">
      <table class="table">
        <thead><tr><th>Día</th><th>Vehículo</th><th>Concepto</th><th>Estado</th><th>Pago</th><th class="num">Valor</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cobros as $c): ?>
          <tr<?= $c['estado'] === 'ANULADO' ? ' class="is-off"' : '' ?>>
            <td class="nowrap"><?= e(fecha($c['fecha'], 'd/m/Y')) ?></td>
            <td><?= e(tipo_label($c['tipo_vehiculo'])) ?></td>
            <td><?= $c['concepto'] === 'DIFERENCIA' ? 'Diferencia' : 'Cobro del día' ?></td>
            <td><?= estado_badge($c['estado']) ?><?php if ($c['motivo_anulacion']): ?><span class="sub"><?= e($c['motivo_anulacion']) ?></span><?php endif; ?></td>
            <td class="mono"><?= $c['consecutivo'] ? e($c['consecutivo']) : '—' ?></td>
            <td class="num"><?= e(money($c['valor'])) ?></td>
            <td class="actions">
              <?php if ($c['estado'] === 'PENDIENTE' && can('COBRO_ANULAR')): ?>
                <details class="anular">
                  <summary>Anular cobro</summary>
                  <form method="post" action="<?= e(url('cobros/' . $c['id_cobro'] . '/anular')) ?>"
                        data-confirm="¿Anular este cobro? La fila se conserva con el motivo y queda en la auditoría.">
                    <?= csrf_field() ?>
                    <label for="motivo-c<?= e($c['id_cobro']) ?>">Motivo (obligatorio)</label>
                    <textarea id="motivo-c<?= e($c['id_cobro']) ?>" name="motivo" required minlength="<?= $minMotivo ?>" maxlength="255"></textarea>
                    <button class="btn btn-danger btn-sm" type="submit">Anular cobro</button>
                  </form>
                </details>
              <?php elseif ($c['estado'] === 'PAGADO' && $c['id_pago'] && can('PAGO_ANULAR')): ?>
                <span class="sub">Para anular, anula primero el pago <?= e($c['consecutivo']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel panel-flush mt-24">
  <div class="panel-head pad-head"><h2>Movimientos de saldo</h2></div>
  <?php if (!$movimientos): ?>
    <p class="empty">Sin movimientos de saldo.</p>
  <?php else: ?>
    <div class="table-wrap sin-borde">
      <table class="table">
        <thead><tr><th>Fecha</th><th>Movimiento</th><th>Registró</th><th class="num">Valor</th><th class="num">Saldo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($movimientos as $m): ?>
          <tr<?= $m['id_reverso'] ? ' class="is-off"' : '' ?>>
            <td class="nowrap"><?= e(fecha($m['creado_en'])) ?><span class="sub mono"><?= e($m['consecutivo'] ?: '#' . $m['id_movimiento']) ?></span></td>
            <td><?= e($nombresMov[$m['tipo']] ?? $m['tipo']) ?><?php if ($m['motivo']): ?><span class="sub"><?= e($m['motivo']) ?></span><?php endif; ?><?php if ($m['id_reverso']): ?><span class="sub">Reversada</span><?php endif; ?></td>
            <td><?= e($m['cajero']) ?></td>
            <td class="num"><?= e((in_array($m['tipo'], ['DEBITO', 'REVERSO_RECARGA'], true) ? '− ' : '+ ') . money($m['monto'])) ?></td>
            <td class="num"><?= e(money($m['saldo_nuevo'])) ?></td>
            <td class="actions">
              <?php if ($m['tipo'] === 'RECARGA' && !$m['id_reverso'] && can('SALDO_REVERSAR')): ?>
                <details class="anular">
                  <summary>Reversar recarga</summary>
                  <form method="post" action="<?= e(url('saldo/' . $m['id_movimiento'] . '/reversar')) ?>"
                        data-confirm="¿Reversar esta recarga de <?= e(money($m['monto'])) ?>? Se crea un movimiento de reverso; la recarga se conserva.">
                    <?= csrf_field() ?>
                    <label for="motivo-m<?= e($m['id_movimiento']) ?>">Motivo (obligatorio)</label>
                    <textarea id="motivo-m<?= e($m['id_movimiento']) ?>" name="motivo" required minlength="<?= $minMotivo ?>" maxlength="255"></textarea>
                    <button class="btn btn-danger btn-sm" type="submit">Reversar</button>
                  </form>
                </details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
