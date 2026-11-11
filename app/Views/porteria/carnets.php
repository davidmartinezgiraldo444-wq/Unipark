<?php /** Hoja imprimible de carnés FICTICIOS (Code 128, QR y PDF417) para la demostración. */ ?>
<header class="page-head">
  <div>
    <p class="eyebrow">Portería · Demostración</p>
    <h1>Carnés de prueba</h1>
    <p>Imprime esta hoja o ábrela en otro celular y escanéalos con el escáner de portería. Todos los datos son
       <strong>ficticios</strong>. Cada persona tiene tres formatos: Code 128 (carné), QR y PDF417 de prueba (estructura
       de cédula que UniPark sabe leer, con encabezado «UNIPARK-FICTICIO»; no es un documento real).</p>
  </div>
  <div class="actions"><button class="btn btn-line" type="button" data-print>Imprimir</button></div>
</header>

<div class="carnets">
  <?php foreach ($personas as $p): ?>
    <article class="carnet">
      <div class="carnet-head">
        <strong><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></strong>
        <?= categoria_tag($p['categoria']) ?>
      </div>
      <p class="carnet-sello">Carné ficticio · solo demostración · <span class="mono"><?= e(mask_doc($p['documento'])) ?></span></p>
      <div class="carnet-codigos">
        <figure><canvas class="carnet-codigo" data-bcid="code128" data-texto="<?= e($p['codigo']) ?>" role="img" aria-label="Code 128"></canvas><figcaption>Code 128</figcaption></figure>
        <figure><canvas class="carnet-qr" data-bcid="qrcode" data-texto="<?= e($p['codigo']) ?>" role="img" aria-label="Código QR"></canvas><figcaption>QR</figcaption></figure>
        <?php if ($p['pdf417']): ?>
          <figure class="carnet-ancho"><canvas class="carnet-pdf417" data-bcid="pdf417" data-texto="<?= e($p['pdf417']) ?>" role="img" aria-label="PDF417 de prueba"></canvas><figcaption>PDF417 de prueba</figcaption></figure>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<p class="muted mt-24">Para un visitante, escribe o escanea un documento que no esté registrado: el portero decide su ingreso.</p>

<script src="https://cdn.jsdelivr.net/npm/bwip-js@4.7.0/dist/bwip-js-min.js" integrity="sha384-JI1F3FlpZZtMawSamp5pGt6y2zlVSQ1MPtBf9sRs1rCJ4jj5HQ/L8SNJCHJtSGJo" crossorigin="anonymous"></script>
<script src="<?= e(asset('js/carnets.js')) ?>"></script>
