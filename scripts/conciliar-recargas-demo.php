<?php
declare(strict_types=1);

/**
 * UniPark · Conciliación de recargas de la copia de DEMOSTRACIÓN (pasarela simulada, base unipark_demo).
 * Es scripts/conciliar-recargas.php con la configuración de htdocs/UniParkDemo. Lo ejecuta la tarea programada
 * «UniPark\RecargasDemo» (scripts/cobros-programar.bat). Si la demo no existe, termina sin hacer nada.
 */

$config = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'UniParkDemo' . DIRECTORY_SEPARATOR . 'config-demo.php';
if (!is_file($config)) {
    exit(0);
}
define('UNIPARK_ENV_INSTANCIA', $config);
require __DIR__ . '/conciliar-recargas.php';
