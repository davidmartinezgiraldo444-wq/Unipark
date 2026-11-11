<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Cliente HTTP mínimo para hablar con servicios externos (pasarela de pagos).
 * Es una interfaz para que las pruebas usen un cliente falso y nunca salgan a internet.
 */
interface ClienteHttp
{
    /**
     * @param array<string,string> $cabeceras
     * @return array{estado:int,cuerpo:string,error:?string} estado 0 si no hubo respuesta
     */
    public function get(string $url, array $cabeceras = []): array;
}
