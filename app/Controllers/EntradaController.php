<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\EstudianteRepository;
use App\Repositories\TarifaRepository;
use App\Repositories\VisitaRepository;
use App\Services\ReglaNegocioException;
use App\Services\ServicioEntrada;

/** Panel de Portería: búsqueda, entrada y consulta de la visita abierta. */
final class EntradaController extends Controller
{
    public function index(Request $request): Response
    {
        $documento = $request->query('documento');
        $estudiante = null;
        $visita = null;
        $abiertas = [];
        $visitante = null;
        $error = null;

        if ($documento !== '') {
            if (!Validator::esDocumento($documento)) {
                $error = 'Escribe un documento válido: entre 5 y 20 caracteres, sin espacios.';
            } else {
                $estudiante = (new EstudianteRepository($this->db()))->findByCodigo(strtoupper($documento));
                if ($estudiante === null) {
                    // Documento no registrado: se clasifica como visitante (sin cobro).
                    $visitante = strtoupper($documento);
                } elseif ((int) $estudiante['activo'] !== 1) {
                    $error = 'La persona está inactiva. No puede registrar entradas.';
                } else {
                    $abiertas = (new VisitaRepository($this->db()))->abiertasDePersona((int) $estudiante['id_estudiante']);
                    $visita = $estudiante['categoria'] === 'VISITANTE' ? null : ($abiertas[0] ?? null);
                }
            }
        }

        $visitas = new VisitaRepository($this->db());
        return $this->view('porteria/index', [
            'titulo'     => 'Portería',
            'scripts'    => ['entrada.js', 'latido.js', 'avisos.js'],
            'documento'  => $documento,
            'estudiante' => $estudiante,
            'visita'     => $visita,
            'abiertas'   => $abiertas,
            'visitante'  => $visitante,
            'error'      => $error,
            'tarifas'    => (new TarifaRepository($this->db()))->vigentes(),
            'ocupacion'  => $visitas->ocupacion(),
            'recientes'  => array_slice(array_reverse($visitas->abiertas()), 0, 6),
        ]);
    }

    public function registrar(Request $request): Response
    {
        $idEstudiante = $request->int('id_estudiante');
        $documento = $request->input('documento');
        $servicio = new ServicioEntrada($this->db());
        if ($idEstudiante === null && $request->input('visitante') !== '1') {
            $this->flash('err', 'Busca primero a la persona por su documento.');
            return $this->redirect('porteria');
        }

        try {
            $visita = $idEstudiante !== null
                ? $servicio->registrar($idEstudiante, $request->input('tipo'), $this->usuarioId())
                : $servicio->registrarVisitante($documento, mb_substr($request->input('nombre'), 0, 80), $request->input('tipo'), $this->usuarioId());
            $this->flash('ok', sprintf(
                'Entrada registrada: %s · %s · %s.',
                trim($visita['nombres'] . ' ' . $visita['apellidos']),
                tipo_label($visita['tipo_vehiculo']),
                $visita['tarifa_aplicada'] !== null
                    ? ($visita['liquidacion'] === 'CUBIERTA' ? 'ya tiene el cobro de hoy' : sprintf('%d min de tolerancia, luego %s del día', (int) config('tolerancia_minutos'), money($visita['tarifa_aplicada'])))
                    : 'sin cobro'
            ));
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        return $this->redirect('porteria?documento=' . rawurlencode($documento));
    }

    public function anular(Request $request, int $id): Response
    {
        try {
            (new ServicioEntrada($this->db()))->anular($id, $this->usuarioId(), $request->input('motivo'));
            $this->flash('ok', 'La visita quedó anulada. El motivo se registró en la auditoría.');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        $visita = (new VisitaRepository($this->db()))->find($id);
        return $this->redirect($visita ? 'estudiantes/' . $visita['id_estudiante'] : 'reportes');
    }
}
