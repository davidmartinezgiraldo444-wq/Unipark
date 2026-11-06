<header class="page-head">
  <div>
    <p class="eyebrow">Tarifas</p>
    <h1>Tarifa fija por vehículo y categoría</h1>
    <p>Un cambio rige desde el momento en que se guarda y solo para las nuevas entradas. Las visitas ya registradas conservan el valor que se les aplicó al entrar.</p>
  </div>
</header>

<div class="tarifa-matrix">
  <?php foreach ($vigentes as $tipo => $porCategoria): ?>
    <section class="panel tarifa-card">
      <div class="panel-head">
        <h2 class="mono"><?= e($tipo) ?></h2>
        <span class="muted">Valor por visita</span>
      </div>

      <?php foreach ($porCategoria as $cat => $t): ?>
        <div class="tarifa-cat">
          <div>
            <?= categoria_tag($cat) ?>
            <p class="field-hint"><?= $t ? 'Desde ' . e(fecha($t['vigente_desde'], 'd/m/Y H:i')) : 'Sin tarifa vigente' ?></p>
          </div>
          <span class="valor"><?= $t ? e(money($t['valor'])) : '—' ?></span>
        </div>
        <form method="post" action="<?= e(url('tarifas')) ?>"
              data-confirm="¿Cambiar la tarifa de <?= e($tipo) ?> para <?= e(mb_strtolower(categoria_label($cat))) ?>? Rige desde ahora para las nuevas entradas.">
          <?= csrf_field() ?>
          <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
          <input type="hidden" name="categoria" value="<?= e($cat) ?>">
          <label class="sr-only" for="valor-<?= e($tipo . '-' . $cat) ?>">Nuevo valor para <?= e($tipo) ?> <?= e(categoria_label($cat)) ?></label>
          <input id="valor-<?= e($tipo . '-' . $cat) ?>" name="valor" type="text" inputmode="decimal" maxlength="11" required
                 placeholder="Nuevo valor, ej. <?= $t ? e((string) (float) $t['valor']) : '2000' ?>" autocomplete="off">
          <button class="btn btn-ink btn-sm" type="submit">Guardar</button>
        </form>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>

<h2 class="section-title">Historial de vigencias</h2>
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Tipo</th><th>Categoría</th><th class="num">Valor</th><th>Vigente desde</th><th>Vigente hasta</th><th>Registró</th><th class="num">Visitas</th></tr></thead>
    <tbody>
    <?php foreach ($historial as $t): ?>
      <tr<?= $t['vigente_hasta'] ? ' class="is-off"' : '' ?>>
        <td class="mono"><?= e($t['tipo_vehiculo']) ?></td>
        <td><?= e(categoria_label($t['categoria'])) ?></td>
        <td class="num"><?= e(money($t['valor'])) ?></td>
        <td class="nowrap"><?= e(fecha($t['vigente_desde'])) ?></td>
        <td class="nowrap"><?= $t['vigente_hasta'] ? e(fecha($t['vigente_hasta'])) : '<span class="dot dot-ok"></span>Vigente' ?></td>
        <td><?= e($t['creado_por_nombre']) ?></td>
        <td class="num"><?= e($t['visitas']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
