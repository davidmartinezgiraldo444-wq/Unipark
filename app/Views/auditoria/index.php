<?php $sel = static fn (string $campo, string $valor) => $filtros[$campo] === $valor ? 'selected' : ''; ?>
<header class="page-head">
  <div>
    <p class="eyebrow">Auditoría</p>
    <h1>Bitácora de operaciones</h1>
    <p>Quién hizo qué, cuándo y con qué resultado. Los registros no se pueden modificar ni borrar, ni siquiera desde la base de datos.</p>
  </div>
</header>

<form class="filters" method="get" action="<?= e(url('auditoria')) ?>">
  <div class="field"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
  <div class="field"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
  <div class="field">
    <label for="accion">Acción</label>
    <select id="accion" name="accion">
      <option value="">Todas</option>
      <?php foreach ($acciones as $a): ?><option value="<?= e($a) ?>" <?= $sel('accion', $a) ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="resultado">Resultado</label>
    <select id="resultado" name="resultado">
      <option value="">Todos</option>
      <?php foreach (['EXITO', 'DENEGADO', 'ERROR'] as $r): ?><option value="<?= $r ?>" <?= $sel('resultado', $r) ?>><?= $r ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="usuario">Usuario</label>
    <select id="usuario" name="usuario">
      <option value="">Todos</option>
      <?php foreach ($usuarios as $u): ?><option value="<?= e($u['id_usuario']) ?>" <?= $sel('usuario', (string) $u['id_usuario']) ?>><?= e($u['nombre']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-ink" type="submit">Filtrar</button>
</form>

<div class="table-wrap">
  <?php if (!$eventos): ?>
    <p class="empty">No hay eventos con estos filtros.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Registro</th><th>Resultado</th><th>Motivo y detalle</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($eventos as $ev): ?>
        <tr>
          <td class="nowrap"><?= e(fecha($ev['creado_en'], 'd/m/Y H:i:s')) ?></td>
          <td><?= $ev['usuario'] ? e($ev['usuario']) . '<span class="sub mono">' . e($ev['login']) . '</span>' : '<span class="muted">Sin sesión</span>' ?></td>
          <td class="mono"><?= e($ev['accion']) ?></td>
          <td class="mono"><?= e($ev['entidad']) ?><?= $ev['id_registro'] ? ' #' . e($ev['id_registro']) : '' ?></td>
          <td><?= estado_badge($ev['resultado']) ?></td>
          <td>
            <?= $ev['motivo'] ? e($ev['motivo']) : '' ?>
            <?php if ($ev['detalle_json']): ?>
              <details class="more"><summary>Detalle</summary><pre><?= e(json_encode(json_decode($ev['detalle_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details>
            <?php endif; ?>
          </td>
          <td class="mono muted"><?= e($ev['ip_origen']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php if (count($eventos) >= 300): ?><p class="field-hint panel-note">Se muestran los 300 eventos más recientes. Ajusta los filtros para acotar el resultado.</p><?php endif; ?>
