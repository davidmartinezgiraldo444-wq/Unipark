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
        // Estudiantes, profesores y trabajadores solo tienen su cuenta propia.
        if (can('CUENTA_PROPIA') && !can('ENTRADA_CREAR', 'PAGO_REGISTRAR', 'REPORTE_OPERATIVO', 'USUARIO_GESTIONAR')) {
            return $this->redirect('mi-cuenta');
        }
        $visitas = new VisitaRepository($this->db());
        $datos = [
            'titulo'    => 'Panel',
            'ocupacion' => $visitas->ocupacion(),
            'tarifas'   => (new TarifaRepository($this->db()))->vigentes(),
            'recaudo'   => can('REPORTE_CAJA') ? (new PagoRepository($this->db()))->totalDelDia() : null,
            'abiertas'  => can('REPORTE_OPERATIVO') ? array_slice($visitas->abiertas(), 0, 8) : [],
            'gestion'   => can('REPORTE_CAJA', 'CLASIFICACION_RESOLVER') ? $this->gestion($visitas) : null,
        ];
        return $this->view('dashboard/index', $datos);
    }
    /** Indicadores de control: deuda pendiente, movimientos de saldo del día, cobros sin procesar y eventos en portería. */
    private function gestion(VisitaRepository $visitas): array
    {
        $pdo = $this->db();
        $deuda = $pdo->query("SELECT COUNT(*) AS cantidad, COALESCE(SUM(valor), 0) AS total FROM cobros_diarios WHERE estado = 'PENDIENTE'")->fetch();
        $saldo = $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN tipo = 'RECARGA' THEN monto WHEN tipo = 'REVERSO_RECARGA' THEN -monto END), 0) AS recargas,
                    COALESCE(SUM(CASE WHEN tipo = 'DEBITO' THEN monto END), 0) AS debitos
               FROM movimientos_saldo WHERE creado_en >= CURDATE()"
        )->fetch();
        return [
            'deuda'       => $deuda,
            'recargas'    => $saldo['recargas'],
            'debitos'     => $saldo['debitos'],
            'sin_procesar' => count($visitas->incidenciasDeCobro()),
            'eventos'     => (int) $pdo->query("SELECT COUNT(*) FROM eventos_acceso WHERE estado = 'PENDIENTE_PORTERO'")->fetchColumn(),
        ];
    }
}
