-- Migración: 20261107_1230_datos_roles_tarifas_diarias
-- Autor: UniPark
-- Motivo: roles con cuenta propia (Estudiante, Profesor, Trabajador), permisos de saldo,
--         tarifas diarias aprobadas, consecutivo de recargas y puesto de cámara.
--         Tarifas por día (COP): Estudiante carro 2.000 · moto 1.000;
--         Profesor y Trabajador carro 3.000 · moto 2.000. Bicicleta y patineta: sin tarifa.
-- Reversión: cerrar las tarifas nuevas y registrar una nueva vigencia desde el módulo Tarifas.

SET NAMES utf8mb4;

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
    (5, 'Estudiante', 'Consulta su saldo, sus cobros diarios y su historial. Recibe avisos de pago.'),
    (6, 'Profesor',   'Consulta su saldo, sus cobros diarios y su historial. Recibe avisos de pago.'),
    (7, 'Trabajador', 'Consulta su saldo, sus cobros diarios y su historial. Recibe avisos de pago.')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO permisos (id_permiso, codigo, descripcion) VALUES
    (17, 'CUENTA_PROPIA',  'Consultar su propio saldo, cobros, pagos y visitas'),
    (18, 'SALDO_RECARGAR', 'Recargar saldo de una persona en Caja')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) VALUES
    (2, 18),
    (5, 17), (6, 17), (7, 17);

-- Las tarifas por visita dejan de regir; quedan en el historial.
UPDATE tarifas SET vigente_hasta = NOW() WHERE vigente_hasta IS NULL AND activa = 1;

INSERT INTO tarifas (tipo_vehiculo, categoria, valor, vigente_desde, vigente_hasta, creado_por) VALUES
    ('CARRO', 'ESTUDIANTE', 2000.00, NOW(), NULL, 1),
    ('MOTO',  'ESTUDIANTE', 1000.00, NOW(), NULL, 1),
    ('CARRO', 'PROFESOR',   3000.00, NOW(), NULL, 1),
    ('MOTO',  'PROFESOR',   2000.00, NOW(), NULL, 1),
    ('CARRO', 'TRABAJADOR', 3000.00, NOW(), NULL, 1),
    ('MOTO',  'TRABAJADOR', 2000.00, NOW(), NULL, 1);

INSERT IGNORE INTO consecutivos (nombre, ultimo) VALUES ('RECARGA', 0);

INSERT IGNORE INTO puestos_camara (id_puesto, nombre) VALUES (1, 'Portería principal');
