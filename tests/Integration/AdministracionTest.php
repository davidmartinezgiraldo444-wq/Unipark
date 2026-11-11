<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServicioCobro;
use App\Services\ServicioEntrada;
use App\Services\ServicioImportacion;
use App\Services\ServicioPago;
use App\Services\ServicioSaldo;
use Tests\Support\TestCase;

/**
 * Administración: reverso de recargas, anulación de cobros desde el historial, saldo que paga
 * pendientes salteando los que no alcanza, reportes por rol e importación de la base institucional.
 *
 * Personas de database/seed.sql: 1 Valentina (estudiante, saldo 10.000), 2 Santiago (estudiante, saldo 0).
 */
final class AdministracionTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const PORTERIA = 2;
    private const CAJA = 3;
    private const ADMIN = 1;

    private function adelantar(int $minutos): void
    {
        $this->db()->exec('SET timestamp = UNIX_TIMESTAMP(NOW() + INTERVAL ' . $minutos . ' MINUTE)');
    }

    private function csv(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($ruta, $contenido);
        return $ruta;
    }

    // ------------------------------------------------------------ Reverso de recargas

    public function testReversarUnaRecargaMalDigitadaCreaUnMovimientoYNoBorraNada(): void
    {
        $saldo = new ServicioSaldo($this->db());
        $r = $saldo->recargar(2, '50.000', 'EFECTIVO', self::CAJA);
        $this->assertSame('50000.00', $r['saldo']);

        $rev = $saldo->reversarRecarga($r['id_movimiento'], self::ADMIN, 'Se digitó 50.000 en lugar de 5.000');
        $this->assertSame('0.00', $rev['saldo']);
        $this->assertSame('0.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 2'));
        $this->assertSame('RECARGA', $this->valor('SELECT tipo FROM movimientos_saldo WHERE id_movimiento = ?', [$r['id_movimiento']]), 'La recarga se conserva');
        $m = $this->fila('SELECT * FROM movimientos_saldo WHERE id_movimiento = ?', [$rev['id_movimiento']]);
        $this->assertSame('REVERSO_RECARGA', $m['tipo']);
        $this->assertSame((string) $r['id_movimiento'], (string) $m['id_movimiento_origen']);
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'SALDO_REVERSADO'"));

        $this->assertRegla('YA_REVERSADA', fn () => $saldo->reversarRecarga($r['id_movimiento'], self::ADMIN, 'Segundo intento del reverso'));
        $this->assertRegla('MOTIVO', fn () => $saldo->reversarRecarga($r['id_movimiento'], self::ADMIN, 'x'));
    }

    public function testNoSeReversaUnaRecargaQueYaPagoCobros(): void
    {
        (new ServicioEntrada($this->db()))->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $r = (new ServicioSaldo($this->db()))->recargar(2, '5.000', 'EFECTIVO', self::CAJA);
        $this->assertSame('3000.00', $r['saldo'], 'La recarga pagó el cobro pendiente de 2.000');

        $this->assertRegla('SALDO_INSUFICIENTE', fn () => (new ServicioSaldo($this->db()))->reversarRecarga($r['id_movimiento'], self::ADMIN, 'Recarga equivocada de persona'));
        $this->assertSame('3000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 2'), 'Sin cambios');
    }

    public function testSoloElAdministradorReversaRecargasPorLaWeb(): void
    {
        $r = (new ServicioSaldo($this->db()))->recargar(2, '7.000', 'EFECTIVO', self::CAJA);
        $this->iniciarComo('caja');
        $this->assertSame(403, $this->solicitud('POST', '/saldo/' . $r['id_movimiento'] . '/reversar', ['motivo' => 'Recarga equivocada de persona'])->status);
        $this->assertSame('7000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 2'));

        $this->iniciarComo('admin');
        $html = $this->solicitud('GET', '/estudiantes/2')->body;
        $this->assertContains('Movimientos de saldo', $html);
        $this->assertContains('Reversar recarga', $html, 'El administrador ve el reverso en el historial');
        $res = $this->solicitud('POST', '/saldo/' . $r['id_movimiento'] . '/reversar', ['motivo' => 'Recarga equivocada de persona']);
        $this->assertSame(303, $res->status);
        $this->assertSame('0.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 2'));
    }

    // ------------------------------------------------------------ Cobros

    public function testElAdministradorAnulaUnCobroPendienteDesdeElHistorial(): void
    {
        (new ServicioEntrada($this->db()))->registrar(2, 'MOTO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $cobro = (int) $this->valor("SELECT id_cobro FROM cobros_diarios WHERE id_estudiante = 2 AND estado = 'PENDIENTE'");

        $this->iniciarComo('auditor');
        $hist = $this->solicitud('GET', '/estudiantes/2')->body;
        $this->assertContains('Cobros diarios', $hist, 'El auditor ve los cobros');
        $this->assertNotContains('Anular cobro', $hist, 'pero no los anula');
        $this->iniciarComo('caja');
        $this->assertSame(403, $this->solicitud('POST', "/cobros/{$cobro}/anular", ['motivo' => 'Prueba de permisos de caja'])->status);

        $this->iniciarComo('admin');
        $this->assertContains('Anular cobro', $this->solicitud('GET', '/estudiantes/2')->body);
        $this->solicitud('POST', "/cobros/{$cobro}/anular", ['motivo' => 'Era bicicleta, no motocicleta']);
        $this->assertSame('ANULADO', $this->valor('SELECT estado FROM cobros_diarios WHERE id_cobro = ?', [$cobro]));
        $this->assertSame('Era bicicleta, no motocicleta', $this->valor('SELECT motivo_anulacion FROM cobros_diarios WHERE id_cobro = ?', [$cobro]));
    }

    public function testElSaldoPagaLosPendientesQueAlcanzaAunqueUnoAnteriorNoAlcance(): void
    {
        // Dos cobros pendientes de días distintos: carro (2.000) ayer y moto (1.000) hoy.
        $entrada = new ServicioEntrada($this->db());
        $v1 = $entrada->registrar(2, 'CARRO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        (new ServicioPago($this->db()))->registrarCobro((int) $this->valor("SELECT id_cobro FROM cobros_diarios WHERE id_estudiante = 2"), 'EFECTIVO', self::CAJA);
        (new \App\Services\ServicioSalida($this->db()))->autorizar((int) $v1['id_visita'], self::PORTERIA);
        // Se deja el cobro de ayer pendiente otra vez anulando su pago.
        $pago = (int) $this->valor("SELECT id_pago FROM pagos WHERE id_cobro IS NOT NULL AND estado = 'PAGADO' ORDER BY id_pago DESC LIMIT 1");
        (new ServicioPago($this->db()))->anular($pago, self::ADMIN, 'Pago registrado a la persona equivocada');
        $this->adelantar(24 * 60);
        $entrada->registrar(2, 'MOTO', self::PORTERIA);
        $this->adelantar(16);
        (new ServicioCobro($this->db()))->procesarVencidos();
        $this->assertSame(2, (int) $this->valor("SELECT COUNT(*) FROM cobros_diarios WHERE id_estudiante = 2 AND estado = 'PENDIENTE'"));

        // 1.500 no alcanza el carro (2.000, el más antiguo) pero sí la moto (1.000).
        $r = (new ServicioSaldo($this->db()))->recargar(2, '1.500', 'EFECTIVO', self::CAJA);
        $this->assertSame(1, count($r['cobros_pagados']), 'Se pagó el que alcanzaba');
        $this->assertSame('500.00', $r['saldo']);
        $this->assertSame('CARRO', $this->valor("SELECT tipo_vehiculo FROM cobros_diarios WHERE id_estudiante = 2 AND estado = 'PENDIENTE'"));
    }

    // ------------------------------------------------------------ Reportes y panel por rol

    public function testLosReportesNuevosRespetanElRol(): void
    {
        $this->iniciarComo('caja');
        $this->assertSame(403, $this->solicitud('GET', '/reportes?tab=saldos')->status, 'Caja no consulta reportes de saldos');

        $this->iniciarComo('auditor');
        foreach (['cobros', 'movimientos', 'saldos'] as $tab) {
            $r = $this->solicitud('GET', '/reportes?tab=' . $tab);
            $this->assertSame(200, $r->status, "El auditor abre {$tab}");
        }
        $this->assertSame(200, $this->solicitud('GET', '/reportes/exportar?tab=saldos')->status, 'El auditor exporta saldos');

        $this->iniciarComo('admin');
        $r = $this->solicitud('GET', '/reportes?tab=eventos');
        $this->assertSame(200, $r->status);
        $this->assertContains('Calibración de la IA', $r->body);
        $panel = $this->solicitud('GET', '/panel')->body;
        $this->assertContains('Deuda pendiente', $panel);
        $this->assertContains('Esperando al portero', $panel);

        $this->iniciarComo('porteria');
        $this->assertNotContains('Deuda pendiente', $this->solicitud('GET', '/panel')->body, 'Portería no ve la deuda');
        $this->assertSame(403, $this->solicitud('GET', '/estudiantes/importar')->status);
    }

    // ------------------------------------------------------------ Importación institucional

    public function testValidarNoGuardaPersonasYAplicarCreaYActualiza(): void
    {
        $csv = $this->csv("\xEF\xBB\xBFCédula;Nombres;Apellidos;Tipo de vinculación;Programa;Correo;Estado\n"
            . "2000000001;Laura;Pérez Gil;Estudiante;Ingeniería Informática;laura@correo.test;Activo\n"
            . "1000000001;Valentina;Ríos Cardona;Docente;Maestría;;\n"
            . "2000000002;Ana;;Estudiante;;;\n"
            . "2000000001;Repetida;Persona;Estudiante;;;\n"
            . "2000000003;Pedro;Ruiz;Rector;;;\n"
            . "2000000004;Marta;Gómez;administrativo;Biblioteca;;Retirado\n");
        $antes = (int) $this->valor('SELECT COUNT(*) FROM estudiantes');
        $servicio = new ServicioImportacion($this->db());

        $v = $servicio->procesar($csv, 'base.csv', 'VALIDAR', self::ADMIN);
        $this->assertSame(6, $v['filas']);
        $this->assertSame(2, $v['creadas']);
        $this->assertSame(1, $v['actualizadas']);
        $this->assertSame(3, $v['rechazadas'], 'Sin apellidos, documento repetido, categoría desconocida');
        $this->assertSame($antes, (int) $this->valor('SELECT COUNT(*) FROM estudiantes'), 'VALIDAR no guarda personas');
        $this->assertNotContains('2000000002', (string) $this->valor('SELECT errores_json FROM importaciones WHERE id_importacion = ?', [$v['id_importacion']]), 'El registro de errores no copia el documento completo');

        $a = $servicio->procesar($csv, 'base.csv', 'APLICAR', self::ADMIN);
        $this->assertSame(2, $a['creadas']);
        $this->assertSame($antes + 2, (int) $this->valor('SELECT COUNT(*) FROM estudiantes'));
        $laura = $this->fila("SELECT * FROM estudiantes WHERE documento = '2000000001'");
        $this->assertSame('INSTITUCIONAL', $laura['origen']);
        $this->assertSame('Ingeniería Informática', $laura['programa']);
        $this->assertSame('0', (string) $this->valor("SELECT activo FROM estudiantes WHERE documento = '2000000004'"), 'Retirado entra inactivo');
        $this->assertSame('TRABAJADOR', $this->valor("SELECT categoria FROM estudiantes WHERE documento = '2000000004'"));

        $valentina = $this->fila('SELECT * FROM estudiantes WHERE id_estudiante = 1');
        $this->assertSame('PROFESOR', $valentina['categoria'], 'Actualiza la categoría');
        $this->assertSame('valentina.rios@correo.test', $valentina['correo'], 'Un campo vacío no borra el dato');
        $this->assertSame('10000.00', $valentina['saldo'], 'Nunca toca el saldo');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'PERSONAS_IMPORTADAS'"));

        // Reimportar el mismo archivo no duplica ni cambia nada.
        $otra = $servicio->procesar($csv, 'base.csv', 'APLICAR', self::ADMIN);
        $this->assertSame(0, $otra['creadas']);
        $this->assertSame(0, $otra['actualizadas']);
        $this->assertSame(3, $otra['sin_cambios']);
        $this->assertSame($antes + 2, (int) $this->valor('SELECT COUNT(*) FROM estudiantes'));
        unlink($csv);
    }

    public function testLaImportacionExigeLasColumnasObligatoriasYRespetaCarnetsAjenos(): void
    {
        $sin = $this->csv("documento,nombre\n2000000009,Luis\n");
        $this->assertRegla('COLUMNAS', fn () => (new ServicioImportacion($this->db()))->procesar($sin, 'x.csv', 'VALIDAR', self::ADMIN));
        unlink($sin);

        $this->db()->exec("UPDATE estudiantes SET codigo_carnet = 'C-111' WHERE id_estudiante = 3");
        $csv = $this->csv("documento,codigo_carnet,nombres,apellidos,categoria\n2000000010,C-111,Luis,Mora,ESTUDIANTE\n1000000004,C-111,Juan José,Arango Vélez,ESTUDIANTE\n");
        $r = (new ServicioImportacion($this->db()))->procesar($csv, 'x.csv', 'APLICAR', self::ADMIN);
        $this->assertSame(2, $r['rechazadas'], 'El carnet de otra persona no se reasigna');
        $this->assertSame('3', (string) $this->valor("SELECT id_estudiante FROM estudiantes WHERE codigo_carnet = 'C-111'"));
        unlink($csv);
    }

    public function testLaImportacionEsSoloDelAdministrador(): void
    {
        $this->iniciarComo('admin');
        $r = $this->solicitud('GET', '/estudiantes/importar');
        $this->assertSame(200, $r->status);
        $this->assertContains('Columnas reconocidas', $r->body);
        foreach (['caja', 'auditor', 'porteria'] as $rol) {
            $this->iniciarComo($rol);
            $this->assertSame(403, $this->solicitud('POST', '/estudiantes/importar', ['modo' => 'APLICAR'])->status, "{$rol} no importa");
        }
    }
}
