<?php /** Importación de la base institucional. */ ?>
<div class="page-head">
  <div>
    <h1>Importar base institucional</h1>
    <p class="muted">Carga el CSV de personas que entrega la institución. Primero valida; si el resultado es correcto, aplica.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('estudiantes')) ?>">Volver a personas</a>
</div>

<div class="grid-main">
  <section class="panel">
    <h2>Cargar archivo</h2>
    <form method="post" action="<?= e(url('estudiantes/importar')) ?>" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?>
      <div class="field">
        <label for="archivo">Archivo CSV</label>
        <input id="archivo" name="archivo" type="file" accept=".csv,.txt,text/csv" required>
      </div>
      <fieldset class="field">
        <legend>Qué hacer</legend>
        <label class="check"><input type="radio" name="modo" value="VALIDAR" checked> Solo validar (no guarda nada)</label>
        <label class="check"><input type="radio" name="modo" value="APLICAR"> Aplicar: crear y actualizar personas</label>
      </fieldset>
      <button class="btn btn-primary" type="submit">Procesar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Reglas</h2>
    <ul class="lista-reglas">
      <li>La persona se reconoce por su <strong>documento</strong>. Si ya existe se actualiza; si no, se crea.</li>
      <li>Nunca se borra a nadie, no se toca el saldo y no se desactiva a quien no venga en el archivo.</li>
      <li>Un campo opcional vacío no borra el dato que ya existe.</li>
      <li>Documentos o carnets repetidos y categorías desconocidas se rechazan con su número de fila.</li>
      <li>Al aplicar, todas las filas válidas entran juntas en una sola transacción.</li>
      <li>El registro de errores guarda la fila y el documento enmascarado, no los datos personales.</li>
    </ul>
    <h3 class="mt-12">Columnas reconocidas</h3>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Campo</th><th>Nombres de columna aceptados</th></tr></thead>
        <tbody>
        <?php foreach ($columnas as $campo => $nombres): ?>
          <tr><td class="mono"><?= e($campo) ?><?= in_array($campo, ['documento', 'nombres', 'apellidos', 'categoria'], true) ? ' *' : '' ?></td><td><?= e(implode(', ', $nombres)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted">* obligatorio. Los nombres se ajustan en <span class="mono">config/app.php</span> (importacion).</p>
  </section>
</div>

<?php if ($ver): ?>
<section class="panel panel-flush mt-24">
  <div class="panel-head pad-head"><h2>Filas rechazadas · importación #<?= e($ver) ?></h2></div>
  <?php if (!$errores): ?>
    <p class="empty">Sin filas rechazadas.</p>
  <?php else: ?>
    <div class="table-wrap sin-borde">
      <table class="table">
        <thead><tr><th class="num">Fila</th><th>Documento</th><th>Problema</th></tr></thead>
        <tbody>
        <?php foreach ($errores as $er): ?>
          <tr><td class="num"><?= e($er['fila']) ?></td><td class="mono"><?= e($er['documento'] ?: '—') ?></td><td><?= e($er['error']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel panel-flush mt-24">
  <div class="panel-head pad-head"><h2>Importaciones anteriores</h2></div>
  <?php if (!$historial): ?>
    <p class="empty">Todavía no hay importaciones.</p>
  <?php else: ?>
    <div class="table-wrap sin-borde">
      <table class="table">
        <thead><tr><th>Fecha</th><th>Archivo</th><th>Modo</th><th class="num">Filas</th><th class="num">Nuevas</th><th class="num">Actualizadas</th><th class="num">Sin cambios</th><th class="num">Rechazadas</th></tr></thead>
        <tbody>
        <?php foreach ($historial as $h): ?>
          <tr>
            <td class="nowrap"><?= e(fecha($h['creado_en'])) ?><span class="sub"><?= e($h['usuario'] ?? 'Consola') ?></span></td>
            <td><?= e($h['archivo']) ?></td>
            <td><?= $h['modo'] === 'APLICAR' ? 'Aplicada' : 'Validación' ?></td>
            <td class="num"><?= e($h['filas']) ?></td>
            <td class="num"><?= e($h['creadas']) ?></td>
            <td class="num"><?= e($h['actualizadas']) ?></td>
            <td class="num"><?= e($h['sin_cambios']) ?></td>
            <td class="num"><?= (int) $h['rechazadas'] > 0 ? '<a href="' . e(url('estudiantes/importar?ver=' . $h['id_importacion'])) . '">' . e($h['rechazadas']) . '</a>' : '0' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
