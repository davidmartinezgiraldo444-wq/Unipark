<?php
declare(strict_types=1);

namespace App\Helpers;

/** Detalles técnicos a storage/logs, nunca a la pantalla (RF-18). */
final class Logger
{
    public static function error(string $mensaje, array $contexto = []): void
    {
        self::write('ERROR', $mensaje, $contexto);
    }

    public static function info(string $mensaje, array $contexto = []): void
    {
        self::write('INFO', $mensaje, $contexto);
    }

    private static function write(string $nivel, string $mensaje, array $contexto): void
    {
        $linea = json_encode([
            'fecha'    => date('c'),
            'nivel'    => $nivel,
            'mensaje'  => $mensaje,
            'contexto' => $contexto,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        @file_put_contents(
            BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log',
            $linea . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
