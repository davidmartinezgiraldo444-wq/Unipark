<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Repositories\EventoRepository;
use App\Services\AuthService;
use App\Services\ReglaNegocioException;
use App\Services\ServicioAcceso;

/**
 * API del punto de acceso (dispositivo de portería) y del panel del portero.
 * El punto de acceso identifica y envía lo que vio la cámara; NUNCA elige el tipo de vehículo.
 * Solo un usuario con CLASIFICACION_RESOLVER (portero) resuelve los eventos pendientes.
 */
final class AccesoController extends Controller
{
    // ------------------------------------------------------------ Punto de acceso

    public function identificar(Request $request): Response
    {
        try {
            $r = (new ServicioAcceso($this->db()))->identificar(
                $request->raw('codigo'),
                mb_substr($request->input('nombre'), 0, 80),
                $request->int('puesto') ?? 1,
                $this->usuarioId(),
            );
        } catch (ReglaNegocioException $e) {
            return $this->errorRegla($e);
        }
        return Response::json($r);
    }

    public function clasificacion(Request $request, int $id): Response
    {
        try {
            $r = (new ServicioAcceso($this->db()))->clasificar(
                $id,
                $this->usuarioId(),
                $request->input('fuente', 'CELULAR'),
                $request->input('tipo', 'DESCONOCIDO'),
                $request->input('confianza'),
                $request->input('segunda'),
                $request->input('final') === '1',
                $request->raw('captura') ?: null,
            );
        } catch (ReglaNegocioException $e) {
            return $this->errorRegla($e);
        }
        return Response::json($r);
    }

    /** El punto de acceso consulta su evento (por ejemplo, mientras espera al portero). */
    public function estado(Request $request, int $id): Response
    {
        $ev = (new EventoRepository($this->db()))->find($id);
        if ($ev === null || ((int) $ev['creado_por'] !== $this->usuarioId() && !can('CLASIFICACION_RESOLVER'))) {
            return Response::json(['ok' => false, 'codigo' => 'NO_EXISTE', 'error' => 'El evento no existe.'], 404);
        }
        return Response::json((new ServicioAcceso($this->db()))->estadoPublico($id));
    }

    // ------------------------------------------------------------ Portero

    /** Avisos pendientes para el panel de Portería (solo datos necesarios; documento enmascarado). */
    public function pendientes(Request $request): Response
    {
        $tipos = array_map(static fn ($t) => ['codigo' => $t, 'etiqueta' => tipo_label($t)], (array) config('tipos_habilitados'));
        $servicio = new \App\Services\ServicioAcceso($this->db());
        $lista = array_map(static function (array $e) use ($servicio): array {
            return [
                'id'          => (int) $e['id_evento'],
                'evento'      => $e['tipo'],
                'motivo'      => $e['motivo'],
                // La IA lo identificó con confianza suficiente: el portero CONFIRMA o cambia (no elige desde cero).
                'confirmar'   => $servicio->sugerenciaConfiable($e) ? (string) $e['tipo_detectado'] : null,
                'mensaje'     => $e['mensaje'],
                'creado'      => fecha($e['creado_en'], 'H:i:s'),
                'puesto'      => $e['puesto'],
                'persona'     => $e['id_estudiante']
                    ? trim($e['nombres'] . ' ' . $e['apellidos'])
                    : ($e['nombre_leido'] ?: 'Persona no registrada'),
                'categoria'   => $e['id_estudiante'] ? categoria_label($e['categoria']) : 'Sin registro',
                'documento'   => mask_doc((string) ($e['documento'] ?? $e['documento_leido'])),
                'identidad'   => $e['id_estudiante'] ? 'Identidad validada' : 'Identidad no registrada',
                'sugerencia'  => $e['tipo_detectado'] && $e['tipo_detectado'] !== 'DESCONOCIDO' ? tipo_label($e['tipo_detectado']) : null,
                'confianza'   => $e['confianza'] !== null ? (int) round((float) $e['confianza'] * 100) : null,
                'ingreso'     => $e['tipo_ingreso'] ? tipo_label($e['tipo_ingreso']) . ' · ' . fecha($e['ingreso_en'], 'H:i') : null,
                'captura'     => (int) $e['tiene_captura'] === 1 ? url('porteria/eventos/' . $e['id_evento'] . '/captura') : null,
            ];
        }, (new EventoRepository($this->db()))->pendientes());

        // Actividad en tiempo real (solo lectura, SIN botones): quién se está identificando y qué se resolvió solo.
        // El portero solo puede elegir el vehículo en los pendientes, es decir, cuando la IA no lo identificó.
        $actividad = array_map(static function (array $e): array {
            $documento = (string) ($e['documento'] ?? $e['documento_leido'] ?? '');
            return [
                'id'        => (int) $e['id_evento'],
                'evento'    => $e['tipo'],
                'estado'    => $e['estado'],
                'resultado' => $e['resultado'],
                'persona'   => $e['nombres'] !== null ? trim($e['nombres'] . ' ' . $e['apellidos']) : ($e['nombre_leido'] ?: 'Persona no registrada'),
                'categoria' => $e['categoria'] !== null ? categoria_label($e['categoria']) : 'Sin registro',
                'documento' => mask_doc($documento),
                'vehiculo'  => $e['tipo_confirmado'] ? tipo_label($e['tipo_confirmado']) : null,
                'metodo'    => $e['metodo'] === 'MANUAL' ? 'Portería' : ($e['metodo'] === 'IA' ? ($e['resuelto_por'] !== null ? 'IA · confirmado por Portería' : 'IA en el dispositivo') : null),
                'detectado' => $e['tipo_detectado'] && $e['tipo_detectado'] !== 'DESCONOCIDO' ? tipo_label($e['tipo_detectado']) : null,
                'confianza' => $e['confianza'] !== null ? (int) round((float) $e['confianza'] * 100) : null,
                'hora'      => fecha($e['resuelto_en'] ?? $e['creado_en'], 'H:i:s'),
                'puesto'    => $e['puesto'],
            ];
        }, (new EventoRepository($this->db()))->actividad());

        return Response::json(['ok' => true, 'eventos' => $lista, 'tipos' => $tipos, 'actividad' => $actividad]);
    }

    public function resolver(Request $request, int $id): Response
    {
        try {
            $r = (new ServicioAcceso($this->db()))->resolver(
                $id,
                $this->usuarioId(),
                $request->input('accion'),
                $request->input('tipo'),
                mb_substr($request->input('nota'), 0, 200),
            );
        } catch (ReglaNegocioException $e) {
            return $this->errorRegla($e);
        }
        return Response::json($r);
    }

    /** Captura opcional del vehículo (solo si la configuración la habilita y mientras el evento está pendiente). */
    public function captura(Request $request, int $id): Response
    {
        $ev = (new EventoRepository($this->db()))->find($id);
        $ruta = ServicioAcceso::rutaCaptura($id);
        if ($ev === null || $ev['estado'] !== 'PENDIENTE_PORTERO' || !is_file($ruta)) {
            return $this->notFound('La captura no existe o ya se resolvió el evento.');
        }
        return new Response((string) file_get_contents($ruta), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
    }

    private function errorRegla(ReglaNegocioException $e): Response
    {
        $estado = match ($e->codigo) {
            'NO_EXISTE' => 404,
            'AJENO'     => 403,
            'OCUPADO'   => 409,
            default     => 422,
        };
        return Response::json(['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()], $estado);
    }
}
