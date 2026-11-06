<?php
declare(strict_types=1);

namespace App\Helpers;

/** Plantillas PHP en app/Views, con un layout opcional. */
final class View
{
    private static string $base = '';

    public static function setBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    public static function render(string $plantilla, array $datos = [], ?string $layout = 'layouts/app'): string
    {
        $contenido = self::capture($plantilla, $datos);
        if ($layout === null) {
            return $contenido;
        }
        return self::capture($layout, ['contenido' => $contenido] + $datos);
    }

    private static function capture(string $__plantilla, array $__variables): string
    {
        // Los nombres con __ evitan que una variable de la vista (por ejemplo $datos) choque con los de esta función.
        $__archivo = BASE_PATH . '/app/Views/' . $__plantilla . '.php';
        if (!is_file($__archivo)) {
            throw new \RuntimeException('Vista no encontrada: ' . $__plantilla);
        }
        extract($__variables, EXTR_SKIP);
        ob_start();
        try {
            require $__archivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
