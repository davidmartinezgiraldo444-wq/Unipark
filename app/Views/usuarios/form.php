<?php
$propia = $usuario && (int) $usuario['id_usuario'] === \App\Services\AuthService::id();
$accion = $usuario ? url('usuarios/' . $usuario['id_usuario']) : url('usuarios');
?>
<header class="page-head">
  <div>
    <p class="eyebrow"><a href="<?= e(url('usuarios')) ?>">Usuarios</a></p>
    <h1><?= e($usuario ? $usuario['nombre'] : 'Nuevo usuario') ?></h1>
    <p><?= $usuario
        ? 'Rol actual: ' . e($usuario['rol']) . '. Los cambios de rol se aplican en la siguiente solicitud de la persona.'
        : 'Se genera una contraseña temporal que la persona debe cambiar en su primer ingreso.' ?></p>
  </div>
</header>

<?php if ($error): ?><p class="alert alert-err" role="alert"><?= e($error) ?></p><div class="panel-note"></div><?php endif; ?>

<section class="panel">
  <form method="post" action="<?= e($accion) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field">
        <label for="nombre">Nombre completo</label>
        <input id="nombre" name="nombre" type="text" value="<?= e(old($datos, 'nombre')) ?>" maxlength="120" required autocomplete="off">
      </div>
      <div class="field">
        <label for="login">Usuario o correo de acceso</label>
        <?php if ($usuario): ?>
          <input id="login" type="text" value="<?= e($usuario['login']) ?>" disabled>
          <span class="field-hint">El usuario no se cambia para conservar la trazabilidad.</span>
        <?php else: ?>
          <input id="login" name="login" type="text" value="<?= e(old($datos, 'login')) ?>" maxlength="120" required
                 autocomplete="off" autocapitalize="none" spellcheck="false">
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="id_rol">Rol</label>
        <select id="id_rol" name="id_rol" required <?= $propia ? 'disabled' : '' ?>>
          <option value="">Selecciona…</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r['id_rol']) ?>" <?= old($datos, 'id_rol') === (string) $r['id_rol'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($propia): ?>
          <input type="hidden" name="id_rol" value="<?= e($usuario['id_rol']) ?>">
          <span class="field-hint">No puedes cambiar tu propio rol.</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-ink" type="submit"><?= $usuario ? 'Guardar cambios' : 'Crear cuenta' ?></button>
      <a class="btn btn-line" href="<?= e(url('usuarios')) ?>">Volver</a>
    </div>
  </form>
</section>

<?php if ($usuario): $activo = (int) $usuario['activo'] === 1; ?>
  <div class="grid-2 panel-note">
    <section class="panel">
      <div class="panel-head"><h2>Restablecer acceso</h2></div>
      <p class="muted">Genera una contraseña temporal. La actual deja de funcionar de inmediato.</p>
      <form method="post" action="<?= e(url('usuarios/' . $usuario['id_usuario'] . '/clave')) ?>" class="form-actions"
            data-confirm="¿Generar una contraseña temporal para <?= e($usuario['login']) ?>?">
        <?= csrf_field() ?>
        <button class="btn btn-line" type="submit">Generar contraseña temporal</button>
      </form>
    </section>
    <section class="panel">
      <div class="panel-head"><h2><?= $activo ? 'Desactivar cuenta' : 'Activar cuenta' ?></h2></div>
      <p class="muted"><?= $activo ? 'La persona no podrá iniciar sesión. Sus registros se conservan.' : 'La persona podrá volver a iniciar sesión.' ?></p>
      <form method="post" action="<?= e(url('usuarios/' . $usuario['id_usuario'] . '/estado')) ?>" class="form-actions"
            data-confirm="<?= $activo ? '¿Desactivar la cuenta ' . e($usuario['login']) . '?' : '¿Activar la cuenta ' . e($usuario['login']) . '?' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="activo" value="<?= $activo ? '0' : '1' ?>">
        <button class="btn <?= $activo ? 'btn-danger' : 'btn-line' ?>" type="submit" <?= $propia && $activo ? 'disabled' : '' ?>>
          <?= $activo ? 'Desactivar' : 'Activar' ?>
        </button>
      </form>
    </section>
  </div>
<?php endif; ?>
