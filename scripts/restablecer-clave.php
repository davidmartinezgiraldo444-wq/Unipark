<?php
declare(strict_types=1);

/**
 * UniPark · Fijar la contraseña de una cuenta desde la consola del servidor (por ejemplo, admin).
 *
 *   php scripts/restablecer-clave.php admin
 *
 * Pide la contraseña nueva dos veces (no queda escrita en ningún archivo), exige la misma política que la
 * aplicación (10 caracteres o más, distinta del usuario) y queda registrada en la auditoría.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\UsuarioRepository;
use App\Services\AuditService;
use App\Services\AuthService;

$login = $argv[1] ?? '';
$pdo = Database::connection();
$id = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE login = ?');
$id->execute([$login]);
$idUsuario = (int) $id->fetchColumn();
if ($idUsuario === 0) {
    fwrite(STDERR, "No existe la cuenta «{$login}». Uso: php scripts/restablecer-clave.php admin\n");
    exit(1);
}

$leer = static fn (): string => trim(str_replace("\u{FEFF}", '', (string) fgets(STDIN)), " \t\r\n\0\x0B");
echo "Nueva contraseña para {$login} (10 caracteres o más; se ve mientras la escribes): ";
$clave = $leer();
echo "Escríbela otra vez para confirmar: ";
if ($leer() !== $clave) {
    fwrite(STDERR, "Las dos contraseñas no coinciden. No se cambió nada.\n");
    exit(1);
}
$error = AuthService::validarPolitica($clave, $login);
if ($error !== null) {
    fwrite(STDERR, $error . " No se cambió nada.\n");
    exit(1);
}
(new UsuarioRepository($pdo))->setPassword($idUsuario, password_hash($clave, PASSWORD_DEFAULT), false);
(new AuditService($pdo))->log('CLAVE_RESTABLECIDA', 'usuarios', $idUsuario, 'EXITO', 'Contraseña fijada desde la consola del servidor');
echo "Listo: la contraseña de {$login} quedó cambiada.\n";
