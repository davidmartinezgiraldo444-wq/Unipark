<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Identificación INDIVIDUAL del vehículo (placa, etiqueta RFID, etc.), separada de la clasificación de
 * categoría que hace la IA. Hoy no hay hardware ni aprobación para esto: la implementación activa es
 * SinIdentificadorVehiculo y la coincidencia de salida compara solo la categoría.
 *
 * Una implementación futura (lector de placas, RFID) devolvería un identificador estable y permitiría
 * afirmar que el vehículo que sale es el mismo que entró. Requiere aprobación: la política vigente prohíbe
 * guardar placas.
 */
interface IdentificadorVehiculo
{
    /** Identificador del vehículo frente a la cámara, o null si no hay un identificador confiable. */
    public function identificar(int $idPuesto): ?string;

    public function disponible(): bool;
}
