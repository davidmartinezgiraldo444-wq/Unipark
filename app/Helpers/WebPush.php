<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Notificaciones Web Push sin dependencias externas (RFC 8030, 8291 y 8292).
 *
 *  - Claves VAPID P-256 generadas una vez y guardadas en storage/push/vapid.php (fuera del alcance web).
 *  - El contenido viaja cifrado con aes128gcm: el servicio de push del navegador no puede leerlo.
 *  - Requiere las extensiones openssl y curl de PHP (incluidas en XAMPP) y HTTPS en el sitio
 *    (o localhost) para que el navegador permita suscribirse.
 */
final class WebPush
{
    private const ARCHIVO = '/storage/push/vapid.php';
    private const PREFIJO_SPKI = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** @return array{publica:string,privada_pem:string} publica en base64url (65 bytes sin comprimir) */
    public static function claves(): array
    {
        $archivo = BASE_PATH . self::ARCHIVO;
        if (is_file($archivo)) {
            return require $archivo;
        }
        $clave = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + self::opcionesOpenssl());
        if ($clave === false) {
            throw new \RuntimeException('No se pudo generar la clave VAPID: ' . openssl_error_string());
        }
        openssl_pkey_export($clave, $pem, null, self::opcionesOpenssl());
        $detalles = openssl_pkey_get_details($clave);
        $publica = self::b64("\x04" . self::pad32($detalles['ec']['x']) . self::pad32($detalles['ec']['y']));
        $datos = ['publica' => $publica, 'privada_pem' => $pem];
        if (!is_dir(dirname($archivo))) {
            mkdir(dirname($archivo), 0750, true);
        }
        file_put_contents($archivo, "<?php\n// Claves VAPID de UniPark. No compartir.\nreturn " . var_export($datos, true) . ";\n", LOCK_EX);
        @chmod($archivo, 0640);
        return $datos;
    }

    /**
     * Envía un mensaje a una suscripción.
     * @param array{endpoint:string,p256dh:string,auth:string} $sub
     * @return array{ok:bool,estado:int,vencida:bool,error:?string}
     */
    public static function enviar(array $sub, array $mensaje, string $asunto, int $ttl = 3600): array
    {
        if (!self::servicioPermitido($sub['endpoint'])) {
            return ['ok' => false, 'estado' => 0, 'vencida' => true, 'error' => 'Servicio de push no permitido'];
        }
        $claves = self::claves();
        $cuerpo = self::cifrar(json_encode($mensaje, JSON_UNESCAPED_UNICODE), $sub['p256dh'], $sub['auth']);
        $jwt = self::jwtVapid($sub['endpoint'], $asunto, $claves['privada_pem']);

        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            // Solo HTTPS y sin seguir redirecciones: el servidor nunca termina en una URL distinta.
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: ' . $ttl,
                'Urgency: high',
                'Authorization: vapid t=' . $jwt . ', k=' . $claves['publica'],
            ],
        ]);
        $respuesta = curl_exec($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        $ok = $estado >= 200 && $estado < 300;
        return [
            'ok'      => $ok,
            'estado'  => $estado,
            // 404 y 410: la suscripción ya no existe (el usuario la revocó o desinstaló el navegador).
            'vencida' => in_array($estado, [404, 410], true),
            'error'   => $ok ? null : ($errorCurl !== '' ? $errorCurl : 'HTTP ' . $estado . ' ' . mb_substr((string) $respuesta, 0, 150)),
        ];
    }

    /**
     * El endpoint pertenece a un servicio de push conocido (config push_servicios). Evita SSRF: sin esta
     * comprobación, una cuenta podría registrar una URL interna y el servidor le haría peticiones.
     */
    public static function servicioPermitido(string $endpoint): bool
    {
        $p = parse_url($endpoint);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['port'])) {
            return false;
        }
        $host = strtolower($p['host']);
        foreach ((array) config('push_servicios', []) as $permitido) {
            $permitido = strtolower($permitido);
            if (str_starts_with($permitido, '*.')
                ? str_ends_with($host, substr($permitido, 1)) && strlen($host) > strlen($permitido) - 1
                : $host === $permitido) {
                return true;
            }
        }
        return false;
    }

    /** Cifrado aes128gcm del mensaje para la clave del navegador (RFC 8291). */
    public static function cifrar(string $texto, string $p256dhB64, string $authB64, ?string $sal = null, $efimera = null): string
    {
        $uaPublica = self::unb64($p256dhB64);
        $authSecret = self::unb64($authB64);
        if (strlen($uaPublica) !== 65 || strlen($authSecret) !== 16) {
            throw new \InvalidArgumentException('Suscripción push con claves inválidas');
        }

        $efimera ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + self::opcionesOpenssl());
        $det = openssl_pkey_get_details($efimera);
        $asPublica = "\x04" . self::pad32($det['ec']['x']) . self::pad32($det['ec']['y']);

        // Sin longitud: PHP 8.5 declara obsoleto ese parámetro; P-256 deriva siempre 32 bytes.
        $secreto = openssl_pkey_derive(self::publicaPem($uaPublica), $efimera);
        if ($secreto === false) {
            throw new \RuntimeException('No se pudo derivar el secreto ECDH');
        }
        $sal ??= random_bytes(16);

        $ikm = hash_hkdf('sha256', $secreto, 32, "WebPush: info\x00" . $uaPublica . $asPublica, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $sal);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $sal);

        $cifrado = openssl_encrypt($texto . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        return $sal . pack('N', 4096) . chr(65) . $asPublica . $cifrado . $tag;
    }

    /** JWT ES256 para el servicio de push del navegador (RFC 8292). */
    public static function jwtVapid(string $endpoint, string $asunto, string $privadaPem): string
    {
        $partes = parse_url($endpoint);
        $aud = $partes['scheme'] . '://' . $partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '');
        $cabecera = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $datos = self::b64(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $asunto]));
        openssl_sign($cabecera . '.' . $datos, $firmaDer, $privadaPem, OPENSSL_ALGO_SHA256);
        return $cabecera . '.' . $datos . '.' . self::b64(self::derARaw($firmaDer));
    }

    public static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64(string $texto): string
    {
        $texto = strtr($texto, '-_', '+/');
        return (string) base64_decode($texto . str_repeat('=', (4 - strlen($texto) % 4) % 4), true);
    }

    /** Clave pública P-256 sin comprimir → PEM SubjectPublicKeyInfo. */
    public static function publicaPem(string $raw)
    {
        $der = hex2bin(self::PREFIJO_SPKI) . $raw;
        return openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
    }

    /** Firma ECDSA DER (SEQUENCE de dos INTEGER) → r||s de 32 bytes cada uno. */
    private static function derARaw(string $der): string
    {
        $pos = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $pos += ord($der[1]) & 0x7f;
        }
        $leer = static function () use ($der, &$pos): string {
            $largo = ord($der[$pos + 1]);
            $valor = substr($der, $pos + 2, $largo);
            $pos += 2 + $largo;
            return str_pad(ltrim($valor, "\x00"), 32, "\x00", STR_PAD_LEFT);
        };
        return $leer() . $leer();
    }

    private static function pad32(string $bin): string
    {
        return str_pad($bin, 32, "\x00", STR_PAD_LEFT);
    }

    /** En Windows/XAMPP openssl necesita su archivo de configuración para generar claves. */
    private static function opcionesOpenssl(): array
    {
        if (getenv('OPENSSL_CONF')) {
            return [];
        }
        foreach ([dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf', 'C:/xampp/php/extras/ssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf'] as $cnf) {
            if (is_file($cnf)) {
                return ['config' => $cnf];
            }
        }
        return [];
    }
}
