<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Repositories\PagoRepository;
use App\Repositories\TarifaRepository;
use App\Repositories\VisitaRepository;

final class DashboardController extends Controller
{
    public function raiz(Request $request): Response
    {
        return $this->redirect(Session::get('id_usuario') ? 'panel' : 'login');
    }

    public function landing(Request $request): Response
    {
        // La página pública muestra las tarifas vigentes; si la base no responde, se muestra igual sin valores.
        try {
            $tarifas = (new TarifaRepository($this->db()))->vigentes();
        } catch (\Throwable) {
            $tarifas = [];
        }
        return Response::html(View::render('landing', [
            'conectado' => (bool) Session::get('id_usuario'),
            'tarifas'   => $tarifas,
        ], null));
    }

    public function index(Request $request): Response
    {
        $visitas = new VisitaRepository($this->db());
        $datos = [
            'titulo'    => 'Panel',
            'ocupacion' => $visitas->ocupacion(),
            'tarifas'   => (new TarifaRepository($this->db()))->vigentes(),
            'recaudo'   => can('REPORTE_CAJA') ? (new PagoRepository($this->db()))->totalDelDia() : null,
            'abiertas'  => can('REPORTE_OPERATIVO') ? array_slice($visitas->abiertas(), 0, 8) : [],
        ];
        return $this->view('dashboard/index', $datos);
    }
}
