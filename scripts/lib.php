<?php
declare(strict_types=1);

// Utilidades compartidas por los scripts de respaldo y restauración.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Env;

/**
 * Ruta de un ejecutable de MySQL/MariaDB (mysql, mysqldump). MariaDB 11 ya no instala los nombres antiguos
 * («mysql», «mysqldump») sino «mariadb» y «mariadb-dump»: se prueban los dos.
 */
function mysql_bin(string $nombre): string
{
    $alternativo = ['mysql' => 'mariadb', 'mysqldump' => 'mariadb-dump'][$nombre] ?? $nombre;
    $sufijo = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
    $dir = Env::get('MYSQL_BIN_DIR', '');
    foreach (array_unique([$nombre, $alternativo]) as $candidato) {
        if ($dir !== '') {
            $ruta = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $candidato . $sufijo;
            if (is_file($ruta)) {
                return $ruta;
            }
        }
    }
    // Sin carpeta configurada: el que exista en el PATH (en Linux, `command -v`).
    if (PHP_OS_FAMILY !== 'Windows') {
        foreach (array_unique([$nombre, $alternativo]) as $candidato) {
            $ruta = trim((string) shell_exec('command -v ' . escapeshellarg($candidato) . ' 2>/dev/null'));
            if ($ruta !== '') {
                return $ruta;
            }
        }
    }
    return $nombre;
}

/**
 * Archivo temporal de opciones con la cuenta administrativa, para no pasar la contraseña
 * en la línea de comandos (quedaría visible en la lista de procesos).
 */
function mysql_defaults_file(): string
{
    $archivo = tempnam(sys_get_temp_dir(), 'unipark');
    $contenido = "[client]\n"
        . 'host=' . Env::get('DB_HOST', '127.0.0.1') . "\n"
        . 'port=' . Env::get('DB_PORT', '3306') . "\n"
        . 'user=' . Env::get('DB_ADMIN_USER', 'root') . "\n"
        . 'password="' . addcslashes(Env::get('DB_ADMIN_PASS', ''), '"\\') . "\"\n"
        . "default-character-set=utf8mb4\n";
    file_put_contents($archivo, $contenido);
    @chmod($archivo, 0600);
    register_shutdown_function(static fn () => @unlink($archivo));
    return $archivo;
}

function nombre_base(string $nombre): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $nombre)) {
        fail("Nombre de base de datos no válido: {$nombre}");
    }
    return $nombre;
}

/** Ejecuta un proceso y devuelve [código, salida de error]. */
function ejecutar(array $comando, array $descriptores = []): array
{
    $descriptores += [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($comando, $descriptores, $pipes);
    if (!is_resource($proc)) {
        fail('No se pudo ejecutar ' . $comando[0]);
    }
    $salida = isset($pipes[1]) ? stream_get_contents($pipes[1]) : '';
    $error = isset($pipes[2]) ? stream_get_contents($pipes[2]) : '';
    foreach ($pipes as $p) {
        fclose($p);
    }
    $codigo = proc_close($proc);
    return [$codigo, trim($error . "\n" . $salida)];
}

function respaldar(string $base, ?string $destino = null): string
{
    $destino ??= BASE_PATH . '/storage/backups/' . $base . '_' . date('Ymd_His') . '.sql';
    [$codigo, $error] = ejecutar([
        mysql_bin('mysqldump'),
        '--defaults-extra-file=' . mysql_defaults_file(),
        '--single-transaction',
        '--routines',
        '--triggers',
        '--add-drop-table',
        '--no-create-db',
        '--default-character-set=utf8mb4',
        '--result-file=' . $destino,
        $base,
    ]);
    if ($codigo !== 0 || !is_file($destino) || filesize($destino) === 0) {
        @unlink($destino);
        fail("mysqldump falló ({$codigo}): {$error}");
    }
    return $destino;
}

function restaurar(string $base, string $archivo): void
{
    [$codigo, $error] = ejecutar(
        [mysql_bin('mysql'), '--defaults-extra-file=' . mysql_defaults_file(), $base],
        [0 => ['file', $archivo, 'r']]
    );
    if ($codigo !== 0) {
        fail("La restauración falló ({$codigo}): {$error}");
    }
}

function fail(string $mensaje): never
{
    fwrite(STDERR, $mensaje . PHP_EOL);
    exit(1);
}
