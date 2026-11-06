<header class="page-head">
  <div>
    <p class="eyebrow">Caja</p>
    <h1>Cobro de visitas</h1>
    <p>Visitas abiertas sin pago confirmado. El valor es la tarifa fija aplicada al entrar; no depende del tiempo de permanencia.</p>
  </div>
  <div class="actions">
    <?php if (can('CAJA_CIERRE')): ?><a class="btn btn-line" href="<?= e(url('caja/cierre')) ?>">Cierre de caja</a><?php endif; ?>
  </div>
</header>

<div class="grid-main">
  <section class="panel panel-flush">
    <div class="panel-head">
      <h2>Pendientes de pago</h2>
      <span class="muted"><?= count($pendientes) ?> visitas</span>
    </div>
    <form class="search filters-inline" method="get" action="<?= e(url('caja')) ?>" role="search">
      <div class="field">
        <label for="buscar" class="sr-only">Buscar por documento o nombre</label>
        <input id="buscar" name="buscar" type="search" value="<?= e($buscar) ?>" placeholder="Documento o nombre" maxlength="60">
      </div>
      <button class="btn btn-line" type="submit">Buscar</button>
    </form>

    <?php if (!$pendientes): ?>
      <p class="empty"><?= $buscar !== '' ? 'Ninguna visita pendiente coincide con la búsqueda.' : 'No hay visitas pendientes de pago.' ?></p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Persona</th><th>Tipo</th><th>Entrada</th><th class="num">Valor</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($pendientes as $v): $sel = $seleccion && $seleccion['id_visita'] === $v['id_visita']; ?>
            <tr<?= $sel ? ' class="is-selected"' : '' ?>>
              <td><?= e($v['nombres'] . ' ' . $v['apellidos']) ?><span class="sub mono"><?= e($v['documento']) ?></span></td>
              <td class="mono"><?= e($v['tipo_vehiculo']) ?><span class="sub"><?= e(categoria_label($v['categoria'])) ?></span></td>
              <td class="nowrap"><?= e(fecha($v['entrada_en'], 'H:i')) ?><span class="sub">hace <?= e(duracion($v['entrada_en'])) ?></span></td>
              <td class="num"><?= e(money($v['tarifa_aplicada'])) ?></td>
              <td class="actions">
                <a class="btn btn-sm <?= $sel ? 'btn-ink' : 'btn-line' ?>"
                   href="<?= e(url('caja?visita=' . $v['id_visita'] . ($buscar !== '' ? '&buscar=' . rawurlencode($buscar) : ''))) ?>">Cobrar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <aside class="stack">
    <section class="panel" id="cobro">
      <div class="panel-head"><h2>Registrar pago</h2></div>
      <?php if (!$seleccion): ?>
        <p class="muted">Selecciona una visita pendiente para cobrarla.</p>
      <?php else: ?>
        <dl class="dl">
          <dt>Nombre</dt><dd><?= e($seleccion['nombres'] . ' ' . $seleccion['apellidos']) ?></dd>
          <dt>Tipo</dt><dd><span class="mono"><?= e($seleccion['tipo_vehiculo']) ?></span> · <?= categoria_tag($seleccion['categoria']) ?></dd>
          <dt>Entrada</dt><dd><?= e(fecha($seleccion['entrada_en'])) ?></dd>
          <dt>Valor a pagar</dt><dd class="total" data-monto="<?= e($seleccion['tarifa_aplicada']) ?>"><?= e(money($seleccion['tarifa_aplicada'])) ?></dd>
        </dl>

        <form method="post" action="<?= e(url('caja/pago')) ?>" class="stack panel-note" data-pago-form
              data-confirm="¿Confirmar el pago de <?= e(money($seleccion['tarifa_aplicada'])) ?> de <?= e($seleccion['nombres']) ?>?">
          <?= csrf_field() ?>
          <input type="hidden" name="id_visita" value="<?= e($seleccion['id_visita']) ?>">

          <div class="field">
            <label for="medio_pago">Medio de pago</label>
            <select id="medio_pago" name="medio_pago" required>
              <?php foreach ($medios as $codigo => $nombre): ?>
                <option value="<?= e($codigo) ?>"><?= e($nombre) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field" data-referencia hidden>
            <label for="referencia">Referencia o autorización <span class="muted">(opcional)</span></label>
            <input id="referencia" name="referencia" type="text" maxlength="60" autocomplete="off">
            <span class="field-hint">No escribas números de tarjeta.</span>
          </div>

          <div class="field" data-efectivo>
            <label for="recibido">Efectivo recibido <span class="muted">(solo para calcular el cambio)</span></label>
            <input id="recibido" type="text" inputmode="numeric" autocomplete="off">
            <p class="cambio" data-cambio hidden><span>Cambio</span><strong></strong></p>
          </div>

          <button class="btn btn-ink btn-block" type="submit">Confirmar pago</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Mis pagos de hoy</h2></div>
      <?php if (!$mios): ?>
        <p class="muted">Aún no registras pagos hoy.</p>
      <?php else: ?>
        <dl class="dl">
          <?php foreach ($mios as $m): ?>
            <dt><?= e($medios[$m['medio_pago']] ?? $m['medio_pago']) ?> · <?= e($m['cantidad']) ?></dt>
            <dd><?= e(money($m['total'])) ?></dd>
          <?php endforeach; ?>
        </dl>
      <?php endif; ?>
    </section>
  </aside>
</div>
