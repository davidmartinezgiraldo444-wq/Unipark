<?php
declare(strict_types=1);

use App\Helpers\Env;

// Credenciales leídas de variables de entorno (.env local o servidor).
// La cuenta debe tener privilegios mínimos: SELECT, INSERT y UPDATE, sin DELETE (ver scripts/install.php).
return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'name' => Env::get('DB_NAME', 'unipark_db'),
    'user' => Env::get('DB_USER', 'unipark_app'),
    'pass' => Env::get('DB_PASS', ''),
];
