<?php
/**
 * Pantallas de cámara para el celular del portero: escáner de carnets y cámara de vehículos.
 * Espera $titulo y $config (se entrega al JavaScript como JSON, sin ejecutar código en línea).
 */
use App\Services\AuthService;

$usuario = AuthService::usuario();
?>
<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/head.php'; ?>
  <meta name="theme-color" content="#16181d">
  <meta name="unipark-latido" content="10000">
  <link rel="stylesheet" href="<?= e(asset('css/movil.css')) ?>">
</head>
<body class="movil">
<?php require __DIR__ . '/demo_banner.php'; ?>
  <header class="movil-head">
    <a class="brand brand-sm" href="<?= e(url('porteria')) ?>" aria-label="Volver a Portería">
      <span class="brand-mark" aria-hidden="true">P</span>
      <span class="brand-name">UniPark</span>
    </a>
    <span class="movil-user mono"><?= e($usuario['nombre'] ?? '') ?></span>
  </header>

  <main class="movil-main" id="contenido">
    <?= $contenido ?>
  </main>

  <script type="application/json" id="unipark-config"><?= json_encode($config ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script src="<?= e(asset('js/camara.js')) ?>"></script>
  <script src="<?= e(asset('js/vehiculos-ia.js')) ?>"></script>
  <?php if (can('COBRO_PROCESAR')): ?><script src="<?= e(asset('js/latido.js')) ?>"></script><?php endif; ?>
  <?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset('js/' . $script)) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
