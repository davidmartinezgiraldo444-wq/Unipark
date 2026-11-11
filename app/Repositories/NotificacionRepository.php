<?php
declare(strict_types=1);

namespace App\Repositories;

/** Avisos para la persona y navegadores suscritos a notificaciones push. */
final class NotificacionRepository extends Repository
{
    /** Crea el aviso una sola vez por cobro y tipo. @return ?int null si ya existía */
    public function crear(int $idEstudiante, ?int $idCobro, string $tipo, string $titulo, string $mensaje): ?int
    {
        $n = $this->run(
            'INSERT IGNORE INTO notificaciones (id_estudiante, id_cobro, tipo, titulo, mensaje) VALUES (?, ?, ?, ?, ?)',
            [$idEstudiante, $idCobro, $tipo, $titulo, $mensaje]
        );
        return $n > 0 ? (int) $this->pdo->lastInsertId() : null;
    }

    public function marcarEnviada(int $id): void
    {
        $this->run('UPDATE notificaciones SET enviada_push = 1 WHERE id_notificacion = ?', [$id]);
    }

    public function dePersona(int $idEstudiante, int $limite = 20): array
    {
        return $this->all(
            'SELECT * FROM notificaciones WHERE id_estudiante = ? ORDER BY id_notificacion DESC LIMIT ' . max(1, $limite),
            [$idEstudiante]
        );
    }

    public function marcarLeidas(int $idEstudiante): void
    {
        $this->run('UPDATE notificaciones SET leida_en = NOW() WHERE id_estudiante = ? AND leida_en IS NULL', [$idEstudiante]);
    }

    // --- Suscripciones push ------------------------------------------------

    public function guardarSuscripcion(int $idUsuario, string $endpoint, string $p256dh, string $auth, string $navegador): void
    {
        $this->run(
            'INSERT INTO push_suscripciones (id_usuario, endpoint, endpoint_hash, p256dh, auth, navegador, activa)
             VALUES (?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE id_usuario = VALUES(id_usuario), p256dh = VALUES(p256dh), auth = VALUES(auth),
                                     navegador = VALUES(navegador), activa = 1, ultimo_error = NULL',
            [$idUsuario, $endpoint, hash('sha256', $endpoint), $p256dh, $auth, mb_substr($navegador, 0, 160)]
        );
    }

    public function desactivarSuscripcion(string $endpoint, ?int $idUsuario = null, ?string $error = null): void
    {
        $sql = 'UPDATE push_suscripciones SET activa = 0, ultimo_error = ? WHERE endpoint_hash = ?';
        $params = [$error, hash('sha256', $endpoint)];
        if ($idUsuario !== null) {
            $sql .= ' AND id_usuario = ?';
            $params[] = $idUsuario;
        }
        $this->run($sql, $params);
    }

    public function registrarEnvio(int $idSuscripcion, ?string $error): void
    {
        $this->run(
            'UPDATE push_suscripciones SET ultimo_envio = NOW(), ultimo_error = ? WHERE id_suscripcion = ?',
            [$error !== null ? mb_substr($error, 0, 255) : null, $idSuscripcion]
        );
    }

    /** Suscripciones activas de las cuentas vinculadas a la persona. */
    public function suscripcionesDePersona(int $idEstudiante): array
    {
        return $this->all(
            'SELECT s.* FROM push_suscripciones s
               JOIN usuarios u ON u.id_usuario = s.id_usuario AND u.activo = 1
              WHERE u.id_estudiante = ? AND s.activa = 1',
            [$idEstudiante]
        );
    }

    public function suscripcionesDeUsuario(int $idUsuario): int
    {
        return (int) $this->one(
            'SELECT COUNT(*) AS n FROM push_suscripciones WHERE id_usuario = ? AND activa = 1',
            [$idUsuario]
        )['n'];
    }
}
