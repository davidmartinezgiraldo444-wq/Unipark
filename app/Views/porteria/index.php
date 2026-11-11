<?php
use App\Services\ReglasVisita;

$esVisitante = $visitante !== null || ($estudiante && $estudiante['categoria'] === ReglasVisita::VISITANTE);
$categoria = $esVisitante ? ReglasVisita::VISITANTE : ($estudiante['categoria'] ?? null);

/** Formulario de entrada con los cuatro tipos de vehículo (clasificación manual desde el computador). */
$formEntrada = static function (?array $persona, ?string $documentoVisitante) use ($tarifas, $categoria): void { ?>
  <form method="post" action="<?= e(url('porteria/entrada')) ?>" data-entrada-form novalidate>
    <?= csrf_field() ?>
    <?php if ($persona): ?>
      <input type="hidden" name="id_estudiante" value="<?= e($persona['id_estudiante']) ?>">
      <input type="hidden" name="documento" value="<?= e($persona['documento']) ?>">
    <?php else: ?>
      <input type="hidden" name="visitante" value="1">
      <input type="hidden" name="documento" value="<?= e($documentoVisitante) ?>">
      <div class="field mb-16">
        <label for="nombre">Nombre del visitante <span class="muted">(opcional)</span></label>
        <input id="nombre" name="nombre" type="text" maxlength="80" autocomplete="off">
      </div>
    <?php endif; ?>

    <fieldset class="tipo-choice tipo-choice-4">
      <legend>Tipo de vehículo</legend>
      <?php foreach ((array) config('tipos_habilitados') as $tipo):
          $t = $tarifas[$tipo][$categoria] ?? null; ?>
        <label>
          <input type="radio" name="tipo" value="<?= e($tipo) ?>" required>
          <span class="t"><?= e(tipo_label($tipo)) ?></span>
          <span class="v"><?= $categoria === ReglasVisita::VISITANTE ? 'Sin cobro' : ($t ? e(money($t['valor'])) . '/día' : 'Sin tarifa') ?></span>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <p class="field-hint" data-tipo-hint>
      <?= $categoria === ReglasVisita::VISITANTE
          ? 'Visitante: se registra la entrada sin cobro.'
          : e(sprintf('Tarifa diaria de %s. %s de tolerancia sin cobro; se cobra una sola vez por día.', mb_strtolower(categoria_label($categoria)), minutos_texto((int) config('tolerancia_minutos')))) ?>
      Solo se registra el tipo de vehículo: sin placa, color ni fotos. En el celular la cámara lo reconoce sola.</p>

    <div class="form-actions">
      <button class="btn btn-ink" type="submit">Registrar entrada</button>
    </div>
  </form>
<?php };

/** Línea de estado del cobro de una visita abierta. */
$estadoCobro = static function (array $v): array {
    return match (true) {
        $v['liquidacion'] === 'LEGADO' => $v['pagada']
            ? [money($v['tarifa_aplicada']) . ' · ' . $v['consecutivo'], 'Pagada (esquema anterior)', true]
            : ['Pendiente', 'Valor ' . money($v['tarifa_aplicada']), false],
        $v['liquidacion'] === 'EN_TOLERANCIA' && strtotime($v['tolerancia_hasta']) <= time() => ['Incidencia', 'La tolerancia venció y el cobro automático no se procesó', false],
        $v['liquidacion'] === 'EN_TOLERANCIA' => ['En tolerancia', 'Hasta las ' . fecha($v['tolerancia_hasta'], 'H:i') . ' · sin cobro si sale antes', true],
        $v['liquidacion'] === 'NO_APLICA' => ['Sin cobro', $v['categoria'] === 'VISITANTE' ? 'Visitante' : tipo_label($v['tipo_vehiculo']) . ' sin tarifa', true],
        $v['cobro_estado'] === 'PAGADO' => [money($v['cobro_valor']) . ' · ' . $v['consecutivo'], $v['medio_pago'] === 'SALDO' ? 'Débito automático del saldo' : 'Pagado en Caja', true],
        $v['cobro_estado'] === 'ANULADO' => ['Cobro anulado', 'Sin valor a pagar', true],
        default => ['Pendiente', 'Cobro del día ' . money($v['cobro_valor']) . ' · sin saldo suficiente', false],
    };
};
?>
<header class="page-head">
  <div>
    <p class="eyebrow">Portería</p>
    <h1>Entrada y salida</h1>
    <p class="solo-escritorio">Busca a la persona por su documento o carnet.</p>
  </div>
  <div class="actions">
    <a class="btn btn-yellow" href="<?= e(url('porteria/escaner')) ?>">Abrir escáner en el celular</a>
  </div>
</header>

<?php /* El buscador es la acción principal del portero: va primero, también en el celular (hallazgo B4). */ ?>
<section class="panel porteria-buscar">
  <form class="search" method="get" action="<?= e(url('porteria')) ?>" role="search" data-documento-form>
    <div class="field">
      <label for="documento">Documento o código del carnet</label>
      <input id="documento" name="documento" type="text" inputmode="numeric" autocomplete="off"
             value="<?= e($documento) ?>" maxlength="20" required autofocus
             <?= $error ? 'aria-invalid="true" aria-describedby="doc-error"' : '' ?>>
    </div>
    <button class="btn btn-ink" type="submit">Buscar</button>
  </form>
  <?php if ($error): ?>
    <p class="block-msg err" id="doc-error" role="alert"><?= e($error) ?></p>
  <?php endif; ?>
</section>

<?php if (can('CLASIFICACION_RESOLVER')): ?>
  <section class="panel avisos-panel" data-avisos aria-live="polite">
    <div class="panel-head">
      <h2>Panel de portería</h2>
      <span class="avisos-contador" data-avisos-contador>Cargando…</span>
    </div>
    <div class="avisos-lista" data-avisos-lista></div>
    <h3 class="avisos-subtitulo">En tiempo real</h3>
    <p class="field-hint">Personas identificadas en el punto de acceso. Solo lectura. Cada detección de la IA aparece arriba para que la confirmes o la cambies; si la IA no reconoce el vehículo, eliges el tipo.</p>
    <ol class="actividad" data-avisos-actividad></ol>
  </section>
<?php endif; ?>

<div class="grid-main">
  <div class="stack">
    <?php if ($visitante !== null): ?>
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2>Visitante</h2>
            <span class="muted mono">Documento <?= e(mask_doc($visitante)) ?></span> <?= categoria_tag('VISITANTE') ?>
          </div>
          <span class="badge">NO REGISTRADO</span>
        </div>
        <p class="muted mb-16">El documento no está registrado: se clasifica como visitante. Los visitantes no generan cobro.</p>
        <?php if (can('ENTRADA_CREAR')) $formEntrada(null, $visitante); ?>
      </section>
    <?php endif; ?>

    <?php if ($estudiante && (int) $estudiante['activo'] === 1): ?>
      <section class="panel">
        <div class="panel-head">
          <div>
            <h2><?= e(trim($estudiante['nombres'] . ' ' . $estudiante['apellidos'])) ?></h2>
            <span class="muted mono">Doc. <?= e(mask_doc($estudiante['documento'])) ?></span> <?= categoria_tag($estudiante['categoria']) ?>
            <?php if (!$esVisitante): ?><span class="muted"> · Saldo <?= e(money($estudiante['saldo'])) ?></span><?php endif; ?>
          </div>
          <?= $abiertas ? estado_badge('EN_CURSO') : '<span class="badge">SIN VISITA ABIERTA</span>' ?>
        </div>

        <?php foreach ($abiertas as $v): [$pagoV, $pagoS, $puede] = $estadoCobro($v); ?>
          <div class="timeline mb-12">
            <div>
              <span class="k">Entrada</span>
              <span class="v"><?= e(fecha($v['entrada_en'], 'H:i')) ?> · <?= e(tipo_label($v['tipo_vehiculo'])) ?></span>
              <span class="s"><?= e(fecha($v['entrada_en'], 'd/m/Y')) ?> · hace <?= e(duracion($v['entrada_en'])) ?> · <?= $v['tipo_origen'] === 'IA' ? 'reconocido por IA' : 'clasificación manual' ?></span>
            </div>
            <div class="<?= $puede ? '' : 'pending' ?>">
              <span class="k">Cobro</span>
              <span class="v"><?= e($pagoV) ?></span>
              <span class="s"><?= e($pagoS) ?></span>
            </div>
            <div>
              <span class="k">Salida</span>
              <span class="v">—</span>
              <span class="s">Registró entrada: <?= e($v['entrada_por_nombre']) ?></span>
            </div>
          </div>
          <?php if (!$puede && $pagoV === 'Incidencia'): ?>
            <p class="block-msg err">Incidencia: el cobro automático no se procesó. Resuélvela sin detener la salida de los demás.</p>
            <form method="post" action="<?= e(url('cobros/visita/' . $v['id_visita'] . '/procesar')) ?>" class="form-actions mt-12">
              <?= csrf_field() ?><input type="hidden" name="volver" value="porteria"><input type="hidden" name="documento" value="<?= e($estudiante['documento']) ?>">
              <button class="btn btn-ink" type="submit">Procesar cobro</button>
            </form>
          <?php elseif (!$puede): ?><p class="block-msg err">Pago pendiente. Diríjase a Caja.</p><?php endif; ?>
          <?php if (can('SALIDA_AUTORIZAR')): ?>
            <form method="post" action="<?= e(url('porteria/salida')) ?>" class="form-actions mt-12">
              <?= csrf_field() ?>
              <input type="hidden" name="id_visita" value="<?= e($v['id_visita']) ?>">
              <input type="hidden" name="documento" value="<?= e($estudiante['documento']) ?>">
              <button class="btn <?= $puede ? 'btn-ok' : 'btn-line' ?>" type="submit">
                <?= $puede ? 'Autorizar salida' : 'Verificar pago y autorizar salida' ?>
              </button>
            </form>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if ((!$abiertas || $esVisitante) && can('ENTRADA_CREAR')): ?>
          <?php if ($abiertas): ?><h3 class="section-title">Nueva entrada</h3><?php endif; ?>
          <?php $formEntrada($estudiante, null); ?>
        <?php elseif (!$abiertas): ?>
          <p class="muted">La persona no tiene una visita abierta.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>

  <aside class="stack">
    <section class="panel">
      <div class="panel-head"><h2>Dentro ahora</h2></div>
      <dl class="dl">
        <dt>Automóviles</dt><dd><?= e($ocupacion['CARRO']) ?></dd>
        <dt>Motocicletas</dt><dd><?= e($ocupacion['MOTO']) ?></dd>
        <dt>Bicicletas</dt><dd><?= e($ocupacion['BICICLETA']) ?></dd>
        <?php if ($ocupacion['PATINETA'] > 0 || in_array('PATINETA', config('tipos_habilitados'), true)): ?><dt>Patinetas</dt><dd><?= e($ocupacion['PATINETA']) ?></dd><?php endif; ?>
        <dt>Visitantes</dt><dd><?= e($ocupacion['visitantes']) ?></dd>
        <dt>En tolerancia</dt><dd><?= e($ocupacion['tolerancia']) ?></dd>
        <dt>Pendientes de pago</dt><dd><?= e($ocupacion['pendientes']) ?></dd>
      </dl>
    </section>
    <section class="panel panel-flush">
      <div class="panel-head"><h2>Últimas entradas</h2></div>
      <?php if (!$recientes): ?>
        <p class="empty">Sin visitas abiertas.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <tbody>
            <?php foreach ($recientes as $v): ?>
              <tr>
                <td><?= e($v['nombres']) ?><span class="sub mono"><?= e(mask_doc($v['documento'])) ?> · <?= e(tipo_label($v['tipo_vehiculo'])) ?> · <?= e(categoria_label($v['categoria'])) ?></span></td>
                <td class="num"><?= e(fecha($v['entrada_en'], 'H:i')) ?><span class="sub"><?= e($estadoCobro($v)[0]) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Demostración</h2></div>
      <p class="muted">Códigos de barras de prueba para escanear con el celular.</p>
      <div class="form-actions mt-12"><a class="btn btn-line btn-sm" href="<?= e(url('porteria/carnets')) ?>">Carnets de prueba</a></div>
    </section>
  </aside>
</div>
