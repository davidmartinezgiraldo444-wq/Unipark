<?php $usuario = \App\Services\AuthService::usuario(); ?>
<div class="auth-card">
  <header>
    <h1>Cambia tu contraseña</h1>
    <p><?= (int) ($usuario['debe_cambiar_clave'] ?? 0) === 1
        ? 'Estás usando una contraseña temporal. Define una propia para continuar.'
        : 'Define una nueva contraseña para tu cuenta.' ?></p>
  </header>

  <?php if ($error !== null): ?>
    <p class="alert alert-err" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <form class="auth-form" method="post" action="<?= e(url('cambiar-clave')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="text" name="usuario" value="<?= e($usuario['login'] ?? '') ?>" autocomplete="username" hidden>

    <div class="field">
      <label for="actual">Contraseña actual</label>
      <input id="actual" name="actual" type="password" autocomplete="current-password" maxlength="255" required autofocus>
    </div>
    <div class="field">
      <label for="nueva">Nueva contraseña</label>
      <input id="nueva" name="nueva" type="password" autocomplete="new-password" maxlength="255" required
             aria-describedby="nueva-ayuda">
      <span class="field-hint" id="nueva-ayuda">Mínimo <?= (int) config('clave_min_longitud') ?> caracteres, con letras y números.</span>
    </div>
    <div class="field">
      <label for="confirmacion">Confirma la nueva contraseña</label>
      <input id="confirmacion" name="confirmacion" type="password" autocomplete="new-password" maxlength="255" required>
    </div>

    <button class="btn btn-ink btn-block" type="submit">Guardar contraseña</button>
  </form>

  <form class="auth-help auth-links" method="post" action="<?= e(url('logout')) ?>">
    <?php if ((int) ($usuario['debe_cambiar_clave'] ?? 0) !== 1): ?>
      <a href="<?= e(url('panel')) ?>">← Volver sin cambiar</a>
    <?php endif; ?>
    <?= csrf_field() ?>
    <button class="link-btn link-btn-muted" type="submit">Cerrar sesión</button>
  </form>
</div>
