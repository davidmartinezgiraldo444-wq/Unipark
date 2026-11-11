<?php
declare(strict_types=1);

namespace App\Repositories;

final class VisitaRepository extends Repository
{
    // p: pago de una visita LEGADO · c/pc: cobro diario que cubre la visita y su pago válido.
    private const DETALLE = "SELECT v.*,
                                    e.documento, e.nombres, e.apellidos, e.saldo,
                                    ue.nombre AS entrada_por_nombre,
                                    us.nombre AS salida_por_nombre,
                                    COALESCE(p.id_pago, pc.id_pago) AS id_pago,
                                    COALESCE(p.consecutivo, pc.consecutivo) AS consecutivo,
                                    COALESCE(p.pagado_en, pc.pagado_en) AS pagado_en,
                                    COALESCE(p.medio_pago, pc.medio_pago) AS medio_pago,
                                    c.estado AS cobro_estado, c.valor AS cobro_valor, c.fecha AS cobro_fecha,
                                    IF(p.id_pago IS NULL AND pc.id_pago IS NULL, 0, 1) AS pagada
                               FROM visitas v
                               JOIN estudiantes e ON e.id_estudiante = v.id_estudiante
                               JOIN usuarios ue   ON ue.id_usuario = v.entrada_por
                          LEFT JOIN usuarios us   ON us.id_usuario = v.salida_por
                          LEFT JOIN pagos p       ON p.pago_valido = v.id_visita
                          LEFT JOIN cobros_diarios c ON c.id_cobro = v.id_cobro
                          LEFT JOIN pagos pc      ON pc.pago_cobro_valido = v.id_cobro";

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

    /**
     * Incidencias de cobro: visitas en curso cuya tolerancia ya venció y el proceso automático aún no
     * liquidó (cron detenido, sin pantallas abiertas). Las resuelven Caja o Portería con «Procesar cobro».
     */
    public function incidenciasDeCobro(): array
    {
        return $this->all(
            self::DETALLE . " WHERE v.estado = 'EN_CURSO' AND v.liquidacion = 'EN_TOLERANCIA' AND v.tolerancia_hasta <= NOW()
                               ORDER BY v.tolerancia_hasta"
        );
    }

    /** Todas las visitas abiertas de una persona (un visitante puede tener varias). */
    public function abiertasDePersona(int $idEstudiante): array
    {
        return $this->all(
            self::DETALLE . " WHERE v.id_estudiante = ? AND v.estado = 'EN_CURSO' ORDER BY v.entrada_en",
            [$idEstudiante]
        );
    }

    /**
     * Nueva visita. La tolerancia corre desde la hora de entrada del servidor.
     * @param array{tipo:string,origen:string,categoria:string,id_tarifa:?int,tarifa:?string,liquidacion:string,id_cobro:?int} $d
     */
    public function create(int $idEstudiante, array $d, int $usuario): int
    {
        return $this->insert(
            'INSERT INTO visitas (id_estudiante, tipo_vehiculo, tipo_origen, categoria, id_tarifa, tarifa_aplicada,
                                  entrada_en, entrada_por, tolerancia_hasta, liquidacion, id_cobro, id_evento_entrada)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW() + INTERVAL ? MINUTE, ?, ?, ?)',
            [
                $idEstudiante, $d['tipo'], $d['origen'], $d['categoria'], $d['id_tarifa'], $d['tarifa'],
                $usuario, (int) config('tolerancia_minutos'), $d['liquidacion'], $d['id_cobro'], $d['id_evento'] ?? null,
            ]
        );
    }

    /** Cierra la visita. $liquidacion deja constancia de que salió dentro de la tolerancia. */
    public function cerrar(int $id, int $usuario, ?string $liquidacion = null, ?int $idEvento = null): int
    {
        return $this->run(
            "UPDATE visitas SET salida_en = NOW(), salida_por = ?, estado = 'CERRADA',
                    liquidacion = COALESCE(?, liquidacion), id_evento_salida = ?
              WHERE id_visita = ? AND estado = 'EN_CURSO'",
            [$usuario, $liquidacion, $idEvento, $id]
        );
    }

    /** Resultado de la liquidación diaria de una visita en curso cuya tolerancia venció. */
    public function liquidar(int $id, string $liquidacion, ?int $idCobro): int
    {
        return $this->run(
            "UPDATE visitas SET liquidacion = ?, id_cobro = ?
              WHERE id_visita = ? AND estado = 'EN_CURSO' AND liquidacion = 'EN_TOLERANCIA'",
            [$liquidacion, $idCobro, $id]
        );
    }

    /** Visitas en curso con la tolerancia vencida y sin procesar. @return list<int> */
    public function vencidasSinLiquidar(int $limite = 200): array
    {
        $filas = $this->all(
            "SELECT id_visita FROM visitas
              WHERE estado = 'EN_CURSO' AND liquidacion = 'EN_TOLERANCIA' AND tolerancia_hasta <= NOW()
              ORDER BY tolerancia_hasta
              LIMIT " . max(1, $limite)
        );
        return array_map('intval', array_column($filas, 'id_visita'));
    }

    /** Visitas abiertas desde un día anterior que ya tienen cobro: sus días siguientes también se cobran. @return list<int> */
    public function abiertasDeDiasAnteriores(int $limite = 200): array
    {
        $filas = $this->all(
            "SELECT id_visita FROM visitas
              WHERE estado = 'EN_CURSO' AND liquidacion IN ('COBRADA', 'DIFERENCIA', 'CUBIERTA') AND entrada_en < CURDATE()
              ORDER BY entrada_en
              LIMIT " . max(1, $limite)
        );
        return array_map('intval', array_column($filas, 'id_visita'));
    }

    public function anular(int $id, int $usuario, string $motivo): int
    {
        return $this->run(
            "UPDATE visitas SET estado = 'ANULADA', anulada_en = NOW(), anulada_por = ?, motivo_anulacion = ?
              WHERE id_visita = ? AND estado = 'EN_CURSO'",
            [$usuario, $motivo, $id]
        );
    }

    /** Visitas LEGADO abiertas sin pago (RF-09). Las del esquema diario se cobran en cobros_diarios. */
    public function pendientesDePago(string $texto = ''): array
    {
        $params = [];
        $filtro = '';
        if ($texto !== '') {
            $filtro = " AND (e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?)";
            $params = [self::like($texto), self::like($texto)];
        }
        return $this->all(
            self::DETALLE . " WHERE v.estado = 'EN_CURSO' AND v.liquidacion = 'LEGADO' AND p.id_pago IS NULL $filtro ORDER BY v.entrada_en",
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

    /** @return array{CARRO:int,MOTO:int,BICICLETA:int,PATINETA:int,visitantes:int,tolerancia:int,pendientes:int} */
    public function ocupacion(): array
    {
        $fila = $this->one(
            "SELECT SUM(v.tipo_vehiculo = 'CARRO') AS carros,
                    SUM(v.tipo_vehiculo = 'MOTO') AS motos,
                    SUM(v.tipo_vehiculo = 'BICICLETA') AS bicicletas,
                    SUM(v.tipo_vehiculo = 'PATINETA') AS patinetas,
                    SUM(v.categoria = 'VISITANTE') AS visitantes,
                    SUM(v.liquidacion = 'EN_TOLERANCIA') AS tolerancia,
                    SUM((v.liquidacion = 'LEGADO' AND p.id_pago IS NULL) OR c.estado = 'PENDIENTE') AS pendientes
               FROM visitas v
          LEFT JOIN pagos p ON p.pago_valido = v.id_visita
          LEFT JOIN cobros_diarios c ON c.id_cobro = v.id_cobro
              WHERE v.estado = 'EN_CURSO'"
        );
        return [
            'CARRO'      => (int) ($fila['carros'] ?? 0),
            'MOTO'       => (int) ($fila['motos'] ?? 0),
            'BICICLETA'  => (int) ($fila['bicicletas'] ?? 0),
            'PATINETA'   => (int) ($fila['patinetas'] ?? 0),
            'visitantes' => (int) ($fila['visitantes'] ?? 0),
            'tolerancia' => (int) ($fila['tolerancia'] ?? 0),
            'pendientes' => (int) ($fila['pendientes'] ?? 0),
        ];
    }

    /** Historial de una persona con todos sus pagos, válidos y anulados (RF-15). */
    public function historialEstudiante(int $idEstudiante, int $limite = 500): array
    {
        $visitas = $this->all(
            self::DETALLE . ' WHERE v.id_estudiante = ? ORDER BY v.entrada_en DESC LIMIT ' . max(1, $limite),
            [$idEstudiante]
        );
        if (!$visitas) {
            return [];
        }
        $ids = array_column($visitas, 'id_visita');
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $pagos = $this->all(
            "SELECT p.*, u.nombre AS cajero FROM pagos p LEFT JOIN usuarios u ON u.id_usuario = p.registrado_por
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
            $where[] = '(p.id_pago IS NOT NULL OR pc.id_pago IS NOT NULL)';
        } elseif (($f['pago'] ?? '') === 'no') {
            $where[] = '(p.id_pago IS NULL AND pc.id_pago IS NULL)';
        }
        $sql = self::DETALLE . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY v.entrada_en DESC LIMIT ' . (int) $limite;
        return $this->all($sql, $params);
    }
}
