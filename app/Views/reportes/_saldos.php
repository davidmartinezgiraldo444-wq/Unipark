<?php /** Saldo y valor pendiente por persona. */ $sumaSaldo = 0.0; $sumaPend = 0.0; ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Persona</th><th>Categoría</th><th>Último movimiento</th><th class="num">Pendiente</th><th class="num">Saldo</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $p): $sumaSaldo += (float) $p['saldo']; $sumaPend += (float) $p['pendiente']; ?>
      <tr<?= (int) $p['activo'] !== 1 ? ' class="is-off"' : '' ?>>
        <td><a href="<?= e(url('estudiantes/' . $p['id_estudiante'])) ?>"><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></a><span class="sub mono"><?= e(mask_doc($p['documento'])) ?></span></td>
        <td><?= categoria_tag($p['categoria']) ?></td>
        <td class="nowrap"><?= $p['ultimo_movimiento'] ? e(fecha($p['ultimo_movimiento'])) : '—' ?></td>
        <td class="num"><?= (float) $p['pendiente'] > 0 ? '<span class="text-err">' . e(money($p['pendiente'])) . '</span>' : '—' ?></td>
        <td class="num"><?= e(money($p['saldo'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="3">Totales</td><td class="num"><?= e(money($sumaPend)) ?></td><td class="num"><?= e(money($sumaSaldo)) ?></td></tr></tfoot>
  </table>
</div>
