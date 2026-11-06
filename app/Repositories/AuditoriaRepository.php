<?php
declare(strict_types=1);

namespace App\Repositories;

final class AuditoriaRepository extends Repository
{
    public function create(?int $usuario, string $accion, string $entidad, ?int $idRegistro, string $resultado,
                           ?string $motivo, ?string $ip, ?string $detalle): int
    {
        return $this->insert(
            'INSERT INTO auditoria (id_usuario, accion, entidad, id_registro, resultado, motivo, ip_origen, detalle_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$usuario, $accion, $entidad, $idRegistro, $resultado, $motivo, $ip, $detalle]
        );
    }

    /** Intentos fallidos recientes para un login o una IP (limitación de intentos, sección 10). */
    public function fallidosRecientes(string $login, string $ip, int $segundos): array
    {
        $fila = $this->one(
            "SELECT SUM(JSON_UNQUOTE(JSON_EXTRACT(detalle_json, '$.login')) = ?) AS por_login,
                    SUM(ip_origen = ?) AS por_ip
               FROM auditoria
              WHERE accion = 'LOGIN_FALLIDO' AND creado_en >= NOW() - INTERVAL ? SECOND",
            [$login, $ip, $segundos]
        );
        return ['login' => (int) ($fila['por_login'] ?? 0), 'ip' => (int) ($fila['por_ip'] ?? 0)];
    }

    /** @param array{desde?:string,hasta?:string,accion?:string,usuario?:string,resultado?:string,entidad?:string} $f */
    public function buscar(array $f, int $limite = 300): array
    {
        $where = [];
        $params = [];
        self::rango('a.creado_en', $f, $where, $params);
        foreach (['accion' => 'a.accion', 'resultado' => 'a.resultado', 'entidad' => 'a.entidad'] as $clave => $col) {
            if (!empty($f[$clave])) {
                $where[] = "$col = ?";
                $params[] = $f[$clave];
            }
        }
        if (!empty($f['usuario'])) {
            $where[] = 'a.id_usuario = ?';
            $params[] = (int) $f['usuario'];
        }
        return $this->all(
            'SELECT a.*, u.nombre AS usuario, u.login
               FROM auditoria a
          LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY a.id_auditoria DESC LIMIT ' . (int) $limite,
            $params
        );
    }

    public function acciones(): array
    {
        return array_column($this->all('SELECT DISTINCT accion FROM auditoria ORDER BY accion'), 'accion');
    }

    public function ultimoDe(string $accion, string $entidad, int $idRegistro): ?array
    {
        return $this->one(
            'SELECT * FROM auditoria WHERE accion = ? AND entidad = ? AND id_registro = ?
              ORDER BY id_auditoria DESC LIMIT 1',
            [$accion, $entidad, $idRegistro]
        );
    }
}
