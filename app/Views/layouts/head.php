<?php /** Etiquetas comunes del <head>. Espera $titulo. */ ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e(($titulo ?? '') !== '' ? $titulo . ' · UniPark' : 'UniPark') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
  <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
