<?php
declare(strict_types=1);

use App\Controllers\AccesoController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CajaController;
use App\Controllers\CobroController;
use App\Controllers\CuentaController;
use App\Controllers\DashboardController;
use App\Controllers\EntradaController;
use App\Controllers\EscanerController;
use App\Controllers\EstudianteController;
use App\Controllers\ImportacionController;
use App\Controllers\PasarelaController;
use App\Controllers\ReporteController;
use App\Controllers\SaldoController;
use App\Controllers\SimuladorController;
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

    // Portería: operaciones manuales del portero (permiten elegir el tipo; solo el rol Portería las tiene)
    // Punto de acceso (dispositivo): la persona escanea su carné y la cámara clasifica el vehículo.
    ['GET',  '/porteria/escaner',   [EscanerController::class, 'index'],       'perm' => ['ACCESO_KIOSCO']],
    ['GET',  '/porteria/camara',    [EscanerController::class, 'camara'],      'perm' => ['ACCESO_KIOSCO']],
    ['POST', '/api/acceso/identificar',          [AccesoController::class, 'identificar'],   'perm' => ['ACCESO_KIOSCO']],
    ['POST', '/api/acceso/{id}/clasificacion',   [AccesoController::class, 'clasificacion'], 'perm' => ['ACCESO_KIOSCO']],
    ['GET',  '/api/acceso/{id}',                 [AccesoController::class, 'estado'],        'perm' => ['ACCESO_KIOSCO', 'CLASIFICACION_RESOLVER']],
    // Panel del portero: avisos en tiempo real y resolución (solo CLASIFICACION_RESOLVER).
    ['GET',  '/api/porteria/eventos',              [AccesoController::class, 'pendientes'], 'perm' => ['CLASIFICACION_RESOLVER']],
    ['POST', '/api/porteria/eventos/{id}/resolver', [AccesoController::class, 'resolver'],  'perm' => ['CLASIFICACION_RESOLVER']],
    ['GET',  '/porteria/eventos/{id}/captura',     [AccesoController::class, 'captura'],    'perm' => ['CLASIFICACION_RESOLVER']],
    ['GET',  '/porteria/carnets',   [EscanerController::class, 'carnets'],     'perm' => ['ESTUDIANTE_VER']],
    ['POST', '/api/porteria/identificar', [EscanerController::class, 'identificar'], 'perm' => ['ENTRADA_CREAR', 'SALIDA_AUTORIZAR']],
    ['POST', '/api/porteria/entrada',     [EscanerController::class, 'entrada'],     'perm' => ['ENTRADA_CREAR']],
    ['POST', '/api/porteria/salida',      [EscanerController::class, 'salida'],      'perm' => ['SALIDA_AUTORIZAR']],
    // Celular de cámara: su publicación continua SÍ es actividad (antes la sesión expiraba a los 15 min).
    ['POST', '/api/camara/deteccion',     [EscanerController::class, 'deteccion'],   'perm' => ['ACCESO_KIOSCO']],
    ['GET',  '/api/camara/ultima',        [EscanerController::class, 'ultima'],      'perm' => ['ACCESO_KIOSCO', 'ENTRADA_CREAR'], 'actividad' => false],

    // Cobro diario automático: latido de las pantallas abiertas, cron por URL y anulación
    ['POST', '/api/cobros/procesar', [CobroController::class, 'procesar'], 'perm' => ['COBRO_PROCESAR'], 'actividad' => false],
    ['GET',  '/cron/cobros',         [CobroController::class, 'cron'],     'auth' => false],
    ['POST', '/cobros/{id}/anular',  [CobroController::class, 'anular'],   'perm' => ['COBRO_ANULAR']],
    // Incidencias: la tolerancia venció y el cobro automático no corrió. Lo resuelve Caja o Portería, no la salida.
    ['POST', '/cobros/visita/{id}/procesar',     [CobroController::class, 'procesarVisita'],    'perm' => ['PAGO_REGISTRAR', 'ENTRADA_CREAR']],
    ['POST', '/api/cobros/visita/{id}/procesar', [CobroController::class, 'procesarVisitaApi'], 'perm' => ['PAGO_REGISTRAR', 'ENTRADA_CREAR']],

    // Cuenta propia: estudiantes, profesores y trabajadores
    ['GET',  '/mi-cuenta',               [CuentaController::class, 'index'],        'perm' => ['CUENTA_PROPIA']],
    ['GET',  '/api/mi-cuenta/estado',    [CuentaController::class, 'estado'],       'perm' => ['CUENTA_PROPIA'], 'actividad' => false],
    ['GET',  '/api/push/clave',          [CuentaController::class, 'clavePublica'], 'perm' => ['CUENTA_PROPIA']],
    ['POST', '/api/push/suscribir',      [CuentaController::class, 'suscribir'],    'perm' => ['CUENTA_PROPIA']],
    ['POST', '/api/push/cancelar',       [CuentaController::class, 'cancelar'],     'perm' => ['CUENTA_PROPIA']],
    ['POST', '/api/push/probar',         [CuentaController::class, 'probar'],       'perm' => ['CUENTA_PROPIA']],

    // Mi saldo: saldo prepago y recargas VOLUNTARIAS en línea (la persona inicia y autoriza cada una)
    ['GET',  '/mi-saldo',                        [SaldoController::class, 'index'],      'perm' => ['CUENTA_PROPIA']],
    ['GET',  '/mi-saldo/recargar',               [SaldoController::class, 'formRecarga'], 'perm' => ['CUENTA_PROPIA']],
    ['POST', '/mi-saldo/recargar',               [SaldoController::class, 'crear'],      'perm' => ['CUENTA_PROPIA']],
    ['GET',  '/mi-saldo/recargas/{id}',          [SaldoController::class, 'ver'],        'perm' => ['CUENTA_PROPIA']],
    ['POST', '/mi-saldo/recargas/{id}/cancelar', [SaldoController::class, 'cancelar'],   'perm' => ['CUENTA_PROPIA']],
    ['GET',  '/api/mi-saldo/recargas/{id}',      [SaldoController::class, 'estado'],     'perm' => ['CUENTA_PROPIA'], 'actividad' => false],
    // Webhook de la pasarela: servidor a servidor, sin sesión ni CSRF; se autentica con la firma del proveedor.
    ['POST', '/api/pasarela/wompi/eventos',      [PasarelaController::class, 'eventos'], 'auth' => false, 'csrf' => false, 'sesion' => false],
    // Regreso desde la página de pago. 'sesion' => false: no inicia sesión ni envía Set-Cookie. Al volver desde
    // otro sitio el navegador no envía la cookie SameSite=Strict; iniciar una sesión aquí la reemplazaría y la
    // persona quedaría desconectada. Esta página solo anota el id reportado y lleva a la recarga (mismo sitio).
    ['GET',  '/pago/retorno',                    [SaldoController::class, 'retorno'],    'auth' => false, 'sesion' => false],
    // Checkout SIMULADO (solo copia de demostración; con otra pasarela o en producción responden 404). Dentro de UniPark:
    // solo la persona dueña de la recarga, con sesión y CSRF. El panel (resolver pendientes, reenviar) es de Administración.
    ['GET',  '/simulador-pago',         [SimuladorController::class, 'pagina'], 'perm' => ['CUENTA_PROPIA']],
    ['POST', '/simulador-pago',         [SimuladorController::class, 'pagar'],  'perm' => ['CUENTA_PROPIA']],
    ['GET',  '/simulador-pago/panel',   [SimuladorController::class, 'panel'],  'perm' => ['SALDO_REVERSAR']],
    ['POST', '/simulador-pago/accion',  [SimuladorController::class, 'accion'], 'perm' => ['SALDO_REVERSAR']],

    // Caja
    ['GET',  '/caja',             [CajaController::class, 'index'],        'perm' => ['PAGO_REGISTRAR']],
    ['POST', '/caja/pago',         [CajaController::class, 'pagar'],        'perm' => ['PAGO_REGISTRAR']],
    ['POST', '/caja/cobro',        [CajaController::class, 'pagarCobro'],   'perm' => ['PAGO_REGISTRAR']],
    ['POST', '/caja/recarga',      [CajaController::class, 'recargar'],     'perm' => ['SALDO_RECARGAR']],
    ['GET',  '/caja/recarga/{id}', [CajaController::class, 'reciboRecarga'], 'perm' => ['SALDO_RECARGAR', 'REPORTE_CAJA']],
    ['GET',  '/caja/recibo/{id}',  [CajaController::class, 'recibo'],       'perm' => ['PAGO_REGISTRAR', 'REPORTE_CAJA']],
    ['GET',  '/caja/cierre',       [CajaController::class, 'cierre'],       'perm' => ['CAJA_CIERRE']],
    ['POST', '/caja/cierre',       [CajaController::class, 'cerrarCaja'],   'perm' => ['CAJA_CIERRE']],
    ['POST', '/pagos/{id}/anular', [CajaController::class, 'anular'],       'perm' => ['PAGO_ANULAR']],
    ['POST', '/saldo/{id}/reversar', [CajaController::class, 'reversarRecarga'], 'perm' => ['SALDO_REVERSAR']],

    // Estudiantes
    ['GET',  '/estudiantes',              [EstudianteController::class, 'index'],     'perm' => ['ESTUDIANTE_VER']],
    ['GET',  '/estudiantes/importar',     [ImportacionController::class, 'index'],    'perm' => ['PERSONA_IMPORTAR']],
    ['POST', '/estudiantes/importar',     [ImportacionController::class, 'procesar'], 'perm' => ['PERSONA_IMPORTAR']],
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
