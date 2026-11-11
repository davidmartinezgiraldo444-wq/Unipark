-- Migración: 20261107_1200_personas_cuentas_saldo
-- Autor: UniPark
-- Motivo: el parqueadero atiende también a trabajadores y visitantes; el carnet puede tener un
--         código distinto del documento; cada persona tiene un saldo para el débito automático;
--         estudiantes, profesores y trabajadores tienen cuenta propia para consultar su saldo.
-- Reversión: ver bloque final.

SET NAMES utf8mb4;

-- Personas: nuevas categorías, código del carnet y saldo -------------------------------
ALTER TABLE estudiantes
    MODIFY categoria ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL DEFAULT 'ESTUDIANTE',
    ADD COLUMN codigo_carnet VARCHAR(40) NULL AFTER documento,
    ADD COLUMN saldo DECIMAL(10, 2) NOT NULL DEFAULT 0 AFTER categoria,
    ADD CONSTRAINT uq_estudiantes_carnet UNIQUE (codigo_carnet),
    ADD CONSTRAINT ck_estudiantes_saldo CHECK (saldo >= 0);

-- Cuenta de acceso vinculada a una persona (rol Estudiante, Profesor o Trabajador) -----
ALTER TABLE usuarios
    ADD COLUMN id_estudiante INT UNSIGNED NULL AFTER id_rol,
    ADD CONSTRAINT uq_usuarios_estudiante UNIQUE (id_estudiante),
    ADD CONSTRAINT fk_usuarios_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante);

-- Tarifas: categoría Trabajador (los visitantes no tienen tarifa) ----------------------
ALTER TABLE tarifas
    MODIFY categoria ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR') NOT NULL;

-- Reversión (solo si ninguna fila usa los valores nuevos):
-- ALTER TABLE tarifas MODIFY categoria ENUM('ESTUDIANTE','PROFESOR') NOT NULL;
-- ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_estudiante, DROP INDEX uq_usuarios_estudiante, DROP COLUMN id_estudiante;
-- ALTER TABLE estudiantes DROP CONSTRAINT ck_estudiantes_saldo, DROP INDEX uq_estudiantes_carnet,
--     DROP COLUMN saldo, DROP COLUMN codigo_carnet,
--     MODIFY categoria ENUM('ESTUDIANTE','PROFESOR') NOT NULL DEFAULT 'ESTUDIANTE';
