# Migraciones

`database/schema.sql` crea la base **desde cero** (borra y recrea las tablas). Sirve para instalaciones nuevas, pruebas y desarrollo.
Cuando el sistema ya tenga datos reales, los cambios al esquema se aplican con **migraciones**: archivos que modifican la estructura sin perder información.

## Convenciones

- **Nombre:** `AAAAMMDD_HHMM_descripcion_corta.sql`, por ejemplo `20261101_0900_agregar_medio_nequi.sql`. El orden alfabético es el orden de aplicación.
- **Encabezado obligatorio:**
  ```sql
  -- Migración: 20261101_0900_agregar_medio_nequi
  -- Autor: nombre
  -- Motivo: la universidad aprobó Nequi como medio de pago (decisión pendiente, sección 15)
  -- Reversión: ver bloque final
  ```
- **Una migración, un cambio.** No mezclar cambios de estructura con cargas de datos.
- **Nunca editar una migración ya aplicada.** Si hay un error, se corrige con una migración nueva.
- **Actualizar también `schema.sql`**, el diccionario de datos y el diagrama, para que una instalación nueva quede igual que una base migrada.
- **Probar antes de aplicar:** sacar un respaldo (`scripts/backup-db.bat` o `.sh`), aplicar en `unipark_test`, ejecutar `php tests/run.php` y solo después aplicar en la base real.
- **Bloque de reversión** al final, comentado, con las sentencias que deshacen el cambio cuando sea posible.

## Cambios que requieren cuidado

| Cambio | Riesgo | Recomendación |
|---|---|---|
| Agregar un valor a un `ENUM` (p. ej. un medio de pago) | Bajo | `ALTER TABLE … MODIFY` agregando el valor **al final** de la lista |
| Quitar un valor de un `ENUM` | Alto | Solo si ninguna fila lo usa; las filas históricas no se borran |
| Cambiar `visita_abierta` o `pago_valido` | Crítico | Son las restricciones de RN-07 y RN-09; ejecutar las pruebas de concurrencia (CP-03) |
| Disparadores | Medio | `DROP TRIGGER` + `CREATE TRIGGER` en la misma migración, usando `DELIMITER` |

## Registro de migraciones aplicadas

| Archivo | Fecha de aplicación | Aplicada por |
|---|---|---|
| *(ninguna aún: la versión 1.0 se instala con `schema.sql`)* | | |
