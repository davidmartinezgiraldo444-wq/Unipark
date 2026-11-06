<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Repositories\UsuarioRepository;
use App\Services\ReglaNegocioException;
use App\Services\ServicioUsuario;

final class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('usuarios/index', [
            'titulo'   => 'Usuarios',
            'usuarios' => (new UsuarioRepository($this->db()))->list(),
        ]);
    }

    public function nuevo(Request $request): Response
    {
        return $this->form(null, [], null);
    }

    public function crear(Request $request): Response
    {
        $datos = ['nombre' => $request->input('nombre'), 'login' => $request->input('login'), 'id_rol' => $request->input('id_rol')];
        try {
            $r = (new ServicioUsuario($this->db()))->crear($datos['nombre'], $datos['login'], (int) $datos['id_rol']);
        } catch (ReglaNegocioException $e) {
            return $this->form(null, $datos, $e->getMessage(), 422);
        }
        $this->flash('clave', $r['clave']);
        $this->flash('ok', 'Cuenta creada. Entrega la contraseña temporal a la persona: deberá cambiarla al ingresar.');
        return $this->redirect('usuarios/' . $r['id'] . '/editar');
    }

    public function editar(Request $request, int $id): Response
    {
        $usuario = (new UsuarioRepository($this->db()))->find($id);
        return $usuario ? $this->form($usuario, $usuario, null) : $this->notFound();
    }

    public function actualizar(Request $request, int $id): Response
    {
        $usuario = (new UsuarioRepository($this->db()))->find($id);
        if ($usuario === null) {
            return $this->notFound();
        }
        $datos = ['nombre' => $request->input('nombre'), 'id_rol' => $request->input('id_rol')] + $usuario;
        try {
            (new ServicioUsuario($this->db()))->actualizar($id, $datos['nombre'], (int) $datos['id_rol'], $this->usuarioId());
        } catch (ReglaNegocioException $e) {
            return $this->form($usuario, $datos, $e->getMessage(), 422);
        }
        $this->flash('ok', 'Cuenta actualizada.');
        return $this->redirect('usuarios');
    }

    public function estado(Request $request, int $id): Response
    {
        $activar = $request->input('activo') === '1';
        try {
            (new ServicioUsuario($this->db()))->cambiarEstado($id, $activar, $this->usuarioId());
            $this->flash('ok', $activar ? 'Cuenta activada.' : 'Cuenta desactivada. Ya no puede iniciar sesión.');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        return $this->redirect('usuarios');
    }

    public function clave(Request $request, int $id): Response
    {
        try {
            $clave = (new ServicioUsuario($this->db()))->restablecerClave($id);
            $this->flash('clave', $clave);
            $this->flash('ok', 'Acceso restablecido. La persona deberá cambiar la contraseña temporal al ingresar.');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        return $this->redirect('usuarios/' . $id . '/editar');
    }

    private function form(?array $usuario, array $datos, ?string $error, int $estado = 200): Response
    {
        return $this->view('usuarios/form', [
            'titulo'  => $usuario ? 'Editar usuario' : 'Nuevo usuario',
            'usuario' => $usuario,
            'datos'   => $datos,
            'error'   => $error,
            'roles'   => (new UsuarioRepository($this->db()))->roles(),
        ], 'layouts/app', $estado);
    }
}
