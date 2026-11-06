<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Validator;
use App\Repositories\TarifaRepository;
use PDO;

/** Cambio de tarifas con historial de vigencias (RF-06, RN-11). */
final class ServicioTarifa
{
    private TarifaRepository $tarifas;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->tarifas = new TarifaRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * Cierra la tarifa vigente y abre una nueva desde este momento.
     * Las visitas ya registradas conservan su tarifa_aplicada.
     * @throws ReglaNegocioException
     */
    public function cambiar(string $tipo, string $categoria, string $valor, int $usuario): int
    {
        $valor = str_replace(',', '.', trim($valor));
        if (!ReglasVisita::tipoValido($tipo)) {
            throw new ReglaNegocioException('TIPO_INVALIDO', 'Tipo de vehículo no válido.');
        }
        if (!ReglasVisita::categoriaValida($categoria)) {
            throw new ReglaNegocioException('CATEGORIA_INVALIDA', 'Categoría no válida.');
        }
        if (!Validator::esDinero($valor, (bool) config('permitir_tarifa_cero'))) {
            throw new ReglaNegocioException('VALOR_INVALIDO', config('permitir_tarifa_cero')
                ? 'El valor debe ser cero o positivo, con máximo dos decimales.'
                : 'El valor debe ser mayor que cero, con máximo dos decimales.');
        }

        $this->pdo->beginTransaction();
        try {
            $anterior = $this->tarifas->abierta($tipo, $categoria);
            if ($anterior !== null && ReglasVisita::montoCoincide($anterior['valor'], $valor)) {
                throw new ReglaNegocioException('SIN_CAMBIO', 'El valor es igual al de la tarifa vigente.');
            }
            $ahora = $this->pdo->query('SELECT NOW() AS ahora')->fetch()['ahora'];
            if ($anterior !== null) {
                $this->tarifas->cerrar((int) $anterior['id_tarifa'], $ahora);
            }
            $id = $this->tarifas->create($tipo, $categoria, $valor, $ahora, $usuario);
            $this->audit->log('TARIFA_CAMBIADA', 'tarifas', $id, 'EXITO', null, [
                'tipo'            => $tipo,
                'categoria'       => $categoria,
                'valor_anterior'  => $anterior['valor'] ?? null,
                'valor_nuevo'     => $valor,
                'tarifa_anterior' => $anterior ? (int) $anterior['id_tarifa'] : null,
            ], $usuario);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
