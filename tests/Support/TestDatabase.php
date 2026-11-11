<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Helpers\Database;
use App\Helpers\Env;
use App\Helpers\SqlFile;
use PDO;

/** Recrea la base de pruebas desde database/schema.sql, base.sql y seed.sql. */
final class TestDatabase
{
    private static ?PDO $admin = null;

    public static function nombre(): string
    {
        return Env::get('TEST_DB_NAME', 'unipark_test');
    }

    public static function admin(): PDO
    {
        if (self::$admin === null) {
            self::$admin = Database::connect([
                'host' => Env::get('DB_HOST', '127.0.0.1'),
                'port' => Env::get('DB_PORT', '3306'),
                'name' => self::nombre(),
                'user' => Env::get('DB_ADMIN_USER', 'root'),
                'pass' => Env::get('DB_ADMIN_PASS', ''),
            ]);
        }
        return self::$admin;
    }

    private static bool $privilegios = false;

    public static function reset(): void
    {
        SqlFile::run(self::admin(), BASE_PATH . '/database/schema.sql');
        SqlFile::run(self::admin(), BASE_PATH . '/database/base.sql');
        SqlFile::run(self::admin(), BASE_PATH . '/database/seed.sql');
        self::otorgarPrivilegios();
    }

    /**
     * Privilegios mínimos de la cuenta de la aplicación sobre las tablas de la BASE DE PRUEBAS (los mismos
     * que aplica scripts/migrar.php): así una tabla nueva del esquema queda accesible sin reinstalar nada.
     * Se hace una vez por ejecución; nunca toca la base real.
     */
    private static function otorgarPrivilegios(): void
    {
        $usuario = Env::get('DB_USER', 'unipark_app');
        if (self::$privilegios || preg_match('/^[A-Za-z0-9_]{1,32}$/', $usuario) !== 1 || $usuario === Env::get('DB_ADMIN_USER', 'root')) {
            return;
        }
        // tests/run.php ya garantiza que TEST_DB_NAME es distinta de la base real.
        $base = self::nombre();
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $base) !== 1) {
            return;
        }
        $origen = in_array(Env::get('DB_HOST', '127.0.0.1'), ['127.0.0.1', 'localhost', '::1'], true) ? 'localhost' : '%';
        $tablas = self::admin()->query('SELECT table_name FROM information_schema.tables WHERE table_schema = ' . self::admin()->quote($base))
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tablas as $tabla) {
            $privilegios = in_array($tabla, ['auditoria', 'movimientos_saldo', 'migraciones', 'importaciones', 'notificaciones_pasarela'], true)
                ? 'SELECT, INSERT' : 'SELECT, INSERT, UPDATE';
            self::admin()->exec("GRANT {$privilegios} ON `{$base}`.`{$tabla}` TO '{$usuario}'@'{$origen}'");
        }
        self::$privilegios = true;
    }
}
