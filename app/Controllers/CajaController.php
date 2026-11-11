<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\CobroRepository;
use App\Repositories\EstudianteRepository;
use App\Repositories\PagoRepository;
use App\Repositories\SaldoRepository;
use App\Repositories\VisitaRepository;
use App\Services\ReglaNegocioException;
use App\Services\ServicioPago;
use App\Services\ServicioSaldo;

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

        // Cobro diario pendiente seleccionado.
        $cobros = new CobroRepository($this->db());
        $cobroSel = null;
        $idCobro = $request->int('cobro');
        if ($idCobro !== null) {
            $cobroSel = $cobros->find($idCobro);
            if ($cobroSel !== null && $cobroSel['estado'] !== 'PENDIENTE') {
                $this->flash('err', $cobroSel['estado'] === 'PAGADO' ? 'Este cobro ya está pagado.' : 'Este cobro está anulado.');
                $cobroSel = null;
            }
        }

        // Persona a la que se le va a recargar saldo.
        $recargar = mb_substr($request->query('recargar'), 0, 40);
        $personaRecarga = null;
        if ($recargar !== '') {
            $personaRecarga = (new EstudianteRepository($this->db()))->findByCodigo(strtoupper(str_replace([' ', '.'], '', $recargar)));
            if ($personaRecarga === null || $personaRecarga['categoria'] === 'VISITANTE') {
                $this->flash('err', 'No hay un estudiante, profesor o trabajador con ese documento.');
                $personaRecarga = null;
            }
        }

        $hoy = date('Y-m-d');
        return $this->view('caja/index', [
            'titulo'     => 'Caja',
            'scripts'    => ['caja.js', 'latido.js'],
            'buscar'     => $buscar,
            'cobros'     => $cobros->pendientes($buscar),
            'incidencias' => $visitas->incidenciasDeCobro(),
            'cobroSel'   => $cobroSel,
            'recargar'   => $recargar,
            'personaRecarga' => $personaRecarga,
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

    /** Caja confirma el pago de un cobro diario pendiente. */
    public function pagarCobro(Request $request): Response
    {
        $idCobro = $request->int('id_cobro');
        if ($idCobro === null) {
            $this->flash('err', 'Selecciona el cobro que vas a recibir.');
            return $this->redirect('caja');
        }
        try {
            $pago = (new ServicioPago($this->db()))->registrarCobro(
                $idCobro,
                $request->input('medio_pago'),
                $this->usuarioId(),
                $request->input('referencia'),
            );
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
            return $this->redirect('caja');
        }
        $this->flash('ok', 'Pago confirmado. Comprobante ' . $pago['consecutivo'] . '. La salida queda autorizada.');
        return $this->redirect('caja/recibo/' . $pago['id_pago']);
    }

    /** Recarga de saldo. Si la persona tenía cobros pendientes, el saldo nuevo los paga de inmediato. */
    public function recargar(Request $request): Response
    {
        $idPersona = $request->int('id_estudiante');
        if ($idPersona === null) {
            $this->flash('err', 'Busca primero a la persona por su documento.');
            return $this->redirect('caja');
        }
        try {
            $r = (new ServicioSaldo($this->db()))->recargar(
                $idPersona,
                $request->input('monto'),
                $request->input('medio_pago'),
                $this->usuarioId(),
                $request->input('referencia'),
            );
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
            return $this->redirect('caja?recargar=' . rawurlencode($request->input('documento')));
        }
        $this->flash('ok', sprintf('Recarga registrada (comprobante %s). Saldo actual: %s.%s', $r['consecutivo'], money($r['saldo']),
            $r['cobros_pagados'] ? ' Se pagaron ' . count($r['cobros_pagados']) . ' cobro(s) pendiente(s) con el saldo.' : ''));
        return $this->redirect('caja/recarga/' . $r['id_movimiento']);
    }

    public function reciboRecarga(Request $request, int $id): Response
    {
        $mov = (new SaldoRepository($this->db()))->find($id);
        if ($mov === null || $mov['tipo'] !== 'RECARGA') {
            return $this->notFound('El comprobante de recarga no existe.');
        }
        if (!can('REPORTE_CAJA') && (int) $mov['registrado_por'] !== $this->usuarioId()) {
            return $this->view('errors/error', ['codigo' => 403, 'titulo' => 'Acceso no autorizado',
                'mensaje' => 'Solo puedes consultar las recargas que registraste.'], 'layouts/app', 403);
        }
        return $this->view('caja/recarga', ['titulo' => 'Recarga ' . $mov['consecutivo'], 'mov' => $mov]);
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
        // Las recargas de saldo también son dinero recibido por el cajero.
        $saldos = new SaldoRepository($this->db());
        $recargas = $saldos->recargasCajero($this->usuarioId(), $desde, $hasta);
        foreach ($recargas as $fila) {
            $total += (float) $fila['total'];
            if ($fila['medio_pago'] === 'EFECTIVO') {
                $efectivo += (float) $fila['total'];
            }
        }
        return [
            'titulo'   => 'Cierre de caja',
            'recargas' => $recargas,
            'detalleRecargas' => $saldos->detalleRecargasCajero($this->usuarioId(), $desde, $hasta),
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

    /** Reverso de una recarga mal digitada (Administrador, permiso SALDO_REVERSAR). */
    public function reversarRecarga(Request $request, int $id): Response
    {
        $mov = (new SaldoRepository($this->db()))->find($id);
        try {
            $r = (new ServicioSaldo($this->db()))->reversarRecarga($id, $this->usuarioId(), $request->input('motivo'));
            $this->flash('ok', 'Recarga reversada. Saldo actual: ' . money($r['saldo']) . '. El motivo quedó en la auditoría.');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        return $this->redirect($mov ? 'estudiantes/' . $mov['id_estudiante'] : 'reportes?tab=movimientos');
    }
}
