<?php
/** Mi cuenta: estudiante, profesor o trabajador. Solo sus propios datos. */
$codigo = $persona['codigo_carnet'] ?: $persona['documento'];
$tono = $hoy['tono'] === 'warn' ? 'warn' : ($hoy['tono'] === 'err' ? 'err' : 'ok');
?>
<header class="page-head">
  <div>
    <p class="eyebrow">Mi cuenta · <?= e(categoria_label($persona['categoria'])) ?></p>
    <h1><?= e($persona['nombres'] . ' ' . $persona['apellidos']) ?></h1>
  </div>
</header>

<div class="kpis">
  <div class="kpi">
    <span class="kpi-label">Saldo disponible</span>
    <span class="kpi-value" data-saldo><?= e(money($persona['saldo'])) ?></span>
    <span class="kpi-note"><a href="<?= e(url('mi-saldo')) ?>">Ver Mi saldo y recargar</a></span>
  </div>
  <div class="kpi <?= $hoy['cobro'] && $hoy['cobro']['estado'] === 'PENDIENTE' ? 'kpi-warn' : '' ?>">
    <span class="kpi-label">Cobro de hoy</span>
    <span class="kpi-value" data-cobro-hoy><?= $hoy['cobro'] ? e(money($hoy['cobro']['valor'])) : '—' ?></span>
    <span class="kpi-note" data-cobro-estado><?= $hoy['cobro'] ? e(ucfirst(mb_strtolower($hoy['cobro']['estado']))) : 'Sin cobro' ?></span>
  </div>
  <div class="kpi">
    <span class="kpi-label">Tolerancia</span>
    <span class="kpi-value mono" data-tolerancia data-segundos="<?= e($hoy['segundos_tolerancia']) ?>">
      <?= $hoy['segundos_tolerancia'] > 0 ? e(sprintf('%02d:%02d', intdiv($hoy['segundos_tolerancia'], 60), $hoy['segundos_tolerancia'] % 60)) : '—' ?>
    </span>
    <span class="kpi-note"><?= $hoy['visita'] ? e($hoy['visita']['tipo'] . ' · entró ' . $hoy['visita']['entrada']) : 'Sin ingreso abierto' ?></span>
  </div>
</div>

<div class="alert alert-<?= e($tono) ?> estado-hoy" data-estado-hoy role="status">
  <strong class="estado-hoy-titulo" data-estado-titulo<?= $hoy['titulo'] === '' ? ' hidden' : '' ?>><?= e($hoy['titulo']) ?></strong>
  <span data-estado-mensaje><?= e($hoy['mensaje']) ?></span>
  <a href="<?= e(url('mi-saldo/recargar')) ?>" data-estado-recargar<?= $tono === 'err' ? '' : ' hidden' ?>>Recargar saldo</a>
</div>

<div class="grid-main mt-24">
  <div class="stack">
    <section class="panel panel-flush">
      <div class="panel-head pad-head"><h2>Cobros diarios</h2></div>
      <?php if (!$cobros): ?>
        <p class="empty">Aún no tienes cobros.</p>
      <?php else: ?>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Fecha</th><th>Vehículo</th><th class="num">Valor</th><th>Estado</th><th>Pago</th></tr></thead>
            <tbody>
            <?php foreach ($cobros as $c): ?>
              <tr>
                <td class="nowrap"><?= e(fecha($c['fecha'], 'd/m/Y')) ?></td>
                <td><?= e(tipo_label($c['tipo_vehiculo'])) ?><?php if ($c['concepto'] === 'DIFERENCIA'): ?><span class="sub">Diferencia (vehículo de tarifa mayor)</span><?php endif; ?></td>
                <td class="num"><?= e(money($c['valor'])) ?></td>
                <td><?= estado_badge($c['estado']) ?></td>
                <td class="mono"><?= $c['consecutivo'] ? e($c['consecutivo'] . ' · ' . ($c['medio_pago'] === 'SALDO' ? 'Saldo' : (config('medios_pago')[$c['medio_pago']] ?? $c['medio_pago']))) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel panel-flush">
      <div class="panel-head pad-head"><h2>Movimientos de saldo</h2></div>
      <?php if (!$movimientos): ?>
        <p class="empty">Sin movimientos. Recarga saldo en Caja para que el cobro diario se pague solo.</p>
      <?php else: ?>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Fecha</th><th>Movimiento</th><th class="num">Valor</th><th class="num">Saldo</th></tr></thead>
            <tbody>
            <?php foreach ($movimientos as $m): ?>
              <tr>
                <td class="nowrap"><?= e(fecha($m['creado_en'])) ?></td>
                <td><?= e($m['tipo'] === 'RECARGA' ? ($m['medio_pago'] === 'PASARELA' ? 'Recarga en línea' : 'Recarga en Caja') : (['DEBITO' => 'Cobro diario', 'REINTEGRO' => 'Reintegro', 'REVERSO_RECARGA' => 'Reverso de recarga'][$m['tipo']] ?? $m['tipo'])) ?>
                  <?php if ($m['consecutivo']): ?><span class="sub mono"><?= e($m['consecutivo']) ?></span><?php endif; ?></td>
                <td class="num"><?= e(($m['tipo'] === 'DEBITO' ? '− ' : '+ ') . money($m['monto'])) ?></td>
                <td class="num"><?= e(money($m['saldo_nuevo'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel panel-flush">
      <div class="panel-head pad-head"><h2>Ingresos recientes</h2></div>
      <?php if (!$visitas): ?>
        <p class="empty">Aún no registras ingresos.</p>
      <?php else: ?>
        <div class="table-wrap sin-borde">
          <table class="table">
            <thead><tr><th>Entrada</th><th>Salida</th><th>Vehículo</th><th>Cobro</th></tr></thead>
            <tbody>
            <?php foreach ($visitas as $v): ?>
              <tr>
                <td class="nowrap"><?= e(fecha($v['entrada_en'])) ?></td>
                <td class="nowrap"><?= $v['salida_en'] ? e(fecha($v['salida_en'], 'H:i')) : e($v['estado'] === 'EN_CURSO' ? 'Dentro' : '—') ?></td>
                <td><?= e(tipo_label($v['tipo_vehiculo'])) ?></td>
                <td><?= e(liquidacion_label($v['liquidacion'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Carnet digital</h2></div>
      <canvas class="carnet-codigo" data-barcode="<?= e($codigo) ?>" role="img" aria-label="Código de barras de tu carnet"></canvas>
      <p class="field-hint">Puedes mostrar este código en Portería si no tienes el carnet a la mano.</p>
    </section>

    <section class="panel" data-push>
      <div class="panel-head"><h2>Avisos en el celular</h2></div>
      <p class="muted" data-push-estado>Recibe una notificación si superas la tolerancia y tu saldo no alcanza para el cobro del día.</p>
      <div class="form-actions mt-14">
        <button class="btn btn-ink btn-sm" type="button" data-push-activar>Activar avisos</button>
        <button class="btn btn-line btn-sm" type="button" data-push-probar <?= $suscripciones ? '' : 'hidden' ?>>Enviar prueba</button>
        <button class="link-btn" type="button" data-push-desactivar hidden>Desactivar en este celular</button>
      </div>
      <p class="field-hint mt-10">En iPhone: abre el sitio en Safari, «Compartir → Agregar a inicio», y activa los avisos desde ese ícono.</p>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Avisos recientes</h2></div>
      <?php if (!$avisos): ?>
        <p class="muted">Sin avisos.</p>
      <?php else: ?>
        <ul class="avisos">
          <?php foreach ($avisos as $a): ?>
            <li><strong><?= e($a['titulo']) ?></strong><span class="sub"><?= e(fecha($a['creado_en'])) ?></span><p><?= e($a['mensaje']) ?></p></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Tarifa diaria</h2></div>
      <dl class="dl">
        <?php foreach ($tarifas as $tipo => $porCategoria): $t = $porCategoria[$persona['categoria']] ?? null; ?>
          <dt><?= e(tipo_label($tipo)) ?></dt><dd><?= $t ? e(money($t['valor'])) : '—' ?></dd>
        <?php endforeach; ?>
        <dt>Bicicleta</dt><dd>Sin tarifa</dd>
      </dl>
    </section>
  </aside>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js" integrity="sha384-Kk5SjBOKprEnGfyBWfD2zROFd1Cu8kwOXxG2GIhYPcoDL2rBJS9P8Ud1ZMy4412a" crossorigin="anonymous"></script>
<script src="<?= e(asset('js/carnets.js')) ?>"></script>
