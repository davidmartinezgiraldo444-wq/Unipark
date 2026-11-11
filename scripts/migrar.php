<?php
declare(strict_types=1);

/**
 * UniPark · Aplicar migraciones sobre una base existente, sin perder datos.
 *
 *   php scripts/migrar.php                 Aplica las migraciones pendientes a DB_NAME (respaldo previo).
 *   php scripts/migrar.php --demo          Incluye las migraciones de demostración (*_demo_*): usuarios de prueba.
 *   php scripts/migrar.php --estado        Solo muestra qué migraciones están aplicadas y cuáles faltan.
 *   php scripts/migrar.php --base=nombre   Usa otra base (por ejemplo unipark_db_test).
 *   php scripts/migrar.php --sin-respaldo  No ejecuta mysqldump antes (no recomendado).
 *
 * Usa la cuenta administrativa (DB_ADMIN_USER / DB_ADMIN_PASS). Al terminar vuelve a otorgar a la
 * cuenta de la aplicación (DB_USER) privilegios mínimos sobre todas las tablas: SELECT, INSERT y
 * UPDATE, sin DELETE; la auditoría y el libro de saldos solo admiten SELECT e INSERT.
 */

require __DIR__ . '/lib.php';

use App\Helpers\Database;
use App\Helpers\Env;
use App\Helpers\SqlFile;

$opciones = getopt('', ['demo', 'estado', 'base:', 'sin-respaldo', 'help']);
if (isset($opciones['help'])) {
    echo file_get_contents(__FILE__, false, null, 0, 1100), PHP_EOL;
    exit(0);
}

$base = nombre_base((string) ($opciones['base'] ?? Env::get('DB_NAME', 'unipark_db')));
$pdo = Database::connect([
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'name' => $base,
    'user' => Env::get('DB_ADMIN_USER', 'root'),
    'pass' => Env::get('DB_ADMIN_PASS', ''),
]);

$pdo->exec('CREATE TABLE IF NOT EXISTS migraciones (
    archivo     VARCHAR(120) NOT NULL,
    aplicada_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (archivo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');

$aplicadas = $pdo->query('SELECT archivo FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
$archivos = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($archivos, SORT_STRING);

$pendientes = [];
echo "UniPark · migraciones en «{$base}»\n";
foreach ($archivos as $archivo) {
    $nombre = basename($archivo);
    $esDemo = str_contains($nombre, '_demo_');
    $estado = in_array($nombre, $aplicadas, true) ? 'aplicada' : ($esDemo && !isset($opciones['demo']) ? 'omitida (usa --demo)' : 'pendiente');
    echo sprintf("  %-55s %s\n", $nombre, $estado);
    if ($estado === 'pendiente') {
        $pendientes[] = $archivo;
    }
}

if (isset($opciones['estado'])) {
    exit(0);
}
if (!$pendientes) {
    echo "Nada que aplicar.\n";
    otorgarPrivilegios($pdo, $base);
    exit(0);
}

if (!isset($opciones['sin-respaldo'])) {
    $respaldo = respaldar($base);
    echo "Respaldo previo: {$respaldo}\n";
}

foreach ($pendientes as $archivo) {
    $nombre = basename($archivo);
    echo "→ {$nombre}\n";
    try {
        $n = SqlFile::run($pdo, $archivo);
    } catch (PDOException $e) {
        fail("   ✗ Falló: " . $e->getMessage() . "\n   La base puede haber quedado a medias: restaura el respaldo con scripts/restore-db.");
    }
    $stmt = $pdo->prepare('INSERT INTO migraciones (archivo) VALUES (?)');
    $stmt->execute([$nombre]);
    echo "   ✓ {$n} sentencias\n";
}

otorgarPrivilegios($pdo, $base);
echo "Listo.\n";

/** Privilegios mínimos de la cuenta de la aplicación sobre todas las tablas (sección 9.2). */
function otorgarPrivilegios(PDO $pdo, string $base): void
{
    $usuario = Env::get('DB_USER', 'unipark_app');
    if (preg_match('/^[A-Za-z0-9_]{1,32}$/', $usuario) !== 1 || $usuario === Env::get('DB_ADMIN_USER', 'root')) {
        echo "Cuenta de la aplicación igual a la administrativa: no se ajustan privilegios.\n";
        return;
    }
    $origen = in_array(Env::get('DB_HOST', '127.0.0.1'), ['127.0.0.1', 'localhost', '::1'], true) ? 'localhost' : '%';
    $cuenta = "'{$usuario}'@'{$origen}'";
    $existe = (int) $pdo->query('SELECT COUNT(*) FROM mysql.user WHERE User = ' . $pdo->quote($usuario))->fetchColumn();
    if ($existe === 0) {
        echo "La cuenta {$cuenta} no existe: créala con el instalador.\n";
        return;
    }
    $tablas = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = ' . $pdo->quote($base))
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tablas as $tabla) {
        $privilegios = in_array($tabla, ['auditoria', 'movimientos_saldo', 'migraciones', 'importaciones', 'notificaciones_pasarela'], true) ? 'SELECT, INSERT' : 'SELECT, INSERT, UPDATE';
        $pdo->exec("GRANT {$privilegios} ON `{$base}`.`{$tabla}` TO {$cuenta}");
    }
    $pdo->exec('FLUSH PRIVILEGES');
    echo "Privilegios mínimos de {$cuenta} actualizados en " . count($tablas) . " tablas.\n";
}
