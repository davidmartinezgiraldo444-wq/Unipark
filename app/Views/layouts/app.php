<?php
use App\Services\AuthService;

$usuario = AuthService::usuario();
$rutaActual = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// Menú: solo se muestra lo que el rol puede usar; el servidor vuelve a verificarlo en cada ruta.
$grupos = [
    'Operación' => [
        ['panel',       'Panel',          true],
        ['porteria',    'Portería',       can('ENTRADA_CREAR', 'SALIDA_AUTORIZAR')],
        ['caja',        'Caja',           can('PAGO_REGISTRAR')],
        ['caja/cierre', 'Cierre de caja', can('CAJA_CIERRE')],
    ],
    'Gestión' => [
        ['estudiantes', 'Estudiantes y profesores', can('ESTUDIANTE_VER')],
        ['reportes',    'Reportes',       can('REPORTE_OPERATIVO', 'REPORTE_VER', 'REPORTE_CAJA')],
        ['tarifas',     'Tarifas',        can('TARIFA_GESTIONAR')],
        ['usuarios',    'Usuarios',       can('USUARIO_GESTIONAR')],
        ['auditoria',   'Auditoría',      can('AUDITORIA_VER')],
    ],
];
$activo = static function (string $ruta) use ($rutaActual): bool {
    $destino = parse_url(url($ruta), PHP_URL_PATH);
    if ($ruta === 'caja') {
        return $rutaActual === $destino || str_starts_with($rutaActual, $destino . '/recibo');
    }
    return $rutaActual === $destino || str_starts_with($rutaActual, $destino . '/');
};
$iniciales = $usuario ? mb_strtoupper(implode('', array_map(
    static fn ($p) => mb_substr($p, 0, 1),
    array_slice(preg_split('/\s+/', trim($usuario['nombre'])), 0, 2)
))) : '';
?>
<!doctype html>
<html lang="es">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body class="app">

  <aside class="sidebar">
    <a class="brand brand-sm" href="<?= e(url('panel')) ?>">
      <span class="brand-mark" aria-hidden="true">P</span>
      <span class="brand-name">UniPark</span>
    </a>

    <?php if ($usuario): ?>
      <nav class="side-nav" aria-label="Módulos">
        <?php foreach ($grupos as $grupo => $items):
            $visibles = array_filter($items, static fn ($i) => $i[2]);
            if (!$visibles) continue; ?>
          <span class="side-group"><?= e($grupo) ?></span>
          <?php foreach ($visibles as [$ruta, $etiqueta]): ?>
            <a href="<?= e(url($ruta)) ?>"<?= $activo($ruta) ? ' aria-current="page"' : '' ?>><?= e($etiqueta) ?></a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>

      <div class="side-user">
        <div class="side-user-id">
          <span class="avatar" aria-hidden="true"><?= e($iniciales) ?></span>
          <div>
            <strong><?= e($usuario['nombre']) ?></strong>
            <span class="mono"><?= e($usuario['rol']) ?></span>
          </div>
        </div>
        <div class="side-user-actions">
          <a href="<?= e(url('cambiar-clave')) ?>">Cambiar contraseña</a>
          <form method="post" action="<?= e(url('logout')) ?>">
            <?= csrf_field() ?>
            <button class="link-btn" type="submit">Cerrar sesión</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </aside>

  <main class="main" id="contenido">
    <?php require __DIR__ . '/flash.php'; ?>
    <?= $contenido ?>
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
  <?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset('js/' . $script)) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
