# Manual del Administrador

El Administrador gestiona cuentas, estudiantes y tarifas, consulta reportes y auditoría, y es el único que anula pagos o visitas. **No registra entradas, pagos ni salidas ordinarias**: esas operaciones son de Portería y Caja.

## Panel

Muestra los carros y motos dentro, las visitas pendientes de pago, el recaudo del día, las visitas abiertas y las tarifas vigentes.

## Usuarios

**Crear una cuenta**
1. **Usuarios → Nuevo usuario**: nombre completo, usuario de acceso y rol.
2. Al guardar, el sistema muestra una **contraseña temporal una sola vez**. Entrégala a la persona por un medio seguro; deberá cambiarla en su primer ingreso.

**Gestionar una cuenta**: desde **Gestionar** puedes:

| Acción | Efecto |
|---|---|
| Cambiar nombre o rol | Se aplica en la siguiente acción de la persona, sin esperar a que cierre sesión |
| Generar contraseña temporal | La contraseña anterior deja de servir de inmediato |
| Desactivar | La persona no puede ingresar y pierde la sesión abierta. Su historial se conserva. |
| Activar | Restablece el acceso |

**Restricciones**:
- No puedes cambiar tu propio rol ni desactivar tu propia cuenta.
- Siempre debe quedar al menos un administrador activo.
- El usuario de acceso no se modifica, para conservar la trazabilidad.

Las cuentas son individuales: no crees cuentas compartidas como «portería-turno».

## Estudiantes y profesores

- **Registrar persona**: documento (único), **categoría** (Estudiante o Profesor), nombres y apellidos. La categoría define la tarifa de cada visita. El correo y el teléfono son opcionales; regístralos solo si son necesarios.
- **Editar** desde el listado o el historial.
- **Desactivar**: el estudiante no puede registrar nuevas entradas y su historial se conserva. No existe la opción de borrar.
- **Historial**: entradas, pagos (incluidos los anulados), salidas, tipo de vehículo y estado de cada visita.

## Tarifas

Hay cuatro tarifas, una por vehículo y categoría:

| Vehículo | Estudiante | Profesor |
|---|---|---|
| Carro | $ 2.050 | $ 2.800 |
| Moto | $ 1.050 | $ 1.800 |

1. En **Tarifas**, escribe el nuevo valor en la fila de la categoría y pulsa **Guardar**.
2. La nueva tarifa rige **desde ese momento** y solo para las nuevas entradas. Las visitas ya registradas conservan el valor que se les aplicó (RN-11).
3. El historial muestra cada vigencia por vehículo y categoría, quién la registró y cuántas visitas la usaron.

## Contraseñas

Todas las cuentas iniciales usan `Demo2026*`. Cada persona la cambia con **Cambiar contraseña** (menú, abajo). Si alguien la olvida, genera una temporal desde **Usuarios → Gestionar → Generar contraseña temporal**.

Una tarifa no se edita ni se borra: cada cambio crea una nueva vigencia y queda en la auditoría con el valor anterior y el nuevo.

## Anulaciones

Las anulaciones son excepcionales y siempre llevan **motivo obligatorio** (mínimo 10 caracteres). Se hacen desde el **historial del estudiante**.

| Anulación | Cuándo | Efecto |
|---|---|---|
| **Anular pago** | Cobro con datos equivocados, reverso o reembolso autorizado | El pago queda `ANULADO` con fecha, motivo y tu nombre. Si la visita sigue en curso, vuelve a quedar pendiente y puede cobrarse otra vez. |
| **Anular visita** | Entrada registrada por error (estudiante o tipo equivocado) | La visita queda `ANULADA` y el estudiante puede volver a ingresar. Solo es posible si la visita no tiene un pago válido: anula primero el pago. |

Ninguna anulación borra filas: el registro original permanece y la acción queda en la auditoría.

## Reportes

| Pestaña | Contenido |
|---|---|
| Visitas abiertas | Vehículos dentro ahora y tiempo transcurrido (informativo) |
| Entradas y salidas | Por fechas, tipo, estado, pago y funcionario |
| Pagos | Por fechas, medio, estado y cajero, con total de pagos válidos |
| Recaudo | Total por día, cajero y medio de pago |

**Exportar CSV** descarga el reporte con los filtros aplicados. El documento del estudiante sale enmascarado (`••••••0007`) y cada exportación queda en la auditoría (RF-20).

## Auditoría

Filtra por fechas, acción, resultado y usuario. Acciones principales:

| Acción | Se registra cuando |
|---|---|
| `LOGIN_EXITOSO`, `LOGIN_FALLIDO`, `LOGIN_BLOQUEADO`, `LOGOUT`, `SESION_EXPIRADA` | Accesos |
| `ENTRADA_REGISTRADA`, `ENTRADA_RECHAZADA` | Entradas y duplicados rechazados |
| `PAGO_REGISTRADO`, `PAGO_RECHAZADO`, `PAGO_ANULADO` | Pagos |
| `SALIDA_AUTORIZADA`, `SALIDA_DENEGADA` | Salidas |
| `VISITA_ANULADA` | Anulación de visitas |
| `TARIFA_CAMBIADA` | Cambios de tarifa, con valor anterior y nuevo |
| `USUARIO_*`, `CLAVE_*`, `ESTUDIANTE_*` | Acciones administrativas |
| `CIERRE_CAJA` | Arqueo con total del sistema, efectivo contado y diferencia |
| `ACCESO_DENEGADO`, `CSRF_INVALIDO` | Intentos bloqueados por seguridad |
| `REPORTE_EXPORTADO` | Exportaciones CSV |

La bitácora no se puede modificar ni borrar desde la aplicación, y tampoco desde la base de datos: lo impiden los disparadores.

## Respaldos

Ver [manual de instalación, sección 9](manual-instalacion.md#9-recuperación-ante-fallos). Se recomienda un respaldo diario con `scripts\backup-db.bat` y una prueba de restauración periódica en la base de pruebas.
