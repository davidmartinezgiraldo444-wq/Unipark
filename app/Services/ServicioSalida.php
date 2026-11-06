<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PagoRepository;
use App\Repositories\VisitaRepository;
use PDO;

/** Salida en portería (sección 7.3). */
final class ServicioSalida
{
    private VisitaRepository $visitas;
    private PagoRepository $pagos;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->visitas = new VisitaRepository($pdo);
        $this->pagos = new PagoRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * Cierra la visita solo si está abierta y pagada (RF-12, RF-14).
     * Sin pago no guarda la hora de salida, audita la denegación y remite a Caja (RF-13).
     * @throws ReglaNegocioException
     */
    public function autorizar(int $idVisita, int $usuario): array
    {
        $this->pdo->beginTransaction();
        try {
            $visita = $this->visitas->find($idVisita, true);
            $pago = $visita ? $this->pagos->validoDeVisita($idVisita, true) : null;

            $error = ReglasVisita::evaluarSalida($visita, $pago);
            if ($error !== null) {
                $this->pdo->rollBack();
                $this->audit->log('SALIDA_DENEGADA', 'visitas', $visita ? $idVisita : null, 'DENEGADO', $error, [], $usuario);
                throw new ReglaNegocioException($error, ReglasVisita::mensaje($error));
            }

            $this->visitas->cerrar($idVisita, $usuario);
            $this->audit->log('SALIDA_AUTORIZADA', 'visitas', $idVisita, 'EXITO', null, [
                'id_pago'     => (int) $pago['id_pago'],
                'consecutivo' => $pago['consecutivo'],
            ], $usuario);
            $this->pdo->commit();
        } catch (ReglaNegocioException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->visitas->find($idVisita);
    }
}
