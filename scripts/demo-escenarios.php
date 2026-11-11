<?php
declare(strict_types=1);

/**
 * UniPark · Escenarios de la copia de DEMOSTRACIÓN.
 *
 *   php scripts/demo-escenarios.php      (lo ejecuta scripts/demo.php --crear|--reiniciar; se puede repetir tras reiniciar)
 *
 * Corre con la configuración de la DEMO (htdocs/UniParkDemo/config-demo.php): base unipark_demo, pasarela SIMULADA,
 * tolerancia acelerada. Así los avisos y las recargas salen exactamente como en la demo (hallazgo B9).
 * Todo se genera con los SERVICIOS reales (entrada, cobro automático, recargas, webhook firmado, salida), con
 * auditoría; nunca con inserciones a mano. Se niega a correr sobre cualquier base que no sea la de la demo.
 *
 * Escenarios que deja listos:
 *   1. Visitas LEGADO del seed cerradas (ya no confunden en Portería ni en los reportes).
 *   2. Saldo suficiente  · Valentina (estudiante1): entró hace 20 min, el cobro del día se pagó solo con su saldo
 *                          → su salida queda AUTORIZADA.
 *   3. Saldo insuficiente · Santiago (estudiante2, $0): entró hace 20 min, el cobro quedó PENDIENTE
 *                          → su salida queda BLOQUEADA hasta que recargue o pague en Caja.
 *   4. Recargas en línea de Valentina: una APROBADA (acreditada por el webhook), una RECHAZADA y una PENDIENTE
 *      (se aprueba o rechaza en vivo desde el panel del simulador, como Administrador).
 * Escenarios que se hacen EN VIVO (no se pueden dejar preparados): visitante sin registro (documento 99887766),
 * clasificación de baja confianza (el portero elige el tipo), y la ruta completa de Mariana (estudiante3).
 */

$config = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'UniParkDemo' . DIRECTORY_SEPARATOR . 'config-demo.php';
if (!is_file($config)) {
    fwrite(STDERR, "La demo no está creada: ejecuta php scripts/demo.php --crear\n");
    exit(1);
}
define('UNIPARK_ENV_INSTANCIA', $config);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Services\Pasarela\PasarelaSimulada;
use App\Services\Pasarela\Pasarelas;
use App\Services\Pasarela\PasarelaWompi;
use App\Services\ServicioCobro;
use App\Services\ServicioEntrada;
use App\Services\ServicioRecargaLinea;
use App\Services\ServicioSalida;

if (config('instancia') !== 'demo' || App\Helpers\Env::get('DB_NAME', '') !== 'unipark_demo') {
    fwrite(STDERR, "Estos escenarios solo se crean en la base de la demo (unipark_demo).\n");
    exit(1);
}
$pdo = Database::connection();
$id = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$admin = $id("SELECT id_usuario FROM usuarios WHERE login = 'admin'");
$porteria = $id("SELECT id_usuario FROM usuarios WHERE login = 'porteria'");
$valentina = $id("SELECT id_estudiante FROM estudiantes WHERE documento = '1000000001'");
$santiago = $id("SELECT id_estudiante FROM estudiantes WHERE documento = '1000000002'");
$usuarioValentina = $id("SELECT id_usuario FROM usuarios WHERE login = 'estudiante1'");
if (in_array(0, [$admin, $porteria, $valentina, $santiago, $usuarioValentina], true)) {
    fwrite(STDERR, "Faltan los usuarios de demostración del seed.\n");
    exit(1);
}
$saldo = static fn (int $persona): string => money((string) $pdo->query('SELECT COALESCE(saldo, 0) FROM estudiantes WHERE id_estudiante = ' . $persona)->fetchColumn());

// 1. Visitas del esquema anterior (LEGADO) que el seed deja abiertas: la pagada sale; las demás se anulan con motivo.
$cerradas = 0;
foreach ($pdo->query("SELECT id_visita FROM visitas WHERE estado = 'EN_CURSO' AND liquidacion = 'LEGADO'")->fetchAll(PDO::FETCH_COLUMN) as $v) {
    try {
        if ((new App\Repositories\PagoRepository($pdo))->validoDeVisita((int) $v) !== null) {
            (new ServicioSalida($pdo))->autorizar((int) $v, $porteria);
        } else {
            (new ServicioEntrada($pdo))->anular((int) $v, $admin, 'Visita del esquema anterior cerrada para la demostración.');
        }
        $cerradas++;
    } catch (Throwable $e) {
        echo "  · Visita LEGADO {$v}: " . $e->getMessage() . "\n";
    }
}
echo "  ✓ Visitas LEGADO abiertas cerradas: {$cerradas}\n";

// Cuentas de «Mi cuenta» para las personas de los carnés impresos (Juan José 1000000004, Tomás 1000000008 y
// Emiliano 1000000010): misma contraseña que el resto de la demo. Solo en la demo; el sistema real no cambia.
$rolEstudiante = $id("SELECT id_rol FROM usuarios WHERE login = 'estudiante1'");
$claveDemo = (string) $pdo->query("SELECT password_hash FROM usuarios WHERE login = 'estudiante1'")->fetchColumn();
foreach (['1000000004' => 'estudiante4', '1000000008' => 'estudiante8', '1000000010' => 'estudiante10'] as $documento => $login) {
    $persona = $pdo->prepare('SELECT nombres, apellidos FROM estudiantes WHERE documento = ?');
    $persona->execute([(string) $documento]);
    $p = $persona->fetch(PDO::FETCH_ASSOC);
    if (!$p || $id("SELECT COUNT(*) FROM usuarios WHERE login = '{$login}'") > 0) {
        continue;
    }
    $cuenta = (new App\Services\ServicioUsuario($pdo))->crear($p['nombres'] . ' ' . $p['apellidos'], $login, $rolEstudiante, (string) $documento);
    $pdo->prepare('UPDATE usuarios SET password_hash = ?, debe_cambiar_clave = 0 WHERE id_usuario = ?')->execute([$claveDemo, $cuenta['id']]);
    echo "  ✓ Cuenta {$login} para {$p['nombres']} {$p['apellidos']} (documento {$documento})\n";
}

// 2 y 3. Entradas de hace 20 minutos y cobro automático (moviendo solo el reloj de ESTA conexión).
$pdo->exec('SET timestamp = UNIX_TIMESTAMP(NOW() - INTERVAL 20 MINUTE)');
try {
    (new ServicioEntrada($pdo))->registrar($valentina, 'CARRO', $porteria);
    (new ServicioEntrada($pdo))->registrar($santiago, 'CARRO', $porteria);
} finally {
    $pdo->exec('SET timestamp = DEFAULT');
}
$r = (new ServicioCobro($pdo))->procesarVencidos();
echo "  ✓ Saldo suficiente: Valentina (estudiante1) con el cobro pagado de su saldo → salida autorizada (saldo {$saldo($valentina)})\n";
echo "  ✓ Saldo insuficiente: Santiago (estudiante2) con el cobro pendiente → salida bloqueada (cobros: {$r['debitados']} debitado, {$r['pendientes']} pendiente)\n";

// 4. Recargas en línea de Valentina con la pasarela SIMULADA: el mismo camino que el checkout de la demo
//    (transacción → regreso del navegador que NO acredita → webhook firmado que sí acredita, una sola vez).
$sim = Pasarelas::activa();
if (!$sim instanceof PasarelaSimulada) {
    echo "  · Recargas omitidas: la demo no usa la pasarela simulada.\n";
    exit(0);
}
$recargas = new ServicioRecargaLinea($pdo);
foreach ([['10000', 'APPROVED', 'aprobada'], ['15000', 'DECLINED', 'rechazada'], ['20000', 'PENDING', 'pendiente']] as [$monto, $estado, $texto]) {
    $recarga = $recargas->crear($valentina, $usuarioValentina, $monto);
    $tx = $sim->crearTransaccion($recarga['referencia'], PasarelaWompi::centavos($recarga['monto']), $estado);
    $recargas->reportarRetornoPorReferencia($recarga['referencia'], $tx);
    [$cuerpo, $cabeceras] = $sim->evento($tx);
    $res = $recargas->procesarWebhook($cuerpo, $cabeceras);
    echo "  ✓ Recarga {$texto} de " . money($monto) . " ({$recarga['referencia']} · {$tx} · {$res['resultado']})\n";
}
echo "  ✓ Saldo final de Valentina: {$saldo($valentina)}\n";
