<?php
declare(strict_types=1);

namespace App\Repositories;

final class UsuarioRepository extends Repository
{
    private const SELECT = 'SELECT u.*, r.nombre AS rol, r.activo AS rol_activo
                              FROM usuarios u
                              JOIN roles r ON r.id_rol = u.id_rol';

    public function find(int $id): ?array
    {
        return $this->one(self::SELECT . ' WHERE u.id_usuario = ?', [$id]);
    }

    public function findByLogin(string $login): ?array
    {
        return $this->one(self::SELECT . ' WHERE u.login = ?', [$login]);
    }

    /** @return list<string> códigos de permiso del rol del usuario */
    public function permisos(int $idUsuario): array
    {
        $filas = $this->all(
            'SELECT p.codigo
               FROM usuarios u
               JOIN roles r        ON r.id_rol = u.id_rol AND r.activo = 1
               JOIN rol_permiso rp ON rp.id_rol = r.id_rol
               JOIN permisos p     ON p.id_permiso = rp.id_permiso
              WHERE u.id_usuario = ? AND u.activo = 1',
            [$idUsuario]
        );
        return array_column($filas, 'codigo');
    }

    public function list(): array
    {
        return $this->all(self::SELECT . ' ORDER BY u.activo DESC, r.id_rol, u.nombre');
    }

    public function roles(): array
    {
        return $this->all('SELECT * FROM roles WHERE activo = 1 ORDER BY id_rol');
    }

    public function rol(int $idRol): ?array
    {
        return $this->one('SELECT * FROM roles WHERE id_rol = ? AND activo = 1', [$idRol]);
    }

    public function create(string $nombre, string $login, string $hash, int $idRol): int
    {
        return $this->insert(
            'INSERT INTO usuarios (nombre, login, password_hash, id_rol, activo, debe_cambiar_clave)
             VALUES (?, ?, ?, ?, 1, 1)',
            [$nombre, $login, $hash, $idRol]
        );
    }

    public function update(int $id, string $nombre, int $idRol): void
    {
        $this->run('UPDATE usuarios SET nombre = ?, id_rol = ? WHERE id_usuario = ?', [$nombre, $idRol, $id]);
    }

    public function setActivo(int $id, bool $activo): void
    {
        $this->run('UPDATE usuarios SET activo = ? WHERE id_usuario = ?', [$activo ? 1 : 0, $id]);
    }

    public function setPassword(int $id, string $hash, bool $debeCambiar): void
    {
        $this->run(
            'UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = ? WHERE id_usuario = ?',
            [$hash, $debeCambiar ? 1 : 0, $id]
        );
    }

    public function touchUltimoAcceso(int $id): void
    {
        $this->run('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?', [$id]);
    }

    /** Administradores activos distintos del indicado: impide dejar el sistema sin administración. */
    public function otrosAdministradoresActivos(int $excepto): int
    {
        return (int) $this->one(
            "SELECT COUNT(*) AS n
               FROM usuarios u
               JOIN rol_permiso rp ON rp.id_rol = u.id_rol
               JOIN permisos p     ON p.id_permiso = rp.id_permiso AND p.codigo = 'USUARIO_GESTIONAR'
              WHERE u.activo = 1 AND u.id_usuario <> ?",
            [$excepto]
        )['n'];
    }

    public function conActividad(): array
    {
        return $this->all(
            'SELECT DISTINCT u.id_usuario, u.nombre, r.nombre AS rol
               FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
              ORDER BY u.nombre'
        );
    }
}
