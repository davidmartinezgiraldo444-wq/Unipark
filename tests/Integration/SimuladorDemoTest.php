<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\Env;
use App\Services\Pasarela\PasarelaException;
use App\Services\Pasarela\Pasarelas;
use App\Services\Pasarela\PasarelaSimulada;
use App\Services\Pasarela\PasarelaWompi;
use App\Services\Pasarela\TransaccionPasarela;
use App\Services\ServicioRecargaLinea;
use Tests\Support\TestCase;
use Tests\Unit\PasarelaWompiTest;

/**
 * Copia de demostración: pasarela SIMULADA (recorre el mismo camino del servidor que Wompi), candados para que el
 * dinero de pruebas nunca llegue a la base protegida (B2) y recargas atadas a su entorno (B3).
 * Persona 2 (estudiante2, Santiago) empieza con saldo 0.
 */
final class SimuladorDemoTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private PasarelaSimulada $sim;
    private string $carpeta;

    public function setUp(): void
    {
        parent::setUp();
        require_once BASE_PATH . '/tests/Unit/PasarelaWompiTest.php';
        $this->carpeta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'unipark-sim-' . bin2hex(random_bytes(4));
        $this->sim = new PasarelaSimulada([
            'secreto_integridad' => str_repeat('i', 40), 'secreto_eventos' => str_repeat('e', 40), 'carpeta' => $this->carpeta,
        ], url('simulador-pago'));
        Pasarelas::usar($this->sim);
    }

    public function tearDown(): void
    {
        Pasarelas::usar(null);
        foreach (glob($this->carpeta . '/*.json') ?: [] as $f) {
            unlink($f);
        }
        if (is_dir($this->carpeta)) {
            rmdir($this->carpeta);
        }
        parent::tearDown();
    }

    private function saldo(int $persona): string
    {
        return (string) $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = ?', [$persona]);
    }

    /** Recarga creada por estudiante2 y los campos del formulario que lleva a la página simulada. */
    private function iniciarPago(string $monto = '20000'): array
    {
        $this->iniciarComo('estudiante2');
        $this->solicitud('POST', '/mi-saldo/recargar', ['monto' => $monto]);
        $r = $this->fila('SELECT * FROM recargas_en_linea WHERE id_estudiante = 2 ORDER BY id_recarga DESC LIMIT 1');
        $pagina = $this->solicitud('GET', '/mi-saldo/recargas/' . $r['id_recarga'])->body;
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)">/', $pagina, $m, PREG_SET_ORDER);
        $campos = [];
        foreach ($m as [, $nombre, $valor]) {
            if ($nombre !== '_csrf') {
                $campos[$nombre] = html_entity_decode($valor, ENT_QUOTES);
            }
        }
        $this->assertContains('action="/simulador-pago"', $pagina, 'El botón de pago lleva al simulador');
        return [$r, $campos];
    }

    public function testElFlujoCompletoDeDemostracionAcreditaUnaSolaVez(): void
    {
        [$r, $campos] = $this->iniciarPago();
        $pagina = $this->solicitud('GET', '/simulador-pago?' . http_build_query($campos));
        $this->assertSame(200, $pagina->status);
        $this->assertContains('Simulación académica', $pagina->body, 'Siempre rotulado como simulación');
        $this->assertContains('$ 20.000', $pagina->body);
        $this->assertContains('Cerrar sesión', $pagina->body, 'Dentro del diseño de UniPark (con su menú)');

        $resp = $this->solicitud('POST', '/simulador-pago', $campos + ['resultado' => 'APPROVED']);
        $this->assertSame(303, $resp->status);
        $this->assertContains('/pago/retorno?ref=' . $r['referencia'] . '&id=SIM-', $resp->headers['Location'], 'Vuelve a la página puente con el id');
        $this->assertSame('20000.00', $this->saldo(2), 'Acreditada por el mismo procesador del webhook');
        $this->assertSame('APROBADA', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));
        $this->assertSame('PASARELA', $this->valor("SELECT medio_pago FROM movimientos_saldo WHERE id_estudiante = 2 AND tipo = 'RECARGA'"));

        // Volver a pagar la misma recarga (por ejemplo, con el botón «atrás» del navegador): no se procesa.
        $this->assertSame(422, $this->solicitud('POST', '/simulador-pago', $campos + ['resultado' => 'APPROVED'])->status, 'Una recarga procesada no se paga otra vez');

        // «Reenviar notificación» (duplicado) desde el panel de Administración: no acredita otra vez.
        $id = $this->valor('SELECT id_transaccion FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]);
        $this->iniciarComo('admin');
        $this->solicitud('POST', '/simulador-pago/accion', ['id' => $id, 'accion' => 'reenviar']);
        $this->assertSame('20000.00', $this->saldo(2), 'El duplicado no suma');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE id_estudiante = 2 AND tipo = 'RECARGA'"));
        $this->assertContains($id, $this->solicitud('GET', '/simulador-pago/panel')->body);
    }

    /**
     * Reembolso: el proveedor anula el pago (VOIDED) → UniPark NO toca el saldo solo (requiere revisión) →
     * Administración registra el reembolso (movimiento compensatorio con motivo) → la recarga se ve «Reembolsada»
     * en Mi saldo, el reporte deja de sumarla y la persona recibe un aviso.
     */
    public function testUnReembolsoDelProveedorSeRevisaYSeRegistraConMovimientoCompensatorio(): void
    {
        [$r, $campos] = $this->iniciarPago();
        $this->solicitud('POST', '/simulador-pago', $campos + ['resultado' => 'APPROVED']);
        $this->assertSame('20000.00', $this->saldo(2));
        $tx = (string) $this->valor('SELECT id_transaccion FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]);

        // El proveedor reembolsa: aviso firmado VOIDED después de acreditar.
        $this->iniciarComo('admin');
        $resp = $this->solicitud('POST', '/simulador-pago/accion', ['id' => $tx, 'accion' => 'reembolsar']);
        $this->assertContains('REQUIERE_REVISION', urldecode((string) $resp->headers['Location']), 'Queda para revisión');
        $this->assertSame('20000.00', $this->saldo(2), 'El saldo no se descuenta solo');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'RECARGA_LINEA_REVISION' AND id_registro = ?", [$r['id_recarga']]));

        // Administración registra el reembolso en la ficha de la persona.
        $mov = (int) $this->valor('SELECT id_movimiento FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]);
        $this->solicitud('POST', '/saldo/' . $mov . '/reversar', ['motivo' => 'Reembolso aprobado por la pasarela']);
        $this->assertSame('0.00', $this->saldo(2), 'Movimiento compensatorio');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE tipo = 'REVERSO_RECARGA' AND id_movimiento_origen = ?", [$mov]));
        $this->assertSame('APROBADA', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]), 'La recarga no se edita: el reembolso está en el libro');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones WHERE id_estudiante = 2 AND tipo = 'RECARGA_REEMBOLSADA'"), 'Aviso a la persona');
        $this->assertContains('Reembolsada', $this->solicitud('GET', '/reportes?tab=recargas')->body);

        // La persona ve la recarga como reembolsada, con el motivo.
        $this->iniciarComo('estudiante2');
        $this->assertContains('Reembolsada', $this->solicitud('GET', '/mi-saldo')->body);
        $detalle = $this->solicitud('GET', '/mi-saldo/recargas/' . $r['id_recarga'])->body;
        $this->assertContains('Recarga reembolsada el', $detalle);
        $this->assertContains('Reembolso aprobado por la pasarela', $detalle);

        // Un segundo reembolso de la misma recarga no se permite.
        $this->iniciarComo('admin');
        $this->solicitud('POST', '/saldo/' . $mov . '/reversar', ['motivo' => 'Reembolso aprobado por la pasarela']);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE tipo = 'REVERSO_RECARGA' AND id_movimiento_origen = ?", [$mov]));
    }

    public function testRechazadoYPendienteNoAcreditanHastaLaAprobacion(): void
    {
        [$r, $campos] = $this->iniciarPago();
        $this->solicitud('POST', '/simulador-pago', $campos + ['resultado' => 'DECLINED']);
        $this->assertSame('0.00', $this->saldo(2), 'Rechazado: sin saldo');
        $this->assertSame('RECHAZADA', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));

        [$r2, $campos2] = $this->iniciarPago('10000');
        $this->solicitud('POST', '/simulador-pago', $campos2 + ['resultado' => 'PENDING']);
        $this->assertSame('PENDIENTE', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r2['id_recarga']]));
        $this->assertSame('0.00', $this->saldo(2), 'Pendiente: sin saldo');

        $id = $this->valor('SELECT id_transaccion FROM recargas_en_linea WHERE id_recarga = ?', [$r2['id_recarga']]);
        $this->assertSame(403, $this->solicitud('POST', '/simulador-pago/accion', ['id' => $id, 'accion' => 'aprobar'])->status, 'La persona no aprueba su propio pendiente');
        $this->assertSame('0.00', $this->saldo(2));
        $this->iniciarComo('admin');
        $this->solicitud('POST', '/simulador-pago/accion', ['id' => $id, 'accion' => 'aprobar']);
        $this->assertSame('10000.00', $this->saldo(2), 'Al aprobarse el pendiente, se acredita');
    }

    public function testElSimuladorRechazaUnPagoAlterado(): void
    {
        [, $campos] = $this->iniciarPago();
        $alterado = ['amount-in-cents' => '100'] + $campos;
        $this->assertSame(422, $this->solicitud('GET', '/simulador-pago?' . http_build_query($alterado))->status, 'Monto cambiado: firma inválida');
        $this->assertSame(422, $this->solicitud('POST', '/simulador-pago', $alterado + ['resultado' => 'APPROVED'])->status);
        $otroDestino = ['redirect-url' => 'https://evil.test/pago/retorno'] + $campos;
        $this->assertSame(422, $this->solicitud('GET', '/simulador-pago?' . http_build_query($otroDestino))->status, 'Solo vuelve a esta instalación');
        $this->assertSame('0.00', $this->saldo(2));
        $this->assertSame([], $this->sim->transacciones(), 'No se creó ninguna transacción');
    }

    public function testUnaNotificacionSimuladaConFirmaFalsaNoAcredita(): void
    {
        [$r] = $this->iniciarPago();
        $id = $this->sim->crearTransaccion($r['referencia'], 2000000, 'APPROVED');
        [$cuerpo] = $this->sim->evento($id);
        $resp = $this->webhook('/api/pasarela/wompi/eventos', $cuerpo, ['x-event-checksum' => str_repeat('a', 64)]);
        $this->assertSame(401, $resp->status);
        $this->assertSame('0.00', $this->saldo(2));
    }

    public function testSinSimuladorActivoSusPaginasNoExisten(): void
    {
        Pasarelas::usar(new PasarelaWompi(PasarelaWompiTest::CONFIG, new \Tests\Support\ClienteHttpFalso()));
        $this->iniciarComo('estudiante2');
        $this->assertSame(404, $this->solicitud('GET', '/simulador-pago')->status);
        $this->iniciarComo('admin');
        $this->assertSame(404, $this->solicitud('GET', '/simulador-pago/panel')->status);
        Pasarelas::usar(null);
        $this->iniciarComo('estudiante2');
        $this->assertSame(404, $this->solicitud('POST', '/simulador-pago', ['resultado' => 'APPROVED'])->status, 'Pasarela apagada');
    }

    /** Hallazgo B1 de la auditoría: nadie aprueba una recarga ajena y el panel no es público. */
    public function testNadiePuedeAprobarUnaRecargaAjenaNiVerElPanelSinPermiso(): void
    {
        [$r, $campos] = $this->iniciarPago();

        $_SESSION = [];
        $this->assertSame(303, $this->solicitud('GET', '/simulador-pago?' . http_build_query($campos))->status, 'Sin sesión: al login');
        $this->assertSame(303, $this->solicitud('GET', '/simulador-pago/panel')->status, 'El panel no es público');
        $this->assertSame(419, $this->solicitud('POST', '/simulador-pago', $campos + ['resultado' => 'APPROVED'], false)->status, 'Sin CSRF');

        $this->iniciarComo('estudiante1');
        $this->assertSame(422, $this->solicitud('GET', '/simulador-pago?' . http_build_query($campos))->status, 'Otra persona no ve el pago');
        $this->assertSame(422, $this->solicitud('POST', '/simulador-pago', $campos + ['resultado' => 'APPROVED'])->status, 'Ni lo aprueba');
        $this->assertSame(403, $this->solicitud('GET', '/simulador-pago/panel')->status);
        $this->iniciarComo('caja');
        $this->assertSame(403, $this->solicitud('GET', '/simulador-pago/panel')->status, 'El panel es solo de Administración');
        $this->assertSame(403, $this->solicitud('POST', '/simulador-pago/accion', ['id' => 'SIM-261108-AAAAAAAAAA', 'accion' => 'reenviar'])->status);

        $this->assertSame('0.00', $this->saldo(2), 'Nada se acreditó');
        $this->assertSame([], $this->sim->transacciones(), 'No se creó ninguna transacción');
        $this->assertSame('CREADA', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));
    }

    // B2 ------------------------------------------------------------------------------------
    public function testElDineroDePruebasNuncaLlegaALaBaseProtegida(): void
    {
        $falla = function (callable $fn): string {
            try {
                $fn();
            } catch (PasarelaException $e) {
                return $e->codigo;
            }
            return 'SIN_ERROR';
        };
        $this->assertSame('CONFIGURACION', $falla(fn () => Pasarelas::exigirEntornoDePruebas('Prueba', 'produccion', 'unipark_demo')), 'Instalación de producción');
        $this->assertSame('CONFIGURACION', $falla(fn () => Pasarelas::exigirEntornoDePruebas('Prueba', 'local', (string) config('base_protegida'))), 'Base protegida');
        $this->assertSame('SIN_ERROR', $falla(fn () => Pasarelas::exigirEntornoDePruebas('Prueba', 'local', 'unipark_demo')), 'Copia de demostración');
        $this->assertSame('unipark_db', config('base_protegida'));
        // Esta suite corre con APP_ENV=produccion (config/env.php): crear el simulador «de verdad» debe negarse.
        if (config('entorno') === 'produccion') {
            $this->assertSame('CONFIGURACION', $falla(fn () => Pasarelas::crear('SIMULADO')));
        }
    }

    // B3 ------------------------------------------------------------------------------------
    public function testUnaRecargaSoloAceptaTransaccionesDeSuEntorno(): void
    {
        [$r] = $this->iniciarPago();
        $tx = new TransaccionPasarela('SIMULADO', 'SIM-261108-AAAAAAAAAA', $r['referencia'], 'APROBADA', 'APPROVED', 2000000, 'COP', 'SIMULADO', 'PRODUCCION');
        $res = (new ServicioRecargaLinea($this->db()))->aplicarTransaccion($tx);
        $this->assertSame('ENTORNO_DISTINTO', $res['resultado']);
        $this->assertSame('0.00', $this->saldo(2));

        // Una recarga SANDBOX no ofrece pagar con una pasarela de producción.
        $prod = new PasarelaWompi(['entorno' => 'produccion', 'produccion_autorizada' => true, 'llave_publica' => 'pub_prod_X', 'llave_privada' => 'prv_prod_X',
            'secreto_eventos' => 'prod_events_X', 'secreto_integridad' => 'prod_integrity_X'] + PasarelaWompiTest::CONFIG, new \Tests\Support\ClienteHttpFalso());
        Pasarelas::usar($prod);
        $recarga = $this->fila('SELECT * FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]);
        $persona = $this->fila('SELECT * FROM estudiantes WHERE id_estudiante = 2');
        $this->assertNull((new ServicioRecargaLinea($this->db()))->formularioPago(['proveedor' => 'WOMPI'] + $recarga, $persona, 'https://x/pago/retorno'), 'Sandbox ≠ producción');
    }

    // Instancia -------------------------------------------------------------------------------
    public function testLaInstanciaImponeSuConfiguracion(): void
    {
        $archivo = $this->carpeta . '-config.php';
        file_put_contents($archivo, "<?php return ['PRUEBA_INSTANCIA_UNIPARK' => 'demo'];");
        Env::forzarDesde($archivo);
        unlink($archivo);
        $this->assertSame('demo', Env::get('PRUEBA_INSTANCIA_UNIPARK'));
        try {
            Env::forzarDesde($archivo);
            $this->assertTrue(false, 'Un archivo inexistente debe fallar');
        } catch (\RuntimeException) {
        }
        $this->assertNotContains('MODO DEMOSTRACIÓN', $this->solicitud('GET', '/login')->body, 'El sistema principal no muestra el rótulo de demo');
    }
}
