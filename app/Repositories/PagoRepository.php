<?php
declare(strict_types=1);

namespace App\Repositories;

final class PagoRepository extends Repository
{
    // Un pago cubre una visita LEGADO (p.id_visita) o un cobro diario (p.id_cobro → c.id_visita).
    // El débito automático del saldo no tiene cajero: se muestra como «Débito automático».
    private const DETALLE = "SELECT p.*, COALESCE(u.nombre, 'Débito automático') AS cajero, ua.nombre AS anulado_por_nombre,
                                    v.tipo_vehiculo, v.categoria, v.entrada_en,
                                    COALESCE(c.valor, v.tarifa_aplicada) AS tarifa_aplicada, v.estado AS estado_visita,
                                    c.fecha AS cobro_fecha, c.estado AS cobro_estado,
                                    e.documento, e.nombres, e.apellidos
                               FROM pagos p
                          LEFT JOIN usuarios u     ON u.id_usuario = p.registrado_por
                          LEFT JOIN usuarios ua    ON ua.id_usuario = p.anulado_por
                          LEFT JOIN cobros_diarios c ON c.id_cobro = p.id_cobro
                               JOIN visitas v      ON v.id_visita = COALESCE(p.id_visita, c.id_visita)
                               JOIN estudiantes e  ON e.id_estudiante = v.id_estudiante";

    public function find(int $id, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            return $this->one('SELECT * FROM pagos WHERE id_pago = ? FOR UPDATE', [$id]);
        }
        return $this->one(self::DETALLE . ' WHERE p.id_pago = ?', [$id]);
    }

    /** Pago válido (PAGADO) de una visita, si existe. */
    public function validoDeVisita(int $idVisita, bool $bloquear = false): ?array
    {
        return $this->one(
            'SELECT * FROM pagos WHERE pago_valido = ?' . ($bloquear ? ' FOR UPDATE' : ''),
            [$idVisita]
        );
    }

    /**
     * Siguiente número de comprobante. Bloquea la fila del contador hasta el fin de la transacción,
     * así dos cajeros nunca obtienen el mismo número y un pago revertido no deja huecos.
     */
    public function siguienteConsecutivo(): string
    {
        $this->run("UPDATE consecutivos SET ultimo = LAST_INSERT_ID(ultimo + 1) WHERE nombre = 'RECIBO'");
        $numero = (int) $this->one('SELECT LAST_INSERT_ID() AS n')['n'];
        return 'R' . str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }

    public function create(int $idVisita, string $monto, string $medio, int $usuario, string $consecutivo, ?string $referencia): int
    {
        return $this->insert(
            'INSERT INTO pagos (id_visita, monto, registrado_por, medio_pago, consecutivo, referencia)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$idVisita, $monto, $usuario, $medio, $consecutivo, $referencia]
        );
    }

    /** Pago de un cobro diario. $usuario null = débito automático del saldo (medio SALDO). */
    public function createDeCobro(int $idCobro, string $monto, string $medio, ?int $usuario, string $consecutivo, ?string $referencia): int
    {
        return $this->insert(
            'INSERT INTO pagos (id_cobro, monto, registrado_por, medio_pago, consecutivo, referencia)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$idCobro, $monto, $usuario, $medio, $consecutivo, $referencia]
        );
    }

    /** Pago válido (PAGADO) de un cobro diario, si existe. */
    public function validoDeCobro(int $idCobro, bool $bloquear = false): ?array
    {
        return $this->one(
            'SELECT * FROM pagos WHERE pago_cobro_valido = ?' . ($bloquear ? ' FOR UPDATE' : ''),
            [$idCobro]
        );
    }

    public function anular(int $id, int $usuario, string $motivo): int
    {
        return $this->run(
            "UPDATE pagos SET estado = 'ANULADO', anulado_en = NOW(), anulado_por = ?, motivo_anulacion = ?
              WHERE id_pago = ? AND estado = 'PAGADO'",
            [$usuario, $motivo, $id]
        );
    }

    /** Transacciones de un cajero en un periodo (RF-19). */
    public function delCajero(int $usuario, string $desde, string $hasta): array
    {
        return $this->all(
            self::DETALLE . ' WHERE p.registrado_por = ? AND p.pagado_en >= ? AND p.pagado_en <= ? ORDER BY p.pagado_en',
            [$usuario, $desde, $hasta]
        );
    }

    /** Total por medio de pago, solo pagos válidos. */
    public function resumenCajero(int $usuario, string $desde, string $hasta): array
    {
        return $this->all(
            "SELECT medio_pago, COUNT(*) AS cantidad, SUM(monto) AS total
               FROM pagos
              WHERE registrado_por = ? AND estado = 'PAGADO' AND pagado_en >= ? AND pagado_en <= ?
              GROUP BY medio_pago ORDER BY medio_pago",
            [$usuario, $desde, $hasta]
        );
    }

    /** @param array{desde?:string,hasta?:string,medio?:string,usuario?:string,estado?:string,tipo?:string} $f */
    public function reporte(array $f, int $limite = 500): array
    {
        [$where, $params] = $this->filtros($f);
        $sql = self::DETALLE . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY p.pagado_en DESC LIMIT ' . (int) $limite;
        return $this->all($sql, $params);
    }

    /** Recaudo por día y cajero, solo pagos válidos. */
    public function recaudo(array $f): array
    {
        $f['estado'] = 'PAGADO';
        [$where, $params] = $this->filtros($f);
        return $this->all(
            'SELECT DATE(p.pagado_en) AS dia, COALESCE(u.nombre, \'Débito automático\') AS cajero, p.medio_pago,
                    COUNT(*) AS cantidad, SUM(p.monto) AS total
               FROM pagos p
          LEFT JOIN usuarios u    ON u.id_usuario = p.registrado_por
          LEFT JOIN cobros_diarios c ON c.id_cobro = p.id_cobro
               JOIN visitas v     ON v.id_visita = COALESCE(p.id_visita, c.id_visita)
               JOIN estudiantes e ON e.id_estudiante = v.id_estudiante
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY DATE(p.pagado_en), u.id_usuario, p.medio_pago
              ORDER BY dia DESC, cajero, p.medio_pago',
            $params
        );
    }

    public function totalDelDia(): array
    {
        return $this->one(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(monto), 0) AS total
               FROM pagos WHERE estado = 'PAGADO' AND pagado_en >= CURDATE()"
        );
    }

    private function filtros(array $f): array
    {
        $where = [];
        $params = [];
        self::rango('p.pagado_en', $f, $where, $params);
        foreach (['medio' => 'p.medio_pago', 'estado' => 'p.estado', 'tipo' => 'v.tipo_vehiculo', 'categoria' => 'v.categoria'] as $clave => $col) {
            if (!empty($f[$clave])) {
                $where[] = "$col = ?";
                $params[] = $f[$clave];
            }
        }
        if (!empty($f['usuario'])) {
            $where[] = 'p.registrado_por = ?';
            $params[] = (int) $f['usuario'];
        }
        return [$where, $params];
    }
}
