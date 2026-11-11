<div class="auth-card auth-card-wide">
  <header>
    <p class="eyebrow">Instalación completa</p>
    <h1>UniPark está listo.</h1>
    <p>La base de datos quedó creada y el instalador se bloqueó para que nadie pueda volver a ejecutarlo.</p>
  </header>

  <ul class="checklist">
    <?php foreach ($pasos as $paso): ?>
      <li class="ok"><span><?= e($paso) ?></span></li>
    <?php endforeach; ?>
  </ul>

  <h2 class="section-title">Cuentas para ingresar</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Usuario</th><th>Rol</th><th>Contraseña</th></tr></thead>
      <tbody>
        <tr><td class="mono">admin</td><td>Administrador</td><td class="mono">demo2026*</td></tr>
        <?php if ($demo): ?>
          <tr><td class="mono">porteria</td><td>Portería</td><td class="mono">demo2026*</td></tr>
          <tr><td class="mono">caja</td><td>Caja</td><td class="mono">demo2026*</td></tr>
          <tr><td class="mono">caja2</td><td>Caja</td><td class="mono">demo2026*</td></tr>
          <tr><td class="mono">auditor</td><td>Auditor</td><td class="mono">demo2026*</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="alert alert-warn panel-note">Cambia estas contraseñas desde «Cambiar contraseña» antes de usar UniPark con datos reales.</p>

  <div class="form-actions">
    <a class="btn btn-ink btn-block" href="<?= e(url('login')) ?>">Ir a UniPark</a>
  </div>
</div>
