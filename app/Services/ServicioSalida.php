<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CobroRepository;
use App\Repositories\PagoRepository;
use App\Repositories\VisitaRepository;
use PDO;

/**
 * Salida en portería (sección 7.3). Es la operación más rápida del flujo: NO calcula tarifas,
 * NO genera cobros, NO debita saldo y NO inicia pagos. Solo consulta el estado que dejó el cobro
 * automático y autoriza o deniega. Si el cobro automático no corrió, deniega con una incidencia.
 */
final class ServicioSalida
{
    private VisitaRepository $visitas;
    private PagoRepository $pagos;
    private CobroRepository $cobros;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->visitas = new VisitaRepository($pdo);
        $this->pagos = new PagoRepository($pdo);
        $this->cobros = new CobroRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * Cierra la visita si no requiere pago o si su pago ya está confirmado (RF-12, RF-14).
     * Si se deniega no guarda la hora de salida, audita el rechazo y remite a Caja (RF-13).
     * @throws ReglaNegocioException
     */
    public function autorizar(int $idVisita, int $usuario): array
    {
        $this->pdo->beginTransaction();
        try {
            $r = $this->decidirEnTransaccion($idVisita, $usuario);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        if (!$r['autorizada']) {
            throw new ReglaNegocioException($r['codigo'], $r['mensaje'], ['id_visita' => $idVisita]);
        }
        return $this->visitas->find($idVisita);
    }

    /**
     * Decisión de salida dentro de una transacción abierta por quien llama (evento de acceso).
     * Solo CONSULTA: nunca calcula tarifas, genera cobros ni debita saldo.
     * @return array{autorizada:bool,codigo:?string,mensaje:string,liquidacion:?string}
     */
    public function decidirEnTransaccion(int $idVisita, int $usuario, ?int $idEvento = null): array
    {
        $visita = $this->visitas->find($idVisita, true);

        if ($visita !== null && $visita['liquidacion'] === 'LEGADO') {
            // Visitas del esquema anterior: un pago confirmado por visita.
            $pago = $this->pagos->validoDeVisita($idVisita, true);
            $error = ReglasVisita::evaluarSalida($visita, $pago);
            $detalle = $pago ? ['id_pago' => (int) $pago['id_pago'], 'consecutivo' => $pago['consecutivo']] : [];
        } else {
            $cobro = $visita && $visita['id_cobro'] !== null ? $this->cobros->find((int) $visita['id_cobro']) : null;
            $ahora = (string) $this->pdo->query('SELECT NOW()')->fetchColumn();
            $error = ReglasCobro::evaluarSalida($visita, $cobro, $ahora);
            $detalle = ['liquidacion' => $visita['liquidacion'] ?? null, 'id_cobro' => $cobro ? (int) $cobro['id_cobro'] : null];
            // Estadía de varios días: cada día dentro tiene su cobro; todos deben estar pagados (o anulados).
            if ($error === null && $visita !== null && in_array($visita['liquidacion'], ReglasCobro::LIQUIDACIONES_CON_COBRO, true)) {
                $pendientes = $this->cobros->pendientesDeVisita($idVisita, true);
                $faltan = ReglasCobro::diasDeEstadiaPorCobrar($visita['entrada_en'], $ahora, $this->cobros->fechasDeVisita($idVisita));
                if ($pendientes !== []) {
                    $error = 'PAGO_PENDIENTE';
                    $detalle['dias_pendientes'] = array_column($pendientes, 'fecha');
                } elseif ($faltan !== []) {
                    $error = 'VENCIDA_SIN_LIQUIDAR'; // El proceso automático aún no generó el cobro de esos días.
                    $detalle['dias_sin_cobro'] = $faltan;
                }
            }
        }
        $detalle['id_evento'] = $idEvento;

        if ($error === 'VENCIDA_SIN_LIQUIDAR') {
            // La tolerancia venció y el proceso automático aún no generó el cobro (cron detenido).
            // La salida NO calcula ni cobra: queda una incidencia que Caja o Portería resuelven aparte.
            $this->audit->log('SALIDA_INCIDENCIA', 'visitas', $idVisita, 'DENEGADO', 'Cobro automático sin procesar', $detalle, $usuario);
            return ['autorizada' => false, 'codigo' => 'COBRO_SIN_PROCESAR', 'mensaje' => ReglasVisita::mensaje('COBRO_SIN_PROCESAR'), 'liquidacion' => $visita['liquidacion']];
        }
        if ($error !== null) {
            $this->audit->log('SALIDA_DENEGADA', 'visitas', $visita ? $idVisita : null, 'DENEGADO', $error, $detalle, $usuario);
            return ['autorizada' => false, 'codigo' => $error, 'mensaje' => ReglasVisita::mensaje($error), 'liquidacion' => $visita['liquidacion'] ?? null];
        }

        $enTolerancia = ($visita['liquidacion'] ?? '') === 'EN_TOLERANCIA';
        $this->visitas->cerrar($idVisita, $usuario, $enTolerancia ? 'SALIDA_EN_TOLERANCIA' : null, $idEvento);
        $this->audit->log('SALIDA_AUTORIZADA', 'visitas', $idVisita, 'EXITO', $enTolerancia ? 'Salida dentro de la tolerancia: sin cobro' : null, $detalle, $usuario);
        $liq = $enTolerancia ? 'SALIDA_EN_TOLERANCIA' : $visita['liquidacion'];
        return [
            'autorizada'  => true,
            'codigo'      => null,
            'mensaje'     => match ($liq) {
                'SALIDA_EN_TOLERANCIA' => 'Salida autorizada dentro de la tolerancia: sin cobro.',
                'NO_APLICA'            => 'Salida autorizada. Sin cobro.',
                default                => 'Salida autorizada. Pago verificado.',
            },
            'liquidacion' => $liq,
        ];
    }
}
