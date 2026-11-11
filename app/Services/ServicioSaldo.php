<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Validator;
use App\Repositories\EstudianteRepository;
use App\Repositories\SaldoRepository;
use PDO;

/** Recarga de saldo en Caja. Después de recargar se pagan solos los cobros pendientes que alcancen. */
final class ServicioSaldo
{
    private EstudianteRepository $personas;
    private SaldoRepository $saldos;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->personas = new EstudianteRepository($pdo);
        $this->saldos = new SaldoRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * @return array{id_movimiento:int,consecutivo:string,saldo:string,cobros_pagados:list<int>}
     * @throws ReglaNegocioException
     */
    public function recargar(int $idEstudiante, string $monto, string $medio, int $usuario, ?string $referencia = null): array
    {
        $monto = str_replace(',', '.', str_replace('.', '', trim($monto)));
        if (!Validator::esDinero($monto) || (float) $monto > (float) config('recarga_maxima')) {
            throw new ReglaNegocioException('MONTO', 'Escribe un valor de recarga mayor que cero y hasta ' . money(config('recarga_maxima')) . '.');
        }
        if (!array_key_exists($medio, config('medios_pago'))) {
            throw new ReglaNegocioException('MEDIO_INVALIDO', 'Selecciona un medio de pago autorizado.');
        }
        $referencia = $referencia !== null && trim($referencia) !== '' ? mb_substr(trim($referencia), 0, 60) : null;

        $this->pdo->beginTransaction();
        try {
            $persona = $this->personas->find($idEstudiante, true);
            if ($persona === null || (int) $persona['activo'] !== 1 || $persona['categoria'] === ReglasVisita::VISITANTE) {
                throw new ReglaNegocioException('PERSONA', 'Solo se recarga saldo a estudiantes, profesores y trabajadores activos.');
            }
            $anterior = $persona['saldo'];
            $nuevo = number_format((ReglasVisita::centavos($anterior) + ReglasVisita::centavos($monto)) / 100, 2, '.', '');
            $consecutivo = $this->saldos->siguienteConsecutivo();
            $id = $this->saldos->registrar([
                'id_estudiante'  => $idEstudiante,
                'tipo'           => 'RECARGA',
                'monto'          => $monto,
                'saldo_anterior' => $anterior,
                'saldo_nuevo'    => $nuevo,
                'medio_pago'     => $medio,
                'referencia'     => $referencia,
                'consecutivo'    => $consecutivo,
                'registrado_por' => $usuario,
            ]);
            $this->personas->setSaldo($idEstudiante, $nuevo);
            $this->audit->log('SALDO_RECARGADO', 'movimientos_saldo', $id, 'EXITO', null, [
                'id_estudiante' => $idEstudiante,
                'monto'         => $monto,
                'medio'         => $medio,
                'consecutivo'   => $consecutivo,
                'saldo_nuevo'   => $nuevo,
            ], $usuario);

            // El saldo nuevo paga de inmediato lo pendiente (paso 4-5 del flujo de cobro).
            $persona['saldo'] = $nuevo;
            $pagados = (new ServicioCobro($this->pdo))->aplicarSaldoAPendientes($persona);
            $saldoFinal = $this->personas->find($idEstudiante)['saldo'];
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return ['id_movimiento' => $id, 'consecutivo' => $consecutivo, 'saldo' => $saldoFinal, 'cobros_pagados' => $pagados];
    }

    /**
     * Reverso controlado de una recarga mal digitada: crea un movimiento REVERSO_RECARGA (nunca borra ni
     * modifica la recarga), una sola vez por recarga y solo si el saldo actual alcanza (si parte del saldo
     * ya pagó cobros, no se reversa: primero se anulan esos pagos, que reintegran el saldo).
     * @throws ReglaNegocioException
     */
    public function reversarRecarga(int $idMovimiento, int $usuario, string $motivo): array
    {
        $motivo = trim($motivo);
        $min = (int) config('motivo_min_longitud');
        if (mb_strlen($motivo) < $min) {
            throw new ReglaNegocioException('MOTIVO', "Describe el motivo del reverso (mínimo {$min} caracteres).");
        }
        $this->pdo->beginTransaction();
        try {
            $recarga = $this->saldos->recargaParaReverso($idMovimiento);
            if ($recarga === null) {
                throw new ReglaNegocioException('NO_EXISTE', 'La recarga no existe.');
            }
            if ($recarga['id_reverso'] !== null) {
                throw new ReglaNegocioException('YA_REVERSADA', 'Esta recarga ya fue reversada.');
            }
            $persona = $this->personas->find((int) $recarga['id_estudiante'], true);
            if (ReglasVisita::centavos($persona['saldo']) < ReglasVisita::centavos($recarga['monto'])) {
                throw new ReglaNegocioException('SALDO_INSUFICIENTE', 'El saldo actual (' . money($persona['saldo']) . ') es menor que la recarga: parte ya se usó. Anula primero los pagos con saldo correspondientes.');
            }
            $nuevo = number_format((ReglasVisita::centavos($persona['saldo']) - ReglasVisita::centavos($recarga['monto'])) / 100, 2, '.', '');
            $id = $this->saldos->registrar([
                'id_estudiante'        => (int) $persona['id_estudiante'],
                'tipo'                 => 'REVERSO_RECARGA',
                'monto'                => $recarga['monto'],
                'saldo_anterior'       => $persona['saldo'],
                'saldo_nuevo'          => $nuevo,
                'id_movimiento_origen' => $idMovimiento,
                'motivo'               => mb_substr($motivo, 0, 255),
                'registrado_por'       => $usuario,
            ]);
            $this->personas->setSaldo((int) $persona['id_estudiante'], $nuevo);
            $this->audit->log('SALDO_REVERSADO', 'movimientos_saldo', $id, 'EXITO', $motivo, [
                'recarga' => $idMovimiento, 'consecutivo' => $recarga['consecutivo'], 'monto' => $recarga['monto'], 'saldo_nuevo' => $nuevo,
            ], $usuario);
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            // Dos reversos simultáneos: el índice único reverso_unico deja pasar solo uno.
            if (\App\Helpers\Database::isDuplicate($e)) {
                throw new ReglaNegocioException('YA_REVERSADA', 'Esta recarga ya fue reversada.');
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        // Aviso a la persona: su saldo bajó porque la recarga se reembolsó o se registró por error.
        (new ServicioNotificacion($this->pdo))->recarga((int) $persona['id_estudiante'], 'REEMBOLSADA', (string) $recarga['monto'], $nuevo);
        return ['id_movimiento' => $id, 'saldo' => $nuevo, 'id_estudiante' => (int) $persona['id_estudiante']];
    }
}
