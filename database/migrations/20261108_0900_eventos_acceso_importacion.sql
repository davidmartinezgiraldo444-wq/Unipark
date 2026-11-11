-- Migración: 20261108_0900_eventos_acceso_importacion
-- Autor: UniPark
-- Motivo: especificación definitiva del 08/11/2026.
--   · Eventos de acceso: cada pasada por la portería relaciona identidad → detección del vehículo →
--     confirmación (IA o portero) → visita. La detección dudosa queda pendiente para el portero.
--   · Personas institucionales: programa/dependencia, origen y fecha de sincronización; bitácora de importaciones.
--   · Reverso controlado de recargas (movimiento REVERSO_RECARGA, uno por recarga).
--   · Detección del celular de cámara con la segunda opción (margen de confianza).
-- Reversión: no se ofrece; restaurar el respaldo tomado antes de aplicar.

SET NAMES utf8mb4;

-- Personas: datos institucionales -------------------------------------------------
ALTER TABLE estudiantes
    ADD COLUMN programa VARCHAR(120) NULL AFTER categoria,
    ADD COLUMN origen ENUM('UNIPARK', 'INSTITUCIONAL') NOT NULL DEFAULT 'UNIPARK' AFTER activo,
    ADD COLUMN sincronizado_en DATETIME NULL AFTER origen;

-- Celular de cámara: también la probabilidad de la segunda opción --------------------
ALTER TABLE puestos_camara
    ADD COLUMN segunda DECIMAL(5, 4) NULL AFTER confianza,
    ADD CONSTRAINT ck_puestos_segunda CHECK (segunda IS NULL OR (segunda >= 0 AND segunda <= 1));

-- Eventos de acceso ----------------------------------------------------------------
CREATE TABLE eventos_acceso (
    id_evento        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tipo             ENUM('ENTRADA', 'SALIDA') NOT NULL,
    id_puesto        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    id_estudiante    INT UNSIGNED     NULL,
    -- Solo si la identidad no está registrada (para que el portero admita a un visitante).
    documento_leido  VARCHAR(20)      NULL,
    nombre_leido     VARCHAR(80)      NULL,
    id_visita        INT UNSIGNED     NULL,
    estado           ENUM('EN_CAPTURA', 'PENDIENTE_PORTERO', 'CONFIRMADO', 'RECHAZADO', 'EXPIRADO') NOT NULL DEFAULT 'EN_CAPTURA',
    motivo           ENUM('VEHICULO_NO_CLASIFICADO', 'VEHICULO_NO_COINCIDE', 'IDENTIDAD_NO_REGISTRADA',
                          'PERSONA_NO_AUTORIZADA', 'CAPTURA_REEMPLAZADA', 'SIN_ATENDER') NULL,
    tipo_detectado   ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA', 'DESCONOCIDO') NULL,
    confianza        DECIMAL(5, 4)    NULL,
    segunda          DECIMAL(5, 4)    NULL,
    fuente           ENUM('CELULAR', 'PUESTO') NULL,
    tipo_confirmado  ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NULL,
    metodo           ENUM('IA', 'MANUAL') NULL,
    resultado        VARCHAR(40)      NULL,
    mensaje          VARCHAR(255)     NULL,
    tiene_captura    TINYINT(1)       NOT NULL DEFAULT 0,
    creado_por       INT UNSIGNED     NOT NULL,
    resuelto_por     INT UNSIGNED     NULL,
    creado_en        DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    clasificado_en   DATETIME(3)      NULL,
    resuelto_en      DATETIME(3)      NULL,
    -- Un punto de acceso captura un vehículo a la vez, una persona tiene un solo evento abierto y
    -- un documento no registrado genera un solo aviso aunque se escanee varias veces
    captura_puesto   TINYINT UNSIGNED AS (IF(estado = 'EN_CAPTURA', id_puesto, NULL)) STORED,
    abierto_persona  INT UNSIGNED     AS (IF(estado IN ('EN_CAPTURA', 'PENDIENTE_PORTERO'), id_estudiante, NULL)) STORED,
    abierto_documento VARCHAR(20)     AS (IF(estado IN ('EN_CAPTURA', 'PENDIENTE_PORTERO') AND id_estudiante IS NULL, documento_leido, NULL)) STORED,
    PRIMARY KEY (id_evento),
    CONSTRAINT uq_eventos_captura_puesto  UNIQUE (captura_puesto),
    CONSTRAINT uq_eventos_abierto_persona UNIQUE (abierto_persona),
    CONSTRAINT uq_eventos_abierto_doc     UNIQUE (abierto_documento),
    KEY ix_eventos_estado (estado, creado_en),
    KEY ix_eventos_persona (id_estudiante, creado_en),
    KEY ix_eventos_visita (id_visita),
    KEY ix_eventos_creado_por (creado_por),
    KEY ix_eventos_resuelto_por (resuelto_por),
    CONSTRAINT fk_eventos_puesto     FOREIGN KEY (id_puesto)     REFERENCES puestos_camara (id_puesto),
    CONSTRAINT fk_eventos_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_eventos_visita     FOREIGN KEY (id_visita)     REFERENCES visitas (id_visita),
    CONSTRAINT fk_eventos_creado     FOREIGN KEY (creado_por)    REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_eventos_resuelto   FOREIGN KEY (resuelto_por)  REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_eventos_confianza  CHECK (confianza IS NULL OR (confianza >= 0 AND confianza <= 1)),
    CONSTRAINT ck_eventos_segunda    CHECK (segunda IS NULL OR (segunda >= 0 AND segunda <= 1)),
    CONSTRAINT ck_eventos_identidad  CHECK (id_estudiante IS NOT NULL OR documento_leido IS NOT NULL),
    CONSTRAINT ck_eventos_pendiente  CHECK (estado <> 'PENDIENTE_PORTERO' OR motivo IS NOT NULL),
    CONSTRAINT ck_eventos_confirmado CHECK (estado <> 'CONFIRMADO' OR (tipo_confirmado IS NOT NULL AND metodo IS NOT NULL)),
    CONSTRAINT ck_eventos_manual     CHECK (metodo <> 'MANUAL' OR resuelto_por IS NOT NULL)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Visitas: eventos de entrada y salida que las respaldan --------------------------------
ALTER TABLE visitas
    ADD COLUMN id_evento_entrada BIGINT UNSIGNED NULL AFTER id_cobro,
    ADD COLUMN id_evento_salida  BIGINT UNSIGNED NULL AFTER id_evento_entrada,
    ADD KEY ix_visitas_evento_entrada (id_evento_entrada),
    ADD KEY ix_visitas_evento_salida (id_evento_salida),
    ADD CONSTRAINT fk_visitas_evento_entrada FOREIGN KEY (id_evento_entrada) REFERENCES eventos_acceso (id_evento),
    ADD CONSTRAINT fk_visitas_evento_salida  FOREIGN KEY (id_evento_salida)  REFERENCES eventos_acceso (id_evento);

-- Movimientos de saldo: reverso controlado de una recarga -------------------------------
ALTER TABLE movimientos_saldo
    MODIFY tipo ENUM('RECARGA', 'DEBITO', 'REINTEGRO', 'REVERSO_RECARGA') NOT NULL,
    ADD COLUMN id_movimiento_origen BIGINT UNSIGNED NULL AFTER id_pago,
    ADD COLUMN motivo VARCHAR(255) NULL AFTER referencia,
    DROP CONSTRAINT ck_movimientos_cuadre;

ALTER TABLE movimientos_saldo
    ADD COLUMN reverso_unico BIGINT UNSIGNED AS (IF(tipo = 'REVERSO_RECARGA', id_movimiento_origen, NULL)) STORED,
    ADD CONSTRAINT uq_movimientos_reverso UNIQUE (reverso_unico),
    ADD KEY ix_movimientos_origen (id_movimiento_origen),
    ADD CONSTRAINT fk_movimientos_origen FOREIGN KEY (id_movimiento_origen) REFERENCES movimientos_saldo (id_movimiento),
    ADD CONSTRAINT ck_movimientos_cuadre CHECK (
        (tipo IN ('DEBITO', 'REVERSO_RECARGA') AND saldo_nuevo = saldo_anterior - monto)
     OR (tipo IN ('RECARGA', 'REINTEGRO') AND saldo_nuevo = saldo_anterior + monto)
    ),
    ADD CONSTRAINT ck_movimientos_reverso CHECK (
        tipo <> 'REVERSO_RECARGA' OR (id_movimiento_origen IS NOT NULL AND registrado_por IS NOT NULL AND motivo IS NOT NULL)
    );

-- Bitácora de importaciones de la base institucional (solo se inserta) -----------------
CREATE TABLE importaciones (
    id_importacion INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    archivo        VARCHAR(160)  NOT NULL,
    sha256         CHAR(64)      NOT NULL,
    modo           ENUM('VALIDAR', 'APLICAR') NOT NULL,
    filas          INT UNSIGNED  NOT NULL DEFAULT 0,
    creadas        INT UNSIGNED  NOT NULL DEFAULT 0,
    actualizadas   INT UNSIGNED  NOT NULL DEFAULT 0,
    sin_cambios    INT UNSIGNED  NOT NULL DEFAULT 0,
    rechazadas     INT UNSIGNED  NOT NULL DEFAULT 0,
    errores_json   LONGTEXT      NULL,
    realizado_por  INT UNSIGNED  NULL,
    creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_importacion),
    KEY ix_importaciones_fecha (creado_en),
    CONSTRAINT fk_importaciones_usuario FOREIGN KEY (realizado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_importaciones_json CHECK (errores_json IS NULL OR JSON_VALID(errores_json))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Disparadores: los eventos cerrados no cambian y nada se borra -----------------------
DROP TRIGGER IF EXISTS tr_eventos_cerrados;
DROP TRIGGER IF EXISTS tr_eventos_no_delete;
DROP TRIGGER IF EXISTS tr_importaciones_no_delete;

DELIMITER $$

CREATE TRIGGER tr_eventos_cerrados BEFORE UPDATE ON eventos_acceso FOR EACH ROW
BEGIN
    IF OLD.estado IN ('CONFIRMADO', 'RECHAZADO', 'EXPIRADO') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un evento de acceso cerrado no se puede modificar';
    END IF;
    IF NOT (NEW.tipo <=> OLD.tipo) OR NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.creado_por <=> OLD.creado_por) OR NOT (NEW.creado_en <=> OLD.creado_en) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La identidad y el origen de un evento no se modifican';
    END IF;
END$$

CREATE TRIGGER tr_eventos_no_delete BEFORE DELETE ON eventos_acceso FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los eventos de acceso no se borran';
END$$

CREATE TRIGGER tr_importaciones_no_delete BEFORE DELETE ON importaciones FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitácora de importaciones no se borra';
END$$

DELIMITER ;
