<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServicioPago;
use App\Services\ServicioTarifa;
use Tests\Support\TestCase;
use Tests\Support\TestDatabase;

/** Trazabilidad: CP-13 y protección de la bitácora (RN-10, RF-17). */
final class AuditoriaTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    public function testCP13CambioDeTarifaRegistraUsuarioHoraAccionYValores(): void
    {
        $id = (new ServicioTarifa($this->db()))->cambiar('MOTO', 'ESTUDIANTE', '1200', 1);

        $ev = $this->fila("SELECT * FROM auditoria WHERE accion = 'TARIFA_CAMBIADA' AND id_registro = ?", [$id]);
        $this->assertSame('1', $ev['id_usuario'] ?? null, 'Usuario');
        $this->assertSame('EXITO', $ev['resultado']);
        $this->assertTrue(abs(strtotime($ev['creado_en']) - time()) < 120, 'Hora del servidor');
        $detalle = json_decode($ev['detalle_json'], true);
        $this->assertSame('1050.00', $detalle['valor_anterior']);
        $this->assertSame('1200', $detalle['valor_nuevo']);
        $this->assertSame('ESTUDIANTE', $detalle['categoria']);

        $anterior = $this->fila('SELECT * FROM tarifas WHERE id_tarifa = 3');
        $this->assertTrue($anterior['vigente_hasta'] !== null, 'La tarifa anterior queda cerrada, no borrada');
        $this->assertSame('1200.00', $this->valor("SELECT valor FROM tarifas WHERE tarifa_abierta = 'MOTO-ESTUDIANTE'"));
        $this->assertSame('1800.00', $this->valor("SELECT valor FROM tarifas WHERE tarifa_abierta = 'MOTO-PROFESOR'"), 'La tarifa de profesor no cambia');
    }

    public function testCP13AnulacionRegistraElMotivo(): void
    {
        (new ServicioPago($this->db()))->anular(7, 1, 'Devolución autorizada por la coordinación');
        $ev = $this->fila("SELECT * FROM auditoria WHERE accion = 'PAGO_ANULADO' AND id_registro = 7");
        $this->assertSame('Devolución autorizada por la coordinación', $ev['motivo'] ?? null);
        $this->assertSame('1', $ev['id_usuario']);
    }

    public function testTarifaIgualOValorInvalidoSeRechaza(): void
    {
        $servicio = new ServicioTarifa($this->db());
        $this->assertRegla('SIN_CAMBIO', fn () => $servicio->cambiar('CARRO', 'ESTUDIANTE', '2050', 1));
        $this->assertRegla('VALOR_INVALIDO', fn () => $servicio->cambiar('CARRO', 'ESTUDIANTE', '-100', 1));
        $this->assertRegla('VALOR_INVALIDO', fn () => $servicio->cambiar('CARRO', 'PROFESOR', '0', 1));
        $this->assertRegla('CATEGORIA_INVALIDA', fn () => $servicio->cambiar('CARRO', 'VISITANTE', '3000', 1));
        $this->assertSame(6, (int) $this->valor('SELECT COUNT(*) FROM tarifas'));
    }

    public function testLaBitacoraNoSePuedeModificarNiBorrar(): void
    {
        $this->assertSqlFalla(
            fn () => TestDatabase::admin()->exec("UPDATE auditoria SET motivo = 'alterado' WHERE id_auditoria = 1"),
            'El disparador debía impedir modificar la auditoría'
        );
        $this->assertSqlFalla(
            fn () => TestDatabase::admin()->exec('DELETE FROM auditoria WHERE id_auditoria = 1'),
            'El disparador debía impedir borrar la auditoría'
        );
        $this->assertSame(2, (int) $this->valor('SELECT COUNT(*) FROM auditoria'));
    }

    public function testLaCuentaDeLaAplicacionNoTienePermisoDeBorrar(): void
    {
        // Mínimo privilegio (sección 9.2): además de los disparadores, la cuenta no tiene DELETE.
        foreach (['pagos', 'visitas', 'estudiantes', 'usuarios'] as $tabla) {
            $e = $this->assertSqlFalla(
                fn () => $this->db()->exec("DELETE FROM {$tabla} WHERE 1 = 0"),
                "La cuenta de la aplicación no debía poder borrar en {$tabla}"
            );
            $this->assertSame(1142, (int) $e->errorInfo[1], "Error de privilegio en {$tabla}");
        }
        $e = $this->assertSqlFalla(
            fn () => $this->db()->exec('UPDATE auditoria SET motivo = motivo WHERE 1 = 0'),
            'La cuenta de la aplicación no debía poder modificar la auditoría'
        );
        $this->assertSame(1142, (int) $e->errorInfo[1]);
    }
}
