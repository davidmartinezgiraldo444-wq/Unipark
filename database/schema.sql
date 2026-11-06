-- =====================================================================
-- UniPark · Esquema de base de datos
-- Compatible con MariaDB 10.4+ y MySQL 8.0.16+ (InnoDB, utf8mb4).
--
-- Se aplica sobre una base ya seleccionada:
--   mysql -u root unipark_db < database/schema.sql
-- o con el instalador web (instalar.php) o php scripts/install.php
--
-- ATENCIÓN: elimina y recrea todas las tablas de UniPark.
-- Después se cargan database/base.sql (obligatorio) y database/seed.sql (demostración).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS consecutivos;
DROP TABLE IF EXISTS visitas;
DROP TABLE IF EXISTS tarifas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS rol_permiso;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS estudiantes;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Personas que usan el parqueadero: estudiantes y profesores (RN-01, RF-04).
-- Solo los datos personales necesarios. La categoría define la tarifa.
-- ---------------------------------------------------------------------
CREATE TABLE estudiantes (
    id_estudiante  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    documento      VARCHAR(20)   NOT NULL,
    nombres        VARCHAR(80)   NOT NULL,
    apellidos      VARCHAR(80)   NOT NULL,
    correo         VARCHAR(120)  NULL,
    telefono       VARCHAR(20)   NULL,
    categoria      ENUM('ESTUDIANTE', 'PROFESOR') NOT NULL DEFAULT 'ESTUDIANTE',
    activo         TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_estudiante),
    CONSTRAINT uq_estudiantes_documento UNIQUE (documento),
    CONSTRAINT ck_estudiantes_documento CHECK (CHAR_LENGTH(documento) BETWEEN 5 AND 20),
    CONSTRAINT ck_estudiantes_activo    CHECK (activo IN (0, 1)),
    KEY ix_estudiantes_nombre (apellidos, nombres)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Roles, permisos y su relación muchos a muchos (RBAC, sección 4).
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id_rol      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(40)      NOT NULL,
    descripcion VARCHAR(200)     NOT NULL,
    activo      TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id_rol),
    CONSTRAINT uq_roles_nombre UNIQUE (nombre),
    CONSTRAINT ck_roles_activo CHECK (activo IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE permisos (
    id_permiso  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo      VARCHAR(40)       NOT NULL,
    descripcion VARCHAR(200)      NOT NULL,
    PRIMARY KEY (id_permiso),
    CONSTRAINT uq_permisos_codigo UNIQUE (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE rol_permiso (
    id_rol     TINYINT UNSIGNED  NOT NULL,
    id_permiso SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    KEY ix_rol_permiso_permiso (id_permiso),
    CONSTRAINT fk_rol_permiso_rol     FOREIGN KEY (id_rol)     REFERENCES roles (id_rol),
    CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (id_permiso) REFERENCES permisos (id_permiso)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Usuarios: cuentas individuales, contraseña solo como hash (RNF-01).
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre             VARCHAR(120)     NOT NULL,
    login              VARCHAR(120)     NOT NULL,
    password_hash      VARCHAR(255)     NOT NULL,
    id_rol             TINYINT UNSIGNED NOT NULL,
    activo             TINYINT(1)       NOT NULL DEFAULT 1,
    debe_cambiar_clave TINYINT(1)       NOT NULL DEFAULT 1,
    ultimo_acceso      DATETIME         NULL,
    creado_en          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuarios_login UNIQUE (login),
    KEY ix_usuarios_rol (id_rol),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles (id_rol),
    CONSTRAINT ck_usuarios_activo CHECK (activo IN (0, 1)),
    CONSTRAINT ck_usuarios_cambio CHECK (debe_cambiar_clave IN (0, 1)),
    CONSTRAINT ck_usuarios_hash   CHECK (CHAR_LENGTH(password_hash) >= 50)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tarifas con historial de vigencia (RN-03, RF-06).
-- Valor fijo por tipo de vehículo y categoría (estudiante o profesor).
-- Solo una tarifa abierta (sin vigente_hasta) por combinación tipo + categoría.
-- ---------------------------------------------------------------------
CREATE TABLE tarifas (
    id_tarifa      INT UNSIGNED           NOT NULL AUTO_INCREMENT,
    tipo_vehiculo  ENUM('CARRO', 'MOTO')  NOT NULL,
    categoria      ENUM('ESTUDIANTE', 'PROFESOR') NOT NULL,
    valor          DECIMAL(10, 2)         NOT NULL,
    vigente_desde  DATETIME               NOT NULL,
    vigente_hasta  DATETIME               NULL,
    activa         TINYINT(1)             NOT NULL DEFAULT 1,
    creado_por     INT UNSIGNED           NOT NULL,
    creado_en      DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tarifa_abierta VARCHAR(20) AS (IF(vigente_hasta IS NULL AND activa = 1, CONCAT(tipo_vehiculo, '-', categoria), NULL)) STORED,
    PRIMARY KEY (id_tarifa),
    CONSTRAINT uq_tarifas_abierta UNIQUE (tarifa_abierta),
    CONSTRAINT uq_tarifas_id_tipo UNIQUE (id_tarifa, tipo_vehiculo, categoria),
    KEY ix_tarifas_vigencia (tipo_vehiculo, categoria, vigente_desde),
    KEY ix_tarifas_creado_por (creado_por),
    CONSTRAINT fk_tarifas_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_tarifas_valor   CHECK (valor >= 0),
    CONSTRAINT ck_tarifas_rango   CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde),
    CONSTRAINT ck_tarifas_activa  CHECK (activa IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Visitas: una fila por estancia, sin atributos del vehículo (RN-02).
-- visita_abierta vale id_estudiante solo mientras la visita está EN_CURSO;
-- el índice UNIQUE impide dos visitas abiertas del mismo estudiante,
-- incluso con solicitudes simultáneas (RN-07, RN-13, sección 8.3).
-- ---------------------------------------------------------------------
CREATE TABLE visitas (
    id_visita        INT UNSIGNED                          NOT NULL AUTO_INCREMENT,
    id_estudiante    INT UNSIGNED                          NOT NULL,
    tipo_vehiculo    ENUM('CARRO', 'MOTO')                 NOT NULL,
    categoria        ENUM('ESTUDIANTE', 'PROFESOR')        NOT NULL,
    id_tarifa        INT UNSIGNED                          NOT NULL,
    tarifa_aplicada  DECIMAL(10, 2)                        NOT NULL,
    entrada_en       DATETIME                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entrada_por      INT UNSIGNED                          NOT NULL,
    salida_en        DATETIME                              NULL,
    salida_por       INT UNSIGNED                          NULL,
    estado           ENUM('EN_CURSO', 'CERRADA', 'ANULADA') NOT NULL DEFAULT 'EN_CURSO',
    anulada_en       DATETIME                              NULL,
    anulada_por      INT UNSIGNED                          NULL,
    motivo_anulacion VARCHAR(255)                          NULL,
    visita_abierta   INT UNSIGNED AS (IF(estado = 'EN_CURSO', id_estudiante, NULL)) STORED,
    PRIMARY KEY (id_visita),
    CONSTRAINT uq_visitas_abierta UNIQUE (visita_abierta),
    KEY ix_visitas_estudiante (id_estudiante, entrada_en),
    KEY ix_visitas_estado (estado, entrada_en),
    KEY ix_visitas_entrada (entrada_en),
    KEY ix_visitas_tarifa (id_tarifa, tipo_vehiculo, categoria),
    KEY ix_visitas_entrada_por (entrada_por),
    KEY ix_visitas_salida_por (salida_por),
    KEY ix_visitas_anulada_por (anulada_por),
    CONSTRAINT fk_visitas_estudiante  FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    -- La FK compuesta garantiza que el tipo y la categoría de la visita coincidan con los de la tarifa usada.
    CONSTRAINT fk_visitas_tarifa      FOREIGN KEY (id_tarifa, tipo_vehiculo, categoria) REFERENCES tarifas (id_tarifa, tipo_vehiculo, categoria),
    CONSTRAINT fk_visitas_entrada_por FOREIGN KEY (entrada_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_visitas_salida_por  FOREIGN KEY (salida_por)  REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_visitas_anulada_por FOREIGN KEY (anulada_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_visitas_tarifa  CHECK (tarifa_aplicada >= 0),
    CONSTRAINT ck_visitas_salida  CHECK (salida_en IS NULL OR salida_en >= entrada_en),
    CONSTRAINT ck_visitas_estado  CHECK (
        (estado = 'EN_CURSO' AND salida_en IS NULL AND salida_por IS NULL AND anulada_en IS NULL)
     OR (estado = 'CERRADA'  AND salida_en IS NOT NULL AND salida_por IS NOT NULL AND anulada_en IS NULL)
     OR (estado = 'ANULADA'  AND salida_en IS NULL AND anulada_en IS NOT NULL AND anulada_por IS NOT NULL
                             AND motivo_anulacion IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Consecutivos de comprobantes. Se incrementan dentro de la transacción
-- del pago, así que un pago revertido no deja huecos en la numeración.
-- ---------------------------------------------------------------------
CREATE TABLE consecutivos (
    nombre VARCHAR(20)  NOT NULL,
    ultimo INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (nombre)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO consecutivos (nombre, ultimo) VALUES ('RECIBO', 0);

-- ---------------------------------------------------------------------
-- Pagos: un único pago válido por visita (RN-09, RF-11).
-- pago_valido vale id_visita solo mientras el pago está PAGADO;
-- un pago ANULADO conserva la fila y libera la visita para un nuevo cobro.
-- No se almacenan datos de tarjetas.
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
    id_pago          INT UNSIGNED                                   NOT NULL AUTO_INCREMENT,
    id_visita        INT UNSIGNED                                   NOT NULL,
    monto            DECIMAL(10, 2)                                 NOT NULL,
    pagado_en        DATETIME                                       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por   INT UNSIGNED                                   NOT NULL,
    medio_pago       ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO')  NOT NULL,
    estado           ENUM('PAGADO', 'ANULADO')                      NOT NULL DEFAULT 'PAGADO',
    consecutivo      VARCHAR(12)                                    NOT NULL,
    referencia       VARCHAR(60)                                    NULL,
    anulado_en       DATETIME                                       NULL,
    anulado_por      INT UNSIGNED                                   NULL,
    motivo_anulacion VARCHAR(255)                                   NULL,
    pago_valido      INT UNSIGNED AS (IF(estado = 'PAGADO', id_visita, NULL)) STORED,
    PRIMARY KEY (id_pago),
    CONSTRAINT uq_pagos_consecutivo UNIQUE (consecutivo),
    CONSTRAINT uq_pagos_valido      UNIQUE (pago_valido),
    KEY ix_pagos_visita (id_visita),
    KEY ix_pagos_fecha (pagado_en),
    KEY ix_pagos_cajero (registrado_por, pagado_en),
    KEY ix_pagos_anulado_por (anulado_por),
    CONSTRAINT fk_pagos_visita      FOREIGN KEY (id_visita)      REFERENCES visitas (id_visita),
    CONSTRAINT fk_pagos_usuario     FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_pagos_anulado_por FOREIGN KEY (anulado_por)    REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_pagos_monto  CHECK (monto >= 0),
    CONSTRAINT ck_pagos_estado CHECK (
        (estado = 'PAGADO'  AND anulado_en IS NULL AND anulado_por IS NULL)
     OR (estado = 'ANULADO' AND anulado_en IS NOT NULL AND anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Auditoría: bitácora de operaciones sensibles (RN-10, RF-17).
-- ---------------------------------------------------------------------
CREATE TABLE auditoria (
    id_auditoria BIGINT UNSIGNED                     NOT NULL AUTO_INCREMENT,
    id_usuario   INT UNSIGNED                        NULL,
    accion       VARCHAR(40)                         NOT NULL,
    entidad      VARCHAR(40)                         NOT NULL,
    id_registro  BIGINT UNSIGNED                     NULL,
    resultado    ENUM('EXITO', 'DENEGADO', 'ERROR')  NOT NULL,
    motivo       VARCHAR(255)                        NULL,
    ip_origen    VARCHAR(45)                         NULL,
    creado_en    DATETIME                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    detalle_json LONGTEXT                            NULL,
    PRIMARY KEY (id_auditoria),
    KEY ix_auditoria_fecha (creado_en),
    KEY ix_auditoria_accion (accion, creado_en),
    KEY ix_auditoria_usuario (id_usuario, creado_en),
    KEY ix_auditoria_registro (entidad, id_registro),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_auditoria_json CHECK (detalle_json IS NULL OR JSON_VALID(detalle_json))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Disparadores de protección del historial (RN-11, RN-12).
-- Refuerzan en la base de datos lo que la aplicación ya controla.
-- ---------------------------------------------------------------------
DELIMITER $$

CREATE TRIGGER tr_auditoria_no_update BEFORE UPDATE ON auditoria FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se puede modificar';
END$$

CREATE TRIGGER tr_auditoria_no_delete BEFORE DELETE ON auditoria FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoria no se puede borrar';
END$$

CREATE TRIGGER tr_visitas_no_delete BEFORE DELETE ON visitas FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las visitas no se borran: use anulacion auditada';
END$$

CREATE TRIGGER tr_pagos_no_delete BEFORE DELETE ON pagos FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los pagos no se borran: use anulacion auditada';
END$$

CREATE TRIGGER tr_visitas_inmutables BEFORE UPDATE ON visitas FOR EACH ROW
BEGIN
    IF OLD.estado <> 'EN_CURSO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una visita cerrada o anulada no se puede modificar';
    END IF;
    IF NEW.id_estudiante <> OLD.id_estudiante
       OR NEW.tipo_vehiculo <> OLD.tipo_vehiculo
       OR NEW.categoria <> OLD.categoria
       OR NEW.id_tarifa <> OLD.id_tarifa
       OR NEW.tarifa_aplicada <> OLD.tarifa_aplicada
       OR NEW.entrada_en <> OLD.entrada_en
       OR NEW.entrada_por <> OLD.entrada_por THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los datos de entrada y la tarifa aplicada no se modifican';
    END IF;
END$$

CREATE TRIGGER tr_pagos_inmutables BEFORE UPDATE ON pagos FOR EACH ROW
BEGIN
    IF OLD.estado <> 'PAGADO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un pago anulado no se puede modificar';
    END IF;
    IF NEW.id_visita <> OLD.id_visita
       OR NEW.monto <> OLD.monto
       OR NEW.pagado_en <> OLD.pagado_en
       OR NEW.registrado_por <> OLD.registrado_por
       OR NEW.medio_pago <> OLD.medio_pago
       OR NEW.consecutivo <> OLD.consecutivo THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un pago confirmado solo puede anularse';
    END IF;
END$$

CREATE TRIGGER tr_tarifas_historicas BEFORE UPDATE ON tarifas FOR EACH ROW
BEGIN
    IF NEW.valor <> OLD.valor
       OR NEW.tipo_vehiculo <> OLD.tipo_vehiculo
       OR NEW.categoria <> OLD.categoria
       OR NEW.vigente_desde <> OLD.vigente_desde THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una tarifa no se edita: registre una nueva vigencia';
    END IF;
END$$

DELIMITER ;
