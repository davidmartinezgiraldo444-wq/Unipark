<?php
declare(strict_types=1);

use App\Helpers\Env;

// Parámetros generales, sin secretos. Los valores sensibles vienen de .env.
return [
    'nombre'  => 'UniPark',
    'entorno' => Env::get('APP_ENV', 'local'),
    'debug'   => Env::get('APP_DEBUG', '0') === '1',

    // RNF-08: hora del servidor, zona horaria única en PHP y MySQL.
    'zona_horaria' => 'America/Bogota',
    'offset_mysql' => '-05:00',

    // RN-14: expiración por inactividad.
    'sesion_inactividad' => (int) Env::get('SESSION_IDLE_MINUTES', '15') * 60,

    // Limitar intentos repetidos de inicio de sesión (sección 10).
    'login_max_intentos' => 5,
    'login_ventana'      => 300,

    // Política de contraseñas.
    'clave_min_longitud' => 10,

    // Decisiones pendientes de la universidad (sección 15). Ajustar al confirmarlas.
    'medios_pago' => [
        'EFECTIVO'      => 'Efectivo',
        'TRANSFERENCIA' => 'Transferencia',
        'DATAFONO'      => 'Datáfono',
    ],
    'permitir_tarifa_cero' => false,
    'moneda'               => 'COP',
    'motivo_min_longitud'  => 10,
];
