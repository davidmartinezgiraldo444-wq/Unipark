<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\EstudianteRepository;
use App\Repositories\RecargaLineaRepository;
use App\Repositories\SaldoRepository;
use App\Services\Pasarela\PasarelaException;
use App\Services\Pasarela\PasarelaPagos;
use App\Services\Pasarela\Pasarelas;
use App\Services\Pasarela\TransaccionPasarela;
use PDO;

/**
 * Recarga VOLUNTARIA del saldo UniPark desde la web.
 *
 *   Mi saldo → crear() recarga CREADA → la persona paga en la página del proveedor (PSE, tarjeta, Nequi…)
 *   → la pasarela envía un webhook → procesarWebhook(): firma válida + consulta al proveedor
 *   → aplicarTransaccion(): SOLO si la transacción está APROBADA y el monto coincide, acredita UNA vez
 *   (movimiento RECARGA con medio PASARELA) y paga los cobros pendientes con el saldo nuevo.
 *
 * El retorno del navegador nunca acredita: como mucho deja anotado el id de transacción para que la
 * conciliación del servidor (conciliar()) lo consulte al proveedor.
 * Nada de esto toca la portería: la salida sigue consultando solo el estado del cobro.
 */
final class ServicioRecargaLinea
{
    private RecargaLineaRepository $recargas;
    private EstudianteRepository $personas;
    private SaldoRepository $saldos;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->recargas = new RecargaLineaRepository($pdo);
        $this->personas = new EstudianteRepository($pdo);
        $this->saldos = new SaldoRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    public static function config(): array
    {
        return (array) config('recargas_en_linea');
    }

    // ============================================================ 1. Crear la recarga

    /**
     * Crea una recarga CREADA por el valor elegido. No mueve dinero.
     * @throws ReglaNegocioException
     */
    public function crear(int $idEstudiante, int $idUsuario, string $montoTexto): array
    {
        $pasarela = $this->pasarela();
        $cfg = self::config();
        $monto = preg_replace('/[\s.$]/', '', trim($montoTexto));
        if (!ctype_digit($monto) || (int) $monto < $cfg['minimo'] || (int) $monto > $cfg['maximo']) {
            throw new ReglaNegocioException('MONTO', sprintf('Elige un valor entre %s y %s, en pesos y sin centavos.', money($cfg['minimo']), money($cfg['maximo'])));
        }
        $persona = $this->personas->find($idEstudiante);
        if ($persona === null || (int) $persona['activo'] !== 1 || $persona['categoria'] === ReglasVisita::VISITANTE) {
            throw new ReglaNegocioException('PERSONA', 'Solo estudiantes, profesores y trabajadores activos pueden recargar saldo.');
        }
        if ($this->recargas->abiertasDePersona($idEstudiante) >= (int) $cfg['max_abiertas']) {
            throw new ReglaNegocioException('ABIERTAS', 'Ya tienes recargas sin terminar. Págalas o cancélalas antes de crear otra.');
        }

        $referencia = 'UPK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
        $id = $this->recargas->crear([
            'referencia'    => $referencia,
            'id_estudiante' => $idEstudiante,
            'id_usuario'    => $idUsuario,
            'proveedor'     => $pasarela->codigo(),
            'entorno'       => $pasarela->entorno(),
            'monto'         => $monto . '.00',
            'expira_min'    => max(5, (int) $cfg['expira_min']),
        ]);
        $this->audit->log('RECARGA_LINEA_CREADA', 'recargas_en_linea', $id, 'EXITO', null, [
            'referencia' => $referencia, 'monto' => $monto, 'proveedor' => $pasarela->codigo(), 'entorno' => $pasarela->entorno(),
        ], $idUsuario);
        return $this->recargas->find($id);
    }

    /** Formulario hacia la página de pago del proveedor (solo para recargas CREADA o PENDIENTE vigentes). */
    public function formularioPago(array $recarga, array $persona, string $urlRetorno): ?array
    {
        // Si ya se conoce una transacción (por el regreso del navegador o un webhook), el pago está en curso:
        // no se ofrece pagar de nuevo la misma recarga (evita el doble pago).
        if (!in_array($recarga['estado'], ['CREADA'], true) || strtotime($recarga['expira_en']) <= time()
            || $recarga['id_transaccion'] !== null || $recarga['id_transaccion_reportada'] !== null) {
            return null;
        }
        $pasarela = $this->pasarela();
        // B3: no se paga una recarga de otro proveedor ni de otro entorno (p. ej. una recarga sandbox tras pasar a producción).
        if ($pasarela->codigo() !== $recarga['proveedor'] || $pasarela->entorno() !== $recarga['entorno']) {
            return null;
        }
        return $pasarela->formularioPago(
            ['referencia' => $recarga['referencia'], 'monto' => $recarga['monto'], 'expira_en' => $recarga['expira_en']],
            ['correo' => $persona['correo'] ?? null, 'nombre' => trim($persona['nombres'] . ' ' . $persona['apellidos'])],
            $urlRetorno,
        );
    }

    /** La persona desiste de una recarga que todavía no pagó. @throws ReglaNegocioException */
    public function cancelar(int $idRecarga, int $idEstudiante, int $idUsuario): void
    {
        $this->pdo->beginTransaction();
        try {
            $r = $this->recargas->find($idRecarga, true);
            if ($r === null || (int) $r['id_estudiante'] !== $idEstudiante) {
                throw new ReglaNegocioException('NO_EXISTE', 'La recarga no existe.');
            }
            if ($r['estado'] !== 'CREADA' || $r['id_transaccion'] !== null || $r['id_transaccion_reportada'] !== null) {
                throw new ReglaNegocioException('NO_CANCELABLE', 'Esta recarga ya tiene un pago en curso o terminado: no se puede cancelar.');
            }
            $this->recargas->cambiarEstado($idRecarga, 'CANCELADA', null, null, 'Cancelada por la persona.');
            $this->audit->log('RECARGA_LINEA_CANCELADA', 'recargas_en_linea', $idRecarga, 'EXITO', null, [], $idUsuario);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * El navegador volvió del checkout con un id de transacción. NO cambia el estado ni acredita: solo lo
     * anota para que la conciliación lo consulte al proveedor y compruebe que corresponde a esta recarga.
     */
    public function reportarRetorno(int $idRecarga, int $idEstudiante, string $idTransaccion): bool
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $idTransaccion) !== 1) {
            return false;
        }
        $r = $this->recargas->find($idRecarga);
        if ($r === null || (int) $r['id_estudiante'] !== $idEstudiante || $r['estado'] === 'APROBADA'
            || $r['id_transaccion_reportada'] === $idTransaccion) {
            return false;
        }
        return $this->recargas->reportarRetorno($idRecarga, $idTransaccion) > 0;
    }

    /** Formato de las referencias que genera crear(): UPK-aammdd-<12 hexadecimales aleatorios>. */
    public const PATRON_REFERENCIA = '/^UPK-\d{6}-[0-9A-F]{12}$/';

    /**
     * Regreso SIN sesión desde la página de pago (/pago/retorno). La referencia es aleatoria (48 bits) y solo
     * la conoce quien inició la recarga. Igual que reportarRetorno(): solo anota el id para que la conciliación
     * lo verifique con el proveedor; nunca cambia el estado ni acredita.
     * @return ?int id de la recarga (para llevar a la persona a su detalle), o null si la referencia no existe
     */
    public function reportarRetornoPorReferencia(string $referencia, string $idTransaccion): ?int
    {
        if (preg_match(self::PATRON_REFERENCIA, $referencia) !== 1) {
            return null;
        }
        $r = $this->recargas->porReferencia($referencia);
        if ($r === null) {
            return null;
        }
        if ($idTransaccion !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $idTransaccion) === 1
            && $r['estado'] !== 'APROBADA' && $r['id_transaccion_reportada'] !== $idTransaccion) {
            $this->recargas->reportarRetorno((int) $r['id_recarga'], $idTransaccion);
        }
        return (int) $r['id_recarga'];
    }

    // ============================================================ 2. Webhook

    /**
     * Procesa una notificación de la pasarela. Devuelve el código HTTP que se responde al proveedor:
     * 200 cuando el evento quedó atendido (aunque no acredite), 401/400 si no es auténtico y 503 si falló
     * algo temporal (el proveedor reintenta).
     * @return array{http:int,resultado:string}
     */
    public function procesarWebhook(string $cuerpo, array $cabeceras): array
    {
        try {
            $pasarela = Pasarelas::activa();
        } catch (PasarelaException $e) {
            return ['http' => 503, 'resultado' => 'NO_CONFIGURADA'];
        }
        $huella = hash('sha256', $cuerpo);
        if ($this->recargas->notificacionProcesada($huella)) {
            return ['http' => 200, 'resultado' => 'DUPLICADA'];
        }

        try {
            $notificacion = $pasarela->leerNotificacion($cuerpo, $cabeceras);
        } catch (PasarelaException $e) {
            // Sin huella: si mañana el secreto se corrige, el reintento del proveedor se procesa.
            $this->recargas->registrarNotificacion([
                'proveedor' => $pasarela->codigo(), 'origen' => 'WEBHOOK', 'firma_valida' => false,
                'resultado' => $e->codigo, 'mensaje' => $e->getMessage(),
            ]);
            $this->audit->log('PASARELA_NOTIFICACION_RECHAZADA', 'notificaciones_pasarela', null, 'DENEGADO', $e->codigo, ['ip' => AuditService::$ip]);
            return ['http' => $e->codigo === 'CUERPO_INVALIDO' ? 400 : 401, 'resultado' => $e->codigo];
        }

        $tx = $notificacion->transaccion;
        $base = [
            'proveedor' => $pasarela->codigo(), 'origen' => 'WEBHOOK', 'huella' => $huella, 'tipo_evento' => $notificacion->tipoEvento,
            'firma_valida' => true, 'id_transaccion' => $tx?->id, 'referencia' => $tx?->referencia,
            'estado_reportado' => $tx?->estadoProveedor, 'monto_centavos' => $tx?->montoCentavos,
        ];
        if ($tx === null) {
            $this->recargas->registrarNotificacion($base + ['resultado' => 'IGNORADA', 'mensaje' => 'Evento que no es de una transacción.']);
            return ['http' => 200, 'resultado' => 'IGNORADA'];
        }

        // Defensa adicional: el estado que cuenta es el que responde el proveedor, no el del mensaje.
        if (self::config()['verificar_consulta'] ?? true) {
            try {
                $consultada = $pasarela->consultar($tx->id);
            } catch (PasarelaException $e) {
                $this->recargas->registrarNotificacion(['huella' => null, 'resultado' => 'ERROR_CONSULTA', 'mensaje' => $e->getMessage()] + $base);
                return ['http' => 503, 'resultado' => 'ERROR_CONSULTA'];
            }
            if ($consultada === null || $consultada->referencia !== $tx->referencia) {
                $resultado = $consultada === null ? 'NO_EXISTE' : 'REFERENCIA_DISTINTA';
                $this->recargas->registrarNotificacion($base + ['resultado' => $resultado, 'mensaje' => 'La consulta al proveedor no confirma el evento.']);
                $this->audit->log('PASARELA_NOTIFICACION_RECHAZADA', 'notificaciones_pasarela', null, 'DENEGADO', $resultado, ['transaccion' => $tx->id]);
                return ['http' => 200, 'resultado' => $resultado];
            }
            $tx = $consultada;
        }

        $r = $this->aplicarTransaccion($tx);
        $this->recargas->registrarNotificacion($base + [
            'resultado' => $r['resultado'], 'mensaje' => $r['mensaje'], 'id_recarga' => $r['id_recarga'],
            'estado_reportado' => $tx->estadoProveedor, 'monto_centavos' => $tx->montoCentavos,
        ]);
        return ['http' => 200, 'resultado' => $r['resultado']];
    }

    // ============================================================ 3. Aplicar una transacción verificada

    /**
     * Aplica a la recarga el estado de una transacción YA VERIFICADA por el servidor (webhook firmado y
     * consultado, o consulta de conciliación). Idempotente: se puede llamar muchas veces, desde varios
     * procesos a la vez, y la recarga se acredita una sola vez.
     * @return array{resultado:string,mensaje:string,id_recarga:?int,cobros_pagados:list<int>}
     */
    public function aplicarTransaccion(TransaccionPasarela $tx): array
    {
        $previa = $this->recargas->porReferencia($tx->referencia);
        if ($previa === null) {
            return self::resultado('SIN_RECARGA', 'No existe una recarga con esa referencia.', null);
        }
        $id = (int) $previa['id_recarga'];
        if ($previa['proveedor'] !== $tx->proveedor) {
            return self::resultado('PROVEEDOR_DISTINTO', 'La recarga pertenece a otro proveedor.', $id);
        }
        // B3: una recarga creada en sandbox nunca se acredita con una transacción de producción, ni al revés.
        if ($previa['entorno'] !== $tx->entorno) {
            $this->audit->log('RECARGA_LINEA_ENTORNO_DISTINTO', 'recargas_en_linea', $id, 'DENEGADO', null, [
                'recarga' => $previa['entorno'], 'transaccion' => $tx->entorno, 'id_transaccion' => $tx->id,
            ]);
            return self::resultado('ENTORNO_DISTINTO', 'La transacción es de otro entorno (sandbox/producción): no se acredita.', $id);
        }

        $aviso = null;
        $this->pdo->beginTransaction();
        try {
            // Mismo orden de bloqueo que cobros y recargas en Caja: persona → recarga.
            $persona = $this->personas->find((int) $previa['id_estudiante'], true);
            $recarga = $this->recargas->find($id, true);

            if ($recarga['estado'] === 'APROBADA') {
                $this->pdo->commit();
                if ($tx->estado === 'APROBADA' && $tx->id === $recarga['id_transaccion']) {
                    return self::resultado('DUPLICADA', 'La recarga ya estaba acreditada.', $id);
                }
                // Otra transacción aprobada con la misma referencia, o un reverso del proveedor después de
                // acreditar: no se toca el saldo automáticamente; Administración lo revisa.
                $this->audit->log('RECARGA_LINEA_REVISION', 'recargas_en_linea', $id, 'DENEGADO', 'Transacción posterior a la acreditación', [
                    'transaccion' => $tx->id, 'estado' => $tx->estadoProveedor,
                ]);
                return self::resultado($tx->estado === 'APROBADA' || $tx->estado === 'ANULADA' ? 'REQUIERE_REVISION' : 'SIN_CAMBIO',
                    'La recarga ya estaba acreditada con otra transacción.', $id);
            }

            if ($tx->moneda !== 'COP' || $tx->montoCentavos !== ReglasVisita::centavos($recarga['monto'])) {
                $this->pdo->commit();
                $this->audit->log('RECARGA_LINEA_MONTO_DISTINTO', 'recargas_en_linea', $id, 'DENEGADO', null, [
                    'esperado_centavos' => ReglasVisita::centavos($recarga['monto']), 'recibido_centavos' => $tx->montoCentavos, 'moneda' => $tx->moneda,
                ]);
                return self::resultado('MONTO_DISTINTO', 'El monto o la moneda no coinciden con la recarga: no se acredita.', $id);
            }

            switch ($tx->estado) {
                case 'APROBADA':
                    $r = $this->acreditar($persona, $recarga, $tx);
                    $aviso = ['APROBADA', $recarga['monto'], $r['saldo']];
                    $this->pdo->commit();
                    break;
                case 'PENDIENTE':
                case 'RECHAZADA':
                case 'ANULADA':
                case 'ERROR':
                    if ($recarga['estado'] === $tx->estado && $recarga['id_transaccion'] === $tx->id) {
                        $this->pdo->commit();
                        return self::resultado('SIN_CAMBIO', 'La recarga ya tenía ese estado.', $id);
                    }
                    $this->recargas->cambiarEstado($id, $tx->estado, $tx->id, $tx->medio, self::detalle($tx->estado));
                    $this->audit->log('RECARGA_LINEA_' . $tx->estado, 'recargas_en_linea', $id, $tx->estado === 'PENDIENTE' ? 'EXITO' : 'DENEGADO', null, [
                        'transaccion' => $tx->id, 'estado_proveedor' => $tx->estadoProveedor,
                    ]);
                    if ($tx->estado !== 'PENDIENTE') {
                        $aviso = [$tx->estado, $recarga['monto'], $persona['saldo']];
                    }
                    $this->pdo->commit();
                    $r = ['cobros_pagados' => []];
                    break;
                default:
                    $this->pdo->commit();
                    return self::resultado('ESTADO_DESCONOCIDO', 'El proveedor reportó un estado desconocido: ' . $tx->estadoProveedor, $id);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($aviso !== null) {
            (new ServicioNotificacion($this->pdo))->recarga((int) $previa['id_estudiante'], $aviso[0], (string) $aviso[1], (string) $aviso[2]);
        }
        return $tx->estado === 'APROBADA'
            ? self::resultado('ACREDITADA', 'Recarga acreditada al saldo.', $id, $r['cobros_pagados'])
            : self::resultado('ESTADO_ACTUALIZADO', self::detalle($tx->estado), $id);
    }

    /**
     * Acredita dentro de la transacción abierta: movimiento RECARGA (medio PASARELA), saldo nuevo,
     * recarga APROBADA (con su movimiento, que es UNIQUE) y pago de los cobros pendientes con ese saldo.
     * @return array{saldo:string,cobros_pagados:list<int>}
     */
    private function acreditar(array $persona, array $recarga, TransaccionPasarela $tx): array
    {
        $anterior = $persona['saldo'];
        $nuevo = number_format((ReglasVisita::centavos($anterior) + ReglasVisita::centavos($recarga['monto'])) / 100, 2, '.', '');
        $consecutivo = $this->saldos->siguienteConsecutivo();
        $idMovimiento = $this->saldos->registrar([
            'id_estudiante'  => (int) $persona['id_estudiante'],
            'tipo'           => 'RECARGA',
            'monto'          => $recarga['monto'],
            'saldo_anterior' => $anterior,
            'saldo_nuevo'    => $nuevo,
            'medio_pago'     => 'PASARELA',
            'referencia'     => mb_substr($tx->proveedor . ' ' . $tx->id, 0, 60),
            'consecutivo'    => $consecutivo,
            'registrado_por' => null,
        ]);
        $this->personas->setSaldo((int) $persona['id_estudiante'], $nuevo);
        if ($this->recargas->aprobar((int) $recarga['id_recarga'], $tx->id, $tx->medio, $idMovimiento) !== 1) {
            throw new \RuntimeException('La recarga cambió durante la acreditación');
        }
        $this->audit->log('SALDO_RECARGADO_EN_LINEA', 'movimientos_saldo', $idMovimiento, 'EXITO', null, [
            'id_recarga' => (int) $recarga['id_recarga'], 'referencia' => $recarga['referencia'], 'transaccion' => $tx->id,
            'proveedor' => $tx->proveedor, 'medio' => $tx->medio, 'monto' => $recarga['monto'], 'consecutivo' => $consecutivo, 'saldo_nuevo' => $nuevo,
        ]);

        // El saldo nuevo paga de inmediato los cobros pendientes (la misma regla que la recarga en Caja).
        $persona['saldo'] = $nuevo;
        $pagados = (new ServicioCobro($this->pdo))->aplicarSaldoAPendientes($persona);
        return ['saldo' => $this->personas->find((int) $persona['id_estudiante'])['saldo'], 'cobros_pagados' => $pagados];
    }

    // ============================================================ 4. Conciliación

    /**
     * Consulta al proveedor las recargas no aprobadas de las que se conoce una transacción (por un webhook
     * PENDING o por el retorno del navegador) y expira las que nadie pagó. Para el cron (scripts/conciliar-recargas.php).
     * @return array{consultadas:int,acreditadas:int,actualizadas:int,errores:int,expiradas:int}
     */
    public function conciliar(int $limite = 100): array
    {
        $r = ['consultadas' => 0, 'acreditadas' => 0, 'actualizadas' => 0, 'errores' => 0, 'expiradas' => $this->recargas->expirarVencidas()];
        $pasarela = $this->pasarela();
        foreach ($this->recargas->paraConciliar(2, $limite) as $recarga) {
            $this->recargas->marcarConsultada((int) $recarga['id_recarga']);
            $ids = array_values(array_unique(array_filter([$recarga['id_transaccion'], $recarga['id_transaccion_reportada']])));
            foreach ($ids as $idTx) {
                $r['consultadas']++;
                $base = ['proveedor' => $pasarela->codigo(), 'origen' => 'CONSULTA', 'id_transaccion' => $idTx,
                         'referencia' => $recarga['referencia'], 'id_recarga' => (int) $recarga['id_recarga'], 'firma_valida' => true];
                try {
                    $tx = $pasarela->consultar((string) $idTx);
                } catch (PasarelaException $e) {
                    $r['errores']++;
                    $this->recargas->registrarNotificacion($base + ['resultado' => 'ERROR_CONSULTA', 'mensaje' => $e->getMessage()]);
                    continue;
                }
                if ($tx === null || $tx->referencia !== $recarga['referencia']) {
                    // Un id reportado por el navegador que no es de esta recarga (error o intento de abuso).
                    $this->recargas->registrarNotificacion($base + ['resultado' => $tx === null ? 'NO_EXISTE' : 'REFERENCIA_DISTINTA',
                        'mensaje' => 'La transacción consultada no corresponde a esta recarga.']);
                    continue;
                }
                $a = $this->aplicarTransaccion($tx);
                $a['resultado'] === 'ACREDITADA' ? $r['acreditadas']++ : ($a['resultado'] === 'ESTADO_ACTUALIZADO' ? $r['actualizadas']++ : null);
                $this->recargas->registrarNotificacion($base + ['resultado' => $a['resultado'], 'mensaje' => $a['mensaje'],
                    'estado_reportado' => $tx->estadoProveedor, 'monto_centavos' => $tx->montoCentavos]);
                if ($a['resultado'] === 'ACREDITADA') {
                    break;
                }
            }
        }
        return $r;
    }

    // ============================================================ Ayudas

    /** @throws ReglaNegocioException */
    private function pasarela(): PasarelaPagos
    {
        try {
            return Pasarelas::activa();
        } catch (PasarelaException $e) {
            throw new ReglaNegocioException('NO_DISPONIBLE', 'Las recargas en línea no están disponibles en este momento. Puedes recargar en Caja.');
        }
    }

    public static function detalle(string $estado): string
    {
        return [
            'PENDIENTE' => 'El pago está en proceso en la pasarela.',
            'RECHAZADA' => 'El medio de pago rechazó la transacción. No se descontó saldo ni se acreditó nada.',
            'ANULADA'   => 'La transacción fue anulada por el medio de pago.',
            'ERROR'     => 'La pasarela reportó un error en la transacción. No se acreditó nada.',
        ][$estado] ?? '';
    }

    private static function resultado(string $resultado, string $mensaje, ?int $idRecarga, array $pagados = []): array
    {
        return ['resultado' => $resultado, 'mensaje' => $mensaje, 'id_recarga' => $idRecarga, 'cobros_pagados' => $pagados];
    }
}
