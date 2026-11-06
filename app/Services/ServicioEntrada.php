<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\EstudianteRepository;
use App\Repositories\PagoRepository;
use App\Repositories\TarifaRepository;
use App\Repositories\VisitaRepository;
use PDO;
use PDOException;

/** Entrada en portería (sección 7.1) y anulación de visitas (sección 7.4). */
final class ServicioEntrada
{
    private EstudianteRepository $estudiantes;
    private VisitaRepository $visitas;
    private TarifaRepository $tarifas;
    private PagoRepository $pagos;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->estudiantes = new EstudianteRepository($pdo);
        $this->visitas = new VisitaRepository($pdo);
        $this->tarifas = new TarifaRepository($pdo);
        $this->pagos = new PagoRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * Registra la entrada con la tarifa vigente copiada en la visita (RF-07, RN-11).
     * Bloquea la fila del estudiante para serializar entradas simultáneas (RN-13);
     * el índice único visita_abierta es la segunda barrera.
     * @throws ReglaNegocioException
     */
    public function registrar(int $idEstudiante, string $tipo, int $usuario): array
    {
        if (!ReglasVisita::tipoValido($tipo)) {
            throw new ReglaNegocioException('TIPO_INVALIDO', 'Selecciona el tipo de vehículo: CARRO o MOTO.');
        }

        $this->pdo->beginTransaction();
        try {
            $estudiante = $this->estudiantes->find($idEstudiante, true);
            if ($estudiante === null || (int) $estudiante['activo'] !== 1) {
                throw new ReglaNegocioException(
                    'ESTUDIANTE_NO_DISPONIBLE',
                    'El estudiante no está registrado o está inactivo. Remítelo a Administración.'
                );
            }

            $abierta = $this->visitas->abiertaDeEstudiante($idEstudiante, true);
            if ($abierta !== null) {
                throw new ReglaNegocioException(
                    'VISITA_ABIERTA',
                    'El estudiante ya tiene una visita abierta. No se registró una nueva entrada.',
                    ['id_visita' => (int) $abierta['id_visita']]
                );
            }

            // La tarifa depende del tipo de vehículo y de la categoría de la persona (estudiante o profesor).
            $categoria = $estudiante['categoria'];
            $tarifa = $this->tarifas->vigente($tipo, $categoria);
            if ($tarifa === null) {
                throw new ReglaNegocioException(
                    'SIN_TARIFA',
                    "No hay una tarifa vigente para {$tipo} · {$categoria}. Comunícate con el Administrador."
                );
            }

            $id = $this->visitas->create($idEstudiante, $tipo, $categoria, (int) $tarifa['id_tarifa'], $tarifa['valor'], $usuario);
            $this->audit->log('ENTRADA_REGISTRADA', 'visitas', $id, 'EXITO', null, [
                'tipo'            => $tipo,
                'categoria'       => $categoria,
                'id_tarifa'       => (int) $tarifa['id_tarifa'],
                'tarifa_aplicada' => $tarifa['valor'],
            ], $usuario);
            $this->pdo->commit();
        } catch (ReglaNegocioException $e) {
            $this->pdo->rollBack();
            if ($e->codigo === 'VISITA_ABIERTA') {
                $this->rechazoDuplicado($idEstudiante, $usuario, $e->datos['id_visita'] ?? null);
            }
            throw $e;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if (Database::isDuplicate($e)) {
                $this->rechazoDuplicado($idEstudiante, $usuario, null);
                throw new ReglaNegocioException(
                    'VISITA_ABIERTA',
                    'El estudiante ya tiene una visita abierta. No se registró una nueva entrada.'
                );
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->visitas->find($id);
    }

    /**
     * Anulación excepcional de una visita abierta, con motivo (RN-12). Nunca borra la fila.
     * Si la visita tiene un pago válido, primero debe anularse el pago.
     * @throws ReglaNegocioException
     */
    public function anular(int $idVisita, int $usuario, string $motivo): void
    {
        $motivo = trim($motivo);
        $min = (int) config('motivo_min_longitud');
        if (mb_strlen($motivo) < $min) {
            throw new ReglaNegocioException('MOTIVO', "Describe el motivo de la anulación (mínimo {$min} caracteres).");
        }

        $this->pdo->beginTransaction();
        try {
            $visita = $this->visitas->find($idVisita, true);
            if ($visita === null || $visita['estado'] !== 'EN_CURSO') {
                throw new ReglaNegocioException('VISITA_NO_ABIERTA', 'Solo se puede anular una visita en curso.');
            }
            if ($this->pagos->validoDeVisita($idVisita, true) !== null) {
                throw new ReglaNegocioException('VISITA_PAGADA', 'La visita tiene un pago confirmado. Anula primero el pago.');
            }
            $this->visitas->anular($idVisita, $usuario, $motivo);
            $this->audit->log('VISITA_ANULADA', 'visitas', $idVisita, 'EXITO', $motivo, [], $usuario);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function rechazoDuplicado(int $idEstudiante, int $usuario, ?int $idVisita): void
    {
        $this->audit->log('ENTRADA_RECHAZADA', 'visitas', $idVisita, 'DENEGADO', 'El estudiante ya tiene una visita abierta', [
            'id_estudiante' => $idEstudiante,
        ], $usuario);
    }
}
