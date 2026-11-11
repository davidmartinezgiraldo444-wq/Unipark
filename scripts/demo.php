<?php
declare(strict_types=1);

/**
 * UniPark · Copia de DEMOSTRACIÓN (para presentar el proyecto sin tocar el sistema real).
 *
 *   php scripts/demo.php --crear       Crea la base unipark_demo con datos ficticios y la carpeta htdocs/UniParkDemo
 *   php scripts/demo.php --reiniciar   Vuelve la demo a su estado inicial (borra SOLO unipark_demo y sus pagos simulados)
 *   php scripts/demo.php --estado      Muestra cómo está la demo
 *   --tolerancia=N                      Minutos de tolerancia SOLO en la demo (1 a 15; por defecto 1: «tiempo acelerado»)
 *
 * La demo usa el MISMO código que UniPark (htdocs/UniPark), con:
 *   - su propia base de datos (unipark_demo), cargada con database/schema.sql, base.sql y seed.sql (datos ficticios);
 *   - su propia cookie de sesión (UNIPARK_DEMO): nunca comparte sesión con el sistema real;
 *   - la pasarela SIMULADA (no es Wompi; no mueve dinero) y un rótulo «MODO DEMOSTRACIÓN» en todas las pantallas.
 * Nunca opera sobre DB_NAME ni sobre la base protegida (unipark_db).
 */

require __DIR__ . '/lib.php';

use App\Helpers\Database;
use App\Helpers\Env;
use App\Helpers\SqlFile;

const BASE_DEMO = 'unipark_demo';

$opciones = getopt('', ['crear', 'reiniciar', 'estado', 'help', 'tolerancia:']);
// Tiempo acelerado: la demo recorre la ruta completa en vivo. El sistema real siempre usa 15 minutos.
$tolerancia = max(1, min(15, (int) ($opciones['tolerancia'] ?? 1)));
if (isset($opciones['help']) || !$opciones) {
    echo file_get_contents(__FILE__, false, null, 0, 1200), PHP_EOL;
    exit(0);
}

$real = (string) Env::get('DB_NAME', 'unipark_db');
$protegida = (string) config('base_protegida');
if (in_array(BASE_DEMO, [$real, $protegida], true)) {
    fail('La base de la demo no puede ser la base real ni la protegida.');
}
$carpeta = dirname(BASE_PATH) . DIRECTORY_SEPARATOR . 'UniParkDemo';
$admin = Database::connect(['host' => Env::get('DB_HOST', '127.0.0.1'), 'port' => Env::get('DB_PORT', '3306'), 'name' => null,
    'user' => Env::get('DB_ADMIN_USER', 'root'), 'pass' => Env::get('DB_ADMIN_PASS', '')]);
$existe = (bool) $admin->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '" . BASE_DEMO . "'")->fetchColumn();

if (isset($opciones['estado'])) {
    echo 'Base ' . BASE_DEMO . ': ' . ($existe ? 'existe' : 'no existe') . PHP_EOL;
    echo 'Carpeta: ' . $carpeta . (is_file($carpeta . '/index.php') ? ' (lista)' : ' (no creada)') . PHP_EOL;
    if ($existe) {
        $pdo = conectarDemo();
        foreach (['estudiantes', 'usuarios', 'recargas_en_linea', 'movimientos_saldo'] as $t) {
            echo sprintf('  %-20s %d filas', $t, $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn()) . PHP_EOL;
        }
    }
    exit(0);
}

if ($existe && !isset($opciones['reiniciar'])) {
    fail('La demo ya existe. Usa --reiniciar para volverla a su estado inicial.');
}

echo "UniPark · copia de demostración\n";
$admin->exec('DROP DATABASE IF EXISTS `' . BASE_DEMO . '`');
$admin->exec('CREATE DATABASE `' . BASE_DEMO . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = conectarDemo();
foreach (['schema.sql', 'base.sql', 'seed.sql'] as $archivo) {
    $n = SqlFile::run($pdo, BASE_PATH . '/database/' . $archivo);
    echo "  ✓ {$archivo} ({$n} sentencias)\n";
}
// schema.sql ya incluye todas las migraciones: se registran como aplicadas para que migrar.php no las repita.
$registro = $pdo->prepare('INSERT IGNORE INTO migraciones (archivo) VALUES (?)');
foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $m) {
    $registro->execute([basename($m)]);
}
otorgarPrivilegiosDemo($admin);

// Pagos simulados de una demo anterior: solo la carpeta de esta base.
$sim = BASE_PATH . '/storage/simulador/' . BASE_DEMO;
foreach (glob($sim . '/SIM-*.json') ?: [] as $f) {
    unlink($f);
}

crearCarpeta($carpeta, $tolerancia);
// Escenarios de la demostración: proceso aparte con la configuración de la DEMO (pasarela simulada, tolerancia
// acelerada) y los servicios reales. Ver scripts/demo-escenarios.php.
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/demo-escenarios.php'), $codigo);
if ($codigo !== 0) {
    echo "  · Los escenarios no se completaron (código {$codigo}). La demo funciona, pero sin datos preparados.\n";
}
echo "\nListo. Abre http://localhost/" . basename($carpeta) . "/  · usuarios de seed.sql (por ejemplo estudiante1, caja, admin) · contraseña demo2026*\n";

// ------------------------------------------------------------------------------------------

function conectarDemo(): PDO
{
    return Database::connect(['host' => Env::get('DB_HOST', '127.0.0.1'), 'port' => Env::get('DB_PORT', '3306'), 'name' => BASE_DEMO,
        'user' => Env::get('DB_ADMIN_USER', 'root'), 'pass' => Env::get('DB_ADMIN_PASS', '')]);
}

/** Los mismos privilegios mínimos que scripts/migrar.php, sobre la base de la demo. */
function otorgarPrivilegiosDemo(PDO $admin): void
{
    $usuario = (string) Env::get('DB_USER', 'unipark_app');
    if (preg_match('/^[A-Za-z0-9_]{1,32}$/', $usuario) !== 1 || $usuario === Env::get('DB_ADMIN_USER', 'root')) {
        echo "  · Cuenta de la aplicación igual a la administrativa: no se ajustan privilegios.\n";
        return;
    }
    $origen = in_array(Env::get('DB_HOST', '127.0.0.1'), ['127.0.0.1', 'localhost', '::1'], true) ? 'localhost' : '%';
    $tablas = $admin->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '" . BASE_DEMO . "'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tablas as $t) {
        $p = in_array($t, ['auditoria', 'movimientos_saldo', 'migraciones', 'importaciones', 'notificaciones_pasarela'], true) ? 'SELECT, INSERT' : 'SELECT, INSERT, UPDATE';
        $admin->exec("GRANT {$p} ON `" . BASE_DEMO . "`.`{$t}` TO '{$usuario}'@'{$origen}'");
    }
    echo '  ✓ Privilegios mínimos (sin DELETE) en ' . count($tablas) . " tablas\n";
}

/** Carpeta htdocs/UniParkDemo: punto de entrada que reutiliza el código de UniPark con la configuración de la demo. */
function crearCarpeta(string $carpeta, int $tolerancia): void
{
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }
    $principal = basename(BASE_PATH);
    file_put_contents($carpeta . '/index.php', "<?php\ndeclare(strict_types=1);\n\n"
        . "// Copia de DEMOSTRACIÓN de UniPark (generada por scripts/demo.php). Mismo código, base y sesión propias, pagos simulados.\n"
        . "define('UNIPARK_ENV_INSTANCIA', __DIR__ . '/config-demo.php');\n"
        . 'require ' . var_export(BASE_PATH . DIRECTORY_SEPARATOR . 'index.php', true) . ";\n");
    file_put_contents($carpeta . '/.htaccess', "# Copia de demostración de UniPark (generada por scripts/demo.php).\n"
        . "Options -Indexes\nDirectoryIndex index.php\n\n"
        . "<Files \"config-demo.php\">\n    Require all denied\n</Files>\n\n"
        . "<IfModule mod_rewrite.c>\n    RewriteEngine On\n"
        . "    # Hojas de estilo, scripts e imágenes: los del sistema principal.\n"
        . "    RewriteRule ^(assets/.*|sw\\.js|manifest\\.webmanifest)$ /{$principal}/\$1 [L]\n"
        . "    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^ index.php [QSA,L]\n"
        . "</IfModule>\n");
    // Los secretos del simulador se conservan entre reinicios (no son credenciales de ningún servicio externo).
    $config = $carpeta . '/config-demo.php';
    $previa = is_file($config) ? (require $config) : [];
    $valores = [
        'INSTANCIA'                    => 'demo',
        'APP_ENV'                      => 'local',
        'APP_DEBUG'                    => '0',
        'DB_NAME'                      => BASE_DEMO,
        'SESSION_NAME'                 => 'UNIPARK_DEMO',
        'PASARELA_PROVEEDOR'           => 'SIMULADO',
        'TOLERANCIA_MINUTOS'           => (string) $tolerancia,
        'SIMULADOR_SECRETO_INTEGRIDAD' => $previa['SIMULADOR_SECRETO_INTEGRIDAD'] ?? bin2hex(random_bytes(24)),
        'SIMULADOR_SECRETO_EVENTOS'    => $previa['SIMULADOR_SECRETO_EVENTOS'] ?? bin2hex(random_bytes(24)),
        'CRON_TOKEN'                   => '',
        'APP_URL'                      => '',
    ];
    // Si ya se configuró Wompi sandbox en la demo, el reinicio lo conserva (proveedor y sus llaves de pruebas).
    $conservar = ['NGROK_DOMINIO', 'PASARELA_PROVEEDOR', 'WOMPI_ENTORNO', 'WOMPI_LLAVE_PUBLICA', 'WOMPI_LLAVE_PRIVADA', 'WOMPI_SECRETO_EVENTOS',
                  'WOMPI_SECRETO_INTEGRIDAD', 'APP_URL'];
    $valores = array_intersect_key(is_array($previa) ? $previa : [], array_flip($conservar)) + $valores;
    file_put_contents($config, "<?php\n// Configuración de la copia de DEMOSTRACIÓN (generada por scripts/demo.php). Sin credenciales reales.\nreturn "
        . var_export($valores, true) . ";\n", LOCK_EX);
    echo "  ✓ Carpeta {$carpeta} (index.php, .htaccess, config-demo.php)\n";
    echo "  ✓ Tolerancia de la demo: {$tolerancia} min" . ($tolerancia < 15 ? ' (tiempo acelerado; el sistema real sigue en 15)' : '') . "\n";
}
