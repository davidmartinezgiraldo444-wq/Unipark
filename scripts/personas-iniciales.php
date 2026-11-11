<?php
declare(strict_types=1);

/**
 * UniPark · Personas y cuentas iniciales de la base del sistema (después de una instalación limpia).
 *
 *   php scripts/personas-iniciales.php
 *
 * Crea (si no existen) las tres personas de los carnés impresos, con el MISMO documento que leen sus códigos de
 * barras, y una cuenta de estudiante para cada una. Las contraseñas se piden en la consola: no quedan escritas en
 * ningún archivo. Es idempotente: si la persona o la cuenta ya existen, no se duplican.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\EstudianteRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditService;
use App\Services\ServicioUsuario;

$personas = [
    ['documento' => '1000000010', 'nombres' => 'Emiliano',  'apellidos' => 'Castaño Salazar', 'login' => 'usuario1'],
    ['documento' => '1000000004', 'nombres' => 'Juan José', 'apellidos' => 'Arango Vélez',    'login' => 'usuario2'],
    ['documento' => '1000000008', 'nombres' => 'Tomás',     'apellidos' => 'Cardona Ospina',  'login' => 'usuario3'],
];

$pdo = Database::connection();
$repo = new EstudianteRepository($pdo);
$usuarios = new UsuarioRepository($pdo);
$audit = new AuditService($pdo);
$rolEstudiante = (int) $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'Estudiante'")->fetchColumn();
if ($rolEstudiante === 0) {
    fwrite(STDERR, "No existe el rol Estudiante: instala primero la base (scripts/install.php).\n");
    exit(1);
}

echo "UniPark · personas y cuentas iniciales (base " . App\Helpers\Env::get('DB_NAME') . ")\n\n";
foreach ($personas as $p) {
    $persona = $repo->findByCodigo($p['documento']);
    if ($persona === null) {
        $id = $repo->create($p + ['codigo_carnet' => null, 'correo' => null, 'telefono' => null, 'categoria' => 'ESTUDIANTE']);
        $audit->log('ESTUDIANTE_CREADO', 'estudiantes', $id, 'EXITO', 'Persona inicial (carné impreso)', ['documento' => '••••' . substr($p['documento'], -4)]);
        echo "  ✓ Persona {$p['nombres']} {$p['apellidos']} (documento {$p['documento']})\n";
    } else {
        echo "  · Persona {$p['nombres']} {$p['apellidos']} ya existía\n";
    }

    $existente = $pdo->query('SELECT id_usuario FROM usuarios WHERE login = ' . $pdo->quote($p['login']))->fetchColumn();
    if ($existente !== false) {
        echo "    La cuenta {$p['login']} ya existe. Nueva contraseña (Enter para dejarla igual): ";
        $clave = leerClave();
        if ($clave === '') {
            echo "  · Cuenta {$p['login']} sin cambios\n\n";
        } else {
            $usuarios->setPassword((int) $existente, password_hash($clave, PASSWORD_DEFAULT), false);
            $audit->log('CLAVE_RESTABLECIDA', 'usuarios', (int) $existente, 'EXITO', 'Contraseña inicial fijada desde la consola');
            echo "  ✓ Contraseña de {$p['login']} actualizada\n\n";
        }
        continue;
    }
    // Una persona tiene una sola cuenta: si ya tiene otra, se informa y no se crea.
    $otra = $pdo->query('SELECT u.login FROM usuarios u JOIN estudiantes e ON e.id_estudiante = u.id_estudiante WHERE e.documento = ' . $pdo->quote($p['documento']))->fetchColumn();
    if ($otra !== false) {
        echo "  · {$p['nombres']} ya tiene la cuenta «{$otra}»: no se crea {$p['login']}\n\n";
        continue;
    }
    echo "    Contraseña para {$p['login']} ({$p['nombres']}): ";
    $clave = leerClave();
    if ($clave === '') {
        echo "    (vacía: no se creó la cuenta)\n\n";
        continue;
    }
    $cuenta = (new ServicioUsuario($pdo))->crear($p['nombres'] . ' ' . $p['apellidos'], $p['login'], $rolEstudiante, $p['documento']);
    $usuarios->setPassword($cuenta['id'], password_hash($clave, PASSWORD_DEFAULT), false);
    echo "  ✓ Cuenta {$p['login']} creada" . (mb_strlen($clave) < (int) config('clave_min_longitud') ? ' (aviso: la contraseña es más corta que el mínimo recomendado de ' . config('clave_min_longitud') . ' caracteres)' : '') . "\n\n";
}
echo "Listo.\n";

/** Una línea de la consola, sin espacios ni caracteres invisibles (BOM, retorno de carro). */
function leerClave(): string
{
    $linea = (string) fgets(STDIN);
    return trim(str_replace("\u{FEFF}", '', $linea), " \t\r\n\0\x0B");
}
