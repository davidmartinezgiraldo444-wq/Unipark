<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditoriaRepository;
use PDO;

/** Registra quién hizo qué, cuándo, con qué resultado y por qué (RN-10, RF-17). */
final class AuditService
{
    /** IP de la solicitud en curso; la fija el Kernel. */
    public static ?string $ip = null;

    private AuditoriaRepository $repo;

    public function __construct(PDO $pdo)
    {
        $this->repo = new AuditoriaRepository($pdo);
    }

    public function log(
        string $accion,
        string $entidad,
        ?int $idRegistro,
        string $resultado = 'EXITO',
        ?string $motivo = null,
        array $detalle = [],
        ?int $usuario = null,
    ): void {
        $this->repo->create(
            $usuario ?? AuthService::id(),
            $accion,
            $entidad,
            $idRegistro,
            $resultado,
            $motivo !== null ? mb_substr($motivo, 0, 255) : null,
            self::$ip,
            $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        );
    }
}
