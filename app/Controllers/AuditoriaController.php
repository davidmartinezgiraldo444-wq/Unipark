<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\AuditoriaRepository;
use App\Repositories\UsuarioRepository;

final class AuditoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $repo = new AuditoriaRepository($this->db());
        $acciones = $repo->acciones();
        $f = [
            'desde'     => $request->query('desde', date('Y-m-d', strtotime('-7 days'))),
            'hasta'     => $request->query('hasta', date('Y-m-d')),
            'accion'    => $request->query('accion'),
            'resultado' => $request->query('resultado'),
            'usuario'   => $request->query('usuario'),
        ];
        foreach (['desde', 'hasta'] as $c) {
            if ($f[$c] !== '' && Validator::aFecha($f[$c]) === null) {
                $f[$c] = '';
            }
        }
        $f['accion'] = in_array($f['accion'], $acciones, true) ? $f['accion'] : '';
        $f['resultado'] = in_array($f['resultado'], ['EXITO', 'DENEGADO', 'ERROR'], true) ? $f['resultado'] : '';
        $f['usuario'] = ctype_digit($f['usuario']) ? $f['usuario'] : '';

        return $this->view('auditoria/index', [
            'titulo'   => 'Auditoría',
            'filtros'  => $f,
            'eventos'  => $repo->buscar($f),
            'acciones' => $acciones,
            'usuarios' => (new UsuarioRepository($this->db()))->conActividad(),
        ]);
    }
}
