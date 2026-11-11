# Fase 1 · Validación de las migraciones financieras

Fecha: 08/11/2026 · Servidor: MariaDB 10.4.32 (XAMPP) · Base real: `unipark_db`
Estado: **validada y ejecutada en `unipark_db` el 08/11/2026 a las 22:04** (ver sección 8).

## 1. Respaldo

| Dato | Valor |
|---|---|
| Archivo | `storage/backups/unipark_db_fase1_antes_migraciones_20261108_215146.sql` |
| Tamaño | 72,1 KB |
| SHA-256 | `7ccd4f123aae2c4ceddee04bc0d601e3c38aabb57e4337f778ba751352c109c5` |
| Herramienta | `scripts/backup.php` (mysqldump `--single-transaction --routines --triggers`) |
| Contenido | 18 tablas, 14 disparadores, cierre `Dump completed` |

**Restaurable:** se restauró en una base nueva `unipark_fase1_verificacion` (0,7 s) y se comparó con la real:
conteo de filas de las 18 tablas, saldos, los 6 movimientos columna por columna, pagos, cobros, visitas, tarifas,
consecutivos, estructura de todas las tablas y los 14 disparadores → **idénticos**.

Hallazgo de la auditoría resuelto: los 14 disparadores de inmutabilidad **sí existen** en la base real.

## 2. Línea base financiera (antes)

- Saldo total de las personas: **$ 49.500**, igual a la suma de las 6 RECARGA del libro (cuadre exacto).
- 0 DEBITO, 0 cobros diarios, 7 pagos (esquema anterior), 0 descuadres entre saldo y libro.
- 0 DEBITO duplicados (por pago o por cobro), 0 DEBITO sin pago, 0 RECARGA sin cajero.

## 3. Las dos migraciones

| Migración | Sentencias | Qué cambia | Datos afectados |
|---|---|---|---|
| `20261108_1000_debito_unico_por_pago.sql` | 3 | `debito_unico` pasa de `id_cobro` a `id_pago` (error E1) | Ninguno: no hay DEBITO en la base real |
| `20261108_1010_recargas_en_linea.sql` | 12 | Medio `PASARELA`, regla de recarga sin cajero, tablas `recargas_en_linea` y `notificaciones_pasarela`, 4 disparadores | Ninguno: solo estructura |

Compatibilidad con MariaDB 10.4.32 (comprobada ejecutándolas en la copia): `DROP CONSTRAINT`, `CHECK` aplicados,
columnas generadas `STORED` con índice único, disparadores con `SIGNAL`: **sin errores** (0,09 s + 0,14 s).

Riesgo conocido: cada sentencia DDL se confirma sola (MariaDB no revierte DDL). Si una falla a mitad, la base queda
parcialmente migrada → plan de recuperación (sección 6). Entre la 1.ª y la 3.ª sentencia de la migración 1000 la tabla
queda unos milisegundos sin `uq_movimientos_debito`; el doble cobro sigue impedido por `uq_pagos_cobro_valido`, pero se
recomienda aplicarlas sin operación (cron de cobros detenido).

## 4. Resultado del ensayo en la copia

- Huella financiera antes vs después: **0 diferencias** (ningún movimiento duplicado, perdido ni modificado).
- Estructura: solo cambia `movimientos_saldo`; se agregan 2 tablas vacías y 4 disparadores; los 14 existentes intactos.
- Sondas (transacción revertida al final, la copia quedó igual):

| Sonda | Esperado | Resultado |
|---|---|---|
| RECARGA en Caja sin cajero | rechazo | rechazada (`ck_movimientos_recarga`) |
| RECARGA en línea sin referencia | rechazo | rechazada |
| RECARGA en línea sin consecutivo | rechazo | rechazada |
| RECARGA en línea con referencia | aceptada | aceptada |
| RECARGA en Caja con cajero | aceptada | aceptada |
| RECARGA que no cuadra | rechazo | rechazada (`ck_movimientos_cuadre`) |
| DEBITO sin cobro ni pago | rechazo | rechazada |
| UPDATE / DELETE de un movimiento | rechazo | rechazados por disparador |
| DELETE en la bitácora de la pasarela | rechazo | rechazado por disparador |

La corrección de E1 (dos débitos del mismo cobro tras anular un pago con saldo) se probó en `unipark_db_test`
con la suite automática: 170 pruebas, 0 fallidas (`docs/evidencias/resultado-pruebas-recargas-2026-11-08.txt`).

## 5. Procedimiento de ejecución (cuando se autorice)

1. Detener el cron de cobros y avisar que la operación se pausa unos minutos.
2. `php scripts/migrar.php --estado` → deben figurar solo las dos migraciones como pendientes.
3. `php scripts/migrar.php` → saca **otro** respaldo automático, aplica las dos, las registra en `migraciones` y
   otorga privilegios mínimos (las tablas nuevas: `recargas_en_linea` SELECT/INSERT/UPDATE; `notificaciones_pasarela`
   solo SELECT/INSERT).
4. Verificar: saldo total sigue en $ 49.500, 6 movimientos, 18 + 2 tablas, 14 + 4 disparadores.
5. Abrir «Mi saldo» y «Reportes → Recargas en línea» con un usuario de prueba. Reactivar el cron.

`PASARELA_PROVEEDOR` sigue vacío: las recargas en línea quedan apagadas hasta la Fase 2.

## 6. Plan de recuperación

**Opción A — restaurar el respaldo (recomendada ante cualquier duda).** Probada: datos, estructura y disparadores
quedan idénticos al original. Importante: `scripts/restore.php` solo reemplaza las 18 tablas del respaldo; las dos
tablas nuevas **sobreviven** (comprobado). Por eso, antes de restaurar:

```sql
-- Con la cuenta administrativa, sobre unipark_db
DROP TABLE IF EXISTS notificaciones_pasarela;
DROP TABLE IF EXISTS recargas_en_linea;
```

y luego `php scripts/restore.php storage/backups/unipark_db_fase1_antes_migraciones_20261108_215146.sql`
(crea a su vez un respaldo del estado actual antes de restaurar). Si ya hubo recargas en línea reales, **no** se
restaura: se usa la opción B para no perder esos movimientos.

**Opción B — reversión SQL** (bloque «Reversión» al final de cada migración, en orden inverso: 1010 y luego 1000).
Probada en la copia: datos idénticos; la estructura solo difiere en el orden en que se listan dos CHECK (misma
definición). Solo es posible mientras no existan movimientos `PASARELA` ni dos DEBITO del mismo cobro.

Después de cualquiera de las dos: borrar de `migraciones` las filas `20261108_1000…` y `20261108_1010…` solo si se
usó la opción B (con A ya quedan como antes).

## 7. Base auxiliar

`unipark_fase1_verificacion` es una copia creada para esta validación. No la usa la aplicación. Puede eliminarse
(`DROP DATABASE unipark_fase1_verificacion`) cuando se apruebe este informe.

## 8. Ejecución en `unipark_db` (08/11/2026, 22:04, autorizada)

Condiciones previas: base `unipark_db` en MariaDB 10.4.32 · respaldo de la Fase 1 con SHA-256 coincidente · sin
tareas automáticas activas (ninguna tarea programada de UniPark, sin `cobros.php --bucle`, `CRON_TOKEN` vacío, último
latido de pantallas a las 16:45) · 0 transacciones InnoDB abiertas y ninguna conexión de usuario · huella financiera
igual a la de la Fase 1.

| Paso | Resultado |
|---|---|
| `migrar.php --estado` | Solo `20261108_1000` y `20261108_1010` pendientes |
| Respaldo automático | `storage/backups/unipark_db_20261108_220457.sql` · SHA-256 `925bfba5649c929d7934d036759530c86e97a8b4b9ae4970990c76e5d9d64a42` · 18 tablas, 14 disparadores |
| `20261108_1000_debito_unico_por_pago.sql` | ✓ 3 sentencias |
| `20261108_1010_recargas_en_linea.sql` | ✓ 12 sentencias |
| Privilegios | Actualizados en 20 tablas; `recargas_en_linea` SELECT/INSERT/UPDATE, `notificaciones_pasarela` SELECT/INSERT, ningún DELETE |

Verificación posterior:

- Saldo total $ 49.500 antes y después; los 6 movimientos idénticos columna por columna; 0 duplicados, 0 descuadres.
- Única diferencia en datos: `migraciones` pasa de 9 a 11 filas (y `auditoria` crece por los inicios de sesión de prueba).
- 18 tablas originales: solo `movimientos_saldo` cambió (la columna `debito_unico`, el valor `PASARELA` y la regla
  `ck_movimientos_recarga`), igual que en el ensayo. 2 tablas nuevas vacías. 14 disparadores originales intactos + 4 nuevos.
- La estructura resultante es idéntica a la del ensayo en la copia.
- Funcional (Apache real, usuarios de prueba): login de estudiante1, auditor, admin, caja y porteria; Mi cuenta, Mi saldo,
  Recargar saldo, Reportes (incluida «Recargas en línea»), Auditoría, Panel, Estudiantes, Tarifas, Usuarios, Caja, Cierre
  y Portería responden 200; Caja recibe 403 en Mi saldo (correcto). 0 errores en el log.
- Las recargas en línea siguen apagadas (`PASARELA_PROVEEDOR` vacío). No se hizo ninguna recarga.
