<?php $u = \App\Services\AuthService::usuario(); ?>
<header class="page-head">
  <div>
    <p class="eyebrow"><?= e($u['rol']) ?> · <?= e(fecha(date('Y-m-d H:i:s'), 'd/m/Y')) ?></p>
    <h1>Hola, <?= e(explode(' ', $u['nombre'])[0]) ?>.</h1>
    <p>Estado del parqueadero en este momento.</p>
  </div>
  <div class="actions">
    <?php if (can('ENTRADA_CREAR')): ?><a class="btn btn-ink" href="<?= e(url('porteria')) ?>">Ir a Portería</a><?php endif; ?>
    <?php if (can('PAGO_REGISTRAR')): ?><a class="btn btn-ink" href="<?= e(url('caja')) ?>">Ir a Caja</a><?php endif; ?>
    <?php if (can('REPORTE_VER')): ?><a class="btn btn-line" href="<?= e(url('reportes')) ?>">Reportes</a><?php endif; ?>
  </div>
</header>

<section class="kpis" aria-label="Indicadores">
  <div class="kpi">
    <span class="kpi-label">Carros dentro</span>
    <span class="kpi-value"><?= e($ocupacion['CARRO']) ?></span>
  </div>
  <div class="kpi">
    <span class="kpi-label">Motos dentro</span>
    <span class="kpi-value"><?= e($ocupacion['MOTO']) ?></span>
  </div>
  <div class="kpi<?= $ocupacion['pendientes'] > 0 ? ' kpi-warn' : '' ?>">
    <span class="kpi-label">Pendientes de pago</span>
    <span class="kpi-value"><?= e($ocupacion['pendientes']) ?></span>
  </div>
  <?php if ($recaudo !== null): ?>
    <div class="kpi">
      <span class="kpi-label">Recaudo de hoy</span>
      <span class="kpi-value"><?= e(money($recaudo['total'])) ?></span>
      <span class="kpi-note"><?= e($recaudo['cantidad']) ?> pagos</span>
    </div>
  <?php endif; ?>
</section>

<div class="grid-main">
  <section class="panel panel-flush">
    <div class="panel-head">
      <h2>Visitas abiertas</h2>
      <?php if (can('REPORTE_OPERATIVO')): ?><a class="muted" href="<?= e(url('reportes?tab=abiertas')) ?>">Ver todas</a><?php endif; ?>
    </div>
    <?php if (!can('REPORTE_OPERATIVO')): ?>
      <p class="empty">Tu rol no consulta visitas abiertas.</p>
    <?php elseif (!$abiertas): ?>
      <p class="empty">No hay vehículos dentro del parqueadero.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Persona</th><th>Tipo</th><th>Entrada</th><th>Tiempo</th><th>Pago</th></tr></thead>
          <tbody>
          <?php foreach ($abiertas as $v): ?>
            <tr>
              <td><?= e($v['nombres'] . ' ' . $v['apellidos']) ?><span class="sub mono"><?= e(mask_doc($v['documento'])) ?></span></td>
              <td class="mono"><?= e($v['tipo_vehiculo']) ?><span class="sub"><?= e(categoria_label($v['categoria'])) ?></span></td>
              <td class="nowrap"><?= e(fecha($v['entrada_en'], 'H:i')) ?></td>
              <td class="nowrap muted"><?= e(duracion($v['entrada_en'])) ?></td>
              <td><?= $v['pagada'] ? '<span class="dot dot-ok"></span>Pagada' : '<span class="dot dot-warn"></span>Pendiente' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <aside class="panel">
    <div class="panel-head"><h2>Tarifas vigentes</h2></div>
    <dl class="dl">
      <?php foreach ($tarifas as $tipo => $porCategoria): foreach ($porCategoria as $cat => $t): ?>
        <dt><span class="mono"><?= e($tipo) ?></span> · <?= e(categoria_label($cat)) ?></dt>
        <dd><?= $t ? e(money($t['valor'])) : '<span class="muted">Sin tarifa</span>' ?></dd>
      <?php endforeach; endforeach; ?>
    </dl>
    <p class="field-hint panel-note">Valor fijo por visita. El tiempo de permanencia no cambia el valor.</p>
  </aside>
</div>
