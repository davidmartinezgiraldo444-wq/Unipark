<?php
declare(strict_types=1);

namespace App\Repositories;

final class VisitaRepository extends Repository
{
    private const DETALLE = "SELECT v.*,
                                    e.documento, e.nombres, e.apellidos,
                                    ue.nombre AS entrada_por_nombre,
                                    us.nombre AS salida_por_nombre,
                                    p.id_pago, p.consecutivo, p.pagado_en, p.medio_pago,
                                    IF(p.id_pago IS NULL, 0, 1) AS pagada
                               FROM visitas v
                               JOIN estudiantes e ON e.id_estudiante = v.id_estudiante
                               JOIN usuarios ue   ON ue.id_usuario = v.entrada_por
                          LEFT JOIN usuarios us   ON us.id_usuario = v.salida_por
                          LEFT JOIN pagos p       ON p.pago_valido = v.id_visita";

    public function find(int $id, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            return $this->one('SELECT * FROM visitas WHERE id_visita = ? FOR UPDATE', [$id]);
        }
        return $this->one(self::DETALLE . ' WHERE v.id_visita = ?', [$id]);
    }

    public function abiertaDeEstudiante(int $idEstudiante, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            return $this->one('SELECT * FROM visitas WHERE visita_abierta = ? FOR UPDATE', [$idEstudiante]);
        }
        return $this->one(self::DETALLE . ' WHERE v.visita_abierta = ?', [$idEstudiante]);
    }

    public function create(int $idEstudiante, string $tipo, string $categoria, int $idTarifa, string $tarifa, int $usuario): int
    {
        return $this->insert(
            'INSERT INTO visitas (id_estudiante, tipo_vehiculo, categoria, id_tarifa, tarifa_aplicada, entrada_por)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$idEstudiante, $tipo, $categoria, $idTarifa, $tarifa, $usuario]
        );
    }

    public function cerrar(int $id, int $usuario): int
    {
        return $this->run(
            "UPDATE visitas SET salida_en = NOW(), salida_por = ?, estado = 'CERRADA'
              WHERE id_visita = ? AND estado = 'EN_CURSO'",
            [$usuario, $id]
        );
    }

    public function anular(int $id, int $usuario, string $motivo): int
    {
        return $this->run(
            "UPDATE visitas SET estado = 'ANULADA', anulada_en = NOW(), anulada_por = ?, motivo_anulacion = ?
              WHERE id_visita = ? AND estado = 'EN_CURSO'",
            [$usuario, $motivo, $id]
        );
    }

    /** Visitas abiertas sin pago confirmado (RF-09). */
    public function pendientesDePago(string $texto = ''): array
    {
        $params = [];
        $filtro = '';
        if ($texto !== '') {
            $filtro = " AND (e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?)";
            $params = [self::like($texto), self::like($texto)];
        }
        return $this->all(
            self::DETALLE . " WHERE v.estado = 'EN_CURSO' AND p.id_pago IS NULL $filtro ORDER BY v.entrada_en",
            $params
        );
    }

    /** Visitas abiertas en tiempo real (sección 12). */
    public function abiertas(?string $tipo = null, ?string $categoria = null): array
    {
        $params = [];
        $filtro = '';
        if ($tipo !== null && $tipo !== '') {
            $filtro .= ' AND v.tipo_vehiculo = ?';
            $params[] = $tipo;
        }
        if ($categoria !== null && $categoria !== '') {
            $filtro .= ' AND v.categoria = ?';
            $params[] = $categoria;
        }
        return $this->all(self::DETALLE . " WHERE v.estado = 'EN_CURSO' $filtro ORDER BY v.entrada_en", $params);
    }

    /** @return array{CARRO:int,MOTO:int,pendientes:int} */
    public function ocupacion(): array
    {
        $fila = $this->one(
            "SELECT SUM(v.tipo_vehiculo = 'CARRO') AS carros,
                    SUM(v.tipo_vehiculo = 'MOTO') AS motos,
                    SUM(p.id_pago IS NULL) AS pendientes
               FROM visitas v
          LEFT JOIN pagos p ON p.pago_valido = v.id_visita
              WHERE v.estado = 'EN_CURSO'"
        );
        return [
            'CARRO'      => (int) ($fila['carros'] ?? 0),
            'MOTO'       => (int) ($fila['motos'] ?? 0),
            'pendientes' => (int) ($fila['pendientes'] ?? 0),
        ];
    }

    /** Historial de un estudiante con todos sus pagos, válidos y anulados (RF-15). */
    public function historialEstudiante(int $idEstudiante): array
    {
        $visitas = $this->all(
            self::DETALLE . ' WHERE v.id_estudiante = ? ORDER BY v.entrada_en DESC',
            [$idEstudiante]
        );
        if (!$visitas) {
            return [];
        }
        $ids = array_column($visitas, 'id_visita');
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $pagos = $this->all(
            "SELECT p.*, u.nombre AS cajero FROM pagos p JOIN usuarios u ON u.id_usuario = p.registrado_por
              WHERE p.id_visita IN ($marcas) ORDER BY p.pagado_en",
            $ids
        );
        foreach ($visitas as &$v) {
            $v['pagos'] = array_values(array_filter($pagos, static fn ($p) => $p['id_visita'] === $v['id_visita']));
        }
        unset($v);
        return $visitas;
    }

    /**
     * Reporte de visitas con filtros (RF-16).
     * @param array{desde?:string,hasta?:string,tipo?:string,estado?:string,usuario?:string,pago?:string} $f
     */
    public function reporte(array $f, int $limite = 500): array
    {
        $where = [];
        $params = [];
        self::rango('v.entrada_en', $f, $where, $params);
        if (!empty($f['tipo'])) {
            $where[] = 'v.tipo_vehiculo = ?';
            $params[] = $f['tipo'];
        }
        if (!empty($f['categoria'])) {
            $where[] = 'v.categoria = ?';
            $params[] = $f['categoria'];
        }
        if (!empty($f['estado'])) {
            $where[] = 'v.estado = ?';
            $params[] = $f['estado'];
        }
        if (!empty($f['usuario'])) {
            $where[] = '(v.entrada_por = ? OR v.salida_por = ?)';
            $params[] = (int) $f['usuario'];
            $params[] = (int) $f['usuario'];
        }
        if (($f['pago'] ?? '') === 'si') {
            $where[] = 'p.id_pago IS NOT NULL';
        } elseif (($f['pago'] ?? '') === 'no') {
            $where[] = 'p.id_pago IS NULL';
        }
        $sql = self::DETALLE . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY v.entrada_en DESC LIMIT ' . (int) $limite;
        return $this->all($sql, $params);
    }
}
