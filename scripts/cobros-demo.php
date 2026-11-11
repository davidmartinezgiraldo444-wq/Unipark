<?php
declare(strict_types=1);

/**
 * UniPark · Cobro automático de la copia de DEMOSTRACIÓN (base unipark_demo, tolerancia acelerada).
 * Es scripts/cobros.php con la configuración de htdocs/UniParkDemo. Lo ejecuta la tarea programada
 * «UniPark\CobrosDemo» (scripts/cobros-programar.bat). Si la demo no existe, termina sin hacer nada.
 */

$config = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'UniParkDemo' . DIRECTORY_SEPARATOR . 'config-demo.php';
if (!is_file($config)) {
    exit(0);
}
define('UNIPARK_ENV_INSTANCIA', $config);
require __DIR__ . '/cobros.php';
