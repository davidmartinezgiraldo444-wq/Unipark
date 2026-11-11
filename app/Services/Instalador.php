<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\SqlFile;
use PDO;
use PDOException;

/**
 * Instalación de la base de datos de UniPark. La usan el formulario web (instalar.php)
 * y la consola (scripts/install.php).
 *
 * Pasos: conectar con la cuenta indicada → crear la base → aplicar schema.sql y base.sql
 * (y seed.sql si se piden datos de demostración) → crear la cuenta de la aplicación con
 * privilegios mínimos → comprobar que la aplicación puede conectarse.
 */
final class Instalador
{
    public const ARCHIVO_BLOQUEO = '/storage/installed.lock';
    public const ARCHIVO_CONFIG = '/config/env.php';

    /** @var list<string> */
    private array $pasos = [];

    public static function instalado(): bool
    {
        return is_file(BASE_PATH . self::ARCHIVO_BLOQUEO);
    }

    /** @return list<array{nombre:string,ok:bool,detalle:string}> */
    public static function requisitos(): array
    {
        $escribible = static fn (string $dir) => is_dir(BASE_PATH . $dir) && is_writable(BASE_PATH . $dir);
        return [
            ['nombre' => 'PHP 8.1 o superior', 'ok' => PHP_VERSION_ID >= 80100, 'detalle' => 'Versión ' . PHP_VERSION],
            ['nombre' => 'Extensión pdo_mysql', 'ok' => extension_loaded('pdo_mysql'), 'detalle' => 'Conexión con MySQL/MariaDB'],
            ['nombre' => 'Extensión mbstring', 'ok' => extension_loaded('mbstring'), 'detalle' => 'Textos con tildes y ñ'],
            ['nombre' => 'Carpeta config/ escribible', 'ok' => $escribible('/config'), 'detalle' => 'Para guardar la conexión'],
            ['nombre' => 'Carpeta storage/ escribible', 'ok' => $escribible('/storage') && $escribible('/storage/logs'), 'detalle' => 'Logs, comprobantes y respaldos'],
        ];
    }

    /**
     * @param array{
     *   host:string, port:string, base:string, usuario:string, clave:string,
     *   crear_cuenta:bool, cuenta_app:string, clave_app?:?string,
     *   demo:bool, sobrescribir:bool
     * } $d
     * @return array{pasos:list<string>, app_usuario:string, app_clave:string}
     * @throws ReglaNegocioException
     */
    public function instalar(array $d): array
    {
        $this->validar($d);

        try {
            $pdo = Database::connect([
                'host' => $d['host'], 'port' => $d['port'], 'name' => null,
                'user' => $d['usuario'], 'pass' => $d['clave'],
            ]);
        } catch (PDOException $e) {
            throw new ReglaNegocioException('CONEXION', sprintf(
                'No se pudo conectar a MySQL en %s:%s con el usuario «%s». Verifica que MySQL esté iniciado en XAMPP y que el usuario y la contraseña sean correctos. (%s)',
                $d['host'], $d['port'], $d['usuario'], self::mensajeMysql($e)
            ));
        }
        $this->paso('Conexión con MySQL como ' . $d['usuario'] . '@' . $d['host']);
        $this->verificarVersion($pdo);

        $base = $d['base'];
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // Sin permiso para crear bases: sirve si la base ya existe (hosting compartido).
        }
        try {
            $pdo->exec("USE `{$base}`");
        } catch (PDOException $e) {
            throw new ReglaNegocioException('BASE', "La base de datos «{$base}» no existe y el usuario no tiene permiso para crearla. Créala desde phpMyAdmin e inténtalo de nuevo.");
        }
        $this->paso("Base de datos «{$base}» lista (utf8mb4)");

        $tablas = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . $pdo->quote($base))->fetchColumn();
        if ($tablas > 0 && !$d['sobrescribir']) {
            throw new ReglaNegocioException('BASE_CON_DATOS', "La base «{$base}» ya tiene {$tablas} tablas. Usa otro nombre o marca «Reemplazar las tablas existentes» (se borrarán sus datos).");
        }

        try {
            $n = SqlFile::run($pdo, BASE_PATH . '/database/schema.sql');
            $this->paso("Tablas, restricciones y disparadores creados ({$n} sentencias)");
            SqlFile::run($pdo, BASE_PATH . '/database/base.sql');
            $this->paso('Roles, permisos, tarifas y cuenta del administrador');
            if ($d['demo']) {
                SqlFile::run($pdo, BASE_PATH . '/database/seed.sql');
                $this->paso('Datos de demostración: usuarios de prueba por rol (contraseña demo2026*), personas, saldos, visitas y pagos');
            }
        } catch (PDOException $e) {
            throw new ReglaNegocioException('ESQUEMA', 'No se pudo crear la estructura de la base de datos. El usuario necesita permisos CREATE, ALTER, DROP, INDEX, REFERENCES y TRIGGER. (' . self::mensajeMysql($e) . ')');
        }

        [$appUsuario, $appClave] = $d['crear_cuenta']
            ? $this->crearCuentaAplicacion($pdo, $base, $d['host'], $d['cuenta_app'], $d['clave_app'] ?? null)
            : [$d['usuario'], $d['clave']];

        // Comprobación final: la aplicación entra con su propia cuenta y ve los datos.
        try {
            $app = Database::connect(['host' => $d['host'], 'port' => $d['port'], 'name' => $base, 'user' => $appUsuario, 'pass' => $appClave]);
            $app->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        } catch (PDOException $e) {
            throw new ReglaNegocioException('VERIFICACION', 'La base quedó creada, pero la aplicación no logró conectarse con su cuenta. (' . self::mensajeMysql($e) . ')');
        }
        $this->paso('Conexión de la aplicación verificada');

        return ['pasos' => $this->pasos, 'app_usuario' => $appUsuario, 'app_clave' => $appClave];
    }

    /** Guarda la conexión en config/env.php (PHP: no se puede leer desde el navegador). */
    public function escribirConfiguracion(array $valores): void
    {
        $contenido = "<?php\n// UniPark · Configuración generada por el instalador el " . date('Y-m-d H:i') . ".\n"
            . "// Contiene credenciales: no la compartas ni la subas a un repositorio.\nreturn "
            . var_export(array_map('strval', $valores), true) . ";\n";
        if (file_put_contents(BASE_PATH . self::ARCHIVO_CONFIG, $contenido, LOCK_EX) === false) {
            throw new ReglaNegocioException('CONFIG', 'No se pudo escribir config/env.php. Revisa los permisos de la carpeta config/.');
        }
        @chmod(BASE_PATH . self::ARCHIVO_CONFIG, 0640);
    }

    /** Impide volver a ejecutar el instalador. */
    public function bloquear(): void
    {
        file_put_contents(BASE_PATH . self::ARCHIVO_BLOQUEO, 'Instalado el ' . date('c') . PHP_EOL, LOCK_EX);
    }

    /** Carpeta de mysql/mysqldump de XAMPP, si existe (para respaldos). */
    public static function detectarBinMysql(): string
    {
        foreach (['C:/xampp/mysql/bin', 'D:/xampp/mysql/bin', '/opt/lampp/bin', '/Applications/XAMPP/xamppfiles/bin'] as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }
        return '';
    }

    /** @return array{0:string,1:string} */
    private function crearCuentaAplicacion(PDO $pdo, string $base, string $host, string $usuario, ?string $clave): array
    {
        $clave ??= bin2hex(random_bytes(12));
        $origen = in_array($host, ['127.0.0.1', 'localhost', '::1'], true) ? 'localhost' : '%';
        $cuenta = "'{$usuario}'@'{$origen}'";
        try {
            $pdo->exec("CREATE USER IF NOT EXISTS {$cuenta} IDENTIFIED BY " . $pdo->quote($clave));
            $pdo->exec("ALTER USER {$cuenta} IDENTIFIED BY " . $pdo->quote($clave));
            $tablas = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = ' . $pdo->quote($base))
                ->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tablas as $tabla) {
                // Mínimo privilegio (sección 9.2): sin DELETE; la auditoría y el libro de saldos solo admiten INSERT.
                $privilegios = in_array($tabla, ['auditoria', 'movimientos_saldo', 'migraciones', 'importaciones', 'notificaciones_pasarela'], true) ? 'SELECT, INSERT' : 'SELECT, INSERT, UPDATE';
                $pdo->exec("GRANT {$privilegios} ON `{$base}`.`{$tabla}` TO {$cuenta}");
            }
            $pdo->exec('FLUSH PRIVILEGES');
        } catch (PDOException $e) {
            throw new ReglaNegocioException('CUENTA', 'No se pudo crear la cuenta de la aplicación: el usuario no tiene permiso para crear usuarios. Desmarca «Crear una cuenta de la aplicación» para usar la misma cuenta. (' . self::mensajeMysql($e) . ')');
        }
        $this->paso("Cuenta de la aplicación «{$usuario}» con privilegios mínimos (sin DELETE)");
        return [$usuario, $clave];
    }

    private function verificarVersion(PDO $pdo): void
    {
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $esMaria = stripos($version, 'mariadb') !== false;
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m);
        $numero = isset($m[1]) ? sprintf('%d.%d.%d', $m[1], $m[2], $m[3]) : '0.0.0';
        $minimo = $esMaria ? '10.4.0' : '8.0.16';
        if (version_compare($numero, $minimo, '<')) {
            throw new ReglaNegocioException('VERSION', sprintf(
                'El servidor es %s %s. UniPark requiere %s %s o superior (restricciones CHECK y columnas generadas).',
                $esMaria ? 'MariaDB' : 'MySQL', $numero, $esMaria ? 'MariaDB' : 'MySQL', $minimo
            ));
        }
        $this->paso(($esMaria ? 'MariaDB ' : 'MySQL ') . $numero);
    }

    private function validar(array $d): void
    {
        $errores = [];
        if (preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $d['host']) !== 1) {
            $errores[] = 'El servidor debe ser un nombre o dirección IP, por ejemplo 127.0.0.1.';
        }
        if (!ctype_digit($d['port']) || (int) $d['port'] < 1 || (int) $d['port'] > 65535) {
            $errores[] = 'El puerto debe ser un número entre 1 y 65535 (MySQL usa 3306).';
        }
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $d['base']) !== 1) {
            $errores[] = 'El nombre de la base solo admite letras, números y guion bajo.';
        }
        if ($d['usuario'] === '' || mb_strlen($d['usuario']) > 80 || preg_match('/[\'"`\\\\]/', $d['usuario'])) {
            $errores[] = 'Escribe un usuario de MySQL válido.';
        }
        if ($d['crear_cuenta'] && preg_match('/^[A-Za-z0-9_]{1,32}$/', $d['cuenta_app']) !== 1) {
            $errores[] = 'La cuenta de la aplicación solo admite letras, números y guion bajo (máximo 32).';
        }
        if ($d['crear_cuenta'] && $d['cuenta_app'] === $d['usuario']) {
            $errores[] = 'La cuenta de la aplicación debe ser distinta del usuario administrador.';
        }
        if ($errores) {
            throw new ReglaNegocioException('DATOS', implode(' ', $errores));
        }
    }

    private function paso(string $texto): void
    {
        $this->pasos[] = $texto;
    }

    private static function mensajeMysql(PDOException $e): string
    {
        $codigo = $e->errorInfo[1] ?? $e->getCode();
        $texto = $e->errorInfo[2] ?? $e->getMessage();
        return 'MySQL ' . $codigo . ': ' . mb_substr((string) $texto, 0, 200);
    }
}
