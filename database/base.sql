-- =====================================================================
-- UniPark · Datos base (obligatorios)
-- Roles, permisos, la cuenta del administrador y las tarifas vigentes.
-- Se carga siempre después de schema.sql, con o sin datos de demostración.
--
-- Cuenta inicial:  admin  /  Demo2026*
-- Cámbiala desde "Cambiar contraseña" después del primer ingreso.
-- =====================================================================

SET NAMES utf8mb4;

-- Roles ---------------------------------------------------------------
INSERT INTO roles (id_rol, nombre, descripcion) VALUES
    (1, 'Portería',      'Registra entradas y autoriza salidas con pago validado.'),
    (2, 'Caja',          'Registra pagos, emite comprobantes y genera su cierre de caja.'),
    (3, 'Administrador', 'Gestiona usuarios, personas, tarifas, reportes y auditoría.'),
    (4, 'Auditor',       'Consulta reportes, pagos, visitas y bitácora. Solo lectura.');

-- Permisos (mismo catálogo que config/permissions.php) ----------------
INSERT INTO permisos (id_permiso, codigo, descripcion) VALUES
    (1,  'ESTUDIANTE_VER',       'Consultar estudiantes y profesores'),
    (2,  'ESTUDIANTE_GESTIONAR', 'Crear, editar y desactivar estudiantes y profesores'),
    (3,  'VISITA_VER',           'Consultar el historial de visitas'),
    (4,  'ENTRADA_CREAR',        'Registrar entradas'),
    (5,  'SALIDA_AUTORIZAR',     'Autorizar salidas con pago validado'),
    (6,  'VISITA_ANULAR',        'Anular visitas con motivo'),
    (7,  'PAGO_REGISTRAR',       'Registrar pagos y emitir comprobantes'),
    (8,  'PAGO_ANULAR',          'Anular pagos con motivo'),
    (9,  'CAJA_CIERRE',          'Generar el cierre de caja propio'),
    (10, 'TARIFA_GESTIONAR',     'Administrar tarifas'),
    (11, 'USUARIO_GESTIONAR',    'Administrar usuarios y roles'),
    (12, 'REPORTE_OPERATIVO',    'Ver visitas abiertas en tiempo real'),
    (13, 'REPORTE_VER',          'Ver reportes de visitas'),
    (14, 'REPORTE_CAJA',         'Ver reportes de pagos y recaudo'),
    (15, 'REPORTE_EXPORTAR',     'Exportar reportes a CSV'),
    (16, 'AUDITORIA_VER',        'Consultar la bitácora de auditoría');

-- Matriz de permisos (sección 4.1) -------------------------------------
INSERT INTO rol_permiso (id_rol, id_permiso) VALUES
    -- Portería
    (1, 1), (1, 3), (1, 4), (1, 5), (1, 12),
    -- Caja
    (2, 1), (2, 7), (2, 9),
    -- Administrador
    (3, 1), (3, 2), (3, 3), (3, 6), (3, 8), (3, 10), (3, 11), (3, 12), (3, 13), (3, 14), (3, 15), (3, 16),
    -- Auditor
    (4, 1), (4, 3), (4, 12), (4, 13), (4, 14), (4, 15), (4, 16);

-- Administrador inicial (contraseña: Demo2026*) -------------------------
INSERT INTO usuarios (id_usuario, nombre, login, password_hash, id_rol, activo, debe_cambiar_clave) VALUES
    (1, 'Laura Administradora', 'admin', '$2y$10$0Uz6RID4LJYlNfMIEh2jo.YEvZ5gmQYJ/9aqNKWo9C5oojcTIgjjC', 3, 1, 0);

-- Tarifas vigentes (valor fijo por visita, en pesos) --------------------
INSERT INTO tarifas (id_tarifa, tipo_vehiculo, categoria, valor, vigente_desde, vigente_hasta, creado_por) VALUES
    (1, 'CARRO', 'ESTUDIANTE', 2050.00, '2026-01-01 00:00:00', NULL, 1),
    (2, 'CARRO', 'PROFESOR',   2800.00, '2026-01-01 00:00:00', NULL, 1),
    (3, 'MOTO',  'ESTUDIANTE', 1050.00, '2026-01-01 00:00:00', NULL, 1),
    (4, 'MOTO',  'PROFESOR',   1800.00, '2026-01-01 00:00:00', NULL, 1);
