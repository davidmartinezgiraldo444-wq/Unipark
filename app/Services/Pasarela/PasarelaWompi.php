<?php
declare(strict_types=1);

namespace App\Services\Pasarela;

use App\Helpers\ClienteHttp;

/**
 * Wompi (Colombia) con Web Checkout: la persona paga en checkout.wompi.co con PSE, tarjeta, Nequi,
 * Bancolombia u otro medio habilitado en la cuenta del comercio. UniPark no ve ni guarda datos del medio.
 *
 * Basado en la documentación oficial (consultada el 08/11/2026):
 *  - Web Checkout: GET https://checkout.wompi.co/p/ con public-key, currency, amount-in-cents, reference,
 *    signature:integrity, redirect-url y expiration-time.
 *  - Firma de integridad: SHA-256 de <referencia><monto en centavos><moneda>[<fecha de expiración>]<secreto de integridad>.
 *  - Eventos: POST JSON {event, data.transaction, environment, signature{properties, checksum}, timestamp};
 *    checksum = SHA-256 de los valores de signature.properties + timestamp + secreto de eventos
 *    (también en la cabecera X-Event-Checksum). Se responde HTTP 200.
 *  - Consulta: GET {api}/transactions/{id} con «Authorization: Bearer <llave privada>».
 *  - Estados: PENDING (inicial); finales APPROVED, DECLINED, VOIDED, ERROR.
 * Pendiente de verificar con una cuenta sandbox real (ver docs/recargas-en-linea.md).
 */
final class PasarelaWompi implements PasarelaPagos
{
    private const ESTADOS = [
        'PENDING'  => 'PENDIENTE',
        'APPROVED' => 'APROBADA',
        'DECLINED' => 'RECHAZADA',
        'VOIDED'   => 'ANULADA',
        'ERROR'    => 'ERROR',
    ];

    /** Prefijos de cada llave por entorno: impiden usar llaves de producción en sandbox (y al revés). */
    private const PREFIJOS = [
        'sandbox'    => ['llave_publica' => 'pub_test_', 'llave_privada' => 'prv_test_', 'secreto_eventos' => 'test_events_', 'secreto_integridad' => 'test_integrity_'],
        'produccion' => ['llave_publica' => 'pub_prod_', 'llave_privada' => 'prv_prod_', 'secreto_eventos' => 'prod_events_', 'secreto_integridad' => 'prod_integrity_'],
    ];

    private string $entorno;

    /**
     * @param array{entorno:string,produccion_autorizada:bool,llave_publica:string,llave_privada:string,
     *              secreto_eventos:string,secreto_integridad:string,url_checkout:string,url_api:array<string,string>} $config
     * @throws PasarelaException CONFIGURACION
     */
    public function __construct(private array $config, private ClienteHttp $http)
    {
        $this->entorno = $config['entorno'] ?? '';
        if (!isset(self::PREFIJOS[$this->entorno])) {
            throw new PasarelaException('CONFIGURACION', 'WOMPI_ENTORNO debe ser «sandbox» o «produccion».');
        }
        if ($this->entorno === 'produccion' && empty($config['produccion_autorizada'])) {
            throw new PasarelaException('CONFIGURACION', 'Los pagos reales no están autorizados: falta WOMPI_PRODUCCION_AUTORIZADA=1.');
        }
        foreach (self::PREFIJOS[$this->entorno] as $clave => $prefijo) {
            $valor = (string) ($config[$clave] ?? '');
            if ($valor === '' || !str_starts_with($valor, $prefijo)) {
                throw new PasarelaException('CONFIGURACION', sprintf('Falta %s o no corresponde al entorno %s (debe empezar por %s).', self::variable($clave), $this->entorno, $prefijo));
            }
        }
    }

    public function codigo(): string
    {
        return 'WOMPI';
    }

    public function entorno(): string
    {
        return $this->entorno === 'produccion' ? 'PRODUCCION' : 'SANDBOX';
    }

    public function formularioPago(array $recarga, array $pagador, string $urlRetorno): array
    {
        $centavos = self::centavos($recarga['monto']);
        // Wompi pide la expiración en ISO 8601 UTC (ejemplo de la documentación: 2023-06-09T20:28:50.000Z).
        $expira = (new \DateTimeImmutable($recarga['expira_en']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
        $campos = [
            'public-key'          => $this->config['llave_publica'],
            'currency'            => 'COP',
            'amount-in-cents'     => (string) $centavos,
            'reference'           => $recarga['referencia'],
            'signature:integrity' => self::firmaIntegridad($recarga['referencia'], $centavos, 'COP', $expira, $this->config['secreto_integridad']),
            'redirect-url'        => $urlRetorno,
            'expiration-time'     => $expira,
        ];
        if (!empty($pagador['correo']) && filter_var($pagador['correo'], FILTER_VALIDATE_EMAIL)) {
            $campos['customer-data:email'] = $pagador['correo'];
        }
        if (($pagador['nombre'] ?? '') !== '') {
            $campos['customer-data:full-name'] = mb_substr($pagador['nombre'], 0, 100);
        }
        return ['url' => $this->config['url_checkout'], 'metodo' => 'GET', 'campos' => $campos];
    }

    public function leerNotificacion(string $cuerpo, array $cabeceras): NotificacionPasarela
    {
        $evento = json_decode($cuerpo, true);
        if (!is_array($evento) || !is_string($evento['event'] ?? null) || !is_array($evento['data'] ?? null)
            || !is_array($evento['signature']['properties'] ?? null) || !is_string($evento['signature']['checksum'] ?? null)
            || !isset($evento['timestamp']) || !is_scalar($evento['timestamp'])) {
            throw new PasarelaException('CUERPO_INVALIDO', 'La notificación no tiene la estructura de un evento de Wompi.');
        }

        $propiedades = $evento['signature']['properties'];
        // Solo se confía en un evento que firma, como mínimo, el id y el estado de la transacción.
        if ($evento['event'] === 'transaction.updated'
            && (!in_array('transaction.id', $propiedades, true) || !in_array('transaction.status', $propiedades, true))) {
            throw new PasarelaException('FIRMA_INVALIDA', 'La firma del evento no cubre el id y el estado de la transacción.');
        }
        $concatenado = '';
        foreach ($propiedades as $ruta) {
            $valor = is_string($ruta) ? self::valorEnRuta($evento['data'], $ruta) : null;
            if ($valor === null) {
                throw new PasarelaException('FIRMA_INVALIDA', 'La firma referencia una propiedad que no está en el evento.');
            }
            $concatenado .= $valor;
        }
        $esperado = hash('sha256', $concatenado . $evento['timestamp'] . $this->config['secreto_eventos']);
        $recibido = strtolower($evento['signature']['checksum']);
        $cabecera = strtolower((string) ($cabeceras['x-event-checksum'] ?? $recibido));
        if (!hash_equals($esperado, $recibido) || !hash_equals($esperado, $cabecera)) {
            throw new PasarelaException('FIRMA_INVALIDA', 'La firma del evento no es válida.');
        }

        $entornoEvento = (string) ($evento['environment'] ?? '');
        if ($entornoEvento !== ($this->entorno === 'produccion' ? 'prod' : 'test')) {
            throw new PasarelaException('ENTORNO_DISTINTO', 'El evento corresponde a otro entorno de Wompi (' . mb_substr($entornoEvento, 0, 10) . ').');
        }

        $transaccion = null;
        if ($evento['event'] === 'transaction.updated') {
            if (!is_array($evento['data']['transaction'] ?? null)) {
                throw new PasarelaException('CUERPO_INVALIDO', 'El evento no incluye la transacción.');
            }
            $transaccion = $this->transaccion($evento['data']['transaction']);
        }
        return new NotificacionPasarela($evento['event'], $transaccion, hash('sha256', $cuerpo));
    }

    public function consultar(string $idTransaccion): ?TransaccionPasarela
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $idTransaccion) !== 1) {
            throw new PasarelaException('ID_INVALIDO', 'El identificador de la transacción no es válido.');
        }
        $url = $this->config['url_api'][$this->entorno] . '/transactions/' . rawurlencode($idTransaccion);
        $r = $this->http->get($url, ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->config['llave_privada']]);
        if ($r['estado'] === 404) {
            return null;
        }
        if ($r['estado'] !== 200) {
            // Nunca se registra el cuerpo completo ni la llave: solo el código y el error de red.
            throw new PasarelaException('CONSULTA_FALLIDA', 'Wompi no respondió la consulta (HTTP ' . $r['estado'] . ($r['error'] ? ', ' . mb_substr($r['error'], 0, 80) : '') . ').');
        }
        $json = json_decode($r['cuerpo'], true);
        $datos = is_array($json['data'] ?? null) ? $json['data'] : $json;
        if (!is_array($datos) || !isset($datos['id'], $datos['status'], $datos['reference'], $datos['amount_in_cents'])) {
            throw new PasarelaException('CONSULTA_FALLIDA', 'La respuesta de Wompi no tiene los datos de la transacción.');
        }
        return $this->transaccion($datos);
    }

    // ------------------------------------------------------------------ Fórmulas de la documentación

    /** SHA-256 de <referencia><monto en centavos><moneda>[<expiración>]<secreto de integridad>. */
    public static function firmaIntegridad(string $referencia, int $centavos, string $moneda, ?string $expiracion, string $secreto): string
    {
        return hash('sha256', $referencia . $centavos . $moneda . ($expiracion ?? '') . $secreto);
    }

    /** Pesos (texto con hasta dos decimales) a centavos enteros, sin coma flotante. */
    public static function centavos(string $monto): int
    {
        [$entero, $decimal] = array_pad(explode('.', trim($monto), 2), 2, '0');
        return (int) $entero * 100 + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }

    private function transaccion(array $t): TransaccionPasarela
    {
        $estadoProveedor = strtoupper((string) ($t['status'] ?? ''));
        return new TransaccionPasarela(
            'WOMPI',
            mb_substr((string) ($t['id'] ?? ''), 0, 64),
            mb_substr((string) ($t['reference'] ?? ''), 0, 80),
            self::ESTADOS[$estadoProveedor] ?? 'DESCONOCIDO',
            mb_substr($estadoProveedor, 0, 20),
            (int) ($t['amount_in_cents'] ?? 0),
            strtoupper((string) ($t['currency'] ?? '')),
            isset($t['payment_method_type']) ? mb_substr((string) $t['payment_method_type'], 0, 30) : null,
            $this->entorno(),
        );
    }

    /** «transaction.id» → $data['transaction']['id'] como texto; null si no existe o no es escalar. */
    private static function valorEnRuta(array $data, string $ruta): ?string
    {
        $actual = $data;
        foreach (explode('.', $ruta) as $parte) {
            if (!is_array($actual) || !array_key_exists($parte, $actual)) {
                return null;
            }
            $actual = $actual[$parte];
        }
        if (is_bool($actual)) {
            return $actual ? 'true' : 'false';
        }
        return is_scalar($actual) ? (string) $actual : null;
    }

    private static function variable(string $clave): string
    {
        return [
            'llave_publica' => 'WOMPI_LLAVE_PUBLICA', 'llave_privada' => 'WOMPI_LLAVE_PRIVADA',
            'secreto_eventos' => 'WOMPI_SECRETO_EVENTOS', 'secreto_integridad' => 'WOMPI_SECRETO_INTEGRIDAD',
        ][$clave];
    }
}
