<?php
$nombres = ['abiertas' => 'Visitas abiertas', 'visitas' => 'Entradas y salidas', 'pagos' => 'Pagos', 'recaudo' => 'Recaudo'];
$query = static fn (array $extra = []) => http_build_query(array_filter($extra + $filtros + ['tab' => $tab], static fn ($v) => $v !== ''));
$sel = static fn (string $campo, string $valor) => $filtros[$campo] === $valor ? 'selected' : '';
$total = 0.0;
?>
<header class="page-head">
  <div>
    <p class="eyebrow">Reportes</p>
    <h1><?= e($nombres[$tab]) ?></h1>
    <p><?= match ($tab) {
        'abiertas' => 'Vehículos dentro en este momento. El tiempo transcurrido es informativo; no se usa para cobrar.',
        'visitas'  => 'Entradas y salidas por fecha, tipo de vehículo, estado y funcionario.',
        'pagos'    => 'Pagos por fecha, medio, cajero y consecutivo, incluidos los anulados.',
        'recaudo'  => 'Total recaudado por día, cajero y medio de pago. Solo pagos válidos.',
    } ?></p>
  </div>
  <?php if (can('REPORTE_EXPORTAR')): ?>
    <div class="actions"><a class="btn btn-line" href="<?= e(url('reportes/exportar?' . $query())) ?>">Exportar CSV</a></div>
  <?php endif; ?>
</header>

<?php if (count($tabs) > 1): ?>
  <nav class="tabs" aria-label="Tipo de reporte">
    <?php foreach ($tabs as $t): ?>
      <a href="<?= e(url('reportes?tab=' . $t)) ?>"<?= $t === $tab ? ' aria-current="page"' : '' ?>><?= e($nombres[$t]) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<form class="filters" method="get" action="<?= e(url('reportes')) ?>">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php if ($tab !== 'abiertas'): ?>
    <div class="field"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
    <div class="field"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
  <?php endif; ?>
  <div class="field">
    <label for="tipo">Tipo</label>
    <select id="tipo" name="tipo">
      <option value="">Todos</option>
      <option value="CARRO" <?= $sel('tipo', 'CARRO') ?>>Carro</option>
      <option value="MOTO" <?= $sel('tipo', 'MOTO') ?>>Moto</option>
    </select>
  </div>
  <div class="field">
    <label for="categoria">Categoría</label>
    <select id="categoria" name="categoria">
      <option value="">Todas</option>
      <option value="ESTUDIANTE" <?= $sel('categoria', 'ESTUDIANTE') ?>>Estudiante</option>
      <option value="PROFESOR" <?= $sel('categoria', 'PROFESOR') ?>>Profesor</option>
    </select>
  </div>
  <?php if ($tab === 'visitas'): ?>
    <div class="field">
      <label for="estado">Estado</label>
      <select id="estado" name="estado">
        <option value="">Todos</option>
        <?php foreach (['EN_CURSO', 'CERRADA', 'ANULADA'] as $es): ?><option value="<?= $es ?>" <?= $sel('estado', $es) ?>><?= $es ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="pago">Pago</label>
      <select id="pago" name="pago">
        <option value="">Todos</option>
        <option value="si" <?= $sel('pago', 'si') ?>>Pagadas</option>
        <option value="no" <?= $sel('pago', 'no') ?>>Sin pago</option>
      </select>
    </div>
  <?php elseif ($tab === 'pagos'): ?>
    <div class="field">
      <label for="estado">Estado</label>
      <select id="estado" name="estado">
        <option value="">Todos</option>
        <?php foreach (['PAGADO', 'ANULADO'] as $es): ?><option value="<?= $es ?>" <?= $sel('estado', $es) ?>><?= $es ?></option><?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <?php if (in_array($tab, ['pagos', 'recaudo'], true)): ?>
    <div class="field">
      <label for="medio">Medio</label>
      <select id="medio" name="medio">
        <option value="">Todos</option>
        <?php foreach ($medios as $c => $n): ?><option value="<?= e($c) ?>" <?= $sel('medio', $c) ?>><?= e($n) ?></option><?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <?php if ($tab !== 'abiertas'): ?>
    <div class="field">
      <label for="usuario">Funcionario</label>
      <select id="usuario" name="usuario">
        <option value="">Todos</option>
        <?php foreach ($usuarios as $u): ?><option value="<?= e($u['id_usuario']) ?>" <?= $sel('usuario', (string) $u['id_usuario']) ?>><?= e($u['nombre']) ?></option><?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <button class="btn btn-ink" type="submit">Filtrar</button>
</form>

<?php if (!$filas): ?>
  <div class="table-wrap"><p class="empty">No hay registros con estos filtros.</p></div>
<?php elseif ($tab === 'abiertas' || $tab === 'visitas'): ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Persona</th><th>Tipo</th><th class="num">Tarifa</th><th>Entrada</th><th><?= $tab === 'abiertas' ? 'Tiempo' : 'Salida' ?></th><th>Pago</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($filas as $v): ?>
        <tr>
          <td>
            <?php if (can('VISITA_VER')): ?><a href="<?= e(url('estudiantes/' . $v['id_estudiante'])) ?>"><?= e($v['nombres'] . ' ' . $v['apellidos']) ?></a>
            <?php else: ?><?= e($v['nombres'] . ' ' . $v['apellidos']) ?><?php endif; ?>
            <span class="sub mono"><?= e(mask_doc($v['documento'])) ?></span>
          </td>
          <td class="mono"><?= e($v['tipo_vehiculo']) ?><span class="sub"><?= e(categoria_label($v['categoria'])) ?></span></td>
          <td class="num"><?= e(money($v['tarifa_aplicada'])) ?></td>
          <td class="nowrap"><?= e(fecha($v['entrada_en'])) ?><span class="sub"><?= e($v['entrada_por_nombre']) ?></span></td>
          <td class="nowrap">
            <?php if ($tab === 'abiertas'): ?><?= e(duracion($v['entrada_en'])) ?>
            <?php elseif ($v['salida_en']): ?><?= e(fecha($v['salida_en'])) ?><span class="sub"><?= e($v['salida_por_nombre']) ?></span>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
          <td class="mono"><?= $v['pagada'] ? e($v['consecutivo']) : '<span class="dot dot-warn"></span>Pendiente' ?></td>
          <td><?= estado_badge($v['estado']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php elseif ($tab === 'pagos'): ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Comprobante</th><th>Fecha</th><th>Persona</th><th>Tipo</th><th>Medio</th><th>Cajero</th><th>Estado</th><th class="num">Monto</th></tr></thead>
      <tbody>
      <?php foreach ($filas as $p): if ($p['estado'] === 'PAGADO') { $total += (float) $p['monto']; } ?>
        <tr<?= $p['estado'] === 'ANULADO' ? ' class="is-off"' : '' ?>>
          <td class="mono"><a href="<?= e(url('caja/recibo/' . $p['id_pago'])) ?>"><?= e($p['consecutivo']) ?></a></td>
          <td class="nowrap"><?= e(fecha($p['pagado_en'])) ?></td>
          <td><?= e($p['nombres'] . ' ' . $p['apellidos']) ?><span class="sub mono"><?= e(mask_doc($p['documento'])) ?></span></td>
          <td class="mono"><?= e($p['tipo_vehiculo']) ?><span class="sub"><?= e(categoria_label($p['categoria'])) ?></span></td>
          <td><?= e($medios[$p['medio_pago']] ?? $p['medio_pago']) ?></td>
          <td><?= e($p['cajero']) ?></td>
          <td><?= estado_badge($p['estado']) ?><?php if ($p['motivo_anulacion']): ?><span class="sub"><?= e($p['motivo_anulacion']) ?></span><?php endif; ?></td>
          <td class="num"><?= e(money($p['monto'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="7">Total pagos válidos</td><td class="num"><?= e(money($total)) ?></td></tr></tfoot>
    </table>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Día</th><th>Cajero</th><th>Medio</th><th class="num">Pagos</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($filas as $r): $total += (float) $r['total']; ?>
        <tr>
          <td class="nowrap"><?= e(fecha($r['dia'], 'd/m/Y')) ?></td>
          <td><?= e($r['cajero']) ?></td>
          <td><?= e($medios[$r['medio_pago']] ?? $r['medio_pago']) ?></td>
          <td class="num"><?= e($r['cantidad']) ?></td>
          <td class="num"><?= e(money($r['total'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4">Total del periodo</td><td class="num"><?= e(money($total)) ?></td></tr></tfoot>
    </table>
  </div>
<?php endif; ?>
<?php if (count($filas) >= 500): ?><p class="field-hint panel-note">Se muestran los 500 registros más recientes. Ajusta los filtros para acotar el resultado.</p><?php endif; ?>
