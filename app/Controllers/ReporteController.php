<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\CobroRepository;
use App\Repositories\EstudianteRepository;
use App\Repositories\EventoRepository;
use App\Repositories\PagoRepository;
use App\Repositories\RecargaLineaRepository;
use App\Repositories\SaldoRepository;
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
        'cobros'      => 'REPORTE_CAJA',
        'movimientos' => 'REPORTE_CAJA',
        'saldos'      => 'REPORTE_CAJA',
        'eventos'     => 'REPORTE_VER',
        'recargas'    => 'REPORTE_CAJA',
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
            'calibracion' => $tab === 'eventos' ? (new EventoRepository($this->db()))->calibracion() : [],
            'problemas'   => $tab === 'recargas' ? (new RecargaLineaRepository($this->db()))->problemas($filtros) : [],
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
            'cobros' => [
                ['Cobro', 'Fecha', 'Concepto', 'Documento', 'Categoría', 'Vehículo', 'Valor', 'Estado', 'Comprobante', 'Medio'],
                array_map(static fn ($c) => [
                    $c['id_cobro'], $c['fecha'], $c['concepto'], mask_doc($c['documento']), $c['categoria'], $c['tipo_vehiculo'],
                    $c['valor'], $c['estado'], $c['consecutivo'], $c['medio_pago'],
                ], $filas),
            ],
            'movimientos' => [
                ['Movimiento', 'Fecha', 'Tipo', 'Documento', 'Monto', 'Saldo anterior', 'Saldo nuevo', 'Medio', 'Comprobante', 'Registró', 'Motivo'],
                array_map(static fn ($m) => [
                    $m['id_movimiento'], $m['creado_en'], $m['tipo'], mask_doc($m['documento']), $m['monto'], $m['saldo_anterior'],
                    $m['saldo_nuevo'], $m['medio_pago'], $m['consecutivo'], $m['cajero'], $m['motivo'],
                ], $filas),
            ],
            'saldos' => [
                ['Documento', 'Categoría', 'Saldo', 'Pendiente', 'Último movimiento', 'Activo'],
                array_map(static fn ($p) => [mask_doc($p['documento']), $p['categoria'], $p['saldo'], $p['pendiente'], $p['ultimo_movimiento'], $p['activo']], $filas),
            ],
            'eventos' => [
                ['Evento', 'Fecha', 'Tipo', 'Estado', 'Motivo', 'Documento', 'Detectado', 'Confianza', 'Confirmado', 'Método', 'Portero', 'Resultado'],
                array_map(static fn ($e) => [
                    $e['id_evento'], $e['creado_en'], $e['tipo'], $e['estado'], $e['motivo'], mask_doc((string) ($e['documento'] ?? $e['documento_leido'])),
                    $e['tipo_detectado'], $e['confianza'], $e['tipo_confirmado'], $e['metodo'], $e['portero'], $e['resultado'],
                ], $filas),
            ],
            'recargas' => [
                ['Recarga', 'Referencia', 'Creada', 'Documento', 'Monto', 'Estado', 'Proveedor', 'Entorno', 'Transacción', 'Medio', 'Aprobada', 'Comprobante'],
                array_map(static fn ($r) => [
                    $r['id_recarga'], $r['referencia'], $r['creada_en'], mask_doc($r['documento']), $r['monto'], $r['estado'], $r['proveedor'],
                    $r['entorno'], $r['id_transaccion'], $r['medio_externo'], $r['aprobada_en'], $r['consecutivo'],
                ], $filas),
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
            'cobros'      => (new CobroRepository($this->db()))->reporte($f),
            'movimientos' => (new SaldoRepository($this->db()))->reporte($f),
            'saldos'      => (new EstudianteRepository($this->db()))->saldos($f['texto'], $f['categoria']),
            'eventos'     => (new EventoRepository($this->db()))->reporte($f),
            'recargas'    => (new RecargaLineaRepository($this->db()))->reporte($f),
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
            'texto'   => mb_substr($request->query('texto'), 0, 60),
            'tipo_mov' => $request->query('tipo_mov'),
            'concepto' => $request->query('concepto'),
            'estado_cobro' => $request->query('estado_cobro'),
            'estado_evento' => $request->query('estado_evento'),
            'metodo'  => $request->query('metodo'),
            'estado_recarga' => $request->query('estado_recarga'),
        ];
        // Valores fuera de lo permitido se descartan en vez de llegar a la consulta.
        foreach (['desde', 'hasta'] as $c) {
            if ($f[$c] !== '' && Validator::aFecha($f[$c]) === null) {
                $f[$c] = '';
            }
        }
        $f['tipo'] = in_array($f['tipo'], \App\Services\ReglasVisita::TIPOS, true) ? $f['tipo'] : '';
        $f['categoria'] = in_array($f['categoria'], ['ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE'], true) ? $f['categoria'] : '';
        $f['tipo_mov'] = in_array($f['tipo_mov'], ['RECARGA', 'DEBITO', 'REINTEGRO', 'REVERSO_RECARGA'], true) ? $f['tipo_mov'] : '';
        $f['concepto'] = in_array($f['concepto'], ['DIARIO', 'DIFERENCIA'], true) ? $f['concepto'] : '';
        $f['estado_cobro'] = in_array($f['estado_cobro'], ['PENDIENTE', 'PAGADO', 'ANULADO'], true) ? $f['estado_cobro'] : '';
        $f['estado_evento'] = in_array($f['estado_evento'], ['EN_CAPTURA', 'PENDIENTE_PORTERO', 'CONFIRMADO', 'RECHAZADO', 'EXPIRADO'], true) ? $f['estado_evento'] : '';
        $f['estado_recarga'] = in_array($f['estado_recarga'], ['CREADA', 'PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA', 'ERROR', 'CANCELADA', 'EXPIRADA'], true) ? $f['estado_recarga'] : '';
        $f['metodo'] = in_array($f['metodo'], ['IA', 'MANUAL'], true) ? $f['metodo'] : '';
        $f['estado'] = in_array($f['estado'], ['EN_CURSO', 'CERRADA', 'ANULADA', 'PAGADO', 'ANULADO'], true) ? $f['estado'] : '';
        $f['usuario'] = ctype_digit($f['usuario']) ? $f['usuario'] : '';
        $f['pago'] = in_array($f['pago'], ['si', 'no'], true) ? $f['pago'] : '';
        $f['medio'] = array_key_exists($f['medio'], config('medios_pago')) ? $f['medio'] : '';
        return $f;
    }
}
