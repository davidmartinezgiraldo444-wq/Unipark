<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Services\AuthService;
use App\Services\ReglaNegocioException;
use Tests\Support\TestCase;

/** Control de acceso en el servidor: CP-08, CP-09, RN-14 y matriz de permisos (sección 4.1). */
final class PermisosTest extends TestCase
{
    protected bool $usaBaseDeDatos = true;

    public function testCP08CajaNoPuedeRegistrarSalidaNiLlamandoLaUrlDirectamente(): void
    {
        $this->iniciarComo('caja');
        $r = $this->solicitud('POST', '/porteria/salida', ['id_visita' => '7']);

        $this->assertSame(403, $r->status);
        $this->assertSame('EN_CURSO', $this->valor('SELECT estado FROM visitas WHERE id_visita = 7'), 'La visita no cambia');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'ACCESO_DENEGADO'"), 'El intento se audita');
    }

    public function testCP08PorteriaNoPuedeRegistrarPagoNiLlamandoLaUrlDirectamente(): void
    {
        $this->iniciarComo('porteria');
        $r = $this->solicitud('POST', '/caja/pago', ['id_visita' => '8', 'medio_pago' => 'EFECTIVO']);

        $this->assertSame(403, $r->status);
        $this->assertSame(0, (int) $this->valor('SELECT COUNT(*) FROM pagos WHERE id_visita = 8'), 'No se crea el pago');
    }

    public function testCP08CadaRolSoloAccedeASusModulos(): void
    {
        $casos = [
            // [login, método, ruta, estado esperado]
            ['porteria', 'GET',  '/porteria',  200],
            ['porteria', 'GET',  '/tarifas',   403],
            ['porteria', 'GET',  '/usuarios',  403],
            ['porteria', 'GET',  '/auditoria', 403],
            ['caja',     'GET',  '/caja',      200],
            ['caja',     'GET',  '/porteria',  403],
            ['caja',     'GET',  '/reportes',  403],
            ['auditor',  'GET',  '/reportes',  200],
            ['auditor',  'GET',  '/auditoria', 200],
            ['auditor',  'POST', '/tarifas',   403],
            ['auditor',  'POST', '/porteria/entrada', 403],
            ['auditor',  'POST', '/pagos/7/anular',   403],
            ['admin',    'GET',  '/usuarios',  200],
            ['admin',    'GET',  '/tarifas',   200],
            ['admin',    'POST', '/porteria/salida',  403],
            ['admin',    'POST', '/caja/pago',        403],
        ];
        foreach ($casos as [$login, $metodo, $ruta, $esperado]) {
            $this->iniciarComo($login);
            $r = $this->solicitud($metodo, $ruta);
            $this->assertSame($esperado, $r->status, "{$login} {$metodo} {$ruta}");
        }
    }

    public function testSinSesionTodaRutaProtegidaRedirigeAlLogin(): void
    {
        foreach (['/panel', '/porteria', '/caja', '/reportes', '/auditoria', '/estudiantes/1'] as $ruta) {
            $r = $this->solicitud('GET', $ruta);
            $this->assertSame(303, $r->status, $ruta);
            // Lleva al login y, tras ingresar, de vuelta a la pantalla pedida (el panel es el destino por defecto).
            $esperada = '/login' . ($ruta === '/panel' ? '' : '?volver=' . rawurlencode(ltrim($ruta, '/')));
            $this->assertSame($esperada, $r->headers['Location'] ?? null, $ruta);
        }
    }

    public function testCP09UsuarioInactivoNoPuedeIniciarSesion(): void
    {
        $auth = new AuthService($this->db());
        $e = $this->assertRegla('CREDENCIALES', fn () => $auth->intentar('inactivo', 'demo2026*', '127.0.0.1'));
        $this->assertSame(AuthService::MENSAJE_GENERICO, $e->getMessage(), 'Mensaje genérico, no revela que la cuenta existe');

        $r = $this->solicitud('POST', '/login', ['login' => 'inactivo', 'clave' => 'demo2026*']);
        $this->assertSame(401, $r->status);
    }

    public function testCuentaDesactivadaPierdeLaSesionDeInmediato(): void
    {
        $this->iniciarComo('porteria');
        $this->assertSame(200, $this->solicitud('GET', '/porteria')->status);

        \Tests\Support\TestDatabase::admin()->exec("UPDATE usuarios SET activo = 0 WHERE login = 'porteria'");
        $r = $this->solicitud('GET', '/porteria');
        $this->assertSame(303, $r->status);
        $this->assertSame([], $_SESSION, 'La sesión se destruye');
    }

    public function testRN14LaSesionExpiraPorInactividad(): void
    {
        $this->iniciarComo('caja');
        $_SESSION['ultima_actividad'] = time() - (int) config('sesion_inactividad') - 1;
        $r = $this->solicitud('GET', '/caja');
        $this->assertSame(303, $r->status);
        $this->assertSame('/login?expirado=1&volver=caja', $r->headers['Location'] ?? null);
    }

    public function testMensajeGenericoYLimiteDeIntentos(): void
    {
        $auth = new AuthService($this->db());
        $inexistente = $this->assertRegla('CREDENCIALES', fn () => $auth->intentar('nadie', 'x', '10.0.0.9'));
        $claveMala = $this->assertRegla('CREDENCIALES', fn () => $auth->intentar('caja', 'x', '10.0.0.9'));
        $this->assertSame($inexistente->getMessage(), $claveMala->getMessage(), 'Mismo mensaje exista o no el usuario');

        for ($i = 0; $i < (int) config('login_max_intentos'); $i++) {
            try {
                $auth->intentar('caja', 'incorrecta', '10.0.0.9');
            } catch (ReglaNegocioException) {
            }
        }
        $this->assertRegla('BLOQUEADO', fn () => $auth->intentar('caja', 'demo2026*', '10.0.0.9'));
    }

    public function testLoginCorrectoRegeneraLaSesionYAuditaElIngreso(): void
    {
        $_SESSION['_csrf'] = 'token-anterior';
        $r = $this->solicitud('POST', '/login', ['login' => 'caja', 'clave' => 'demo2026*', '_csrf' => 'token-anterior']);
        $this->assertSame(303, $r->status);
        $this->assertSame('/caja', $r->headers['Location'] ?? null, 'Caja inicia en su panel');
        $this->assertSame(3, $_SESSION['id_usuario'] ?? null);
        $this->assertTrue(($_SESSION['_csrf'] ?? '') !== 'token-anterior', 'El token CSRF se renueva al autenticar');
        $this->assertSame(1, (int) $this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'LOGIN_EXITOSO' AND id_usuario = 3"));
        $this->assertTrue($this->valor('SELECT ultimo_acceso FROM usuarios WHERE id_usuario = 3') !== null);
    }

    public function testLaMatrizDePermisosDeLaBaseCoincideConLaDocumentada(): void
    {
        $esperada = (require BASE_PATH . '/config/permissions.php')['roles'];
        foreach ($esperada as $rol => $permisos) {
            $stmt = $this->db()->prepare(
                'SELECT p.codigo FROM roles r JOIN rol_permiso rp ON rp.id_rol = r.id_rol
                   JOIN permisos p ON p.id_permiso = rp.id_permiso WHERE r.nombre = ? ORDER BY p.codigo'
            );
            $stmt->execute([$rol]);
            $real = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            sort($permisos);
            $this->assertSame($permisos, $real, "Permisos del rol {$rol}");
        }
    }

    public function testNoSePuedeDesactivarAlUltimoAdministradorNiLaPropiaCuenta(): void
    {
        $servicio = new \App\Services\ServicioUsuario($this->db());
        $this->assertRegla('PROPIA_CUENTA', fn () => $servicio->cambiarEstado(1, false, 1));
        // Con datos de demostración hay dos administradores: se desactiva admin2 y queda solo admin.
        $servicio->cambiarEstado((int) $this->valor("SELECT id_usuario FROM usuarios WHERE login = 'admin2'"), false, 1);
        $this->assertRegla('ULTIMO_ADMIN', fn () => $servicio->cambiarEstado(1, false, 4));
    }
}
