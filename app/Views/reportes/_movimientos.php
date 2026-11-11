<?php /** Historial de movimientos de saldo. */
$nombresMov = ['RECARGA' => 'Recarga', 'DEBITO' => 'Débito automático', 'REINTEGRO' => 'Reintegro', 'REVERSO_RECARGA' => 'Reverso de recarga']; ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Fecha</th><th>Persona</th><th>Movimiento</th><th>Registró</th><th class="num">Valor</th><th class="num">Saldo</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $m): ?>
      <tr<?= $m['id_reverso'] ? ' class="is-off"' : '' ?>>
        <td class="nowrap"><?= e(fecha($m['creado_en'])) ?><span class="sub mono"><?= e($m['consecutivo'] ?: '#' . $m['id_movimiento']) ?></span></td>
        <td><a href="<?= e(url('estudiantes/' . $m['id_estudiante'])) ?>"><?= e($m['nombres'] . ' ' . $m['apellidos']) ?></a><span class="sub mono"><?= e(mask_doc($m['documento'])) ?></span></td>
        <td><?= e($nombresMov[$m['tipo']] ?? $m['tipo']) ?><?php if ($m['medio_pago']): ?><span class="sub"><?= e(medio_label($m['medio_pago'])) ?><?= $m['medio_pago'] === 'PASARELA' && $m['referencia'] ? ' · ' . e($m['referencia']) : '' ?></span><?php endif; ?>
          <?php if ($m['motivo']): ?><span class="sub"><?= e($m['motivo']) ?></span><?php endif; ?>
          <?php if ($m['id_reverso']): ?><span class="sub">Reversada</span><?php endif; ?></td>
        <td><?= e($m['cajero']) ?></td>
        <td class="num"><?= e((in_array($m['tipo'], ['DEBITO', 'REVERSO_RECARGA'], true) ? '− ' : '+ ') . money($m['monto'])) ?></td>
        <td class="num"><?= e(money($m['saldo_nuevo'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
