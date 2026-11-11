<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Helpers\ClienteHttp;

/**
 * Cliente HTTP falso: las pruebas nunca salen a internet ni usan credenciales reales.
 * Responde lo programado para cada transacción y registra cada llamada (URL y cabeceras).
 */
final class ClienteHttpFalso implements ClienteHttp
{
    /** @var array<string,array{estado:int,cuerpo:string,error:?string}> id de transacción => respuesta */
    public array $respuestas = [];
    /** @var list<array{url:string,cabeceras:array}> */
    public array $llamadas = [];

    /** Programa la respuesta de GET /transactions/{id} como la devolvería Wompi. */
    public function transaccion(string $id, string $referencia, string $estado, int $centavos, string $moneda = 'COP'): void
    {
        $this->respuestas[$id] = ['estado' => 200, 'error' => null, 'cuerpo' => json_encode(['data' => [
            'id' => $id, 'reference' => $referencia, 'status' => $estado, 'amount_in_cents' => $centavos,
            'currency' => $moneda, 'payment_method_type' => 'PSE',
        ]])];
    }

    public function fallar(string $id, int $estado = 503): void
    {
        $this->respuestas[$id] = ['estado' => $estado, 'cuerpo' => '', 'error' => $estado === 0 ? 'sin conexión' : null];
    }

    public function get(string $url, array $cabeceras = []): array
    {
        $this->llamadas[] = ['url' => $url, 'cabeceras' => $cabeceras];
        $id = rawurldecode((string) substr($url, strrpos($url, '/') + 1));
        return $this->respuestas[$id] ?? ['estado' => 404, 'cuerpo' => '{"error":{"type":"NOT_FOUND_ERROR"}}', 'error' => null];
    }
}
