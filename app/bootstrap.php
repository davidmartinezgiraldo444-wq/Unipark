<?php
declare(strict_types=1);

// Arranque común para la web, los scripts y las pruebas.

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Autoload PSR-4 propio: el proyecto funciona sin Composer.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once BASE_PATH . '/app/Helpers/functions.php';

// Configuración: primero la del instalador (config/env.php), luego un .env de desarrollo si existe.
App\Helpers\Env::loadPhp(BASE_PATH . '/config/env.php');
App\Helpers\Env::load(BASE_PATH . '/.env');
// Instancia adicional (copia de demostración): su configuración se impone a la principal. Ver scripts/demo.php.
// En consola también se puede indicar con la variable UNIPARK_ENV_INSTANCIA (por ejemplo, para conciliar las
// recargas de la demo). Solo en consola: en Apache la instancia la fija únicamente su index.php.
if (!defined('UNIPARK_ENV_INSTANCIA') && PHP_SAPI === 'cli' && is_string(getenv('UNIPARK_ENV_INSTANCIA')) && getenv('UNIPARK_ENV_INSTANCIA') !== '') {
    define('UNIPARK_ENV_INSTANCIA', getenv('UNIPARK_ENV_INSTANCIA'));
}
if (defined('UNIPARK_ENV_INSTANCIA')) {
    App\Helpers\Env::forzarDesde(UNIPARK_ENV_INSTANCIA);
}

date_default_timezone_set(config('zona_horaria'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
// En consola (instalador, pruebas, respaldos) los errores se muestran; en la web solo con APP_DEBUG=1.
ini_set('display_errors', PHP_SAPI === 'cli' ? 'stderr' : (config('debug') ? '1' : '0'));
ini_set('log_errors', '1');
ini_set('error_log', App\Helpers\Logger::carpeta() . '/php-errors.log');

// Toda advertencia se convierte en excepción para no continuar en un estado dudoso.
set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if (!(error_reporting() & $nivel)) {
        return false;
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});
