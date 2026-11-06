<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\View;
use App\Services\AuditService;
use App\Services\AuthService;
use PDO;

/**
 * Verifica en el servidor el permiso de la ruta (RF-02). Ocultar un botón no protege nada:
 * esta comprobación aplica aunque se llame la URL directamente (CP-08).
 */
final class RoleMiddleware
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param list<string> $permisos basta con tener uno */
    public function handle(Request $request, array $permisos): ?Response
    {
        if (AuthService::can(...$permisos)) {
            return null;
        }
        (new AuditService($this->pdo))->log('ACCESO_DENEGADO', 'ruta', null, 'DENEGADO', 'Permiso insuficiente', [
            'ruta'      => $request->method . ' ' . $request->path,
            'requiere'  => $permisos,
        ]);
        return Response::html(View::render('errors/error', [
            'titulo'  => 'Acceso no autorizado',
            'codigo'  => 403,
            'mensaje' => 'Tu rol no tiene permiso para esta operación. El intento quedó registrado.',
        ]), 403);
    }
}
