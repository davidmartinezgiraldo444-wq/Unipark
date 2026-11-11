<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Sesión con cookie HttpOnly, SameSite y Secure en HTTPS (RNF-02).
 * En la línea de comandos (pruebas) usa $_SESSION como un arreglo normal.
 */
final class Session
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION ??= [];
            return;
        }
        $https = Request::esHttps();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        // Cada instancia (principal o demostración) tiene su propia cookie: nunca comparten sesión.
        $nombre = (string) \App\Helpers\Env::get('SESSION_NAME', 'UNIPARK_SESSION');
        session_name(preg_match('/^[A-Z_]{4,32}$/', $nombre) === 1 ? $nombre : 'UNIPARK_SESSION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public static function get(string $clave, mixed $defecto = null): mixed
    {
        return $_SESSION[$clave] ?? $defecto;
    }

    public static function set(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public static function forget(string ...$claves): void
    {
        foreach ($claves as $clave) {
            unset($_SESSION[$clave]);
        }
    }

    /** Nuevo identificador al autenticar, contra fijación de sesión. */
    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_destroy();
    }

    public static function flash(string $tipo, string $mensaje): void
    {
        $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    /** @return list<array{tipo:string,mensaje:string}> */
    public static function takeFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }
}
