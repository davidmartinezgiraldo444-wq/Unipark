<?php
use App\Helpers\Session;

foreach (Session::takeFlash() as $f):
    if ($f['tipo'] === 'clave'): ?>
      <div class="alert alert-key" role="status">
        <span>Contraseña temporal, se muestra una sola vez:</span>
        <code class="mono"><?= e($f['mensaje']) ?></code>
      </div>
    <?php else: ?>
      <p class="alert<?= $f['tipo'] === 'err' ? ' alert-err' : ($f['tipo'] === 'ok' ? ' alert-ok' : '') ?>"
         role="<?= $f['tipo'] === 'err' ? 'alert' : 'status' ?>"><?= e($f['mensaje']) ?></p>
    <?php endif;
endforeach;
