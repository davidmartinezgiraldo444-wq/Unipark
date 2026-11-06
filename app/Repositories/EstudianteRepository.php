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
            'INSERT INTO estudiantes (documento, nombres, apellidos, correo, telefono, categoria) VALUES (?, ?, ?, ?, ?, ?)',
            [$d['documento'], $d['nombres'], $d['apellidos'], $d['correo'] ?: null, $d['telefono'] ?: null, $d['categoria']]
        );
    }

    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE estudiantes SET documento = ?, nombres = ?, apellidos = ?, correo = ?, telefono = ?, categoria = ?
              WHERE id_estudiante = ?',
            [$d['documento'], $d['nombres'], $d['apellidos'], $d['correo'] ?: null, $d['telefono'] ?: null, $d['categoria'], $id]
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
}
