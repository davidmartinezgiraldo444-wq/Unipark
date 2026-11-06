<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Kernel;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\ReglaNegocioException;
use PDO;

/**
 * Base mínima de pruebas: aserciones y ayudas para la base de datos y el Kernel.
 * Las pruebas de integración se conectan con la cuenta de la aplicación (sin DELETE),
 * igual que en producción; el reinicio de la base usa la cuenta administrativa.
 */
abstract class TestCase
{
    /** Las pruebas que tocan la base la reinician antes de cada caso. */
    protected bool $usaBaseDeDatos = false;

    public function setUp(): void
    {
        $_SESSION = [];
        AuthService::setUsuario(null);
        AuditService::$ip = '127.0.0.1';
        if ($this->usaBaseDeDatos) {
            TestDatabase::reset();
            Database::set(null);
        }
    }

    public function tearDown(): void
    {
        $pdo = Database::current();
        if ($pdo !== null && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    protected function db(): PDO
    {
        return Database::connection();
    }

    protected function valor(string $sql, array $params = []): mixed
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    protected function fila(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /** Simula una sesión iniciada para el login indicado (usuarios de database/seed.sql). */
    protected function iniciarComo(string $login): int
    {
        $id = (int) $this->valor('SELECT id_usuario FROM usuarios WHERE login = ?', [$login]);
        $this->assertTrue($id > 0, "El usuario de prueba '{$login}' no existe");
        $_SESSION = ['id_usuario' => $id, 'ultima_actividad' => time()];
        Csrf::rotate();
        return $id;
    }

    /** Envía una solicitud por el mismo recorrido que la web: CSRF, sesión, permiso y controlador. */
    protected function solicitud(string $metodo, string $ruta, array $datos = [], bool $conCsrf = true): Response
    {
        if ($metodo === 'POST' && $conCsrf && !isset($datos['_csrf'])) {
            $datos['_csrf'] = Csrf::token();
        }
        [$path, $qs] = array_pad(explode('?', $ruta, 2), 2, '');
        parse_str($qs, $query);
        $request = new Request($metodo, $path, $metodo === 'GET' ? $query + $datos : $query, $metodo === 'POST' ? $datos : []);
        return (new Kernel())->handle($request);
    }

    // --- Aserciones ---------------------------------------------------------

    protected function assertTrue(mixed $valor, string $mensaje = 'Se esperaba verdadero'): void
    {
        if ($valor !== true) {
            throw new AssertionFailed($mensaje);
        }
    }

    protected function assertFalse(mixed $valor, string $mensaje = 'Se esperaba falso'): void
    {
        if ($valor !== false) {
            throw new AssertionFailed($mensaje);
        }
    }

    protected function assertSame(mixed $esperado, mixed $real, string $mensaje = ''): void
    {
        if ($esperado !== $real) {
            throw new AssertionFailed(($mensaje ? $mensaje . ': ' : '')
                . 'se esperaba ' . var_export($esperado, true) . ', se obtuvo ' . var_export($real, true));
        }
    }

    protected function assertNull(mixed $valor, string $mensaje = 'Se esperaba null'): void
    {
        $this->assertSame(null, $valor, $mensaje);
    }

    protected function assertContains(string $aguja, string $pajar, string $mensaje = ''): void
    {
        if (!str_contains($pajar, $aguja)) {
            throw new AssertionFailed(($mensaje ? $mensaje . ': ' : '') . "no se encontró «{$aguja}»");
        }
    }

    protected function assertNotContains(string $aguja, string $pajar, string $mensaje = ''): void
    {
        if (str_contains($pajar, $aguja)) {
            throw new AssertionFailed(($mensaje ? $mensaje . ': ' : '') . "no debía aparecer «{$aguja}»");
        }
    }

    /** Ejecuta $fn y exige una regla de negocio rechazada con el código indicado. */
    protected function assertRegla(string $codigo, callable $fn): ReglaNegocioException
    {
        try {
            $fn();
        } catch (ReglaNegocioException $e) {
            $this->assertSame($codigo, $e->codigo, 'Código de la regla');
            return $e;
        }
        throw new AssertionFailed("Se esperaba el rechazo {$codigo} y la operación se completó");
    }

    /** Exige que la base de datos rechace la sentencia (restricción, índice único o disparador). */
    protected function assertSqlFalla(callable $fn, string $mensaje): \PDOException
    {
        try {
            $fn();
        } catch (\PDOException $e) {
            return $e;
        }
        throw new AssertionFailed($mensaje);
    }

    protected function skip(string $motivo): never
    {
        throw new Skipped($motivo);
    }
}
