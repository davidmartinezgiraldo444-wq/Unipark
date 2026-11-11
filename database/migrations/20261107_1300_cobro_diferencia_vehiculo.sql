-- Migración: 20261107_1300_cobro_diferencia_vehiculo
-- Autor: UniPark
-- Motivo: regla aprobada el 07/11/2026. El cobro del día corresponde a la tarifa MÁS ALTA de los
--         vehículos usados ese día. Si la persona ya pagó una tarifa menor (por ejemplo moto $1.000) y
--         luego usa un vehículo de tarifa mayor (carro $2.000) por más de 15 minutos, se cobra solo la
--         diferencia ($1.000) como un cobro de concepto DIFERENCIA. Nunca un segundo cobro completo.
-- Reversión: no se ofrece; restaurar el respaldo tomado antes de aplicar.

SET NAMES utf8mb4;

-- Cobros: concepto DIARIO (uno vigente por persona y día) o DIFERENCIA (uno vigente por visita).
ALTER TABLE cobros_diarios
    DROP INDEX uq_cobros_vigente,
    DROP COLUMN cobro_vigente;

ALTER TABLE cobros_diarios
    ADD COLUMN concepto ENUM('DIARIO', 'DIFERENCIA') NOT NULL DEFAULT 'DIARIO' AFTER fecha,
    ADD COLUMN cobro_vigente VARCHAR(24) AS (IF(estado <> 'ANULADO' AND concepto = 'DIARIO', CONCAT(id_estudiante, '@', fecha), NULL)) STORED,
    ADD COLUMN diferencia_vigente INT UNSIGNED AS (IF(estado <> 'ANULADO' AND concepto = 'DIFERENCIA', id_visita, NULL)) STORED;

ALTER TABLE cobros_diarios
    ADD CONSTRAINT uq_cobros_vigente UNIQUE (cobro_vigente),
    ADD CONSTRAINT uq_cobros_diferencia UNIQUE (diferencia_vigente);

-- Visitas: la visita que generó una diferencia queda como DIFERENCIA (con su id_cobro).
ALTER TABLE visitas
    MODIFY liquidacion ENUM('LEGADO', 'EN_TOLERANCIA', 'SALIDA_EN_TOLERANCIA', 'NO_APLICA', 'COBRADA', 'CUBIERTA', 'DIFERENCIA')
        NOT NULL DEFAULT 'EN_TOLERANCIA',
    DROP CONSTRAINT ck_visitas_cobro;

ALTER TABLE visitas
    ADD CONSTRAINT ck_visitas_cobro CHECK (liquidacion NOT IN ('COBRADA', 'CUBIERTA', 'DIFERENCIA') OR id_cobro IS NOT NULL);

DROP TRIGGER IF EXISTS tr_cobros_inmutables;

DELIMITER $$

CREATE TRIGGER tr_cobros_inmutables BEFORE UPDATE ON cobros_diarios FOR EACH ROW
BEGIN
    IF OLD.estado = 'ANULADO' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un cobro anulado no se puede modificar';
    END IF;
    IF NOT (NEW.id_estudiante <=> OLD.id_estudiante)
       OR NOT (NEW.fecha <=> OLD.fecha)
       OR NOT (NEW.concepto <=> OLD.concepto)
       OR NOT (NEW.id_visita <=> OLD.id_visita)
       OR NOT (NEW.tipo_vehiculo <=> OLD.tipo_vehiculo)
       OR NOT (NEW.categoria <=> OLD.categoria)
       OR NOT (NEW.id_tarifa <=> OLD.id_tarifa)
       OR NOT (NEW.valor <=> OLD.valor)
       OR NOT (NEW.generado_en <=> OLD.generado_en) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El valor y los datos de un cobro no se modifican';
    END IF;
END$$

DELIMITER ;
