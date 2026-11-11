<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Repositories\UsuarioRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use PDO;

/**
 * Exige una sesión válida en cada solicitud protegida.
 * La cuenta y sus permisos se leen de la base en cada petición: una cuenta desactivada
 * o un cambio de rol surten efecto de inmediato, sin esperar a que expire la sesión.
 */
final class AuthMiddleware
{
    private const SIN_CAMBIO_CLAVE = ['/cambiar-clave', '/logout'];

    public function __construct(private PDO $pdo)
    {
    }

    public function handle(Request $request, bool $cuentaComoActividad = true): ?Response
    {
        $id = (int) Session::get('id_usuario', 0);
        if ($id === 0) {
            return Response::redirect(url('login' . self::volver($request)));
        }

        $usuarios = new UsuarioRepository($this->pdo);
        $usuario = $usuarios->find($id);
        if ($usuario === null || (int) $usuario['activo'] !== 1 || (int) $usuario['rol_activo'] !== 1) {
            Session::destroy();
            return Response::redirect(url('login'));
        }

        // RN-14: expiración por inactividad. Los dispositivos de portería (punto de acceso, cámara) y el
        // panel del portero trabajan un turno completo sin interacción: tienen su propio límite por rol.
        $ultima = (int) Session::get('ultima_actividad', 0);
        if (time() - $ultima > self::limiteInactividad($usuario['rol'])) {
            (new AuditService($this->pdo))->log('SESION_EXPIRADA', 'usuarios', $id, 'EXITO', null, [], $id);
            Session::destroy();
            return Response::redirect(url('login?expirado=1' . str_replace('?', '&', self::volver($request))));
        }

        AuthService::setUsuario($usuario, $usuarios->permisos($id));
        if ($cuentaComoActividad) {
            Session::set('ultima_actividad', time());
        }

        if ((int) $usuario['debe_cambiar_clave'] === 1 && !in_array($request->path, self::SIN_CAMBIO_CLAVE, true)) {
            return Response::redirect(url('cambiar-clave'));
        }
        return null;
    }

    /**
     * «?volver=…» para regresar a la página pedida después de iniciar sesión (por ejemplo, el estado de una
     * recarga cuando la sesión venció durante el pago). Solo GET de pantallas y solo rutas internas seguras.
     */
    private static function volver(Request $request): string
    {
        if ($request->method !== 'GET' || str_starts_with($request->path, '/api/')) {
            return '';
        }
        $ruta = volver_seguro($request->path);
        return $ruta !== null && !in_array($ruta, ['panel'], true) ? '?volver=' . rawurlencode($ruta) : '';
    }

    /** Segundos de inactividad permitidos para el rol (config/app.php, sesion_inactividad_por_rol). */
    public static function limiteInactividad(string $rol): int
    {
        $porRol = (array) config('sesion_inactividad_por_rol', []);
        return (int) ($porRol[$rol] ?? config('sesion_inactividad'));
    }
}
