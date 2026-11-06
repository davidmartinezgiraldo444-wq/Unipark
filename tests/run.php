<?php
declare(strict_types=1);

/**
 * UniPark · Ejecutor de pruebas (sin dependencias externas).
 *
 *   php tests/run.php              Todas las pruebas
 *   php tests/run.php Integration  Solo una carpeta
 *   php tests/run.php Pago         Clases o métodos que contengan el texto
 *
 * Las pruebas de integración y seguridad usan TEST_DB_NAME, que se BORRA y recrea en cada caso.
 * Prepárala una vez con: php scripts/install.php --test
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Env;
use Tests\Support\AssertionFailed;
use Tests\Support\Skipped;

// La aplicación usará la base de pruebas desde aquí en adelante.
$baseApp = Env::get('DB_NAME', 'unipark_db');
$basePruebas = Env::get('TEST_DB_NAME', 'unipark_test');
if ($basePruebas === $baseApp) {
    fwrite(STDERR, "TEST_DB_NAME no puede ser igual a DB_NAME: las pruebas borran la base.\n");
    exit(1);
}
putenv('DB_NAME=' . $basePruebas);

spl_autoload_register(static function (string $clase): void {
    if (str_starts_with($clase, 'Tests\\')) {
        $archivo = __DIR__ . '/' . str_replace('\\', '/', substr($clase, 6)) . '.php';
        if (is_file($archivo)) {
            require $archivo;
        }
    }
});

$filtro = $argv[1] ?? '';
$archivos = [];
foreach (['Unit', 'Integration', 'Security'] as $carpeta) {
    foreach (glob(__DIR__ . "/{$carpeta}/*Test.php") as $archivo) {
        $archivos[] = [$carpeta, $archivo];
    }
}

$total = $fallas = $omitidas = 0;
$errores = [];
$inicio = microtime(true);
$color = stream_isatty(STDOUT);
$verde = static fn (string $t) => $color ? "\033[32m{$t}\033[0m" : $t;
$rojo = static fn (string $t) => $color ? "\033[31m{$t}\033[0m" : $t;
$gris = static fn (string $t) => $color ? "\033[90m{$t}\033[0m" : $t;

echo "UniPark · pruebas sobre la base '{$basePruebas}'\n";

foreach ($archivos as [$carpeta, $archivo]) {
    $clase = 'Tests\\' . $carpeta . '\\' . basename($archivo, '.php');
    require_once $archivo;
    $metodos = array_filter(get_class_methods($clase), static fn ($m) => str_starts_with($m, 'test'));
    $coincide = $filtro === '' || str_contains($clase, $filtro);
    $metodos = array_filter($metodos, static fn ($m) => $coincide || str_contains($m, $filtro));
    if (!$metodos) {
        continue;
    }

    echo "\n" . $carpeta . '/' . basename($archivo, '.php') . "\n";
    foreach ($metodos as $metodo) {
        $total++;
        $prueba = new $clase();
        $t0 = microtime(true);
        try {
            $prueba->setUp();
            $prueba->$metodo();
            $estado = $verde('✓');
        } catch (Skipped $e) {
            $omitidas++;
            $estado = $gris('○') . ' ' . $gris('omitida: ' . $e->getMessage());
        } catch (AssertionFailed $e) {
            $fallas++;
            $estado = $rojo('✗');
            $errores[] = "{$clase}::{$metodo}\n  " . $e->getMessage();
        } catch (Throwable $e) {
            $fallas++;
            $estado = $rojo('✗');
            $errores[] = "{$clase}::{$metodo}\n  " . $e::class . ': ' . $e->getMessage()
                . "\n  " . $e->getFile() . ':' . $e->getLine();
        } finally {
            try {
                $prueba->tearDown();
            } catch (Throwable) {
            }
        }
        $ms = (int) round((microtime(true) - $t0) * 1000);
        printf("  %s %s %s\n", $estado, $metodo, $gris("{$ms} ms"));
    }
}

foreach ($errores as $error) {
    echo "\n" . $rojo('FALLA ') . $error . "\n";
}

printf(
    "\n%d pruebas · %s · %d omitidas · %.1f s\n",
    $total,
    $fallas ? $rojo("{$fallas} fallidas") : $verde('0 fallidas'),
    $omitidas,
    microtime(true) - $inicio
);
exit($fallas > 0 ? 1 : 0);
