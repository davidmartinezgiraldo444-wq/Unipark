<?php
declare(strict_types=1);

/**
 * Proceso auxiliar para las pruebas de concurrencia (CP-03).
 * Espera hasta el instante indicado y ejecuta la operación, para que varios procesos
 * lleguen a la base de datos al mismo tiempo. Imprime el resultado en JSON.
 *
 *   php concurrente.php entrada <id_estudiante> <tipo> <id_usuario> <inicio_microtime>
 *   php concurrente.php pago    <id_visita> <medio> <id_usuario> <inicio_microtime>
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';
putenv('DB_NAME=' . App\Helpers\Env::get('TEST_DB_NAME', 'unipark_test'));

use App\Helpers\Database;
use App\Services\ReglaNegocioException;
use App\Services\ServicioEntrada;
use App\Services\ServicioPago;

[, $operacion, $id, $param, $usuario, $inicio] = $argv;

$pdo = Database::connection();
while (microtime(true) < (float) $inicio) {
    usleep(500);
}

try {
    if ($operacion === 'entrada') {
        $r = (new ServicioEntrada($pdo))->registrar((int) $id, $param, (int) $usuario);
        echo json_encode(['ok' => true, 'id' => (int) $r['id_visita']]);
    } else {
        $r = (new ServicioPago($pdo))->registrar((int) $id, $param, (int) $usuario);
        echo json_encode(['ok' => true, 'id' => (int) $r['id_pago']]);
    }
} catch (ReglaNegocioException $e) {
    echo json_encode(['ok' => false, 'codigo' => $e->codigo]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'codigo' => 'ERROR', 'mensaje' => $e->getMessage()]);
}
