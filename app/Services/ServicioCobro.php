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

/**
 * Cobro diario automático: se ejecuta ANTES de que la persona llegue a la salida.
 *
 *   Entrada ─(15 min)→ vence la tolerancia → ¿corresponde cobro? → ¿ya se cobró hoy?
 *          → genera el cobro del día → ¿saldo suficiente? → débito y pago registrado
 *                                                        → si no: valor pendiente en Caja + aviso al celular
 *
 * Lo disparan, en este orden de preferencia: el cron (scripts/cobros.php cada minuto), el latido de las
 * pantallas abiertas de Portería, Caja y Mi cuenta, y como último recurso la salida de esa misma visita
 * si el proceso automático no alcanzó a correr.
 */
final class ServicioCobro
{
    private VisitaRepository $visitas;
    private CobroRepository $cobros;
    private EstudianteRepository $personas;
    private PagoRepository $pagos;
    private SaldoRepository $saldos;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->visitas = new VisitaRepository($pdo);
        $this->cobros = new CobroRepository($pdo);
        $this->personas = new EstudianteRepository($pdo);
        $this->pagos = new PagoRepository($pdo);
        $this->saldos = new SaldoRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * Procesa todas las visitas con la tolerancia vencida. Es idempotente: se puede llamar
     * muchas veces y desde varios lugares a la vez sin generar cobros duplicados.
     * @return array{procesadas:int,cobros:int,debitados:int,pendientes:int,cubiertas:int,sin_cobro:int}
     */
    public function procesarVencidos(int $limite = 200): array
    {
        $r = ['procesadas' => 0, 'cobros' => 0, 'debitados' => 0, 'pendientes' => 0, 'cubiertas' => 0, 'sin_cobro' => 0];
        foreach ($this->visitas->vencidasSinLiquidar($limite) as $idVisita) {
            $resultado = $this->liquidar($idVisita);
            if ($resultado === null) {
                continue;
            }
            $r['procesadas']++;
            match ($resultado['liquidacion']) {
                'COBRADA', 'DIFERENCIA' => $r['cobros']++,
                'CUBIERTA'  => $r['cubiertas']++,
                default     => $r['sin_cobro']++,
            };
            if (in_array($resultado['liquidacion'], ['COBRADA', 'DIFERENCIA'], true)) {
                $resultado['estado_cobro'] === 'PAGADO' ? $r['debitados']++ : $r['pendientes']++;
            }
        }
        // Estadías de varios días: cada día nuevo que el vehículo sigue dentro genera su propio cobro.
        $r['dias_estadia'] = 0;
        foreach ($this->visitas->abiertasDeDiasAnteriores($limite) as $idVisita) {
            for ($i = 0; $i < 366; $i++) {
                $estado = $this->cobrarDiaDeEstadia($idVisita);
                if ($estado === null) {
                    break;
                }
                $r['dias_estadia']++;
                $r['cobros']++;
                $estado === 'PAGADO' ? $r['debitados']++ : $r['pendientes']++;
            }
        }
        return $r;
    }

    /**
     * Cobra el primer día de estadía aún sin cobro de una visita que sigue abierta desde un día anterior: mismo
     * valor que la tarifa de la visita, desde las 00:00 de ese día y sin nueva tolerancia. Se descuenta del saldo
     * si alcanza; si no, queda pendiente y se avisa, igual que el cobro del primer día.
     * Devuelve el estado del cobro creado, o null si no quedaba ningún día por cobrar.
     */
    public function cobrarDiaDeEstadia(int $idVisita): ?string
    {
        $avisar = null;
        $pagado = null;
        $this->pdo->beginTransaction();
        try {
            $previa = $this->visitas->find($idVisita);
            if ($previa === null) {
                $this->pdo->rollBack();
                return null;
            }
            $persona = $this->personas->find((int) $previa['id_estudiante'], true);
            $visita = $this->visitas->find($idVisita, true);
            if ($visita['estado'] !== 'EN_CURSO' || !in_array($visita['liquidacion'], ReglasCobro::LIQUIDACIONES_CON_COBRO, true)) {
                $this->pdo->rollBack();
                return null;
            }
            $dias = ReglasCobro::diasDeEstadiaPorCobrar($visita['entrada_en'], $this->ahora(), $this->cobros->fechasDeVisita($idVisita, true));
            if ($dias === []) {
                $this->pdo->rollBack();
                return null;
            }
            $fecha = $dias[0];
            $valor = (string) $visita['tarifa_aplicada'];
            $idCobro = $this->cobros->create($visita, $fecha, 'DIARIO', $valor);
            $this->audit->log('COBRO_ESTADIA', 'cobros_diarios', $idCobro, 'EXITO', 'El vehículo siguió dentro al empezar un día nuevo', [
                'id_visita' => $idVisita,
                'fecha'     => $fecha,
                'entrada'   => $visita['entrada_en'],
                'tipo'      => $visita['tipo_vehiculo'],
                'valor'     => $valor,
            ]);
            $estado = 'PENDIENTE';
            if (ReglasCobro::saldoSuficiente($persona['saldo'], $valor)) {
                $this->debitar($persona, $this->cobros->find($idCobro, true));
                $estado = 'PAGADO';
                $pagado = ['saldo' => number_format((float) $persona['saldo'] - (float) $valor, 2, '.', '')];
            } else {
                $avisar = ['saldo' => $persona['saldo']];
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // Ese día ya tiene un cobro diario de la persona (índice único): no se cobra dos veces.
            if (Database::isDuplicate($e)) {
                return null;
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $avisos = new ServicioNotificacion($this->pdo);
        if ($avisar !== null) {
            $avisos->pagoPendiente((int) $visita['id_estudiante'], $idCobro, $valor, (string) $avisar['saldo']);
        }
        if ($pagado !== null) {
            $avisos->pagoVerificado((int) $visita['id_estudiante'], $idCobro, $valor, $pagado['saldo']);
        }
        return $estado;
    }

    /**
     * Liquida una visita cuya tolerancia venció. Devuelve null si no había nada que hacer
     * (otra solicitud ya la procesó, la visita salió o la tolerancia aún no vence).
     * @return ?array{liquidacion:string,id_cobro:?int,estado_cobro:?string}
     */
    public function liquidar(int $idVisita): ?array
    {
        $avisar = null;
        $pagado = null;
        $this->pdo->beginTransaction();
        try {
            // Orden de bloqueo fijo (persona → visita → cobro) para que entradas, salidas, recargas
            // y este proceso no se crucen sobre la misma persona.
            $previa = $this->visitas->find($idVisita);
            if ($previa === null) {
                $this->pdo->rollBack();
                return null;
            }
            $persona = $this->personas->find((int) $previa['id_estudiante'], true);
            $visita = $this->visitas->find($idVisita, true);
            $ahora = $this->ahora();
            if ($visita['estado'] !== 'EN_CURSO' || $visita['liquidacion'] !== 'EN_TOLERANCIA'
                || ReglasCobro::enTolerancia($visita['tolerancia_hasta'], $ahora)) {
                $this->pdo->rollBack();
                return null;
            }

            $fecha = substr($visita['entrada_en'], 0, 10);
            $cobroDelDia = $this->cobros->delDia((int) $visita['id_estudiante'], $fecha, true);
            // Lo ya cobrado hoy (cobro diario + diferencias). El día vale la tarifa más alta usada.
            // Sin cobro diario vigente (por ejemplo, anulado) se cobra la tarifa completa.
            $totalDia = $cobroDelDia ? $this->cobros->totalDelDia((int) $visita['id_estudiante'], $fecha, true) : null;
            $decision = ReglasCobro::alVencer($visita, $totalDia);

            if ($decision === 'NO_APLICA') {
                $this->visitas->liquidar($idVisita, 'NO_APLICA', null);
                $this->pdo->commit();
                return ['liquidacion' => 'NO_APLICA', 'id_cobro' => null, 'estado_cobro' => null];
            }
            if ($decision === 'CUBIERTA') {
                $this->visitas->liquidar($idVisita, 'CUBIERTA', (int) $cobroDelDia['id_cobro']);
                $this->audit->log('VISITA_CUBIERTA', 'visitas', $idVisita, 'EXITO', 'Lo cobrado hoy cubre esta tarifa', [
                    'id_cobro'  => (int) $cobroDelDia['id_cobro'],
                    'total_dia' => $totalDia,
                ]);
                $this->pdo->commit();
                return ['liquidacion' => 'CUBIERTA', 'id_cobro' => (int) $cobroDelDia['id_cobro'], 'estado_cobro' => $cobroDelDia['estado']];
            }

            // COBRAR: primer cobro completo del día. DIFERENCIA: vehículo de tarifa mayor, solo lo que falta.
            $concepto = $decision === 'DIFERENCIA' ? 'DIFERENCIA' : 'DIARIO';
            $valor = $decision === 'DIFERENCIA'
                ? ReglasCobro::diferencia((string) $visita['tarifa_aplicada'], (string) $totalDia)
                : (string) $visita['tarifa_aplicada'];
            $idCobro = $this->cobros->create($visita, $fecha, $concepto, $valor);
            $liquidacion = $decision === 'DIFERENCIA' ? 'DIFERENCIA' : 'COBRADA';
            $this->visitas->liquidar($idVisita, $liquidacion, $idCobro);
            $this->audit->log($decision === 'DIFERENCIA' ? 'COBRO_DIFERENCIA' : 'COBRO_GENERADO', 'cobros_diarios', $idCobro, 'EXITO', null, [
                'id_visita' => $idVisita,
                'fecha'     => $fecha,
                'tipo'      => $visita['tipo_vehiculo'],
                'categoria' => $visita['categoria'],
                'tarifa'    => $visita['tarifa_aplicada'],
                'ya_cobrado_hoy' => $totalDia,
                'valor'     => $valor,
            ]);

            $estado = 'PENDIENTE';
            if (ReglasCobro::saldoSuficiente($persona['saldo'], $valor)) {
                $this->debitar($persona, $this->cobros->find($idCobro, true));
                $estado = 'PAGADO';
                $pagado = ['id_estudiante' => (int) $visita['id_estudiante'], 'id_cobro' => $idCobro, 'valor' => $valor,
                           'saldo' => number_format((float) $persona['saldo'] - (float) $valor, 2, '.', '')];
            } else {
                $avisar = ['id_estudiante' => (int) $visita['id_estudiante'], 'id_cobro' => $idCobro,
                           'valor' => $valor, 'saldo' => $persona['saldo'], 'diferencia' => $decision === 'DIFERENCIA'];
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // Otra solicitud generó el cobro del día al mismo tiempo: el índice único lo impidió.
            if (Database::isDuplicate($e)) {
                return null;
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($avisar !== null) {
            (new ServicioNotificacion($this->pdo))->pagoPendiente($avisar['id_estudiante'], $avisar['id_cobro'], $avisar['valor'], $avisar['saldo'], $avisar['diferencia']);
        }
        // Aviso de pago (no de salida): la persona sigue dentro hasta escanear su carné en la portería.
        if ($pagado !== null) {
            (new ServicioNotificacion($this->pdo))->pagoVerificado($pagado['id_estudiante'], $pagado['id_cobro'], $pagado['valor'], $pagado['saldo']);
        }
        return ['liquidacion' => $liquidacion, 'id_cobro' => $idCobro, 'estado_cobro' => $estado];
    }

    /**
     * Aplica el saldo a los cobros pendientes de la persona (después de una recarga).
     * Debe llamarse dentro de una transacción con la persona bloqueada.
     * @return list<int> cobros pagados
     */
    public function aplicarSaldoAPendientes(array $persona): array
    {
        $pagados = [];
        // Del más antiguo al más reciente. Un cobro que el saldo no alcanza se salta (queda pendiente)
        // y se intenta con los siguientes: antes el recorrido se detenía en el primero que no alcanzaba.
        foreach ($this->cobros->pendientesDePersona((int) $persona['id_estudiante'], true) as $cobro) {
            if (!ReglasCobro::saldoSuficiente($persona['saldo'], $cobro['valor'])) {
                continue;
            }
            $persona['saldo'] = $this->debitar($persona, $cobro);
            $pagados[] = (int) $cobro['id_cobro'];
        }
        return $pagados;
    }

    /**
     * Anula un cobro pendiente con motivo (por ejemplo, vehículo mal clasificado). Si ya se pagó,
     * primero se anula el pago (que reintegra el saldo si fue un débito).
     * @throws ReglaNegocioException
     */
    public function anular(int $idCobro, int $usuario, string $motivo): void
    {
        $motivo = trim($motivo);
        $min = (int) config('motivo_min_longitud');
        if (mb_strlen($motivo) < $min) {
            throw new ReglaNegocioException('MOTIVO', "Describe el motivo de la anulación (mínimo {$min} caracteres).");
        }
        $this->pdo->beginTransaction();
        try {
            $cobro = $this->cobros->find($idCobro, true);
            if ($cobro === null) {
                throw new ReglaNegocioException('SIN_COBRO', 'El cobro no existe.');
            }
            if ($cobro['estado'] === 'PAGADO') {
                throw new ReglaNegocioException('COBRO_PAGADO', 'El cobro está pagado. Anula primero el pago.');
            }
            if ($cobro['estado'] !== 'PENDIENTE') {
                throw new ReglaNegocioException('COBRO_ANULADO', 'El cobro ya está anulado.');
            }
            $this->cobros->anular($idCobro, $usuario, $motivo);
            $this->audit->log('COBRO_ANULADO', 'cobros_diarios', $idCobro, 'EXITO', $motivo, ['valor' => $cobro['valor']], $usuario);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Débito del saldo: pago con medio SALDO, movimiento en el libro y cobro PAGADO. Devuelve el saldo nuevo. */
    private function debitar(array $persona, array $cobro): string
    {
        $anterior = $persona['saldo'];
        $nuevo = number_format((ReglasVisita::centavos($anterior) - ReglasVisita::centavos($cobro['valor'])) / 100, 2, '.', '');
        $consecutivo = $this->pagos->siguienteConsecutivo();
        $idPago = $this->pagos->createDeCobro((int) $cobro['id_cobro'], $cobro['valor'], 'SALDO', null, $consecutivo, null);
        $this->saldos->registrar([
            'id_estudiante'  => (int) $persona['id_estudiante'],
            'tipo'           => 'DEBITO',
            'monto'          => $cobro['valor'],
            'saldo_anterior' => $anterior,
            'saldo_nuevo'    => $nuevo,
            'id_cobro'       => (int) $cobro['id_cobro'],
            'id_pago'        => $idPago,
        ]);
        $this->personas->setSaldo((int) $persona['id_estudiante'], $nuevo);
        $this->cobros->marcarPagado((int) $cobro['id_cobro']);
        $this->audit->log('PAGO_REGISTRADO', 'pagos', $idPago, 'EXITO', 'Débito automático del saldo', [
            'id_cobro'    => (int) $cobro['id_cobro'],
            'monto'       => $cobro['valor'],
            'medio'       => 'SALDO',
            'consecutivo' => $consecutivo,
            'saldo_nuevo' => $nuevo,
        ]);
        return $nuevo;
    }

    private function ahora(): string
    {
        return (string) $this->pdo->query('SELECT NOW()')->fetchColumn();
    }
}
