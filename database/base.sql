-- =====================================================================
-- UniPark · Datos base (obligatorios)
-- Roles, permisos, la cuenta del administrador y las tarifas diarias vigentes.
-- Se carga siempre después de schema.sql, con o sin datos de demostración.
--
-- Cuenta inicial:  admin  /  demo2026*
-- Es temporal: el sistema obliga a cambiarla en el primer ingreso.
-- =====================================================================

SET NAMES utf8mb4;

-- Roles ---------------------------------------------------------------
INSERT INTO roles (id_rol, nombre, descripcion) VALUES
    (1, 'Portería',      'Registra entradas y autoriza salidas con pago validado.'),
    (2, 'Caja',          'Registra pagos, emite comprobantes y genera su cierre de caja.'),
    (3, 'Administrador', 'Gestiona usuarios, personas, tarifas, reportes y auditoría.'),
    (4, 'Auditor',       'Consulta reportes, pagos, visitas y bitácora. Solo lectura.'),
    (5, 'Estudiante',    'Consulta su saldo, sus cobros diarios y su historial. Recibe avisos de pago.'),
    (6, 'Profesor',      'Consulta su saldo, sus cobros diarios y su historial. Recibe avisos de pago.'),
    (7, 'Trabajador',    'Consulta su saldo, sus cobros diarios y su historial. Recibe avisos de pago.');

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
    (16, 'AUDITORIA_VER',        'Consultar la bitácora de auditoría'),
    (17, 'CUENTA_PROPIA',        'Consultar su propio saldo, cobros, pagos y visitas'),
    (18, 'SALDO_RECARGAR',       'Recargar saldo de una persona en Caja');

-- Matriz de permisos (sección 4.1) -------------------------------------
INSERT INTO rol_permiso (id_rol, id_permiso) VALUES
    -- Portería
    (1, 1), (1, 3), (1, 4), (1, 5), (1, 12),
    -- Caja
    (2, 1), (2, 7), (2, 9), (2, 18),
    -- Administrador
    (3, 1), (3, 2), (3, 3), (3, 6), (3, 8), (3, 10), (3, 11), (3, 12), (3, 13), (3, 14), (3, 15), (3, 16),
    -- Auditor
    (4, 1), (4, 3), (4, 12), (4, 13), (4, 14), (4, 15), (4, 16),
    -- Estudiante, Profesor y Trabajador: solo su propia cuenta
    (5, 17), (6, 17), (7, 17);

-- Administrador inicial (contraseña temporal: demo2026*; se exige cambiarla en el primer ingreso) ----
INSERT INTO usuarios (id_usuario, nombre, login, password_hash, id_rol, activo, debe_cambiar_clave) VALUES
    (1, 'Administrador', 'admin', '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6', 3, 1, 1);

-- Tarifas -------------------------------------------------------------
-- 1-4: tarifas por visita del esquema anterior (historial, ya no rigen).
-- 7-12: tarifas por DÍA vigentes (COP). Bicicleta, patineta y visitantes no tienen tarifa.
INSERT INTO tarifas (id_tarifa, tipo_vehiculo, categoria, valor, vigente_desde, vigente_hasta, creado_por) VALUES
    (1,  'CARRO', 'ESTUDIANTE', 2050.00, '2026-01-01 00:00:00', '2026-11-07 00:00:00', 1),
    (2,  'CARRO', 'PROFESOR',   2800.00, '2026-01-01 00:00:00', '2026-11-07 00:00:00', 1),
    (3,  'MOTO',  'ESTUDIANTE', 1050.00, '2026-01-01 00:00:00', '2026-11-07 00:00:00', 1),
    (4,  'MOTO',  'PROFESOR',   1800.00, '2026-01-01 00:00:00', '2026-11-07 00:00:00', 1),
    (7,  'CARRO', 'ESTUDIANTE', 2000.00, '2026-11-07 00:00:00', NULL, 1),
    (8,  'MOTO',  'ESTUDIANTE', 1000.00, '2026-11-07 00:00:00', NULL, 1),
    (9,  'CARRO', 'PROFESOR',   3000.00, '2026-11-07 00:00:00', NULL, 1),
    (10, 'MOTO',  'PROFESOR',   2000.00, '2026-11-07 00:00:00', NULL, 1),
    (11, 'CARRO', 'TRABAJADOR', 3000.00, '2026-11-07 00:00:00', NULL, 1),
    (12, 'MOTO',  'TRABAJADOR', 2000.00, '2026-11-07 00:00:00', NULL, 1);

-- Puesto de cámara para el modo de dos celulares ----------------------
INSERT INTO puestos_camara (id_puesto, nombre) VALUES (1, 'Portería principal');

-- Una instalación nueva ya tiene la estructura de todas las migraciones --
INSERT INTO migraciones (archivo) VALUES
    ('20261107_1200_personas_cuentas_saldo.sql'),
    ('20261107_1210_cobro_diario_tolerancia.sql'),
    ('20261107_1220_notificaciones_camaras.sql'),
    ('20261107_1230_datos_roles_tarifas_diarias.sql'),
    ('20261107_1300_cobro_diferencia_vehiculo.sql'),
    ('20261108_0900_eventos_acceso_importacion.sql'),
    ('20261108_0910_datos_dispositivo_permisos.sql'),
    ('20261108_1000_debito_unico_por_pago.sql'),
    ('20261108_1010_recargas_en_linea.sql');

-- Rol de dispositivo «Punto de acceso» y permisos (igual que la migración 20261108_0910) ----
INSERT INTO roles (id_rol, nombre, descripcion) VALUES
    (8, 'Punto de acceso', 'Dispositivo de portería: lee carnés y clasifica el vehículo con la cámara. No elige tipos ni resuelve eventos.')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO permisos (id_permiso, codigo, descripcion) VALUES
    (19, 'ACCESO_KIOSCO',          'Punto de acceso: identificar y enviar la clasificación del vehículo'),
    (20, 'CLASIFICACION_RESOLVER', 'Resolver eventos de acceso pendientes (tipo de vehículo, salida, visitante)'),
    (21, 'COBRO_PROCESAR',         'Procesar cobros con la tolerancia vencida'),
    (22, 'SALDO_REVERSAR',         'Reversar una recarga de saldo con motivo'),
    (23, 'PERSONA_IMPORTAR',       'Importar la base de datos institucional'),
    (24, 'COBRO_ANULAR',           'Anular un cobro diario pendiente con motivo')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) VALUES
    -- Portería: también puede operar el punto de acceso y resolver los avisos
    (1, 19), (1, 20), (1, 21),
    -- Caja: procesa cobros (latido de su pantalla)
    (2, 21),
    -- Administrador
    (3, 21), (3, 22), (3, 23), (3, 24),
    -- Punto de acceso (dispositivo)
    (8, 19);
