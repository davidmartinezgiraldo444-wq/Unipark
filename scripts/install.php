<?php
declare(strict_types=1);

/**
 * UniPark · Instalación desde la consola (alternativa al instalador web instalar.php).
 *
 *   php scripts/install.php             Crea DB_NAME, aplica schema.sql + base.sql y la cuenta de la aplicación.
 *   php scripts/install.php --seed      Además carga los datos de demostración (seed.sql).
 *   php scripts/install.php --test      Prepara también la base de pruebas (TEST_DB_NAME).
 *   php scripts/install.php --yes       No pide confirmación si la base ya tiene tablas (las BORRA).
 *
 * Lee la conexión de .env o config/env.php. Usa DB_ADMIN_USER / DB_ADMIN_PASS para crear
 * la base y la cuenta DB_USER / DB_PASS, que recibe SELECT, INSERT y UPDATE, sin DELETE.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Env;
use App\Services\Instalador;
use App\Services\ReglaNegocioException;

$opciones = getopt('', ['seed', 'test', 'yes', 'help']);
if (isset($opciones['help'])) {
    echo file_get_contents(__FILE__, false, null, 0, 800), PHP_EOL;
    exit(0);
}

$appPass = Env::get('DB_PASS', '');
if ($appPass === '' || $appPass === 'cambia-esta-clave') {
    fwrite(STDERR, "Define DB_PASS en .env con una contraseña propia antes de instalar.\n");
    exit(1);
}

$bases = [Env::get('DB_NAME', 'unipark_db')];
if (isset($opciones['test'])) {
    $bases[] = Env::get('TEST_DB_NAME', 'unipark_test');
}

$instalador = new Instalador();
foreach ($bases as $i => $base) {
    $datos = [
        'host'         => Env::get('DB_HOST', '127.0.0.1'),
        'port'         => Env::get('DB_PORT', '3306'),
        'base'         => $base,
        'usuario'      => Env::get('DB_ADMIN_USER', 'root'),
        'clave'        => Env::get('DB_ADMIN_PASS', ''),
        'crear_cuenta' => true,
        'cuenta_app'   => Env::get('DB_USER', 'unipark_app'),
        'clave_app'    => $appPass,
        // La base de pruebas siempre lleva los datos de demostración: las pruebas los usan.
        'demo'         => isset($opciones['seed']) || $i > 0,
        'sobrescribir' => isset($opciones['yes']) || $i > 0,
    ];

    echo "→ {$base}\n";
    while (true) {
        try {
            $resultado = (new Instalador())->instalar($datos);
            break;
        } catch (ReglaNegocioException $e) {
            if ($e->codigo !== 'BASE_CON_DATOS') {
                fwrite(STDERR, $e->getMessage() . "\n");
                exit(1);
            }
            echo $e->getMessage(), "\nEscribe el nombre de la base para confirmar que se BORRE: ";
            if (trim((string) fgets(STDIN)) !== $base) {
                echo "Cancelado. No se modificó nada.\n";
                exit(1);
            }
            $datos['sobrescribir'] = true;
        }
    }
    foreach ($resultado['pasos'] as $paso) {
        echo "   ✓ {$paso}\n";
    }
}

$instalador->bloquear();
echo "\nListo. Inicia la aplicación con:\n  php -S localhost:8000 index.php\n";
echo "Cuentas: admin" . (isset($opciones['seed']) ? ', porteria, caja, caja2, auditor' : '') . " · contraseña Demo2026*\n";
