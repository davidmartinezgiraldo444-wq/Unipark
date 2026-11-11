<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Repositories\CobroRepository;
use App\Repositories\EstudianteRepository;
use App\Repositories\RecargaLineaRepository;
use App\Repositories\SaldoRepository;
use App\Services\AuthService;
use App\Services\Pasarela\Pasarelas;
use App\Services\ReglaNegocioException;
use App\Services\ReglasVisita;
use App\Services\ServicioRecargaLinea;

/**
 * «Mi saldo»: saldo prepago de la persona, historial y recargas voluntarias en línea.
 * Solo ve y opera sobre su propia cuenta (id_estudiante de la sesión, nunca del formulario).
 */
final class SaldoController extends Controller
{
    public function index(Request $request): Response
    {
        $persona = $this->persona();
        if ($persona === null) {
            return $this->sinPersona();
        }
        $id = (int) $persona['id_estudiante'];
        $pendientes = (new CobroRepository($this->db()))->pendientesDePersona($id);
        return $this->view('saldo/index', [
            'titulo'      => 'Mi saldo',
            'persona'     => $persona,
            'pendiente'   => array_sum(array_map(static fn ($c) => ReglasVisita::centavos($c['valor']), $pendientes)) / 100,
            'pendientes'  => $pendientes,
            'enLinea'     => Pasarelas::disponible(),
            'recargas'    => (new RecargaLineaRepository($this->db()))->dePersona($id, 30),
            'movimientos' => (new SaldoRepository($this->db()))->dePersona($id, 40),
            'cobros'      => (new CobroRepository($this->db()))->dePersona($id, 30),
        ]);
    }

    public function formRecarga(Request $request): Response
    {
        $persona = $this->persona();
        if ($persona === null) {
            return $this->sinPersona();
        }
        return $this->view('saldo/recargar', [
            'titulo'   => 'Recargar saldo',
            'persona'  => $persona,
            'enLinea'  => Pasarelas::disponible(),
            'cfg'      => ServicioRecargaLinea::config(),
            'error'    => null,
            'monto'    => $request->query('monto'),
            'scripts'  => ['mi-saldo.js'],
        ]);
    }

    public function crear(Request $request): Response
    {
        $persona = $this->persona();
        if ($persona === null) {
            return $this->sinPersona();
        }
        // El valor elegido en los botones o el escrito en «Otro valor».
        $monto = $request->input('otro') !== '' ? $request->input('otro') : $request->input('monto');
        try {
            $recarga = (new ServicioRecargaLinea($this->db()))->crear((int) $persona['id_estudiante'], $this->usuarioId(), $monto);
        } catch (ReglaNegocioException $e) {
            return $this->view('saldo/recargar', [
                'titulo' => 'Recargar saldo', 'persona' => $persona, 'enLinea' => Pasarelas::disponible(),
                'cfg' => ServicioRecargaLinea::config(), 'error' => $e->getMessage(), 'monto' => $monto, 'scripts' => ['mi-saldo.js'],
            ], 'layouts/app', 422);
        }
        return $this->redirect('mi-saldo/recargas/' . $recarga['id_recarga']);
    }

    /**
     * Detalle de una recarga: resumen y botón hacia la página de pago (si está CREADA) o su estado.
     * También es la URL de retorno de la pasarela: el ?id= que trae el navegador NO confirma el pago.
     */
    public function ver(Request $request, int $id): Response
    {
        $persona = $this->persona();
        $recargas = new RecargaLineaRepository($this->db());
        $recarga = $persona ? $recargas->find($id) : null;
        if ($recarga === null || (int) $recarga['id_estudiante'] !== (int) $persona['id_estudiante']) {
            return $this->notFound('La recarga no existe.');
        }
        $servicio = new ServicioRecargaLinea($this->db());
        $volvio = $request->query('id') !== '' || $request->query('volvio') === '1';
        if ($volvio && $servicio->reportarRetorno($id, (int) $persona['id_estudiante'], $request->query('id'))) {
            $recarga = $recargas->find($id);
        }
        $formulario = null;
        try {
            // La pasarela devuelve a la persona a la página puente sin sesión (ver retorno()).
            $formulario = $servicio->formularioPago($recarga, $persona, url_absoluta('pago/retorno?ref=' . rawurlencode($recarga['referencia'])));
        } catch (ReglaNegocioException) {
            // Pasarela apagada: se muestra el estado sin el botón de pago.
        }
        $respuesta = $this->view('saldo/recarga', [
            'titulo'     => 'Recarga ' . $recarga['referencia'],
            'recarga'    => $recargas->conReembolso($recarga),
            'formulario' => $formulario,
            'volvio'     => $volvio || $recarga['id_transaccion_reportada'] !== null,
            'saldo'      => $persona['saldo'],
            'scripts'    => ['mi-saldo.js'],
        ]);
        // Solo si el pago es en otro sitio (checkout.wompi.co) se amplía form-action; una URL propia (el simulador
        // de la demo, relativa) ya está permitida por 'self'.
        $partes = $formulario !== null ? parse_url($formulario['url']) : null;
        if (isset($partes['scheme'], $partes['host'])) {
            $respuesta->conFormularioHacia($partes['scheme'] . '://' . $partes['host']);
        }
        return $respuesta;
    }

    /**
     * Regreso desde la página de pago, SIN sesión (ver app/routes.php). No muestra datos de la recarga ni de la
     * persona: solo anota el id reportado y redirige, desde este mismo sitio, al detalle de la recarga. Esa segunda
     * navegación es del mismo sitio, así que el navegador sí envía la cookie de sesión (SameSite=Strict).
     */
    public function retorno(Request $request): Response
    {
        // La pasarela agrega su id a la URL de retorno. Si la agregara con «?» en lugar de «&», el id quedaría
        // dentro de ref («UPK-…?id=…»): se separa aquí para aceptar las dos formas.
        $ref = $request->query('ref');
        $id = $request->query('id');
        if (str_contains($ref, '?')) {
            [$ref, $resto] = explode('?', $ref, 2);
            parse_str($resto, $extra);
            $id = $id !== '' ? $id : (is_string($extra['id'] ?? null) ? $extra['id'] : '');
        }
        $idRecarga = (new ServicioRecargaLinea($this->db()))->reportarRetornoPorReferencia(mb_substr($ref, 0, 40), mb_substr($id, 0, 64));
        $destino = $idRecarga !== null ? 'mi-saldo/recargas/' . $idRecarga . '?volvio=1' : 'mi-saldo';
        return Response::html(\App\Helpers\View::render('saldo/retorno', [
            'titulo'    => 'Volviendo a UniPark',
            'destino'   => url($destino),
            'redirigir' => url($destino),
        ], 'layouts/plain'));
    }

    /** Estado en vivo de una recarga (la página consulta mientras la pasarela confirma). */
    public function estado(Request $request, int $id): Response
    {
        $persona = $this->persona();
        $recarga = $persona ? (new RecargaLineaRepository($this->db()))->find($id) : null;
        if ($recarga === null || (int) $recarga['id_estudiante'] !== (int) $persona['id_estudiante']) {
            return Response::json(['ok' => false, 'codigo' => 'NO_EXISTE', 'error' => 'La recarga no existe.'], 404);
        }
        return Response::json([
            'ok'       => true,
            'estado'   => $recarga['estado'],
            'etiqueta' => recarga_label($recarga['estado']),
            'final'    => !in_array($recarga['estado'], ['CREADA', 'PENDIENTE'], true),
            'detalle'  => $recarga['detalle_estado'],
            'saldo'    => (float) $persona['saldo'],
            'saldo_texto' => money($persona['saldo']),
        ]);
    }

    public function cancelar(Request $request, int $id): Response
    {
        $persona = $this->persona();
        if ($persona === null) {
            return $this->sinPersona();
        }
        try {
            (new ServicioRecargaLinea($this->db()))->cancelar($id, (int) $persona['id_estudiante'], $this->usuarioId());
            $this->flash('ok', 'Recarga cancelada. No se realizó ningún cobro.');
        } catch (ReglaNegocioException $e) {
            $this->flash('err', $e->getMessage());
        }
        return $this->redirect('mi-saldo');
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
