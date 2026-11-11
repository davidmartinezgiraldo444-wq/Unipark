<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Logger;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Repositories\CobroRepository;
use App\Services\ReglaNegocioException;
use App\Services\ServicioCobro;

/** Disparadores del cobro automático y anulación de cobros. */
final class CobroController extends Controller
{
    /**
     * Latido: las pantallas abiertas de Portería, Caja y Mi cuenta lo llaman cada pocos segundos.
     * Procesa las tolerancias vencidas aunque no haya cron configurado (útil en la demostración).
     */
    public function procesar(Request $request): Response
    {
        $r = (new ServicioCobro($this->db()))->procesarVencidos(50);
        $r['eventos_expirados'] = (new \App\Services\ServicioAcceso($this->db()))->expirarVencidos();
        return Response::json(['ok' => true] + $r);
    }

    /** Cron por URL para hostings sin cron de consola: /cron/cobros?token=… */
    public function cron(Request $request): Response
    {
        // Solo con una ficha larga (≥ 32 caracteres) configurada en el servidor; si no, queda deshabilitado.
        // Lo recomendado es el cron de consola (scripts/cobros.php), que no expone nada por la web.
        $token = (string) config('cron_token');
        if (strlen($token) < 32 || !hash_equals($token, $request->query('token'))) {
            return Response::json(['ok' => false, 'error' => 'No autorizado'], 403);
        }
        $r = (new ServicioCobro($this->db()))->procesarVencidos(500);
        $r['eventos_expirados'] = (new \App\Services\ServicioAcceso($this->db()))->expirarVencidos();
        if ($r['procesadas'] > 0) {
            Logger::info('Cobro automático (cron URL)', $r);
        }
        return Response::json(['ok' => true] + $r);
    }

    /**
     * Resolver una incidencia: Caja o Portería procesan el cobro de UNA visita cuya tolerancia venció
     * sin que el proceso automático corriera. Es una acción aparte de la salida (la salida solo consulta).
     */
    public function procesarVisita(Request $request, int $id): Response
    {
        $r = (new ServicioCobro($this->db()))->liquidar($id);
        $this->flash('ok', $this->mensajeLiquidacion($r));
        $volver = $request->input('volver');
        return $this->redirect(in_array($volver, ['caja', 'porteria'], true) ? $volver . ($volver === 'porteria' && $request->input('documento') !== '' ? '?documento=' . rawurlencode($request->input('documento')) : '') : 'caja');
    }

    /** Lo mismo desde el escáner del celular (JSON). */
    public function procesarVisitaApi(Request $request, int $id): Response
    {
        $r = (new ServicioCobro($this->db()))->liquidar($id);
        return Response::json(['ok' => true, 'mensaje' => $this->mensajeLiquidacion($r), 'resultado' => $r]);
    }

    private function mensajeLiquidacion(?array $r): string
    {
        if ($r === null) {
            return 'La visita ya estaba procesada o ya salió: no había nada pendiente.';
        }
        return match (true) {
            $r['liquidacion'] === 'NO_APLICA'  => 'Incidencia resuelta: la visita no genera cobro.',
            $r['liquidacion'] === 'CUBIERTA'   => 'Incidencia resuelta: ya tenía el cobro de hoy.',
            $r['estado_cobro'] === 'PAGADO'    => 'Incidencia resuelta: se generó el cobro del día y se pagó con el saldo.',
            default                            => 'Incidencia resuelta: se generó el cobro del día; queda pendiente de pago en Caja (sin saldo suficiente).',
        };
    }

    public function anular(Request $request, int $id): Response
    {
        $cobro = (new CobroRepository($this->db()))->find($id);
        try {
            (new ServicioCobro($this->db()))->anular($id, $this->usuarioId(), $request->input('motivo'));
            $this->flash('ok', 'El cobro quedó anulado. La persona ya no lo debe; el motivo quedó en la auditoría.');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        return $this->redirect($cobro ? 'estudiantes/' . $cobro['id_estudiante'] : 'reportes');
    }
}
