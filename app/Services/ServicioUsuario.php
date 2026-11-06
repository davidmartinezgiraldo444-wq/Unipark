<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\UsuarioRepository;
use PDO;
use PDOException;

/** Administración de cuentas individuales (RF-03). */
final class ServicioUsuario
{
    private UsuarioRepository $usuarios;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->usuarios = new UsuarioRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    /**
     * Crea la cuenta con una contraseña temporal que debe cambiarse en el primer ingreso.
     * @return array{id:int,clave:string}
     * @throws ReglaNegocioException
     */
    public function crear(string $nombre, string $login, int $idRol): array
    {
        $this->validar($nombre, $idRol);
        $login = mb_strtolower(trim($login));
        if (preg_match('/^[a-z0-9._@-]{3,120}$/', $login) !== 1) {
            throw new ReglaNegocioException('LOGIN', 'El usuario debe tener entre 3 y 120 caracteres: letras, números, punto, guion o @.');
        }
        $clave = AuthService::claveTemporal();
        try {
            $id = $this->usuarios->create(trim($nombre), $login, password_hash($clave, PASSWORD_DEFAULT), $idRol);
        } catch (PDOException $e) {
            if (Database::isDuplicate($e)) {
                throw new ReglaNegocioException('LOGIN_DUPLICADO', 'Ya existe una cuenta con ese usuario.');
            }
            throw $e;
        }
        $this->audit->log('USUARIO_CREADO', 'usuarios', $id, 'EXITO', null, ['login' => $login, 'id_rol' => $idRol]);
        return ['id' => $id, 'clave' => $clave];
    }

    /** @throws ReglaNegocioException */
    public function actualizar(int $id, string $nombre, int $idRol, int $actor): void
    {
        $usuario = $this->existente($id);
        $this->validar($nombre, $idRol);
        if ($id === $actor && (int) $usuario['id_rol'] !== $idRol) {
            throw new ReglaNegocioException('PROPIO_ROL', 'No puedes cambiar tu propio rol.');
        }
        $this->usuarios->update($id, trim($nombre), $idRol);
        $this->audit->log('USUARIO_ACTUALIZADO', 'usuarios', $id, 'EXITO', null, [
            'rol_anterior' => (int) $usuario['id_rol'],
            'rol_nuevo'    => $idRol,
        ]);
    }

    /** @throws ReglaNegocioException */
    public function cambiarEstado(int $id, bool $activo, int $actor): void
    {
        $this->existente($id);
        if (!$activo && $id === $actor) {
            throw new ReglaNegocioException('PROPIA_CUENTA', 'No puedes desactivar tu propia cuenta.');
        }
        if (!$activo && $this->usuarios->otrosAdministradoresActivos($id) === 0) {
            throw new ReglaNegocioException('ULTIMO_ADMIN', 'Debe quedar al menos un administrador activo.');
        }
        $this->usuarios->setActivo($id, $activo);
        $this->audit->log($activo ? 'USUARIO_ACTIVADO' : 'USUARIO_DESACTIVADO', 'usuarios', $id);
    }

    /** Restablece el acceso con una contraseña temporal. @throws ReglaNegocioException */
    public function restablecerClave(int $id): string
    {
        $this->existente($id);
        $clave = AuthService::claveTemporal();
        $this->usuarios->setPassword($id, password_hash($clave, PASSWORD_DEFAULT), true);
        $this->audit->log('CLAVE_RESTABLECIDA', 'usuarios', $id);
        return $clave;
    }

    private function existente(int $id): array
    {
        $usuario = $this->usuarios->find($id);
        if ($usuario === null) {
            throw new ReglaNegocioException('NO_EXISTE', 'La cuenta no existe.');
        }
        return $usuario;
    }

    private function validar(string $nombre, int $idRol): void
    {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 120) {
            throw new ReglaNegocioException('NOMBRE', 'Escribe el nombre completo (máximo 120 caracteres).');
        }
        if ($this->usuarios->rol($idRol) === null) {
            throw new ReglaNegocioException('ROL', 'Selecciona un rol válido.');
        }
    }
}
