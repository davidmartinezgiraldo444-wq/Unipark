<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Services\AuditService;
use App\Services\AuthService;
use PDO;

abstract class Controller
{
    protected function db(): PDO
    {
        return Database::connection();
    }

    protected function view(string $plantilla, array $datos = [], string $layout = 'layouts/app', int $estado = 200): Response
    {
        return Response::html(View::render($plantilla, $datos, $layout), $estado);
    }

    protected function redirect(string $ruta): Response
    {
        return Response::redirect(url($ruta));
    }

    protected function flash(string $tipo, string $mensaje): void
    {
        Session::flash($tipo, $mensaje);
    }

    protected function audit(): AuditService
    {
        return new AuditService($this->db());
    }

    protected function usuarioId(): int
    {
        return (int) AuthService::id();
    }

    protected function notFound(string $mensaje = 'El registro solicitado no existe.'): Response
    {
        return $this->view('errors/error', ['codigo' => 404, 'titulo' => 'No encontrado', 'mensaje' => $mensaje], 'layouts/app', 404);
    }
}
