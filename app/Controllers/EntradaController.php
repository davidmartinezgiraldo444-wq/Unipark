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
        $error = null;

        if ($documento !== '') {
            if (!Validator::esDocumento($documento)) {
                $error = 'Escribe un documento válido: entre 5 y 20 caracteres, sin espacios.';
            } else {
                $estudiante = (new EstudianteRepository($this->db()))->findByDocumento($documento);
                if ($estudiante === null) {
                    $error = 'No hay un estudiante registrado con ese documento. Remítelo a Administración para su registro.';
                } elseif ((int) $estudiante['activo'] !== 1) {
                    $error = 'El estudiante está inactivo. No puede registrar entradas.';
                } else {
                    $visita = (new VisitaRepository($this->db()))->abiertaDeEstudiante((int) $estudiante['id_estudiante']);
                }
            }
        }

        $visitas = new VisitaRepository($this->db());
        return $this->view('porteria/index', [
            'titulo'     => 'Portería',
            'scripts'    => ['entrada.js'],
            'documento'  => $documento,
            'estudiante' => $estudiante,
            'visita'     => $visita,
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
        if ($idEstudiante === null) {
            $this->flash('err', 'Busca primero al estudiante por su documento.');
            return $this->redirect('porteria');
        }

        try {
            $visita = (new ServicioEntrada($this->db()))->registrar($idEstudiante, $request->input('tipo'), $this->usuarioId());
            $this->flash('ok', sprintf(
                'Entrada registrada: %s · %s · tarifa %s.',
                $visita['nombres'] . ' ' . $visita['apellidos'],
                $visita['tipo_vehiculo'],
                money($visita['tarifa_aplicada'])
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
