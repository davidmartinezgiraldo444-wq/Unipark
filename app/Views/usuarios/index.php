<header class="page-head">
  <div>
    <p class="eyebrow">Usuarios</p>
    <h1>Cuentas y roles</h1>
    <p>Cada funcionario tiene una cuenta individual. Una cuenta desactivada no puede iniciar sesión y su historial se conserva.</p>
  </div>
  <div class="actions"><a class="btn btn-ink" href="<?= e(url('usuarios/nuevo')) ?>">Nuevo usuario</a></div>
</header>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $u): $activo = (int) $u['activo'] === 1; ?>
      <tr<?= $activo ? '' : ' class="is-off"' ?>>
        <td><?= e($u['nombre']) ?><?php if ((int) $u['id_usuario'] === \App\Services\AuthService::id()): ?> <span class="muted">(tú)</span><?php endif; ?></td>
        <td class="mono"><?= e($u['login']) ?></td>
        <td><?= e($u['rol']) ?></td>
        <td>
          <?= $activo ? '<span class="dot dot-ok"></span>Activa' : '<span class="dot"></span>Inactiva' ?>
          <?php if ((int) $u['debe_cambiar_clave'] === 1 && $activo): ?><span class="sub">Clave temporal</span><?php endif; ?>
        </td>
        <td class="nowrap"><?= e(fecha($u['ultimo_acceso'])) ?></td>
        <td class="actions"><a href="<?= e(url('usuarios/' . $u['id_usuario'] . '/editar')) ?>">Gestionar</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
