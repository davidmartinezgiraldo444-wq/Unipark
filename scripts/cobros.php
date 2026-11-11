<?php
declare(strict_types=1);

/**
 * UniPark · Cobro automático al vencer la tolerancia (ejecutar cada minuto).
 *
 *   php scripts/cobros.php            Procesa una vez y termina (para cron / Programador de tareas).
 *   php scripts/cobros.php --bucle    Repite cada 20 segundos hasta detenerlo con Ctrl+C (demostración).
 *
 * Cron en Linux/hosting:   * * * * * php /ruta/UniPark/scripts/cobros.php >/dev/null 2>&1
 * Windows (XAMPP):         schtasks /Create /SC MINUTE /MO 1 /TN UniParkCobros /TR "C:\xampp\php\php.exe C:\xampp\htdocs\UniPark\scripts\cobros.php"
 *
 * Por cada visita con la tolerancia vencida: determina si corresponde cobro, verifica si ya se cobró
 * ese día, genera el cobro diario, debita el saldo si alcanza y, si no, deja el valor pendiente en Caja
 * y avisa al celular de la persona. Es idempotente: ejecutarlo de más no duplica cobros.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Services\ServicioCobro;

$bucle = in_array('--bucle', $argv, true);
do {
    try {
        Database::set(null);
        $r = (new ServicioCobro(Database::connection()))->procesarVencidos(500);
        // Eventos de acceso sin atender (esperando al portero) que superaron su tiempo.
        $r['eventos_expirados'] = (new \App\Services\ServicioAcceso(Database::connection()))->expirarVencidos();
        $linea = sprintf('[%s] procesadas %d · cobros %d (debitados %d, pendientes %d, días de estadía %d) · cubiertas %d · sin cobro %d · eventos expirados %d',
            date('Y-m-d H:i:s'), $r['procesadas'], $r['cobros'], $r['debitados'], $r['pendientes'], $r['dias_estadia'], $r['cubiertas'], $r['sin_cobro'], $r['eventos_expirados']);
        if ($r['procesadas'] > 0 || $r['dias_estadia'] > 0 || $r['eventos_expirados'] > 0) {
            Logger::info('Cobro automático', $r);
        }
        echo $linea, PHP_EOL;
    } catch (PDOException $e) {
        // MySQL apagado (XAMPP detenido): la tarea programada lo reintenta en un minuto. No se llena el log cada minuto.
        if (in_array((int) ($e->errorInfo[1] ?? 0), [2002, 2006], true) || str_contains($e->getMessage(), '[2002]')) {
            fwrite(STDERR, 'Base de datos no disponible; se reintentará.' . PHP_EOL);
            if (!$bucle) {
                exit(0);
            }
        } else {
            Logger::error('Cobro automático falló: ' . $e->getMessage(), ['archivo' => $e->getFile() . ':' . $e->getLine()]);
            fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
            if (!$bucle) {
                exit(1);
            }
        }
    } catch (Throwable $e) {
        Logger::error('Cobro automático falló: ' . $e->getMessage(), ['archivo' => $e->getFile() . ':' . $e->getLine()]);
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        if (!$bucle) {
            exit(1);
        }
    }
    if ($bucle) {
        sleep(20);
    }
} while ($bucle);
