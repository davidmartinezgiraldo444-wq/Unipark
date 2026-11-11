<?php /** Notificaciones y consultas de la pasarela que requieren revisión (firma inválida, monto distinto, errores). */ ?>
<section class="panel panel-flush mt-24">
  <div class="panel-head pad-head">
    <h2>Conciliación: casos para revisar</h2>
  </div>
  <?php if (!$problemas): ?>
    <p class="empty">Sin casos pendientes de revisión en el periodo.</p>
  <?php else: ?>
    <div class="table-wrap sin-borde">
      <table class="table">
        <thead><tr><th>Recibida</th><th>Origen</th><th>Resultado</th><th>Transacción</th><th>Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($problemas as $n): ?>
          <tr>
            <td class="nowrap"><?= e(fecha($n['recibida_en'])) ?></td>
            <td><?= e($n['origen'] === 'WEBHOOK' ? 'Webhook' : 'Consulta') ?><span class="sub"><?= e($n['proveedor']) ?><?= $n['firma_valida'] ? '' : ' · firma no válida' ?></span></td>
            <td class="mono"><?= e($n['resultado']) ?></td>
            <td class="mono"><?= e($n['id_transaccion'] ?? '—') ?><span class="sub"><?= e($n['referencia_recarga'] ?? $n['referencia'] ?? '') ?></span>
              <?php if ($n['estado_reportado']): ?><span class="sub"><?= e($n['estado_reportado']) ?><?= $n['monto_centavos'] !== null ? ' · ' . e(money((int) $n['monto_centavos'] / 100)) : '' ?></span><?php endif; ?></td>
            <td><?= e($n['mensaje'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <p class="field-hint pad-nota">REQUIERE_REVISION: la pasarela reportó otra transacción aprobada o un reverso después de acreditar. El saldo no se modifica solo: si corresponde, Administración registra un reverso de la recarga (movimiento compensatorio, con motivo).</p>
</section>
