<?php /** Recargas en línea: operación, referencia interna, transacción de la pasarela y comprobante del saldo. */
$aprobado = 0.0; ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Creada</th><th>Persona</th><th>Referencia</th><th>Pasarela</th><th>Estado</th><th class="num">Valor</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $r): if ($r['estado'] === 'APROBADA' && empty($r['reembolsada_en'])) { $aprobado += (float) $r['monto']; } ?>
      <tr<?= in_array($r['estado'], ['CANCELADA', 'EXPIRADA'], true) ? ' class="is-off"' : '' ?>>
        <td class="nowrap"><?= e(fecha($r['creada_en'])) ?><?php if ($r['aprobada_en']): ?><span class="sub">Aprobada <?= e(fecha($r['aprobada_en'])) ?></span><?php endif; ?></td>
        <td><a href="<?= e(url('estudiantes/' . $r['id_estudiante'])) ?>"><?= e($r['nombres'] . ' ' . $r['apellidos']) ?></a><span class="sub mono"><?= e(mask_doc($r['documento'])) ?></span></td>
        <td class="mono"><?= e($r['referencia']) ?><?php if ($r['consecutivo']): ?><span class="sub">Comprobante <?= e($r['consecutivo']) ?></span><?php endif; ?></td>
        <td><?= e($r['proveedor']) ?> · <?= e(mb_strtolower($r['entorno'])) ?>
          <?php if ($r['id_transaccion']): ?><span class="sub mono"><?= e($r['id_transaccion']) ?></span><?php endif; ?>
          <?php if ($r['medio_externo']): ?><span class="sub"><?= e($r['medio_externo']) ?></span><?php endif; ?></td>
        <td><?= estado_badge($r['estado']) ?><?php if (!empty($r['reembolsada_en'])): ?><span class="sub text-err">Reembolsada <?= e(fecha($r['reembolsada_en'])) ?><?= $r['motivo_reembolso'] ? ' · ' . e($r['motivo_reembolso']) : '' ?></span><?php endif; ?><?php if ($r['detalle_estado']): ?><span class="sub"><?= e($r['detalle_estado']) ?></span><?php endif; ?></td>
        <td class="num"><?= e(money($r['monto'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="5">Total acreditado (aprobadas)</td><td class="num"><?= e(money($aprobado)) ?></td></tr></tfoot>
  </table>
</div>
