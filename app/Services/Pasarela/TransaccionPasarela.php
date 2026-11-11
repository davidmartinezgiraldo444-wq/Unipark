<?php
declare(strict_types=1);

namespace App\Services\Pasarela;

/**
 * Transacción de una pasarela, ya traducida al vocabulario de UniPark.
 * estado: PENDIENTE | APROBADA | RECHAZADA | ANULADA | ERROR | DESCONOCIDO
 */
final class TransaccionPasarela
{
    public const ESTADOS = ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA', 'ERROR', 'DESCONOCIDO'];

    public function __construct(
        public readonly string $proveedor,
        public readonly string $id,
        public readonly string $referencia,
        public readonly string $estado,
        public readonly string $estadoProveedor,
        public readonly int $montoCentavos,
        public readonly string $moneda,
        public readonly ?string $medio = null,
        /** SANDBOX o PRODUCCION: una recarga solo acepta transacciones de su mismo entorno. */
        public readonly string $entorno = 'SANDBOX',
    ) {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new \InvalidArgumentException('Estado de transacción no válido: ' . $estado);
        }
    }
}
