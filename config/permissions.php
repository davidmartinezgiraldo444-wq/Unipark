<?php
declare(strict_types=1);

// Catálogo de permisos y matriz por rol (sección 4.1).
// La fuente de verdad en ejecución es la base de datos (tablas permisos y rol_permiso);
// este archivo documenta la matriz y las pruebas verifican que la semilla coincida con él.
return [
    'permisos' => [
        'ESTUDIANTE_VER'       => 'Consultar estudiantes',
        'ESTUDIANTE_GESTIONAR' => 'Crear, editar y desactivar estudiantes',
        'VISITA_VER'           => 'Consultar el historial de visitas',
        'ENTRADA_CREAR'        => 'Registrar entradas',
        'SALIDA_AUTORIZAR'     => 'Autorizar salidas con pago validado',
        'VISITA_ANULAR'        => 'Anular visitas con motivo',
        'PAGO_REGISTRAR'       => 'Registrar pagos y emitir comprobantes',
        'PAGO_ANULAR'          => 'Anular pagos con motivo',
        'CAJA_CIERRE'          => 'Generar el cierre de caja propio',
        'TARIFA_GESTIONAR'     => 'Administrar tarifas',
        'USUARIO_GESTIONAR'    => 'Administrar usuarios y roles',
        'REPORTE_OPERATIVO'    => 'Ver visitas abiertas en tiempo real',
        'REPORTE_VER'          => 'Ver reportes de visitas',
        'REPORTE_CAJA'         => 'Ver reportes de pagos y recaudo',
        'REPORTE_EXPORTAR'     => 'Exportar reportes a CSV',
        'AUDITORIA_VER'        => 'Consultar la bitácora de auditoría',
    ],

    'roles' => [
        'Portería' => [
            'ESTUDIANTE_VER', 'VISITA_VER', 'ENTRADA_CREAR', 'SALIDA_AUTORIZAR', 'REPORTE_OPERATIVO',
        ],
        'Caja' => [
            'ESTUDIANTE_VER', 'PAGO_REGISTRAR', 'CAJA_CIERRE',
        ],
        'Administrador' => [
            'ESTUDIANTE_VER', 'ESTUDIANTE_GESTIONAR', 'VISITA_VER', 'VISITA_ANULAR', 'PAGO_ANULAR',
            'TARIFA_GESTIONAR', 'USUARIO_GESTIONAR', 'REPORTE_OPERATIVO', 'REPORTE_VER', 'REPORTE_CAJA',
            'REPORTE_EXPORTAR', 'AUDITORIA_VER',
        ],
        'Auditor' => [
            'ESTUDIANTE_VER', 'VISITA_VER', 'REPORTE_OPERATIVO', 'REPORTE_VER', 'REPORTE_CAJA',
            'REPORTE_EXPORTAR', 'AUDITORIA_VER',
        ],
    ],

    // Pantalla inicial de cada rol después de iniciar sesión.
    'inicio' => [
        'Portería'      => 'porteria',
        'Caja'          => 'caja',
        'Administrador' => 'panel',
        'Auditor'       => 'reportes',
    ],
];
