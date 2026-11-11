<?php $marcado = static fn (string $k) => !empty($datos[$k]) ? 'checked' : ''; ?>
<div class="auth-card auth-card-wide">
  <header>
    <p class="eyebrow">Instalación</p>
    <h1>Conectar UniPark con MySQL</h1>
    <p>Escribe los datos de tu servidor MySQL. En XAMPP normalmente son <span class="mono">127.0.0.1</span>, puerto <span class="mono">3306</span> y usuario <span class="mono">root</span> sin contraseña.</p>
  </header>

  <?php if ($error !== null): ?>
    <p class="alert alert-err" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <ul class="checklist" aria-label="Requisitos del servidor">
    <?php foreach ($requisitos as $r): ?>
      <li class="<?= $r['ok'] ? 'ok' : 'err' ?>"><span><?= e($r['nombre']) ?></span><span class="muted"><?= e($r['detalle']) ?></span></li>
    <?php endforeach; ?>
  </ul>

  <form class="auth-form" method="post" action="<?= e(url('instalar.php')) ?>" novalidate
        data-confirm="Se creará la base de datos indicada con las tablas, reglas y cuentas de UniPark. ¿Continuar?">
    <?= csrf_field() ?>

    <fieldset class="form-section">
      <legend>Servidor MySQL</legend>
      <div class="form-grid form-grid-3">
        <div class="field span-2">
          <label for="host">Servidor</label>
          <input id="host" name="host" type="text" value="<?= e($datos['host']) ?>" required autocomplete="off" spellcheck="false">
        </div>
        <div class="field">
          <label for="port">Puerto</label>
          <input id="port" name="port" type="text" inputmode="numeric" value="<?= e($datos['port']) ?>" required>
        </div>
      </div>
      <div class="field">
        <label for="base">Nombre de la base de datos</label>
        <input id="base" name="base" type="text" value="<?= e($datos['base']) ?>" required autocomplete="off" spellcheck="false">
        <span class="field-hint">Se crea si no existe. Letras, números y guion bajo.</span>
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="usuario">Usuario de MySQL</label>
          <input id="usuario" name="usuario" type="text" value="<?= e($datos['usuario']) ?>" required autocomplete="off" spellcheck="false">
        </div>
        <div class="field">
          <label for="clave">Contraseña de MySQL</label>
          <div class="field-pass">
            <input id="clave" name="clave" type="password" autocomplete="new-password">
            <button class="pass-toggle" type="button" aria-controls="clave" aria-pressed="false">Mostrar</button>
          </div>
          <span class="field-hint">En XAMPP, vacía.</span>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-section">
      <legend>Opciones</legend>
      <label class="check">
        <input type="checkbox" name="crear_cuenta" value="1" <?= $marcado('crear_cuenta') ?>>
        <span><strong>Crear una cuenta de la aplicación con privilegios mínimos</strong> (recomendado). UniPark se conectará con ella, sin permiso para borrar datos. Su contraseña se genera automáticamente.</span>
      </label>
      <div class="field check-detail">
        <label for="cuenta_app">Nombre de la cuenta de la aplicación</label>
        <input id="cuenta_app" name="cuenta_app" type="text" value="<?= e($datos['cuenta_app']) ?>" autocomplete="off" spellcheck="false">
      </div>
      <label class="check">
        <input type="checkbox" name="demo" value="1" <?= $marcado('demo') ?>>
        <span><strong>Cargar datos de demostración</strong>: usuarios de cada rol, estudiantes, profesores, visitas y pagos de ejemplo.</span>
      </label>
      <label class="check">
        <input type="checkbox" name="sobrescribir" value="1" <?= $marcado('sobrescribir') ?>>
        <span><strong>Reemplazar las tablas existentes</strong> si la base ya tiene datos. <span class="text-err">Se borrará su contenido.</span></span>
      </label>
    </fieldset>

    <button class="btn btn-ink btn-block" type="submit" <?= $cumple ? '' : 'disabled' ?>>Instalar UniPark</button>
  </form>

  <p class="auth-help">Todas las cuentas iniciales usan la contraseña <span class="mono">demo2026*</span>. Cada persona puede cambiarla desde «Cambiar contraseña» después de ingresar.</p>
</div>
