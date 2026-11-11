<?php
$medioSelect = static function (string $id) use ($medios): string {
    $html = '<select id="' . e($id) . '" name="medio_pago" required>';
    foreach ($medios as $codigo => $nombre) {
        $html .= '<option value="' . e($codigo) . '">' . e($nombre) . '</option>';
    }
    return $html . '</select>';
};
?>
<header class="page-head">
  <div>
    <p class="eyebrow">Caja</p>
    <h1>Cobros y recargas</h1>
    <p>El cobro es uno por persona y por día, y se genera solo al vencer los <?= e(minutos_texto((int) config('tolerancia_minutos'))) ?> de tolerancia. Si la persona tiene saldo se debita automáticamente; aquí aparecen los que quedaron pendientes por falta de saldo.</p>
  </div>
  <div class="actions">
    <?php if (can('CAJA_CIERRE')): ?><a class="btn btn-line" href="<?= e(url('caja/cierre')) ?>">Cierre de caja</a><?php endif; ?>
  </div>
</header>

<div class="grid-main">
  <div class="stack">
    <?php if ($incidencias): ?>
      <section class="panel panel-flush panel-incidencia">
        <div class="panel-head pad-head">
          <h2>Incidencias de cobro</h2>
          <span class="muted"><?= count($incidencias) ?></span>
        </div>
        <p class="muted pad-nota">La tolerancia venció y el cobro automático aún no se procesó (revisa el cron). La salida no cobra: resuélvelas aquí.</p>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Persona</th><th>Vehículo</th><th>Tolerancia vencida</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($incidencias as $i): ?>
              <tr>
                <td><?= e(trim($i['nombres'] . ' ' . $i['apellidos'])) ?><span class="sub mono"><?= e($i['documento']) ?></span></td>
                <td><?= e(tipo_label($i['tipo_vehiculo'])) ?><span class="sub"><?= e(categoria_label($i['categoria'])) ?></span></td>
                <td class="nowrap"><?= e(fecha($i['tolerancia_hasta'], 'H:i')) ?><span class="sub">hace <?= e(duracion($i['tolerancia_hasta'])) ?></span></td>
                <td class="actions">
                  <form method="post" action="<?= e(url('cobros/visita/' . $i['id_visita'] . '/procesar')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="volver" value="caja">
                    <button class="btn btn-sm btn-ink" type="submit">Procesar cobro</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
    <section class="panel panel-flush">
      <div class="panel-head pad-head">
        <h2>Cobros diarios pendientes</h2>
        <span class="muted"><?= count($cobros) ?></span>
      </div>
      <form class="search filters-inline pt-14" method="get" action="<?= e(url('caja')) ?>" role="search">
        <div class="field">
          <label for="buscar" class="sr-only">Buscar por documento o nombre</label>
          <input id="buscar" name="buscar" type="search" value="<?= e($buscar) ?>" placeholder="Documento o nombre" maxlength="60">
        </div>
        <button class="btn btn-line" type="submit">Buscar</button>
      </form>

      <?php if (!$cobros): ?>
        <p class="empty"><?= $buscar !== '' ? 'Ningún cobro pendiente coincide con la búsqueda.' : 'No hay cobros pendientes: todos se pagaron con saldo o en Caja.' ?></p>
      <?php else: ?>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Persona</th><th>Vehículo</th><th>Día</th><th class="num">Valor</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cobros as $c): $sel = $cobroSel && $cobroSel['id_cobro'] === $c['id_cobro']; ?>
              <tr<?= $sel ? ' class="is-selected"' : '' ?>>
                <td><?= e($c['nombres'] . ' ' . $c['apellidos']) ?><span class="sub mono"><?= e($c['documento']) ?> · saldo <?= e(money($c['saldo'])) ?></span></td>
                <td><?= e(tipo_label($c['tipo_vehiculo'])) ?><span class="sub"><?= e(categoria_label($c['categoria'])) ?><?= $c['concepto'] === 'DIFERENCIA' ? ' · diferencia por cambio de vehículo' : '' ?></span></td>
                <td class="nowrap"><?= e(fecha($c['fecha'], 'd/m/Y')) ?><span class="sub">desde <?= e(fecha($c['generado_en'], 'H:i')) ?></span></td>
                <td class="num"><?= e(money($c['valor'])) ?></td>
                <td class="actions">
                  <a class="btn btn-sm <?= $sel ? 'btn-ink' : 'btn-line' ?>"
                     href="<?= e(url('caja?cobro=' . $c['id_cobro'] . ($buscar !== '' ? '&buscar=' . rawurlencode($buscar) : ''))) ?>#cobro">Cobrar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($pendientes): ?>
      <section class="panel panel-flush">
        <div class="panel-head pad-head">
          <h2>Visitas del esquema anterior sin pago</h2>
          <span class="muted"><?= count($pendientes) ?></span>
        </div>
        <p class="muted pad-nota">Entraron antes del cobro diario: se pagan por visita con la tarifa que se les aplicó.</p>
        <div class="table-wrap sin-borde">
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
                     href="<?= e(url('caja?visita=' . $v['id_visita'] . ($buscar !== '' ? '&buscar=' . rawurlencode($buscar) : ''))) ?>#cobro">Cobrar</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <aside class="stack">
    <section class="panel" id="cobro">
      <div class="panel-head"><h2>Registrar pago</h2></div>
      <?php if ($cobroSel): ?>
        <dl class="dl">
          <dt>Nombre</dt><dd><?= e($cobroSel['nombres'] . ' ' . $cobroSel['apellidos']) ?></dd>
          <dt>Vehículo</dt><dd><?= e(tipo_label($cobroSel['tipo_vehiculo'])) ?> · <?= categoria_tag($cobroSel['categoria']) ?></dd>
          <dt>Día</dt><dd><?= e(fecha($cobroSel['fecha'], 'd/m/Y')) ?></dd>
          <dt>Concepto</dt><dd><?= $cobroSel['concepto'] === 'DIFERENCIA' ? 'Diferencia por vehículo de tarifa mayor' : 'Cobro del día' ?></dd>
          <dt>Valor a pagar</dt><dd class="total" data-monto="<?= e($cobroSel['valor']) ?>"><?= e(money($cobroSel['valor'])) ?></dd>
        </dl>
        <form method="post" action="<?= e(url('caja/cobro')) ?>" class="stack panel-note" data-pago-form
              data-confirm="¿Confirmar el pago de <?= e(money($cobroSel['valor'])) ?> de <?= e($cobroSel['nombres']) ?>?">
          <?= csrf_field() ?>
          <input type="hidden" name="id_cobro" value="<?= e($cobroSel['id_cobro']) ?>">
          <div class="field"><label for="medio_pago">Medio de pago</label><?= $medioSelect('medio_pago') ?></div>
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
      <?php elseif ($seleccion): ?>
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
          <div class="field"><label for="medio_pago">Medio de pago</label><?= $medioSelect('medio_pago') ?></div>
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
      <?php else: ?>
        <p class="muted">Selecciona un cobro pendiente para recibir el pago.</p>
      <?php endif; ?>
    </section>

    <?php if (can('SALDO_RECARGAR')): ?>
      <section class="panel" id="recarga">
        <div class="panel-head"><h2>Recargar saldo</h2></div>
        <form class="search" method="get" action="<?= e(url('caja')) ?>#recarga" role="search">
          <div class="field">
            <label for="recargar" class="sr-only">Documento o código del carnet</label>
            <input id="recargar" name="recargar" type="text" inputmode="numeric" value="<?= e($recargar) ?>" placeholder="Documento o carnet" maxlength="40">
          </div>
          <button class="btn btn-line" type="submit">Buscar</button>
        </form>
        <?php if ($personaRecarga): ?>
          <dl class="dl panel-note">
            <dt>Nombre</dt><dd><?= e($personaRecarga['nombres'] . ' ' . $personaRecarga['apellidos']) ?></dd>
            <dt>Categoría</dt><dd><?= categoria_tag($personaRecarga['categoria']) ?></dd>
            <dt>Saldo actual</dt><dd class="total"><?= e(money($personaRecarga['saldo'])) ?></dd>
          </dl>
          <form method="post" action="<?= e(url('caja/recarga')) ?>" class="stack panel-note"
                data-confirm="¿Registrar la recarga de saldo para <?= e($personaRecarga['nombres']) ?>? Si tiene cobros pendientes se pagarán con el saldo.">
            <?= csrf_field() ?>
            <input type="hidden" name="id_estudiante" value="<?= e($personaRecarga['id_estudiante']) ?>">
            <input type="hidden" name="documento" value="<?= e($personaRecarga['documento']) ?>">
            <div class="field">
              <label for="monto">Valor a recargar</label>
              <input id="monto" name="monto" type="text" inputmode="numeric" maxlength="11" required autocomplete="off" placeholder="Ej. 10000">
            </div>
            <div class="field"><label for="medio_recarga">Medio de pago</label><?= $medioSelect('medio_recarga') ?></div>
            <div class="field">
              <label for="referencia_recarga">Referencia <span class="muted">(opcional)</span></label>
              <input id="referencia_recarga" name="referencia" type="text" maxlength="60" autocomplete="off">
            </div>
            <button class="btn btn-ink btn-block" type="submit">Registrar recarga</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

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
