<?php /** Rótulo visible en TODA pantalla de la copia de demostración. */ ?>
<?php if (config('instancia') === 'demo'): ?>
  <div class="demo-banner" role="note">MODO DEMOSTRACIÓN · datos ficticios · pagos simulados · no es el sistema real
    <?php if (tiempo_acelerado()): ?><span class="demo-acelerado">⏱ Tiempo acelerado para la demostración: tolerancia de <?= e(config('tolerancia_minutos')) ?> min (en el sistema real son 15)</span><?php endif; ?>
  </div>
<?php endif; ?>
