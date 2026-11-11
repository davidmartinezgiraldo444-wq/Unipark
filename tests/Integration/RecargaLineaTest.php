<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Pasarela\Pasarelas;
use App\Services\Pasarela\PasarelaWompi;
use App\Services\Pasarela\TransaccionPasarela;
use App\Services\ServicioCobro;
use App\Services\ServicioEntrada;
use App\Services\ServicioPago;
use App\Services\ServicioRecargaLinea;
use App\Services\ServicioSaldo;
use App\Services\ServicioSalida;
use Tests\Support\ClienteHttpFalso;
use Tests\Support\TestCase;
use Tests\Unit\PasarelaWompiTest;

/**
 * Saldo prepago con recargas VOLUNTARIAS en línea (Wompi en modo sandbox simulado, sin internet).
 * Personas de database/seed.sql: 1 Valentina (estudiante1, saldo 10.000), 2 Santiago (estudiante2, saldo 0).
 */
final class RecargaLineaTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const PORTERIA = 2;
    private const CAJA = 3;
    private const WEBHOOK = '/api/pasarela/wompi/eventos';

    private ClienteHttpFalso $http;

    public function setUp(): void
    {
        parent::setUp();
        require_once BASE_PATH . '/tests/Unit/PasarelaWompiTest.php';
        $this->http = new ClienteHttpFalso();
        Pasarelas::usar(new PasarelaWompi(PasarelaWompiTest::CONFIG, $this->http));
    }

    public function tearDown(): void
    {
        Pasarelas::usar(null);
        parent::tearDown();
    }

    private function usuarioDe(int $persona): int
    {
        return (int) $this->valor('SELECT id_usuario FROM usuarios WHERE id_estudiante = ?', [$persona]);
    }

    private function recarga(int $persona, string $monto = '20000'): array
    {
        return (new ServicioRecargaLinea($this->db()))->crear($persona, $this->usuarioDe($persona), $monto);
    }

    /** Wompi confirma la transacción (consulta) y envía el webhook firmado. */
    private function pagar(array $recarga, string $estado, string $idTx = '11-1-1', int $timestamp = 1700000000): \App\Helpers\Response
    {
        $centavos = (int) round((float) $recarga['monto'] * 100);
        $this->http->transaccion($idTx, $recarga['referencia'], $estado, $centavos);
        [$cuerpo, $cab] = PasarelaWompiTest::evento($idTx, $recarga['referencia'], $estado, $centavos, $timestamp);
        return $this->webhook(self::WEBHOOK, $cuerpo, $cab);
    }

    private function saldo(int $persona): string
    {
        return (string) $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = ?', [$persona]);
    }

    private function recargasEnLinea(int $persona): int
    {
        return (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE id_estudiante = ? AND tipo = 'RECARGA' AND medio_pago = 'PASARELA'", [$persona]);
    }

    private function adelantar(int $minutos): void
    {
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW() + INTERVAL ' . $minutos . ' MINUTE)');
    }

    // 1 ----------------------------------------------------------------------------------
    public function testRecargaAprobadaAumentaElSaldoUnaSolaVez(): void
    {
        $r = $this->recarga(2, '20.000');
        $this->assertSame('CREADA', $r['estado']);
        $this->assertSame('0.00', $this->saldo(2), 'Crear la recarga no mueve dinero');

        $resp = $this->pagar($r, 'APPROVED');
        $this->assertSame(200, $resp->status);
        $this->assertSame('20000.00', $this->saldo(2));
        $this->assertSame(1, $this->recargasEnLinea(2));
        $fila = $this->fila('SELECT * FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]);
        $this->assertSame('APROBADA', $fila['estado']);
        $mov = $this->fila('SELECT * FROM movimientos_saldo WHERE id_movimiento = ?', [$fila['id_movimiento']]);
        $this->assertSame('0.00', $mov['saldo_anterior']);
        $this->assertSame('20000.00', $mov['saldo_nuevo']);
        $this->assertNull($mov['registrado_por'], 'No es dinero recibido por un cajero');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones WHERE id_estudiante = 2 AND tipo = 'RECARGA_APROBADA'"), 'Se avisa a la persona');
    }

    // 2 ----------------------------------------------------------------------------------
    public function testRecargaRechazadaNoAumentaElSaldo(): void
    {
        $r = $this->recarga(2);
        $this->pagar($r, 'DECLINED');
        $this->assertSame('0.00', $this->saldo(2));
        $this->assertSame(0, $this->recargasEnLinea(2));
        $this->assertSame('RECHAZADA', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));

        // Un reintento aprobado de la misma referencia sí acredita (la persona pagó con otro medio).
        $this->pagar($r, 'APPROVED', '11-1-2', 1700000100);
        $this->assertSame('20000.00', $this->saldo(2));
    }

    // 3 ----------------------------------------------------------------------------------
    public function testRecargaPendienteNoAumentaElSaldoHastaLaConfirmacion(): void
    {
        $r = $this->recarga(2);
        $this->pagar($r, 'PENDING');
        $this->assertSame('PENDIENTE', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));
        $this->assertSame('0.00', $this->saldo(2));

        $this->pagar($r, 'APPROVED', '11-1-1', 1700000200);
        $this->assertSame('20000.00', $this->saldo(2), 'Se acredita cuando llega la aprobación');
    }

    // 4 ----------------------------------------------------------------------------------
    public function testUnWebhookRepetidoNoDuplicaLaAcreditacion(): void
    {
        $r = $this->recarga(2);
        $centavos = 2000000;
        $this->http->transaccion('11-1-1', $r['referencia'], 'APPROVED', $centavos);
        [$cuerpo, $cab] = PasarelaWompiTest::evento('11-1-1', $r['referencia'], 'APPROVED', $centavos);
        $this->assertSame(200, $this->webhook(self::WEBHOOK, $cuerpo, $cab)->status);
        $this->assertSame(200, $this->webhook(self::WEBHOOK, $cuerpo, $cab)->status, 'El reenvío idéntico responde 200');
        $this->pagar($r, 'APPROVED', '11-1-1', 1700009999); // reintento con otro timestamp (otro cuerpo)
        (new ServicioRecargaLinea($this->db()))->conciliar();
        $this->assertSame('20000.00', $this->saldo(2));
        $this->assertSame(1, $this->recargasEnLinea(2), 'Una sola acreditación');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones_pasarela WHERE resultado = 'ACREDITADA'"));
    }

    // 5 ----------------------------------------------------------------------------------
    public function testElRetornoDelNavegadorNoAcreditaSaldo(): void
    {
        $r = $this->recarga(2);
        $this->iniciarComo('estudiante2');
        $resp = $this->solicitud('GET', '/mi-saldo/recargas/' . $r['id_recarga'] . '?id=11-1-1&env=test&status=APPROVED');
        $this->assertSame(200, $resp->status);
        $this->assertContains('esperando la confirmación', $resp->body);
        $this->assertSame('0.00', $this->saldo(2), 'Volver a la página de éxito no acredita');
        $this->assertSame('CREADA', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));
        $this->assertSame(0, count($this->http->llamadas), 'Ni siquiera consulta a la pasarela desde el navegador');

        // La conciliación del servidor consulta ese id: si no es de esta recarga, no acredita.
        $this->http->transaccion('11-1-1', 'UPK-DE-OTRA-PERSONA', 'APPROVED', 2000000);
        (new ServicioRecargaLinea($this->db()))->conciliar();
        $this->assertSame('0.00', $this->saldo(2));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones_pasarela WHERE resultado = 'REFERENCIA_DISTINTA'"));

        // Si la pasarela confirma que SÍ es de esta recarga y está aprobada, se acredita (verificación del servidor).
        $this->db()->exec('UPDATE recargas_en_linea SET consultada_en = NULL');
        $this->http->transaccion('11-1-1', $r['referencia'], 'APPROVED', 2000000);
        (new ServicioRecargaLinea($this->db()))->conciliar();
        $this->assertSame('20000.00', $this->saldo(2));
    }

    // 6 ----------------------------------------------------------------------------------
    public function testConSaldoSuficienteSeDescuentaLaTarifaCorrecta(): void
    {
        (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA);
        $this->adelantar(15);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('8000.00', $this->saldo(1), '10.000 - 2.000 (carro estudiante)');
        $this->assertSame(0, count($this->http->llamadas), 'El cobro no contacta ninguna pasarela');
    }

    // 7 ----------------------------------------------------------------------------------
    public function testSinSaldoNoSeIntentaCobrarNingunaTarjeta(): void
    {
        (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('PENDIENTE', $this->valor('SELECT estado FROM cobros_diarios WHERE id_estudiante = 2'));
        $this->assertSame('0.00', $this->saldo(2), 'El saldo nunca queda negativo');
        $this->assertSame(0, count($this->http->llamadas), 'Ninguna llamada a la pasarela');
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM recargas_en_linea'), 'No se crea ninguna recarga sola');
        $aviso = (string) $this->valor("SELECT mensaje FROM notificaciones WHERE id_estudiante = 2 AND tipo = 'PAGO_PENDIENTE'");
        $this->assertContains('recarga en Mi saldo', $aviso, 'El aviso lleva a recargar');
    }

    // 8 ----------------------------------------------------------------------------------
    public function testLaRecargaPosteriorPagaElCobroPendienteUnaSolaVez(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $r = $this->recarga(2, '10000');
        $this->pagar($r, 'APPROVED');
        $this->pagar($r, 'APPROVED', '11-1-1', 1700000300);

        $this->assertSame('PAGADO', $this->valor('SELECT estado FROM cobros_diarios WHERE id_estudiante = 2'));
        $this->assertSame('8000.00', $this->saldo(2), '10.000 - 2.000');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE id_estudiante = 2 AND tipo = 'DEBITO'"));
        $this->assertSame('CERRADA', (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA)['estado'], 'Ya puede salir');
    }

    // 9 y 10 -----------------------------------------------------------------------------
    public function testLaToleranciaDe15MinutosSeConserva(): void
    {
        $antes = (new ServicioEntrada($this->db()))->registrar(1, 'MOTO', self::PORTERIA);
        $this->adelantar(14);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('SALIDA_EN_TOLERANCIA', (new ServicioSalida($this->db()))->autorizar((int) $antes['id_visita'], self::PORTERIA)['liquidacion']);
        $this->assertSame('10000.00', $this->saldo(1), 'Antes de 15 minutos no se cobra');

        (new ServicioEntrada($this->db()))->registrar(1, 'MOTO', self::PORTERIA);
        $this->adelantar(15);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('9000.00', $this->saldo(1), 'Al vencer la tolerancia: tarifa moto estudiante (1.000)');
    }

    // 11 ---------------------------------------------------------------------------------
    public function testDosProcesosSimultaneosNoDuplicanLaAcreditacion(): void
    {
        $r = $this->recarga(2);
        $inicio = microtime(true) + 1.5;
        $handles = $pipes = [];
        for ($i = 0; $i < 4; $i++) {
            $handles[] = proc_open(
                [PHP_BINARY, BASE_PATH . '/tests/Support/concurrente.php', 'recarga', $r['referencia'], '2000000', '11-1-1', (string) $inicio],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$i]
            );
        }
        $resultados = [];
        foreach ($handles as $i => $h) {
            $salida = stream_get_contents($pipes[$i][1]) . stream_get_contents($pipes[$i][2]);
            proc_close($h);
            $resultados[] = json_decode(trim($salida), true) ?? ['ok' => false, 'codigo' => 'SALIDA_INVALIDA: ' . $salida];
        }
        $acreditadas = array_filter($resultados, static fn ($x) => $x['ok']);
        $this->assertSame(1, count($acreditadas), 'Exactamente una acreditación: ' . json_encode($resultados));
        $this->assertSame(3, count(array_filter($resultados, static fn ($x) => $x['codigo'] === 'DUPLICADA')), 'Las demás ven la recarga ya acreditada');
        $this->assertSame('20000.00', $this->saldo(2));
        $this->assertSame(1, $this->recargasEnLinea(2));
    }

    // 12 (y error E1 de la auditoría) ------------------------------------------------------
    public function testAnularUnPagoConSaldoYRecargarDespuesMantieneLaIntegridad(): void
    {
        (new ServicioEntrada($this->db()))->registrar(1, 'MOTO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $pago = $this->fila("SELECT * FROM pagos WHERE medio_pago = 'SALDO'");
        (new ServicioPago($this->db()))->anular((int) $pago['id_pago'], 1, 'Prueba de anulación del débito');
        $this->assertSame('10000.00', $this->saldo(1), 'Reintegro');

        // Antes del arreglo, esta recarga fallaba con un error de base de datos (DEBITO duplicado del cobro).
        $r = (new ServicioSaldo($this->db()))->recargar(1, '5.000', 'EFECTIVO', self::CAJA);
        $this->assertSame(1, count($r['cobros_pagados']), 'El cobro reabierto se vuelve a pagar con el saldo');
        $this->assertSame('14000.00', $this->saldo(1), '10.000 + 5.000 - 1.000');
        $this->assertSame(2, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE id_estudiante = 1 AND tipo = 'DEBITO'"), 'Un débito por cada pago');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM pagos WHERE id_cobro = ? AND estado = 'PAGADO'", [$pago['id_cobro']]), 'Un solo pago válido por cobro');

        // Lo mismo con una recarga en línea.
        $pago2 = $this->fila("SELECT * FROM pagos WHERE medio_pago = 'SALDO' AND estado = 'PAGADO'");
        (new ServicioPago($this->db()))->anular((int) $pago2['id_pago'], 1, 'Segunda anulación de prueba');
        $this->pagar($this->recarga(1, '10000'), 'APPROVED');
        $this->assertSame('PAGADO', $this->valor('SELECT estado FROM cobros_diarios WHERE id_cobro = ?', [$pago['id_cobro']]));
        $this->assertSame('24000.00', $this->saldo(1), '15.000 + 10.000 - 1.000');
        $this->assertSame($this->saldo(1), (string) $this->valor('SELECT saldo_nuevo FROM movimientos_saldo WHERE id_estudiante = 1 ORDER BY id_movimiento DESC LIMIT 1'), 'Saldo = último movimiento del libro');
    }

    // 13 ---------------------------------------------------------------------------------
    public function testElHistorialFinancieroConservaLaTrazabilidad(): void
    {
        $r = $this->recarga(2);
        $this->pagar($r, 'APPROVED');
        foreach (['RECARGA_LINEA_CREADA', 'SALDO_RECARGADO_EN_LINEA'] as $accion) {
            $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM auditoria WHERE accion = ?', [$accion]), 'Auditoría ' . $accion);
        }
        $this->assertSqlFalla(fn () => $this->db()->exec("UPDATE recargas_en_linea SET estado = 'RECHAZADA'"), 'Una recarga aprobada no se modifica');
        $this->assertSqlFalla(fn () => $this->db()->exec('UPDATE notificaciones_pasarela SET resultado = 1'), 'La bitácora no se modifica');
        $this->assertSqlFalla(fn () => \Tests\Support\TestDatabase::admin()->exec('DELETE FROM recargas_en_linea'), 'Ni se borra');
        $this->assertSqlFalla(fn () => $this->db()->exec('UPDATE movimientos_saldo SET monto = 1'), 'El libro no se modifica');
        $this->assertSqlFalla(fn () => $this->db()->exec("UPDATE recargas_en_linea SET monto = 1"), 'Ni el monto de una recarga');
    }

    // 14 ---------------------------------------------------------------------------------
    public function testLaSalidaSigueSiendoRapidaYNoUsaLaPasarela(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $t0 = microtime(true);
        (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA);
        $this->assertTrue(microtime(true) - $t0 < 1.0, 'La salida responde en menos de un segundo');
        $this->assertSame(0, count($this->http->llamadas), 'La salida no llama a la pasarela');
        foreach (['ServicioSalida', 'ServicioCobro', 'ServicioEntrada', 'ServicioAcceso'] as $clase) {
            $codigo = (string) file_get_contents(BASE_PATH . '/app/Services/' . $clase . '.php');
            $this->assertNotContains('Pasarela', $codigo, $clase . ' no depende de la pasarela');
            $this->assertNotContains('curl_', $codigo, $clase . ' no hace llamadas HTTP');
        }
    }

    // Seguridad del webhook y de Mi saldo ------------------------------------------------
    public function testUnWebhookFalsoOConMontoDistintoNoAcredita(): void
    {
        $r = $this->recarga(2);
        [$cuerpo] = PasarelaWompiTest::evento('11-1-1', $r['referencia'], 'APPROVED', 2000000, 1700000000, 'test_events_ADIVINADO');
        $this->assertSame(401, $this->webhook(self::WEBHOOK, $cuerpo)->status, 'Firma inválida');

        $this->http->transaccion('11-1-9', $r['referencia'], 'APPROVED', 100);
        [$cuerpo, $cab] = PasarelaWompiTest::evento('11-1-9', $r['referencia'], 'APPROVED', 100);
        $this->assertSame(200, $this->webhook(self::WEBHOOK, $cuerpo, $cab)->status);
        $this->assertSame('0.00', $this->saldo(2), 'Pagó $1 por una recarga de $20.000: no se acredita');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones_pasarela WHERE resultado = 'MONTO_DISTINTO'"));

        // Webhook firmado que dice APPROVED, pero la consulta al proveedor dice DECLINED: manda la consulta.
        $this->http->transaccion('11-1-8', $r['referencia'], 'DECLINED', 2000000);
        [$cuerpo, $cab] = PasarelaWompiTest::evento('11-1-8', $r['referencia'], 'APPROVED', 2000000);
        $this->webhook(self::WEBHOOK, $cuerpo, $cab);
        $this->assertSame('0.00', $this->saldo(2));

        // Pasarela caída: 503 para que el proveedor reintente, sin acreditar.
        $this->http->fallar('11-1-7');
        [$cuerpo, $cab] = PasarelaWompiTest::evento('11-1-7', $r['referencia'], 'APPROVED', 2000000);
        $this->assertSame(503, $this->webhook(self::WEBHOOK, $cuerpo, $cab)->status);
        $this->assertSame('0.00', $this->saldo(2));
    }

    public function testCadaPersonaSoloVeYCreaSusPropiasRecargas(): void
    {
        $r = $this->recarga(2);
        $this->iniciarComo('estudiante1');
        $this->assertSame(404, $this->solicitud('GET', '/mi-saldo/recargas/' . $r['id_recarga'])->status, 'No ve la recarga de otra persona');
        $this->assertSame(404, $this->solicitud('GET', '/api/mi-saldo/recargas/' . $r['id_recarga'])->status);

        $resp = $this->solicitud('POST', '/mi-saldo/recargar', ['monto' => '30000', 'id_estudiante' => '2']);
        $this->assertSame(303, $resp->status);
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM recargas_en_linea WHERE id_estudiante = 1'), 'La recarga es de quien inició sesión, no del formulario');
        $this->assertRegla('MONTO', fn () => $this->recarga(1, '500'));
        $this->assertRegla('MONTO', fn () => $this->recarga(1, '99999999'));

        $this->iniciarComo('caja');
        $this->assertSame(403, $this->solicitud('GET', '/mi-saldo')->status, 'Caja no tiene cuenta propia');
        $this->iniciarComo('auditor');
        $this->assertSame(200, $this->solicitud('GET', '/reportes?tab=recargas')->status, 'El auditor consulta las recargas');
    }

    public function testLaPaginaDePagoNoExponeSecretosYPermiteSoloElCheckout(): void
    {
        $r = $this->recarga(1);
        $this->iniciarComo('estudiante1');
        $resp = $this->solicitud('GET', '/mi-saldo/recargas/' . $r['id_recarga']);
        $this->assertContains('https://checkout.wompi.co/p/', $resp->body);
        $this->assertContains('signature:integrity', $resp->body);
        $this->assertNotContains('prv_test_', $resp->body, 'Sin llave privada');
        $this->assertNotContains('test_events_', $resp->body, 'Sin secreto de eventos');
        $this->assertNotContains('test_integrity_', $resp->body, 'Sin secreto de integridad');
        $this->assertContains("form-action 'self' https://checkout.wompi.co", $resp->headers['Content-Security-Policy']);
        $this->assertNotContains('checkout.wompi.co', (string) ($this->solicitud('GET', '/mi-saldo')->headers['Content-Security-Policy'] ?? "form-action 'self'"), 'Solo en la página de pago');
    }

    // B1: regreso desde la pasarela sin perder la sesión ------------------------------------
    public function testElRegresoDesdeLaPasarelaNoIniciaSesionYLlevaALaRecarga(): void
    {
        $this->assertTrue(\App\Kernel::sinSesion('GET', '/pago/retorno'), 'El regreso no inicia sesión (no envía Set-Cookie)');
        $this->assertTrue(\App\Kernel::sinSesion('POST', self::WEBHOOK), 'El webhook tampoco');
        $this->assertFalse(\App\Kernel::sinSesion('GET', '/mi-saldo'), 'Las pantallas normales sí usan sesión');

        $r = $this->recarga(2);
        $resp = $this->solicitud('GET', '/pago/retorno?ref=' . $r['referencia'] . '&id=11-1-1');
        $this->assertSame(200, $resp->status);
        $destino = '/mi-saldo/recargas/' . $r['id_recarga'] . '?volvio=1';
        $this->assertContains('http-equiv="refresh" content="1;url=' . $destino . '"', $resp->body, 'Redirige desde el mismo sitio');
        $this->assertNotContains('Santiago', $resp->body, 'Sin datos de la persona');
        $this->assertNotContains('20.000', $resp->body, 'Sin datos de la recarga');
        $this->assertSame([], $_SESSION, 'No se creó ninguna sesión');
        $this->assertSame('11-1-1', $this->valor('SELECT id_transaccion_reportada FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));
        $this->assertSame('CREADA', $this->valor('SELECT estado FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]), 'Volver no cambia el estado');
        $this->assertSame('0.00', $this->saldo(2), 'Volver no acredita');
        $this->assertSame(0, count($this->http->llamadas), 'Ni consulta a la pasarela');

        // Si la pasarela agregara su id con «?» en lugar de «&», también se reconoce.
        $this->solicitud('GET', '/pago/retorno?ref=' . $r['referencia'] . '?id=11-1-5');
        $this->assertSame('11-1-5', $this->valor('SELECT id_transaccion_reportada FROM recargas_en_linea WHERE id_recarga = ?', [$r['id_recarga']]));

        // Referencia desconocida o mal formada: página genérica hacia Mi saldo, sin registrar nada.
        $resp = $this->solicitud('GET', '/pago/retorno?ref=UPK-000000-000000000000&id=x');
        $this->assertContains('url=/mi-saldo"', $resp->body);
        $this->assertSame(200, $this->solicitud('GET', '/pago/retorno?ref=<script>')->status);
    }

    public function testDespuesDeVolverNoSeOfreceDeNuevoElPago(): void
    {
        $r = $this->recarga(1);
        $this->iniciarComo('estudiante1');
        $antes = $this->solicitud('GET', '/mi-saldo/recargas/' . $r['id_recarga'])->body;
        $this->assertContains('https://checkout.wompi.co/p/', $antes, 'Antes de pagar: botón de pago');
        $this->assertContains('/pago/retorno?ref=' . $r['referencia'] . '"', $antes, 'La pasarela devuelve a la página puente');
        $this->solicitud('GET', '/pago/retorno?ref=' . $r['referencia'] . '&id=11-1-1');
        $pagina = $this->solicitud('GET', '/mi-saldo/recargas/' . $r['id_recarga'] . '?volvio=1')->body;
        $this->assertNotContains('https://checkout.wompi.co/p/', $pagina, 'Pago en curso: no se ofrece pagar otra vez');
        $this->assertContains('esperando la confirmación', $pagina);
    }

    public function testElLoginVuelveALaRecargaYSoloARutasInternas(): void
    {
        $r = $this->recarga(1);
        $ruta = 'mi-saldo/recargas/' . $r['id_recarga'];
        $resp = $this->solicitud('GET', '/' . $ruta);
        $this->assertSame('/login?volver=' . rawurlencode($ruta), $resp->headers['Location'] ?? null, 'Sin sesión: login con regreso');
        $this->assertContains('name="volver" value="' . $ruta . '"', $this->solicitud('GET', '/login?volver=' . rawurlencode($ruta))->body);

        $resp = $this->solicitud('POST', '/login', ['login' => 'estudiante1', 'clave' => 'demo2026*', 'volver' => $ruta]);
        $this->assertSame('/' . $ruta, $resp->headers['Location'] ?? null, 'Tras ingresar vuelve a su recarga');

        foreach (['//evil.test/x', 'https://evil.test', '/\\evil.test', 'mi-saldo?x=1', 'logout', 'api/mi-saldo/recargas/1', '..%2F..'] as $malo) {
            $_SESSION = [];
            $resp = $this->solicitud('POST', '/login', ['login' => 'estudiante1', 'clave' => 'demo2026*', 'volver' => $malo]);
            $this->assertSame('/mi-cuenta', $resp->headers['Location'] ?? null, 'Se ignora el destino inseguro ' . $malo);
        }
        $this->assertNull(volver_seguro('MI-SALDO'), 'Solo minúsculas');
        $this->assertSame('mi-saldo/recargas/3', volver_seguro('/mi-saldo/recargas/3'));
    }

    public function testAnularUnaVisitaConCobroDeDiferenciaExigeAnularPrimeroElCobro(): void
    {
        $moto = (new ServicioEntrada($this->db()))->registrar(1, 'MOTO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        (new ServicioSalida($this->db()))->autorizar((int) $moto['id_visita'], self::PORTERIA);
        $carro = (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA);
        $this->adelantar(32);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('DIFERENCIA', $this->valor('SELECT liquidacion FROM visitas WHERE id_visita = ?', [$carro['id_visita']]));
        $this->assertRegla('VISITA_COBRADA', fn () => (new ServicioEntrada($this->db()))->anular((int) $carro['id_visita'], 1, 'Prueba de anulación con diferencia'));
    }
}
