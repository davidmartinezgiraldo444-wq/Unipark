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

    public static function reset(): void
    {
        SqlFile::run(self::admin(), BASE_PATH . '/database/schema.sql');
        SqlFile::run(self::admin(), BASE_PATH . '/database/base.sql');
        SqlFile::run(self::admin(), BASE_PATH . '/database/seed.sql');
    }
}
