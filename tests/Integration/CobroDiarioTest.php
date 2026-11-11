<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServicioCobro;
use App\Services\ServicioEntrada;
use App\Services\ServicioPago;
use App\Services\ServicioSaldo;
use App\Services\ServicioSalida;
use Tests\Support\TestCase;

/**
 * Cobro diario con tolerancia de 15 minutos, débito del saldo antes de la salida, un solo cobro por
 * persona y día, visitantes sin cobro y salida que solo consulta.
 *
 * Para simular el paso del tiempo se adelanta el reloj de la sesión de MariaDB (SET timestamp):
 * NOW() y CURDATE() de esa conexión avanzan, igual que ocurriría al esperar.
 *
 * Personas de database/seed.sql: 1 Valentina (estudiante, saldo 10.000), 2 Santiago (estudiante, saldo 0),
 * 11 Luciana (profesora, saldo 20.000), 15 Hernán (trabajador, saldo 10.000), 16 Gloria (trabajadora, saldo 0).
 */
final class CobroDiarioTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const PORTERIA = 2;
    private const CAJA = 3;

    private function adelantar(int $minutos): void
    {
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW() + INTERVAL ' . $minutos . ' MINUTE)');
    }

    private function cobros(int $persona): int
    {
        return (int) $this->valor("SELECT COUNT(*) FROM cobros_diarios WHERE id_estudiante = ? AND estado <> 'ANULADO'", [$persona]);
    }

    public function testLaEntradaArrancaLaToleranciaDe15Minutos(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA, 'IA');
        $this->assertSame('EN_TOLERANCIA', $v['liquidacion']);
        $this->assertSame('IA', $v['tipo_origen'], 'Se guarda que el tipo lo definió la IA');
        $this->assertSame(900, strtotime($v['tolerancia_hasta']) - strtotime($v['entrada_en']), '15 minutos exactos');
    }

    public function testSiSaleAntesDe15MinutosNoSeCobra(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(14);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $cerrada = (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA);

        $this->assertSame('CERRADA', $cerrada['estado']);
        $this->assertSame('SALIDA_EN_TOLERANCIA', $cerrada['liquidacion']);
        $this->assertSame(0, $this->cobros(2), 'Sin cobro');
    }

    public function testAlVencerLaToleranciaSeDebitaElSaldoAntesDeLaSalida(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA);
        $this->adelantar(15);
        $r = (new ServicioCobro($this->db()))->procesarVencidos();

        $this->assertSame(1, $r['debitados']);
        $cobro = $this->fila('SELECT * FROM cobros_diarios WHERE id_estudiante = 1');
        $this->assertSame('PAGADO', $cobro['estado'], 'Pagado antes de llegar a la salida');
        $this->assertSame('2000.00', $cobro['valor'], 'Tarifa diaria carro estudiante');
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'), '10.000 - 2.000');
        $pago = $this->fila('SELECT * FROM pagos WHERE id_cobro = ?', [$cobro['id_cobro']]);
        $this->assertSame('SALDO', $pago['medio_pago']);
        $this->assertNull($pago['registrado_por'], 'Débito automático: sin cajero');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE tipo = 'DEBITO' AND id_cobro = ?", [$cobro['id_cobro']]));

        $salida = (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA);
        $this->assertSame('CERRADA', $salida['estado'], 'La salida solo verifica el pago');
    }

    public function testSinSaldoQuedaPendienteSeAvisaYLaSalidaSeDeniegaHastaPagarEnCaja(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(16, 'MOTO', self::PORTERIA);
        $this->adelantar(20);
        (new ServicioCobro($this->db()))->procesarVencidos();

        $cobro = $this->fila('SELECT * FROM cobros_diarios WHERE id_estudiante = 16');
        $this->assertSame('PENDIENTE', $cobro['estado']);
        $this->assertSame('2000.00', $cobro['valor'], 'Moto trabajador');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones WHERE id_cobro = ? AND tipo = 'PAGO_PENDIENTE'", [$cobro['id_cobro']]), 'Aviso al celular');

        $salida = new ServicioSalida($this->db());
        $this->assertRegla('PAGO_PENDIENTE', fn () => $salida->autorizar((int) $v['id_visita'], self::PORTERIA));
        $this->assertSame('EN_CURSO', $this->valor('SELECT estado FROM visitas WHERE id_visita = ?', [$v['id_visita']]));

        (new ServicioPago($this->db()))->registrarCobro((int) $cobro['id_cobro'], 'EFECTIVO', self::CAJA);
        $this->assertSame('CERRADA', $salida->autorizar((int) $v['id_visita'], self::PORTERIA)['estado']);
    }

    /**
     * Al vencer la tolerancia la persona ve un aviso de PAGO (verificado o pendiente), no un veredicto de SALIDA:
     * sigue dentro. «Salida autorizada» o «Salida denegada» solo aparecen cuando escanea su carné para salir.
     */
    public function testAlVencerLaToleranciaSeAvisaElPagoYNoLaSalida(): void
    {
        // Con saldo: Valentina (estudiante1).
        $v = (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA);
        $this->iniciarComo('estudiante1');
        $this->assertSame('En tiempo de tolerancia', $this->estadoCuenta()['titulo']);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();

        $aviso = $this->fila("SELECT titulo, mensaje FROM notificaciones WHERE id_estudiante = 1 AND tipo = 'PAGO_VERIFICADO'");
        $this->assertSame('Pago verificado · Estacionamiento pagado', $aviso['titulo'], 'Aviso al celular al momento del cobro');
        $this->assertContains('continúa registrado dentro del parqueadero', $aviso['mensaje']);
        $this->assertContains('$ 8.000', $aviso['mensaje'], 'Saldo después del débito');
        $hoy = $this->estadoCuenta();
        $this->assertSame('Pago verificado · Estacionamiento pagado', $hoy['titulo']);
        $this->assertNotContains('Salida', $hoy['titulo'] . $hoy['mensaje'], 'Pagar no es salir');
        $this->assertSame('EN_CURSO', $this->valor('SELECT estado FROM visitas WHERE id_visita = ?', [$v['id_visita']]), 'Sigue dentro');

        // Sin saldo: Santiago (estudiante2).
        (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('Pago pendiente · Diríjase a Caja', $this->valor("SELECT titulo FROM notificaciones WHERE id_estudiante = 2 AND tipo = 'PAGO_PENDIENTE'"));
        $this->iniciarComo('estudiante2');
        $hoy = $this->estadoCuenta();
        $this->assertSame('Pago pendiente · Diríjase a Caja', $hoy['titulo']);
        $this->assertSame('err', $hoy['tono']);
        $this->assertNotContains('Salida denegada', $hoy['titulo'] . $hoy['mensaje']);

        // Solo el escaneo de salida da el veredicto de salida.
        $kiosco = (int) $this->valor("SELECT id_usuario FROM usuarios WHERE login = 'acceso1'");
        $acceso = new \App\Services\ServicioAcceso($this->db());
        $sal = $acceso->identificar('1000000001', '', 1, $kiosco);
        $this->assertSame('SALIDA', $sal['tipo']);
        $portero = (int) $this->valor("SELECT id_usuario FROM usuarios WHERE login = 'porteria'");
        $acceso->clasificar($sal['id'], $kiosco, 'CELULAR', 'CARRO', '0.93', '0.02');                 // la IA sugiere
        $this->assertSame('SALIDA_AUTORIZADA', $acceso->resolver($sal['id'], $portero, 'CLASIFICAR', 'CARRO')['resultado']); // el portero confirma
        $sal = $acceso->identificar('1000000002', '', 1, $kiosco);
        $acceso->clasificar($sal['id'], $kiosco, 'CELULAR', 'CARRO', '0.93', '0.02');
        $this->assertSame('SALIDA_DENEGADA', $acceso->resolver($sal['id'], $portero, 'CLASIFICAR', 'CARRO')['resultado']);
    }

    // ------------------------------------------------------------ Estadías de varios días

    /** Reloj de la sesión fijado a hoy a una hora concreta, para que los cambios de día sean deterministas. */
    private function reloj(string $hora): void
    {
        $this->db()->exec("SET timestamp = UNIX_TIMESTAMP(CONCAT(CURDATE(), ' {$hora}'))");
    }

    public function testSiElVehiculoNoSaleElMismoDiaSeCobraElDiaSiguiente(): void
    {
        $this->reloj('18:00:00');
        $v = (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA); // Valentina, saldo 10.000
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'), 'Día 1');

        // Pasa la noche: a las 00:00 del día siguiente se cobra ese día con su saldo.
        $this->adelantar(6 * 60 + 5); // 00:21 del día siguiente
        $r = (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame(1, $r['dias_estadia']);
        $this->assertSame(1, $r['debitados']);
        $this->assertSame('6000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'), 'Día 2 debitado');
        $dia2 = $this->fila('SELECT * FROM cobros_diarios WHERE id_visita = ? ORDER BY fecha DESC LIMIT 1', [$v['id_visita']]);
        $this->assertSame('PAGADO', $dia2['estado']);
        $this->assertSame('DIARIO', $dia2['concepto']);
        $this->assertSame((string) $this->valor('SELECT CURDATE()'), $dia2['fecha'], 'El cobro es del día nuevo');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'COBRO_ESTADIA' AND id_registro = ?", [$dia2['id_cobro']]));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones WHERE id_cobro = ? AND tipo = 'PAGO_VERIFICADO'", [$dia2['id_cobro']]));

        // Idempotente: volver a correr no cobra otra vez el mismo día.
        $this->assertSame(0, (new ServicioCobro($this->db()))->procesarVencidos()['dias_estadia']);
        $this->assertSame(2, $this->cobros(1));

        $salida = (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA);
        $this->assertSame('CERRADA', $salida['estado'], 'Los dos días pagados: sale');
    }

    public function testSinSaldoElDiaSiguienteQuedaPendienteYBloqueaLaSalida(): void
    {
        $this->reloj('17:00:00');
        $v = (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA); // Santiago, saldo 0
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $dia1 = (int) $this->valor('SELECT id_cobro FROM cobros_diarios WHERE id_visita = ?', [$v['id_visita']]);
        (new ServicioPago($this->db()))->registrarCobro($dia1, 'EFECTIVO', self::CAJA); // Pagó el día 1 en Caja

        $this->adelantar(8 * 60); // 01:16 del día siguiente
        $r = (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame(1, $r['pendientes']);
        $dia2 = $this->fila("SELECT * FROM cobros_diarios WHERE id_visita = ? AND fecha = CURDATE()", [$v['id_visita']]);
        $this->assertSame('PENDIENTE', $dia2['estado']);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones WHERE id_cobro = ? AND tipo = 'PAGO_PENDIENTE'", [$dia2['id_cobro']]), 'Aviso al celular');

        $salida = new ServicioSalida($this->db());
        $this->assertRegla('PAGO_PENDIENTE', fn () => $salida->autorizar((int) $v['id_visita'], self::PORTERIA));
        (new ServicioPago($this->db()))->registrarCobro((int) $dia2['id_cobro'], 'EFECTIVO', self::CAJA);
        $this->assertSame('CERRADA', $salida->autorizar((int) $v['id_visita'], self::PORTERIA)['estado']);
    }

    public function testSiElCobroDeLosDiasNoCorrioLaSalidaRegistraUnaIncidenciaYNoCobra(): void
    {
        $this->reloj('20:00:00');
        $v = (new ServicioEntrada($this->db()))->registrar(1, 'MOTO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->adelantar(2 * 24 * 60); // Dos días sin que corra el proceso automático
        $this->assertRegla('COBRO_SIN_PROCESAR', fn () => (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA));
        $this->assertSame(1, $this->cobros(1), 'La salida nunca cobra');

        $this->assertSame(2, (new ServicioCobro($this->db()))->procesarVencidos()['dias_estadia'], 'Se ponen al día los dos días');
        $this->assertSame('CERRADA', (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA)['estado']);
    }

    public function testUnDiaDeEstadiaAnuladoNoSeVuelveACobrar(): void
    {
        $this->reloj('19:00:00');
        $v = (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        $cobro = new ServicioCobro($this->db());
        $cobro->procesarVencidos();
        $this->adelantar(6 * 60);
        $cobro->procesarVencidos();
        foreach ($this->db()->query('SELECT id_cobro FROM cobros_diarios WHERE id_visita = ' . (int) $v['id_visita'])->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $cobro->anular((int) $id, 1, 'Vehículo de mantenimiento autorizado por la administración');
        }
        $this->assertSame(0, $cobro->procesarVencidos()['dias_estadia'], 'El día anulado no se recrea');
        $this->assertSame('CERRADA', (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA)['estado']);
    }

    /** @return array<string,mixed> estado de Mi cuenta (lo que ve la persona en su celular) */
    private function estadoCuenta(): array
    {
        return json_decode($this->solicitud('GET', '/api/mi-cuenta/estado')->body, true);
    }

    public function testUnaPersonaPagaUnaSolaVezPorDiaAunqueEntreYSalgaVariasVeces(): void
    {
        $entrada = new ServicioEntrada($this->db());
        $cobro = new ServicioCobro($this->db());
        $salida = new ServicioSalida($this->db());
        $this->db()->exec("SET timestamp = UNIX_TIMESTAMP(CONCAT(CURDATE(), ' 07:00:00'))");

        $v1 = $entrada->registrar(1, 'CARRO', self::PORTERIA);         // 7:00
        $this->adelantar(180);                                         // 10:00
        $cobro->procesarVencidos();
        $salida->autorizar((int) $v1['id_visita'], self::PORTERIA);
        $this->adelantar(60);                                          // 11:00
        $v2 = $entrada->registrar(1, 'CARRO', self::PORTERIA);
        $this->assertSame('CUBIERTA', $v2['liquidacion'], 'El cobro de hoy ya existe');
        $this->adelantar(60);                                          // 12:00
        $salida->autorizar((int) $v2['id_visita'], self::PORTERIA);
        $this->adelantar(120);                                         // 14:00
        $v3 = $entrada->registrar(1, 'CARRO', self::PORTERIA);
        $this->adelantar(30);
        $cobro->procesarVencidos();

        $this->assertSame(1, $this->cobros(1), 'Un solo cobro en el día');
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'), 'Un solo débito');
        $this->assertSame('CERRADA', $salida->autorizar((int) $v3['id_visita'], self::PORTERIA)['estado']);
    }

    /** Regla aprobada (07/11/2026): el día vale la tarifa más alta usada; al cambiar a una mayor se cobra solo la diferencia. */
    public function testMotoYLuegoAutomovilCobraSoloLaDiferencia(): void
    {
        $entrada = new ServicioEntrada($this->db());
        $salida = new ServicioSalida($this->db());
        $cobro = new ServicioCobro($this->db());
        $this->db()->exec("SET timestamp = UNIX_TIMESTAMP(CONCAT(CURDATE(), ' 07:00:00'))");

        $moto = $entrada->registrar(1, 'MOTO', self::PORTERIA);          // 7:00 en moto
        $this->adelantar(20);
        $cobro->procesarVencidos();                                      // cobro del día: 1.000
        $salida->autorizar((int) $moto['id_visita'], self::PORTERIA);
        $this->adelantar(300);                                           // 12:20 vuelve en automóvil
        $carro = $entrada->registrar(1, 'CARRO', self::PORTERIA);
        $this->assertSame('EN_TOLERANCIA', $carro['liquidacion'], 'Tarifa mayor: espera la tolerancia');
        $this->adelantar(20);
        $r = $cobro->procesarVencidos();
        $this->assertSame(1, $r['debitados']);

        $filas = $this->db()->query("SELECT concepto, valor, estado FROM cobros_diarios WHERE id_estudiante = 1 ORDER BY id_cobro")->fetchAll();
        $this->assertSame(2, count($filas));
        $this->assertSame(['DIARIO', '1000.00', 'PAGADO'], array_values($filas[0]));
        $this->assertSame(['DIFERENCIA', '1000.00', 'PAGADO'], array_values($filas[1]), 'Solo la diferencia, no un segundo cobro completo');
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'), 'Total del día 2.000 = tarifa del carro');
        $this->assertSame('DIFERENCIA', $this->valor('SELECT liquidacion FROM visitas WHERE id_visita = ?', [$carro['id_visita']]));
        $this->assertSame('CERRADA', $salida->autorizar((int) $carro['id_visita'], self::PORTERIA)['estado']);

        // Otra visita en carro ese día ya está cubierta (2.000 pagados).
        $otro = $entrada->registrar(1, 'CARRO', self::PORTERIA);
        $this->assertSame('CUBIERTA', $otro['liquidacion']);
        $this->adelantar(30);
        $cobro->procesarVencidos();
        $this->assertSame(2, $this->cobros(1), 'Sin más cobros');
    }

    public function testAutomovilYLuegoMotoNoCobraNadaMas(): void
    {
        $entrada = new ServicioEntrada($this->db());
        $salida = new ServicioSalida($this->db());
        $this->db()->exec("SET timestamp = UNIX_TIMESTAMP(CONCAT(CURDATE(), ' 07:00:00'))");
        $carro = $entrada->registrar(1, 'CARRO', self::PORTERIA);
        $this->adelantar(20);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $salida->autorizar((int) $carro['id_visita'], self::PORTERIA);
        $moto = $entrada->registrar(1, 'MOTO', self::PORTERIA);
        $this->assertSame('CUBIERTA', $moto['liquidacion'], 'La moto (1.000) ya está cubierta por el carro (2.000)');
        $this->assertSame(1, $this->cobros(1));
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'));
    }

    public function testDiferenciaSinSaldoQuedaPendienteYLaSalidaSeDeniega(): void
    {
        $entrada = new ServicioEntrada($this->db());
        $salida = new ServicioSalida($this->db());
        $this->db()->exec("SET timestamp = UNIX_TIMESTAMP(CONCAT(CURDATE(), ' 07:00:00'))");
        // Mariana (estudiante3) tiene 1.500: alcanza la moto (1.000) pero no la diferencia (1.000 con 500 de saldo).
        $moto = $entrada->registrar(3, 'MOTO', self::PORTERIA);
        $this->adelantar(20);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $salida->autorizar((int) $moto['id_visita'], self::PORTERIA);
        $carro = $entrada->registrar(3, 'CARRO', self::PORTERIA);
        $this->adelantar(20);
        (new ServicioCobro($this->db()))->procesarVencidos();

        $dif = $this->fila("SELECT * FROM cobros_diarios WHERE id_estudiante = 3 AND concepto = 'DIFERENCIA'");
        $this->assertSame('PENDIENTE', $dif['estado']);
        $this->assertSame('1000.00', $dif['valor']);
        $this->assertContains('tarifa mayor', (string) $this->valor('SELECT mensaje FROM notificaciones WHERE id_cobro = ?', [$dif['id_cobro']]));
        $this->assertRegla('PAGO_PENDIENTE', fn () => $salida->autorizar((int) $carro['id_visita'], self::PORTERIA));
        $this->assertSqlFalla(fn () => $this->db()->exec(
            "INSERT INTO cobros_diarios (id_estudiante, fecha, concepto, id_visita, tipo_vehiculo, categoria, id_tarifa, valor)
             VALUES (3, CURDATE(), 'DIFERENCIA', {$carro['id_visita']}, 'CARRO', 'ESTUDIANTE', 7, 1000)"
        ), 'Una sola diferencia por visita');
    }
    public function testElDiaSiguienteSeCobraDeNuevo(): void
    {
        $entrada = new ServicioEntrada($this->db());
        $this->db()->exec("SET timestamp = UNIX_TIMESTAMP(CONCAT(CURDATE(), ' 08:00:00'))");
        $v = $entrada->registrar(11, 'CARRO', self::PORTERIA);
        $this->adelantar(60);
        (new ServicioCobro($this->db()))->procesarVencidos();
        (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA);

        $this->adelantar(24 * 60);
        $entrada->registrar(11, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame(2, $this->cobros(11), 'Un cobro por cada día');
        $this->assertSame('14000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 11'), '20.000 - 3.000 - 3.000 (carro profesor)');
    }

    public function testLaBaseImpideDosCobrosVigentesDeLaMismaPersonaElMismoDia(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(2, 'MOTO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame(1, $this->cobros(2), 'Procesar de nuevo no duplica');

        $e = $this->assertSqlFalla(fn () => $this->db()->exec(
            "INSERT INTO cobros_diarios (id_estudiante, fecha, id_visita, tipo_vehiculo, categoria, id_tarifa, valor)
             SELECT 2, CURDATE(), {$v['id_visita']}, 'MOTO', 'ESTUDIANTE', 8, 1000"
        ), 'El índice único cobro_vigente debía impedir el segundo cobro del día');
        $this->assertSame(1062, (int) $e->errorInfo[1]);
    }

    public function testVisitantesNoPaganYPuedenTenerVariasVisitasAbiertas(): void
    {
        $entrada = new ServicioEntrada($this->db());
        $a = $entrada->registrarVisitante('79555111', 'Carlos Visitante', 'CARRO', self::PORTERIA);
        $b = $entrada->registrarVisitante('79555111', '', 'MOTO', self::PORTERIA);

        $this->assertSame('VISITANTE', $a['categoria'], 'Un documento no registrado es visitante');
        $this->assertSame('NO_APLICA', $a['liquidacion']);
        $this->assertNull($a['tarifa_aplicada']);
        $this->assertSame(2, (int) $this->valor("SELECT COUNT(*) FROM visitas WHERE id_estudiante = ? AND estado = 'EN_CURSO'", [$a['id_estudiante']]), 'Sin restricción por visita abierta');
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'ENTRADA_RECHAZADA'"), 'Sin advertencias');

        $this->adelantar(120);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame(0, $this->cobros((int) $a['id_estudiante']), 'Los visitantes no generan cobros');
        $this->assertSame('CERRADA', (new ServicioSalida($this->db()))->autorizar((int) $b['id_visita'], self::PORTERIA)['estado']);
    }

    public function testBicicletaSinTarifaYPatinetaDeshabilitada(): void
    {
        $entrada = new ServicioEntrada($this->db());
        $v = $entrada->registrar(2, 'BICICLETA', self::PORTERIA, 'IA');
        $this->assertSame('NO_APLICA', $v['liquidacion']);
        $this->assertNull($v['id_tarifa'], 'No se inventa una tarifa');
        $this->adelantar(90);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame(0, $this->cobros(2));
        $this->assertSame('CERRADA', (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA)['estado']);

        // Especificación del 08/11/2026: solo automóvil, motocicleta y bicicleta. La patineta sigue en la base
        // de datos pero está deshabilitada en config/app.php (tipos_habilitados): no se registra.
        $this->assertRegla('TIPO_INVALIDO', fn () => $entrada->registrar(2, 'PATINETA', self::PORTERIA));
    }

    public function testLaSalidaNuncaCobraSiElProcesoNoCorrioRegistraUnaIncidencia(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(15, 'CARRO', self::PORTERIA);
        $this->adelantar(40); // Nadie ejecutó el cron ni hubo pantallas abiertas.

        $salida = new ServicioSalida($this->db());
        $this->assertRegla('COBRO_SIN_PROCESAR', fn () => $salida->autorizar((int) $v['id_visita'], self::PORTERIA));
        $this->assertSame(0, $this->cobros(15), 'La salida no generó cobros');
        $this->assertSame('10000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 15'), 'La salida no debitó');
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM pagos WHERE id_cobro IS NOT NULL'), 'La salida no creó pagos');
        $this->assertSame('EN_CURSO', $this->valor('SELECT estado FROM visitas WHERE id_visita = ?', [$v['id_visita']]));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'SALIDA_INCIDENCIA' AND id_registro = ?", [$v['id_visita']]));
        $this->assertSame(1, count((new \App\Repositories\VisitaRepository($this->db()))->incidenciasDeCobro()), 'Aparece en incidencias de Caja y Portería');

        // Caja o Portería la resuelven aparte; luego la salida vuelve a ser solo una consulta.
        (new ServicioCobro($this->db()))->liquidar((int) $v['id_visita']);
        $this->assertSame('7000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 15'), '10.000 - 3.000');
        $this->assertSame(0, count((new \App\Repositories\VisitaRepository($this->db()))->incidenciasDeCobro()));
        $this->assertSame('CERRADA', $salida->autorizar((int) $v['id_visita'], self::PORTERIA)['estado']);
    }

    /** Visita cuya tolerancia venció hace 5 minutos, insertada como la dejaría una entrada de hace 20 minutos. */
    private function visitaVencida(int $persona, int $idTarifa, string $tipo, string $categoria, string $valor): int
    {
        $this->db()->exec("INSERT INTO visitas (id_estudiante, tipo_vehiculo, categoria, id_tarifa, tarifa_aplicada, entrada_en, entrada_por, tolerancia_hasta, liquidacion)
                           VALUES ($persona, '$tipo', '$categoria', $idTarifa, $valor, NOW() - INTERVAL 20 MINUTE, 2, NOW() - INTERVAL 5 MINUTE, 'EN_TOLERANCIA')");
        return (int) $this->db()->lastInsertId();
    }

    public function testElCronEjecutadoVariasVecesALaVezNoDuplicaCobrosNiDebitos(): void
    {
        $this->visitaVencida(1, 7, 'CARRO', 'ESTUDIANTE', '2000');
        $this->visitaVencida(11, 10, 'MOTO', 'PROFESOR', '2000');
        $this->visitaVencida(16, 11, 'CARRO', 'TRABAJADOR', '3000');

        // Cinco ejecuciones simultáneas de scripts/cobros.php, como si el cron se solapara.
        $procesos = [];
        $tubos = [];
        for ($i = 0; $i < 5; $i++) {
            $procesos[$i] = proc_open([PHP_BINARY, BASE_PATH . '/scripts/cobros.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos[$i]);
        }
        $salidas = [];
        foreach ($procesos as $i => $p) {
            $salidas[] = trim(stream_get_contents($tubos[$i][1]) . stream_get_contents($tubos[$i][2]));
            $this->assertSame(0, proc_close($p), 'El script termina sin error: ' . end($salidas));
        }

        $this->assertSame(3, (int) $this->valor('SELECT COUNT(*) FROM cobros_diarios'), 'Un cobro por persona: ' . implode(' | ', $salidas));
        $this->assertSame(2, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE tipo = 'DEBITO'"), 'Dos personas con saldo');
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'));
        $this->assertSame('18000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 11'));
        $this->assertSame('PENDIENTE', $this->valor('SELECT estado FROM cobros_diarios WHERE id_estudiante = 16'), 'Sin saldo: pendiente');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM notificaciones WHERE id_estudiante = 16"), 'Un solo aviso');
    }

    public function testElCobroUsaLaHoraYFechaDeColombia(): void
    {
        $this->assertSame('America/Bogota', date_default_timezone_get(), 'PHP');
        $this->assertSame('-05:00', $this->valor('SELECT @@session.time_zone'), 'MySQL (Colombia no tiene horario de verano)');
        $this->assertTrue(abs(strtotime((string) $this->valor('SELECT NOW()')) - time()) < 5, 'PHP y MySQL marcan la misma hora');

        // A las 11:50 p. m. hora de Colombia (04:50 UTC del día siguiente) el cobro es del día colombiano.
        $this->db()->exec("SET timestamp = UNIX_TIMESTAMP(CONCAT(CURDATE(), ' 23:50:00'))");
        $hoy = (string) $this->valor('SELECT CURDATE()');
        $v = (new ServicioEntrada($this->db()))->registrar(1, 'CARRO', self::PORTERIA);
        $this->adelantar(20); // 00:10 del día siguiente
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame($hoy, $this->valor('SELECT fecha FROM cobros_diarios WHERE id_visita = ?', [$v['id_visita']]), 'Fecha de la entrada en Colombia');
    }

    public function testLaRecargaPagaLosCobrosPendientes(): void
    {
        $v = (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('PENDIENTE', $this->valor('SELECT estado FROM cobros_diarios WHERE id_estudiante = 2'));

        $r = (new ServicioSaldo($this->db()))->recargar(2, '5.000', 'EFECTIVO', self::CAJA);
        $this->assertSame(1, count($r['cobros_pagados']));
        $this->assertSame('3000.00', $r['saldo'], '5.000 - 2.000');
        $this->assertSame('PAGADO', $this->valor('SELECT estado FROM cobros_diarios WHERE id_estudiante = 2'));
        $this->assertSame('CERRADA', (new ServicioSalida($this->db()))->autorizar((int) $v['id_visita'], self::PORTERIA)['estado']);
        $this->assertRegla('MONTO', fn () => (new ServicioSaldo($this->db()))->recargar(2, '0', 'EFECTIVO', self::CAJA));
    }

    public function testAnularUnDebitoReintegraElSaldoYDejaElCobroPendiente(): void
    {
        (new ServicioEntrada($this->db()))->registrar(1, 'MOTO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $pago = $this->fila("SELECT * FROM pagos WHERE medio_pago = 'SALDO'");
        $this->assertSame('9000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'));

        (new ServicioPago($this->db()))->anular((int) $pago['id_pago'], 1, 'Vehículo mal clasificado por la cámara');
        $this->assertSame('10000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'), 'Saldo reintegrado');
        $this->assertSame('PENDIENTE', $this->valor('SELECT estado FROM cobros_diarios WHERE id_cobro = ?', [$pago['id_cobro']]));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM movimientos_saldo WHERE tipo = 'REINTEGRO'"));

        (new ServicioCobro($this->db()))->anular((int) $pago['id_cobro'], 1, 'Era bicicleta, no motocicleta');
        $this->assertSame('ANULADO', $this->valor('SELECT estado FROM cobros_diarios WHERE id_cobro = ?', [$pago['id_cobro']]));
    }

    public function testElLibroDeSaldosNoSeModificaNiSeBorra(): void
    {
        $this->assertSqlFalla(fn () => $this->db()->exec('UPDATE movimientos_saldo SET monto = 1'), 'Los movimientos no se modifican');
        $this->assertSqlFalla(fn () => \Tests\Support\TestDatabase::admin()->exec('DELETE FROM movimientos_saldo'), 'Ni con la cuenta administrativa');
        $this->assertSqlFalla(fn () => $this->db()->exec('UPDATE estudiantes SET saldo = -1 WHERE id_estudiante = 1'), 'El saldo no puede ser negativo');
    }
}
