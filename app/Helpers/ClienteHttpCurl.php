<?php
declare(strict_types=1);

namespace App\Helpers;

/** ClienteHttp con cURL: solo HTTPS, verificación del certificado, sin redirecciones y con tiempo límite. */
final class ClienteHttpCurl implements ClienteHttp
{
    public function __construct(private int $timeout = 10)
    {
    }

    public function get(string $url, array $cabeceras = []): array
    {
        if (!extension_loaded('curl')) {
            return ['estado' => 0, 'cuerpo' => '', 'error' => 'La extensión curl de PHP no está disponible'];
        }
        $lineas = [];
        foreach ($cabeceras as $nombre => $valor) {
            $lineas[] = $nombre . ': ' . $valor;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $lineas,
        ]);
        $cuerpo = curl_exec($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'estado' => $cuerpo === false ? 0 : $estado,
            'cuerpo' => is_string($cuerpo) ? $cuerpo : '',
            'error'  => $error !== '' ? $error : null,
        ];
    }
}
