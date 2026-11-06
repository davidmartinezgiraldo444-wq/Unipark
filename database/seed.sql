-- =====================================================================
-- UniPark · Datos ficticios de demostración (opcional)
-- Se carga después de schema.sql y base.sql. Ningún dato corresponde a
-- personas reales. No usar en producción.
--
-- Cuentas (todas con la contraseña: Demo2026*)
--   admin       Administrador (definida en base.sql)
--   porteria    Portería
--   caja        Caja
--   caja2       Caja (segundo turno)
--   auditor     Auditor
--   inactivo    Portería, cuenta desactivada (prueba CP-09)
--
-- Las fechas son relativas a NOW() para que los reportes del día tengan datos.
-- =====================================================================

SET NAMES utf8mb4;

-- Usuarios de demostración ---------------------------------------------
INSERT INTO usuarios (id_usuario, nombre, login, password_hash, id_rol, activo, debe_cambiar_clave) VALUES
    (2, 'Pedro Portero',    'porteria', '$2y$10$0Uz6RID4LJYlNfMIEh2jo.YEvZ5gmQYJ/9aqNKWo9C5oojcTIgjjC', 1, 1, 0),
    (3, 'Camila Cajera',    'caja',     '$2y$10$0Uz6RID4LJYlNfMIEh2jo.YEvZ5gmQYJ/9aqNKWo9C5oojcTIgjjC', 2, 1, 0),
    (4, 'Andrés Auditor',   'auditor',  '$2y$10$0Uz6RID4LJYlNfMIEh2jo.YEvZ5gmQYJ/9aqNKWo9C5oojcTIgjjC', 4, 1, 0),
    (5, 'Carlos Cajero',    'caja2',    '$2y$10$0Uz6RID4LJYlNfMIEh2jo.YEvZ5gmQYJ/9aqNKWo9C5oojcTIgjjC', 2, 1, 0),
    (6, 'Usuario Inactivo', 'inactivo', '$2y$10$0Uz6RID4LJYlNfMIEh2jo.YEvZ5gmQYJ/9aqNKWo9C5oojcTIgjjC', 1, 0, 0);

-- Tarifas históricas de estudiantes (2025): muestran que las visitas conservan su valor (RN-11)
INSERT INTO tarifas (id_tarifa, tipo_vehiculo, categoria, valor, vigente_desde, vigente_hasta, creado_por) VALUES
    (5, 'CARRO', 'ESTUDIANTE', 1900.00, '2025-07-01 00:00:00', '2026-01-01 00:00:00', 1),
    (6, 'MOTO',  'ESTUDIANTE',  950.00, '2025-07-01 00:00:00', '2026-01-01 00:00:00', 1);

-- Estudiantes y profesores ficticios -----------------------------------
INSERT INTO estudiantes (id_estudiante, documento, nombres, apellidos, correo, telefono, categoria, activo) VALUES
    (1,  '1000000001', 'Valentina', 'Ríos Cardona',     'valentina.rios@correo.test',  NULL,         'ESTUDIANTE', 1),
    (2,  '1000000002', 'Santiago',  'Mejía Restrepo',   NULL,                          '3000000002', 'ESTUDIANTE', 1),
    (3,  '1000000003', 'Mariana',   'Gómez Duque',      'mariana.gomez@correo.test',   NULL,         'ESTUDIANTE', 1),
    (4,  '1000000004', 'Juan José', 'Arango Vélez',     NULL,                          NULL,         'ESTUDIANTE', 1),
    (5,  '1000000005', 'Isabella',  'Henao Zapata',     'isabella.henao@correo.test',  NULL,         'ESTUDIANTE', 1),
    (6,  '1000000006', 'Samuel',    'Osorio Gil',       NULL,                          '3000000006', 'PROFESOR',   1),
    (7,  '1000000007', 'Sara',      'Londoño Patiño',   NULL,                          NULL,         'ESTUDIANTE', 1),
    (8,  '1000000008', 'Tomás',     'Cardona Ospina',   'tomas.cardona@correo.test',   NULL,         'ESTUDIANTE', 1),
    (9,  '1000000009', 'Antonella', 'Muñoz Betancur',   NULL,                          NULL,         'ESTUDIANTE', 1),
    (10, '1000000010', 'Emiliano',  'Castaño Salazar',  NULL,                          NULL,         'ESTUDIANTE', 1),
    (11, '1000000011', 'Luciana',   'Echeverri Tobón',  'luciana.echeverri@correo.test', NULL,       'PROFESOR',   1),
    (12, '1000000012', 'Matías',    'Quintero Álvarez', NULL,                          NULL,         'ESTUDIANTE', 0);

-- Visitas --------------------------------------------------------------
-- 1-5 cerradas y pagadas · 6 y 8 abiertas sin pago · 7 abierta y pagada (profesor) · 9 anulada
INSERT INTO visitas (id_visita, id_estudiante, tipo_vehiculo, categoria, id_tarifa, tarifa_aplicada,
                     entrada_en, entrada_por, salida_en, salida_por, estado,
                     anulada_en, anulada_por, motivo_anulacion) VALUES
    (1, 3, 'CARRO', 'ESTUDIANTE', 5, 1900.00, '2025-11-10 07:12:00', 2, '2025-11-10 12:40:00', 2, 'CERRADA', NULL, NULL, NULL),
    (2, 4, 'MOTO',  'ESTUDIANTE', 6,  950.00, '2025-12-02 09:05:00', 2, '2025-12-02 16:20:00', 2, 'CERRADA', NULL, NULL, NULL),
    (3, 1, 'CARRO', 'ESTUDIANTE', 1, 2050.00, NOW() - INTERVAL 3 DAY - INTERVAL 6 HOUR, 2, NOW() - INTERVAL 3 DAY, 2, 'CERRADA', NULL, NULL, NULL),
    (4, 2, 'MOTO',  'ESTUDIANTE', 3, 1050.00, NOW() - INTERVAL 2 DAY - INTERVAL 5 HOUR, 2, NOW() - INTERVAL 2 DAY, 2, 'CERRADA', NULL, NULL, NULL),
    (5, 1, 'CARRO', 'ESTUDIANTE', 1, 2050.00, NOW() - INTERVAL 5 HOUR,                  2, NOW() - INTERVAL 1 HOUR, 2, 'CERRADA', NULL, NULL, NULL),
    (6, 5, 'MOTO',  'ESTUDIANTE', 3, 1050.00, NOW() - INTERVAL 2 HOUR,                  2, NULL, NULL, 'EN_CURSO', NULL, NULL, NULL),
    (7, 6, 'CARRO', 'PROFESOR',   2, 2800.00, NOW() - INTERVAL 90 MINUTE,               2, NULL, NULL, 'EN_CURSO', NULL, NULL, NULL),
    (8, 7, 'CARRO', 'ESTUDIANTE', 1, 2050.00, NOW() - INTERVAL 30 MINUTE,               2, NULL, NULL, 'EN_CURSO', NULL, NULL, NULL),
    (9, 8, 'MOTO',  'ESTUDIANTE', 3, 1050.00, NOW() - INTERVAL 1 DAY - INTERVAL 4 HOUR, 2, NULL, NULL, 'ANULADA',
        NOW() - INTERVAL 1 DAY - INTERVAL 3 HOUR, 1, 'Entrada registrada al estudiante equivocado.');

-- Pagos ----------------------------------------------------------------
-- El pago 4 se anuló por un medio mal registrado y la visita 4 se volvió a cobrar (pago 5).
INSERT INTO pagos (id_pago, id_visita, monto, pagado_en, registrado_por, medio_pago, estado, consecutivo,
                   referencia, anulado_en, anulado_por, motivo_anulacion) VALUES
    (1, 1, 1900.00, '2025-11-10 12:31:00', 3, 'EFECTIVO',      'PAGADO',  'R00000001', NULL, NULL, NULL, NULL),
    (2, 2,  950.00, '2025-12-02 16:10:00', 3, 'TRANSFERENCIA', 'PAGADO',  'R00000002', 'TRX-552910', NULL, NULL, NULL),
    (3, 3, 2050.00, NOW() - INTERVAL 3 DAY - INTERVAL 10 MINUTE, 3, 'EFECTIVO', 'PAGADO', 'R00000003', NULL, NULL, NULL, NULL),
    (4, 4, 1050.00, NOW() - INTERVAL 2 DAY - INTERVAL 20 MINUTE, 5, 'DATAFONO', 'ANULADO', 'R00000004', NULL,
        NOW() - INTERVAL 2 DAY - INTERVAL 15 MINUTE, 1, 'Medio de pago registrado por error; se cobró en efectivo.'),
    (5, 4, 1050.00, NOW() - INTERVAL 2 DAY - INTERVAL 12 MINUTE, 5, 'EFECTIVO', 'PAGADO', 'R00000005', NULL, NULL, NULL, NULL),
    (6, 5, 2050.00, NOW() - INTERVAL 70 MINUTE, 3, 'EFECTIVO', 'PAGADO', 'R00000006', NULL, NULL, NULL, NULL),
    (7, 7, 2800.00, NOW() - INTERVAL 20 MINUTE, 3, 'DATAFONO', 'PAGADO', 'R00000007', 'AUT-118204', NULL, NULL, NULL);

UPDATE consecutivos SET ultimo = 7 WHERE nombre = 'RECIBO';

-- Eventos de auditoría de los datos de ejemplo --------------------------
INSERT INTO auditoria (id_usuario, accion, entidad, id_registro, resultado, motivo, ip_origen, creado_en, detalle_json) VALUES
    (1, 'VISITA_ANULADA', 'visitas', 9, 'EXITO', 'Entrada registrada al estudiante equivocado.', '127.0.0.1',
        NOW() - INTERVAL 1 DAY - INTERVAL 3 HOUR, NULL),
    (1, 'PAGO_ANULADO',   'pagos',   4, 'EXITO', 'Medio de pago registrado por error; se cobró en efectivo.', '127.0.0.1',
        NOW() - INTERVAL 2 DAY - INTERVAL 15 MINUTE, '{"consecutivo":"R00000004","monto":"1050.00"}');
