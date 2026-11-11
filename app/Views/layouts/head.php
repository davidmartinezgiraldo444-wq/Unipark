<?php /** Etiquetas comunes del <head>. Espera $titulo. */ ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <meta name="unipark-base" content="<?= e(url('')) ?>">
  <?php if (!empty($redirigir)): ?>
  <!-- Redirección iniciada por esta página (mismo sitio): el navegador sí envía la cookie de sesión. -->
  <meta http-equiv="refresh" content="1;url=<?= e($redirigir) ?>">
  <?php endif; ?>
  <?php if (\App\Services\AuthService::usuario()): ?>
  <meta name="csrf-token" content="<?= e(\App\Helpers\Csrf::token()) ?>">
  <?php endif; ?>
  <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
  <title><?= e(($titulo ?? '') !== '' ? $titulo . ' · UniPark' : 'UniPark') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/senaletica.css')) ?>">
  <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
