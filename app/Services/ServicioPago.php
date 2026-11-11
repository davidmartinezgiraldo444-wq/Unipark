<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\CobroRepository;
use App\Repositories\EstudianteRepository;
use App\Repositories\PagoRepository;
use App\Repositories\SaldoRepository;
use App\Repositories\VisitaRepository;
use PDO;
use PDOException;

/** Pago en caja (sección 7.2) y anulación de pagos. */
final class ServicioPago
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
     * Registra un pago único por el valor exacto de la tarifa aplicada (RF-10, RF-11).
     * El monto no llega desde el formulario: se toma de la visita.
     * El pago solo existe como confirmado si la transacción termina bien.
     * @throws ReglaNegocioException
     */
    public function registrar(int $idVisita, string $medio, int $usuario, ?string $referencia = null): array
    {
        if (!array_key_exists($medio, config('medios_pago'))) {
            throw new ReglaNegocioException('MEDIO_INVALIDO', 'Selecciona un medio de pago autorizado.');
        }
        $referencia = $referencia !== null && trim($referencia) !== '' ? mb_substr(trim($referencia), 0, 60) : null;

        $this->pdo->beginTransaction();
        try {
            $visita = $this->visitas->find($idVisita, true);
            $existente = $visita ? $this->pagos->validoDeVisita($idVisita, true) : null;
            $monto = $visita['tarifa_aplicada'] ?? '0';

            $error = ReglasVisita::evaluarPago($visita, $existente, $monto);
            if ($error !== null) {
                throw new ReglaNegocioException($error, ReglasVisita::mensaje($error));
            }

            $consecutivo = $this->pagos->siguienteConsecutivo();
            $id = $this->pagos->create($idVisita, $monto, $medio, $usuario, $consecutivo, $referencia);
            $this->audit->log('PAGO_REGISTRADO', 'pagos', $id, 'EXITO', null, [
                'id_visita'   => $idVisita,
                'monto'       => $monto,
                'medio'       => $medio,
                'consecutivo' => $consecutivo,
            ], $usuario);
            $this->pdo->commit();
        } catch (ReglaNegocioException $e) {
            $this->pdo->rollBack();
            if ($e->codigo === 'PAGO_DUPLICADO') {
                $this->rechazo($idVisita, $usuario);
            }
            throw $e;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if (Database::isDuplicate($e)) {
                $this->rechazo($idVisita, $usuario);
                throw new ReglaNegocioException('PAGO_DUPLICADO', ReglasVisita::mensaje('PAGO_DUPLICADO'));
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->pagos->find($id);
    }

    /**
     * Caja confirma el pago de un cobro diario pendiente (la persona no tenía saldo suficiente).
     * El monto es el valor del cobro; un cobro tiene como máximo un pago válido.
     * @throws ReglaNegocioException
     */
    public function registrarCobro(int $idCobro, string $medio, int $usuario, ?string $referencia = null): array
    {
        if (!array_key_exists($medio, config('medios_pago'))) {
            throw new ReglaNegocioException('MEDIO_INVALIDO', 'Selecciona un medio de pago autorizado.');
        }
        $referencia = $referencia !== null && trim($referencia) !== '' ? mb_substr(trim($referencia), 0, 60) : null;
        $cobros = new CobroRepository($this->pdo);

        $this->pdo->beginTransaction();
        try {
            $cobro = $cobros->find($idCobro, true);
            if ($cobro === null) {
                throw new ReglaNegocioException('SIN_COBRO', 'El cobro no existe.');
            }
            if ($cobro['estado'] === 'PAGADO' || $this->pagos->validoDeCobro($idCobro, true) !== null) {
                throw new ReglaNegocioException('PAGO_DUPLICADO', 'Este cobro ya tiene un pago confirmado.');
            }
            if ($cobro['estado'] !== 'PENDIENTE') {
                throw new ReglaNegocioException('COBRO_ANULADO', 'El cobro está anulado: no hay nada que pagar.');
            }
            $consecutivo = $this->pagos->siguienteConsecutivo();
            $id = $this->pagos->createDeCobro($idCobro, $cobro['valor'], $medio, $usuario, $consecutivo, $referencia);
            $cobros->marcarPagado($idCobro);
            $this->audit->log('PAGO_REGISTRADO', 'pagos', $id, 'EXITO', null, [
                'id_cobro'    => $idCobro,
                'monto'       => $cobro['valor'],
                'medio'       => $medio,
                'consecutivo' => $consecutivo,
            ], $usuario);
            $this->pdo->commit();
        } catch (ReglaNegocioException $e) {
            $this->pdo->rollBack();
            if ($e->codigo === 'PAGO_DUPLICADO') {
                $this->audit->log('PAGO_RECHAZADO', 'cobros_diarios', $idCobro, 'DENEGADO', 'El cobro ya tiene un pago válido', [], $usuario);
            }
            throw $e;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if (Database::isDuplicate($e)) {
                $this->audit->log('PAGO_RECHAZADO', 'cobros_diarios', $idCobro, 'DENEGADO', 'El cobro ya tiene un pago válido', [], $usuario);
                throw new ReglaNegocioException('PAGO_DUPLICADO', 'Este cobro ya tiene un pago confirmado.');
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $this->pagos->find($id);
    }

    /**
     * Anulación o reverso autorizado: conserva la fila con motivo y auditoría (sección 7.4).
     * Si la visita sigue en curso, vuelve a quedar pendiente de pago. Si el pago era de un
     * cobro diario, el cobro vuelve a PENDIENTE; si fue un débito del saldo, el valor se reintegra.
     * @throws ReglaNegocioException
     */
    public function anular(int $idPago, int $usuario, string $motivo): void
    {
        $motivo = trim($motivo);
        $min = (int) config('motivo_min_longitud');
        if (mb_strlen($motivo) < $min) {
            throw new ReglaNegocioException('MOTIVO', "Describe el motivo de la anulación (mínimo {$min} caracteres).");
        }

        $this->pdo->beginTransaction();
        try {
            $pago = $this->pagos->find($idPago, true);
            if ($pago === null || $pago['estado'] !== 'PAGADO') {
                throw new ReglaNegocioException('PAGO_NO_VALIDO', 'Solo se puede anular un pago confirmado.');
            }
            $reintegro = null;
            if ($pago['id_cobro'] !== null) {
                $cobros = new CobroRepository($this->pdo);
                $cobro = $cobros->find((int) $pago['id_cobro'], true);
                if ($pago['medio_pago'] === 'SALDO') {
                    $personas = new EstudianteRepository($this->pdo);
                    $persona = $personas->find((int) $cobro['id_estudiante'], true);
                    $reintegro = number_format((ReglasVisita::centavos($persona['saldo']) + ReglasVisita::centavos($pago['monto'])) / 100, 2, '.', '');
                    (new SaldoRepository($this->pdo))->registrar([
                        'id_estudiante'  => (int) $persona['id_estudiante'],
                        'tipo'           => 'REINTEGRO',
                        'monto'          => $pago['monto'],
                        'saldo_anterior' => $persona['saldo'],
                        'saldo_nuevo'    => $reintegro,
                        'id_cobro'       => (int) $cobro['id_cobro'],
                        'id_pago'        => $idPago,
                        'registrado_por' => $usuario,
                    ]);
                    $personas->setSaldo((int) $persona['id_estudiante'], $reintegro);
                }
                $this->pagos->anular($idPago, $usuario, $motivo);
                $cobros->volverAPendiente((int) $pago['id_cobro']);
            } else {
                $this->pagos->anular($idPago, $usuario, $motivo);
            }
            $this->audit->log('PAGO_ANULADO', 'pagos', $idPago, 'EXITO', $motivo, [
                'consecutivo' => $pago['consecutivo'],
                'monto'       => $pago['monto'],
                'id_visita'   => $pago['id_visita'] !== null ? (int) $pago['id_visita'] : null,
                'id_cobro'    => $pago['id_cobro'] !== null ? (int) $pago['id_cobro'] : null,
                'saldo_reintegrado' => $reintegro,
            ], $usuario);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function rechazo(int $idVisita, int $usuario): void
    {
        $this->audit->log('PAGO_RECHAZADO', 'visitas', $idVisita, 'DENEGADO', 'La visita ya tiene un pago válido', [], $usuario);
    }
}
