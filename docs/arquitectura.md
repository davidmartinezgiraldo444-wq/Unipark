# Arquitectura · UniPark

Aplicación web **monolítica modular** en PHP 8 y MySQL/MariaDB, como recomienda la sección 9 del documento base: es sencilla de desplegar, separa responsabilidades y no necesita dependencias externas (ni Composer ni frameworks).

## Capas

| Capa | Carpeta | Responsabilidad | Ejemplos |
|---|---|---|---|
| Presentación | `assets/`, `app/Views/` | HTML, CSS, JS, formularios y mensajes | `porteria/index.php`, `caja.js` |
| Entrada | `index.php`, `instalar.php`, `app/Kernel.php` | Punto de entrada único, rutas, CSRF, manejo de errores | `routes.php` |
| Middleware | `app/Middleware/` | Sesión válida y permiso del rol | `AuthMiddleware`, `RoleMiddleware` |
| Controladores | `app/Controllers/` | Leer la solicitud, validar parámetros, llamar servicios, elegir la vista | `EntradaController`, `CajaController` |
| Servicios | `app/Services/` | Reglas de negocio y transacciones | `ServicioEntrada`, `ServicioPago`, `ServicioSalida` |
| Repositorios | `app/Repositories/` | Consultas SQL preparadas | `VisitaRepository`, `PagoRepository` |
| Base de datos | `database/schema.sql` | Integridad: claves, índices únicos, CHECK, disparadores | `uq_visitas_abierta` |
| Configuración | `config/`, `config/env.php` | Parámetros; credenciales en un archivo PHP que el navegador no puede leer | `app.php`, `permissions.php` |

**Regla de dependencia:** cada capa solo usa la inferior. Las vistas no consultan la base; los controladores no escriben SQL; los repositorios no deciden reglas de negocio.

## Recorrido de una solicitud

Ejemplo: Portería intenta autorizar una salida.

```
Navegador ── POST /porteria/salida (id_visita, _csrf)
   │
index.php ─ si no existe storage/installed.lock, redirige a instalar.php
         ─ arranca app/bootstrap.php (autoload, config/env.php, zona horaria)
   │             ─ inicia la sesión (cookie HttpOnly, SameSite=Strict)
   ▼
Kernel::handle
   1. Busca la ruta en app/routes.php ......... no existe → 404
   2. POST: verifica el token CSRF ............ inválido → 419 + auditoría CSRF_INVALIDO
   3. AuthMiddleware: sesión, inactividad, cuenta activa (leída de la base en cada solicitud)
                                                 sin sesión → 303 /login
   4. RoleMiddleware: permiso SALIDA_AUTORIZAR  sin permiso → 403 + auditoría ACCESO_DENEGADO
   5. SalidaController::autorizar
   ▼
ServicioSalida::autorizar   ── BEGIN
   • VisitaRepository::find(id, FOR UPDATE)     bloquea la fila de la visita
   • PagoRepository::validoDeVisita(FOR UPDATE)
   • ReglasVisita::evaluarSalida()              regla pura, probada sin base de datos
       sin pago → ROLLBACK, auditoría SALIDA_DENEGADA, «Pago pendiente. Diríjase a Caja.»
   • VisitaRepository::cerrar()                 UPDATE … estado='CERRADA', salida_en=NOW()
   • AuditService::log(SALIDA_AUTORIZADA)
                            ── COMMIT
   ▼
MariaDB valida además: CHECK de estado, salida ≥ entrada, disparador de inmutabilidad
   ▼
Controlador → mensaje flash → 303 /porteria → vista con el resultado
```

Cualquier excepción no prevista revierte la transacción abierta, se escribe en `storage/logs/app-AAAA-MM-DD.log` y el usuario ve un mensaje genérico (RF-18).

## Instalación

`instalar.php` usa `App\Services\Instalador`, el mismo servicio que la consola (`scripts/install.php`). Valida los datos, se conecta a MySQL, aplica `schema.sql`, `base.sql` y opcionalmente `seed.sql`, crea la cuenta de la aplicación con privilegios mínimos y escribe `config/env.php`. Al final crea `storage/installed.lock`: desde ese momento `instalar.php` responde 403 y `index.php` deja de redirigir al instalador.

## Defensa en profundidad para las reglas críticas

| Regla | Interfaz | Servicio (PHP) | Base de datos |
|---|---|---|---|
| Tarifa según categoría | Muestra el precio de la categoría | `vigente(tipo, categoria)` dentro de la transacción | FK compuesta `(id_tarifa, tipo_vehiculo, categoria)` |
| Una visita abierta (RN-07) | Oculta el formulario si hay visita | `FOR UPDATE` sobre el estudiante y la visita | `UNIQUE (visita_abierta)` columna generada |
| Salida solo con pago (RN-06) | Muestra «Pago pendiente» | `evaluarSalida` dentro de la transacción | `CHECK` de estado |
| Un pago válido (RN-09) | Oculta pagadas en Caja | `FOR UPDATE` sobre visita y pago | `UNIQUE (pago_valido)` |
| Tarifa histórica (RN-11) | — | Copia `valor` en la visita | Disparador `tr_visitas_inmutables` |
| No borrar (RN-12) | No hay botones de borrar | Anulación con motivo | Sin privilegio `DELETE` + disparadores |
| Permisos (RF-02) | Menú filtrado por rol | `RoleMiddleware` en cada ruta | Cuenta con privilegios mínimos |

La interfaz solo ayuda; ninguna regla depende de ella. Las pruebas de seguridad llaman las rutas directamente para comprobarlo (CP-08).

## Estructura de carpetas

```
unipark/
├── index.php          Punto de entrada (en la raíz, para XAMPP)
├── instalar.php       Instalador web; se bloquea después de usarse
├── .htaccess          Reescritura a index.php y bloqueo de carpetas internas
├── assets/            css (styles, layout, components), js (app, entrada, caja), img (logo, acceso.jpg)
├── app/
│   ├── Kernel.php     Recorrido de la solicitud
│   ├── routes.php     Rutas y permiso requerido
│   ├── bootstrap.php  Autoload, .env, zona horaria, errores
│   ├── Controllers/  Services/  Repositories/  Middleware/  Helpers/
│   └── Views/         layouts, auth, dashboard, porteria, caja, estudiantes, usuarios, tarifas, reportes, auditoria, errors
├── config/            app.php, database.php, permissions.php
├── database/          schema.sql, seed.sql, migrations/, diagrams/, dictionary/
├── storage/           logs/, receipts/, backups/ (protegido con .htaccess, ignorado por Git)
├── tests/             Unit/, Integration/, Security/, Support/, run.php
├── docs/              Documentación de la entrega
└── scripts/           install.php, backup/restore (.php, .sh, .bat), setup-local.md
```

## Decisiones técnicas

| Decisión | Motivo |
|---|---|
| Sin framework ni Composer | Requisito académico de PHP puro; despliegue en XAMPP sin pasos extra. `composer.json` existe para quien prefiera su autoload. |
| Router propio con tabla de rutas | Cada ruta declara su permiso en un solo lugar, fácil de revisar. |
| Columnas generadas + `UNIQUE` | Es la alternativa de la sección 8.3; funciona en MariaDB 10.4 y MySQL 8 y resiste solicitudes simultáneas. Se complementa con `FOR UPDATE`. |
| Tabla `consecutivos` | Numeración de comprobantes única y sin huecos, dentro de la transacción del pago. |
| Monto del pago tomado de la visita | El cajero no escribe el valor, así que no puede diferir de la tarifa aplicada. |
| Permisos leídos de la base en cada solicitud | Desactivar una cuenta o cambiar un rol surte efecto de inmediato. |
| Ejecutor de pruebas propio | Corre con el PHP de XAMPP, sin instalar PHPUnit. |
