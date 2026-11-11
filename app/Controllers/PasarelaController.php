<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Logger;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\ServicioRecargaLinea;

/**
 * Webhook de la pasarela de pagos (servidor a servidor). Ruta pública sin sesión ni CSRF: la autenticidad
 * se comprueba con la firma del proveedor y, además, consultando la transacción con la llave privada.
 * Nunca responde detalles internos; el proveedor solo necesita el código HTTP.
 */
final class PasarelaController extends Controller
{
    public function eventos(Request $request): Response
    {
        if ($request->cuerpo === '') {
            return Response::json(['ok' => false], 400);
        }
        try {
            $r = (new ServicioRecargaLinea($this->db()))->procesarWebhook($request->cuerpo, $request->cabeceras);
        } catch (\Throwable $e) {
            // 500: el proveedor reintenta. Al log va el error, nunca el cuerpo ni datos del pagador.
            Logger::error('Webhook de pasarela falló: ' . $e->getMessage(), ['tipo' => $e::class, 'archivo' => $e->getFile() . ':' . $e->getLine()]);
            return Response::json(['ok' => false], 500);
        }
        return Response::json(['ok' => $r['http'] === 200, 'resultado' => $r['resultado']], $r['http']);
    }
}
