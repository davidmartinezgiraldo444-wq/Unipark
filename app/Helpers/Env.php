<?php
declare(strict_types=1);

namespace App\Helpers;

/** Lector mínimo de archivos .env. Las variables ya definidas en el entorno tienen prioridad. */
final class Env
{
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
            $valor = trim($valor, "\"'");
            if (getenv($clave) === false) {
                putenv($clave . '=' . $valor);
                $_ENV[$clave] = $valor;
            }
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
            if (getenv((string) $clave) === false) {
                putenv($clave . '=' . $valor);
                $_ENV[$clave] = (string) $valor;
            }
        }
    }

    public static function get(string $clave, ?string $defecto = null): ?string
    {
        $valor = getenv($clave);
        return ($valor === false || $valor === '') ? $defecto : $valor;
    }
}
