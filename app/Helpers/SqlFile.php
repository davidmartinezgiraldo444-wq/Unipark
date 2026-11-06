<?php
declare(strict_types=1);

namespace App\Helpers;

use PDO;

/**
 * Ejecuta archivos .sql respetando DELIMITER, igual que el cliente mysql.
 * Así schema.sql sirve tanto para la consola como para el instalador y las pruebas.
 */
final class SqlFile
{
    /** @return list<string> */
    public static function statements(string $archivo): array
    {
        $sql = file_get_contents($archivo);
        if ($sql === false) {
            throw new \RuntimeException('No se pudo leer ' . $archivo);
        }
        $delimitador = ';';
        $actual = '';
        $sentencias = [];
        foreach (preg_split('/\R/', $sql) as $linea) {
            $limpia = trim($linea);
            if (preg_match('/^DELIMITER\s+(\S+)$/i', $limpia, $m)) {
                $delimitador = $m[1];
                continue;
            }
            if ($actual === '' && ($limpia === '' || str_starts_with($limpia, '--'))) {
                continue;
            }
            $actual .= $linea . "\n";
            if (str_ends_with(rtrim($linea), $delimitador)) {
                $sentencia = trim(substr(rtrim($actual), 0, -strlen($delimitador)));
                if ($sentencia !== '') {
                    $sentencias[] = $sentencia;
                }
                $actual = '';
            }
        }
        if (trim($actual) !== '') {
            $sentencias[] = trim($actual);
        }
        return $sentencias;
    }

    public static function run(PDO $pdo, string $archivo): int
    {
        $sentencias = self::statements($archivo);
        foreach ($sentencias as $sentencia) {
            $pdo->exec($sentencia);
        }
        return count($sentencias);
    }
}
