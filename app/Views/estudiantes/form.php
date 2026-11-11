<?php
$accion = $estudiante ? url('estudiantes/' . $estudiante['id_estudiante']) : url('estudiantes');
$campo = static function (string $nombre, string $etiqueta, array $attrs = []) use ($datos, $errores): string {
    $err = $errores[$nombre] ?? null;
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . ($v === true ? '' : '="' . e($v) . '"');
    }
    return '<div class="field">'
        . '<label for="' . $nombre . '">' . $etiqueta . '</label>'
        . '<input id="' . $nombre . '" name="' . $nombre . '" value="' . e(old($datos, $nombre)) . '"' . $extra
        . ($err ? ' aria-invalid="true" aria-describedby="' . $nombre . '-error"' : '') . '>'
        . ($err ? '<span class="field-error" id="' . $nombre . '-error">' . e($err) . '</span>' : '')
        . '</div>';
};
?>
<header class="page-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('estudiantes')) ?>">Estudiantes y profesores</a></p>
    <h1><?= e($titulo) ?></h1>
    <p>El documento es único. El correo y el teléfono son opcionales: regístralos solo si son necesarios.</p>
  </div>
</header>

<section class="panel">
  <form method="post" action="<?= e($accion) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= $campo('documento', 'Documento de identidad', ['type' => 'text', 'maxlength' => '20', 'required' => true, 'autocomplete' => 'off', 'inputmode' => 'numeric']) ?>
      <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria" required<?= isset($errores['categoria']) ? ' aria-invalid="true"' : '' ?>>
          <?php foreach (array_merge(\App\Services\ReglasVisita::CATEGORIAS, [\App\Services\ReglasVisita::VISITANTE]) as $cat): ?>
            <option value="<?= e($cat) ?>" <?= old($datos, 'categoria', 'ESTUDIANTE') === $cat ? 'selected' : '' ?>><?= e(categoria_label($cat)) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="field-hint">Define la tarifa diaria. Los visitantes no pagan.</span>
        <?php if (isset($errores['categoria'])): ?><span class="field-error"><?= e($errores['categoria']) ?></span><?php endif; ?>
      </div>
      <?= $campo('codigo_carnet', 'Código de barras del carnet (opcional)', ['type' => 'text', 'maxlength' => '40', 'autocomplete' => 'off']) ?>
      <?= $campo('nombres', 'Nombres', ['type' => 'text', 'maxlength' => '80', 'required' => true, 'autocomplete' => 'off']) ?>
      <?= $campo('apellidos', 'Apellidos', ['type' => 'text', 'maxlength' => '80', 'required' => true, 'autocomplete' => 'off']) ?>
      <?= $campo('correo', 'Correo (opcional)', ['type' => 'email', 'maxlength' => '120', 'autocomplete' => 'off']) ?>
      <?= $campo('telefono', 'Teléfono (opcional)', ['type' => 'tel', 'maxlength' => '20', 'autocomplete' => 'off']) ?>
    </div>
    <div class="form-actions">
      <button class="btn btn-ink" type="submit"><?= $estudiante ? 'Guardar cambios' : 'Registrar' ?></button>
      <a class="btn btn-line" href="<?= e(url($estudiante ? 'estudiantes/' . $estudiante['id_estudiante'] : 'estudiantes')) ?>">Cancelar</a>
    </div>
  </form>
</section>

<?php if ($estudiante): ?>
  <section class="panel">
    <div class="panel-head">
      <h2><?= (int) $estudiante['activo'] === 1 ? 'Desactivar registro' : 'Activar registro' ?></h2>
    </div>
    <p class="muted"><?= (int) $estudiante['activo'] === 1
        ? 'Una persona inactiva no puede registrar nuevas entradas. Su historial se conserva.'
        : 'Al activarlo podrá volver a usar el parqueadero.' ?></p>
    <form method="post" action="<?= e(url('estudiantes/' . $estudiante['id_estudiante'] . '/estado')) ?>" class="form-actions"
          data-confirm="<?= (int) $estudiante['activo'] === 1 ? '¿Desactivar este registro?' : '¿Activar este registro?' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="activo" value="<?= (int) $estudiante['activo'] === 1 ? '0' : '1' ?>">
      <button class="btn <?= (int) $estudiante['activo'] === 1 ? 'btn-danger' : 'btn-line' ?>" type="submit">
        <?= (int) $estudiante['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
      </button>
    </form>
  </section>
<?php endif; ?>
