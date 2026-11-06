<?php
declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;

/** Conexión PDO única por solicitud, con consultas preparadas reales (RNF-03). */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        return self::$pdo ??= self::connect(require BASE_PATH . '/config/database.php');
    }

    /** @param array{host:string,port:string,name:?string,user:string,pass:string} $c */
    public static function connect(array $c): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $c['host'], $c['port']);
        if (!empty($c['name'])) {
            $dsn .= ';dbname=' . $c['name'];
        }
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => true,
        ]);
        // Zona horaria y modo estricto iguales en todas las conexiones (RNF-08).
        $pdo->exec("SET time_zone = '" . config('offset_mysql') . "', "
            . "sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }

    public static function set(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function current(): ?PDO
    {
        return self::$pdo;
    }

    /** Violación de un índice UNIQUE (visita abierta, pago válido, documento, login). */
    public static function isDuplicate(PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
