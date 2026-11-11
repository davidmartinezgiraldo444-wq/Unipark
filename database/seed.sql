-- =====================================================================
-- UniPark · Datos ficticios de demostración (opcional)
-- Se carga después de schema.sql y base.sql. Ningún dato corresponde a
-- personas reales. No usar en producción.
--
-- Cuentas (todas con la contraseña: demo2026*)
--   admin, admin2                          Administrador (admin definida en base.sql)
--   porteria, porteria2, porteria3         Portería
--   caja, caja2, caja3                     Caja
--   auditor, auditor2                      Auditor
--   estudiante1, estudiante2, estudiante3  Estudiante (cuenta propia: saldo, cobros, avisos)
--   profesor1, profesor2, profesor3        Profesor
--   trabajador1, trabajador2, trabajador3  Trabajador
--   acceso1, acceso2                       Punto de acceso (dispositivo de portería)
--   inactivo                               Portería, cuenta desactivada (prueba CP-09)
--
-- Las visitas y pagos 1 a 9 son del esquema anterior (un pago por visita) y
-- quedan como LEGADO, igual que en una base migrada.
--
-- Las fechas son relativas a NOW() para que los reportes del día tengan datos.
-- =====================================================================

SET NAMES utf8mb4;

-- Usuarios de demostración ---------------------------------------------
INSERT INTO usuarios (id_usuario, nombre, login, password_hash, id_rol, activo, debe_cambiar_clave) VALUES
    (2, 'Pedro Portero',    'porteria', '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6', 1, 1, 0),
    (3, 'Camila Cajera',    'caja',     '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6', 2, 1, 0),
    (4, 'Andrés Auditor',   'auditor',  '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6', 4, 1, 0),
    (5, 'Carlos Cajero',    'caja2',    '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6', 2, 1, 0),
    (6, 'Usuario Inactivo', 'inactivo', '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6', 1, 0, 0);

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
-- Son del esquema anterior (un pago por visita): se insertan como LEGADO, igual que en una base migrada.
ALTER TABLE visitas ALTER COLUMN liquidacion SET DEFAULT 'LEGADO';
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

ALTER TABLE visitas ALTER COLUMN liquidacion SET DEFAULT 'EN_TOLERANCIA';

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

-- =====================================================================
-- Usuarios de prueba por rol, profesores y trabajadores, y saldos iniciales.
-- Igual que database/migrations/20261107_1240_demo_usuarios_prueba.sql
-- =====================================================================
-- Personas ficticias nuevas: profesores y trabajadores --------------------------------
INSERT IGNORE INTO estudiantes (documento, nombres, apellidos, correo, categoria, activo) VALUES
    ('1000000013', 'Ricardo', 'Alzate Mora',     'ricardo.alzate@correo.test', 'PROFESOR',   1),
    ('1000000014', 'Paola',   'Restrepo Vélez',  NULL,                         'PROFESOR',   1),
    ('1000000015', 'Hernán',  'Gil Ocampo',      NULL,                         'TRABAJADOR', 1),
    ('1000000016', 'Gloria',  'Zuluaga Ríos',    'gloria.zuluaga@correo.test', 'TRABAJADOR', 1),
    ('1000000017', 'Fabio',   'Montoya Henao',   NULL,                         'TRABAJADOR', 1);

-- Todas las cuentas de demostración con la misma contraseña: demo2026* -----------------
SET @hash = '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6';

UPDATE usuarios SET password_hash = @hash, debe_cambiar_clave = 0
 WHERE login IN ('admin', 'porteria', 'caja', 'caja2', 'auditor', 'inactivo');

INSERT IGNORE INTO usuarios (nombre, login, password_hash, id_rol, id_estudiante, activo, debe_cambiar_clave) VALUES
    ('Paula Portera',        'porteria2',   @hash, 1, NULL, 1, 0),
    ('Mateo Vigilante',      'porteria3',   @hash, 1, NULL, 1, 0),
    ('Daniela Cajera',       'caja3',       @hash, 2, NULL, 1, 0),
    ('Jorge Administrador',  'admin2',      @hash, 3, NULL, 1, 0),
    ('Natalia Auditora',     'auditor2',    @hash, 4, NULL, 1, 0),
    ('Valentina Ríos Cardona',   'estudiante1', @hash, 5, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000001'), 1, 0),
    ('Santiago Mejía Restrepo',  'estudiante2', @hash, 5, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000002'), 1, 0),
    ('Mariana Gómez Duque',      'estudiante3', @hash, 5, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000003'), 1, 0),
    ('Luciana Echeverri Tobón',  'profesor1',   @hash, 6, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000011'), 1, 0),
    ('Ricardo Alzate Mora',      'profesor2',   @hash, 6, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000013'), 1, 0),
    ('Paola Restrepo Vélez',     'profesor3',   @hash, 6, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000014'), 1, 0),
    ('Hernán Gil Ocampo',        'trabajador1', @hash, 7, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000015'), 1, 0),
    ('Gloria Zuluaga Ríos',      'trabajador2', @hash, 7, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000016'), 1, 0),
    ('Fabio Montoya Henao',      'trabajador3', @hash, 7, (SELECT id_estudiante FROM estudiantes WHERE documento = '1000000017'), 1, 0);

-- Saldos iniciales cargados como recargas en Caja (el libro de movimientos cuadra) ------
-- Las personas con saldo 0 sirven para probar el valor pendiente y el aviso al celular.
INSERT INTO consecutivos (nombre, ultimo) VALUES ('RECARGA', 0) ON DUPLICATE KEY UPDATE ultimo = ultimo;

DROP TEMPORARY TABLE IF EXISTS tmp_recargas;
CREATE TEMPORARY TABLE tmp_recargas (n INT, documento VARCHAR(20), monto DECIMAL(10, 2));
INSERT INTO tmp_recargas VALUES
    (1, '1000000001', 10000), (2, '1000000003', 1500), (3, '1000000011', 20000),
    (4, '1000000014', 5000),  (5, '1000000015', 10000), (6, '1000000017', 3000);

SET @base = (SELECT ultimo FROM consecutivos WHERE nombre = 'RECARGA');
SET @cajera = (SELECT id_usuario FROM usuarios WHERE login = 'caja');

INSERT INTO movimientos_saldo (id_estudiante, tipo, monto, saldo_anterior, saldo_nuevo, medio_pago, consecutivo, registrado_por, creado_en)
SELECT e.id_estudiante, 'RECARGA', t.monto, e.saldo, e.saldo + t.monto, 'EFECTIVO',
       CONCAT('C', LPAD(@base + t.n, 8, '0')), @cajera, NOW() - INTERVAL 1 DAY
  FROM tmp_recargas t
  JOIN estudiantes e ON e.documento = t.documento
 WHERE NOT EXISTS (SELECT 1 FROM movimientos_saldo m WHERE m.id_estudiante = e.id_estudiante);

UPDATE estudiantes e
  JOIN (SELECT id_estudiante, SUM(IF(tipo = 'DEBITO', -monto, monto)) AS saldo
          FROM movimientos_saldo GROUP BY id_estudiante) m ON m.id_estudiante = e.id_estudiante
   SET e.saldo = m.saldo;

UPDATE consecutivos
   SET ultimo = (SELECT COALESCE(MAX(CAST(SUBSTRING(consecutivo, 2) AS UNSIGNED)), 0) FROM movimientos_saldo WHERE consecutivo LIKE 'C%')
 WHERE nombre = 'RECARGA';

DROP TEMPORARY TABLE tmp_recargas;

INSERT INTO migraciones (archivo) VALUES ('20261107_1240_demo_usuarios_prueba.sql');

-- Cuentas de prueba del rol Punto de acceso (igual que la migración 20261108_0920) ----------
SET @hash = '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6';

INSERT IGNORE INTO usuarios (nombre, login, password_hash, id_rol, activo, debe_cambiar_clave) VALUES
    ('Punto de acceso · Entrada', 'acceso1', @hash, 8, 1, 0),
    ('Punto de acceso · Cámara',  'acceso2', @hash, 8, 1, 0);

INSERT INTO migraciones (archivo) VALUES ('20261108_0920_demo_dispositivos.sql');
