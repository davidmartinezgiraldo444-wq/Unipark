<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\EstudianteRepository;
use App\Repositories\VisitaRepository;
use App\Services\ReglasVisita;
use PDOException;

final class EstudianteController extends Controller
{
    private const POR_PAGINA = 25;

    public function index(Request $request): Response
    {
        $repo = new EstudianteRepository($this->db());
        $buscar = mb_substr($request->query('buscar'), 0, 60);
        $pagina = max(1, (int) $request->query('pagina', '1'));
        $total = $repo->count($buscar);
        return $this->view('estudiantes/index', [
            'titulo'      => 'Estudiantes y profesores',
            'buscar'      => $buscar,
            'estudiantes' => $repo->search($buscar, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA),
            'pagina'      => $pagina,
            'paginas'     => max(1, (int) ceil($total / self::POR_PAGINA)),
            'total'       => $total,
        ]);
    }

    public function nuevo(Request $request): Response
    {
        return $this->form(null, [], []);
    }

    public function crear(Request $request): Response
    {
        $datos = $this->datos($request);
        $errores = $this->validar($datos);
        if ($errores) {
            return $this->form(null, $datos, $errores, 422);
        }
        try {
            $id = (new EstudianteRepository($this->db()))->create($datos);
        } catch (PDOException $e) {
            if (Database::isDuplicate($e)) {
                return $this->form(null, $datos, self::duplicado($e), 422);
            }
            throw $e;
        }
        $this->audit()->log('ESTUDIANTE_CREADO', 'estudiantes', $id);
        $this->flash('ok', categoria_label($datos['categoria']) . ' registrado.');
        return $this->redirect('estudiantes?buscar=' . rawurlencode($datos['documento']));
    }

    public function editar(Request $request, int $id): Response
    {
        $estudiante = (new EstudianteRepository($this->db()))->find($id);
        return $estudiante ? $this->form($estudiante, $estudiante, []) : $this->notFound();
    }

    public function actualizar(Request $request, int $id): Response
    {
        $repo = new EstudianteRepository($this->db());
        $estudiante = $repo->find($id);
        if ($estudiante === null) {
            return $this->notFound();
        }
        $datos = $this->datos($request);
        $errores = $this->validar($datos);
        if ($errores) {
            return $this->form($estudiante, $datos, $errores, 422);
        }
        try {
            $repo->update($id, $datos);
        } catch (PDOException $e) {
            if (Database::isDuplicate($e)) {
                return $this->form($estudiante, $datos, self::duplicado($e), 422);
            }
            throw $e;
        }
        $cambios = array_keys(array_filter(
            $datos,
            static fn ($v, $k) => (string) ($estudiante[$k] ?? '') !== $v,
            ARRAY_FILTER_USE_BOTH
        ));
        $this->audit()->log('ESTUDIANTE_ACTUALIZADO', 'estudiantes', $id, 'EXITO', null, ['campos' => $cambios]);
        $this->flash('ok', 'Datos actualizados.');
        return $this->redirect('estudiantes/' . $id);
    }

    public function estado(Request $request, int $id): Response
    {
        $repo = new EstudianteRepository($this->db());
        $estudiante = $repo->find($id);
        if ($estudiante === null) {
            return $this->notFound();
        }
        $activar = $request->input('activo') === '1';
        $repo->setActivo($id, $activar);
        $this->audit()->log($activar ? 'ESTUDIANTE_ACTIVADO' : 'ESTUDIANTE_DESACTIVADO', 'estudiantes', $id);
        $this->flash('ok', $activar ? 'Registro activado.' : 'Registro desactivado. No podrá registrar nuevas entradas.');
        return $this->redirect('estudiantes/' . $id);
    }

    /** Historial de visitas, pagos y salidas del estudiante (RF-15). */
    public function historial(Request $request, int $id): Response
    {
        $estudiante = (new EstudianteRepository($this->db()))->find($id);
        if ($estudiante === null) {
            return $this->notFound();
        }
        return $this->view('estudiantes/historial', [
            'titulo'     => $estudiante['nombres'] . ' ' . $estudiante['apellidos'],
            'estudiante' => $estudiante,
            'visitas'    => (new VisitaRepository($this->db()))->historialEstudiante($id),
            'cobros'     => can('REPORTE_CAJA', 'COBRO_ANULAR') ? (new \App\Repositories\CobroRepository($this->db()))->dePersona($id, 60) : [],
            'movimientos' => can('REPORTE_CAJA', 'SALDO_REVERSAR') ? (new \App\Repositories\SaldoRepository($this->db()))->reporte(['id_estudiante' => $id], 60) : [],
        ]);
    }

    private function form(?array $estudiante, array $datos, array $errores, int $estado = 200): Response
    {
        return $this->view('estudiantes/form', [
            'titulo'     => $estudiante ? 'Editar persona' : 'Nueva persona',
            'estudiante' => $estudiante,
            'datos'      => $datos,
            'errores'    => $errores,
        ], 'layouts/app', $estado);
    }

    private function datos(Request $request): array
    {
        return [
            'documento' => strtoupper(str_replace(' ', '', $request->input('documento'))),
            'nombres'   => preg_replace('/\s+/u', ' ', $request->input('nombres')),
            'apellidos' => preg_replace('/\s+/u', ' ', $request->input('apellidos')),
            'correo'    => mb_strtolower($request->input('correo')),
            'telefono'  => $request->input('telefono'),
            'categoria' => $request->input('categoria', 'ESTUDIANTE'),
            'codigo_carnet' => strtoupper(str_replace(' ', '', $request->input('codigo_carnet'))),
        ];
    }

    private function validar(array $d): array
    {
        return (new Validator())
            ->required('documento', $d['documento'], 'El documento')
            ->documento('documento', $d['documento'])
            ->required('nombres', $d['nombres'], 'Los nombres')
            ->max('nombres', $d['nombres'], 80, 'Los nombres')
            ->required('apellidos', $d['apellidos'], 'Los apellidos')
            ->max('apellidos', $d['apellidos'], 80, 'Los apellidos')
            ->email('correo', $d['correo'])
            ->max('correo', $d['correo'], 120, 'El correo')
            ->telefono('telefono', $d['telefono'])
            ->max('codigo_carnet', $d['codigo_carnet'], 40, 'El código del carnet')
            ->in('categoria', $d['categoria'], array_merge(ReglasVisita::CATEGORIAS, [ReglasVisita::VISITANTE]), 'Selecciona si es estudiante, profesor, trabajador o visitante.')
            ->errors();
    }

    private static function duplicado(PDOException $e): array
    {
        return str_contains($e->getMessage(), 'uq_estudiantes_carnet')
            ? ['codigo_carnet' => 'Ese código de carnet ya está asignado a otra persona.']
            : ['documento' => 'Ya existe una persona con ese documento.'];
    }
}
