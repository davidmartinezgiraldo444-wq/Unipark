-- Migración: 20261108_0910_datos_dispositivo_permisos
-- Autor: UniPark
-- Motivo: rol de dispositivo «Punto de acceso» (el celular donde la persona escanea su carné y la cámara
--         clasifica el vehículo) y permisos nuevos con control en el servidor.
--   ACCESO_KIOSCO           identificar, crear eventos y enviar la clasificación de la IA (sin elegir el tipo)
--   CLASIFICACION_RESOLVER  el portero resuelve eventos pendientes (tipo de vehículo, salida, visitante)
--   COBRO_PROCESAR          disparar el procesamiento de cobros vencidos (latido de Portería y Caja)
--   SALDO_REVERSAR          reversar una recarga mal digitada
--   PERSONA_IMPORTAR        importar la base de datos institucional
--   COBRO_ANULAR            anular un cobro diario pendiente
-- Reversión: retirar los permisos del rol desde la base de datos.

SET NAMES utf8mb4;

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
