<?php
declare(strict_types=1);

namespace App;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Logger;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\AuditService;
use App\Services\AuthService;

/**
 * Recorrido de una solicitud: ruta → CSRF → sesión → permiso → controlador.
 * Todo error inesperado se registra en el log y se responde con un mensaje genérico (RF-18).
 */
final class Kernel
{
    /** @var list<array> */
    private array $rutas;

    public function __construct()
    {
        $this->rutas = require __DIR__ . '/routes.php';
    }

    public function handle(Request $request): Response
    {
        AuditService::$ip = $request->ip;
        AuthService::setUsuario(null);

        try {
            [$ruta, $parametros, $otroMetodo] = $this->match($request);
            if ($ruta === null) {
                return $otroMetodo
                    ? $this->error(405, 'Método no permitido', 'Esta dirección no admite el método usado.')
                    : $this->error(404, 'Página no encontrada', 'La dirección no existe o fue movida.');
            }

            if ($request->method === 'POST' && !Csrf::valid($request->post['_csrf'] ?? null)) {
                (new AuditService(Database::connection()))->log('CSRF_INVALIDO', 'ruta', null, 'DENEGADO', 'Token ausente o inválido', [
                    'ruta' => $request->method . ' ' . $request->path,
                ]);
                return $this->error(419, 'La solicitud no es válida',
                    'El formulario expiró o no proviene de UniPark. Vuelve a la página anterior, recárgala e inténtalo de nuevo.');
            }

            if ($ruta['auth'] ?? true) {
                $respuesta = (new AuthMiddleware(Database::connection()))->handle($request);
                if ($respuesta !== null) {
                    return $respuesta;
                }
            }

            if (!empty($ruta['perm'])) {
                $respuesta = (new RoleMiddleware(Database::connection()))->handle($request, (array) $ruta['perm']);
                if ($respuesta !== null) {
                    return $respuesta;
                }
            }

            [$clase, $metodo] = $ruta[2];
            return (new $clase())->$metodo($request, ...$parametros);
        } catch (\Throwable $e) {
            $pdo = Database::current();
            if ($pdo !== null && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error($e->getMessage(), [
                'tipo'    => $e::class,
                'archivo' => $e->getFile() . ':' . $e->getLine(),
                'ruta'    => $request->method . ' ' . $request->path,
                'usuario' => AuthService::id(),
            ]);
            if (config('debug')) {
                throw $e;
            }
            return $this->error(500, 'Ocurrió un error', 'No se pudo completar la operación. Inténtalo de nuevo; si persiste, avisa al Administrador.');
        }
    }

    /** @return array{0:?array,1:list<int>,2:bool} */
    private function match(Request $request): array
    {
        $otroMetodo = false;
        foreach ($this->rutas as $ruta) {
            $patron = '#^' . preg_replace('#\{[a-z]+\}#', '(\d+)', $ruta[1]) . '$#';
            if (!preg_match($patron, $request->path, $m)) {
                continue;
            }
            if ($ruta[0] !== $request->method) {
                $otroMetodo = true;
                continue;
            }
            return [$ruta, array_map('intval', array_slice($m, 1)), false];
        }
        return [null, [], $otroMetodo];
    }

    private function error(int $codigo, string $titulo, string $mensaje): Response
    {
        $layout = AuthService::usuario() ? 'layouts/app' : 'layouts/plain';
        return Response::html(View::render('errors/error', compact('codigo', 'titulo', 'mensaje'), $layout), $codigo);
    }
}
