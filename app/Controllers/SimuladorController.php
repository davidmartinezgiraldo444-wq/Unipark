<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\View;
use App\Services\Pasarela\PasarelaException;
use App\Services\Pasarela\PasarelaSimulada;
use App\Services\Pasarela\Pasarelas;
use App\Services\ServicioRecargaLinea;

/**
 * Checkout SIMULADO dentro de UniPark (solo en la copia de demostración). Hace el papel de la página de pago:
 * la persona elige el resultado y el simulador entrega una notificación firmada al MISMO procesador del webhook que
 * usa Wompi; el servidor verifica la firma, consulta la transacción y acredita una sola vez.
 * Seguridad: solo la persona dueña de la recarga (sesión + CSRF + referencia, monto y estado verificados); el panel
 * (resolver pendientes, reenviar) es solo de Administración. Con otra pasarela o en producción, todo responde 404.
 */
final class SimuladorController extends Controller
{
    private const RESULTADOS = [
        'APPROVED' => 'Aprobar el pago (tarjeta de prueba)',
        'DECLINED' => 'Rechazar el pago',
        'PENDING'  => 'Dejar pendiente (PSE en proceso)',
        'ERROR'    => 'Error de la pasarela',
    ];

    public function pagina(Request $request): Response
    {
        $sim = $this->simulador();
        if ($sim === null) {
            return $this->noDisponible();
        }
        $pago = $this->pagoDesde($request, $sim);
        return $this->pantalla('simulador/pago', [
            'titulo' => 'Pago simulado', 'pago' => $pago, 'resultados' => self::RESULTADOS,
        ], $pago === null ? 422 : 200);
    }

    /** La persona eligió el resultado: se crea la transacción, se notifica al webhook y se vuelve a UniPark. */
    public function pagar(Request $request): Response
    {
        $sim = $this->simulador();
        if ($sim === null) {
            return $this->noDisponible();
        }
        $pago = $this->pagoDesde($request, $sim);
        $estado = $request->input('resultado');
        if ($pago === null || !isset(self::RESULTADOS[$estado])) {
            return $this->pantalla('simulador/pago', ['titulo' => 'Pago simulado', 'pago' => null, 'resultados' => self::RESULTADOS], 422);
        }
        $id = $sim->crearTransaccion($pago['reference'], (int) $pago['centavos'], $estado);
        $this->notificar($sim, $id);
        $separador = str_contains($pago['retorno'], '?') ? '&' : '?';
        return Response::redirect($pago['retorno'] . $separador . 'id=' . rawurlencode($id));
    }

    /** Panel del simulador: transacciones, resolver pendientes y reenviar una notificación (duplicado). */
    public function panel(Request $request): Response
    {
        $sim = $this->simulador();
        if ($sim === null) {
            return $this->noDisponible();
        }
        return $this->pantalla('simulador/panel', ['titulo' => 'Panel del simulador', 'transacciones' => $sim->transacciones(), 'mensaje' => $request->query('m')]);
    }

    public function accion(Request $request): Response
    {
        $sim = $this->simulador();
        if ($sim === null) {
            return $this->noDisponible();
        }
        $id = $request->input('id');
        try {
            $mensaje = match ($request->input('accion')) {
                'aprobar'  => $this->resolver($sim, $id, 'APPROVED'),
                'rechazar' => $this->resolver($sim, $id, 'DECLINED'),
                'reenviar' => 'Notificación reenviada: ' . $this->notificar($sim, $id),
                'reembolsar' => $this->reembolsar($sim, $id),
                default    => 'Acción no válida.',
            };
        } catch (PasarelaException $e) {
            $mensaje = $e->getMessage();
        }
        return Response::redirect(url('simulador-pago/panel?m=' . rawurlencode($mensaje)));
    }

    // ------------------------------------------------------------------ Ayudas

    /** El proveedor anula (reembolsa) un pago aprobado y avisa por el webhook; UniPark lo deja para revisión. */
    private function reembolsar(PasarelaSimulada $sim, string $id): string
    {
        $sim->reembolsar($id);
        return 'Transacción ' . $id . ' reembolsada por el proveedor (VOIDED) · webhook: ' . $this->notificar($sim, $id)
            . '. El saldo no cambia solo: Administración registra el reembolso en la ficha de la persona.';
    }

    private function resolver(PasarelaSimulada $sim, string $id, string $estado): string
    {
        $sim->resolverPendiente($id, $estado);
        return 'Transacción ' . $id . ($estado === 'APPROVED' ? ' aprobada' : ' rechazada') . ' · webhook: ' . $this->notificar($sim, $id);
    }

    /**
     * Entrega el evento firmado al procesador real del webhook (ServicioRecargaLinea::procesarWebhook), el mismo
     * que atiende POST /api/pasarela/…/eventos. Se hace dentro del servidor porque el cliente HTTP de UniPark
     * solo habla HTTPS con servicios externos. @return string resultado del procesamiento
     */
    private function notificar(PasarelaSimulada $sim, string $id): string
    {
        [$cuerpo, $cabeceras] = $sim->evento($id);
        $r = (new ServicioRecargaLinea($this->db()))->procesarWebhook($cuerpo, $cabeceras);
        return $r['resultado'] . ' (HTTP ' . $r['http'] . ')';
    }

    /** Campos del checkout simulado, verificados con la firma de integridad. null si fueron alterados. */
    private function pagoDesde(Request $request, PasarelaSimulada $sim): ?array
    {
        $ref = $request->input('reference');
        $centavos = $request->input('amount-in-cents');
        $expira = $request->input('expiration-time');
        $firma = $request->input('signature:integrity');
        $retorno = $request->input('redirect-url');
        // Solo se vuelve EXACTAMENTE a la página puente de esta instalación para esta referencia (mismo esquema, host y
        // ruta): nunca a otro sitio, aunque la ruta se parezca (https://otro.sitio/pago/retorno).
        if ($ref === '' || !$sim->pagoAutentico($ref, $centavos, $expira, $firma)
            || !hash_equals(url_absoluta('pago/retorno?ref=' . rawurlencode($ref)), $retorno)) {
            return null;
        }
        // Solo la dueña de la recarga, y solo una recarga del simulador que todavía no tiene ningún pago en curso:
        // nadie puede aprobar una recarga ajena ni volver a pagar una que ya se procesó.
        $recarga = (new \App\Repositories\RecargaLineaRepository($this->db()))->porReferencia($ref);
        $persona = (int) (\App\Services\AuthService::usuario()['id_estudiante'] ?? 0);
        if ($recarga === null || $persona === 0 || (int) $recarga['id_estudiante'] !== $persona || $recarga['proveedor'] !== $sim->codigo()
            || $recarga['estado'] !== 'CREADA' || $recarga['id_transaccion'] !== null || $recarga['id_transaccion_reportada'] !== null
            || \App\Services\Pasarela\PasarelaWompi::centavos($recarga['monto']) !== (int) $centavos) {
            return null;
        }
        return [
            'reference' => $ref, 'centavos' => $centavos, 'monto' => (int) $centavos / 100, 'expira' => $expira,
            'firma' => $firma, 'retorno' => $retorno, 'campos' => [
                'reference' => $ref, 'amount-in-cents' => $centavos, 'currency' => 'COP', 'expiration-time' => $expira,
                'signature:integrity' => $firma, 'redirect-url' => $retorno,
            ],
        ];
    }

    private function simulador(): ?PasarelaSimulada
    {
        try {
            $p = Pasarelas::activa();
        } catch (PasarelaException) {
            return null;
        }
        return $p instanceof PasarelaSimulada ? $p : null;
    }

    private function pantalla(string $vista, array $datos, int $estado = 200): Response
    {
        // Dentro del diseño de UniPark (menú, rótulo de demostración); la vista lleva su propio aviso de simulación.
        return Response::html(View::render($vista, $datos, 'layouts/app'), $estado);
    }

    private function noDisponible(): Response
    {
        return Response::html(View::render('errors/error', [
            'codigo' => 404, 'titulo' => 'Página no encontrada', 'mensaje' => 'La dirección no existe o fue movida.',
        ], 'layouts/plain'), 404);
    }
}
