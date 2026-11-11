<?php
declare(strict_types=1);

namespace App\Services\Pasarela;

/** Falla de la pasarela: firma inválida, consulta fallida, configuración incompleta… */
final class PasarelaException extends \RuntimeException
{
    public function __construct(public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
