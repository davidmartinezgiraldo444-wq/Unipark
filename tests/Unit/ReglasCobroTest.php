<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LectorDocumento;
use App\Services\ReglasCobro;
use Tests\Support\TestCase;

/** Reglas puras del cobro diario y de la lectura de documentos. */
final class ReglasCobroTest extends TestCase
{
    public function testUnaEstadiaDeVariosDiasCobraCadaDiaSiguienteALaEntrada(): void
    {
        $this->assertSame([], ReglasCobro::diasDeEstadiaPorCobrar('2026-11-07 07:00:00', '2026-11-07 23:59:59', []), 'Mismo día: nada extra');
        $this->assertSame(['2026-11-08'], ReglasCobro::diasDeEstadiaPorCobrar('2026-11-07 07:00:00', '2026-11-08 00:00:00', []), 'Desde las 00:00 del día siguiente');
        $this->assertSame(['2026-11-08', '2026-11-09'], ReglasCobro::diasDeEstadiaPorCobrar('2026-11-07 22:00:00', '2026-11-09 08:00:00', ['2026-11-07']));
        $this->assertSame(['2026-11-09'], ReglasCobro::diasDeEstadiaPorCobrar('2026-11-07 22:00:00', '2026-11-09 08:00:00', ['2026-11-07', '2026-11-08']), 'Solo los que faltan');
        $this->assertSame(['2026-01-01'], ReglasCobro::diasDeEstadiaPorCobrar('2025-12-31 18:00:00', '2026-01-01 06:00:00', []), 'Cambio de año');
    }

    public function testLaToleranciaVenceALos15MinutosExactos(): void
    {
        $fin = ReglasCobro::finTolerancia('2026-11-07 07:00:00', 15);
        $this->assertSame('2026-11-07 07:15:00', $fin);
        $this->assertTrue(ReglasCobro::enTolerancia($fin, '2026-11-07 07:14:59'));
        $this->assertFalse(ReglasCobro::enTolerancia($fin, '2026-11-07 07:15:00'), 'A los 15:00 ya se cobra');
    }

    public function testSoloSeCobraACategoriasYVehiculosConTarifa(): void
    {
        foreach (['ESTUDIANTE', 'PROFESOR', 'TRABAJADOR'] as $cat) {
            $this->assertTrue(ReglasCobro::generaCobro($cat, 'CARRO'));
            $this->assertTrue(ReglasCobro::generaCobro($cat, 'MOTO'));
            $this->assertFalse(ReglasCobro::generaCobro($cat, 'BICICLETA'));
            $this->assertFalse(ReglasCobro::generaCobro($cat, 'PATINETA'));
        }
        $this->assertFalse(ReglasCobro::generaCobro('VISITANTE', 'CARRO'), 'Visitantes no pagan');
    }

    public function testUnSoloCobroPorDia(): void
    {
        $this->assertSame('EN_TOLERANCIA', ReglasCobro::liquidacionInicial('ESTUDIANTE', 'CARRO', null, '2000'));
        $this->assertSame('CUBIERTA', ReglasCobro::liquidacionInicial('ESTUDIANTE', 'CARRO', '2000.00', '2000'), 'Mismo vehículo: cubierta');
        $this->assertSame('CUBIERTA', ReglasCobro::liquidacionInicial('ESTUDIANTE', 'MOTO', '2000.00', '1000'), 'Carro y luego moto: cubierta');
        $this->assertSame('EN_TOLERANCIA', ReglasCobro::liquidacionInicial('ESTUDIANTE', 'CARRO', '1000.00', '2000'), 'Moto y luego carro: espera para cobrar la diferencia');
        $visita = ['categoria' => 'PROFESOR', 'tipo_vehiculo' => 'MOTO', 'id_tarifa' => '10', 'tarifa_aplicada' => '2000.00'];
        $this->assertSame('COBRAR', ReglasCobro::alVencer($visita, null));
        $this->assertSame('CUBIERTA', ReglasCobro::alVencer($visita, '3000.00'));
        $this->assertSame('DIFERENCIA', ReglasCobro::alVencer(['tipo_vehiculo' => 'CARRO', 'tarifa_aplicada' => '3000.00'] + $visita, '2000.00'));
        $this->assertSame('1000.00', ReglasCobro::diferencia('2000.00', '1000.00'), 'Solo la diferencia');
        $this->assertNull(ReglasCobro::diferencia('1000.00', '2000.00'));
    }

    public function testLaSalidaSoloConsultaElEstado(): void
    {
        $base = ['id_visita' => '1', 'estado' => 'EN_CURSO', 'tolerancia_hasta' => '2026-11-07 07:15:00'];
        $ahora = '2026-11-07 09:00:00';
        $this->assertNull(ReglasCobro::evaluarSalida($base + ['liquidacion' => 'EN_TOLERANCIA'], null, '2026-11-07 07:10:00'));
        $this->assertSame('VENCIDA_SIN_LIQUIDAR', ReglasCobro::evaluarSalida($base + ['liquidacion' => 'EN_TOLERANCIA'], null, $ahora));
        $this->assertNull(ReglasCobro::evaluarSalida($base + ['liquidacion' => 'NO_APLICA'], null, $ahora));
        $this->assertNull(ReglasCobro::evaluarSalida($base + ['liquidacion' => 'COBRADA'], ['estado' => 'PAGADO'], $ahora));
        $this->assertNull(ReglasCobro::evaluarSalida($base + ['liquidacion' => 'CUBIERTA'], ['estado' => 'ANULADO'], $ahora));
        $this->assertSame('PAGO_PENDIENTE', ReglasCobro::evaluarSalida($base + ['liquidacion' => 'COBRADA'], ['estado' => 'PENDIENTE'], $ahora));
        $this->assertSame('VISITA_NO_ABIERTA', ReglasCobro::evaluarSalida(['estado' => 'CERRADA', 'liquidacion' => 'COBRADA'] + $base, null, $ahora));
    }

    public function testSaldoSuficienteEnCentavos(): void
    {
        $this->assertTrue(ReglasCobro::saldoSuficiente('2000.00', '2000'));
        $this->assertFalse(ReglasCobro::saldoSuficiente('1999.99', '2000.00'));
    }

    public function testLecturaDeCarnetYCedula(): void
    {
        $this->assertSame('1000000001', LectorDocumento::interpretar(' 1000000001 ')['codigo']);
        $this->assertSame('CARNET', LectorDocumento::interpretar('LS-2026-0042')['formato']);
        $this->assertSame('52333444', LectorDocumento::interpretar('52.333.444')['codigo']);

        // PDF417 de la cédula amarilla: número en las posiciones 48-57 y nombres en bloques de 23.
        $pdf417 = str_pad('0349876543PubDSK_1', 48, ' ') . '0052333444'
            . str_pad('PEREZ', 23) . str_pad('GOMEZ', 23) . str_pad('ANA', 23) . str_pad('MARIA', 23)
            . '0F19900101' . str_repeat(' ', 40);
        $c = LectorDocumento::interpretar($pdf417);
        $this->assertSame('CEDULA_PDF417', $c['formato']);
        $this->assertSame('52333444', $c['codigo']);
        $this->assertSame('Ana Maria Perez Gomez', $c['nombre'], 'Solo número y nombre: el resto se descarta');

        $this->assertSame('1023456789', LectorDocumento::interpretar('ICCOL1023456789<5<<<<<<<<<<<<')['codigo']);
        $this->assertSame('', LectorDocumento::interpretar('hola')['codigo']);
    }
}
