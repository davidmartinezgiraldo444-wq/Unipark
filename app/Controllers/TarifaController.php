<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Repositories\TarifaRepository;
use App\Services\ReglaNegocioException;
use App\Services\ServicioTarifa;

final class TarifaController extends Controller
{
    public function index(Request $request): Response
    {
        $repo = new TarifaRepository($this->db());
        return $this->view('tarifas/index', [
            'titulo'    => 'Tarifas',
            'vigentes'  => $repo->vigentes(),
            'historial' => $repo->historial(),
        ]);
    }

    public function cambiar(Request $request): Response
    {
        $tipo = $request->input('tipo');
        $categoria = $request->input('categoria');
        try {
            (new ServicioTarifa($this->db()))->cambiar($tipo, $categoria, $request->input('valor'), $this->usuarioId());
            $this->flash('ok', sprintf('Nueva tarifa de %s · %s vigente desde ahora. Las visitas existentes conservan su valor.', $tipo, categoria_label($categoria)));
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        return $this->redirect('tarifas');
    }
}
