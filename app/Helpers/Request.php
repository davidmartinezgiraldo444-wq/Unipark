<?php
declare(strict_types=1);

namespace App\Helpers;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly string $ip = '127.0.0.1',
        /** Cuerpo crudo de la solicitud (solo JSON, por ejemplo los webhooks de la pasarela). */
        public readonly string $cuerpo = '',
        /** @var array<string,string> cabeceras HTTP con el nombre en minúsculas */
        public readonly array $cabeceras = [],
    ) {
    }

    /** Tamaño máximo del cuerpo JSON que se lee (un evento de pasarela ocupa unos pocos KB). */
    private const MAX_CUERPO = 65536;

    public static function fromGlobals(string $base): self
    {
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $ruta = rawurldecode($ruta);
        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = substr($ruta, strlen($base));
        }
        if (str_starts_with($ruta, '/index.php')) {
            $ruta = substr($ruta, strlen('/index.php'));
        }
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $cuerpo = '';
        if ($metodo === 'POST' && str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $cuerpo = (string) file_get_contents('php://input', false, null, 0, self::MAX_CUERPO);
        }
        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_') && is_string($valor)) {
                $cabeceras[strtolower(str_replace('_', '-', substr($clave, 5)))] = $valor;
            }
        }
        return new self($metodo, '/' . trim($ruta, '/'), $_GET, $_POST, self::ipCliente(), $cuerpo, $cabeceras);
    }

    /**
     * IP real del visitante. Un túnel (ngrok, Cloudflare Tunnel) entrega todas las visitas desde 127.0.0.1;
     * solo en ese caso se confía en la cabecera que el túnel añade, para que la auditoría y el límite
     * de intentos de inicio de sesión distingan a cada persona.
     */
    public static function ipCliente(): string
    {
        $remota = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!in_array($remota, ['127.0.0.1', '::1'], true)) {
            return $remota;
        }
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $cabecera) {
            $valor = trim(explode(',', (string) ($_SERVER[$cabecera] ?? ''))[0]);
            if ($valor !== '' && filter_var($valor, FILTER_VALIDATE_IP) !== false) {
                return $valor;
            }
        }
        return $remota;
    }

    /** HTTPS directo o terminado por un túnel o proxy local. */
    public static function esHttps(): bool
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        return $local && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** Texto recortado desde POST o GET; nunca un arreglo. */
    public function input(string $clave, string $defecto = ''): string
    {
        $valor = $this->post[$clave] ?? $this->query[$clave] ?? $defecto;
        return is_string($valor) ? trim($valor) : $defecto;
    }

    /** Valor POST sin recortar, para contraseñas. */
    public function raw(string $clave): string
    {
        $valor = $this->post[$clave] ?? '';
        return is_string($valor) ? $valor : '';
    }

    public function query(string $clave, string $defecto = ''): string
    {
        $valor = $this->query[$clave] ?? $defecto;
        return is_string($valor) ? trim($valor) : $defecto;
    }

    public function int(string $clave): ?int
    {
        $valor = $this->input($clave);
        return ctype_digit($valor) && $valor !== '' ? (int) $valor : null;
    }
}
