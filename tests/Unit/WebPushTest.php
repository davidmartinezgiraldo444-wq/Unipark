<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\WebPush;
use Tests\Support\TestCase;

/**
 * Cifrado de notificaciones push (RFC 8291) y firma VAPID (RFC 8292).
 * Simula el navegador: genera sus claves, descifra el mensaje y verifica la firma.
 */
final class WebPushTest extends TestCase
{
    public function testElMensajeCifradoSoloLoDescifraElNavegadorSuscrito(): void
    {
        $opts = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + self::cnf();
        $navegador = openssl_pkey_new($opts);
        $det = openssl_pkey_get_details($navegador);
        $uaPublica = "\x04" . str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $auth = random_bytes(16);

        $texto = json_encode(['titulo' => 'Pago pendiente', 'cuerpo' => 'Valor $ 2.000 · diríjase a Caja'], JSON_UNESCAPED_UNICODE);
        $cuerpo = WebPush::cifrar($texto, WebPush::b64($uaPublica), WebPush::b64($auth));

        // Descifrado del lado del navegador.
        $sal = substr($cuerpo, 0, 16);
        $this->assertSame(4096, unpack('N', substr($cuerpo, 16, 4))[1], 'Tamaño de registro');
        $this->assertSame(65, ord($cuerpo[20]));
        $asPublica = substr($cuerpo, 21, 65);
        $datos = substr($cuerpo, 86);
        $secreto = openssl_pkey_derive(WebPush::publicaPem($asPublica), $navegador, 32);
        $ikm = hash_hkdf('sha256', $secreto, 32, "WebPush: info\x00" . $uaPublica . $asPublica, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $sal);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $sal);
        $plano = openssl_decrypt(substr($datos, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($datos, -16));

        $this->assertSame($texto . "\x02", $plano, 'El navegador recupera el mensaje con el delimitador final');

        // Otro navegador (otra clave de autenticación) no puede descifrarlo.
        $ikmFalso = hash_hkdf('sha256', $secreto, 32, "WebPush: info\x00" . $uaPublica . $asPublica, random_bytes(16));
        $cekFalso = hash_hkdf('sha256', $ikmFalso, 16, "Content-Encoding: aes128gcm\x00", $sal);
        $this->assertFalse(openssl_decrypt(substr($datos, 0, -16), 'aes-128-gcm', $cekFalso, OPENSSL_RAW_DATA, $nonce, substr($datos, -16)));
    }

    public function testLaFirmaVapidEsUnJwtEs256Valido(): void
    {
        $claves = WebPush::claves();
        $jwt = WebPush::jwtVapid('https://fcm.googleapis.com/fcm/send/abc', 'mailto:prueba@unipark.test', $claves['privada_pem']);
        [$cab, $datos, $firma] = explode('.', $jwt);
        $this->assertSame('ES256', json_decode(WebPush::unb64($cab), true)['alg']);
        $this->assertSame('https://fcm.googleapis.com', json_decode(WebPush::unb64($datos), true)['aud']);

        $raw = WebPush::unb64($firma);
        $this->assertSame(64, strlen($raw), 'Firma r||s de 64 bytes');
        // Reconstruye la firma DER y la verifica con la clave pública publicada.
        $entero = static function (string $v): string {
            $v = ltrim($v, "\x00");
            if (ord($v[0]) & 0x80) {
                $v = "\x00" . $v;
            }
            return "\x02" . chr(strlen($v)) . $v;
        };
        $seq = $entero(substr($raw, 0, 32)) . $entero(substr($raw, 32));
        $der = "\x30" . chr(strlen($seq)) . $seq;
        $publica = WebPush::publicaPem(WebPush::unb64($claves['publica']));
        $this->assertSame(1, openssl_verify($cab . '.' . $datos, $der, $publica, OPENSSL_ALGO_SHA256));
    }

    private static function cnf(): array
    {
        return is_file('C:/xampp/php/extras/ssl/openssl.cnf') && !getenv('OPENSSL_CONF')
            ? ['config' => 'C:/xampp/php/extras/ssl/openssl.cnf'] : [];
    }
}
