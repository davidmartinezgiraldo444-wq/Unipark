<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Csrf;
use App\Helpers\Session;
use App\Repositories\AuditoriaRepository;
use App\Repositories\UsuarioRepository;
use PDO;

/** Autenticación (RF-01) y usuario actual con sus permisos (RF-02). */
final class AuthService
{
    public const MENSAJE_GENERICO = 'Usuario o contraseña incorrectos.';

    private static ?array $usuario = null;
    /** @var list<string> */
    private static array $permisos = [];

    private UsuarioRepository $usuarios;
    private AuditoriaRepository $auditoria;
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->usuarios = new UsuarioRepository($pdo);
        $this->auditoria = new AuditoriaRepository($pdo);
        $this->audit = new AuditService($pdo);
    }

    // --- Usuario actual ----------------------------------------------------

    public static function setUsuario(?array $usuario, array $permisos = []): void
    {
        self::$usuario = $usuario;
        self::$permisos = $permisos;
    }

    public static function usuario(): ?array
    {
        return self::$usuario;
    }

    public static function id(): ?int
    {
        return self::$usuario ? (int) self::$usuario['id_usuario'] : null;
    }

    /** Verdadero si el usuario tiene al menos uno de los permisos. Deniega por defecto. */
    public static function can(string ...$permisos): bool
    {
        foreach ($permisos as $permiso) {
            if (in_array($permiso, self::$permisos, true)) {
                return true;
            }
        }
        return false;
    }

    // --- Inicio y cierre de sesión ----------------------------------------

    /**
     * Verifica las credenciales. Siempre responde con el mismo mensaje, exista o no el usuario.
     * @throws ReglaNegocioException
     */
    public function intentar(string $login, string $clave, string $ip): array
    {
        $max = (int) config('login_max_intentos');
        $fallidos = $this->auditoria->fallidosRecientes($login, $ip, (int) config('login_ventana'));
        if ($fallidos['login'] >= $max || $fallidos['ip'] >= $max * 3) {
            $this->audit->log('LOGIN_BLOQUEADO', 'usuarios', null, 'DENEGADO', 'Demasiados intentos', ['login' => $login]);
            throw new ReglaNegocioException(
                'BLOQUEADO',
                'Demasiados intentos fallidos. Espera unos minutos antes de volver a intentar.'
            );
        }

        $usuario = $this->usuarios->findByLogin($login);
        // Se verifica un hash aunque el usuario no exista, para no revelarlo por el tiempo de respuesta.
        // En hexadecimal: PHP 8.5 no acepta el byte nulo en bcrypt (random_bytes puede generarlo).
        $hash = $usuario['password_hash'] ?? password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
        // Una clave con byte nulo nunca es válida (bcrypt la truncaría); se verifica igual para no variar el tiempo.
        $claveCorrecta = password_verify(str_replace("\0", '', $clave), $hash) && !str_contains($clave, "\0");
        $habilitado = $usuario !== null && (int) $usuario['activo'] === 1 && (int) $usuario['rol_activo'] === 1;

        if (!$claveCorrecta || !$habilitado) {
            $this->audit->log(
                'LOGIN_FALLIDO',
                'usuarios',
                $usuario ? (int) $usuario['id_usuario'] : null,
                'DENEGADO',
                $claveCorrecta ? 'Cuenta inactiva' : 'Credenciales inválidas',
                ['login' => $login]
            );
            throw new ReglaNegocioException('CREDENCIALES', self::MENSAJE_GENERICO);
        }

        $id = (int) $usuario['id_usuario'];
        if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
            $this->usuarios->setPassword($id, password_hash($clave, PASSWORD_DEFAULT), (bool) $usuario['debe_cambiar_clave']);
        }
        $this->usuarios->touchUltimoAcceso($id);
        $this->audit->log('LOGIN_EXITOSO', 'usuarios', $id, 'EXITO', null, [], $id);
        return $usuario;
    }

    public function iniciarSesion(array $usuario): void
    {
        Session::regenerate();
        Session::forget('_flash');
        Session::set('id_usuario', (int) $usuario['id_usuario']);
        Session::set('ultima_actividad', time());
        Csrf::rotate();
    }

    public function cerrarSesion(): void
    {
        if (self::id() !== null) {
            $this->audit->log('LOGOUT', 'usuarios', self::id());
        }
        Session::destroy();
        self::setUsuario(null);
    }

    // --- Contraseñas -------------------------------------------------------

    /** @throws ReglaNegocioException */
    public function cambiarClave(int $idUsuario, string $actual, string $nueva, string $confirmacion): void
    {
        $usuario = $this->usuarios->find($idUsuario);
        if ($usuario === null || !password_verify($actual, $usuario['password_hash'])) {
            throw new ReglaNegocioException('CLAVE_ACTUAL', 'La contraseña actual no es correcta.');
        }
        if ($nueva !== $confirmacion) {
            throw new ReglaNegocioException('CONFIRMACION', 'La confirmación no coincide con la nueva contraseña.');
        }
        if (str_contains($nueva, "\0")) {
            throw new ReglaNegocioException('POLITICA', 'La contraseña contiene caracteres no permitidos.');
        }
        if (password_verify($nueva, $usuario['password_hash'])) {
            throw new ReglaNegocioException('CLAVE_REPETIDA', 'La nueva contraseña debe ser distinta de la actual.');
        }
        $error = self::validarPolitica($nueva, $usuario['login']);
        if ($error !== null) {
            throw new ReglaNegocioException('POLITICA', $error);
        }
        $this->usuarios->setPassword($idUsuario, password_hash($nueva, PASSWORD_DEFAULT), false);
        $this->audit->log('CLAVE_CAMBIADA', 'usuarios', $idUsuario);
    }

    public static function validarPolitica(string $clave, string $login): ?string
    {
        $min = (int) config('clave_min_longitud');
        if (mb_strlen($clave) < $min) {
            return "La contraseña debe tener al menos {$min} caracteres.";
        }
        if (!preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $clave) || !preg_match('/\d/', $clave)) {
            return 'La contraseña debe combinar letras y números.';
        }
        if (mb_stripos($clave, $login) !== false) {
            return 'La contraseña no puede contener el nombre de usuario.';
        }
        return null;
    }

    /** Contraseña temporal legible, sin caracteres ambiguos (0/O, 1/l). */
    public static function claveTemporal(): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digitos = '23456789';
        $clave = '';
        for ($i = 0; $i < 9; $i++) {
            $clave .= $letras[random_int(0, strlen($letras) - 1)];
        }
        for ($i = 0; $i < 3; $i++) {
            $clave .= $digitos[random_int(0, strlen($digitos) - 1)];
        }
        return str_shuffle($clave);
    }
}
