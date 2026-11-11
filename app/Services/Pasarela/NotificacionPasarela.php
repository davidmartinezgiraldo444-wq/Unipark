<?php
declare(strict_types=1);

namespace App\Services\Pasarela;

/** Notificación (webhook) cuya firma ya se verificó. */
final class NotificacionPasarela
{
    public function __construct(
        public readonly string $tipoEvento,
        /** null si el evento no es de una transacción (p. ej. tokens de Nequi). */
        public readonly ?TransaccionPasarela $transaccion,
        /** SHA-256 del cuerpo recibido: identifica reenvíos idénticos. */
        public readonly string $huella,
    ) {
    }
}
