<?php
declare(strict_types=1);

/**
 * UniPark · Respaldo de la base de datos (RNF-05).
 *
 *   php scripts/backup.php                 Respalda DB_NAME en storage/backups/
 *   php scripts/backup.php --db=otra_base  Respalda otra base
 *   php scripts/backup.php --out=ruta.sql  Elige el archivo de salida
 *
 * Usa mysqldump con --single-transaction: no bloquea la operación mientras respalda.
 * Incluye disparadores. Los respaldos contienen datos personales: guárdalos con acceso restringido.
 */

require __DIR__ . '/lib.php';

use App\Helpers\Env;

$opciones = getopt('', ['db:', 'out:']);
$base = nombre_base($opciones['db'] ?? Env::get('DB_NAME', 'unipark_db'));
$destino = $opciones['out'] ?? null;

if ($destino !== null && is_file($destino)) {
    fail("El archivo {$destino} ya existe. Elige otro nombre.");
}

$archivo = respaldar($base, $destino);
printf("Respaldo creado: %s (%s KB)\n", $archivo, number_format(filesize($archivo) / 1024, 1));
