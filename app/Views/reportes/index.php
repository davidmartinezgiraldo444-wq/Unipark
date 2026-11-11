<?php
$nombres = ['abiertas' => 'Visitas abiertas', 'visitas' => 'Entradas y salidas', 'pagos' => 'Pagos', 'recaudo' => 'Recaudo',
            'cobros' => 'Cobros diarios', 'movimientos' => 'Movimientos de saldo', 'saldos' => 'Saldos', 'eventos' => 'Eventos de acceso', 'recargas' => 'Recargas en línea'];
$nuevas = ['cobros', 'movimientos', 'saldos', 'eventos', 'recargas'];
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
        'cobros'      => 'Cobros del día y diferencias por cambio de vehículo, con su estado y pago.',
        'movimientos' => 'Recargas, débitos automáticos, reintegros y reversos del saldo. Nada se borra.',
        'saldos'      => 'Saldo disponible y valor pendiente por persona.',
        'eventos'     => 'Cada pasada por la portería: identidad, detección de la IA, confirmación y portero.',
        'recargas'    => 'Recargas voluntarias en línea con su referencia, transacción de la pasarela y estado. Solo las aprobadas suman saldo.',
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
  <?php if (!in_array($tab, ['abiertas', 'saldos'], true)): ?>
    <div class="field"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
    <div class="field"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
  <?php endif; ?>
  <?php if (in_array($tab, $nuevas, true)): ?>
    <div class="field"><label for="texto">Persona</label><input id="texto" name="texto" type="search" value="<?= e($filtros['texto']) ?>" placeholder="Documento o nombre" maxlength="60"></div>
  <?php endif; ?>
  <?php if (!in_array($tab, ['movimientos', 'saldos', 'recargas'], true)): ?>
  <div class="field">
    <label for="tipo">Vehículo</label>
    <select id="tipo" name="tipo">
      <option value="">Todos</option>
      <?php foreach (\App\Services\ReglasVisita::TIPOS as $tv): ?><option value="<?= e($tv) ?>" <?= $sel('tipo', $tv) ?>><?= e(tipo_label($tv)) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if (in_array($tab, ['abiertas', 'visitas', 'pagos', 'recaudo', 'cobros', 'saldos'], true)): ?>
  <div class="field">
    <label for="categoria">Categoría</label>
    <select id="categoria" name="categoria">
      <option value="">Todas</option>
      <?php foreach (['ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE'] as $cat): ?><option value="<?= $cat ?>" <?= $sel('categoria', $cat) ?>><?= e(categoria_label($cat)) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if ($tab === 'cobros'): ?>
    <div class="field"><label for="estado_cobro">Estado</label><select id="estado_cobro" name="estado_cobro"><option value="">Todos</option><?php foreach (['PENDIENTE', 'PAGADO', 'ANULADO'] as $es): ?><option value="<?= $es ?>" <?= $sel('estado_cobro', $es) ?>><?= $es ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="concepto">Concepto</label><select id="concepto" name="concepto"><option value="">Todos</option><option value="DIARIO" <?= $sel('concepto', 'DIARIO') ?>>Cobro del día</option><option value="DIFERENCIA" <?= $sel('concepto', 'DIFERENCIA') ?>>Diferencia</option></select></div>
  <?php elseif ($tab === 'movimientos'): ?>
    <div class="field"><label for="tipo_mov">Movimiento</label><select id="tipo_mov" name="tipo_mov"><option value="">Todos</option><?php foreach (['RECARGA' => 'Recarga', 'DEBITO' => 'Débito', 'REINTEGRO' => 'Reintegro', 'REVERSO_RECARGA' => 'Reverso de recarga'] as $c => $n): ?><option value="<?= $c ?>" <?= $sel('tipo_mov', $c) ?>><?= $n ?></option><?php endforeach; ?></select></div>
  <?php elseif ($tab === 'recargas'): ?>
    <div class="field"><label for="estado_recarga">Estado</label><select id="estado_recarga" name="estado_recarga"><option value="">Todos</option><?php foreach (['CREADA', 'PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA', 'ERROR', 'CANCELADA', 'EXPIRADA'] as $es): ?><option value="<?= $es ?>" <?= $sel('estado_recarga', $es) ?>><?= e(recarga_label($es)) ?></option><?php endforeach; ?></select></div>
  <?php elseif ($tab === 'eventos'): ?>
    <div class="field"><label for="estado_evento">Estado</label><select id="estado_evento" name="estado_evento"><option value="">Todos</option><?php foreach (['EN_CAPTURA', 'PENDIENTE_PORTERO', 'CONFIRMADO', 'RECHAZADO', 'EXPIRADO'] as $es): ?><option value="<?= $es ?>" <?= $sel('estado_evento', $es) ?>><?= $es ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="metodo">Método</label><select id="metodo" name="metodo"><option value="">Todos</option><option value="IA" <?= $sel('metodo', 'IA') ?>>IA</option><option value="MANUAL" <?= $sel('metodo', 'MANUAL') ?>>Portero</option></select></div>
  <?php endif; ?>
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
  <?php if (in_array($tab, ['visitas', 'pagos', 'recaudo', 'movimientos'], true)): ?>
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
<?php elseif (in_array($tab, $nuevas, true)): ?>
  <?php require __DIR__ . '/_' . $tab . '.php'; ?>
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

<?php if ($tab === 'eventos'): ?>
  <?php require __DIR__ . '/_calibracion.php'; ?>
<?php endif; ?>
<?php if ($tab === 'recargas'): ?>
  <?php require __DIR__ . '/_conciliacion.php'; ?>
<?php endif; ?>
