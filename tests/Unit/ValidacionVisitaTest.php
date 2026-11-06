<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Validator;
use App\Services\AuthService;
use App\Services\ReglasVisita;
use Tests\Support\TestCase;

/** Reglas de salida y pago sin base de datos (RN-05, RN-06, RN-08, RN-09). */
final class ValidacionVisitaTest extends TestCase
{
    private const ABIERTA = ['id_visita' => '10', 'estado' => 'EN_CURSO', 'tarifa_aplicada' => '5000.00'];
    private const PAGO = ['id_pago' => '3', 'id_visita' => '10', 'estado' => 'PAGADO'];

    public function testSalidaPermitidaConVisitaAbiertaYPagoDeEsaVisita(): void
    {
        $this->assertNull(ReglasVisita::evaluarSalida(self::ABIERTA, self::PAGO));
    }

    public function testSalidaSinPagoSeDeniegaConElMensajeDelDocumento(): void
    {
        $this->assertSame('PAGO_PENDIENTE', ReglasVisita::evaluarSalida(self::ABIERTA, null));
        $this->assertSame('Pago pendiente. Diríjase a Caja.', ReglasVisita::mensaje('PAGO_PENDIENTE'));
    }

    public function testSalidaConPagoAnuladoSeDeniega(): void
    {
        $anulado = ['estado' => 'ANULADO'] + self::PAGO;
        $this->assertSame('PAGO_PENDIENTE', ReglasVisita::evaluarSalida(self::ABIERTA, $anulado));
    }

    public function testSalidaConPagoDeOtraVisitaSeDeniega(): void
    {
        $otro = ['id_visita' => '11'] + self::PAGO;
        $this->assertSame('PAGO_OTRA_VISITA', ReglasVisita::evaluarSalida(self::ABIERTA, $otro));
    }

    public function testSalidaDeVisitaCerradaOInexistenteSeDeniega(): void
    {
        $this->assertSame('VISITA_NO_ABIERTA', ReglasVisita::evaluarSalida(['estado' => 'CERRADA'] + self::ABIERTA, self::PAGO));
        $this->assertSame('VISITA_NO_ABIERTA', ReglasVisita::evaluarSalida(['estado' => 'ANULADA'] + self::ABIERTA, null));
        $this->assertSame('SIN_VISITA', ReglasVisita::evaluarSalida(null, null));
    }

    public function testPagoUnicoPorElMontoExacto(): void
    {
        $this->assertNull(ReglasVisita::evaluarPago(self::ABIERTA, null, '5000.00'));
        $this->assertSame('PAGO_DUPLICADO', ReglasVisita::evaluarPago(self::ABIERTA, self::PAGO, '5000.00'));
        $this->assertSame('MONTO_DISTINTO', ReglasVisita::evaluarPago(self::ABIERTA, null, '4000'));
        $this->assertSame('VISITA_NO_ABIERTA', ReglasVisita::evaluarPago(['estado' => 'CERRADA'] + self::ABIERTA, null, '5000'));
    }

    public function testPagoAnuladoPermiteUnNuevoCobro(): void
    {
        $anulado = ['estado' => 'ANULADO'] + self::PAGO;
        $this->assertNull(ReglasVisita::evaluarPago(self::ABIERTA, $anulado, '5000'));
    }

    public function testDocumentoDelEstudiante(): void
    {
        foreach (['1000000001', 'CE-123456', 'AB12345'] as $doc) {
            $this->assertTrue(Validator::esDocumento($doc), "Debía aceptar {$doc}");
        }
        foreach (['123', '1000 0001', "1' OR '1'='1", str_repeat('9', 21), ''] as $doc) {
            $this->assertFalse(Validator::esDocumento($doc), "Debía rechazar «{$doc}»");
        }
    }

    public function testPoliticaDeContrasenas(): void
    {
        $this->assertNull(AuthService::validarPolitica('Parqueadero2026', 'caja'));
        $this->assertSame('La contraseña debe combinar letras y números.', AuthService::validarPolitica('soloLetrasLargas', 'caja'));
        $this->assertTrue(AuthService::validarPolitica('corta1', 'caja') !== null);
        $this->assertTrue(AuthService::validarPolitica('porteria12345', 'porteria') !== null, 'No puede contener el usuario');
        $temporal = AuthService::claveTemporal();
        $this->assertNull(AuthService::validarPolitica($temporal, 'admin'), 'La contraseña temporal cumple la política');
    }
}
