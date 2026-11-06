<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServicioPago;
use App\Services\ServicioSalida;
use Tests\Support\TestCase;

/**
 * Salida en portería: CP-06 y CP-07.
 * Datos de database/seed.sql: visita 8 abierta sin pago, visita 7 abierta y pagada.
 */
final class SalidaTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const PORTERIA = 2;

    public function testCP06SalidaSinPagoSeDeniegaYNoSeRegistra(): void
    {
        $e = $this->assertRegla('PAGO_PENDIENTE', fn () => (new ServicioSalida($this->db()))->autorizar(8, self::PORTERIA));

        $this->assertSame('Pago pendiente. Diríjase a Caja.', $e->getMessage());
        $visita = $this->fila('SELECT * FROM visitas WHERE id_visita = 8');
        $this->assertSame('EN_CURSO', $visita['estado'], 'La visita sigue abierta');
        $this->assertNull($visita['salida_en'], 'No se registra hora de salida');
        $this->assertNull($visita['salida_por']);

        $auditoria = $this->fila("SELECT * FROM auditoria WHERE accion = 'SALIDA_DENEGADA' AND id_registro = 8");
        $this->assertSame('DENEGADO', $auditoria['resultado'] ?? null, 'La salida denegada se audita (RF-17)');
        $this->assertSame((string) self::PORTERIA, $auditoria['id_usuario']);
    }

    public function testCP07SalidaConPagoCierraLaVisita(): void
    {
        $visita = (new ServicioSalida($this->db()))->autorizar(7, self::PORTERIA);

        $this->assertSame('CERRADA', $visita['estado']);
        $this->assertTrue($visita['salida_en'] !== null, 'Registra la hora de salida');
        $this->assertSame((string) self::PORTERIA, $visita['salida_por'], 'Registra quién autoriza');
        $this->assertTrue($visita['salida_en'] >= $visita['entrada_en']);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'SALIDA_AUTORIZADA' AND id_registro = 7"));
    }

    public function testFlujoCompletoEntradaPagoSalida(): void
    {
        $salida = new ServicioSalida($this->db());
        $this->assertRegla('PAGO_PENDIENTE', fn () => $salida->autorizar(8, self::PORTERIA));
        (new ServicioPago($this->db()))->registrar(8, 'EFECTIVO', 3);
        $this->assertSame('CERRADA', $salida->autorizar(8, self::PORTERIA)['estado']);
    }

    public function testUnaVisitaCerradaNoSaleDosVecesNiSeModifica(): void
    {
        $salida = new ServicioSalida($this->db());
        $salida->autorizar(7, self::PORTERIA);
        $this->assertRegla('VISITA_NO_ABIERTA', fn () => $salida->autorizar(7, self::PORTERIA));
        $this->assertRegla('VISITA_NO_ABIERTA', fn () => $salida->autorizar(9, self::PORTERIA));
        $this->assertRegla('SIN_VISITA', fn () => $salida->autorizar(9999, self::PORTERIA));

        $this->assertSqlFalla(
            fn () => $this->db()->exec("UPDATE visitas SET salida_en = NOW() WHERE id_visita = 7"),
            'Una visita cerrada no se puede modificar'
        );
    }

    public function testPagoAnuladoVuelveABloquearLaSalida(): void
    {
        (new ServicioPago($this->db()))->anular(7, 1, 'Reverso autorizado por la administración');
        $this->assertRegla('PAGO_PENDIENTE', fn () => (new ServicioSalida($this->db()))->autorizar(7, self::PORTERIA));
    }

    public function testLaBaseExigeEstadosConsistentes(): void
    {
        $this->assertSqlFalla(
            fn () => $this->db()->exec("UPDATE visitas SET estado = 'CERRADA' WHERE id_visita = 8"),
            'CERRADA sin salida_en debía violar ck_visitas_estado'
        );
        $this->assertSqlFalla(
            fn () => $this->db()->exec("UPDATE visitas SET salida_en = entrada_en - INTERVAL 1 HOUR, salida_por = 2, estado = 'CERRADA' WHERE id_visita = 8"),
            'salida_en anterior a entrada_en debía violar ck_visitas_salida'
        );
        $this->assertSqlFalla(
            fn () => \Tests\Support\TestDatabase::admin()->exec('DELETE FROM visitas WHERE id_visita = 8'),
            'Las visitas no se borran'
        );
    }
}
