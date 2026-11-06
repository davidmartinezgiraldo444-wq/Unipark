<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body class="plain">
  <header class="plain-head">
    <a class="brand" href="<?= e(url()) ?>">
      <span class="brand-mark" aria-hidden="true">P</span>
      <span class="brand-name">UniPark</span>
    </a>
  </header>
  <main class="plain-main"><?= $contenido ?></main>
</body>
</html>
