<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Validator;
use App\Services\ReglasVisita;
use Tests\Support\TestCase;

/** Validación de valores de tarifa y comparación de montos (RN-03, sección 8.4). */
final class TarifaTest extends TestCase
{
    public function testAceptaValoresPositivosConHastaDosDecimales(): void
    {
        foreach (['5000', '2500.5', '2500.50', '0.01', '99999999.99'] as $valor) {
            $this->assertTrue(Validator::esDinero($valor), "Debía aceptar {$valor}");
        }
    }

    public function testRechazaNegativosTextoYMasDeDosDecimales(): void
    {
        foreach (['-1', '-0.01', 'abc', '', '5000.123', '1,5', '1e3', '100000000', ' 5000'] as $valor) {
            $this->assertFalse(Validator::esDinero($valor), "Debía rechazar «{$valor}»");
        }
    }

    public function testTarifaCeroDependeDeLaPoliticaInstitucional(): void
    {
        $this->assertFalse(Validator::esDinero('0'), 'Sin permiso explícito, cero se rechaza');
        $this->assertTrue(Validator::esDinero('0', true), 'Con permitir_tarifa_cero, cero se acepta');
        $this->assertTrue(Validator::esDinero('0.00', true));
    }

    public function testComparaMontosEnCentavosSinErroresDeComaFlotante(): void
    {
        $this->assertTrue(ReglasVisita::montoCoincide('5000', '5000.00'));
        $this->assertTrue(ReglasVisita::montoCoincide('0.3', '0.30'));
        $this->assertFalse(ReglasVisita::montoCoincide('4999.99', '5000.00'));
        $this->assertSame(10, ReglasVisita::centavos('0.1'));
        $this->assertSame(250050, ReglasVisita::centavos('2500.5'));
    }

    public function testSoloExistenLasCategoriasEstudianteYProfesor(): void
    {
        $this->assertTrue(ReglasVisita::categoriaValida('ESTUDIANTE'));
        $this->assertTrue(ReglasVisita::categoriaValida('PROFESOR'));
        foreach (['estudiante', 'VISITANTE', ''] as $cat) {
            $this->assertFalse(ReglasVisita::categoriaValida($cat), "Debía rechazar «{$cat}»");
        }
        $this->assertSame('Profesor', categoria_label('PROFESOR'));
    }

    public function testSeRegistranCuatroTiposYSoloCarroYMotoTienenTarifa(): void
    {
        foreach (['CARRO', 'MOTO', 'BICICLETA', 'PATINETA'] as $tipo) {
            $this->assertTrue(ReglasVisita::tipoValido($tipo), "Debía aceptar «{$tipo}»");
        }
        foreach (['carro', 'AVION', '', 'CARRO '] as $tipo) {
            $this->assertFalse(ReglasVisita::tipoValido($tipo), "Debía rechazar «{$tipo}»");
        }
        $this->assertTrue(ReglasVisita::tipoTarifable('CARRO'));
        $this->assertTrue(ReglasVisita::tipoTarifable('MOTO'));
        $this->assertFalse(ReglasVisita::tipoTarifable('BICICLETA'), 'Bicicleta: sin tarifa definida');
        $this->assertFalse(ReglasVisita::tipoTarifable('PATINETA'), 'Patineta: sin tarifa definida');
    }
}
