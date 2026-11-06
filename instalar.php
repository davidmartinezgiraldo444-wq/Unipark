<?php
declare(strict_types=1);

// UniPark · Instalador web.
// Pide los datos de MySQL, crea la base y las cuentas, guarda config/env.php y se bloquea.
// Para reinstalar hay que borrar storage/installed.lock en el servidor.

require __DIR__ . '/app/bootstrap.php';

use App\Helpers\Csrf;
use App\Helpers\Logger;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Services\Instalador;
use App\Services\ReglaNegocioException;

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
View::setBase($base);
Session::start();

$responder = static function (string $plantilla, array $datos, int $estado = 200): never {
    Response::html(View::render($plantilla, $datos + ['titulo' => 'Instalación'], 'layouts/auth'), $estado)->send();
    exit;
};

if (Instalador::instalado()) {
    $responder('install/bloqueado', [], 403);
}

$requisitos = Instalador::requisitos();
$cumple = !in_array(false, array_column($requisitos, 'ok'), true);

$datos = [
    'host' => '127.0.0.1', 'port' => '3306', 'base' => 'unipark_db', 'usuario' => 'root',
    'crear_cuenta' => true, 'cuenta_app' => 'unipark_app', 'demo' => true, 'sobrescribir' => false,
];
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $texto = static fn (string $k, string $d = '') => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : $d;
    $datos = [
        'host'         => $texto('host', '127.0.0.1'),
        'port'         => $texto('port', '3306'),
        'base'         => $texto('base', 'unipark_db'),
        'usuario'      => $texto('usuario'),
        'clave'        => is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '',
        'crear_cuenta' => isset($_POST['crear_cuenta']),
        'cuenta_app'   => $texto('cuenta_app', 'unipark_app'),
        'demo'         => isset($_POST['demo']),
        'sobrescribir' => isset($_POST['sobrescribir']),
    ];

    if (!Csrf::valid($_POST['_csrf'] ?? null)) {
        $error = 'El formulario expiró. Recarga la página e inténtalo de nuevo.';
    } elseif (!$cumple) {
        $error = 'El servidor no cumple los requisitos indicados.';
    } else {
        try {
            $instalador = new Instalador();
            $resultado = $instalador->instalar($datos);
            $instalador->escribirConfiguracion([
                'APP_ENV'              => 'produccion',
                'APP_DEBUG'            => '0',
                'SESSION_IDLE_MINUTES' => '15',
                'DB_HOST'              => $datos['host'],
                'DB_PORT'              => $datos['port'],
                'DB_NAME'              => $datos['base'],
                'DB_USER'              => $resultado['app_usuario'],
                'DB_PASS'              => $resultado['app_clave'],
                // La cuenta administrativa solo la usan los respaldos y las pruebas desde la consola.
                'DB_ADMIN_USER'        => $datos['usuario'],
                'DB_ADMIN_PASS'        => $datos['clave'],
                'TEST_DB_NAME'         => $datos['base'] . '_test',
                'MYSQL_BIN_DIR'        => Instalador::detectarBinMysql(),
            ]);
            $instalador->bloquear();
            Logger::info('Instalación completada', ['base' => $datos['base'], 'demo' => $datos['demo']]);
            Session::destroy();
            $responder('install/listo', ['pasos' => $resultado['pasos'], 'demo' => $datos['demo']]);
        } catch (ReglaNegocioException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            Logger::error('Instalación fallida: ' . $e->getMessage(), ['archivo' => $e->getFile() . ':' . $e->getLine()]);
            $error = 'Error inesperado durante la instalación: ' . $e->getMessage();
        }
    }
}

$responder('install/form', compact('datos', 'error', 'requisitos', 'cumple'), $error ? 422 : 200);
