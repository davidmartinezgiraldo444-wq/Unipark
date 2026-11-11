<?php $gestiona = can('ESTUDIANTE_GESTIONAR'); ?>
<header class="page-head">
  <div>
    <p class="eyebrow">Personas</p>
    <h1>Estudiantes y profesores</h1>
  </div>
  <?php if ($gestiona): ?>
    <div class="actions"><?php if (can('PERSONA_IMPORTAR')): ?><a class="btn btn-ghost" href="<?= e(url('estudiantes/importar')) ?>">Importar base institucional</a><?php endif; ?><a class="btn btn-ink" href="<?= e(url('estudiantes/nuevo')) ?>">Registrar persona</a></div>
  <?php endif; ?>
</header>

<section class="panel panel-flush">
  <form class="search filters-inline panel-note" method="get" action="<?= e(url('estudiantes')) ?>" role="search">
    <div class="field">
      <label for="buscar" class="sr-only">Buscar por documento o nombre</label>
      <input id="buscar" name="buscar" type="search" value="<?= e($buscar) ?>" placeholder="Documento o nombre" maxlength="60">
    </div>
    <button class="btn btn-line" type="submit">Buscar</button>
  </form>

  <?php if (!$estudiantes): ?>
    <p class="empty">No se encontraron personas.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Documento</th><th>Nombre</th><th>Categoría</th><th>Estado</th><th class="num">Visitas</th><th>Ahora</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($estudiantes as $s): ?>
          <tr<?= (int) $s['activo'] === 1 ? '' : ' class="is-off"' ?>>
            <td class="mono"><?= e($gestiona ? $s['documento'] : mask_doc($s['documento'])) ?></td>
            <td><?= e($s['apellidos'] . ', ' . $s['nombres']) ?></td>
            <td><?= categoria_tag($s['categoria']) ?></td>
            <td><?= (int) $s['activo'] === 1 ? 'Activo' : 'Inactivo' ?></td>
            <td class="num"><?= e($s['visitas']) ?></td>
            <td><?= $s['visita_abierta'] ? '<span class="dot dot-warn"></span>Dentro' : '<span class="muted">—</span>' ?></td>
            <td class="actions">
              <?php if (can('VISITA_VER')): ?><a href="<?= e(url('estudiantes/' . $s['id_estudiante'])) ?>">Historial</a><?php endif; ?>
              <?php if ($gestiona): ?><a href="<?= e(url('estudiantes/' . $s['id_estudiante'] . '/editar')) ?>">Editar</a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($paginas > 1): ?>
  <div class="pagination">
    <span><?= e($total) ?> registros · página <?= e($pagina) ?> de <?= e($paginas) ?></span>
    <nav aria-label="Paginación">
      <?php $q = $buscar !== '' ? '&buscar=' . rawurlencode($buscar) : ''; ?>
      <?php if ($pagina > 1): ?><a class="btn btn-line btn-sm" href="<?= e(url('estudiantes?pagina=' . ($pagina - 1) . $q)) ?>">Anterior</a><?php endif; ?>
      <?php if ($pagina < $paginas): ?><a class="btn btn-line btn-sm" href="<?= e(url('estudiantes?pagina=' . ($pagina + 1) . $q)) ?>">Siguiente</a><?php endif; ?>
    </nav>
  </div>
<?php endif; ?>
