<?php
declare(strict_types=1);

namespace Tests\Security;

use Tests\Support\TestCase;

/** CP-12: el servidor rechaza solicitudes sensibles sin un token CSRF válido. */
final class CsrfTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    public function testCP12PagoSinTokenSeRechaza(): void
    {
        $this->iniciarComo('caja');
        $r = $this->solicitud('POST', '/caja/pago', ['id_visita' => '8', 'medio_pago' => 'EFECTIVO'], false);

        $this->assertSame(419, $r->status);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM pagos WHERE id_visita = 8'), 'No se registra el pago');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'CSRF_INVALIDO'"), 'El intento se audita');
    }

    public function testCP12TokenIncorrectoSeRechazaEnTodasLasOperacionesSensibles(): void
    {
        $this->iniciarComo('admin');
        $rutas = [
            '/tarifas'           => ['tipo' => 'CARRO', 'categoria' => 'ESTUDIANTE', 'valor' => '9000'],
            '/pagos/7/anular'    => ['motivo' => 'Motivo suficientemente largo'],
            '/visitas/8/anular'  => ['motivo' => 'Motivo suficientemente largo'],
            '/usuarios/2/estado' => ['activo' => '0'],
            '/usuarios/2/clave'  => [],
            '/estudiantes'       => ['documento' => '3000000001', 'nombres' => 'A', 'apellidos' => 'B'],
            '/logout'            => [],
        ];
        foreach ($rutas as $ruta => $datos) {
            $r = $this->solicitud('POST', $ruta, $datos + ['_csrf' => str_repeat('0', 64)]);
            $this->assertSame(419, $r->status, $ruta);
        }
        $this->assertSame('2050.00', $this->valor("SELECT valor FROM tarifas WHERE tarifa_abierta = 'CARRO-ESTUDIANTE'"), 'La tarifa no cambia');
        $this->assertSame('PAGADO', $this->valor('SELECT estado FROM pagos WHERE id_pago = 7'), 'El pago no se anula');
        $this->assertSame('1', $this->valor('SELECT activo FROM usuarios WHERE id_usuario = 2'), 'La cuenta sigue activa');
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM estudiantes WHERE documento = '3000000001'"));
        $this->assertSame(1, $_SESSION['id_usuario'] ?? null, 'El logout sin token no cierra la sesión');
    }

    public function testCP12LoginTambienExigeToken(): void
    {
        $r = $this->solicitud('POST', '/login', ['login' => 'caja', 'clave' => 'Demo2026*'], false);
        $this->assertSame(419, $r->status);
        $this->assertSame(null, $_SESSION['id_usuario'] ?? null);
    }

    public function testConTokenValidoLaOperacionProcede(): void
    {
        $this->iniciarComo('caja');
        $r = $this->solicitud('POST', '/caja/pago', ['id_visita' => '8', 'medio_pago' => 'EFECTIVO']);
        $this->assertSame(303, $r->status);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM pagos WHERE id_visita = 8 AND estado = 'PAGADO'"));
    }

    public function testLosFormulariosIncluyenElToken(): void
    {
        $this->iniciarComo('porteria');
        $html = $this->solicitud('GET', '/porteria?documento=1000000007')->body;
        $this->assertContains('name="_csrf" value="' . $_SESSION['_csrf'] . '"', $html);
    }
}
