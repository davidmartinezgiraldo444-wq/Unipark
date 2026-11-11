<?php
declare(strict_types=1);

namespace App\Repositories;

final class EstudianteRepository extends Repository
{
    public function find(int $id, bool $bloquear = false): ?array
    {
        return $this->one(
            'SELECT * FROM estudiantes WHERE id_estudiante = ?' . ($bloquear ? ' FOR UPDATE' : ''),
            [$id]
        );
    }

    public function findByDocumento(string $documento): ?array
    {
        return $this->one('SELECT * FROM estudiantes WHERE documento = ?', [$documento]);
    }

    /** Persona por el código leído del carnet: primero el código propio del carnet, luego el documento. */
    public function findByCodigo(string $codigo): ?array
    {
        return $this->one('SELECT * FROM estudiantes WHERE codigo_carnet = ?', [$codigo])
            ?? $this->findByDocumento($codigo);
    }

    /** Registra un documento no inscrito como visitante. Solo se guarda el documento (y el nombre si se dio). */
    public function crearVisitante(string $documento, string $nombre): int
    {
        return $this->insert(
            "INSERT INTO estudiantes (documento, nombres, apellidos, categoria) VALUES (?, ?, '', 'VISITANTE')",
            [$documento, $nombre !== '' ? mb_substr($nombre, 0, 80) : 'Visitante']
        );
    }

    public function setSaldo(int $id, string $saldo): void
    {
        $this->run('UPDATE estudiantes SET saldo = ? WHERE id_estudiante = ?', [$saldo, $id]);
    }

    /** Personas con carnet para la hoja de carnets de demostración. */
    public function conCarnet(): array
    {
        return $this->all(
            "SELECT * FROM estudiantes WHERE activo = 1 AND categoria <> 'VISITANTE' ORDER BY categoria, apellidos, nombres"
        );
    }

    /** Búsqueda por documento o nombre, paginada. */
    public function search(string $texto, int $limite, int $desplazamiento): array
    {
        [$where, $params] = $this->filtro($texto);
        $limite = max(1, $limite);
        $desplazamiento = max(0, $desplazamiento);
        return $this->all(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM visitas v WHERE v.id_estudiante = e.id_estudiante) AS visitas,
                    (SELECT v.estado FROM visitas v WHERE v.visita_abierta = e.id_estudiante) AS visita_abierta
               FROM estudiantes e
              $where
              ORDER BY e.apellidos, e.nombres
              LIMIT $limite OFFSET $desplazamiento",
            $params
        );
    }

    public function count(string $texto): int
    {
        [$where, $params] = $this->filtro($texto);
        return (int) $this->one("SELECT COUNT(*) AS n FROM estudiantes e $where", $params)['n'];
    }

    public function create(array $d): int
    {
        return $this->insert(
            'INSERT INTO estudiantes (documento, codigo_carnet, nombres, apellidos, correo, telefono, categoria) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['documento'], ($d['codigo_carnet'] ?? '') ?: null, $d['nombres'], $d['apellidos'], $d['correo'] ?: null, $d['telefono'] ?: null, $d['categoria']]
        );
    }

    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE estudiantes SET documento = ?, codigo_carnet = ?, nombres = ?, apellidos = ?, correo = ?, telefono = ?, categoria = ?
              WHERE id_estudiante = ?',
            [$d['documento'], ($d['codigo_carnet'] ?? '') ?: null, $d['nombres'], $d['apellidos'], $d['correo'] ?: null, $d['telefono'] ?: null, $d['categoria'], $id]
        );
    }

    public function setActivo(int $id, bool $activo): void
    {
        $this->run('UPDATE estudiantes SET activo = ? WHERE id_estudiante = ?', [$activo ? 1 : 0, $id]);
    }

    private function filtro(string $texto): array
    {
        if ($texto === '') {
            return ['', []];
        }
        $like = self::like($texto);
        return [
            "WHERE e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?",
            [$like, $like],
        ];
    }

    /** Saldo por persona con cobros pendientes y último movimiento (reporte de saldos). */
    public function saldos(string $texto = '', string $categoria = '', int $limite = 500): array
    {
        $where = ["e.categoria <> 'VISITANTE'"];
        $params = [];
        if ($texto !== '') {
            $where[] = "(e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?)";
            $params[] = self::like($texto);
            $params[] = self::like($texto);
        }
        if ($categoria !== '') {
            $where[] = 'e.categoria = ?';
            $params[] = $categoria;
        }
        return $this->all(
            "SELECT e.id_estudiante, e.documento, e.nombres, e.apellidos, e.categoria, e.saldo, e.activo,
                    (SELECT COALESCE(SUM(c.valor), 0) FROM cobros_diarios c WHERE c.id_estudiante = e.id_estudiante AND c.estado = 'PENDIENTE') AS pendiente,
                    (SELECT MAX(m.creado_en) FROM movimientos_saldo m WHERE m.id_estudiante = e.id_estudiante) AS ultimo_movimiento
               FROM estudiantes e
              WHERE " . implode(' AND ', $where) . '
              ORDER BY e.saldo DESC, e.apellidos
              LIMIT ' . max(1, $limite),
            $params
        );
    }
}
