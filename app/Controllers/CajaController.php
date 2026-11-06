<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\PagoRepository;
use App\Repositories\VisitaRepository;
use App\Services\ReglaNegocioException;
use App\Services\ServicioPago;

/** Panel de Caja: pendientes, cobro, comprobante y cierre de caja. */
final class CajaController extends Controller
{
    public function index(Request $request): Response
    {
        $visitas = new VisitaRepository($this->db());
        $buscar = mb_substr($request->query('buscar'), 0, 60);
        $seleccion = null;
        $idVisita = $request->int('visita');
        if ($idVisita !== null) {
            $seleccion = $visitas->find($idVisita);
            if ($seleccion !== null && ($seleccion['estado'] !== 'EN_CURSO' || (int) $seleccion['pagada'] === 1)) {
                $this->flash('err', (int) $seleccion['pagada'] === 1
                    ? 'Esta visita ya tiene un pago confirmado.'
                    : 'La visita ya no está en curso.');
                $seleccion = null;
            }
        }

        $hoy = date('Y-m-d');
        return $this->view('caja/index', [
            'titulo'     => 'Caja',
            'scripts'    => ['caja.js'],
            'buscar'     => $buscar,
            'pendientes' => $visitas->pendientesDePago($buscar),
            'seleccion'  => $seleccion,
            'medios'     => config('medios_pago'),
            'mios'       => (new PagoRepository($this->db()))->resumenCajero($this->usuarioId(), $hoy . ' 00:00:00', $hoy . ' 23:59:59'),
        ]);
    }

    public function pagar(Request $request): Response
    {
        $idVisita = $request->int('id_visita');
        if ($idVisita === null) {
            $this->flash('err', 'Selecciona la visita que vas a cobrar.');
            return $this->redirect('caja');
        }
        try {
            $pago = (new ServicioPago($this->db()))->registrar(
                $idVisita,
                $request->input('medio_pago'),
                $this->usuarioId(),
                $request->input('referencia'),
            );
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
            return $this->redirect('caja');
        }
        $this->flash('ok', 'Pago confirmado. Comprobante ' . $pago['consecutivo'] . '.');
        return $this->redirect('caja/recibo/' . $pago['id_pago']);
    }

    /** Comprobante. Caja solo ve los que registró; con REPORTE_CAJA se ven todos. */
    public function recibo(Request $request, int $id): Response
    {
        $pago = (new PagoRepository($this->db()))->find($id);
        if ($pago === null) {
            return $this->notFound('El comprobante no existe.');
        }
        if (!can('REPORTE_CAJA') && (int) $pago['registrado_por'] !== $this->usuarioId()) {
            $this->audit()->log('ACCESO_DENEGADO', 'pagos', $id, 'DENEGADO', 'Comprobante de otro cajero');
            return $this->view('errors/error', [
                'codigo' => 403, 'titulo' => 'Acceso no autorizado',
                'mensaje' => 'Solo puedes consultar los comprobantes que registraste.',
            ], 'layouts/app', 403);
        }
        return $this->view('caja/recibo', ['titulo' => 'Comprobante ' . $pago['consecutivo'], 'pago' => $pago]);
    }

    public function cierre(Request $request): Response
    {
        [$desde, $hasta, $error] = $this->periodo($request);
        return $this->view('caja/cierre', $this->datosCierre($desde, $hasta) + [
            'error'   => $error,
            'contado' => null,
        ]);
    }

    /** RF-19: compara el efectivo contado con lo registrado y deja constancia en la auditoría. */
    public function cerrarCaja(Request $request): Response
    {
        [$desde, $hasta, $error] = $this->periodo($request);
        $datos = $this->datosCierre($desde, $hasta);
        $contado = str_replace(['.', ','], ['', '.'], $request->input('efectivo_contado'));

        if ($error === null && !Validator::esDinero($contado, true)) {
            $error = 'Escribe el efectivo contado como un valor numérico, por ejemplo 125000.';
        }
        if ($error !== null) {
            return $this->view('caja/cierre', $datos + ['error' => $error, 'contado' => null], 'layouts/app', 422);
        }

        $diferencia = (float) $contado - $datos['efectivo'];
        $this->audit()->log('CIERRE_CAJA', 'pagos', null, 'EXITO', null, [
            'desde'            => $desde,
            'hasta'            => $hasta,
            'pagos'            => $datos['cantidad'],
            'total_registrado' => $datos['total'],
            'efectivo_sistema' => $datos['efectivo'],
            'efectivo_contado' => $contado,
            'diferencia'       => round($diferencia, 2),
        ]);
        return $this->view('caja/cierre', $datos + [
            'error'   => null,
            'contado' => ['valor' => (float) $contado, 'diferencia' => $diferencia],
        ]);
    }

    public function anular(Request $request, int $id): Response
    {
        try {
            (new ServicioPago($this->db()))->anular($id, $this->usuarioId(), $request->input('motivo'));
            $this->flash('ok', 'El pago quedó anulado. Si la visita sigue en curso, vuelve a estar pendiente de pago.');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        $pago = (new PagoRepository($this->db()))->find($id);
        $visita = $pago ? (new VisitaRepository($this->db()))->find((int) $pago['id_visita']) : null;
        return $this->redirect($visita ? 'estudiantes/' . $visita['id_estudiante'] : 'reportes?tab=pagos');
    }

    /** @return array{0:string,1:string,2:?string} */
    private function periodo(Request $request): array
    {
        $desde = $request->input('desde', date('Y-m-d') . 'T00:00');
        $hasta = $request->input('hasta', date('Y-m-d\TH:i'));
        $d = Validator::aFecha($desde);
        $h = Validator::aFecha($hasta);
        if ($d === null || $h === null || $d > $h) {
            $hoy = date('Y-m-d');
            return [$hoy . ' 00:00:00', date('Y-m-d H:i:s'), 'El periodo del turno no es válido.'];
        }
        return [$d->format('Y-m-d H:i:00'), $h->format('Y-m-d H:i:59'), null];
    }

    private function datosCierre(string $desde, string $hasta): array
    {
        $pagos = new PagoRepository($this->db());
        $resumen = $pagos->resumenCajero($this->usuarioId(), $desde, $hasta);
        $efectivo = 0.0;
        $total = 0.0;
        $cantidad = 0;
        foreach ($resumen as $fila) {
            $total += (float) $fila['total'];
            $cantidad += (int) $fila['cantidad'];
            if ($fila['medio_pago'] === 'EFECTIVO') {
                $efectivo = (float) $fila['total'];
            }
        }
        return [
            'titulo'   => 'Cierre de caja',
            'desde'    => $desde,
            'hasta'    => $hasta,
            'resumen'  => $resumen,
            'detalle'  => $pagos->delCajero($this->usuarioId(), $desde, $hasta),
            'total'    => $total,
            'efectivo' => $efectivo,
            'cantidad' => $cantidad,
            'medios'   => config('medios_pago'),
        ];
    }
}
