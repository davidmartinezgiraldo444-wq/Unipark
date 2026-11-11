<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Helpers\Csrf;
use Tests\Support\TestCase;

/**
 * Usuarios de prueba por rol (contraseña demo2026*): inicio de sesión, pantalla de inicio de cada rol
 * y separación real de permisos. Incluye la causa del problema reportado: los estudiantes no podían
 * ingresar porque no existía ningún rol ni cuenta de estudiante.
 */
final class RolesPruebaTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    private const CUENTAS = [
        'admin' => '/panel', 'admin2' => '/panel',
        'porteria' => '/porteria', 'porteria2' => '/porteria', 'porteria3' => '/porteria',
        'caja' => '/caja', 'caja2' => '/caja', 'caja3' => '/caja',
        'auditor' => '/reportes', 'auditor2' => '/reportes',
        'estudiante1' => '/mi-cuenta', 'estudiante2' => '/mi-cuenta', 'estudiante3' => '/mi-cuenta',
        'profesor1' => '/mi-cuenta', 'profesor2' => '/mi-cuenta', 'profesor3' => '/mi-cuenta',
        'trabajador1' => '/mi-cuenta', 'trabajador2' => '/mi-cuenta', 'trabajador3' => '/mi-cuenta',
    ];

    public function testTodosLosUsuariosDePruebaInicianSesionConDemo2026YVanASuPantalla(): void
    {
        foreach (self::CUENTAS as $login => $inicio) {
            $_SESSION = [];
            Csrf::rotate();
            $r = $this->solicitud('POST', '/login', ['login' => $login, 'clave' => 'demo2026*']);
            $this->assertSame(303, $r->status, "Inicio de sesión de {$login}");
            $this->assertSame($inicio, $r->headers['Location'] ?? null, "Pantalla de inicio de {$login}");
        }
    }

    public function testLasContrasenasSeGuardanComoHashYNoEnTextoPlano(): void
    {
        $this->assertSame(0, (int) $this->valor("SELECT COUNT(*) FROM usuarios WHERE password_hash = 'demo2026*' OR password_hash NOT LIKE '\$2y\$%'"));
    }

    public function testCadaCuentaTieneSuRolYLasCuentasPropiasSuPersona(): void
    {
        $filas = $this->db()->query(
            "SELECT u.login, r.nombre AS rol, e.categoria
               FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol LEFT JOIN estudiantes e ON e.id_estudiante = u.id_estudiante"
        )->fetchAll();
        $porRol = [];
        foreach ($filas as $f) {
            $porRol[$f['rol']][] = $f['login'];
            if (in_array($f['rol'], ['Estudiante', 'Profesor', 'Trabajador'], true)) {
                $this->assertSame(mb_strtoupper($f['rol']), $f['categoria'], "La persona de {$f['login']} coincide con su rol");
            }
        }
        foreach (['Estudiante', 'Profesor', 'Trabajador', 'Portería', 'Caja', 'Administrador', 'Auditor'] as $rol) {
            $this->assertTrue(count($porRol[$rol] ?? []) >= 2, "Al menos dos usuarios de prueba con rol {$rol}");
        }
    }

    public function testElEstudianteSoloVeSuCuentaYNoLaOperacion(): void
    {
        $this->iniciarComo('estudiante1');
        $cuenta = $this->solicitud('GET', '/mi-cuenta');
        $this->assertSame(200, $cuenta->status);
        $this->assertContains('Valentina', $cuenta->body);
        $this->assertContains('$ 10.000', $cuenta->body, 'Su saldo');
        $this->assertNotContains('Santiago', $cuenta->body, 'No ve datos de otras personas');

        foreach (['/porteria', '/porteria/escaner', '/caja', '/estudiantes', '/reportes', '/usuarios', '/tarifas'] as $ruta) {
            $this->assertSame(403, $this->solicitud('GET', $ruta)->status, "Estudiante en {$ruta}");
        }
        $this->assertSame(403, $this->solicitud('POST', '/api/porteria/salida', ['id_visita' => '7'])->status, 'No autoriza salidas');
        $this->assertSame('/mi-cuenta', $this->solicitud('GET', '/panel')->headers['Location'] ?? null);
    }

    public function testElPersonalNoTieneCuentaPropiaNiRecargaSinPermiso(): void
    {
        $this->iniciarComo('porteria');
        $this->assertSame(403, $this->solicitud('GET', '/mi-cuenta')->status);
        $this->assertSame(403, $this->solicitud('POST', '/caja/recarga', ['id_estudiante' => '1', 'monto' => '1000', 'medio_pago' => 'EFECTIVO'])->status);

        $this->iniciarComo('auditor');
        $this->assertSame(403, $this->solicitud('POST', '/api/porteria/entrada', ['id_estudiante' => '1', 'tipo' => 'CARRO'])->status);
    }

    public function testPorteriaIdentificaRolesYVisitantesDesdeElCelular(): void
    {
        $this->iniciarComo('porteria');
        $r = json_decode($this->solicitud('POST', '/api/porteria/identificar', ['codigo' => '1000000015'])->body, true);
        $this->assertSame('REGISTRADO', $r['tipo']);
        $this->assertSame('TRABAJADOR', $r['persona']['categoria']);
        $this->assertSame('ENTRADA', $r['accion']);

        $v = json_decode($this->solicitud('POST', '/api/porteria/identificar', ['codigo' => '52.333.444'])->body, true);
        $this->assertSame('VISITANTE', $v['tipo'], 'Documento no registrado = visitante');
        $this->assertSame('52333444', $v['codigo'], 'Se limpian puntos y espacios');

        $e = json_decode($this->solicitud('POST', '/api/porteria/entrada', ['codigo' => '52333444', 'tipo' => 'MOTO', 'origen' => 'IA'])->body, true);
        $this->assertTrue($e['ok'], 'Entrada de visitante');
        $this->assertSame('NO_APLICA', $e['visita']['liquidacion']);

        $s = json_decode($this->solicitud('POST', '/api/porteria/identificar', ['codigo' => '1000000006'])->body, true);
        $this->assertSame('SALIDA', $s['accion'], 'Persona con visita abierta: salida');
        $salida = json_decode($this->solicitud('POST', '/api/porteria/salida', ['id_visita' => (string) $s['abiertas'][0]['id']])->body, true);
        $this->assertTrue($salida['autorizada'], 'Visita pagada (esquema anterior): sale');
    }

    public function testLasPantallasDeCadaRolCarganSinErrores(): void
    {
        $pantallas = [
            'porteria' => ['/porteria', '/porteria?documento=1000000006', '/porteria?documento=99887766', '/porteria/escaner', '/porteria/camara', '/panel'],
            'caja'     => ['/caja', '/caja?recargar=1000000002', '/caja/cierre', '/panel'],
            'admin'    => ['/panel', '/tarifas', '/usuarios/nuevo', '/estudiantes/1', '/estudiantes/nuevo', '/reportes', '/auditoria', '/inicio', '/porteria/carnets'],
            'profesor1' => ['/mi-cuenta', '/api/mi-cuenta/estado', '/api/push/clave'],
        ];
        foreach ($pantallas as $login => $rutas) {
            $this->iniciarComo($login);
            foreach ($rutas as $ruta) {
                $r = $this->solicitud('GET', $ruta);
                $this->assertSame(200, $r->status, "{$login} {$ruta}");
                $this->assertNotContains('Ocurrió un error', $r->body, "{$login} {$ruta}");
            }
        }
        $this->iniciarComo('caja');
        $r = $this->solicitud('POST', '/caja/recarga', ['id_estudiante' => '2', 'monto' => '5000', 'medio_pago' => 'EFECTIVO', 'documento' => '1000000002']);
        $this->assertSame(303, $r->status);
        $this->assertSame(200, $this->solicitud('GET', $r->headers['Location'] ? substr($r->headers['Location'], 0) : '/caja')->status, 'Comprobante de recarga');
        $this->assertSame('5000.00', $this->valor('SELECT saldo FROM estudiantes WHERE id_estudiante = 2'));
    }

    public function testElCelularDeCamaraSoloPublicaElTipoDeVehiculo(): void
    {
        $this->iniciarComo('porteria2');
        $ok = json_decode($this->solicitud('POST', '/api/camara/deteccion', ['puesto' => '1', 'tipo' => 'MOTO', 'confianza' => '0.93'])->body, true);
        $this->assertTrue($ok['ok']);
        $u = json_decode($this->solicitud('GET', '/api/camara/ultima?puesto=1')->body, true);
        $this->assertSame('MOTO', $u['tipo']);
        $this->assertTrue($u['vigente']);
        $this->assertSame(422, $this->solicitud('POST', '/api/camara/deteccion', ['puesto' => '1', 'tipo' => 'PLACA-ABC123', 'confianza' => '1'])->status);
    }
}
