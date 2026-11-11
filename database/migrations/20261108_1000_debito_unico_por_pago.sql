-- Migración: 20261108_1000_debito_unico_por_pago
-- Autor: UniPark
-- Motivo: corrige el error E1 de la auditoría del 08/11/2026.
--   debito_unico se calculaba con id_cobro: «un solo DEBITO por cobro, para siempre». Al anular un pago
--   hecho con saldo, el cobro vuelve a PENDIENTE y el siguiente débito de ese cobro (por ejemplo, tras una
--   recarga) chocaba con el DEBITO anterior: la recarga completa fallaba con un error 500.
--   Ahora debito_unico se calcula con id_pago: «un DEBITO por pago». No se pierde ninguna garantía:
--   el doble cobro sigue impedido porque un cobro tiene como máximo UN pago válido (uq_pagos_cobro_valido),
--   y cada débito va atado a su propio pago (ck_movimientos_debito ya exige id_pago en todo DEBITO).
-- Datos: no modifica filas; la columna generada se recalcula desde id_pago (todo DEBITO ya lo tiene).
-- Reversión: ver bloque final.

ALTER TABLE movimientos_saldo DROP INDEX uq_movimientos_debito;

ALTER TABLE movimientos_saldo DROP COLUMN debito_unico;

ALTER TABLE movimientos_saldo
    ADD COLUMN debito_unico INT UNSIGNED AS (IF(tipo = 'DEBITO', id_pago, NULL)) STORED AFTER creado_en,
    ADD CONSTRAINT uq_movimientos_debito UNIQUE (debito_unico);

-- Reversión (solo si no hay dos DEBITO del mismo cobro):
-- ALTER TABLE movimientos_saldo DROP INDEX uq_movimientos_debito, DROP COLUMN debito_unico;
-- ALTER TABLE movimientos_saldo ADD COLUMN debito_unico INT UNSIGNED AS (IF(tipo = 'DEBITO', id_cobro, NULL)) STORED AFTER creado_en,
--     ADD CONSTRAINT uq_movimientos_debito UNIQUE (debito_unico);
