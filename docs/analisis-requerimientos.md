# Análisis de requerimientos · UniPark

Resumen del documento base *Análisis de requerimientos y diseño preliminar* (Corporación Universitaria Lasallista, Bases de Datos, seguimiento 1, 15/10/2026), con el estado de implementación de cada punto.

## 1. Propósito

Aplicación web para controlar la **entrada, el pago y la salida** de estudiantes que usan el parqueadero universitario en **carro o moto**. El flujo es obligatorio y en este orden:

1. **Portería** registra la entrada.
2. **Caja** registra el pago de una tarifa fija según el tipo de vehículo.
3. **Portería** verifica el pago antes de autorizar la salida.

No se almacena placa, marca, color, modelo ni fotografía del vehículo.

## 2. Alcance

| Incluido | Estado |
|---|---|
| Inicio y cierre de sesión; usuarios, roles y permisos | Implementado |
| Registro y consulta de estudiantes | Implementado |
| Tarifas fijas para CARRO y MOTO con vigencias | Implementado |
| Entrada, cola de pagos, pago, comprobante y salida | Implementado |
| Historial de visitas, reportes y auditoría | Implementado |
| Validaciones, seguridad web, pruebas y despliegue | Implementado (despliegue documentado para XAMPP) |

**Fuera de alcance:** placas e imágenes, cobro por horas, multas, reservas o mensualidades, cámaras, barreras, pasarelas bancarias, integración con el sistema académico y SSO institucional.

## 3. Reglas de negocio

| ID | Regla | Dónde se aplica |
|---|---|---|
| RN-01 | Documento único, datos mínimos | `UNIQUE documento`, `Validator::documento` |
| RN-02 | Sin datos del vehículo; solo CARRO o MOTO | Esquema sin campos de vehículo; `ENUM` |
| RN-03 | Tarifa fija por tipo de vehículo y categoría (estudiante o profesor) | `tarifas`, `ServicioEntrada` |
| RN-04 | Solo Portería registra entradas | Permiso `ENTRADA_CREAR` |
| RN-05 | Solo Caja registra pagos | Permiso `PAGO_REGISTRAR` |
| RN-06 | Salida solo con visita abierta y pago de esa visita | `ReglasVisita::evaluarSalida`, `ServicioSalida` |
| RN-07 | Una visita abierta por estudiante | `UNIQUE visita_abierta` + `SELECT … FOR UPDATE` |
| RN-08 | Sin pago: «Pago pendiente. Diríjase a Caja» | `ReglasVisita::MENSAJES` |
| RN-09 | Un pago válido por visita | `UNIQUE pago_valido` + bloqueo de fila |
| RN-10 | Trazabilidad | Tabla `auditoria`, `AuditService` |
| RN-11 | Tarifa histórica | `visitas.tarifa_aplicada` + disparador |
| RN-12 | No borrar; anular con motivo | Sin `DELETE` en la cuenta + disparadores |
| RN-13 | Concurrencia | Transacción + bloqueo + índice único (probado con 6 procesos) |
| RN-14 | Expiración por inactividad y cierre de sesión | `AuthMiddleware`, `SESSION_IDLE_MINUTES` |

## 4. Roles

| Rol | Puede | No puede |
|---|---|---|
| Portería | Buscar estudiantes, registrar entradas, autorizar salidas, ver visitas abiertas e historial | Registrar pagos, cambiar tarifas, administrar usuarios |
| Caja | Ver pendientes, cobrar, emitir y reimprimir sus comprobantes, cierre de caja | Registrar entradas o salidas, cambiar tarifas |
| Administrador | Usuarios, roles, estudiantes, tarifas, reportes, auditoría, anulaciones con motivo | Registrar entradas, pagos o salidas ordinarias; modificar en silencio |
| Auditor | Reportes, pagos, visitas y bitácora | Crear, modificar, pagar, anular o autorizar |

Matriz detallada: [`config/permissions.php`](../config/permissions.php).

## 5. Requerimientos funcionales

| ID | Requerimiento | Implementación |
|---|---|---|
| RF-01 | Autenticación con mensajes genéricos | `AuthService::intentar` |
| RF-02 | Autorización en el servidor | `RoleMiddleware` en cada ruta |
| RF-03 | Gestión de usuarios | `UsuarioController`, `ServicioUsuario` |
| RF-04 | Registro de estudiantes | `EstudianteController` |
| RF-05 | Búsqueda por documento | Portería y módulo Estudiantes |
| RF-06 | Tarifas con vigencia y autor | `ServicioTarifa` |
| RF-07 | Registro de entrada | `ServicioEntrada::registrar` |
| RF-08 | Rechazo de entrada duplicada | Código `VISITA_ABIERTA` |
| RF-09 | Visitas pendientes en Caja | `VisitaRepository::pendientesDePago` |
| RF-10 | Pago con monto, hora, cajero, medio y consecutivo | `ServicioPago::registrar` |
| RF-11 | Impedir pago duplicado | Código `PAGO_DUPLICADO` |
| RF-12 | Validación de salida | `ServicioSalida::autorizar` |
| RF-13 | Denegación sin pago | No guarda salida; audita `SALIDA_DENEGADA` |
| RF-14 | Registro de salida | Visita `CERRADA` con hora y funcionario |
| RF-15 | Historial del estudiante | `/estudiantes/{id}` |
| RF-16 | Reportes con filtros | `/reportes` |
| RF-17 | Auditoría | `/auditoria` |
| RF-18 | Errores claros, detalles en logs | `Kernel`, `Logger` (`storage/logs`) |
| RF-19 | Cierre de caja por turno | `/caja/cierre` |
| RF-20 | Exportación CSV autorizada | `/reportes/exportar`, documento enmascarado |

## 6. Requerimientos no funcionales

| ID | Requerimiento | Implementación |
|---|---|---|
| RNF-01 | `password_hash` / `password_verify` | `AuthService` |
| RNF-02 | Sesiones seguras | `Session`: regeneración, HttpOnly, SameSite=Strict, Secure en HTTPS |
| RNF-03 | PDO preparado, escape HTML, CSRF | `Repository`, `e()`, `Csrf` |
| RNF-04 | InnoDB, FK, índices únicos, transacciones | `schema.sql`, servicios |
| RNF-05 | Respaldos y restauración probada | `scripts/backup.php`, `restore.php`, CP-14 |
| RNF-06 | Índices para documento, estado, fecha y pagos | Ver diccionario de datos |
| RNF-07 | Interfaz sencilla y adaptable | Vistas responsivas, revisadas en móvil |
| RNF-08 | Hora del servidor y usuario responsable | `NOW()` en MySQL, zona `-05:00` |
| RNF-09 | Capas separadas | Ver [arquitectura](arquitectura.md) |
| RNF-10 | Datos mínimos y documentos enmascarados | `mask_doc()` en listados y exportaciones |
| RNF-11 | Versiones soportadas, HTTPS | PHP ≥ 8.1, MariaDB ≥ 10.4 / MySQL ≥ 8.0.16 |
| RNF-12 | Procedimiento de recuperación | [manual de instalación](manual-instalacion.md#9-recuperación-ante-fallos) |

## 7. Ampliaciones sobre el documento base

| Ampliación | Motivo | Implementación |
|---|---|---|
| Categoría **Profesor** | La universidad definió tarifas distintas para estudiantes y profesores | Campo `categoria` en `estudiantes`, `tarifas` y `visitas`; la tabla `estudiantes` registra ambas categorías |
| Instalador web | Despliegue en XAMPP sin usar la consola | `instalar.php` + `App\Services\Instalador` |
| Cambio de contraseña voluntario | Todas las cuentas inician con `Demo2026*` | Enlace «Cambiar contraseña» en el menú |

## 8. Decisiones pendientes de la universidad

Implementadas con valores provisionales y configurables. Deben confirmarse antes de operar con datos reales:

| Tema | Valor provisional | Dónde se cambia |
|---|---|---|
| Tarifas | Carro: estudiante $2.050, profesor $2.800 · Moto: estudiante $1.050, profesor $1.800 | Módulo Tarifas |
| Tarifa cero | No permitida | `config/app.php` → `permitir_tarifa_cero` |
| Medios de pago | Efectivo, transferencia, datáfono | `config/app.php` y `ENUM` de `pagos.medio_pago` (requiere migración) |
| Expiración de sesión | 15 minutos | `.env` → `SESSION_IDLE_MINUTES` |
| Elegibilidad | Todo estudiante activo registrado | — |
| Anulaciones | Solo Administrador, motivo de 10+ caracteres | `config/permissions.php`, `motivo_min_longitud` |
| Turnos y arqueo | Periodo libre elegido por el cajero | `/caja/cierre` |
| Contingencia y salida de emergencia | **No implementado**; requiere definir el protocolo institucional | — |
| Estudiante sin documento | **No implementado**; requiere procedimiento institucional | — |
