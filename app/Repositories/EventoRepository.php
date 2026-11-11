<?php
declare(strict_types=1);

namespace App\Repositories;

/** Eventos de acceso: cada pasada por la portería (identidad → vehículo → confirmación). */
final class EventoRepository extends Repository
{
    public function find(int $id, bool $bloquear = false): ?array
    {
        return $this->one('SELECT * FROM eventos_acceso WHERE id_evento = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$id]);
    }

    public function abiertoDePersona(int $idEstudiante, bool $bloquear = false): ?array
    {
        return $this->one('SELECT * FROM eventos_acceso WHERE abierto_persona = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$idEstudiante]);
    }

    public function abiertoDeDocumento(string $documento, bool $bloquear = false): ?array
    {
        return $this->one('SELECT * FROM eventos_acceso WHERE abierto_documento = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$documento]);
    }

    public function enCapturaDePuesto(int $idPuesto, bool $bloquear = false): ?array
    {
        return $this->one('SELECT * FROM eventos_acceso WHERE captura_puesto = ?' . ($bloquear ? ' FOR UPDATE' : ''), [$idPuesto]);
    }

    public function crear(array $d): int
    {
        return $this->insert(
            'INSERT INTO eventos_acceso (tipo, id_puesto, id_estudiante, documento_leido, nombre_leido, id_visita,
                                         estado, motivo, resultado, mensaje, creado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['tipo'], $d['id_puesto'], $d['id_estudiante'] ?? null, $d['documento_leido'] ?? null,
                $d['nombre_leido'] ?? null, $d['id_visita'] ?? null, $d['estado'], $d['motivo'] ?? null,
                $d['resultado'] ?? null, $d['mensaje'] ?? null, $d['creado_por'],
            ]
        );
    }

    /** Guarda lo que vio la cámara (también cuando no fue confiable). */
    public function registrarDeteccion(int $id, string $tipo, ?float $confianza, ?float $segunda, string $fuente): void
    {
        $this->run(
            'UPDATE eventos_acceso SET tipo_detectado = ?, confianza = ?, segunda = ?, fuente = ?, clasificado_en = NOW(3)
              WHERE id_evento = ?',
            [$tipo, $confianza !== null ? round($confianza, 4) : null, $segunda !== null ? round($segunda, 4) : null, $fuente, $id]
        );
    }

    public function pasarAPortero(int $id, string $motivo, string $mensaje, bool $captura = false): void
    {
        $this->run(
            "UPDATE eventos_acceso SET estado = 'PENDIENTE_PORTERO', motivo = ?, mensaje = ?, tiene_captura = ?
              WHERE id_evento = ? AND estado IN ('EN_CAPTURA', 'PENDIENTE_PORTERO')",
            [$motivo, $mensaje, $captura ? 1 : 0, $id]
        );
    }

    /** Cierre del evento: CONFIRMADO, RECHAZADO o EXPIRADO. */
    public function cerrar(int $id, string $estado, array $d): int
    {
        return $this->run(
            "UPDATE eventos_acceso
                SET estado = ?, tipo_confirmado = COALESCE(?, tipo_confirmado), metodo = COALESCE(?, metodo),
                    id_visita = COALESCE(?, id_visita), resultado = ?, mensaje = ?, motivo = COALESCE(?, motivo),
                    resuelto_por = COALESCE(?, resuelto_por), resuelto_en = NOW(3), tiene_captura = 0
              WHERE id_evento = ? AND estado IN ('EN_CAPTURA', 'PENDIENTE_PORTERO')",
            [
                $estado, $d['tipo_confirmado'] ?? null, $d['metodo'] ?? null, $d['id_visita'] ?? null,
                $d['resultado'] ?? null, $d['mensaje'] ?? null, $d['motivo'] ?? null, $d['resuelto_por'] ?? null, $id,
            ]
        );
    }

    /** Pendientes para el panel del portero, los más antiguos primero. */
    public function pendientes(): array
    {
        return $this->all(
            "SELECT ev.*, e.nombres, e.apellidos, e.documento, e.categoria,
                    v.tipo_vehiculo AS tipo_ingreso, v.entrada_en AS ingreso_en,
                    p.nombre AS puesto, u.nombre AS dispositivo
               FROM eventos_acceso ev
          LEFT JOIN estudiantes e ON e.id_estudiante = ev.id_estudiante
          LEFT JOIN visitas v     ON v.id_visita = ev.id_visita
               JOIN puestos_camara p ON p.id_puesto = ev.id_puesto
               JOIN usuarios u    ON u.id_usuario = ev.creado_por
              WHERE ev.estado = 'PENDIENTE_PORTERO'
              ORDER BY ev.creado_en"
        );
    }

    /**
     * Actividad en tiempo real para el portero (solo lectura): personas que se están identificando en el punto de
     * acceso (EN_CAPTURA) y lo resuelto en los últimos minutos. No incluye los pendientes (esos van con botones).
     */
    public function actividad(int $minutos = 3, int $limite = 12): array
    {
        return $this->all(
            "SELECT ev.id_evento, ev.tipo, ev.estado, ev.resultado, ev.tipo_detectado, ev.confianza, ev.tipo_confirmado, ev.metodo, ev.resuelto_por,
                    ev.creado_en, ev.resuelto_en, e.nombres, e.apellidos, e.documento, e.categoria, ev.documento_leido, ev.nombre_leido,
                    p.nombre AS puesto
               FROM eventos_acceso ev
          LEFT JOIN estudiantes e ON e.id_estudiante = ev.id_estudiante
               JOIN puestos_camara p ON p.id_puesto = ev.id_puesto
              WHERE ev.estado = 'EN_CAPTURA'
                 OR (ev.estado IN ('CONFIRMADO', 'RECHAZADO', 'EXPIRADO') AND COALESCE(ev.resuelto_en, ev.creado_en) >= NOW() - INTERVAL ? MINUTE)
              ORDER BY ev.id_evento DESC
              LIMIT " . max(1, $limite),
            [$minutos]
        );
    }

    /** IDs de eventos abiertos que superaron su tiempo. @return list<int> */
    public function vencidos(int $minutosPendiente, int $minutosCaptura): array
    {
        return array_map('intval', array_column($this->all(
            "SELECT id_evento FROM eventos_acceso
              WHERE (estado = 'PENDIENTE_PORTERO' AND creado_en < NOW(3) - INTERVAL ? MINUTE)
                 OR (estado = 'EN_CAPTURA' AND creado_en < NOW(3) - INTERVAL ? MINUTE)",
            [$minutosPendiente, $minutosCaptura]
        ), 'id_evento'));
    }

    /** @param array{desde?:string,hasta?:string,estado?:string,metodo?:string,tipo?:string} $f */
    public function reporte(array $f, int $limite = 500): array
    {
        $where = [];
        $params = [];
        self::rango('ev.creado_en', $f, $where, $params);
        if (!empty($f['texto'])) {
            $where[] = "(e.documento LIKE ? OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ? OR ev.documento_leido LIKE ?)";
            array_push($params, self::like($f['texto']), self::like($f['texto']), self::like($f['texto']));
        }
        foreach (['estado_evento' => 'ev.estado', 'metodo' => 'ev.metodo', 'tipo' => 'ev.tipo_confirmado'] as $k => $col) {
            if (!empty($f[$k])) {
                $where[] = "$col = ?";
                $params[] = $f[$k];
            }
        }
        return $this->all(
            "SELECT ev.*, e.documento, e.nombres, e.apellidos, ur.nombre AS portero
               FROM eventos_acceso ev
          LEFT JOIN estudiantes e ON e.id_estudiante = ev.id_estudiante
          LEFT JOIN usuarios ur ON ur.id_usuario = ev.resuelto_por"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ev.id_evento DESC LIMIT ' . (int) $limite,
            $params
        );
    }

    /**
     * Calibración de la IA con datos reales: por rango de confianza, cuántas detecciones confirmó el
     * portero con la misma categoría. Sirve para ajustar IA_UMBRAL con evidencia de la propia portería.
     */
    public function calibracion(): array
    {
        return $this->all(
            "SELECT FLOOR(confianza * 10) / 10 AS desde,
                    COUNT(*) AS eventos,
                    SUM(metodo = 'IA') AS automaticas,
                    SUM(metodo = 'MANUAL') AS manuales,
                    SUM(metodo = 'MANUAL' AND tipo_detectado = tipo_confirmado) AS manual_coincide,
                    SUM(metodo = 'MANUAL' AND tipo_detectado <> tipo_confirmado) AS manual_corrige
               FROM eventos_acceso
              WHERE confianza IS NOT NULL AND estado = 'CONFIRMADO'
              GROUP BY FLOOR(confianza * 10)
              ORDER BY desde DESC"
        );
    }
}
