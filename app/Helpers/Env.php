<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Configuración de la instalación (config/env.php y .env).
 *
 * Los valores se guardan en un arreglo de PHP propio de cada petición, NUNCA con putenv():
 * Apache en Windows atiende peticiones en hilos del mismo proceso, y putenv()/getenv() no son
 * seguros entre hilos. Con putenv(), una petición podía leer DB_PASS vacío mientras otra cargaba la
 * configuración (auditoría del 08/11/2026: ~17 % de fallos de conexión con peticiones simultáneas).
 *
 * Prioridad: variables reales del entorno del proceso o del servidor (solo se leen) > archivos.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $valores = [];

    public static function load(string $archivo): void
    {
        if (!is_file($archivo)) {
            return;
        }
        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
            self::$valores[$clave] ??= trim($valor, "\"'");
        }
    }

    /**
     * Carga config/env.php, el archivo que escribe el instalador. Es PHP y no texto plano:
     * aunque el servidor ignorara los .htaccess, pedirlo por la web no muestra su contenido.
     */
    public static function loadPhp(string $archivo): void
    {
        if (!is_file($archivo)) {
            return;
        }
        $valores = require $archivo;
        if (!is_array($valores)) {
            return;
        }
        foreach ($valores as $clave => $valor) {
            self::$valores[(string) $clave] ??= (string) $valor;
        }
    }

    /**
     * Valores de una INSTANCIA (por ejemplo, la copia de demostración en htdocs/UniParkDemo): un archivo PHP que
     * devuelve un arreglo. Tienen prioridad sobre config/env.php y .env, así la instancia usa su propia base,
     * su propia cookie de sesión y su propia pasarela sin duplicar el código.
     */
    public static function forzarDesde(string $archivo): void
    {
        $valores = is_file($archivo) ? require $archivo : null;
        if (!is_array($valores)) {
            throw new \RuntimeException('La configuración de la instancia no existe o no es válida: ' . basename($archivo));
        }
        foreach ($valores as $clave => $valor) {
            self::set((string) $clave, (string) $valor);
        }
    }

    /** Valor fijado por el propio proceso (pruebas, scripts). Tiene prioridad sobre los archivos. */
    public static function set(string $clave, string $valor): void
    {
        self::$valores[$clave] = $valor;
        self::$forzados[$clave] = true;
    }

    /** @var array<string,true> */
    private static array $forzados = [];

    public static function get(string $clave, ?string $defecto = null): ?string
    {
        if (isset(self::$forzados[$clave])) {
            return self::$valores[$clave];
        }
        // Lectura del entorno real (por ejemplo, variables del hosting). getenv() solo lee: es seguro.
        $real = getenv($clave);
        if ($real !== false && $real !== '') {
            return $real;
        }
        $valor = self::$valores[$clave] ?? null;
        return ($valor === null || $valor === '') ? $defecto : $valor;
    }
}
