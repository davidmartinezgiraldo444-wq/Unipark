# Análisis de UniPark para el video explicativo (Brag)

Documento generado por un agente de IA (Claude Code, modelo Opus 5.5, skill `/brag-slim` del plugin Brag) como evidencia de la actividad *Crear un video explicativo de un proyecto de GitHub*. Cada afirmación se comprobó leyendo el código y la documentación de la carpeta del proyecto (`C:\xampp\htdocs\UniPark`) el 06/11/2026.

**Repositorio:** <https://github.com/davidmartinezgiraldo444-wq/Uni-Park> · **Video:** [`brag-output/brag.mp4`](../brag-output/brag.mp4) · **Informe de la actividad:** [`INFORME_ACTIVIDAD_BRAG.md`](INFORME_ACTIVIDAD_BRAG.md)

## 1. Identificación del repositorio

| Punto | Resultado comprobado |
|---|---|
| Nombre | `UniPark` (en `composer.json`: `unipark/unipark`) |
| Repositorio de GitHub | <https://github.com/davidmartinezgiraldo444-wq/Uni-Park> (enlace proporcionado por el autor). El 06/11/2026 el repositorio existía pero **estaba vacío**. La copia local analizada (`C:\xampp\htdocs\UniPark`) no tiene Git inicializado ni remoto configurado; el análisis se hizo sobre esa copia local, que es la que se subirá. |
| Tamaño | 131 archivos en `app/`, `assets/`, `config/`, `database/`, `docs/` y `scripts/`, más 20 en `tests/` |
| Archivos principales | `index.php` (punto de entrada), `instalar.php` (instalador web), `app/Kernel.php`, `app/routes.php`, `config/permissions.php`, `database/schema.sql`, `README.md` |
| Tecnologías | PHP ≥ 8.1 sin frameworks ni dependencias externas (solo `pdo`, `pdo_mysql` y `mbstring`), MySQL/MariaDB con InnoDB, HTML, CSS y JavaScript sin librerías, y las fuentes IBM Plex Sans/Mono |
| Documentación | `README.md` y 8 documentos en `docs/` (requerimientos, arquitectura, modelo de datos, manuales de instalación, de usuario y de administrador, plan de pruebas, seguridad), diagrama ER, diccionario de datos y 17 capturas en `docs/evidencias/` |
| Estado | Funcional y probado: `docs/evidencias/resultado-pruebas.txt` registra **78 pruebas, 0 fallidas** (06/11/2026, PHP 8.2.12, MariaDB 10.4.32). Dos decisiones institucionales siguen pendientes (ver sección 11). |

## 2. Objetivo general

Controlar la **entrada, el pago y la salida** de quienes usan el parqueadero universitario en carro o moto, con un flujo obligatorio y en ese orden: Portería registra la entrada, Caja confirma el pago y la salida solo se autoriza cuando ese pago existe.

## 3. Problema que resuelve

Sin un sistema, la portería y la caja de un parqueadero trabajan con registros separados. Un vehículo puede salir sin pagar, una visita puede cobrarse dos veces, las tarifas cambian sin dejar rastro y nadie sabe quién autorizó cada operación. UniPark une los tres pasos en una sola visita, impide saltarse el pago y deja trazabilidad completa sin recolectar datos innecesarios: no guarda placa, marca, color ni fotografías.

## 4. Arquitectura

Monolito web en capas, sin framework (`docs/arquitectura.md`):

```
index.php → Kernel (rutas) → AuthMiddleware / RoleMiddleware + CSRF
          → Controllers → Services (reglas de negocio, transacciones)
          → Repositories (PDO con consultas preparadas) → MySQL/MariaDB
          → Views (PHP + HTML, salida escapada con e())
```

- `app/Services/ReglasVisita.php` contiene las reglas puras (salida, pago, montos en centavos), que se prueban de forma aislada.
- Las reglas críticas se aplican **dos veces**: en PHP (transacción + `SELECT … FOR UPDATE`) y en la base de datos (índices únicos sobre columnas generadas, claves foráneas compuestas, `CHECK` y disparadores).

## 5. Estructura de carpetas

| Carpeta | Contenido |
|---|---|
| `app/` | 11 controladores, 6 repositorios, 10 servicios, 2 middlewares, 11 helpers y vistas por módulo |
| `assets/` | `css/` (styles, layout, components), `js/` (app, caja, entrada), `img/` |
| `config/` | `app.php`, `database.php`, `permissions.php`; `env.php` lo genera el instalador |
| `database/` | `schema.sql`, `base.sql`, `seed.sql`, migraciones, diagrama ER, diccionario |
| `docs/` | Documentación de entrega y evidencias |
| `scripts/` | Instalación por consola, respaldo, restauración, empaquetado, actualización de XAMPP |
| `storage/` | Logs, comprobantes, respaldos (con `.htaccess` que niega el acceso web) |
| `tests/` | `Unit`, `Integration`, `Security` y el ejecutor `run.php` |

## 6. Modelo de datos

10 tablas en `database/schema.sql`: `estudiantes`, `roles`, `permisos`, `rol_permiso`, `usuarios`, `tarifas`, `visitas`, `consecutivos`, `pagos`, `auditoria`.

- **visitas**: `tipo_vehiculo ENUM('CARRO','MOTO')`, `categoria`, `tarifa_aplicada` y `estado ENUM('EN_CURSO','CERRADA','ANULADA')`. Un índice `UNIQUE` sobre la columna generada `visita_abierta` permite solo una visita abierta por persona.
- **pagos**: `medio_pago ENUM('EFECTIVO','TRANSFERENCIA','DATAFONO')` y `estado ENUM('PAGADO','ANULADO')`. Un `UNIQUE` sobre `pago_valido` permite un único pago válido por visita, y cada pago lleva un consecutivo único.
- **tarifas**: valor por `(tipo_vehiculo, categoria)` con vigencias; las visitas la referencian con una FK compuesta.
- **Disparadores**: impiden borrar visitas, pagos y auditoría, y modificar visitas o pagos ya confirmados y tarifas históricas.

## 7. Roles y permisos (comprobados en `config/permissions.php`)

| Rol del sistema | Puede | No puede |
|---|---|---|
| **Portería** | Buscar personas, registrar entradas, autorizar salidas con pago validado, ver visitas abiertas | Registrar pagos, cambiar tarifas |
| **Caja** | Ver pendientes, registrar pagos, emitir comprobantes, hacer el cierre de caja | Registrar entradas o salidas |
| **Administrador** | Usuarios, roles, estudiantes y profesores, tarifas, reportes, exportación CSV, auditoría, anulaciones con motivo | Registrar entradas, pagos o salidas ordinarias |
| **Auditor** | Reportes, visitas, pagos, bitácora y exportación | Crear o modificar nada |

**Sobre los roles pedidos en la actividad:**
- **Portero**: existe como el rol **Portería**.
- **Administrador**: existe.
- **Estudiante**: **no es un rol de usuario del sistema.** Estudiantes y profesores son *personas registradas* (tabla `estudiantes`, con campo `categoria`) que el personal busca por documento; no inician sesión en UniPark.
- Además existen dos roles que la actividad no mencionaba: **Caja** y **Auditor**.

## 8. Funcionalidades verificadas en el código

| Funcionalidad | Estado | Evidencia |
|---|---|---|
| Inicio de sesión, cambio de contraseña, expiración por inactividad | Implementada | `AuthController`, `AuthService`, `AuthMiddleware` |
| Gestión de usuarios del personal (crear, editar, activar/desactivar, contraseña temporal) | Implementada | `/usuarios`, `ServicioUsuario` |
| Registro de estudiantes y profesores con documento único e historial | Implementada | `/estudiantes`, `EstudianteController` |
| **Registro de vehículos** | **Por diseño, solo el tipo** (CARRO/MOTO) en cada visita | No existe tabla de vehículos ni campo de placa (RN-02) |
| Control de ingreso (una visita abierta por persona) | Implementada | `/porteria/entrada`, `ServicioEntrada` |
| Control de salida (solo con pago; si no: «Pago pendiente. Diríjase a Caja.») | Implementada | `/porteria/salida`, `ServicioSalida`, `ReglasVisita` |
| Tarifas fijas por vehículo y categoría con historial de vigencias | Implementada | `/tarifas`, `ServicioTarifa` |
| Pagos por el monto exacto con comprobante y consecutivo | Implementada | `/caja/pago`, `/caja/recibo/{id}`, `ServicioPago` |
| Cierre de caja con arqueo | Implementada | `/caja/cierre` |
| Anulación de visitas y pagos con motivo (nada se borra) | Implementada | `/visitas/{id}/anular`, `/pagos/{id}/anular` |
| Reportes con filtros y exportación CSV con documento enmascarado | Implementada | `/reportes`, `/reportes/exportar` |
| Bitácora de auditoría inmutable | Implementada | `/auditoria`, disparadores |
| Instalador web, respaldo y restauración | Implementada | `instalar.php`, `scripts/backup.php`, `scripts/restore.php` |
| **Recargas / saldo prepagado** | **No existe** | No hay ninguna referencia a recarga ni saldo en `app/` ni en el esquema; el pago es por visita |
| Pasarela de pago en línea | No existe (fuera de alcance) | Solo se registra el medio: efectivo, transferencia o datáfono |

## 9. Procesos principales

1. **Entrada**: Portería busca por documento, elige carro o moto, el sistema verifica que no haya otra visita abierta y copia la tarifa vigente de la categoría. La visita queda `EN_CURSO`.
2. **Pago**: Caja ve los pendientes, cobra el valor exacto y registra el medio. Se emite un comprobante con consecutivo y el pago queda `PAGADO`.
3. **Salida**: Portería solicita la salida y el sistema comprueba que esa visita tenga un pago válido. Si lo tiene, la visita queda `CERRADA`; si no, se deniega y se registra `SALIDA_DENEGADA` en la auditoría.

## 10. Interfaz y experiencia de usuario

Interfaz web propia (sin librerías) con paleta papel `#f6f5f1`, tinta `#16181d` y amarillo `#f2c230`, tipografía IBM Plex y menú lateral por rol. Es adaptable a móvil (capturas 16 y 17). La página pública `/inicio` incluye un simulador interactivo, «Prueba la regla». Los documentos se muestran enmascarados en los listados.

## 11. Clasificación del estado

### Implementadas
Autenticación y sesiones; usuarios y roles (Portería, Caja, Administrador, Auditor); estudiantes y profesores; entrada; pago con comprobante; salida validada; tarifas con vigencias; cierre de caja; anulaciones; reportes y CSV; auditoría; instalador; respaldo y restauración; 78 pruebas automatizadas aprobadas.

### Parcialmente implementadas
- **Registro de vehículos**: solo el tipo (carro o moto) por visita, sin placa ni datos del vehículo. Es una decisión de diseño documentada (RN-02), no un faltante.
- **Pagos**: se registran en el sistema, pero el cobro es presencial; no hay pasarela en línea (fuera de alcance).
- **Despliegue**: documentado y automatizado para XAMPP; producción solo descrita en el manual.
- **Valores provisionales**: tarifas, medios de pago y tiempo de sesión están configurados, pero la universidad debe confirmarlos (`docs/analisis-requerimientos.md` §8).

### Pendientes / no implementadas
- Protocolo de contingencia y salida de emergencia (marcado «No implementado» en la documentación).
- Atención de una persona sin documento (marcado «No implementado»).
- Recargas o saldo prepagado: no existen.
- Acceso de los estudiantes al sistema: no existe un rol Estudiante.

## 12. Qué muestra el video (y qué no)

El video usa capturas reales de `docs/evidencias/` y textos copiados de la interfaz. **No** muestra recargas, placas, un rol Estudiante ni pagos en línea, porque no existen en el código. Los nombres visibles en las capturas («Pedro Portero», «Camila Cajera», etc.) son datos de demostración ficticios de `database/seed.sql`.
