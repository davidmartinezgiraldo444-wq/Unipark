<section class="error-page">
  <p class="eyebrow mono">Error <?= e($codigo) ?></p>
  <h1><?= e($titulo) ?></h1>
  <p><?= e($mensaje) ?></p>
  <div class="actions">
    <a class="btn btn-ink" href="<?= e(url(\App\Services\AuthService::usuario() ? 'panel' : '')) ?>">Ir al inicio</a>
  </div>
</section>
