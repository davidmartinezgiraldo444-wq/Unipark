<?php
declare(strict_types=1);

// UniPark · Punto de entrada único.
// Funciona en la raíz de un dominio o en una subcarpeta de htdocs (http://localhost/UniPark/).

// Servidor embebido de PHP (php -S): entregar los archivos estáticos tal cual.
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($archivo) && str_starts_with(realpath($archivo), __DIR__ . DIRECTORY_SEPARATOR . 'assets')) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';

use App\Helpers\Logger;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Kernel;
use App\Services\Instalador;

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
View::setBase($base);

// Sin instalar: todo se dirige al formulario de instalación.
if (!Instalador::instalado()) {
    header('Location: ' . $base . '/instalar.php', true, 303);
    exit;
}

try {
    $request = Request::fromGlobals($base);
    // Webhook y regreso desde la pasarela: sin sesión (no se envía Set-Cookie). Ver app/routes.php.
    if (Kernel::sinSesion($request->method, $request->path)) {
        $_SESSION = [];
    } else {
        Session::start();
    }
    $respuesta = (new Kernel())->handle($request);
} catch (Throwable $e) {
    Logger::error($e->getMessage(), ['tipo' => $e::class, 'archivo' => $e->getFile() . ':' . $e->getLine()]);
    $respuesta = Response::html(View::render('errors/error', [
        'codigo'  => 500,
        'titulo'  => 'Servicio no disponible',
        'mensaje' => 'No se pudo completar la solicitud. Inténtalo más tarde.',
    ], 'layouts/plain'), 500);
}

$respuesta->send();
