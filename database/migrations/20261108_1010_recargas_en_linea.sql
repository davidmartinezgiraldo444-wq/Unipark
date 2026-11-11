-- Migración: 20261108_1010_recargas_en_linea
-- Autor: UniPark
-- Motivo: recarga voluntaria del saldo prepago desde la web mediante una pasarela de pagos (Wompi en sandbox).
--   - movimientos_saldo sigue siendo el ÚNICO libro del saldo: una recarga en línea aprobada es un
--     movimiento RECARGA con medio PASARELA (nuevo valor al final del ENUM).
--   - recargas_en_linea: la operación iniciada por la persona (estado, referencia interna, transacción externa).
--     Una recarga se acredita una sola vez: id_movimiento es UNIQUE y, una vez APROBADA, la fila no cambia.
--   - notificaciones_pasarela: bitácora de webhooks y consultas de conciliación (solo se inserta). No guarda
--     el cuerpo completo ni datos de tarjeta: solo identificadores, estado, monto y resultado.
-- Datos: no modifica saldos ni movimientos existentes.
-- Reversión: ver bloque final.

-- Una RECARGA en Caja exige el cajero que recibió el dinero. Una RECARGA en línea no tiene cajero: exige en
-- cambio la referencia de la transacción de la pasarela (quién la inició queda en recargas_en_linea.id_usuario).
ALTER TABLE movimientos_saldo
    MODIFY medio_pago ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO', 'PASARELA') NULL,
    DROP CONSTRAINT ck_movimientos_recarga;

ALTER TABLE movimientos_saldo
    ADD CONSTRAINT ck_movimientos_recarga CHECK (tipo <> 'RECARGA' OR (medio_pago IS NOT NULL AND consecutivo IS NOT NULL AND (
        (medio_pago <> 'PASARELA' AND registrado_por IS NOT NULL)
     OR (medio_pago = 'PASARELA' AND referencia IS NOT NULL))));

CREATE TABLE recargas_en_linea (
    id_recarga               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    referencia               VARCHAR(40)     NOT NULL,
    id_estudiante            INT UNSIGNED    NOT NULL,
    id_usuario               INT UNSIGNED    NOT NULL,
    proveedor                VARCHAR(20)     NOT NULL,
    entorno                  ENUM('SANDBOX', 'PRODUCCION') NOT NULL,
    monto                    DECIMAL(10, 2)  NOT NULL,
    moneda                   CHAR(3)         NOT NULL DEFAULT 'COP',
    estado                   ENUM('CREADA', 'PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA', 'ERROR', 'CANCELADA', 'EXPIRADA') NOT NULL DEFAULT 'CREADA',
    -- Transacción de la pasarela que fijó el estado actual (verificada por el servidor).
    id_transaccion           VARCHAR(64)     NULL,
    -- Identificador que trajo el navegador al volver del checkout. NO confirma nada: solo permite
    -- que la conciliación del servidor consulte esa transacción a la pasarela.
    id_transaccion_reportada VARCHAR(64)     NULL,
    medio_externo            VARCHAR(30)     NULL,
    detalle_estado           VARCHAR(255)    NULL,
    id_movimiento            BIGINT UNSIGNED NULL,
    creada_en                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en                DATETIME        NOT NULL,
    actualizada_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    aprobada_en              DATETIME        NULL,
    consultada_en            DATETIME        NULL,
    PRIMARY KEY (id_recarga),
    CONSTRAINT uq_recargas_referencia UNIQUE (referencia),
    CONSTRAINT uq_recargas_movimiento UNIQUE (id_movimiento),
    KEY ix_recargas_persona (id_estudiante, creada_en),
    KEY ix_recargas_estado (estado, creada_en),
    KEY ix_recargas_transaccion (id_transaccion),
    CONSTRAINT fk_recargas_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
    CONSTRAINT fk_recargas_usuario    FOREIGN KEY (id_usuario)    REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_recargas_movimiento FOREIGN KEY (id_movimiento) REFERENCES movimientos_saldo (id_movimiento),
    CONSTRAINT ck_recargas_monto      CHECK (monto > 0),
    CONSTRAINT ck_recargas_moneda     CHECK (moneda = 'COP'),
    CONSTRAINT ck_recargas_aprobada   CHECK (estado <> 'APROBADA' OR (id_movimiento IS NOT NULL AND aprobada_en IS NOT NULL))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE notificaciones_pasarela (
    id_notificacion  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proveedor        VARCHAR(20)     NOT NULL,
    origen           ENUM('WEBHOOK', 'CONSULTA') NOT NULL,
    -- SHA-256 del cuerpo recibido: un reenvío idéntico ya procesado se reconoce sin repetir nada.
    huella           CHAR(64)        NULL,
    tipo_evento      VARCHAR(60)     NULL,
    id_transaccion   VARCHAR(64)     NULL,
    referencia       VARCHAR(80)     NULL,
    estado_reportado VARCHAR(20)     NULL,
    monto_centavos   BIGINT          NULL,
    firma_valida     TINYINT(1)      NOT NULL DEFAULT 0,
    resultado        VARCHAR(30)     NOT NULL,
    mensaje          VARCHAR(255)    NULL,
    id_recarga       INT UNSIGNED    NULL,
    recibida_en      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_notificacion),
    CONSTRAINT uq_notif_pasarela_huella UNIQUE (huella),
    KEY ix_notif_pasarela_recarga (id_recarga),
    KEY ix_notif_pasarela_resultado (resultado, recibida_en),
    CONSTRAINT fk_notif_pasarela_recarga FOREIGN KEY (id_recarga) REFERENCES recargas_en_linea (id_recarga)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS tr_recargas_no_delete;
DROP TRIGGER IF EXISTS tr_recargas_inmutables;
DROP TRIGGER IF EXISTS tr_notif_pasarela_no_update;
DROP TRIGGER IF EXISTS tr_notif_pasarela_no_delete;

DELIMITER $$

CREATE TRIGGER tr_recargas_no_delete BEFORE DELETE ON recargas_en_linea FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las recargas en linea no se borran';
END$$

CREATE TRIGGER tr_recargas_inmutables BEFORE UPDATE ON recargas_en_linea FOR EACH ROW
BEGIN
    IF OLD.estado = 'APROBADA' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una recarga aprobada no se modifica: use un movimiento compensatorio';
    END IF;
    IF NOT (NEW.referencia <=> OLD.referencia) OR NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.monto <=> OLD.monto) OR NOT (NEW.moneda <=> OLD.moneda)
       OR NOT (NEW.proveedor <=> OLD.proveedor) OR NOT (NEW.entorno <=> OLD.entorno)
       OR NOT (NEW.creada_en <=> OLD.creada_en) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los datos de origen de una recarga no se modifican';
    END IF;
END$$

CREATE TRIGGER tr_notif_pasarela_no_update BEFORE UPDATE ON notificaciones_pasarela FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora de la pasarela no se modifica';
END$$

CREATE TRIGGER tr_notif_pasarela_no_delete BEFORE DELETE ON notificaciones_pasarela FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora de la pasarela no se borra';
END$$

DELIMITER ;

-- Reversión (solo si no hay recargas en línea; los movimientos PASARELA impedirían quitar el valor del ENUM):
-- DROP TRIGGER tr_notif_pasarela_no_delete; DROP TRIGGER tr_notif_pasarela_no_update;
-- DROP TRIGGER tr_recargas_inmutables; DROP TRIGGER tr_recargas_no_delete;
-- DROP TABLE notificaciones_pasarela; DROP TABLE recargas_en_linea;
-- ALTER TABLE movimientos_saldo DROP CONSTRAINT ck_movimientos_recarga,
--     MODIFY medio_pago ENUM('EFECTIVO', 'TRANSFERENCIA', 'DATAFONO') NULL,
--     ADD CONSTRAINT ck_movimientos_recarga CHECK (tipo <> 'RECARGA' OR (medio_pago IS NOT NULL AND registrado_por IS NOT NULL AND consecutivo IS NOT NULL));
