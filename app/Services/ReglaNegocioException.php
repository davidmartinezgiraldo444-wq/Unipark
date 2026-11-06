<?php
declare(strict_types=1);

namespace App\Services;

/** Operación rechazada por una regla de negocio. El mensaje se puede mostrar al usuario. */
final class ReglaNegocioException extends \RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly array $datos = [],
    ) {
        parent::__construct($mensaje);
    }
}
