<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

abstract class Repository
{
    public function __construct(protected PDO $pdo)
    {
    }

    protected function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        return $fila === false ? null : $fila;
    }

    protected function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function run(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /** Escapa comodines de LIKE para que el texto del usuario se busque literalmente. */
    protected static function like(string $texto): string
    {
        return '%' . addcslashes($texto, '%_\\') . '%';
    }

    /**
     * Arma condiciones de rango de fechas comunes a los reportes.
     * @param array{desde?:string,hasta?:string} $f
     */
    protected static function rango(string $columna, array $f, array &$where, array &$params): void
    {
        if (!empty($f['desde'])) {
            $where[] = "$columna >= ?";
            $params[] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta'])) {
            $where[] = "$columna < ? + INTERVAL 1 DAY";
            $params[] = $f['hasta'] . ' 00:00:00';
        }
    }
}
