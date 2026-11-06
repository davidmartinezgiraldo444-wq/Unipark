<?php
declare(strict_types=1);

use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CajaController;
use App\Controllers\DashboardController;
use App\Controllers\EntradaController;
use App\Controllers\EstudianteController;
use App\Controllers\ReporteController;
use App\Controllers\SalidaController;
use App\Controllers\TarifaController;
use App\Controllers\UsuarioController;

// [método, ruta, [controlador, acción], 'perm' => permisos (basta uno), 'auth' => false si es pública]
// Toda ruta exige sesión salvo que diga lo contrario; todo POST exige token CSRF.
return [
    // La raíz abre el acceso (o el panel si ya hay sesión); la presentación pública queda en /inicio.
    ['GET',  '/',              [DashboardController::class, 'raiz'],    'auth' => false],
    ['GET',  '/inicio',        [DashboardController::class, 'landing'], 'auth' => false],
    ['GET',  '/login',         [AuthController::class, 'form'],         'auth' => false],
    ['POST', '/login',         [AuthController::class, 'login'],        'auth' => false],
    ['POST', '/logout',        [AuthController::class, 'logout']],
    ['GET',  '/cambiar-clave', [AuthController::class, 'formClave']],
    ['POST', '/cambiar-clave', [AuthController::class, 'cambiarClave']],

    ['GET',  '/panel',         [DashboardController::class, 'index']],

    // Portería
    ['GET',  '/porteria',          [EntradaController::class, 'index'],     'perm' => ['ENTRADA_CREAR', 'SALIDA_AUTORIZAR']],
    ['POST', '/porteria/entrada',  [EntradaController::class, 'registrar'], 'perm' => ['ENTRADA_CREAR']],
    ['POST', '/porteria/salida',   [SalidaController::class, 'autorizar'],  'perm' => ['SALIDA_AUTORIZAR']],
    ['POST', '/visitas/{id}/anular', [EntradaController::class, 'anular'],  'perm' => ['VISITA_ANULAR']],

    // Caja
    ['GET',  '/caja',              [CajaController::class, 'index'],        'perm' => ['PAGO_REGISTRAR']],
    ['POST', '/caja/pago',         [CajaController::class, 'pagar'],        'perm' => ['PAGO_REGISTRAR']],
    ['GET',  '/caja/recibo/{id}',  [CajaController::class, 'recibo'],       'perm' => ['PAGO_REGISTRAR', 'REPORTE_CAJA']],
    ['GET',  '/caja/cierre',       [CajaController::class, 'cierre'],       'perm' => ['CAJA_CIERRE']],
    ['POST', '/caja/cierre',       [CajaController::class, 'cerrarCaja'],   'perm' => ['CAJA_CIERRE']],
    ['POST', '/pagos/{id}/anular', [CajaController::class, 'anular'],       'perm' => ['PAGO_ANULAR']],

    // Estudiantes
    ['GET',  '/estudiantes',              [EstudianteController::class, 'index'],     'perm' => ['ESTUDIANTE_VER']],
    ['GET',  '/estudiantes/nuevo',        [EstudianteController::class, 'nuevo'],     'perm' => ['ESTUDIANTE_GESTIONAR']],
    ['POST', '/estudiantes',              [EstudianteController::class, 'crear'],     'perm' => ['ESTUDIANTE_GESTIONAR']],
    ['GET',  '/estudiantes/{id}',         [EstudianteController::class, 'historial'], 'perm' => ['VISITA_VER']],
    ['GET',  '/estudiantes/{id}/editar',  [EstudianteController::class, 'editar'],    'perm' => ['ESTUDIANTE_GESTIONAR']],
    ['POST', '/estudiantes/{id}',         [EstudianteController::class, 'actualizar'], 'perm' => ['ESTUDIANTE_GESTIONAR']],
    ['POST', '/estudiantes/{id}/estado',  [EstudianteController::class, 'estado'],    'perm' => ['ESTUDIANTE_GESTIONAR']],

    // Usuarios
    ['GET',  '/usuarios',             [UsuarioController::class, 'index'],      'perm' => ['USUARIO_GESTIONAR']],
    ['GET',  '/usuarios/nuevo',       [UsuarioController::class, 'nuevo'],      'perm' => ['USUARIO_GESTIONAR']],
    ['POST', '/usuarios',             [UsuarioController::class, 'crear'],      'perm' => ['USUARIO_GESTIONAR']],
    ['GET',  '/usuarios/{id}/editar', [UsuarioController::class, 'editar'],     'perm' => ['USUARIO_GESTIONAR']],
    ['POST', '/usuarios/{id}',        [UsuarioController::class, 'actualizar'], 'perm' => ['USUARIO_GESTIONAR']],
    ['POST', '/usuarios/{id}/estado', [UsuarioController::class, 'estado'],     'perm' => ['USUARIO_GESTIONAR']],
    ['POST', '/usuarios/{id}/clave',  [UsuarioController::class, 'clave'],      'perm' => ['USUARIO_GESTIONAR']],

    // Tarifas
    ['GET',  '/tarifas', [TarifaController::class, 'index'],   'perm' => ['TARIFA_GESTIONAR']],
    ['POST', '/tarifas', [TarifaController::class, 'cambiar'], 'perm' => ['TARIFA_GESTIONAR']],

    // Reportes y auditoría
    ['GET',  '/reportes',          [ReporteController::class, 'index'],    'perm' => ['REPORTE_OPERATIVO', 'REPORTE_VER', 'REPORTE_CAJA']],
    ['GET',  '/reportes/exportar', [ReporteController::class, 'exportar'], 'perm' => ['REPORTE_EXPORTAR']],
    ['GET',  '/auditoria',         [AuditoriaController::class, 'index'],  'perm' => ['AUDITORIA_VER']],
];
