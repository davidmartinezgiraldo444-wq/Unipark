<?php $gestiona = can('ESTUDIANTE_GESTIONAR'); ?>
<header class="page-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('estudiantes')) ?>">Estudiantes y profesores</a> · Historial</p>
    <h1><?= e($estudiante['nombres'] . ' ' . $estudiante['apellidos']) ?></h1>
    <p class="mono"><?= categoria_tag($estudiante['categoria']) ?> <?= e($gestiona ? $estudiante['documento'] : mask_doc($estudiante['documento'])) ?>
      · <?= (int) $estudiante['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
      <?php if ($estudiante['correo']): ?> · <?= e($estudiante['correo']) ?><?php endif; ?></p>
  </div>
  <?php if ($gestiona): ?>
    <div class="actions"><a class="btn btn-line" href="<?= e(url('estudiantes/' . $estudiante['id_estudiante'] . '/editar')) ?>">Editar datos</a></div>
  <?php endif; ?>
</header>

<section class="panel panel-flush">
  <div class="panel-head"><h2>Visitas</h2><span class="muted"><?= count($visitas) ?> registradas</span></div>
  <?php if (!$visitas): ?>
    <p class="empty">Sin visitas registradas.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Entrada</th><th>Tipo</th><th class="num">Tarifa</th><th>Pagos</th><th>Salida</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($visitas as $v): ?>
          <tr>
            <td class="nowrap"><?= e(fecha($v['entrada_en'])) ?><span class="sub"><?= e($v['entrada_por_nombre']) ?></span></td>
            <td class="mono"><?= e($v['tipo_vehiculo']) ?><span class="sub"><?= e(categoria_label($v['categoria'])) ?></span></td>
            <td class="num"><?= e(money($v['tarifa_aplicada'])) ?></td>
            <td>
              <?php if (!$v['pagos']): ?>
                <span class="muted">Sin pago</span>
              <?php endif; ?>
              <?php foreach ($v['pagos'] as $p): ?>
                <div class="nowrap">
                  <?php if (can('PAGO_REGISTRAR', 'REPORTE_CAJA')): ?>
                    <a class="mono" href="<?= e(url('caja/recibo/' . $p['id_pago'])) ?>"><?= e($p['consecutivo']) ?></a>
                  <?php else: ?>
                    <span class="mono"><?= e($p['consecutivo']) ?></span>
                  <?php endif; ?>
                  <?= estado_badge($p['estado']) ?>
                  <span class="sub"><?= e(fecha($p['pagado_en'])) ?> · <?= e($p['cajero']) ?></span>
                  <?php if ($p['estado'] === 'ANULADO'): ?><span class="sub">Motivo: <?= e($p['motivo_anulacion']) ?></span><?php endif; ?>
                  <?php if ($p['estado'] === 'PAGADO' && can('PAGO_ANULAR')): ?>
                    <details class="anular">
                      <summary>Anular pago</summary>
                      <form method="post" action="<?= e(url('pagos/' . $p['id_pago'] . '/anular')) ?>"
                            data-confirm="¿Anular el pago <?= e($p['consecutivo']) ?>? Queda registrado con el motivo y no se puede deshacer.">
                        <?= csrf_field() ?>
                        <label for="motivo-p<?= e($p['id_pago']) ?>">Motivo (obligatorio)</label>
                        <textarea id="motivo-p<?= e($p['id_pago']) ?>" name="motivo" required minlength="<?= (int) config('motivo_min_longitud') ?>" maxlength="255"></textarea>
                        <button class="btn btn-danger btn-sm" type="submit">Anular pago</button>
                      </form>
                    </details>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </td>
            <td class="nowrap">
              <?php if ($v['salida_en']): ?>
                <?= e(fecha($v['salida_en'])) ?><span class="sub"><?= e($v['salida_por_nombre']) ?> · <?= e(duracion($v['entrada_en'], $v['salida_en'])) ?></span>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?= estado_badge($v['estado']) ?>
              <?php if ($v['estado'] === 'ANULADA'): ?><span class="sub"><?= e($v['motivo_anulacion']) ?></span><?php endif; ?>
            </td>
            <td class="actions">
              <?php if ($v['estado'] === 'EN_CURSO' && !$v['pagada'] && can('VISITA_ANULAR')): ?>
                <details class="anular">
                  <summary>Anular visita</summary>
                  <form method="post" action="<?= e(url('visitas/' . $v['id_visita'] . '/anular')) ?>"
                        data-confirm="¿Anular esta visita? La fila se conserva con el motivo y queda en la auditoría.">
                    <?= csrf_field() ?>
                    <label for="motivo-v<?= e($v['id_visita']) ?>">Motivo (obligatorio)</label>
                    <textarea id="motivo-v<?= e($v['id_visita']) ?>" name="motivo" required minlength="<?= (int) config('motivo_min_longitud') ?>" maxlength="255"></textarea>
                    <button class="btn btn-danger btn-sm" type="submit">Anular visita</button>
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
