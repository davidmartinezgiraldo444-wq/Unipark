<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\ReglaNegocioException;

final class AuthController extends Controller
{
    public function form(Request $request): Response
    {
        $volver = volver_seguro($request->query('volver'));
        if (Session::get('id_usuario')) {
            return $this->redirect($volver ?? 'panel');
        }
        $aviso = match (true) {
            $request->query('expirado') !== '' => 'Tu sesión expiró por inactividad. Ingresa de nuevo.',
            $request->query('salida') !== ''   => 'Cerraste sesión correctamente.',
            $volver !== null                   => 'Ingresa para continuar.',
            default                            => null,
        };
        return $this->view('auth/login', ['titulo' => 'Iniciar sesión', 'aviso' => $aviso, 'error' => null, 'login' => '', 'volver' => $volver], 'layouts/auth');
    }

    /** Ruta segura a la que volver tras iniciar sesión (se conserva si el intento falla). */
    private ?string $volver = null;

    public function login(Request $request): Response
    {
        $this->volver = volver_seguro($request->input('volver'));
        $login = mb_strtolower($request->input('login'));
        $clave = $request->raw('clave');

        if ($login === '' || $clave === '') {
            return $this->formError('Ingresa tu usuario y tu contraseña.', $login, 422);
        }
        if (mb_strlen($login) > 120 || strlen($clave) > 255) {
            return $this->formError(AuthService::MENSAJE_GENERICO, '', 422);
        }

        $auth = new AuthService($this->db());
        try {
            $usuario = $auth->intentar($login, $clave, $request->ip);
        } catch (ReglaNegocioException $e) {
            return $this->formError($e->getMessage(), $login, $e->codigo === 'BLOQUEADO' ? 429 : 401);
        }

        $auth->iniciarSesion($usuario);
        if ((int) $usuario['debe_cambiar_clave'] === 1) {
            return $this->redirect('cambiar-clave');
        }
        // Regreso a la página que se pidió (solo rutas internas seguras); el permiso se verifica allí de nuevo.
        if ($this->volver !== null) {
            return $this->redirect($this->volver);
        }
        $inicio = (require BASE_PATH . '/config/permissions.php')['inicio'];
        return $this->redirect($inicio[$usuario['rol']] ?? 'panel');
    }

    public function logout(Request $request): Response
    {
        (new AuthService($this->db()))->cerrarSesion();
        return $this->redirect('login?salida=1');
    }

    public function formClave(Request $request): Response
    {
        return $this->view('auth/cambiar_clave', ['titulo' => 'Cambiar contraseña', 'error' => null], 'layouts/auth');
    }

    public function cambiarClave(Request $request): Response
    {
        $auth = new AuthService($this->db());
        try {
            $auth->cambiarClave(
                $this->usuarioId(),
                $request->raw('actual'),
                $request->raw('nueva'),
                $request->raw('confirmacion'),
            );
        } catch (ReglaNegocioException $e) {
            return $this->view('auth/cambiar_clave', ['titulo' => 'Cambiar contraseña', 'error' => $e->getMessage()], 'layouts/auth', 422);
        }
        $this->flash('ok', 'Contraseña actualizada.');
        $usuario = (new UsuarioRepository($this->db()))->find($this->usuarioId());
        $inicio = (require BASE_PATH . '/config/permissions.php')['inicio'];
        return $this->redirect($inicio[$usuario['rol']] ?? 'panel');
    }

    private function formError(string $error, string $login, int $estado): Response
    {
        return $this->view('auth/login', ['titulo' => 'Iniciar sesión', 'aviso' => null, 'error' => $error, 'login' => $login, 'volver' => $this->volver], 'layouts/auth', $estado);
    }
}
