<?php /** Panel del simulador (Administración): transacciones simuladas, resolver pendientes y reenviar notificaciones. */ ?>
<header class="page-head">
  <div>
    <p class="eyebrow">Demostración · Pasarela simulada</p>
    <h1>Transacciones simuladas</h1>
    <p>Hace el papel del banco: aprueba o rechaza un pago que quedó pendiente (por ejemplo, un PSE en proceso) o reenvía una notificación para mostrar que nunca se acredita dos veces.</p>
  </div>
</header>

<p class="aviso-simulacion" role="note"><strong>Simulación académica.</strong> Estas transacciones no existen en ninguna pasarela real.</p>
<?php if ($mensaje !== ''): ?><p class="alert" role="status"><?= e($mensaje) ?></p><?php endif; ?>

<section class="panel panel-flush">
  <?php if (!$transacciones): ?>
    <p class="empty">Aún no hay transacciones simuladas.</p>
  <?php else: ?>
    <div class="table-wrap sin-borde">
      <table class="table">
        <thead><tr><th>Transacción</th><th>Referencia</th><th class="num">Valor</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($transacciones as $t): ?>
          <tr>
            <td class="mono"><?= e($t['id']) ?><span class="sub"><?= e(fecha($t['created_at'])) ?></span></td>
            <td class="mono"><?= e($t['reference']) ?></td>
            <td class="num"><?= e(money((int) $t['amount_in_cents'] / 100)) ?></td>
            <td class="mono"><?= e($t['status']) ?></td>
            <td>
              <form method="post" action="<?= e(url('simulador-pago/accion')) ?>" class="sim-acciones">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                <?php if ($t['status'] === 'PENDING'): ?>
                  <button class="btn btn-ink btn-sm" type="submit" name="accion" value="aprobar">Aprobar ahora</button>
                  <button class="btn btn-line btn-sm" type="submit" name="accion" value="rechazar">Rechazar</button>
                <?php endif; ?>
                <?php if ($t['status'] === 'APPROVED'): ?>
                  <button class="btn btn-line btn-sm" type="submit" name="accion" value="reembolsar"
                          title="Como si la pasarela anulara el pago y devolviera el dinero (VOIDED)">Simular reembolso del proveedor</button>
                <?php endif; ?>
                <button class="btn btn-line btn-sm" type="submit" name="accion" value="reenviar">Reenviar notificación</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
