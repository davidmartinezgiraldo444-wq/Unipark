<?php
declare(strict_types=1);

/**
 * UniPark · Restauración de un respaldo.
 *
 *   php scripts/restore.php storage/backups/unipark_db_20261023_090000.sql
 *   php scripts/restore.php archivo.sql --db=otra_base
 *   php scripts/restore.php archivo.sql --yes          Sin confirmación (solo pruebas automáticas)
 *
 * ATENCIÓN: reemplaza el contenido actual de la base. Antes de restaurar se crea
 * automáticamente un respaldo de seguridad del estado actual en storage/backups/.
 */

require __DIR__ . '/lib.php';

use App\Helpers\Env;

// Opciones en cualquier posición (getopt se detiene en el primer argumento sin guiones).
$opciones = [];
$archivo = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opciones[$m[1]] = $m[2] ?? true;
    } else {
        $archivo ??= $arg;
    }
}
$base = nombre_base(is_string($opciones['db'] ?? null) ? $opciones['db'] : Env::get('DB_NAME', 'unipark_db'));

if ($archivo === null || !is_file($archivo)) {
    fail("Uso: php scripts/restore.php <archivo.sql> [--db=base] [--yes]\nNo se encontró el archivo indicado.");
}
$cabecera = (string) file_get_contents($archivo, false, null, 0, 2048);
if (!str_contains($cabecera, 'MySQL dump') && !str_contains($cabecera, 'MariaDB dump')) {
    fail('El archivo no parece un respaldo generado por mysqldump. No se restauró nada.');
}

if (!isset($opciones['yes'])) {
    echo "Se reemplazará el contenido de la base '{$base}' con:\n  {$archivo}\n";
    echo "Escribe el nombre de la base para confirmar: ";
    if (trim((string) fgets(STDIN)) !== $base) {
        echo "Cancelado. No se modificó nada.\n";
        exit(1);
    }
}

if (!isset($opciones['sin-respaldo-previo'])) {
    $previo = respaldar($base, BASE_PATH . '/storage/backups/' . $base . '_antes_de_restaurar_' . date('Ymd_His') . '.sql');
    echo "Respaldo de seguridad del estado actual: {$previo}\n";
}

restaurar($base, $archivo);
echo "Restauración completa en '{$base}'.\n";
