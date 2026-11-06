<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\PagoRepository;
use App\Repositories\UsuarioRepository;
use App\Repositories\VisitaRepository;

/** Reportes operativos, de caja e historial (sección 12, RF-16, RF-20). */
final class ReporteController extends Controller
{
    /** Pestaña => permiso requerido. */
    private const TABS = [
        'abiertas' => 'REPORTE_OPERATIVO',
        'visitas'  => 'REPORTE_VER',
        'pagos'    => 'REPORTE_CAJA',
        'recaudo'  => 'REPORTE_CAJA',
    ];

    public function index(Request $request): Response
    {
        $permitidas = array_keys(array_filter(self::TABS, static fn ($p) => can($p)));
        $tab = $request->query('tab', $permitidas[0]);
        if (!in_array($tab, $permitidas, true)) {
            $tab = $permitidas[0];
        }
        $filtros = $this->filtros($request);

        return $this->view('reportes/index', [
            'titulo'     => 'Reportes',
            'tab'        => $tab,
            'tabs'       => $permitidas,
            'filtros'    => $filtros,
            'filas'      => $this->datos($tab, $filtros),
            'usuarios'   => (new UsuarioRepository($this->db()))->conActividad(),
            'medios'     => config('medios_pago'),
        ]);
    }

    /** Exportación CSV con campos mínimos: documento enmascarado (RF-20, sección 10). */
    public function exportar(Request $request): Response
    {
        $tab = $request->query('tab');
        if (!isset(self::TABS[$tab]) || !can(self::TABS[$tab])) {
            return $this->redirect('reportes');
        }
        $filtros = $this->filtros($request);
        $filas = $this->datos($tab, $filtros);

        [$encabezados, $salida] = match ($tab) {
            'abiertas', 'visitas' => [
                ['Visita', 'Documento', 'Categoría', 'Tipo', 'Tarifa aplicada', 'Entrada', 'Salida', 'Estado', 'Pagada', 'Comprobante'],
                array_map(static fn ($v) => [
                    $v['id_visita'], mask_doc($v['documento']), $v['categoria'], $v['tipo_vehiculo'], $v['tarifa_aplicada'],
                    $v['entrada_en'], $v['salida_en'], $v['estado'], $v['pagada'] ? 'SI' : 'NO', $v['consecutivo'],
                ], $filas),
            ],
            'pagos' => [
                ['Comprobante', 'Fecha', 'Documento', 'Categoría', 'Tipo', 'Monto', 'Medio', 'Estado', 'Cajero'],
                array_map(static fn ($p) => [
                    $p['consecutivo'], $p['pagado_en'], mask_doc($p['documento']), $p['categoria'], $p['tipo_vehiculo'],
                    $p['monto'], $p['medio_pago'], $p['estado'], $p['cajero'],
                ], $filas),
            ],
            'recaudo' => [
                ['Día', 'Cajero', 'Medio', 'Pagos', 'Total'],
                array_map(static fn ($r) => [$r['dia'], $r['cajero'], $r['medio_pago'], $r['cantidad'], $r['total']], $filas),
            ],
        };

        $this->audit()->log('REPORTE_EXPORTADO', 'reportes', null, 'EXITO', null, ['tab' => $tab, 'filtros' => $filtros, 'filas' => count($salida)]);
        return Response::csv('unipark-' . $tab . '-' . date('Ymd-His') . '.csv', $encabezados, $salida);
    }

    private function datos(string $tab, array $f): array
    {
        return match ($tab) {
            'abiertas' => (new VisitaRepository($this->db()))->abiertas($f['tipo'], $f['categoria']),
            'visitas'  => (new VisitaRepository($this->db()))->reporte($f),
            'pagos'    => (new PagoRepository($this->db()))->reporte($f),
            'recaudo'  => (new PagoRepository($this->db()))->recaudo($f),
        };
    }

    private function filtros(Request $request): array
    {
        $f = [
            'desde'   => $request->query('desde', date('Y-m-d', strtotime('-7 days'))),
            'hasta'   => $request->query('hasta', date('Y-m-d')),
            'tipo'    => $request->query('tipo'),
            'categoria' => $request->query('categoria'),
            'estado'  => $request->query('estado'),
            'usuario' => $request->query('usuario'),
            'pago'    => $request->query('pago'),
            'medio'   => $request->query('medio'),
        ];
        // Valores fuera de lo permitido se descartan en vez de llegar a la consulta.
        foreach (['desde', 'hasta'] as $c) {
            if ($f[$c] !== '' && Validator::aFecha($f[$c]) === null) {
                $f[$c] = '';
            }
        }
        $f['tipo'] = in_array($f['tipo'], ['CARRO', 'MOTO'], true) ? $f['tipo'] : '';
        $f['categoria'] = in_array($f['categoria'], ['ESTUDIANTE', 'PROFESOR'], true) ? $f['categoria'] : '';
        $f['estado'] = in_array($f['estado'], ['EN_CURSO', 'CERRADA', 'ANULADA', 'PAGADO', 'ANULADO'], true) ? $f['estado'] : '';
        $f['usuario'] = ctype_digit($f['usuario']) ? $f['usuario'] : '';
        $f['pago'] = in_array($f['pago'], ['si', 'no'], true) ? $f['pago'] : '';
        $f['medio'] = array_key_exists($f['medio'], config('medios_pago')) ? $f['medio'] : '';
        return $f;
    }
}
