<div class="auth-card">
  <header>
    <h1>Iniciar sesión</h1>
    <p>Ingresa con la cuenta que te asignó el Administrador.</p>
  </header>

  <?php if ($error !== null): ?>
    <p class="alert alert-err" role="alert"><?= e($error) ?></p>
  <?php elseif ($aviso !== null): ?>
    <p class="alert" role="status"><?= e($aviso) ?></p>
  <?php endif; ?>

  <form class="auth-form" method="post" action="<?= e(url('login')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if (!empty($volver)): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>

    <div class="field">
      <label for="login">Usuario o correo</label>
      <input id="login" name="login" type="text" value="<?= e($login) ?>"
             autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="120" required autofocus>
    </div>

    <div class="field">
      <label for="clave">Contraseña</label>
      <div class="field-pass">
        <input id="clave" name="clave" type="password" autocomplete="current-password" maxlength="255" required>
        <button class="pass-toggle" type="button" aria-controls="clave" aria-pressed="false">Mostrar</button>
      </div>
    </div>

    <button class="btn btn-ink btn-block" type="submit">Ingresar</button>
  </form>

  <p class="auth-help">¿Olvidaste tu contraseña? Solicita el restablecimiento al Administrador del sistema.</p>
</div>
