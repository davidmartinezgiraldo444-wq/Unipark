# Diccionario de datos · UniPark

Motor InnoDB, codificación `utf8mb4` / `utf8mb4_unicode_ci`, zona horaria de sesión `-05:00` (America/Bogota).
Fuente: [`database/schema.sql`](../schema.sql). Diagrama: [`database/diagrams/er-diagram.png`](../diagrams/er-diagram.png).

Convenciones de la columna **Nulo**: `No` = obligatorio; `Sí` = opcional.
**Gen.** = columna generada (`STORED`), calculada por la base de datos; la aplicación nunca la escribe.

---

## estudiantes

Identidad de estudiantes y profesores. Solo datos personales necesarios (RN-01, RNF-10). El nombre de la tabla se conserva del documento base; la categoría distingue a los profesores.

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_estudiante | INT UNSIGNED | No | PK, AUTO_INCREMENT | Identificador interno |
| documento | VARCHAR(20) | No | UNIQUE `uq_estudiantes_documento`; CHECK 5–20 caracteres | Documento de identidad, clave de búsqueda en portería |
| nombres | VARCHAR(80) | No | | Confirmar identidad |
| apellidos | VARCHAR(80) | No | Índice `ix_estudiantes_nombre` (apellidos, nombres) | Confirmar identidad, ordenar listados |
| correo | VARCHAR(120) | Sí | | Contacto, solo si es necesario |
| telefono | VARCHAR(20) | Sí | | Contacto, solo si es necesario |
| categoria | ENUM('ESTUDIANTE','PROFESOR') | No | DEFAULT 'ESTUDIANTE' | Define la tarifa que se aplica en cada visita |
| activo | TINYINT(1) | No | DEFAULT 1; CHECK 0/1 | Un estudiante inactivo no registra entradas |
| creado_en | DATETIME | No | DEFAULT CURRENT_TIMESTAMP | Trazabilidad |
| actualizado_en | DATETIME | No | ON UPDATE CURRENT_TIMESTAMP | Trazabilidad |

## roles

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_rol | TINYINT UNSIGNED | No | PK | Identificador |
| nombre | VARCHAR(40) | No | UNIQUE | Portería, Caja, Administrador, Auditor |
| descripcion | VARCHAR(200) | No | | Descripción del rol |
| activo | TINYINT(1) | No | DEFAULT 1; CHECK 0/1 | Un rol inactivo no otorga permisos ni permite ingresar |

## permisos

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_permiso | SMALLINT UNSIGNED | No | PK | Identificador |
| codigo | VARCHAR(40) | No | UNIQUE | Código verificado por el servidor, p. ej. `ENTRADA_CREAR` |
| descripcion | VARCHAR(200) | No | | Descripción legible |

Catálogo completo y matriz por rol: [`config/permissions.php`](../../config/permissions.php).

## rol_permiso

Relación muchos a muchos entre roles y permisos.

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_rol | TINYINT UNSIGNED | No | PK compuesta; FK → roles | Rol |
| id_permiso | SMALLINT UNSIGNED | No | PK compuesta; FK → permisos | Permiso otorgado |

## usuarios

Cuentas individuales de funcionarios (RF-03). Nunca contraseñas en texto plano (RNF-01).

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_usuario | INT UNSIGNED | No | PK | Identificador |
| nombre | VARCHAR(120) | No | | Nombre del funcionario |
| login | VARCHAR(120) | No | UNIQUE | Usuario o correo para iniciar sesión |
| password_hash | VARCHAR(255) | No | CHECK longitud ≥ 50 | Resultado de `password_hash()` |
| id_rol | TINYINT UNSIGNED | No | FK → roles | Rol asignado |
| activo | TINYINT(1) | No | DEFAULT 1; CHECK 0/1 | Una cuenta inactiva no puede autenticarse |
| debe_cambiar_clave | TINYINT(1) | No | DEFAULT 1; CHECK 0/1 | Obliga a cambiar la contraseña temporal |
| ultimo_acceso | DATETIME | Sí | | Último inicio de sesión exitoso |
| creado_en / actualizado_en | DATETIME | No | | Trazabilidad |

## tarifas

Valor fijo por tipo de vehículo y categoría, con historial de vigencias (RN-03, RF-06, RN-11).

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_tarifa | INT UNSIGNED | No | PK; UNIQUE (id_tarifa, tipo_vehiculo, categoria) | Identificador; la clave compuesta permite la FK desde visitas |
| tipo_vehiculo | ENUM('CARRO','MOTO') | No | | Tipo al que aplica |
| categoria | ENUM('ESTUDIANTE','PROFESOR') | No | | Categoría a la que aplica |
| valor | DECIMAL(10,2) | No | CHECK ≥ 0 | Valor fijo en pesos |
| vigente_desde | DATETIME | No | | Inicio de vigencia |
| vigente_hasta | DATETIME | Sí | CHECK ≥ vigente_desde | Fin de vigencia; NULL = vigente |
| activa | TINYINT(1) | No | DEFAULT 1 | Permite inhabilitar una tarifa sin borrarla |
| creado_por | INT UNSIGNED | No | FK → usuarios | Quién registró la tarifa |
| creado_en | DATETIME | No | | Trazabilidad |
| tarifa_abierta | VARCHAR(20) | Gen. | UNIQUE | `TIPO-CATEGORIA` (p. ej. `CARRO-PROFESOR`) si la tarifa está abierta y activa, si no NULL: **solo una tarifa vigente por tipo y categoría** |

Disparador `tr_tarifas_historicas`: impide editar valor, tipo o inicio de vigencia; un cambio de precio es siempre una nueva fila.

## visitas

Una fila por estancia del estudiante. **Sin placa, marca, color, modelo ni foto** (RN-02).

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_visita | INT UNSIGNED | No | PK | Identificador |
| id_estudiante | INT UNSIGNED | No | FK → estudiantes | Estudiante |
| tipo_vehiculo | ENUM('CARRO','MOTO') | No | FK compuesta con id_tarifa | Único dato del vehículo |
| categoria | ENUM('ESTUDIANTE','PROFESOR') | No | FK compuesta con id_tarifa | Copia de la categoría de la persona al entrar |
| id_tarifa | INT UNSIGNED | No | FK (id_tarifa, tipo_vehiculo, categoria) → tarifas | Tarifa usada; tipo y categoría deben coincidir |
| tarifa_aplicada | DECIMAL(10,2) | No | CHECK ≥ 0 | **Copia histórica** del valor al entrar (RN-11) |
| entrada_en | DATETIME | No | DEFAULT CURRENT_TIMESTAMP (hora del servidor) | Hora de entrada |
| entrada_por | INT UNSIGNED | No | FK → usuarios | Portero que registró la entrada |
| salida_en | DATETIME | Sí | CHECK ≥ entrada_en | Hora de salida |
| salida_por | INT UNSIGNED | Sí | FK → usuarios | Portero que autorizó la salida |
| estado | ENUM('EN_CURSO','CERRADA','ANULADA') | No | DEFAULT 'EN_CURSO'; CHECK de consistencia | Estado de la visita |
| anulada_en / anulada_por / motivo_anulacion | DATETIME / INT / VARCHAR(255) | Sí | Obligatorios si ANULADA | Anulación auditada (RN-12) |
| visita_abierta | INT UNSIGNED | Gen. | UNIQUE `uq_visitas_abierta` | `id_estudiante` si EN_CURSO, si no NULL: **una sola visita abierta por estudiante**, también ante solicitudes simultáneas (RN-07, RN-13) |

CHECK `ck_visitas_estado`:

- `EN_CURSO` → sin salida ni anulación.
- `CERRADA` → con `salida_en` y `salida_por`.
- `ANULADA` → con fecha, usuario y motivo de anulación, sin salida.

Disparadores: `tr_visitas_inmutables` (una visita cerrada o anulada no cambia; los datos de entrada y la tarifa aplicada nunca cambian) y `tr_visitas_no_delete`.

Índices: `ix_visitas_estado` (estado, entrada_en), `ix_visitas_entrada`, `ix_visitas_estudiante` (id_estudiante, entrada_en) para historial y reportes (RNF-06).

## pagos

Un único pago válido por visita, por el monto exacto (RN-09, RF-10, RF-11). No se guardan datos de tarjetas.

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_pago | INT UNSIGNED | No | PK | Identificador |
| id_visita | INT UNSIGNED | No | FK → visitas | Visita pagada |
| monto | DECIMAL(10,2) | No | CHECK ≥ 0 | Igual a `visitas.tarifa_aplicada` (lo asigna el servidor) |
| pagado_en | DATETIME | No | DEFAULT CURRENT_TIMESTAMP | Hora del pago |
| registrado_por | INT UNSIGNED | No | FK → usuarios | Cajero |
| medio_pago | ENUM('EFECTIVO','TRANSFERENCIA','DATAFONO') | No | | Medio permitido (pendiente de confirmar por la universidad) |
| estado | ENUM('PAGADO','ANULADO') | No | DEFAULT 'PAGADO'; CHECK de consistencia | Solo PAGADO permite la salida |
| consecutivo | VARCHAR(12) | No | UNIQUE | Número de comprobante, p. ej. `R00000008` |
| referencia | VARCHAR(60) | Sí | | Referencia de transferencia o autorización del datáfono |
| anulado_en / anulado_por / motivo_anulacion | DATETIME / INT / VARCHAR(255) | Sí | Obligatorios si ANULADO | Reverso auditado |
| pago_valido | INT UNSIGNED | Gen. | UNIQUE `uq_pagos_valido` | `id_visita` si PAGADO, si no NULL: **un solo pago válido por visita**; un pago anulado libera la visita para un nuevo cobro |

Disparadores: `tr_pagos_inmutables` (un pago confirmado solo puede pasar a ANULADO) y `tr_pagos_no_delete`.

## consecutivos

Tabla añadida al modelo preliminar para numerar comprobantes sin huecos ni duplicados.

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| nombre | VARCHAR(20) | No | PK | Serie (`RECIBO`) |
| ultimo | INT UNSIGNED | No | DEFAULT 0 | Último número emitido |

Se incrementa con `UPDATE … SET ultimo = LAST_INSERT_ID(ultimo + 1)` dentro de la transacción del pago: la fila queda bloqueada hasta el `COMMIT`, y si el pago se revierte el número también se revierte.

## auditoria

Bitácora de operaciones sensibles (RN-10, RF-17). Solo admite inserciones.

| Campo | Tipo | Nulo | Restricciones | Finalidad |
|---|---|---|---|---|
| id_auditoria | BIGINT UNSIGNED | No | PK | Identificador |
| id_usuario | INT UNSIGNED | Sí | FK → usuarios | Quién actuó (NULL si no había sesión, p. ej. login fallido) |
| accion | VARCHAR(40) | No | Índice (accion, creado_en) | `ENTRADA_REGISTRADA`, `SALIDA_DENEGADA`, `PAGO_ANULADO`, `TARIFA_CAMBIADA`… |
| entidad | VARCHAR(40) | No | Índice (entidad, id_registro) | Tabla o recurso afectado |
| id_registro | BIGINT UNSIGNED | Sí | | Fila afectada |
| resultado | ENUM('EXITO','DENEGADO','ERROR') | No | | Resultado de la operación |
| motivo | VARCHAR(255) | Sí | | Motivo de anulación o causa del rechazo |
| ip_origen | VARCHAR(45) | Sí | | IPv4 o IPv6 de la solicitud |
| creado_en | DATETIME | No | DEFAULT CURRENT_TIMESTAMP | Hora del servidor |
| detalle_json | LONGTEXT | Sí | CHECK JSON_VALID | Valores anteriores y nuevos, consecutivos, filtros exportados |

Disparadores `tr_auditoria_no_update` y `tr_auditoria_no_delete`. Además, la cuenta de la aplicación solo tiene `SELECT, INSERT` sobre esta tabla.

---

## Privilegios de la cuenta de la aplicación

Otorgados por [`scripts/install.php`](../../scripts/install.php):

| Tablas | Privilegios |
|---|---|
| Todas excepto `auditoria` | `SELECT, INSERT, UPDATE` |
| `auditoria` | `SELECT, INSERT` |

Ninguna tabla admite `DELETE` desde la aplicación (RN-12).
