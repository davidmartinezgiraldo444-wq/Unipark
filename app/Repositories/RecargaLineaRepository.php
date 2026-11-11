<?php
declare(strict_types=1);

namespace App\Repositories;

/**
 * Recargas en línea del saldo (operación iniciada por la persona) y bitácora de la pasarela.
 * El dinero acreditado vive en movimientos_saldo; aquí solo el estado de cada operación.
 */
final class RecargaLineaRepository extends Repository
{
    public const ABIERTAS = ['CREADA', 'PENDIENTE'];

    public function crear(array $r): int
    {
        return $this->insert(
            'INSERT INTO recargas_en_linea (referencia, id_estudiante, id_usuario, proveedor, entorno, monto, expira_en)
             VALUES (?, ?, ?, ?, ?, ?, NOW() + INTERVAL ? MINUTE)',
            [$r['referencia'], $r['id_estudiante'], $r['id_usuario'], $r['proveedor'], $r['entorno'], $r['monto'], $r['expira_min']]
        );
    }

    public function find(int $id, bool $bloquear = false): ?array
    {
        return $this->one('SELECT * FROM recargas_en_linea WHERE id_recarga = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$id]);
    }

    public function porReferencia(string $referencia, bool $bloquear = false): ?array
    {
        return $this->one('SELECT * FROM recargas_en_linea WHERE referencia = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$referencia]);
    }

    public function abiertasDePersona(int $idEstudiante): int
    {
        return (int) $this->one(
            "SELECT COUNT(*) AS n FROM recargas_en_linea WHERE id_estudiante = ? AND estado IN ('CREADA', 'PENDIENTE') AND expira_en > NOW()",
            [$idEstudiante]
        )['n'];
    }

    /**
     * Reembolso de una recarga aprobada: es el movimiento compensatorio REVERSO_RECARGA de su movimiento de saldo
     * (lo registra Administración, con motivo). No hay columna propia: se deduce del libro, que nunca se edita.
     */
    private const REEMBOLSO = '(SELECT rv.creado_en FROM movimientos_saldo rv WHERE rv.reverso_unico = r.id_movimiento) AS reembolsada_en,
             (SELECT rv.motivo FROM movimientos_saldo rv WHERE rv.reverso_unico = r.id_movimiento) AS motivo_reembolso';

    /** La recarga con los datos de su reembolso, si lo tiene (para la pantalla de detalle). */
    public function conReembolso(array $r): array
    {
        $fila = $this->one('SELECT ' . self::REEMBOLSO . ' FROM recargas_en_linea r WHERE r.id_recarga = ?', [(int) $r['id_recarga']]);
        return $r + ($fila ?? ['reembolsada_en' => null, 'motivo_reembolso' => null]);
    }

    public function dePersona(int $idEstudiante, int $limite = 30): array
    {
        return $this->all(
            'SELECT r.*, m.consecutivo, ' . self::REEMBOLSO . ' FROM recargas_en_linea r
          LEFT JOIN movimientos_saldo m ON m.id_movimiento = r.id_movimiento
              WHERE r.id_estudiante = ? ORDER BY r.id_recarga DESC LIMIT ' . max(1, $limite),
            [$idEstudiante]
        );
    }

    /** Cambio de estado SIN acreditar (pendiente, rechazada, anulada, error, cancelada, expirada). */
    public function cambiarEstado(int $id, string $estado, ?string $idTransaccion, ?string $medio, ?string $detalle): int
    {
        return $this->run(
            "UPDATE recargas_en_linea
                SET estado = ?, id_transaccion = COALESCE(?, id_transaccion), medio_externo = COALESCE(?, medio_externo),
                    detalle_estado = ?
              WHERE id_recarga = ? AND estado <> 'APROBADA'",
            [$estado, $idTransaccion, $medio, $detalle !== null ? mb_substr($detalle, 0, 255) : null, $id]
        );
    }

    /** Aprobación: única transición que acredita, siempre junto con su movimiento RECARGA. */
    public function aprobar(int $id, string $idTransaccion, ?string $medio, int $idMovimiento): int
    {
        return $this->run(
            "UPDATE recargas_en_linea
                SET estado = 'APROBADA', id_transaccion = ?, medio_externo = ?, id_movimiento = ?, aprobada_en = NOW(),
                    detalle_estado = NULL
              WHERE id_recarga = ? AND estado <> 'APROBADA' AND id_movimiento IS NULL",
            [$idTransaccion, $medio, $idMovimiento, $id]
        );
    }

    public function reportarRetorno(int $id, string $idTransaccion): int
    {
        return $this->run(
            "UPDATE recargas_en_linea SET id_transaccion_reportada = ? WHERE id_recarga = ? AND estado <> 'APROBADA'",
            [$idTransaccion, $id]
        );
    }

    public function marcarConsultada(int $id): void
    {
        $this->run("UPDATE recargas_en_linea SET consultada_en = NOW() WHERE id_recarga = ? AND estado <> 'APROBADA'", [$id]);
    }

    /** Recargas no aprobadas con una transacción conocida, para consultarlas al proveedor (conciliación). */
    public function paraConciliar(int $minutosEntreConsultas = 2, int $limite = 100): array
    {
        return $this->all(
            "SELECT * FROM recargas_en_linea
              WHERE estado IN ('CREADA', 'PENDIENTE', 'EXPIRADA', 'CANCELADA')
                AND (id_transaccion IS NOT NULL OR id_transaccion_reportada IS NOT NULL)
                AND creada_en >= NOW() - INTERVAL 7 DAY
                AND (consultada_en IS NULL OR consultada_en <= NOW() - INTERVAL ? MINUTE)
              ORDER BY creada_en LIMIT " . max(1, $limite),
            [$minutosEntreConsultas]
        );
    }

    /** Recargas creadas que nadie pagó a tiempo y de las que no se conoce ninguna transacción. */
    public function expirarVencidas(): int
    {
        return $this->run(
            "UPDATE recargas_en_linea SET estado = 'EXPIRADA', detalle_estado = 'No se recibió el pago antes del vencimiento.'
              WHERE estado = 'CREADA' AND id_transaccion IS NULL AND id_transaccion_reportada IS NULL
                AND expira_en <= NOW() - INTERVAL 5 MINUTE"
        );
    }

    // --- Bitácora de la pasarela (solo se inserta) --------------------------------------

    /** @return bool falso si esa misma notificación (huella) ya estaba registrada */
    public function registrarNotificacion(array $n): bool
    {
        try {
            $this->run(
                'INSERT INTO notificaciones_pasarela (proveedor, origen, huella, tipo_evento, id_transaccion, referencia,
                                                      estado_reportado, monto_centavos, firma_valida, resultado, mensaje, id_recarga)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $n['proveedor'], $n['origen'], $n['huella'] ?? null, $n['tipo_evento'] ?? null,
                    isset($n['id_transaccion']) ? mb_substr((string) $n['id_transaccion'], 0, 64) : null,
                    isset($n['referencia']) ? mb_substr((string) $n['referencia'], 0, 80) : null,
                    isset($n['estado_reportado']) ? mb_substr((string) $n['estado_reportado'], 0, 20) : null,
                    $n['monto_centavos'] ?? null, !empty($n['firma_valida']) ? 1 : 0, $n['resultado'],
                    isset($n['mensaje']) ? mb_substr((string) $n['mensaje'], 0, 255) : null, $n['id_recarga'] ?? null,
                ]
            );
            return true;
        } catch (\PDOException $e) {
            if (\App\Helpers\Database::isDuplicate($e)) {
                return false;
            }
            throw $e;
        }
    }

    public function notificacionProcesada(string $huella): bool
    {
        return $this->one('SELECT 1 AS x FROM notificaciones_pasarela WHERE huella = ?', [$huella]) !== null;
    }

    // --- Reportes ------------------------------------------------------------------------

    /** @param array{desde?:string,hasta?:string,texto?:string,estado_recarga?:string} $f */
    public function reporte(array $f, int $limite = 500): array
    {
        $where = [];
        $params = [];
        self::rango('r.creada_en', $f, $where, $params);
        if (!empty($f['estado_recarga'])) {
            $where[] = 'r.estado = ?';
            $params[] = $f['estado_recarga'];
        }
        if (!empty($f['texto'])) {
            $where[] = "(e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ? OR r.referencia LIKE ? OR r.id_transaccion LIKE ?)";
            array_push($params, self::like($f['texto']), self::like($f['texto']), self::like($f['texto']), self::like($f['texto']));
        }
        return $this->all(
            'SELECT r.*, e.documento, e.nombres, e.apellidos, m.consecutivo, ' . self::REEMBOLSO . '
               FROM recargas_en_linea r
               JOIN estudiantes e ON e.id_estudiante = r.id_estudiante
          LEFT JOIN movimientos_saldo m ON m.id_movimiento = r.id_movimiento'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY r.id_recarga DESC LIMIT ' . (int) $limite,
            $params
        );
    }

    /** Notificaciones que requieren revisión: firma inválida, montos distintos, recarga desconocida, errores. */
    public function problemas(array $f, int $limite = 200): array
    {
        $where = ["n.resultado NOT IN ('ACREDITADA', 'DUPLICADA', 'ESTADO_ACTUALIZADO', 'SIN_CAMBIO', 'IGNORADA')"];
        $params = [];
        self::rango('n.recibida_en', $f, $where, $params);
        return $this->all(
            'SELECT n.*, r.referencia AS referencia_recarga FROM notificaciones_pasarela n
          LEFT JOIN recargas_en_linea r ON r.id_recarga = n.id_recarga
              WHERE ' . implode(' AND ', $where) . ' ORDER BY n.id_notificacion DESC LIMIT ' . (int) $limite,
            $params
        );
    }
}
