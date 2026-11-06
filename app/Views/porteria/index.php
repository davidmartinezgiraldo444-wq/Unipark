<header class="page-head">
  <div>
    <p class="eyebrow">Portería</p>
    <h1>Entrada y salida</h1>
    <p>Busca a la persona por su documento. Si no tiene visita abierta, registra la entrada; si la tiene, verifica el pago y autoriza la salida. La tarifa depende del vehículo y de si es estudiante o profesor.</p>
  </div>
</header>

<div class="grid-main">
  <div class="stack">
    <section class="panel">
      <form class="search" method="get" action="<?= e(url('porteria')) ?>" role="search" data-documento-form>
        <div class="field">
          <label for="documento">Documento del estudiante o profesor</label>
          <input id="documento" name="documento" type="text" inputmode="numeric" autocomplete="off"
                 value="<?= e($documento) ?>" maxlength="20" required autofocus
                 <?= $error ? 'aria-invalid="true" aria-describedby="doc-error"' : '' ?>>
        </div>
        <button class="btn btn-ink" type="submit">Buscar</button>
      </form>
      <?php if ($error): ?>
        <p class="block-msg err" id="doc-error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>
    </section>

    <?php if ($estudiante && (int) $estudiante['activo'] === 1): ?>
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2><?= e($estudiante['nombres'] . ' ' . $estudiante['apellidos']) ?></h2>
            <span class="muted mono">CC <?= e($estudiante['documento']) ?></span> <?= categoria_tag($estudiante['categoria']) ?>
          </div>
          <?= $visita ? estado_badge('EN_CURSO') : '<span class="badge">SIN VISITA ABIERTA</span>' ?>
        </div>

        <?php if ($visita): ?>
          <div class="timeline">
            <div>
              <span class="k">Entrada</span>
              <span class="v"><?= e(fecha($visita['entrada_en'], 'H:i')) ?> · <?= e($visita['tipo_vehiculo']) ?></span>
              <span class="s"><?= e(fecha($visita['entrada_en'], 'd/m/Y')) ?> · hace <?= e(duracion($visita['entrada_en'])) ?></span>
            </div>
            <div class="<?= $visita['pagada'] ? '' : 'pending' ?>">
              <span class="k">Pago</span>
              <?php if ($visita['pagada']): ?>
                <span class="v"><?= e(money($visita['tarifa_aplicada'])) ?> · <?= e($visita['consecutivo']) ?></span>
                <span class="s"><?= e(fecha($visita['pagado_en'], 'H:i')) ?> · <?= e(config('medios_pago')[$visita['medio_pago']] ?? $visita['medio_pago']) ?></span>
              <?php else: ?>
                <span class="v">Pendiente</span>
                <span class="s">Valor <?= e(money($visita['tarifa_aplicada'])) ?></span>
              <?php endif; ?>
            </div>
            <div>
              <span class="k">Salida</span>
              <span class="v">—</span>
              <span class="s">Registró entrada: <?= e($visita['entrada_por_nombre']) ?></span>
            </div>
          </div>

          <?php if (!$visita['pagada']): ?>
            <p class="block-msg err">Pago pendiente. Diríjase a Caja.</p>
          <?php endif; ?>

          <?php if (can('SALIDA_AUTORIZAR')): ?>
            <form method="post" action="<?= e(url('porteria/salida')) ?>" class="form-actions"
                  data-confirm="¿Autorizar la salida de <?= e($estudiante['nombres']) ?>? El sistema verificará el pago antes de cerrar la visita.">
              <?= csrf_field() ?>
              <input type="hidden" name="id_visita" value="<?= e($visita['id_visita']) ?>">
              <input type="hidden" name="documento" value="<?= e($estudiante['documento']) ?>">
              <button class="btn <?= $visita['pagada'] ? 'btn-ok' : 'btn-line' ?>" type="submit">
                <?= $visita['pagada'] ? 'Autorizar salida' : 'Verificar pago y autorizar salida' ?>
              </button>
            </form>
          <?php endif; ?>

        <?php elseif (can('ENTRADA_CREAR')): ?>
          <form method="post" action="<?= e(url('porteria/entrada')) ?>" data-entrada-form novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id_estudiante" value="<?= e($estudiante['id_estudiante']) ?>">
            <input type="hidden" name="documento" value="<?= e($estudiante['documento']) ?>">

            <fieldset class="tipo-choice">
              <legend>Tipo de vehículo</legend>
              <?php foreach ($tarifas as $tipo => $porCategoria): $t = $porCategoria[$estudiante['categoria']] ?? null; ?>
                <label>
                  <input type="radio" name="tipo" value="<?= e($tipo) ?>" required <?= $t ? '' : 'disabled' ?>>
                  <span class="t"><?= e($tipo) ?></span>
                  <span class="v"><?= $t ? e(money($t['valor'])) : 'Sin tarifa' ?></span>
                </label>
              <?php endforeach; ?>
            </fieldset>
            <p class="field-hint" data-tipo-hint>Tarifa de <?= e(mb_strtolower(categoria_label($estudiante['categoria']))) ?>. Sin campo de placa: solo se registra el tipo de vehículo.</p>

            <div class="form-actions">
              <button class="btn btn-ink" type="submit">Registrar entrada</button>
            </div>
          </form>
        <?php else: ?>
          <p class="muted">El estudiante no tiene una visita abierta.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Dentro ahora</h2></div>
      <dl class="dl">
        <dt>Carros</dt><dd><?= e($ocupacion['CARRO']) ?></dd>
        <dt>Motos</dt><dd><?= e($ocupacion['MOTO']) ?></dd>
        <dt>Pendientes de pago</dt><dd><?= e($ocupacion['pendientes']) ?></dd>
      </dl>
    </section>
    <section class="panel panel-flush">
      <div class="panel-head"><h2>Últimas entradas</h2></div>
      <?php if (!$recientes): ?>
        <p class="empty">Sin visitas abiertas.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <tbody>
            <?php foreach ($recientes as $v): ?>
              <tr>
                <td><?= e($v['nombres']) ?><span class="sub mono"><?= e(mask_doc($v['documento'])) ?> · <?= e($v['tipo_vehiculo']) ?> · <?= e(categoria_label($v['categoria'])) ?></span></td>
                <td class="num"><?= e(fecha($v['entrada_en'], 'H:i')) ?><span class="sub"><?= $v['pagada'] ? 'Pagada' : 'Pendiente' ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </aside>
</div>
