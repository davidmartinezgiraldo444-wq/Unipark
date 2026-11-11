<?php
declare(strict_types=1);

namespace App;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Logger;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
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
    /** La solicitud en curso es de la API JSON (/api/…): los errores se responden en JSON. */
    private bool $api = false;

    public function __construct()
    {
        $this->rutas = require __DIR__ . '/routes.php';
    }

    public function handle(Request $request): Response
    {
        AuditService::$ip = $request->ip;
        AuthService::setUsuario(null);
        $this->api = $this->esApi($request);

        try {
            [$ruta, $parametros, $otroMetodo] = $this->match($request);
            if ($ruta === null) {
                return $otroMetodo
                    ? $this->error(405, 'Método no permitido', 'Esta dirección no admite el método usado.')
                    : $this->error(404, 'Página no encontrada', 'La dirección no existe o fue movida.');
            }

            $requiereSesion = $ruta['auth'] ?? true;

            // API JSON: primero la sesión. Una pestaña con la sesión vencida recibe 401 en JSON (y deja de
            // insistir) en lugar de un 419 en HTML y un registro de auditoría cada pocos segundos.
            if ($this->api && $requiereSesion && !Session::get('id_usuario')) {
                return $this->errorJson(401, 'La sesión expiró. Ingresa de nuevo.', 'SESION_EXPIRADA');
            }

            // 'csrf' => false solo en rutas públicas que reciben llamadas de servidor a servidor (webhooks de
            // la pasarela): no usan la sesión ni cookies, y su autenticidad se verifica con la firma del proveedor.
            $exigeCsrf = ($ruta['csrf'] ?? true) || $requiereSesion;
            if ($request->method === 'POST' && $exigeCsrf && !Csrf::valid($request->post['_csrf'] ?? null)) {
                // Solo se audita si hay una sesión activa: un formulario viejo tras cerrar sesión no es un ataque.
                if (Session::get('id_usuario')) {
                    (new AuditService(Database::connection()))->log('CSRF_INVALIDO', 'ruta', null, 'DENEGADO', 'Token ausente o inválido', [
                        'ruta' => $request->method . ' ' . $request->path,
                    ], (int) Session::get('id_usuario'));
                }
                return $this->api
                    ? $this->errorJson(419, 'La solicitud no es válida. Recarga la página.', 'CSRF')
                    : $this->error(419, 'La solicitud no es válida',
                        'El formulario expiró o no proviene de UniPark. Vuelve a la página anterior, recárgala e inténtalo de nuevo.');
            }

            if ($requiereSesion) {
                // 'actividad' => false: llamadas automáticas que no cuentan como actividad de la persona.
                $respuesta = (new AuthMiddleware(Database::connection()))->handle($request, $ruta['actividad'] ?? true);
                if ($respuesta !== null) {
                    return $this->api ? $this->errorJson(401, 'La sesión expiró. Ingresa de nuevo.', 'SESION_EXPIRADA') : $respuesta;
                }
            }

            if (!empty($ruta['perm'])) {
                $respuesta = (new RoleMiddleware(Database::connection()))->handle($request, (array) $ruta['perm']);
                if ($respuesta !== null) {
                    return $this->api ? $this->errorJson(403, 'Tu rol no tiene permiso para esta operación.', 'SIN_PERMISO') : $respuesta;
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

    /**
     * Rutas que no deben iniciar sesión ('sesion' => false): llamadas de servidor a servidor y el regreso desde la
     * pasarela. Se consulta en index.php ANTES de Session::start(), para no emitir una cookie de sesión nueva.
     */
    public static function sinSesion(string $metodo, string $path): bool
    {
        foreach (require __DIR__ . '/routes.php' as $ruta) {
            if (($ruta['sesion'] ?? true) === false && $ruta[0] === $metodo && $ruta[1] === $path) {
                return true;
            }
        }
        return false;
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

    private function esApi(Request $request): bool
    {
        return str_starts_with($request->path, '/api/');
    }

    /** Formato único de error de la API: {ok:false, codigo, error}. */
    private function errorJson(int $codigo, string $mensaje, ?string $clave = null): Response
    {
        return Response::json(['ok' => false, 'codigo' => $clave ?? (string) $codigo, 'error' => $mensaje], $codigo);
    }

    private function error(int $codigo, string $titulo, string $mensaje): Response
    {
        if ($this->api) {
            return $this->errorJson($codigo, $mensaje);
        }
        $layout = AuthService::usuario() ? 'layouts/app' : 'layouts/plain';
        return Response::html(View::render('errors/error', compact('codigo', 'titulo', 'mensaje'), $layout), $codigo);
    }
}
