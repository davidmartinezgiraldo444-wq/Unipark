<?php
// Acceso, cambio de contraseña e instalación. La imagen del campus es la marca de agua de fondo.
$instalando = ($titulo ?? '') === 'Instalación';
?>
<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body class="auth">
<?php require __DIR__ . '/demo_banner.php'; ?>

  <aside class="auth-side">
    <a class="brand brand-sm" href="<?= e(url('inicio')) ?>" aria-label="UniPark, conocer el sistema">
      <span class="brand-mark" aria-hidden="true">P</span>
      <span class="brand-name">UniPark</span>
    </a>

    <div class="auth-side-body">
      <?php if ($instalando): ?>
        <p class="eyebrow">Instalación guiada</p>
        <h2>Tres minutos y listo.</h2>
        <p>El instalador crea la base de datos, las tablas con sus reglas de integridad y las cuentas iniciales. Al terminar se bloquea solo.</p>
        <ol class="auth-steps">
          <li><span class="mono">01</span>Datos de conexión a MySQL</li>
          <li><span class="mono">02</span>Creación de tablas y cuentas</li>
          <li><span class="mono">03</span>Ingreso con <span class="mono">admin</span> / <span class="mono">demo2026*</span></li>
        </ol>
      <?php else: ?>
        <h2>Cada cuenta es individual.</h2>
        <p>Cada entrada, pago y salida queda registrada con el funcionario que la realizó. No compartas tu usuario ni tu contraseña.</p>
        <ul class="auth-roles">
          <li><span class="mono">Portería</span>Entradas y salidas</li>
          <li><span class="mono">Caja</span>Pagos y comprobantes</li>
        </ul>
      <?php endif; ?>
    </div>

    <p class="auth-side-foot">UniPark · Gestión de parqueadero</p>
  </aside>

  <main class="auth-main">
    <?php if (!$instalando): ?><a class="auth-back" href="<?= e(url('inicio')) ?>">Conocer UniPark →</a><?php endif; ?>
    <?= $contenido ?>
    <p class="auth-legal">El tratamiento de datos se ajusta a la política institucional y a la Ley 1581 de 2012. Las sesiones se cierran por inactividad.</p>
  </main>

  <dialog class="confirm" id="confirmar" aria-labelledby="confirmar-titulo">
    <form method="dialog">
      <h2 id="confirmar-titulo">Confirmar</h2>
      <p data-confirm-texto></p>
      <div class="confirm-actions">
        <button class="btn btn-line" value="cancelar">Cancelar</button>
        <button class="btn btn-ink" value="ok">Confirmar</button>
      </div>
    </form>
  </dialog>

  <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
