<?php
declare(strict_types=1);

namespace App\Services;

/** Implementación actual: no existe identificación individual. Nunca afirma que dos vehículos son el mismo. */
final class SinIdentificadorVehiculo implements IdentificadorVehiculo
{
    public function identificar(int $idPuesto): ?string
    {
        return null;
    }

    public function disponible(): bool
    {
        return false;
    }
}
