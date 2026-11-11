-- Migración: 20261107_1240_demo_usuarios_prueba
-- Autor: UniPark
-- Motivo: usuarios de prueba por rol para la demostración. SOLO para bases de demostración:
--         scripts/migrar.php la omite salvo que se ejecute con --demo.
--         Contraseña de TODAS las cuentas de prueba: demo2026*  (guardada como hash bcrypt).
-- Reversión: desactivar las cuentas desde el módulo Usuarios.

SET NAMES utf8mb4;

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
