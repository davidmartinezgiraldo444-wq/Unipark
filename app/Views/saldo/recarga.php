<?php
/** Detalle de una recarga: confirmar e ir a pagar, o ver su estado. El estado lo fija SOLO el servidor. */
$final = !in_array($recarga['estado'], ['CREADA', 'PENDIENTE'], true);
$tono = !empty($recarga['reembolsada_en']) ? 'warn' : (['APROBADA' => 'ok', 'CREADA' => 'warn', 'PENDIENTE' => 'warn'][$recarga['estado']] ?? 'err');
?>
<header class="page-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('mi-saldo')) ?>">Mi saldo</a> · Recarga</p>
    <h1><?= e(money($recarga['monto'])) ?></h1>
    <p class="mono">Referencia <?= e($recarga['referencia']) ?></p>
  </div>
</header>

<section class="panel" data-recarga data-id="<?= e($recarga['id_recarga']) ?>" data-final="<?= $final ? '1' : '0' ?>">
  <dl class="dl">
    <dt>Estado</dt><dd><span data-recarga-estado><?= e(recarga_label($recarga['estado'])) ?></span></dd>
    <dt>Creada</dt><dd><?= e(fecha($recarga['creada_en'])) ?></dd>
    <?php if ($recarga['estado'] === 'CREADA'): ?><dt>Pagar antes de</dt><dd><?= e(fecha($recarga['expira_en'])) ?></dd><?php endif; ?>
    <?php if ($recarga['aprobada_en']): ?><dt>Aprobada</dt><dd><?= e(fecha($recarga['aprobada_en'])) ?></dd><?php endif; ?>
    <?php if ($recarga['id_transaccion']): ?><dt>Transacción</dt><dd class="mono"><?= e($recarga['id_transaccion']) ?></dd><?php endif; ?>
    <?php if ($recarga['medio_externo']): ?><dt>Medio</dt><dd><?= e($recarga['medio_externo']) ?></dd><?php endif; ?>
    <dt>Saldo actual</dt><dd data-recarga-saldo><?= e(money($saldo)) ?></dd>
  </dl>

  <p class="alert alert-<?= e($tono) ?> mt-14" data-recarga-mensaje role="status">
    <?= e(match (true) {
        !empty($recarga['reembolsada_en']) => 'Recarga reembolsada el ' . fecha($recarga['reembolsada_en']) . ': el valor se descontó de tu saldo'
                                              . ($recarga['motivo_reembolso'] ? ' (' . $recarga['motivo_reembolso'] . ')' : '') . '. La devolución del dinero la hace la pasarela al mismo medio de pago.',
        $recarga['estado'] === 'APROBADA'  => 'Pago confirmado por la pasarela. El valor ya está en tu saldo.',
        $volvio && !$final                 => 'Estamos esperando la confirmación de la pasarela. Tu saldo se actualiza solo cuando el pago quede aprobado; no necesitas volver a pagar.',
        $recarga['estado'] === 'CREADA'    => 'Revisa el valor y continúa a la página de pago.',
        $recarga['estado'] === 'PENDIENTE' => 'El pago está en proceso. Tu saldo se actualiza solo cuando la pasarela lo apruebe.',
        default                            => ($recarga['detalle_estado'] ?: recarga_label($recarga['estado'])) . ' No se acreditó saldo.',
    }) ?>
  </p>

  <?php if ($formulario !== null): ?>
    <!-- La pasarela recibe solo la referencia, el valor y la firma de integridad (nunca secretos). -->
    <form class="form-actions mt-14" method="<?= e(strtolower($formulario['metodo'])) ?>" action="<?= e($formulario['url']) ?>">
      <?php foreach ($formulario['campos'] as $nombre => $valor): ?>
        <input type="hidden" name="<?= e($nombre) ?>" value="<?= e($valor) ?>">
      <?php endforeach; ?>
      <button class="btn btn-ink" type="submit">Pagar <?= e(money($recarga['monto'])) ?> en la pasarela</button>
    </form>
    <form class="mt-8" method="post" action="<?= e(url('mi-saldo/recargas/' . $recarga['id_recarga'] . '/cancelar')) ?>">
      <?= csrf_field() ?>
      <button class="link-btn link-btn-muted" type="submit">Cancelar esta recarga</button>
    </form>
  <?php endif; ?>
</section>

<p class="mt-14"><a href="<?= e(url('mi-saldo')) ?>">← Volver a Mi saldo</a><?php if ($final && $recarga['estado'] !== 'APROBADA'): ?> · <a href="<?= e(url('mi-saldo/recargar?monto=' . (int) $recarga['monto'])) ?>">Intentar de nuevo</a><?php endif; ?></p>
