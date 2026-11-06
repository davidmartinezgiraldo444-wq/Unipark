<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServicioPago;
use Tests\Support\TestCase;

/**
 * Pago en caja: CP-04 y CP-05.
 * Datos de database/seed.sql: visitas 6 y 8 abiertas sin pago, visita 7 abierta y pagada.
 */
final class PagoTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const CAJA = 3;

    public function testCP04PagoValidoPorElMontoExactoConConsecutivoUnico(): void
    {
        $servicio = new ServicioPago($this->db());
        $p1 = $servicio->registrar(6, 'EFECTIVO', self::CAJA);
        $p2 = $servicio->registrar(8, 'TRANSFERENCIA', self::CAJA, 'TRX-1');

        $this->assertSame('1050.00', $p1['monto'], 'Monto = tarifa aplicada (moto estudiante)');
        $this->assertSame('2050.00', $p2['monto'], 'Monto = tarifa aplicada (carro estudiante)');
        $this->assertSame('PAGADO', $p1['estado']);
        $this->assertSame((string) self::CAJA, $p1['registrado_por']);
        $this->assertSame('R00000008', $p1['consecutivo'], 'Continúa la numeración del seed');
        $this->assertSame('R00000009', $p2['consecutivo']);
        $this->assertSame('TRX-1', $p2['referencia']);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'PAGO_REGISTRADO' AND id_registro = ?", [$p1['id_pago']]));
    }

    public function testCP05UnaVisitaNoSePuedePagarDosVeces(): void
    {
        $servicio = new ServicioPago($this->db());
        $this->assertRegla('PAGO_DUPLICADO', fn () => $servicio->registrar(7, 'EFECTIVO', self::CAJA));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM pagos WHERE id_visita = 7 AND estado = 'PAGADO'"));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'PAGO_RECHAZADO'"));
        $this->assertSame('7', $this->valor("SELECT ultimo FROM consecutivos WHERE nombre = 'RECIBO'"), 'Un pago rechazado no consume consecutivo');
    }

    public function testCP05LaBaseRechazaUnSegundoPagoValidoDirecto(): void
    {
        $e = $this->assertSqlFalla(
            fn () => $this->db()->exec(
                "INSERT INTO pagos (id_visita, monto, registrado_por, medio_pago, consecutivo) VALUES (7, 2800, 3, 'EFECTIVO', 'X1')"
            ),
            'El índice único pago_valido debía impedir el segundo pago'
        );
        $this->assertSame(1062, (int) $e->errorInfo[1]);
    }

    public function testCP05PagosSimultaneosDeLaMismaVisitaSoloConfirmanUno(): void
    {
        $inicio = microtime(true) + 1.5;
        $handles = [];
        $pipes = [];
        for ($i = 0; $i < 5; $i++) {
            $handles[] = proc_open(
                [PHP_BINARY, BASE_PATH . '/tests/Support/concurrente.php', 'pago', '8', 'EFECTIVO', (string) self::CAJA, (string) $inicio],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$i]
            );
        }
        $resultados = [];
        foreach ($handles as $i => $h) {
            $resultados[] = json_decode(trim(stream_get_contents($pipes[$i][1]) . stream_get_contents($pipes[$i][2])), true);
            proc_close($h);
        }
        $exitos = array_filter($resultados, static fn ($r) => $r['ok'] ?? false);
        $this->assertSame(1, count($exitos), 'Un solo pago confirmado: ' . json_encode($resultados));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM pagos WHERE id_visita = 8"));
        $this->assertSame('8', $this->valor("SELECT ultimo FROM consecutivos WHERE nombre = 'RECIBO'"), 'Sin huecos en la numeración');
    }

    public function testNoSeCobraUnaVisitaCerradaOAnulada(): void
    {
        $servicio = new ServicioPago($this->db());
        $this->assertRegla('VISITA_NO_ABIERTA', fn () => $servicio->registrar(3, 'EFECTIVO', self::CAJA));
        $this->assertRegla('VISITA_NO_ABIERTA', fn () => $servicio->registrar(9, 'EFECTIVO', self::CAJA));
        $this->assertRegla('SIN_VISITA', fn () => $servicio->registrar(9999, 'EFECTIVO', self::CAJA));
        $this->assertRegla('MEDIO_INVALIDO', fn () => $servicio->registrar(6, 'BITCOIN', self::CAJA));
    }

    public function testAnularPagoExigeMotivoConservaLaFilaYPermiteNuevoCobro(): void
    {
        $servicio = new ServicioPago($this->db());
        $this->assertRegla('MOTIVO', fn () => $servicio->anular(7, 1, ''));

        $servicio->anular(7, 1, 'Cobro registrado con medio de pago equivocado');
        $pago = $this->fila('SELECT * FROM pagos WHERE id_pago = 7');
        $this->assertSame('ANULADO', $pago['estado'], 'La fila se conserva como ANULADO');
        $this->assertSame('1', $pago['anulado_por']);
        $this->assertRegla('PAGO_NO_VALIDO', fn () => $servicio->anular(7, 1, 'Intento de anular de nuevo'));

        $nuevo = $servicio->registrar(7, 'EFECTIVO', self::CAJA);
        $this->assertSame('PAGADO', $nuevo['estado'], 'La visita se puede volver a cobrar');
        $this->assertSame(2, (int) $this->valor('SELECT COUNT(*) FROM pagos WHERE id_visita = 7'));
    }

    public function testLaBaseImpideModificarOBorrarPagosConfirmados(): void
    {
        $this->assertSqlFalla(
            fn () => $this->db()->exec('UPDATE pagos SET monto = 1 WHERE id_pago = 7'),
            'El disparador debía impedir cambiar el monto'
        );
        $this->assertSqlFalla(
            fn () => \Tests\Support\TestDatabase::admin()->exec('DELETE FROM pagos WHERE id_pago = 7'),
            'El disparador debía impedir borrar el pago incluso con la cuenta administrativa'
        );
    }
}
