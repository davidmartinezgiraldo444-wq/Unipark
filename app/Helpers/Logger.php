<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Detalles técnicos a storage/logs, nunca a la pantalla (RF-18).
 * Cada instancia escribe en su propia carpeta (hallazgo B11): el sistema real en storage/logs, la copia de
 * demostración en storage/logs/demo y las pruebas automáticas en storage/logs/pruebas.
 */
final class Logger
{
    /** Canal fijado por quien corre fuera de la web (tests/run.php usa «pruebas»). */
    private static ?string $canal = null;

    public static function usarCanal(?string $canal): void
    {
        self::$canal = $canal === null ? null : preg_replace('/[^a-z0-9_-]/', '', strtolower($canal));
        ini_set('error_log', self::carpeta() . '/php-errors.log');
    }

    /** Carpeta de logs de esta instancia (se crea si no existe). */
    public static function carpeta(): string
    {
        $canal = self::$canal ?? preg_replace('/[^a-z0-9_-]/', '', (string) config('instancia'));
        $dir = BASE_PATH . '/storage/logs' . ($canal !== '' ? '/' . $canal : '');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

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

        @file_put_contents(self::carpeta() . '/app-' . date('Y-m-d') . '.log', $linea . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
