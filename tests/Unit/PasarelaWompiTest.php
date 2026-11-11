<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Pasarela\PasarelaException;
use App\Services\Pasarela\PasarelaWompi;
use Tests\Support\ClienteHttpFalso;
use Tests\Support\TestCase;

/**
 * Integración con Wompi sin salir a internet: firmas, verificación de eventos, consulta y configuración.
 * Las llaves son ficticias con los prefijos de sandbox; nunca se usan credenciales reales.
 */
final class PasarelaWompiTest extends TestCase
{
    public const CONFIG = [
        'entorno' => 'sandbox', 'produccion_autorizada' => false,
        'llave_publica' => 'pub_test_FICTICIA', 'llave_privada' => 'prv_test_FICTICIA',
        'secreto_eventos' => 'test_events_FICTICIO', 'secreto_integridad' => 'test_integrity_FICTICIO',
        'url_checkout' => 'https://checkout.wompi.co/p/',
        'url_api' => ['sandbox' => 'https://sandbox.wompi.co/v1', 'produccion' => 'https://production.wompi.co/v1'],
    ];

    /** Evento transaction.updated firmado como lo documenta Wompi. */
    public static function evento(string $id, string $referencia, string $estado, int $centavos, int $timestamp = 1700000000,
                                  string $secreto = 'test_events_FICTICIO', string $entorno = 'test'): array
    {
        $props = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];
        $checksum = hash('sha256', $id . $estado . $centavos . $timestamp . $secreto);
        $cuerpo = json_encode([
            'event' => 'transaction.updated',
            'data' => ['transaction' => ['id' => $id, 'amount_in_cents' => $centavos, 'reference' => $referencia,
                'customer_email' => 'prueba@correo.test', 'currency' => 'COP', 'payment_method_type' => 'PSE', 'status' => $estado]],
            'environment' => $entorno,
            'signature' => ['properties' => $props, 'checksum' => strtoupper($checksum)],
            'timestamp' => $timestamp,
            'sent_at' => '2026-11-08T15:00:00.000Z',
        ]);
        return [$cuerpo, ['X-Event-Checksum' => strtoupper($checksum)]];
    }

    private function wompi(?ClienteHttpFalso $http = null, array $cambios = []): PasarelaWompi
    {
        return new PasarelaWompi($cambios + self::CONFIG, $http ?? new ClienteHttpFalso());
    }

    public function testLaFirmaDeIntegridadCoincideConElEjemploOficial(): void
    {
        // docs.wompi.co/docs/colombia/widget-checkout-web/ (consultado el 08/11/2026)
        $this->assertSame('37c8407747e595535433ef8f6a811d853cd943046624a0ec04662b17bbf33bf5',
            PasarelaWompi::firmaIntegridad('sk8-438k4-xmxm392-sn2m', 2490000, 'COP', null, 'prod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6'));
    }

    public function testElFormularioLlevaMontoEnCentavosYFirmaConExpiracion(): void
    {
        $f = $this->wompi()->formularioPago(['referencia' => 'UPK-1', 'monto' => '20000.00', 'expira_en' => '2026-11-08 10:00:00'],
            ['correo' => 'a@b.test', 'nombre' => 'Ana Prueba'], 'https://unipark.test/mi-saldo/recargas/1');
        $this->assertSame('https://checkout.wompi.co/p/', $f['url']);
        $this->assertSame('2000000', $f['campos']['amount-in-cents'], '$20.000 = 2.000.000 centavos');
        $this->assertSame('2026-11-08T15:00:00.000Z', $f['campos']['expiration-time'], 'Hora de Bogotá convertida a UTC');
        $this->assertSame(hash('sha256', 'UPK-12000000COP2026-11-08T15:00:00.000Ztest_integrity_FICTICIO'), $f['campos']['signature:integrity']);
        $this->assertSame('pub_test_FICTICIA', $f['campos']['public-key']);
        foreach ($f['campos'] as $valor) {
            $this->assertNotContains('prv_test', $valor, 'La llave privada nunca va al navegador');
            $this->assertNotContains('test_integrity', $valor, 'El secreto de integridad nunca va al navegador');
        }
    }

    public function testUnEventoConFirmaValidaEntregaLaTransaccion(): void
    {
        [$cuerpo, $cab] = self::evento('11-1-1', 'UPK-1', 'APPROVED', 2000000);
        $n = $this->wompi()->leerNotificacion($cuerpo, array_change_key_case($cab, CASE_LOWER));
        $this->assertSame('APROBADA', $n->transaccion->estado);
        $this->assertSame(2000000, $n->transaccion->montoCentavos);
        $this->assertSame('UPK-1', $n->transaccion->referencia);
        $this->assertSame(hash('sha256', $cuerpo), $n->huella);
    }

    public function testSeRechazanEventosAlteradosOFalsos(): void
    {
        $w = $this->wompi();
        [$cuerpo, $cab] = self::evento('11-1-1', 'UPK-1', 'APPROVED', 2000000, 1700000000, 'test_events_OTRO_SECRETO');
        $this->assertSame('FIRMA_INVALIDA', $this->falla(fn () => $w->leerNotificacion($cuerpo, array_change_key_case($cab, CASE_LOWER))), 'Firma con otro secreto');

        [$cuerpo] = self::evento('11-1-1', 'UPK-1', 'DECLINED', 2000000);
        $alterado = str_replace('"status":"DECLINED"', '"status":"APPROVED"', $cuerpo);
        $this->assertSame('FIRMA_INVALIDA', $this->falla(fn () => $w->leerNotificacion($alterado, [])), 'Estado cambiado después de firmar');

        [$cuerpo, $cab] = self::evento('11-1-1', 'UPK-1', 'APPROVED', 2000000);
        $this->assertSame('FIRMA_INVALIDA', $this->falla(fn () => $w->leerNotificacion($cuerpo, ['x-event-checksum' => str_repeat('A', 64)])), 'Cabecera distinta');

        [$cuerpo, $cab] = self::evento('11-1-1', 'UPK-1', 'APPROVED', 2000000, 1700000000, 'test_events_FICTICIO', 'prod');
        $this->assertSame('ENTORNO_DISTINTO', $this->falla(fn () => $w->leerNotificacion($cuerpo, array_change_key_case($cab, CASE_LOWER))), 'Evento de producción en sandbox');

        $this->assertSame('CUERPO_INVALIDO', $this->falla(fn () => $w->leerNotificacion('{"hola":1}', [])));

        $sinEstado = json_encode(['event' => 'transaction.updated', 'data' => ['transaction' => ['id' => 'x', 'status' => 'APPROVED']],
            'environment' => 'test', 'timestamp' => 1, 'signature' => ['properties' => ['transaction.id'], 'checksum' => hash('sha256', 'x1test_events_FICTICIO')]]);
        $this->assertSame('FIRMA_INVALIDA', $this->falla(fn () => $w->leerNotificacion($sinEstado, [])), 'La firma debe cubrir el estado');
    }

    public function testLaConsultaUsaLaLlavePrivadaYElEntornoSandbox(): void
    {
        $http = new ClienteHttpFalso();
        $http->transaccion('11-1-1', 'UPK-1', 'APPROVED', 2000000);
        $tx = $this->wompi($http)->consultar('11-1-1');
        $this->assertSame('APROBADA', $tx->estado);
        $this->assertSame('https://sandbox.wompi.co/v1/transactions/11-1-1', $http->llamadas[0]['url']);
        $this->assertSame('Bearer prv_test_FICTICIA', $http->llamadas[0]['cabeceras']['Authorization']);

        $this->assertNull($this->wompi($http)->consultar('no-existe'), '404 = no existe');
        $http->fallar('caida');
        $this->assertSame('CONSULTA_FALLIDA', $this->falla(fn () => $this->wompi($http)->consultar('caida')));
        $this->assertSame('ID_INVALIDO', $this->falla(fn () => $this->wompi($http)->consultar('../../otra')));
    }

    public function testNoSeAceptanLlavesDeProduccionNiPagosRealesSinAutorizacion(): void
    {
        $this->assertSame('CONFIGURACION', $this->falla(fn () => $this->wompi(null, ['llave_privada' => 'prv_prod_X'])), 'Llave de producción en sandbox');
        $this->assertSame('CONFIGURACION', $this->falla(fn () => $this->wompi(null, ['secreto_eventos' => ''])), 'Secreto vacío');
        $prod = ['entorno' => 'produccion', 'llave_publica' => 'pub_prod_X', 'llave_privada' => 'prv_prod_X',
                 'secreto_eventos' => 'prod_events_X', 'secreto_integridad' => 'prod_integrity_X'];
        $this->assertSame('CONFIGURACION', $this->falla(fn () => $this->wompi(null, $prod)), 'Producción sin WOMPI_PRODUCCION_AUTORIZADA');
        $this->assertSame('PRODUCCION', $this->wompi(null, $prod + ['produccion_autorizada' => true])->entorno());
    }

    private function falla(callable $fn): string
    {
        try {
            $fn();
        } catch (PasarelaException $e) {
            return $e->codigo;
        }
        return 'SIN_ERROR';
    }
}
