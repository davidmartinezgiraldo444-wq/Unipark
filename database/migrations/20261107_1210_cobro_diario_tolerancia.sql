-- Migración: 20261107_1210_cobro_diario_tolerancia
-- Autor: UniPark
-- Motivo: el cobro pasa de "uno por visita" a "uno por persona y por día", con 15 minutos de
--         tolerancia desde la entrada, débito automático del saldo y pagos en Caja de lo pendiente.
--         Se agregan bicicleta y patineta (se registran, no tienen tarifa) y los visitantes
--         (se registran, no generan cobros, pueden tener varias visitas abiertas).
--         Las visitas y pagos existentes se conservan: quedan marcados como LEGADO y siguen
--         la regla anterior (un pago por visita) hasta que salgan.
-- Reversión: no se ofrece reversión automática; restaurar el respaldo tomado antes de aplicar.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------------
-- Tipos de vehículo y categorías: la misma definición en todas las tablas que se
-- relacionan por llave foránea compuesta (visitas, tarifas, cobros_diarios).
-- ---------------------------------------------------------------------------------
ALTER TABLE visitas DROP FOREIGN KEY fk_visitas_tarifa;

ALTER TABLE tarifas
    MODIFY tipo_vehiculo ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NOT NULL,
    MODIFY categoria ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL,
    -- Solo carro y moto tienen tarifa; los visitantes no pagan.
    ADD CONSTRAINT ck_tarifas_tipo      CHECK (tipo_vehiculo IN ('CARRO', 'MOTO')),
    ADD CONSTRAINT ck_tarifas_categoria CHECK (categoria IN ('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR'));

-- ---------------------------------------------------------------------------------
-- Visitas
-- ---------------------------------------------------------------------------------
ALTER TABLE visitas
    DROP INDEX uq_visitas_abierta,
    DROP COLUMN visita_abierta;

ALTER TABLE visitas
    MODIFY tipo_vehiculo ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NOT NULL,
    MODIFY categoria ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL,
    MODIFY id_tarifa INT UNSIGNED NULL,
    MODIFY tarifa_aplicada DECIMAL(10, 2) NULL,
    -- Cómo se obtuvo el tipo de vehículo: cámara con IA o clasificación manual del portero.
    ADD COLUMN tipo_origen ENUM('IA', 'MANUAL') NOT NULL DEFAULT 'MANUAL' AFTER tipo_vehiculo,
    -- Fin de los 15 minutos de tolerancia (entrada + tolerancia configurada).
    ADD COLUMN tolerancia_hasta DATETIME NULL AFTER entrada_por,
    -- Resultado de la liquidación diaria de la visita.
    ADD COLUMN liquidacion ENUM('LEGADO', 'EN_TOLERANCIA', 'SALIDA_EN_TOLERANCIA', 'NO_APLICA', 'COBRADA', 'CUBIERTA')
        NOT NULL DEFAULT 'LEGADO' AFTER estado,
    ADD COLUMN id_cobro INT UNSIGNED NULL AFTER liquidacion;

-- Las filas existentes quedaron como LEGADO; las nuevas empiezan en tolerancia.
ALTER TABLE visitas ALTER COLUMN liquidacion SET DEFAULT 'EN_TOLERANCIA';

-- Una visita abierta por persona registrada. Los visitantes no tienen esta restricción.
ALTER TABLE visitas
    ADD COLUMN visita_abierta INT UNSIGNED AS (IF(estado = 'EN_CURSO' AND categoria <> 'VISITANTE', id_estudiante, NULL)) STORED,
    ADD CONSTRAINT uq_visitas_abierta UNIQUE (visita_abierta),
    ADD KEY ix_visitas_liquidacion (liquidacion, tolerancia_hasta),
    ADD KEY ix_visitas_cobro (id_cobro),
    ADD CONSTRAINT fk_visitas_tarifa FOREIGN KEY (id_tarifa, tipo_vehiculo, categoria)
        REFERENCES tarifas (id_tarifa, tipo_vehiculo, categoria),
    ADD CONSTRAINT ck_visitas_legado CHECK (liquidacion <> 'LEGADO' OR (id_tarifa IS NOT NULL AND tarifa_aplicada IS NOT NULL));

-- ---------------------------------------------------------------------------------
-- Cobro diario: uno vigente por persona y fecha (cobro_vigente es UNIQUE).
-- ---------------------------------------------------------------------------------
CREATE TABLE cobros_diarios (
    id_cobro         INT UNSIGNED                              NOT NULL AUTO_INCREMENT,
    id_estudiante    INT UNSIGNED                              NOT NULL,
    fecha            DATE                                      NOT NULL,
    id_visita        INT UNSIGNED                              NOT NULL,
    tipo_vehiculo    ENUM('CARRO', 'MOTO', 'BICICLETA', 'PATINETA') NOT NULL,
    categoria        ENUM('ESTUDIANTE', 'PROFESOR', 'TRABAJADOR', 'VISITANTE') NOT NULL,
    id_tarifa        INT UNSIGNED                              NOT NULL,
    valor            DECIMAL(10, 2)                            NOT NULL,
    estado           ENUM('PENDIENTE', 'PAGADO', 'ANULADO')    NOT NULL DEFAULT 'PENDIENTE',
    generado_en      DATETIME                                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pagado_en        DATETIME                                  NULL,
    notificado_en    DATETIME                                  NULL,
    anulado_en       DATETIME                                  NULL,
    anulado_por      INT UNSIGNED                              NULL,
    motivo_anulacion VARCHAR(255)                              NULL,
    cobro_vigente    VARCHAR(24) AS (IF(estado <> 'ANULADO', CONCAT(id_estudiante, '@', fecha), NULL)) STORED,
    PRIMARY KEY (id_cobro),
    CONSTRAINT uq_cobros_vigente UNIQUE (cobro_vigente),
    KEY ix_cobros_estado (estado, fecha),
    KEY ix_cobros_persona (id_estudiante, fecha),
    KEY ix_cobros_visita (id_visita),
    KEY ix_cobros_tarifa (id_tarifa, tipo_vehiculo, categoria),
    KEY ix_cobros_anulado_por (anulado_por),
    CONSTRAINT fk_cobros_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_cobros_visita     FOREIGN KEY (id_visita)     REFERENCES visitas (id_visita),
    CONSTRAINT fk_cobros_tarifa     FOREIGN KEY (id_tarifa, tipo_vehiculo, categoria) REFERENCES tarifas (id_tarifa, tipo_vehiculo, categoria),
    CONSTRAINT fk_cobros_anulado    FOREIGN KEY (anulado_por)   REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_cobros_valor  CHECK (valor >= 0),
    CONSTRAINT ck_cobros_estado CHECK (
        (estado = 'PENDIENTE' AND pagado_en IS NULL AND anulado_en IS NULL)
     OR (estado = 'PAGADO'    AND pagado_en IS NOT NULL AND anulado_en IS NULL)
     OR (estado = 'ANULADO'   AND anulado_en IS NOT NULL AND anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE visitas
    ADD CONSTRAINT fk_visitas_cobro FOREIGN KEY (id_cobro) REFERENCES cobros_diarios (id_cobro),
    ADD CONSTRAINT ck_visitas_cobro CHECK (liquidacion NOT IN ('COBRADA', 'CUBIERTA') OR id_cobro IS NOT NULL);

-- ---------------------------------------------------------------------------------
-- Pagos: ahora pagan un cobro diario (o una visita LEGADO). El débito automático del
-- saldo no tiene cajero (registrado_por NULL) y usa el medio SALDO.
-- ---------------------------------------------------------------------------------
ALTER TABLE pagos
    DROP FOREIGN KEY fk_pagos_visita,
    DROP FOREIGN KEY fk_pagos_usuario;

ALTER TABLE pagos
    MODIFY id_visita INT UNSIGNED NULL,
    MODIFY registrado_por INT UNSIGNED NULL,
    MODIFY medio_pago ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO', 'SALDO') NOT NULL,
    ADD COLUMN id_cobro INT UNSIGNED NULL AFTER id_visita,
    ADD COLUMN pago_cobro_valido INT UNSIGNED AS (IF(estado = 'PAGADO', id_cobro, NULL)) STORED;

ALTER TABLE pagos
    ADD CONSTRAINT uq_pagos_cobro_valido UNIQUE (pago_cobro_valido),
    ADD KEY ix_pagos_cobro (id_cobro),
    ADD CONSTRAINT fk_pagos_visita  FOREIGN KEY (id_visita)      REFERENCES visitas (id_visita),
    ADD CONSTRAINT fk_pagos_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario),
    ADD CONSTRAINT fk_pagos_cobro   FOREIGN KEY (id_cobro)       REFERENCES cobros_diarios (id_cobro),
    ADD CONSTRAINT ck_pagos_destino CHECK ((id_visita IS NULL) <> (id_cobro IS NULL)),
    ADD CONSTRAINT ck_pagos_cajero  CHECK (registrado_por IS NOT NULL OR medio_pago = 'SALDO');

-- ---------------------------------------------------------------------------------
-- Movimientos de saldo: recargas en Caja, débitos automáticos y reintegros.
-- Es un libro inmutable; estudiantes.saldo guarda el saldo vigente.
-- ---------------------------------------------------------------------------------
CREATE TABLE movimientos_saldo (
    id_movimiento   BIGINT UNSIGNED                              NOT NULL AUTO_INCREMENT,
    id_estudiante   INT UNSIGNED                                 NOT NULL,
    tipo            ENUM('RECARGA', 'DEBITO', 'REINTEGRO')       NOT NULL,
    monto           DECIMAL(10, 2)                               NOT NULL,
    saldo_anterior  DECIMAL(10, 2)                               NOT NULL,
    saldo_nuevo     DECIMAL(10, 2)                               NOT NULL,
    id_cobro        INT UNSIGNED                                 NULL,
    id_pago         INT UNSIGNED                                 NULL,
    medio_pago      ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO') NULL,
    referencia      VARCHAR(60)                                  NULL,
    consecutivo     VARCHAR(12)                                  NULL,
    registrado_por  INT UNSIGNED                                 NULL,
    creado_en       DATETIME                                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    debito_unico    INT UNSIGNED AS (IF(tipo = 'DEBITO', id_cobro, NULL)) STORED,
    reintegro_unico INT UNSIGNED AS (IF(tipo = 'REINTEGRO', id_pago, NULL)) STORED,
    PRIMARY KEY (id_movimiento),
    CONSTRAINT uq_movimientos_debito    UNIQUE (debito_unico),
    CONSTRAINT uq_movimientos_reintegro UNIQUE (reintegro_unico),
    CONSTRAINT uq_movimientos_consec    UNIQUE (consecutivo),
    KEY ix_movimientos_persona (id_estudiante, creado_en),
    KEY ix_movimientos_cajero (registrado_por, creado_en),
    KEY ix_movimientos_cobro (id_cobro),
    KEY ix_movimientos_pago (id_pago),
    CONSTRAINT fk_movimientos_estudiante FOREIGN KEY (id_estudiante)  REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_movimientos_cobro      FOREIGN KEY (id_cobro)       REFERENCES cobros_diarios (id_cobro),
    CONSTRAINT fk_movimientos_pago       FOREIGN KEY (id_pago)        REFERENCES pagos (id_pago),
    CONSTRAINT fk_movimientos_usuario    FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario),
    CONSTRAINT ck_movimientos_monto  CHECK (monto > 0),
    CONSTRAINT ck_movimientos_saldo  CHECK (saldo_nuevo >= 0),
    CONSTRAINT ck_movimientos_cuadre CHECK (
        (tipo = 'DEBITO' AND saldo_nuevo = saldo_anterior - monto)
     OR (tipo IN ('RECARGA', 'REINTEGRO') AND saldo_nuevo = saldo_anterior + monto)
    ),
    CONSTRAINT ck_movimientos_debito    CHECK (tipo <> 'DEBITO' OR (id_cobro IS NOT NULL AND id_pago IS NOT NULL)),
    CONSTRAINT ck_movimientos_recarga   CHECK (tipo <> 'RECARGA' OR (medio_pago IS NOT NULL AND registrado_por IS NOT NULL AND consecutivo IS NOT NULL)),
    CONSTRAINT ck_movimientos_reintegro CHECK (tipo <> 'REINTEGRO' OR id_pago IS NOT NULL)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------
-- Disparadores: protección del historial con las columnas nuevas.
-- <=> compara también valores NULL.
-- ---------------------------------------------------------------------------------
DROP TRIGGER IF EXISTS tr_visitas_inmutables;
DROP TRIGGER IF EXISTS tr_pagos_inmutables;

DELIMITER $$

CREATE TRIGGER tr_visitas_inmutables BEFORE UPDATE ON visitas FOR EACH ROW
BEGIN
    IF OLD.estado <> 'EN_CURSO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una visita cerrada o anulada no se puede modificar';
    END IF;
    IF NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.tipo_vehiculo <=> OLD.tipo_vehiculo)
       OR NOT (NEW.tipo_origen <=> OLD.tipo_origen)
       OR NOT (NEW.categoria <=> OLD.categoria)
       OR NOT (NEW.id_tarifa <=> OLD.id_tarifa)
       OR NOT (NEW.tarifa_aplicada <=> OLD.tarifa_aplicada)
       OR NOT (NEW.entrada_en <=> OLD.entrada_en)
       OR NOT (NEW.entrada_por <=> OLD.entrada_por)
       OR NOT (NEW.tolerancia_hasta <=> OLD.tolerancia_hasta) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los datos de entrada y la tarifa aplicada no se modifican';
    END IF;
    IF OLD.id_cobro IS NOT NULL AND NOT (NEW.id_cobro <=> OLD.id_cobro) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro asociado a la visita no se cambia';
    END IF;
END$$

CREATE TRIGGER tr_pagos_inmutables BEFORE UPDATE ON pagos FOR EACH ROW
BEGIN
    IF OLD.estado <> 'PAGADO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un pago anulado no se puede modificar';
    END IF;
    IF NOT (NEW.id_visita <=> OLD.id_visita)
       OR NOT (NEW.id_cobro <=> OLD.id_cobro)
       OR NOT (NEW.monto <=> OLD.monto)
       OR NOT (NEW.pagado_en <=> OLD.pagado_en)
       OR NOT (NEW.registrado_por <=> OLD.registrado_por)
       OR NOT (NEW.medio_pago <=> OLD.medio_pago)
       OR NOT (NEW.consecutivo <=> OLD.consecutivo) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un pago confirmado solo puede anularse';
    END IF;
END$$

CREATE TRIGGER tr_cobros_no_delete BEFORE DELETE ON cobros_diarios FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los cobros no se borran: use anulacion auditada';
END$$

CREATE TRIGGER tr_cobros_inmutables BEFORE UPDATE ON cobros_diarios FOR EACH ROW
BEGIN
    IF OLD.estado = 'ANULADO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un cobro anulado no se puede modificar';
    END IF;
    IF NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.fecha <=> OLD.fecha)
       OR NOT (NEW.id_visita <=> OLD.id_visita)
       OR NOT (NEW.tipo_vehiculo <=> OLD.tipo_vehiculo)
       OR NOT (NEW.categoria <=> OLD.categoria)
       OR NOT (NEW.id_tarifa <=> OLD.id_tarifa)
       OR NOT (NEW.valor <=> OLD.valor)
       OR NOT (NEW.generado_en <=> OLD.generado_en) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El valor y los datos de un cobro no se modifican';
    END IF;
END$$

CREATE TRIGGER tr_movimientos_no_update BEFORE UPDATE ON movimientos_saldo FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de saldo no se modifican';
END$$

CREATE TRIGGER tr_movimientos_no_delete BEFORE DELETE ON movimientos_saldo FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de saldo no se borran';
END$$

DELIMITER ;
