<?php
declare(strict_types=1);

namespace App\Repositories;

/** Libro de movimientos de saldo (recargas, débitos y reintegros). Solo se inserta. */
final class SaldoRepository extends Repository
{
    public function registrar(array $m): int
    {
        return $this->insert(
            'INSERT INTO movimientos_saldo (id_estudiante, tipo, monto, saldo_anterior, saldo_nuevo,
                                            id_cobro, id_pago, id_movimiento_origen, medio_pago, referencia, motivo,
                                            consecutivo, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $m['id_estudiante'], $m['tipo'], $m['monto'], $m['saldo_anterior'], $m['saldo_nuevo'],
                $m['id_cobro'] ?? null, $m['id_pago'] ?? null, $m['id_movimiento_origen'] ?? null, $m['medio_pago'] ?? null,
                $m['referencia'] ?? null, $m['motivo'] ?? null, $m['consecutivo'] ?? null, $m['registrado_por'] ?? null,
            ]
        );
    }

    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT m.*, e.documento, e.nombres, e.apellidos, u.nombre AS cajero
               FROM movimientos_saldo m
               JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
          LEFT JOIN usuarios u ON u.id_usuario = m.registrado_por
              WHERE m.id_movimiento = ?',
            [$id]
        );
    }

    public function dePersona(int $idEstudiante, int $limite = 60): array
    {
        return $this->all(
            'SELECT m.*, u.nombre AS cajero
               FROM movimientos_saldo m
          LEFT JOIN usuarios u ON u.id_usuario = m.registrado_por
              WHERE m.id_estudiante = ?
              ORDER BY m.id_movimiento DESC
              LIMIT ' . max(1, $limite),
            [$idEstudiante]
        );
    }

    /** Recargas recibidas por un cajero en el periodo, por medio de pago (cierre de caja). */
    public function recargasCajero(int $usuario, string $desde, string $hasta): array
    {
        return $this->all(
            "SELECT medio_pago, COUNT(*) AS cantidad, SUM(monto) AS total
               FROM movimientos_saldo
              WHERE tipo = 'RECARGA' AND registrado_por = ? AND creado_en >= ? AND creado_en <= ?
              GROUP BY medio_pago ORDER BY medio_pago",
            [$usuario, $desde, $hasta]
        );
    }

    public function detalleRecargasCajero(int $usuario, string $desde, string $hasta): array
    {
        return $this->all(
            "SELECT m.*, e.documento, e.nombres, e.apellidos
               FROM movimientos_saldo m
               JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
              WHERE m.tipo = 'RECARGA' AND m.registrado_por = ? AND m.creado_en >= ? AND m.creado_en <= ?
              ORDER BY m.creado_en",
            [$usuario, $desde, $hasta]
        );
    }

    /** Número de comprobante de recarga, con la misma técnica sin huecos que los recibos de pago. */
    public function siguienteConsecutivo(): string
    {
        $this->run("UPDATE consecutivos SET ultimo = LAST_INSERT_ID(ultimo + 1) WHERE nombre = 'RECARGA'");
        $numero = (int) $this->one('SELECT LAST_INSERT_ID() AS n')['n'];
        return 'C' . str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Historial de movimientos de saldo con filtros (recargas, débitos, reintegros y reversos).
     * @param array{desde?:string,hasta?:string,tipo_mov?:string,texto?:string,usuario?:string} $f
     */
    public function reporte(array $f, int $limite = 500): array
    {
        $where = [];
        $params = [];
        self::rango('m.creado_en', $f, $where, $params);
        if (!empty($f['tipo_mov'])) {
            $where[] = 'm.tipo = ?';
            $params[] = $f['tipo_mov'];
        }
        if (!empty($f['texto'])) {
            $where[] = "(e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?)";
            $params[] = self::like($f['texto']);
            $params[] = self::like($f['texto']);
        }
        if (!empty($f['id_estudiante'])) {
            $where[] = 'm.id_estudiante = ?';
            $params[] = (int) $f['id_estudiante'];
        }
        if (!empty($f['usuario'])) {
            $where[] = 'm.registrado_por = ?';
            $params[] = (int) $f['usuario'];
        }
        return $this->all(
            "SELECT m.*, e.documento, e.nombres, e.apellidos, COALESCE(u.nombre, 'Automático') AS cajero,
                    (SELECT r.id_movimiento FROM movimientos_saldo r WHERE r.reverso_unico = m.id_movimiento) AS id_reverso
               FROM movimientos_saldo m
               JOIN estudiantes e ON e.id_estudiante = m.id_estudiante
          LEFT JOIN usuarios u ON u.id_usuario = m.registrado_por"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY m.id_movimiento DESC LIMIT ' . (int) $limite,
            $params
        );
    }

    /** Recarga bloqueada para reversarla (con su reverso, si ya existe). */
    public function recargaParaReverso(int $id): ?array
    {
        return $this->one(
            "SELECT m.*, (SELECT r.id_movimiento FROM movimientos_saldo r WHERE r.reverso_unico = m.id_movimiento) AS id_reverso
               FROM movimientos_saldo m WHERE m.id_movimiento = ? AND m.tipo = 'RECARGA' FOR UPDATE",
            [$id]
        );
    }
}
