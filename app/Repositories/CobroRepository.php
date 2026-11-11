<?php
declare(strict_types=1);

namespace App\Repositories;

/** Cobros diarios: uno vigente por persona y fecha. */
final class CobroRepository extends Repository
{
    private const DETALLE = "SELECT c.*, e.documento, e.nombres, e.apellidos, e.saldo,
                                    p.id_pago, p.consecutivo, p.medio_pago, p.registrado_por,
                                    v.entrada_en, v.estado AS estado_visita
                               FROM cobros_diarios c
                               JOIN estudiantes e ON e.id_estudiante = c.id_estudiante
                               JOIN visitas v     ON v.id_visita = c.id_visita
                          LEFT JOIN pagos p       ON p.pago_cobro_valido = c.id_cobro";

    public function find(int $id, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            return $this->one('SELECT * FROM cobros_diarios WHERE id_cobro = ? FOR UPDATE', [$id]);
        }
        return $this->one(self::DETALLE . ' WHERE c.id_cobro = ?', [$id]);
    }

    /** Cobro DIARIO vigente (no anulado) de la persona en la fecha. */
    public function delDia(int $idEstudiante, string $fecha, bool $bloquear = false): ?array
    {
        return $this->one(
            'SELECT * FROM cobros_diarios WHERE cobro_vigente = ?' . ($bloquear ? ' FOR UPDATE' : ''),
            [$idEstudiante . '@' . $fecha]
        );
    }

    /** Cobro del día (concepto DIARIO) por la tarifa de la visita, o DIFERENCIA por el valor indicado. */
    public function create(array $visita, string $fecha, string $concepto = 'DIARIO', ?string $valor = null): int
    {
        return $this->insert(
            'INSERT INTO cobros_diarios (id_estudiante, fecha, concepto, id_visita, tipo_vehiculo, categoria, id_tarifa, valor)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $visita['id_estudiante'], $fecha, $concepto, $visita['id_visita'], $visita['tipo_vehiculo'],
                $visita['categoria'], $visita['id_tarifa'], $valor ?? $visita['tarifa_aplicada'],
            ]
        );
    }

    /** Fechas que ya tienen un cobro de la visita (incluso anulado: un día anulado no se vuelve a cobrar). @return list<string> */
    public function fechasDeVisita(int $idVisita, bool $bloquear = false): array
    {
        $filas = $this->all('SELECT DISTINCT fecha FROM cobros_diarios WHERE id_visita = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$idVisita]);
        return array_column($filas, 'fecha');
    }

    /** Cobros pendientes de una visita (todos sus días). @return list<array> */
    public function pendientesDeVisita(int $idVisita, bool $bloquear = false): array
    {
        return $this->all("SELECT * FROM cobros_diarios WHERE id_visita = ? AND estado = 'PENDIENTE' ORDER BY fecha" . ($bloquear ? ' FOR UPDATE' : ''), [$idVisita]);
    }

    /** Suma de los cobros vigentes (no anulados) de la persona en la fecha: lo que ya se le cobró ese día. */
    public function totalDelDia(int $idEstudiante, string $fecha, bool $bloquear = false): ?string
    {
        $fila = $this->one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(valor), 0) AS total FROM cobros_diarios
              WHERE id_estudiante = ? AND fecha = ? AND estado <> 'ANULADO'" . ($bloquear ? ' FOR UPDATE' : ''),
            [$idEstudiante, $fecha]
        );
        return (int) $fila['n'] > 0 ? (string) $fila['total'] : null;
    }

    public function marcarPagado(int $id): int
    {
        return $this->run(
            "UPDATE cobros_diarios SET estado = 'PAGADO', pagado_en = NOW() WHERE id_cobro = ? AND estado = 'PENDIENTE'",
            [$id]
        );
    }

    /** Un pago anulado deja el cobro otra vez pendiente. */
    public function volverAPendiente(int $id): int
    {
        return $this->run(
            "UPDATE cobros_diarios SET estado = 'PENDIENTE', pagado_en = NULL WHERE id_cobro = ? AND estado = 'PAGADO'",
            [$id]
        );
    }

    public function anular(int $id, int $usuario, string $motivo): int
    {
        return $this->run(
            "UPDATE cobros_diarios SET estado = 'ANULADO', pagado_en = NULL, anulado_en = NOW(), anulado_por = ?, motivo_anulacion = ?
              WHERE id_cobro = ? AND estado = 'PENDIENTE'",
            [$usuario, $motivo, $id]
        );
    }

    public function marcarNotificado(int $id): void
    {
        $this->run('UPDATE cobros_diarios SET notificado_en = NOW() WHERE id_cobro = ? AND notificado_en IS NULL', [$id]);
    }

    /** Pendientes de pago para Caja, los más antiguos primero. */
    public function pendientes(string $texto = ''): array
    {
        $params = [];
        $filtro = '';
        if ($texto !== '') {
            $filtro = " AND (e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?)";
            $params = [self::like($texto), self::like($texto)];
        }
        return $this->all(self::DETALLE . " WHERE c.estado = 'PENDIENTE' $filtro ORDER BY c.fecha, c.generado_en", $params);
    }

    /** IDs de los pendientes de una persona, del más antiguo al más reciente, bloqueados. @return list<array> */
    public function pendientesDePersona(int $idEstudiante, bool $bloquear = false): array
    {
        return $this->all(
            "SELECT * FROM cobros_diarios WHERE id_estudiante = ? AND estado = 'PENDIENTE' ORDER BY fecha, id_cobro"
            . ($bloquear ? ' FOR UPDATE' : ''),
            [$idEstudiante]
        );
    }

    public function dePersona(int $idEstudiante, int $limite = 60): array
    {
        return $this->all(
            self::DETALLE . ' WHERE c.id_estudiante = ? ORDER BY c.fecha DESC, c.id_cobro DESC LIMIT ' . max(1, $limite),
            [$idEstudiante]
        );
    }

    /** @param array{desde?:string,hasta?:string,estado?:string,categoria?:string,tipo?:string} $f */
    public function reporte(array $f, int $limite = 500): array
    {
        $where = [];
        $params = [];
        if (!empty($f['desde'])) {
            $where[] = 'c.fecha >= ?';
            $params[] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where[] = 'c.fecha <= ?';
            $params[] = $f['hasta'];
        }
        if (!empty($f['texto'])) {
            $where[] = "(e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?)";
            $params[] = self::like($f['texto']);
            $params[] = self::like($f['texto']);
        }
        foreach (['estado_cobro' => 'c.estado', 'categoria' => 'c.categoria', 'tipo' => 'c.tipo_vehiculo', 'concepto' => 'c.concepto'] as $clave => $col) {
            if (!empty($f[$clave])) {
                $where[] = "$col = ?";
                $params[] = $f[$clave];
            }
        }
        return $this->all(
            self::DETALLE . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY c.fecha DESC, c.id_cobro DESC LIMIT ' . (int) $limite,
            $params
        );
    }
}
