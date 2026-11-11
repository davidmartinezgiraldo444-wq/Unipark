-- Migración: 20261108_0920_demo_dispositivos
-- Autor: UniPark
-- Motivo: cuentas de prueba del rol «Punto de acceso» para la demostración (solo con --demo).
--         Contraseña: demo2026* (hash bcrypt).
-- Reversión: desactivar las cuentas desde el módulo Usuarios.

SET NAMES utf8mb4;

SET @hash = '$2y$10$wxeGRqSoiHMXlgNLWUsQdep6bjfq3vKeiiqxrgighkclqP2zK.oY6';

INSERT IGNORE INTO usuarios (nombre, login, password_hash, id_rol, activo, debe_cambiar_clave) VALUES
    ('Punto de acceso · Entrada', 'acceso1', @hash, 8, 1, 0),
    ('Punto de acceso · Cámara',  'acceso2', @hash, 8, 1, 0);
