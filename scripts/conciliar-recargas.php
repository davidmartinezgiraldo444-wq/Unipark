<?php
declare(strict_types=1);

/**
 * UniPark · Conciliación de recargas en línea (ejecutar cada 5 minutos).
 *
 *   php scripts/conciliar-recargas.php
 *
 * Cron en Linux/hosting:   0-59/5 * * * * php /ruta/UniPark/scripts/conciliar-recargas.php >/dev/null 2>&1
 * Windows (XAMPP):         schtasks /Create /SC MINUTE /MO 5 /TN UniParkRecargas /TR "C:\xampp\php\php.exe C:\xampp\htdocs\UniPark\scripts\conciliar-recargas.php"
 *
 * El webhook de la pasarela es la vía principal de confirmación. Este proceso es la red de seguridad:
 *  - Consulta al proveedor (con la llave privada, desde el servidor) las recargas no aprobadas de las que se
 *    conoce una transacción: por un webhook PENDING o por el id que trajo el navegador al volver del pago.
 *  - Acredita SOLO si el proveedor responde APPROVED, la referencia y el monto coinciden. Idempotente.
 *  - Marca como vencidas las recargas creadas que nadie pagó.
 * No cobra nada a nadie: solo confirma pagos que la persona ya hizo voluntariamente.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Services\Pasarela\Pasarelas;
use App\Services\ServicioRecargaLinea;

if (!Pasarelas::disponible()) {
    echo '[', date('Y-m-d H:i:s'), "] Recargas en línea deshabilitadas o mal configuradas: nada que conciliar.\n";
    exit(0);
}

try {
    $r = (new ServicioRecargaLinea(Database::connection()))->conciliar();
    printf("[%s] consultadas %d · acreditadas %d · actualizadas %d · errores %d · vencidas %d\n",
        date('Y-m-d H:i:s'), $r['consultadas'], $r['acreditadas'], $r['actualizadas'], $r['errores'], $r['expiradas']);
    if ($r['consultadas'] > 0 || $r['expiradas'] > 0) {
        Logger::info('Conciliación de recargas', $r);
    }
} catch (PDOException $e) {
    // MySQL apagado: la tarea programada reintenta en 5 minutos, sin llenar el log.
    if (in_array((int) ($e->errorInfo[1] ?? 0), [2002, 2006], true) || str_contains($e->getMessage(), '[2002]')) {
        fwrite(STDERR, 'Base de datos no disponible; se reintentará.' . PHP_EOL);
        exit(0);
    }
    Logger::error('Conciliación de recargas falló: ' . $e->getMessage(), ['archivo' => $e->getFile() . ':' . $e->getLine()]);
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $e) {
    Logger::error('Conciliación de recargas falló: ' . $e->getMessage(), ['archivo' => $e->getFile() . ':' . $e->getLine()]);
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
