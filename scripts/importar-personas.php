<?php
declare(strict_types=1);

/**
 * UniPark · Importación de la base institucional de personas desde CSV.
 *
 *   php scripts/importar-personas.php archivo.csv              Solo valida (no guarda nada).
 *   php scripts/importar-personas.php archivo.csv --aplicar    Crea y actualiza personas.
 *
 * Las columnas se reconocen por nombre (config/app.php → importacion.columnas). Nunca borra personas,
 * no toca saldos y no desactiva a quien no venga en el archivo. El resultado queda en la tabla
 * importaciones y, si se aplica, en la auditoría.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Services\ReglaNegocioException;
use App\Services\ServicioImportacion;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$archivo = $argv[1] ?? '';
if ($archivo === '' || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php scripts/importar-personas.php archivo.csv [--aplicar]\n");
    exit(2);
}
$modo = in_array('--aplicar', $argv, true) ? 'APLICAR' : 'VALIDAR';
try {
    $r = (new ServicioImportacion(Database::connection()))->procesar($archivo, basename($archivo), $modo, null);
} catch (ReglaNegocioException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
printf("%s · importación #%d\nFilas %d · nuevas %d · actualizadas %d · sin cambios %d · rechazadas %d\n",
    $modo === 'APLICAR' ? 'APLICADA' : 'SOLO VALIDACIÓN (no se guardó ninguna persona)',
    $r['id_importacion'], $r['filas'], $r['creadas'], $r['actualizadas'], $r['sin_cambios'], $r['rechazadas']);
foreach (array_slice($r['errores'], 0, 50) as $er) {
    printf("  fila %d %s: %s\n", $er['fila'], $er['documento'], $er['error']);
}
if (count($r['errores']) > 50) {
    printf("  … y %d más (ver en Personas → Importar base institucional)\n", count($r['errores']) - 50);
}
exit($r['rechazadas'] > 0 ? 3 : 0);
