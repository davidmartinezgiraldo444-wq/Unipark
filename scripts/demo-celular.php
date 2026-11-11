<?php
declare(strict_types=1);

/**
 * UniPark · Preparar la demo para el celular (túnel HTTPS). Lo ejecuta scripts/demo-celular.bat.
 *
 *   php scripts/demo-celular.php            Verifica el sitio 127.0.0.1:8090 y pone una contraseña NUEVA y aleatoria
 *                                           a todos los usuarios de la demo (la muestra en esta consola)
 *   php scripts/demo-celular.php --verificar  Solo verifica el sitio (no cambia contraseñas)
 *
 * Por qué: con el túnel abierto la demo es alcanzable desde internet, y la contraseña conocida de la demo
 * (demo2026*) no debe servir allí. `php scripts/demo.php --reiniciar` la devuelve a demo2026*.
 * Solo opera sobre la base de la demo (unipark_demo); nunca sobre el sistema real.
 */

$config = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'UniParkDemo' . DIRECTORY_SEPARATOR . 'config-demo.php';
if (!is_file($config)) {
    fwrite(STDERR, "La demo no está creada: ejecuta php scripts/demo.php --crear\n");
    exit(1);
}
define('UNIPARK_ENV_INSTANCIA', $config);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Env;
use App\Services\AuditService;

// Dominio FIJO de ngrok (para el webhook de Wompi sandbox): se guarda en config-demo.php (NGROK_DOMINIO) y
// demo.php --reiniciar lo conserva. --fijar-dominio=xxx.ngrok-free.app lo guarda; --dominio lo imprime.
$fijar = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--fijar-dominio=')) {
        $fijar = strtolower(trim(preg_replace('#^https?://#', '', substr($a, 16)), " /"));
    }
}
if ($fijar !== null) {
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,62}\.(ngrok-free\.app|ngrok-free\.dev|ngrok\.app|ngrok\.dev|ngrok\.pizza|ngrok\.io)$/', $fijar) !== 1) {
        fwrite(STDERR, "  Ese no parece un dominio de ngrok (por ejemplo: palabra-palabra-1234.ngrok-free.app).\n");
        exit(1);
    }
    $valores = require $config;
    $valores['NGROK_DOMINIO'] = $fijar;
    file_put_contents($config, "<?php\n// Configuración de la copia de DEMOSTRACIÓN (generada por scripts/demo.php). Sin credenciales reales.\nreturn "
        . var_export($valores, true) . ";\n", LOCK_EX);
    echo "  ✓ Dominio fijo guardado: {$fijar}\n";
    exit(0);
}
if (in_array('--dominio', $argv, true)) {
    $valores = require $config;
    $d = (string) ($valores['NGROK_DOMINIO'] ?? '');
    if ($d === '') {
        exit(1);
    }
    echo $d;
    exit(0);
}

// --direccion: espera a que el túnel (cloudflared con --metrics 127.0.0.1:20241) tenga su dirección y la muestra
// completa, lista para el celular. La dirección cambia cada vez que se abre el túnel.
if (in_array('--direccion', $argv, true)) {
    for ($i = 0; $i < 40; $i++) {
        $c = curl_init('http://127.0.0.1:20241/quicktunnel');
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
        $host = (string) (json_decode((string) curl_exec($c), true)['hostname'] ?? '');
        curl_close($c);
        if (preg_match('/^[a-z0-9-]+\.trycloudflare\.com$/', $host) === 1) {
            $linea = str_repeat('=', 78);
            echo "\n {$linea}\n  ABRE ESTO EN EL CELULAR (usuario acceso1):\n\n"
                . "     https://{$host}/UniParkDemo/porteria/escaner\n\n"
                . "  Esta dirección es SOLO de esta vez: al cerrar el túnel deja de existir.\n {$linea}\n\n";
            exit(0);
        }
        sleep(1);
    }
    fwrite(STDERR, "\n  No se obtuvo la dirección del túnel. Búscala en las líneas de arriba (https://....trycloudflare.com).\n");
    exit(1);
}

if (config('instancia') !== 'demo' || Env::get('DB_NAME', '') !== 'unipark_demo') {
    fwrite(STDERR, "Este script solo opera sobre la demo (unipark_demo).\n");
    exit(1);
}

// 1. El sitio solo-demo del puerto 8090 (scripts/apache/unipark-demo-celular.conf) responde y no expone el sistema real.
$estado = static function (string $ruta): int {
    $c = curl_init('http://127.0.0.1:8090' . $ruta);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($c);
    $codigo = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    curl_close($c);
    return $codigo;
};
$pruebas = [
    ['/UniParkDemo/inicio', 200, 'la demo'],
    ['/UniPark/inicio', 403, 'el sistema real (bloqueado)'],
    ['/phpmyadmin/', 403, 'phpMyAdmin (bloqueado)'],
    ['/dashboard/', 403, 'el panel de XAMPP (bloqueado)'],
];
$ok = true;
foreach ($pruebas as [$ruta, $esperado, $texto]) {
    $codigo = $estado($ruta);
    $bien = $codigo === $esperado;
    $ok = $ok && $bien;
    echo sprintf('  %s %-34s %s → %d', $bien ? '✓' : '✗', $texto, $ruta, $codigo) . ($bien ? '' : " (se esperaba {$esperado})") . PHP_EOL;
}
if (!$ok) {
    fwrite(STDERR, "\nEl sitio 127.0.0.1:8090 no está listo. Reinicia Apache en el Panel de XAMPP (Stop y Start) y vuelve a intentar.\n"
        . "Debe estar incluido en httpd.conf: Include \"C:/xampp/htdocs/UniPark/scripts/apache/unipark-demo-celular.conf\"\n");
    exit(2);
}
if (in_array('--verificar', $argv, true)) {
    exit(0);
}

// 2. Contraseña nueva para TODOS los usuarios de la demo (una sola, para escribirla fácil en el celular).
$letras = 'abcdefghjkmnpqrstuvwxyz23456789';
$grupo = static function () use ($letras): string {
    $g = '';
    for ($i = 0; $i < 4; $i++) {
        $g .= $letras[random_int(0, strlen($letras) - 1)];
    }
    return $g;
};
$clave = 'Upk-' . $grupo() . '-' . $grupo() . '-' . $grupo();
$pdo = Database::connection();
$hash = password_hash($clave, PASSWORD_DEFAULT);
$n = $pdo->prepare("UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = 0 WHERE activo = 1");
$n->execute([$hash]);
(new AuditService($pdo))->log('DEMO_CLAVES_TUNEL', 'usuarios', null, 'EXITO', 'Contraseñas de la demo cambiadas para abrir el túnel del celular',
    ['usuarios' => $n->rowCount()]);

echo "\n  Contraseña de esta sesión del túnel para TODOS los usuarios de la demo ({$n->rowCount()}):\n\n      {$clave}\n\n"
    . "  (demo2026* ya no sirve hasta que ejecutes: php scripts/demo.php --reiniciar)\n";
