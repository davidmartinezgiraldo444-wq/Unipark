<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServicioEntrada;
use App\Services\ServicioTarifa;
use Tests\Support\TestCase;

/**
 * Entrada en portería: CP-01, CP-02, CP-03 y CP-10.
 * Datos de database/seed.sql: estudiantes 9 y 10 sin visitas, profesor 11 sin visitas,
 * estudiante 5 con visita abierta, estudiante 12 inactivo.
 */
final class EntradaTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const PORTERIA = 2;

    public function testCP01EntradaValidaCreaVisitaEnCurso(): void
    {
        $visita = (new ServicioEntrada($this->db()))->registrar(9, 'CARRO', self::PORTERIA);

        $this->assertSame('EN_CURSO', $visita['estado']);
        $this->assertSame('9', $visita['id_estudiante']);
        $this->assertSame('CARRO', $visita['tipo_vehiculo']);
        $this->assertSame('ESTUDIANTE', $visita['categoria']);
        $this->assertSame('2000.00', $visita['tarifa_aplicada'], 'Copia la tarifa diaria vigente de carro para estudiante');
        $this->assertSame('EN_TOLERANCIA', $visita['liquidacion'], 'Empieza la tolerancia');
        $this->assertSame((string) self::PORTERIA, $visita['entrada_por'], 'Registra el funcionario');
        $this->assertTrue($visita['entrada_en'] !== null && $visita['salida_en'] === null);

        $auditoria = $this->fila("SELECT * FROM auditoria WHERE accion = 'ENTRADA_REGISTRADA' AND id_registro = ?", [$visita['id_visita']]);
        $this->assertSame((string) self::PORTERIA, $auditoria['id_usuario'] ?? null, 'La entrada queda en la auditoría');
    }

    public function testCP02EntradaDuplicadaSeRechazaYMuestraLaVisitaActual(): void
    {
        $servicio = new ServicioEntrada($this->db());
        $e = $this->assertRegla('VISITA_ABIERTA', fn () => $servicio->registrar(5, 'CARRO', self::PORTERIA));

        $this->assertSame(6, $e->datos['id_visita'], 'Informa la visita abierta existente');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM visitas WHERE id_estudiante = 5 AND estado = 'EN_CURSO'"));
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'ENTRADA_RECHAZADA'"), 'El rechazo se audita');
    }

    public function testCP03DosEntradasSimultaneasSoloConfirmanUna(): void
    {
        $procesos = 6;
        $inicio = microtime(true) + 1.5;
        $handles = [];
        for ($i = 0; $i < $procesos; $i++) {
            $tipo = $i % 2 ? 'MOTO' : 'CARRO';
            $handles[] = proc_open(
                [PHP_BINARY, BASE_PATH . '/tests/Support/concurrente.php', 'entrada', '10', $tipo, (string) self::PORTERIA, (string) $inicio],
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

        $exitos = array_filter($resultados, static fn ($r) => $r['ok']);
        $rechazos = array_filter($resultados, static fn ($r) => !$r['ok'] && $r['codigo'] === 'VISITA_ABIERTA');
        $this->assertSame(1, count($exitos), 'Exactamente una entrada confirmada: ' . json_encode($resultados));
        $this->assertSame($procesos - 1, count($rechazos), 'Las demás reciben un error controlado: ' . json_encode($resultados));
        $this->assertSame(1, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_estudiante = 10'));
    }

    public function testCP03LaBaseRechazaUnaSegundaVisitaAbiertaAunqueSeOmitaLaAplicacion(): void
    {
        $e = $this->assertSqlFalla(
            fn () => $this->db()->exec(
                "INSERT INTO visitas (id_estudiante, tipo_vehiculo, categoria, id_tarifa, tarifa_aplicada, entrada_por)
                 VALUES (5, 'MOTO', 'ESTUDIANTE', 3, 1050, 2)"
            ),
            'El índice único visita_abierta debía impedir la segunda visita abierta'
        );
        $this->assertSame(1062, (int) $e->errorInfo[1]);
    }

    public function testCP10CambioDeTarifaNoAlteraVisitasExistentes(): void
    {
        $antes = $this->valor('SELECT tarifa_aplicada FROM visitas WHERE id_visita = 8');
        (new ServicioTarifa($this->db()))->cambiar('CARRO', 'ESTUDIANTE', '2500', 1);

        $this->assertSame($antes, $this->valor('SELECT tarifa_aplicada FROM visitas WHERE id_visita = 8'), 'La visita abierta conserva su valor');
        $this->assertSame('1900.00', $this->valor('SELECT tarifa_aplicada FROM visitas WHERE id_visita = 1'), 'La visita histórica conserva su valor');

        $servicio = new ServicioEntrada($this->db());
        $this->assertSame('2500.00', $servicio->registrar(10, 'CARRO', self::PORTERIA)['tarifa_aplicada'], 'Las nuevas entradas de estudiantes usan la nueva tarifa');
        $this->assertSame('3000.00', $servicio->registrar(11, 'CARRO', self::PORTERIA)['tarifa_aplicada'], 'La tarifa de profesor no cambia');
    }

    public function testCP10LaBaseImpideEditarLaTarifaAplicada(): void
    {
        $this->assertSqlFalla(
            fn () => $this->db()->exec('UPDATE visitas SET tarifa_aplicada = 1 WHERE id_visita = 8'),
            'El disparador debía impedir cambiar tarifa_aplicada'
        );
        $this->assertSqlFalla(
            fn () => $this->db()->exec('UPDATE tarifas SET valor = 1 WHERE id_tarifa = 1'),
            'El disparador debía impedir editar el valor de una tarifa'
        );
    }

    public function testProfesorPagaLaTarifaDeProfesor(): void
    {
        $servicio = new ServicioEntrada($this->db());
        $moto = $servicio->registrar(11, 'MOTO', self::PORTERIA);
        $this->assertSame('PROFESOR', $moto['categoria']);
        $this->assertSame('2000.00', $moto['tarifa_aplicada'], 'Moto profesor');

        $this->assertSame('1000.00', $servicio->registrar(9, 'MOTO', self::PORTERIA)['tarifa_aplicada'], 'Moto estudiante');
        $this->assertSame('2000.00', $servicio->registrar(10, 'CARRO', self::PORTERIA)['tarifa_aplicada'], 'Carro estudiante');
        $this->assertSame('2800.00', $this->valor('SELECT tarifa_aplicada FROM visitas WHERE id_visita = 7'), 'Carro profesor (visita del esquema anterior)');
    }

    public function testLaBaseImpideUsarLaTarifaDeOtraCategoria(): void
    {
        // Tarifa 1 es CARRO-ESTUDIANTE: la FK compuesta rechaza aplicarla a una visita de profesor.
        $this->assertSqlFalla(
            fn () => $this->db()->exec(
                "INSERT INTO visitas (id_estudiante, tipo_vehiculo, categoria, id_tarifa, tarifa_aplicada, entrada_por)
                 VALUES (11, 'CARRO', 'PROFESOR', 1, 2050, 2)"
            ),
            'La FK (id_tarifa, tipo_vehiculo, categoria) debía rechazar la tarifa de otra categoría'
        );
    }

    public function testEstudianteInactivoOInexistenteNoIngresa(): void
    {
        $servicio = new ServicioEntrada($this->db());
        $this->assertRegla('ESTUDIANTE_NO_DISPONIBLE', fn () => $servicio->registrar(12, 'CARRO', self::PORTERIA));
        $this->assertRegla('ESTUDIANTE_NO_DISPONIBLE', fn () => $servicio->registrar(9999, 'CARRO', self::PORTERIA));
        $this->assertRegla('TIPO_INVALIDO', fn () => $servicio->registrar(9, 'AVION', self::PORTERIA));
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_estudiante IN (9, 12)'));
    }

    public function testDespuesDeSalirElEstudiantePuedeVolverAIngresar(): void
    {
        // El estudiante 1 tiene visitas cerradas: la restricción solo aplica a visitas EN_CURSO.
        $visita = (new ServicioEntrada($this->db()))->registrar(1, 'MOTO', self::PORTERIA);
        $this->assertSame('1000.00', $visita['tarifa_aplicada']);
        $this->assertSame(3, (int) $this->valor('SELECT COUNT(*) FROM visitas WHERE id_estudiante = 1'), 'Dos visitas cerradas y la nueva');
    }

    public function testAnularVisitaExigeMotivoYConservaLaFila(): void
    {
        $servicio = new ServicioEntrada($this->db());
        $this->assertRegla('MOTIVO', fn () => $servicio->anular(8, 1, 'corto'));
        $this->assertRegla('VISITA_PAGADA', fn () => $servicio->anular(7, 1, 'Registro duplicado por error de digitación'));

        $servicio->anular(8, 1, 'Registro duplicado por error de digitación');
        $visita = $this->fila('SELECT * FROM visitas WHERE id_visita = 8');
        $this->assertSame('ANULADA', $visita['estado']);
        $this->assertSame('Registro duplicado por error de digitación', $visita['motivo_anulacion']);
        $this->assertSame('1', $visita['anulada_por']);

        // Una visita anulada libera al estudiante para una nueva entrada.
        $nueva = $servicio->registrar(7, 'CARRO', self::PORTERIA);
        $this->assertSame('EN_CURSO', $nueva['estado']);
    }
}
