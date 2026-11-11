<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\WebPush;
use App\Repositories\CobroRepository;
use App\Repositories\EstudianteRepository;
use App\Repositories\NotificacionRepository;
use App\Repositories\SaldoRepository;
use App\Repositories\TarifaRepository;
use App\Repositories\VisitaRepository;
use App\Services\AuthService;

/**
 * "Mi cuenta" de estudiantes, profesores y trabajadores: saldo, estado del día, cobros,
 * movimientos, visitas y activación de avisos en el celular. Solo ve sus propios datos.
 */
final class CuentaController extends Controller
{
    public function index(Request $request): Response
    {
        $persona = $this->persona();
        if ($persona === null) {
            return $this->sinPersona();
        }
        $id = (int) $persona['id_estudiante'];
        $notificaciones = new NotificacionRepository($this->db());
        $datos = [
            'titulo'         => 'Mi cuenta',
            'scripts'        => ['mi-cuenta.js'],
            'persona'        => $persona,
            'hoy'            => $this->estadoHoy($persona),
            'cobros'         => (new CobroRepository($this->db()))->dePersona($id, 30),
            'movimientos'    => (new SaldoRepository($this->db()))->dePersona($id, 30),
            'visitas'        => (new VisitaRepository($this->db()))->historialEstudiante($id, 20),
            'avisos'         => $notificaciones->dePersona($id, 10),
            'tarifas'        => (new TarifaRepository($this->db()))->vigentes(),
            'suscripciones'  => $notificaciones->suscripcionesDeUsuario($this->usuarioId()),
        ];
        $notificaciones->marcarLeidas($id);
        // jsDelivr: biblioteca del código de barras del carnet digital.
        return $this->view('cuenta/index', $datos)->conBibliotecasDeCamara();
    }

    /** Estado en vivo para la cuenta regresiva de la tolerancia. */
    public function estado(Request $request): Response
    {
        $persona = $this->persona();
        if ($persona === null) {
            return Response::json(['ok' => false, 'error' => 'La cuenta no está vinculada a una persona.'], 404);
        }
        return Response::json(['ok' => true, 'saldo' => (float) $persona['saldo']] + $this->estadoHoy($persona));
    }

    public function clavePublica(Request $request): Response
    {
        return Response::json(['ok' => true, 'clave' => WebPush::claves()['publica']]);
    }

    public function suscribir(Request $request): Response
    {
        $endpoint = $request->input('endpoint');
        $p256dh = $request->input('p256dh');
        $auth = $request->input('auth');
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        if (!WebPush::servicioPermitido($endpoint) || strlen($endpoint) > 600
            || strlen(WebPush::unb64($p256dh)) !== 65 || strlen(WebPush::unb64($auth)) !== 16) {
            return Response::json(['ok' => false, 'error' => 'La suscripción del navegador no es válida.'], 422);
        }
        (new NotificacionRepository($this->db()))->guardarSuscripcion(
            $this->usuarioId(), $endpoint, $p256dh, $auth, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 160)
        );
        $this->audit()->log('PUSH_SUSCRITO', 'push_suscripciones', null, 'EXITO', null, ['servicio' => $host]);
        return Response::json(['ok' => true, 'mensaje' => 'Avisos activados en este celular.']);
    }

    public function cancelar(Request $request): Response
    {
        (new NotificacionRepository($this->db()))->desactivarSuscripcion($request->input('endpoint'), $this->usuarioId(), 'Cancelada por la persona');
        return Response::json(['ok' => true, 'mensaje' => 'Avisos desactivados en este celular.']);
    }

    /** Envía una notificación de prueba a los celulares suscritos de la persona. */
    public function probar(Request $request): Response
    {
        $persona = $this->persona();
        if ($persona === null) {
            return Response::json(['ok' => false, 'error' => 'La cuenta no está vinculada a una persona.'], 404);
        }
        $enviadas = (new \App\Services\ServicioNotificacion($this->db()))->push((int) $persona['id_estudiante'], [
            'titulo' => 'UniPark', 'cuerpo' => 'Los avisos de pago llegarán así a este celular.', 'url' => 'mi-cuenta', 'etiqueta' => 'prueba',
        ]);
        return Response::json($enviadas > 0
            ? ['ok' => true, 'mensaje' => 'Notificación de prueba enviada.']
            : ['ok' => false, 'error' => 'No se pudo entregar: activa los avisos en este celular y revisa que el sitio use HTTPS.'], $enviadas > 0 ? 200 : 422);
    }

    /** @return array{visita:?array,cobro:?array,segundos_tolerancia:int,mensaje:string,tono:string} */
    private function estadoHoy(array $persona): array
    {
        $id = (int) $persona['id_estudiante'];
        $visita = (new VisitaRepository($this->db()))->abiertaDeEstudiante($id);
        $repo = new CobroRepository($this->db());
        $cobro = $repo->delDia($id, date('Y-m-d'));
        if ($cobro !== null) {
            // El día vale la tarifa más alta usada: suma del cobro diario y las diferencias; pendiente si alguno lo está.
            $pendiente = array_filter($repo->pendientesDePersona($id), static fn ($c) => $c['fecha'] === date('Y-m-d'));
            $cobro['valor'] = $repo->totalDelDia($id, date('Y-m-d'));
            $cobro['estado'] = $pendiente ? 'PENDIENTE' : $cobro['estado'];
            if ($pendiente) {
                $cobro['valor_pendiente'] = (string) array_sum(array_column($pendiente, 'valor'));
            }
        }
        $segundos = 0;
        if ($visita && $visita['liquidacion'] === 'EN_TOLERANCIA' && $visita['tolerancia_hasta']) {
            $segundos = max(0, strtotime($visita['tolerancia_hasta']) - time());
        }
        // El aviso del COBRO (al vencer la tolerancia) es distinto del veredicto de SALIDA que da el escáner:
        // pagar no es salir; el vehículo sigue dentro hasta que la persona escanee su carné en la salida.
        $dentro = $visita !== null ? ' Tu vehículo continúa registrado dentro del parqueadero.' : '';
        // Una deuda de días anteriores también impide salir (p. ej. entró ayer y no ha salido): se avisa igual.
        $anteriores = array_filter($repo->pendientesDePersona($id), static fn ($c) => $c['fecha'] !== date('Y-m-d'));
        $deudaAnterior = (string) array_sum(array_column($anteriores, 'valor'));
        [$titulo, $mensaje, $tono] = match (true) {
            $anteriores && ($cobro === null || $cobro['estado'] !== 'PENDIENTE') => ['Pago pendiente · Diríjase a Caja',
                'Tienes ' . money($deudaAnterior) . ' pendientes de días anteriores.' . $dentro
                . (\App\Services\Pasarela\Pasarelas::disponible() ? ' Recarga en Mi saldo o paga en Caja antes de salir.' : ' Paga en Caja antes de salir.'), 'err'],
            $cobro !== null && $cobro['estado'] === 'PENDIENTE' => ['Pago pendiente · Diríjase a Caja',
                'No se pudo completar el cobro de ' . money($cobro['valor_pendiente'] ?? $cobro['valor']) . ': tu saldo no alcanza.' . $dentro
                . (\App\Services\Pasarela\Pasarelas::disponible() ? ' Recarga en Mi saldo o paga en Caja antes de salir.' : ' Paga en Caja antes de salir.'), 'err'],
            $cobro !== null && $cobro['estado'] === 'PAGADO' && $visita !== null => ['Pago verificado · Estacionamiento pagado',
                'El cobro de hoy (' . money($cobro['valor']) . ') se pagó con tu saldo UniPark.' . $dentro . ' Al salir, escanea tu carné.', 'ok'],
            $cobro !== null && $cobro['estado'] === 'PAGADO'    => ['Cobro del día pagado', 'El cobro de hoy (' . money($cobro['valor']) . ') está pagado. Puedes entrar y salir sin más cobros hoy.', 'ok'],
            $visita !== null && $visita['liquidacion'] === 'EN_TOLERANCIA' => ['En tiempo de tolerancia', 'Si sales antes de que termine, no se cobra.', 'warn'],
            $visita !== null && $visita['liquidacion'] === 'NO_APLICA'     => ['Ingreso sin cobro', 'Tu ingreso de hoy (' . mb_strtolower(tipo_label($visita['tipo_vehiculo'])) . ') no tiene cobro.', 'ok'],
            $visita !== null => ['Dentro del parqueadero', 'Estás dentro del parqueadero.', 'ok'],
            default          => ['', 'No tienes ingresos abiertos.', ''],
        };
        return [
            'visita'              => $visita ? [
                'tipo'        => tipo_label($visita['tipo_vehiculo']),
                'entrada'     => fecha($visita['entrada_en'], 'H:i'),
                'liquidacion' => liquidacion_label($visita['liquidacion']),
            ] : null,
            'cobro'               => $cobro ? ['estado' => $cobro['estado'], 'valor' => (float) $cobro['valor']] : null,
            'segundos_tolerancia' => $segundos,
            'titulo'              => $titulo,
            'mensaje'             => $mensaje,
            'tono'                => $tono,
        ];
    }

    private function persona(): ?array
    {
        $id = AuthService::usuario()['id_estudiante'] ?? null;
        return $id ? (new EstudianteRepository($this->db()))->find((int) $id) : null;
    }

    private function sinPersona(): Response
    {
        return $this->view('errors/error', [
            'codigo'  => 409,
            'titulo'  => 'Cuenta sin persona vinculada',
            'mensaje' => 'Tu cuenta no está vinculada a un estudiante, profesor o trabajador. Pide al Administrador que la vincule.',
        ], 'layouts/app', 409);
    }
}
