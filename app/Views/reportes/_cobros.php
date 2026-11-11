<?php /** Reporte de cobros diarios (incluye diferencias por cambio de vehículo). */ $suma = 0.0; ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Día</th><th>Persona</th><th>Vehículo</th><th>Concepto</th><th>Estado</th><th>Pago</th><th class="num">Valor</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $c): if ($c['estado'] !== 'ANULADO') { $suma += (float) $c['valor']; } ?>
      <tr<?= $c['estado'] === 'ANULADO' ? ' class="is-off"' : '' ?>>
        <td class="nowrap"><?= e(fecha($c['fecha'], 'd/m/Y')) ?><span class="sub">#<?= e($c['id_cobro']) ?></span></td>
        <td><a href="<?= e(url('estudiantes/' . $c['id_estudiante'])) ?>"><?= e($c['nombres'] . ' ' . $c['apellidos']) ?></a><span class="sub mono"><?= e(mask_doc($c['documento'])) ?></span></td>
        <td><?= e(tipo_label($c['tipo_vehiculo'])) ?><span class="sub"><?= e(categoria_label($c['categoria'])) ?></span></td>
        <td><?= $c['concepto'] === 'DIFERENCIA' ? 'Diferencia' : 'Cobro del día' ?></td>
        <td><?= estado_badge($c['estado']) ?><?php if ($c['motivo_anulacion']): ?><span class="sub"><?= e($c['motivo_anulacion']) ?></span><?php endif; ?></td>
        <td class="mono"><?= $c['consecutivo'] ? e($c['consecutivo'] . ' · ' . ($c['medio_pago'] === 'SALDO' ? 'Saldo' : $c['medio_pago'])) : '—' ?></td>
        <td class="num"><?= e(money($c['valor'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="6">Total (sin anulados)</td><td class="num"><?= e(money($suma)) ?></td></tr></tfoot>
  </table>
</div>
