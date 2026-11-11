<?php /** Página puente al volver de la pasarela. Sin sesión y sin datos de la recarga: solo lleva a Mi saldo. */ ?>
<div class="auth-card">
  <header>
    <h1>Volviendo a UniPark…</h1>
    <p>Estamos llevándote al estado de tu recarga. Tu saldo se actualiza solo cuando la pasarela confirma el pago.</p>
  </header>
  <p><a class="btn btn-ink btn-block" href="<?= e($destino) ?>">Ver el estado de mi recarga</a></p>
  <p class="auth-help">Si la página no avanza sola, toca el botón.</p>
</div>
