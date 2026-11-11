<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServicioAcceso;
use App\Services\ServicioCobro;
use Tests\Support\TestCase;

/**
 * Flujo definitivo del punto de acceso: identidad → vehículo (IA) → confirmación → visita.
 * Todas son pruebas SIMULADAS de servidor: la "IA" se representa con el resultado que enviaría el
 * navegador. No prueban la cámara ni el modelo con imágenes reales (eso requiere hardware).
 *
 * Personas de seed.sql: 1 Valentina (estudiante, saldo 10.000), 2 Santiago (estudiante, saldo 0),
 * 6 Samuel (profesor, visita LEGADO abierta en carro y pagada), 12 Matías (inactivo).
 */
final class AccesoTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private function usuario(string $login): int
    {
        return (int) $this->valor('SELECT id_usuario FROM usuarios WHERE login = ?', [$login]);
    }

    private function servicio(): ServicioAcceso
    {
        return new ServicioAcceso($this->db());
    }

    /**
     * Clasificación en el punto de acceso + lo que hace el portero (regla de Portería, 10/11/2026): si la IA dio una
     * sugerencia confiable, el portero la CONFIRMA. Una detección no confiable queda pendiente, igual que antes.
     */
    private function clasificarConPortero(int $id, int $kiosco, string $fuente, string $tipo, mixed $confianza, mixed $segunda, bool $final = false): array
    {
        $r = $this->servicio()->clasificar($id, $kiosco, $fuente, $tipo, $confianza, $segunda, $final);
        if ($r['estado'] === 'PENDIENTE_PORTERO' && ($r['por_confirmar'] ?? null) !== null) {
            $sugerido = (string) $this->valor('SELECT tipo_detectado FROM eventos_acceso WHERE id_evento = ?', [$id]);
            $r = $this->servicio()->resolver($id, $this->usuario('porteria'), 'CLASIFICAR', $sugerido);
        }
        return $r;
    }

    /** PDF417 de cédula amarilla con el número en las posiciones 48-57. */
    private static function cedula(string $numero): string
    {
        return str_pad('0349876543PubDSK_1', 48, ' ') . str_pad($numero, 10, '0', STR_PAD_LEFT)
            . str_pad('RIOS', 23) . str_pad('CARDONA', 23) . str_pad('VALENTINA', 23) . str_pad('', 23)
            . '0F20000101' . str_repeat(' ', 40);
    }

    // ------------------------------------------------------------ Identidad

    public function testIdentificacionConCarneYConCedula(): void
    {
        $kiosco = $this->usuario('acceso1');
        $ev = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $this->assertSame('EN_CAPTURA', $ev['estado']);
        $this->assertSame('ENTRADA', $ev['tipo']);
        $this->assertSame('••••••0001', $ev['persona']['documento'], 'El punto de acceso solo ve el documento enmascarado');

        // Cédula (PDF417 crudo): el servidor también extrae el número. Mismo evento: no se duplica.
        $ev2 = $this->servicio()->identificar(self::cedula('1000000001'), '', 1, $kiosco);
        $this->assertSame($ev['id'], $ev2['id'], 'Reintento del mismo escaneo: el mismo evento');
    }

    public function testPersonaNoRegistradaQuedaParaElPorteroSinDuplicarAvisos(): void
    {
        $kiosco = $this->usuario('acceso1');
        $a = $this->servicio()->identificar('79001122', 'Ana Visitante', 1, $kiosco);
        $b = $this->servicio()->identificar('79001122', '', 1, $kiosco);
        $this->assertSame('PENDIENTE_PORTERO', $a['estado']);
        $this->assertSame('IDENTIDAD_NO_REGISTRADA', $a['motivo']);
        $this->assertSame($a['id'], $b['id'], 'Un mismo aviso no se duplica');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM eventos_acceso WHERE estado = 'PENDIENTE_PORTERO'"));
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM estudiantes WHERE documento = '79001122'"), 'No entra solo como visitante');

        // El portero lo admite como visitante en moto.
        $r = $this->servicio()->resolver($a['id'], $this->usuario('porteria'), 'ADMITIR', 'MOTO');
        $this->assertSame('CONFIRMADO', $r['estado']);
        $this->assertSame('VISITANTE_ADMITIDO', $r['resultado']);
        $this->assertSame('VISITANTE', $this->valor("SELECT categoria FROM estudiantes WHERE documento = '79001122'"));
    }

    public function testPersonaDesautorizadaSeRechazaConMotivo(): void
    {
        $ev = $this->servicio()->identificar('1000000012', '', 1, $this->usuario('acceso1'));
        $this->assertSame('RECHAZADO', $ev['estado']);
        $this->assertSame('PERSONA_NO_AUTORIZADA', $ev['motivo']);
        $this->assertContains('no está autorizada', $ev['mensaje']);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_estudiante = 12'));
    }

    // ------------------------------------------------------------ Vehículo e IA

    public function testClasificacionConfiableRegistraLaEntradaConTarifaDelServidor(): void
    {
        $kiosco = $this->usuario('acceso1');
        $ev = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $r = $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', 'CARRO', '0.93', '0.02');

        $this->assertSame('CONFIRMADO', $r['estado']);
        $this->assertSame('ENTRADA_REGISTRADA', $r['resultado']);
        $fila = $this->fila('SELECT * FROM eventos_acceso WHERE id_evento = ?', [$ev['id']]);
        $this->assertSame('IA', $fila['metodo']);
        $this->assertSame('0.9300', $fila['confianza'], 'Se guarda la confianza del modelo');
        $visita = $this->fila('SELECT * FROM visitas WHERE id_visita = ?', [$fila['id_visita']]);
        $this->assertSame('CARRO', $visita['tipo_vehiculo']);
        $this->assertSame('IA', $visita['tipo_origen']);
        $this->assertSame('2000.00', $visita['tarifa_aplicada'], 'Tarifa calculada por el servidor');
        $this->assertSame((string) $ev['id'], $visita['id_evento_entrada'], 'La visita conserva su evento de ingreso');
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM eventos_acceso WHERE estado = 'PENDIENTE_PORTERO'"), 'Confiable: sin aviso al portero');
    }

    public function testClasificacionDeMotoYBicicleta(): void
    {
        $kiosco = $this->usuario('acceso1');
        foreach (['1000000002' => 'MOTO', '1000000003' => 'BICICLETA'] as $doc => $tipo) {
            $ev = $this->servicio()->identificar((string) $doc, '', 1, $kiosco);
            $r = $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', $tipo, '0.91', '0.05');
            $this->assertSame('ENTRADA_REGISTRADA', $r['resultado'], $tipo);
            $this->assertSame($tipo, $this->valor('SELECT tipo_vehiculo FROM visitas WHERE id_evento_entrada = ?', [$ev['id']]));
        }
    }

    public function testBajaConfianzaMargenCortoOTipoDeshabilitadoPasanAlPortero(): void
    {
        $kiosco = $this->usuario('acceso1');
        $casos = [
            ['1000000001', 'CARRO', '0.60', '0.10'],     // confianza baja
            ['1000000002', 'MOTO', '0.85', '0.70'],      // margen corto (dos clases parecidas)
            ['1000000003', 'PATINETA', '0.99', '0.00'],  // tipo deshabilitado
            ['1000000004', 'AVION', '0.99', '0.00'],     // fuera del catálogo
        ];
        foreach ($casos as [$doc, $tipo, $c, $s]) {
            $ev = $this->servicio()->identificar($doc, '', 1, $kiosco);
            $r = $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', $tipo, $c, $s, true);
            $this->assertSame('PENDIENTE_PORTERO', $r['estado'], "$tipo $c/$s");
            $this->assertSame('VEHICULO_NO_CLASIFICADO', $r['motivo']);
        }
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_evento_entrada IS NOT NULL'), 'Nada se registra como definitivo');
        $this->assertSame(4, count((new \App\Repositories\EventoRepository($this->db()))->pendientes()));
    }

    public function testElPorteroResuelveYQuedaAuditado(): void
    {
        $kiosco = $this->usuario('acceso1');
        $portero = $this->usuario('porteria');
        $ev = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', 'DESCONOCIDO', '0', '0', true);

        $r = $this->servicio()->resolver($ev['id'], $portero, 'CLASIFICAR', 'MOTO');
        $this->assertSame('CONFIRMADO', $r['estado']);
        $this->assertSame('MANUAL', $r['metodo']);
        $fila = $this->fila('SELECT * FROM eventos_acceso WHERE id_evento = ?', [$ev['id']]);
        $this->assertSame((string) $portero, $fila['resuelto_por'], 'Identificador del portero');
        $this->assertSame('MOTO', $this->valor('SELECT tipo_vehiculo FROM visitas WHERE id_evento_entrada = ?', [$ev['id']]));
        $this->assertSame('1000.00', $this->valor('SELECT tarifa_aplicada FROM visitas WHERE id_evento_entrada = ?', [$ev['id']]));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'ACCESO_RESUELTO_MANUAL' AND id_registro = ? AND id_usuario = ?", [$ev['id'], $portero]));

        $otra = $this->servicio()->resolver($ev['id'], $portero, 'CLASIFICAR', 'CARRO');
        $this->assertTrue($otra['ya_resuelto'] ?? false, 'Un segundo intento no cambia nada');
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_evento_entrada = ?', [$ev['id']]));
    }

    public function testLosReintentosNoDuplicanEntradas(): void
    {
        $kiosco = $this->usuario('acceso1');
        $ev = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', 'CARRO', '0.93', '0.02');
        $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', 'MOTO', '0.99', '0.00');
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_estudiante = 1 AND estado = \'EN_CURSO\''));
        $this->assertSame('CARRO', $this->valor('SELECT tipo_vehiculo FROM visitas WHERE id_estudiante = 1 AND estado = \'EN_CURSO\''));
    }

    // ------------------------------------------------------------ Salida

    private function entrar(string $doc, string $tipo): array
    {
        $kiosco = $this->usuario('acceso1');
        $ev = $this->servicio()->identificar($doc, '', 1, $kiosco);
        return $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', $tipo, '0.93', '0.02');
    }

    public function testSalidaConElMismoVehiculoAntesDe15MinutosSinCobro(): void
    {
        $this->entrar('1000000002', 'CARRO');
        $kiosco = $this->usuario('acceso1');
        $sal = $this->servicio()->identificar('1000000002', '', 1, $kiosco);
        $this->assertSame('SALIDA', $sal['tipo']);
        $r = $this->clasificarConPortero($sal['id'], $kiosco, 'CELULAR', 'CARRO', '0.90', '0.05');
        $this->assertSame('SALIDA_AUTORIZADA', $r['resultado']);
        $v = $this->fila('SELECT * FROM visitas WHERE id_estudiante = 2 ORDER BY id_visita DESC LIMIT 1');
        $this->assertSame('CERRADA', $v['estado']);
        $this->assertSame('SALIDA_EN_TOLERANCIA', $v['liquidacion']);
        $this->assertSame((string) $sal['id'], $v['id_evento_salida']);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM cobros_diarios WHERE id_estudiante = 2'));
    }

    public function testPermanenciaMayorA15MinutosCobraYLaSalidaSoloVerifica(): void
    {
        $this->entrar('1000000001', 'CARRO');
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW() + INTERVAL 30 MINUTE)');
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame('PAGADO', $this->valor('SELECT estado FROM cobros_diarios WHERE id_estudiante = 1'));
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'));

        $kiosco = $this->usuario('acceso1');
        $sal = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $r = $this->clasificarConPortero($sal['id'], $kiosco, 'CELULAR', 'CARRO', '0.90', '0.05');
        $this->assertSame('SALIDA_AUTORIZADA', $r['resultado']);
        $this->assertSame('8000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 1'), 'La salida no debitó nada');
    }

    public function testSaldoInsuficienteDejaPendienteYLaSalidaSeDeniega(): void
    {
        $this->entrar('1000000002', 'MOTO'); // saldo 0
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW() + INTERVAL 20 MINUTE)');
        (new ServicioCobro($this->db()))->procesarVencidos();
        $kiosco = $this->usuario('acceso1');
        $sal = $this->servicio()->identificar('1000000002', '', 1, $kiosco);
        $r = $this->clasificarConPortero($sal['id'], $kiosco, 'CELULAR', 'MOTO', '0.90', '0.05');
        $this->assertSame('SALIDA_DENEGADA', $r['resultado']);
        $this->assertSame('EN_CURSO', $this->valor('SELECT estado FROM visitas WHERE id_estudiante = 2 ORDER BY id_visita DESC LIMIT 1'));
    }

    public function testSalidaConVehiculoDiferenteNoCierraElEstacionamiento(): void
    {
        $this->entrar('1000000001', 'CARRO');
        $kiosco = $this->usuario('acceso1');
        $sal = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $r = $this->servicio()->clasificar($sal['id'], $kiosco, 'CELULAR', 'MOTO', '0.95', '0.01');
        $this->assertSame('PENDIENTE_PORTERO', $r['estado'], 'La IA sugiere moto: el portero confirma');
        $this->assertSame('Motocicleta', $r['por_confirmar']);
        $this->assertSame('EN_CURSO', $this->valor("SELECT estado FROM visitas WHERE id_estudiante = 1 AND estado = 'EN_CURSO'"));

        // El portero confirma que de verdad es una moto: no coincide, la visita sigue abierta.
        $p = $this->servicio()->resolver($sal['id'], $this->usuario('porteria'), 'CLASIFICAR', 'MOTO');
        $this->assertSame('RECHAZADO', $p['estado']);
        $this->assertSame('VEHICULO_NO_COINCIDE', $p['resultado']);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM visitas WHERE id_estudiante = 1 AND estado = 'EN_CURSO'"));
    }

    public function testSalidaSinDeteccionQuedaParaElPortero(): void
    {
        $this->entrar('1000000001', 'CARRO');
        $kiosco = $this->usuario('acceso1');
        $sal = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $r = $this->clasificarConPortero($sal['id'], $kiosco, 'CELULAR', 'DESCONOCIDO', '0', '0', true);
        $this->assertSame('PENDIENTE_PORTERO', $r['estado']);
        $ok = $this->servicio()->resolver($sal['id'], $this->usuario('porteria'), 'CLASIFICAR', 'CARRO');
        $this->assertSame('SALIDA_AUTORIZADA', $ok['resultado'], 'El portero verifica el carro y la salida se consulta');
    }

    // ------------------------------------------------------------ Concurrencia

    public function testDosPersonasEnElMismoPuntoNoComparteVehiculo(): void
    {
        $kiosco = $this->usuario('acceso1');
        $a = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $b = $this->servicio()->identificar('1000000002', '', 1, $kiosco);
        $this->assertSame('EXPIRADO', $this->valor('SELECT estado FROM eventos_acceso WHERE id_evento = ?', [$a['id']]), 'La captura anterior se reemplaza');
        $tarde = $this->clasificarConPortero($a['id'], $kiosco, 'CELULAR', 'CARRO', '0.95', '0.01');
        $this->assertSame('EXPIRADO', $tarde['estado'], 'El vehículo no se asocia a la persona anterior');
        $this->clasificarConPortero($b['id'], $kiosco, 'CELULAR', 'MOTO', '0.95', '0.01');
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM visitas WHERE id_estudiante = 1 AND estado = 'EN_CURSO'"));
        $this->assertSame('MOTO', $this->valor("SELECT tipo_vehiculo FROM visitas WHERE id_estudiante = 2 AND estado = 'EN_CURSO'"));
    }

    public function testDosPuntosDeAccesoSimultaneos(): void
    {
        \Tests\Support\TestDatabase::admin()->exec("INSERT INTO puestos_camara (id_puesto, nombre) VALUES (2, 'Salida norte')");
        $k1 = $this->usuario('acceso1');
        $k2 = $this->usuario('acceso2');
        $a = $this->servicio()->identificar('1000000001', '', 1, $k1);
        $b = $this->servicio()->identificar('1000000002', '', 2, $k2);
        $this->assertSame('EN_CAPTURA', $this->valor('SELECT estado FROM eventos_acceso WHERE id_evento = ?', [$a['id']]));
        $this->assertSame('EN_CAPTURA', $this->valor('SELECT estado FROM eventos_acceso WHERE id_evento = ?', [$b['id']]));
        // Un punto de acceso no puede clasificar el evento de otro.
        $this->assertRegla('AJENO', fn () => $this->clasificarConPortero($a['id'], $k2, 'CELULAR', 'CARRO', '0.95', '0.01'));
    }

    public function testCamaraDelPuestoSoloSirveSiEsPosteriorAlEscaneo(): void
    {
        $k1 = $this->usuario('acceso1');
        $cam = $this->usuario('acceso2');
        (new \App\Repositories\PuestoCamaraRepository($this->db()))->actualizar(1, 'MOTO', 0.97, $cam, 0.01); // del vehículo anterior
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW(3) + INTERVAL 1 SECOND)');
        $ev = $this->servicio()->identificar('1000000001', '', 1, $k1);
        $espera = $this->clasificarConPortero($ev['id'], $k1, 'PUESTO', '', null, null);
        $this->assertTrue($espera['esperando'] ?? false, 'La detección anterior al escaneo no se usa');
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW(3) + INTERVAL 1 SECOND)');
        (new \App\Repositories\PuestoCamaraRepository($this->db()))->actualizar(1, 'CARRO', 0.94, $cam, 0.02);
        $r = $this->clasificarConPortero($ev['id'], $k1, 'PUESTO', 'MOTO', '0.99', '0'); // lo que diga el navegador se ignora
        $this->assertSame('ENTRADA_REGISTRADA', $r['resultado']);
        $this->assertSame('CARRO', $this->valor('SELECT tipo_vehiculo FROM visitas WHERE id_evento_entrada = ?', [$ev['id']]));
    }

    public function testEventosSinAtenderExpiranSinRegistrarNada(): void
    {
        $ev = $this->servicio()->identificar('79001199', '', 1, $this->usuario('acceso1'));
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW() + INTERVAL 11 MINUTE)');
        $this->assertSame(1, $this->servicio()->expirarVencidos());
        $this->assertSame('EXPIRADO', $this->valor('SELECT estado FROM eventos_acceso WHERE id_evento = ?', [$ev['id']]));
        $this->assertSqlFalla(fn () => $this->db()->exec("UPDATE eventos_acceso SET estado = 'CONFIRMADO' WHERE id_evento = {$ev['id']}"), 'Un evento cerrado no se modifica');
        $this->assertSqlFalla(fn () => \Tests\Support\TestDatabase::admin()->exec('DELETE FROM eventos_acceso'), 'Los eventos no se borran');
    }

    // ------------------------------------------------------------ Resultado detallado de entrada y salida

    public function testElResultadoMuestraElDetalleDeEntradaYSalidaConDeuda(): void
    {
        $kiosco = $this->usuario('acceso1');
        $ev = $this->servicio()->identificar('1000000002', '', 1, $kiosco); // Santiago, saldo $0
        $r = $this->clasificarConPortero($ev['id'], $kiosco, 'CELULAR', 'CARRO', '0.93', '0.03');
        $det = array_column($r['detalle'], 1, 0);
        $this->assertSame('ENTRADA_REGISTRADA', $r['resultado']);
        $this->assertContains('••0002', $det['Persona'], 'Documento enmascarado');
        $this->assertSame('Automóvil · IA · confirmado por Portería', $det['Vehículo']);
        $this->assertSame('En curso', $det['Visita']);
        $this->assertContains('15 minutos de tolerancia', $det['Sin cobro hasta']);

        // Pasan 16 minutos: el cobro automático deja $2.000 pendientes (no hay saldo).
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW() + INTERVAL 16 MINUTE)');
        (new \App\Services\ServicioCobro($this->db()))->procesarVencidos();

        $sal = $this->servicio()->identificar('1000000002', '', 1, $kiosco);
        $this->assertSame('SALIDA', $sal['tipo'], 'El mismo escáner sirve para salir');
        $r = $this->clasificarConPortero($sal['id'], $kiosco, 'CELULAR', 'CARRO', '0.91', '0.04');
        $det = array_column($r['detalle'], 1, 0);
        $this->assertSame('SALIDA_DENEGADA', $r['resultado']);
        $this->assertTrue($r['deuda'], 'Se muestra cómo resolver la deuda');
        $this->assertContains('Automóvil', $det['Al entrar']);
        $this->assertSame('Automóvil · IA · confirmado por Portería', $det['Al salir']);
        $this->assertContains('$ 2.000', $det['Cobro']);
        $this->assertSame('Pendiente', $det['Pago']);
        $this->assertContains('min', $det['Tiempo']);

        // Recarga en Caja → el cobro se paga solo → el siguiente escaneo autoriza la salida.
        (new \App\Services\ServicioSaldo($this->db()))->recargar(2, '5000', 'EFECTIVO', $this->usuario('caja'));
        $otra = $this->servicio()->identificar('1000000002', '', 1, $kiosco);
        $r = $this->clasificarConPortero($otra['id'], $kiosco, 'CELULAR', 'CARRO', '0.92', '0.01');
        $det = array_column($r['detalle'], 1, 0);
        $this->assertSame('SALIDA_AUTORIZADA', $r['resultado']);
        $this->assertFalse($r['deuda']);
        $this->assertSame('Pagado con saldo UniPark', $det['Pago']);
    }

    // ------------------------------------------------------------ Panel del portero en tiempo real

    /**
     * El portero VE en tiempo real a quien se identifica (sin botones) y SOLO recibe la alerta para elegir el
     * vehículo cuando la IA no lo identificó. Cuando la IA lo reconoce, el ingreso se registra solo.
     */
    public function testElPorteroVeLaActividadYConfirmaCadaDeteccionDeLaIa(): void
    {
        $kiosco = $this->usuario('acceso1');
        $valentina = $this->servicio()->identificar('1000000001', '', 1, $kiosco);
        $this->iniciarComo('porteria');
        $d = json_decode($this->solicitud('GET', '/api/porteria/eventos')->body, true);
        $this->assertSame([], $d['eventos'], 'Identificarse no genera alerta');
        $this->assertSame('EN_CAPTURA', $d['actividad'][0]['estado'], 'El portero ve que se está identificando');
        $this->assertSame('Valentina Ríos Cardona', $d['actividad'][0]['persona']);
        $this->assertNotContains('1000000001', json_encode($d), 'Documento enmascarado');

        // IA confiable: NO se registra sola. Al portero le llega «Confirmar vehículo» con la sugerencia.
        $r = $this->servicio()->clasificar($valentina['id'], $kiosco, 'CELULAR', 'CARRO', '0.95', '0.02');
        $this->assertSame('PENDIENTE_PORTERO', $r['estado']);
        $this->assertSame('Automóvil', $r['por_confirmar'], 'El celular muestra lo detectado, sin botones');
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_evento_entrada = ?', [$valentina['id']]), 'Nada registrado aún');
        $d = json_decode($this->solicitud('GET', '/api/porteria/eventos')->body, true);
        $this->assertSame(1, count($d['eventos']), 'El portero recibe la confirmación');
        $this->assertSame('CARRO', $d['eventos'][0]['confirmar']);
        $this->assertSame('Automóvil', $d['eventos'][0]['sugerencia']);
        // El portero verifica que sea correcto y confirma.
        $r = json_decode($this->solicitud('POST', '/api/porteria/eventos/' . $valentina['id'] . '/resolver', ['accion' => 'CLASIFICAR', 'tipo' => 'CARRO'])->body, true);
        $this->assertSame('ENTRADA_REGISTRADA', $r['resultado']);
        $d = json_decode($this->solicitud('GET', '/api/porteria/eventos')->body, true);
        $this->assertSame([], $d['eventos']);
        $this->assertSame('ENTRADA_REGISTRADA', $d['actividad'][0]['resultado']);
        $this->assertSame('IA · confirmado por Portería', $d['actividad'][0]['metodo']);
        $this->assertSame('IA', $this->valor('SELECT metodo FROM eventos_acceso WHERE id_evento = ?', [$valentina['id']]), 'Tipo de la IA, confirmado');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'ACCESO_IA_CONFIRMADA' AND id_registro = ?", [$valentina['id']]));

        // Si el portero CAMBIA la sugerencia, queda como clasificación manual.
        $mariana = $this->servicio()->identificar('1000000003', '', 1, $kiosco);
        $this->servicio()->clasificar($mariana['id'], $kiosco, 'CELULAR', 'CARRO', '0.95', '0.02');
        $this->solicitud('POST', '/api/porteria/eventos/' . $mariana['id'] . '/resolver', ['accion' => 'CLASIFICAR', 'tipo' => 'MOTO']);
        $this->assertSame('MANUAL', $this->valor('SELECT metodo FROM eventos_acceso WHERE id_evento = ?', [$mariana['id']]));
        $this->assertSame('MOTO', $this->valor('SELECT tipo_vehiculo FROM visitas WHERE id_evento_entrada = ?', [$mariana['id']]));
        $traza = json_decode((string) $this->valor("SELECT detalle_json FROM auditoria WHERE accion = 'ACCESO_CLASIFICADO_IA' AND id_registro = ?", [$valentina['id']]), true);
        $this->assertSame('INFERENCIA_EN_NAVEGADOR', $traza['origen'], 'El origen queda registrado honestamente');
        $this->assertSame(0.95, $traza['confianza_reportada'], 'Confianza «reportada», no verificada por el servidor');

        // Hallazgo B8: la entrada manual de Portería ya no puede marcarse como «IA» desde el navegador.
        $this->solicitud('POST', '/api/porteria/entrada', ['id_estudiante' => '3', 'tipo' => 'MOTO', 'origen' => 'IA']);
        $this->assertSame('MANUAL', $this->valor("SELECT tipo_origen FROM visitas WHERE id_estudiante = 3 AND estado = 'EN_CURSO'"));

        // IA sin confianza: SOLO entonces aparece la alerta con los botones de tipo.
        $santiago = $this->servicio()->identificar('1000000002', '', 1, $kiosco);
        $this->clasificarConPortero($santiago['id'], $kiosco, 'CELULAR', 'DESCONOCIDO', '0', '0', true);
        $d = json_decode($this->solicitud('GET', '/api/porteria/eventos')->body, true);
        $this->assertSame(1, count($d['eventos']), 'Una alerta: la IA no identificó el vehículo');
        $this->assertSame('VEHICULO_NO_CLASIFICADO', $d['eventos'][0]['motivo']);
        $this->assertSame(['CARRO', 'MOTO', 'BICICLETA'], array_column($d['tipos'], 'codigo'), 'Botones de tipo para el portero');

        // El punto de acceso (o quien se identifica) no puede resolverla: solo el portero.
        $this->iniciarComo('acceso1');
        $this->assertSame(403, $this->solicitud('POST', '/api/porteria/eventos/' . $santiago['id'] . '/resolver', ['accion' => 'CLASIFICAR', 'tipo' => 'BICICLETA'])->status);
        $this->iniciarComo('estudiante2');
        $this->assertSame(403, $this->solicitud('GET', '/api/porteria/eventos')->status);
        $this->iniciarComo('porteria');
        $r = json_decode($this->solicitud('POST', '/api/porteria/eventos/' . $santiago['id'] . '/resolver', ['accion' => 'CLASIFICAR', 'tipo' => 'CARRO'])->body, true);
        $this->assertSame('CONFIRMADO', $r['estado']);
        $this->assertSame('CARRO', $this->valor('SELECT tipo_vehiculo FROM visitas WHERE id_estudiante = 2 AND estado = ?', ['EN_CURSO']));
    }
}
