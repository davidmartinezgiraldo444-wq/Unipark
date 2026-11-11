<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Logger;
use App\Helpers\WebPush;
use App\Repositories\CobroRepository;
use App\Repositories\NotificacionRepository;
use PDO;

/** Avisos a la persona: quedan en "Mi cuenta" y se envían al celular como notificación push. */
final class ServicioNotificacion
{
    private NotificacionRepository $repo;

    public function __construct(private PDO $pdo)
    {
        $this->repo = new NotificacionRepository($pdo);
    }

    /** Venció la tolerancia y el saldo no alcanzó: el valor quedó pendiente en Caja. */
    public function pagoPendiente(int $idEstudiante, int $idCobro, string $valor, string $saldo, bool $diferencia = false): void
    {
        $titulo = 'Pago pendiente · Diríjase a Caja';
        // Con recargas en línea, el aviso lleva a Mi saldo: la persona recarga desde el celular y el cobro
        // pendiente se paga solo. Nunca se cobra una tarjeta ni una cuenta bancaria.
        $enLinea = \App\Services\Pasarela\Pasarelas::disponible();
        $accion = $enLinea ? 'recarga en Mi saldo o paga en Caja antes de salir.' : 'paga en Caja antes de salir.';
        // Es el aviso del COBRO, no de la salida: la persona sigue dentro y nada se le niega todavía.
        $mensaje = ($diferencia
            ? sprintf('Hoy usaste un vehículo de tarifa mayor. Diferencia a pagar: %s. Tu saldo (%s) no alcanza: %s', money($valor), money($saldo), $accion)
            : sprintf('No se pudo completar el cobro del día (%s): tu saldo (%s) no alcanza. Tu vehículo continúa registrado dentro del parqueadero; %s',
                money($valor), money($saldo), $accion));
        $id = $this->repo->crear($idEstudiante, $idCobro, 'PAGO_PENDIENTE', $titulo, $mensaje);
        if ($id === null) {
            return; // Ya se avisó de este cobro.
        }
        (new CobroRepository($this->pdo))->marcarNotificado($idCobro);
        if ($this->push($idEstudiante, ['titulo' => $titulo, 'cuerpo' => $mensaje, 'url' => $enLinea ? 'mi-saldo/recargar' : 'mi-cuenta', 'etiqueta' => 'cobro-' . $idCobro]) > 0) {
            $this->repo->marcarEnviada($id);
        }
    }

    /**
     * Venció la tolerancia y el cobro del día se pagó con el saldo. Es un aviso de PAGO, no de salida: el vehículo
     * sigue dentro; la salida se autoriza solo cuando la persona escanea su carné en la portería.
     */
    public function pagoVerificado(int $idEstudiante, int $idCobro, string $valor, string $saldo): void
    {
        $titulo = 'Pago verificado · Estacionamiento pagado';
        $mensaje = sprintf('Se descontaron %s de tu saldo UniPark (saldo actual: %s). Tu vehículo continúa registrado dentro del parqueadero. Al salir, escanea tu carné.',
            money($valor), money($saldo));
        $id = $this->repo->crear($idEstudiante, $idCobro, 'PAGO_VERIFICADO', $titulo, $mensaje);
        if ($id === null) {
            return; // Ya se avisó de este cobro.
        }
        if ($this->push($idEstudiante, ['titulo' => $titulo, 'cuerpo' => $mensaje, 'url' => 'mi-cuenta', 'etiqueta' => 'cobro-' . $idCobro]) > 0) {
            $this->repo->marcarEnviada($id);
        }
    }

    /** Resultado de una recarga en línea: APROBADA, RECHAZADA, ANULADA o ERROR. */
    public function recarga(int $idEstudiante, string $estado, string $monto, string $saldo): void
    {
        [$titulo, $mensaje] = match ($estado) {
            'APROBADA' => ['Recarga aprobada', sprintf('Se acreditaron %s a tu saldo UniPark. Saldo actual: %s.', money($monto), money($saldo))],
            'REEMBOLSADA' => ['Recarga reembolsada', sprintf('Se descontaron %s de tu saldo UniPark por el reembolso de una recarga. Saldo actual: %s. Si pagaste en línea, la pasarela devuelve el dinero a tu medio de pago.', money($monto), money($saldo))],
            default    => ['Recarga no aprobada', sprintf('Tu recarga de %s no fue aprobada (%s). No se acreditó saldo; puedes intentarlo de nuevo.',
                money($monto), mb_strtolower($estado))],
        };
        $id = $this->repo->crear($idEstudiante, null, 'RECARGA_' . $estado, $titulo, $mensaje);
        if ($id !== null && $this->push($idEstudiante, ['titulo' => $titulo, 'cuerpo' => $mensaje, 'url' => 'mi-saldo', 'etiqueta' => 'recarga']) > 0) {
            $this->repo->marcarEnviada($id);
        }
    }

    /** Envía el mensaje a todos los navegadores suscritos de la persona. @return int envíos exitosos */
    public function push(int $idEstudiante, array $mensaje): int
    {
        $suscripciones = $this->repo->suscripcionesDePersona($idEstudiante);
        if (!$suscripciones || !extension_loaded('curl') || !extension_loaded('openssl')) {
            return 0;
        }
        $exitos = 0;
        foreach ($suscripciones as $s) {
            try {
                $r = WebPush::enviar($s, $mensaje, (string) config('push_asunto'));
            } catch (\Throwable $e) {
                $r = ['ok' => false, 'vencida' => false, 'error' => $e->getMessage()];
            }
            if ($r['ok']) {
                $exitos++;
                $this->repo->registrarEnvio((int) $s['id_suscripcion'], null);
            } elseif ($r['vencida']) {
                $this->repo->desactivarSuscripcion($s['endpoint'], null, $r['error']);
            } else {
                $this->repo->registrarEnvio((int) $s['id_suscripcion'], $r['error']);
                Logger::error('Push no entregado', ['suscripcion' => (int) $s['id_suscripcion'], 'error' => $r['error']]);
            }
        }
        return $exitos;
    }
}
