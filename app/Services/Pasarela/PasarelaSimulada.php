<?php
declare(strict_types=1);

namespace App\Services\Pasarela;

/**
 * SIMULADOR de pasarela, SOLO para la copia de demostración. No es Wompi y no mueve dinero real.
 *
 * Imita a propósito el contrato de Wompi (los mismos nombres de campos del checkout, la misma firma de integridad
 * y un evento transaction.updated firmado igual) para que UniPark recorra el MISMO camino del servidor que con la
 * pasarela real: firma del pago → página de pago → notificación firmada → consulta de la transacción →
 * ServicioRecargaLinea::aplicarTransaccion(). Lo único simulado es la página de pago y el proveedor.
 *
 * Las «transacciones» se guardan como archivos JSON en storage/simulador/<base>/ (sin tablas nuevas).
 * Pasarelas::crear() se niega a usarlo en producción o sobre la base protegida (unipark_db).
 */
final class PasarelaSimulada implements PasarelaPagos
{
    public const ESTADOS = ['APPROVED' => 'APROBADA', 'DECLINED' => 'RECHAZADA', 'PENDING' => 'PENDIENTE', 'ERROR' => 'ERROR', 'VOIDED' => 'ANULADA'];

    /**
     * @param array{secreto_integridad:string,secreto_eventos:string,carpeta:string} $config
     * @throws PasarelaException CONFIGURACION
     */
    public function __construct(private array $config, private string $urlPagina)
    {
        foreach (['secreto_integridad', 'secreto_eventos'] as $clave) {
            if (strlen((string) ($config[$clave] ?? '')) < 32) {
                throw new PasarelaException('CONFIGURACION', 'El simulador necesita secretos de prueba de 32 caracteres o más (scripts/demo.php los genera).');
            }
        }
    }

    public function codigo(): string
    {
        return 'SIMULADO';
    }

    public function entorno(): string
    {
        return 'SANDBOX';
    }

    public function formularioPago(array $recarga, array $pagador, string $urlRetorno): array
    {
        $centavos = PasarelaWompi::centavos($recarga['monto']);
        $expira = (new \DateTimeImmutable($recarga['expira_en']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
        return ['url' => $this->urlPagina, 'metodo' => 'GET', 'campos' => [
            'currency'            => 'COP',
            'amount-in-cents'     => (string) $centavos,
            'reference'           => $recarga['referencia'],
            'signature:integrity' => $this->firmaPago($recarga['referencia'], $centavos, $expira),
            'redirect-url'        => $urlRetorno,
            'expiration-time'     => $expira,
        ]];
    }

    /** Firma de integridad del pago, con la misma fórmula que Wompi y el secreto del simulador. */
    public function firmaPago(string $referencia, int $centavos, string $expiracion): string
    {
        return PasarelaWompi::firmaIntegridad($referencia, $centavos, 'COP', $expiracion, $this->config['secreto_integridad']);
    }

    /** Verifica que los campos que llegan a la página de pago no se alteraron (monto o referencia). */
    public function pagoAutentico(string $referencia, string $centavos, string $expiracion, string $firma): bool
    {
        return ctype_digit($centavos) && hash_equals($this->firmaPago($referencia, (int) $centavos, $expiracion), strtolower($firma));
    }

    // ------------------------------------------------------------------ Transacciones simuladas

    /** Crea una transacción con el resultado que eligió quien hace la demostración. */
    public function crearTransaccion(string $referencia, int $centavos, string $estadoProveedor): string
    {
        if (!isset(self::ESTADOS[$estadoProveedor])) {
            throw new PasarelaException('ESTADO', 'Estado simulado no válido.');
        }
        $id = 'SIM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
        $this->guardar(['id' => $id, 'reference' => mb_substr($referencia, 0, 40), 'status' => $estadoProveedor, 'amount_in_cents' => $centavos,
            'currency' => 'COP', 'payment_method_type' => 'SIMULADO', 'created_at' => date('c')]);
        return $id;
    }

    /** Cambia el estado de una transacción PENDIENTE (por ejemplo, el PSE que se aprueba más tarde). */
    public function resolverPendiente(string $id, string $estadoProveedor): void
    {
        $t = $this->leer($id);
        if ($t === null || $t['status'] !== 'PENDING' || !in_array($estadoProveedor, ['APPROVED', 'DECLINED'], true)) {
            throw new PasarelaException('ESTADO', 'Solo una transacción pendiente se puede aprobar o rechazar.');
        }
        $t['status'] = $estadoProveedor;
        $this->guardar($t);
    }

    /**
     * Reembolso hecho por el proveedor: una transacción APROBADA pasa a VOIDED (anulada), como cuando la pasarela
     * anula un pago y devuelve el dinero. UniPark no descuenta el saldo solo: lo marca para revisión de Administración.
     */
    public function reembolsar(string $id): void
    {
        $t = $this->leer($id);
        if ($t === null || $t['status'] !== 'APPROVED') {
            throw new PasarelaException('ESTADO', 'Solo una transacción aprobada se puede reembolsar.');
        }
        $t['status'] = 'VOIDED';
        $this->guardar($t);
    }

    /** @return list<array> transacciones simuladas, las más recientes primero */
    public function transacciones(int $limite = 20): array
    {
        $archivos = glob($this->carpeta() . '/SIM-*.json') ?: [];
        usort($archivos, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return array_values(array_filter(array_map(fn ($f) => $this->leer(basename($f, '.json')), array_slice($archivos, 0, $limite))));
    }

    /**
     * Evento transaction.updated firmado como Wompi (checksum SHA-256 de las propiedades + timestamp + secreto).
     * @return array{0:string,1:array<string,string>} cuerpo JSON y cabeceras
     */
    public function evento(string $id): array
    {
        $t = $this->leer($id) ?? throw new PasarelaException('NO_EXISTE', 'La transacción simulada no existe.');
        $timestamp = time();
        $checksum = hash('sha256', $t['id'] . $t['status'] . $t['amount_in_cents'] . $timestamp . $this->config['secreto_eventos']);
        $cuerpo = json_encode([
            'event'       => 'transaction.updated',
            'data'        => ['transaction' => $t],
            'environment' => 'test',
            'signature'   => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], 'checksum' => $checksum],
            'timestamp'   => $timestamp,
            'sent_at'     => gmdate('Y-m-d\TH:i:s.000\Z', $timestamp),
        ], JSON_UNESCAPED_SLASHES);
        return [$cuerpo, ['x-event-checksum' => $checksum]];
    }

    // ------------------------------------------------------------------ Contrato PasarelaPagos

    public function leerNotificacion(string $cuerpo, array $cabeceras): NotificacionPasarela
    {
        $e = json_decode($cuerpo, true);
        if (!is_array($e) || ($e['event'] ?? null) !== 'transaction.updated' || !is_array($e['data']['transaction'] ?? null)
            || !is_scalar($e['timestamp'] ?? null) || !is_string($e['signature']['checksum'] ?? null)) {
            throw new PasarelaException('CUERPO_INVALIDO', 'La notificación simulada no tiene la estructura esperada.');
        }
        $t = $e['data']['transaction'];
        $esperado = hash('sha256', ($t['id'] ?? '') . ($t['status'] ?? '') . ($t['amount_in_cents'] ?? '') . $e['timestamp'] . $this->config['secreto_eventos']);
        if (!hash_equals($esperado, strtolower($e['signature']['checksum'])) || !hash_equals($esperado, strtolower((string) ($cabeceras['x-event-checksum'] ?? '')))) {
            throw new PasarelaException('FIRMA_INVALIDA', 'La firma de la notificación simulada no es válida.');
        }
        if (($e['environment'] ?? '') !== 'test') {
            throw new PasarelaException('ENTORNO_DISTINTO', 'El simulador solo emite eventos de pruebas.');
        }
        return new NotificacionPasarela('transaction.updated', $this->transaccion($t), hash('sha256', $cuerpo));
    }

    public function consultar(string $idTransaccion): ?TransaccionPasarela
    {
        if (preg_match('/^SIM-\d{6}-[0-9A-F]{10}$/', $idTransaccion) !== 1) {
            return null;
        }
        $t = $this->leer($idTransaccion);
        return $t === null ? null : $this->transaccion($t);
    }

    // ------------------------------------------------------------------ Archivos

    private function transaccion(array $t): TransaccionPasarela
    {
        return new TransaccionPasarela('SIMULADO', (string) $t['id'], (string) $t['reference'],
            self::ESTADOS[$t['status']] ?? 'DESCONOCIDO', (string) $t['status'], (int) $t['amount_in_cents'],
            (string) $t['currency'], 'SIMULADO', 'SANDBOX');
    }

    private function carpeta(): string
    {
        $dir = $this->config['carpeta'];
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    private function leer(string $id): ?array
    {
        if (preg_match('/^SIM-\d{6}-[0-9A-F]{10}$/', $id) !== 1 || !is_file($this->carpeta() . '/' . $id . '.json')) {
            return null;
        }
        $t = json_decode((string) file_get_contents($this->carpeta() . '/' . $id . '.json'), true);
        return is_array($t) ? $t : null;
    }

    private function guardar(array $t): void
    {
        file_put_contents($this->carpeta() . '/' . $t['id'] . '.json', json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
