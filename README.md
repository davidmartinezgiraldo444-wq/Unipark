# UniPark

Sistema de control de acceso y operación del parqueadero universitario.
Corporación Universitaria Lasallista · Bases de Datos.

Portería registra la entrada, Caja confirma el pago y la salida solo se autoriza cuando ese pago existe. La tarifa es fija por visita según el vehículo y la categoría de la persona. No se guarda placa ni foto, y cada operación queda registrada con el funcionario responsable.

**Stack:** PHP 8 (sin frameworks ni dependencias) · MySQL/MariaDB (InnoDB) · HTML, CSS y JavaScript.

## Tarifas

| Vehículo | Estudiante | Profesor |
|---|---|---|
| Carro | $ 2.050 | $ 2.800 |
| Moto | $ 1.050 | $ 1.800 |

El Administrador las cambia desde el módulo **Tarifas**. Las visitas ya registradas conservan el valor que se les aplicó.

## Instalación en XAMPP

1. Inicia **Apache** y **MySQL** en el panel de XAMPP.
2. Descomprime `UniPark.zip` en `C:\xampp\htdocs\`. Queda `C:\xampp\htdocs\UniPark\index.php`.
3. Abre <http://localhost/UniPark/>. Se muestra el **instalador**.
4. Escribe los datos de MySQL (en XAMPP: `127.0.0.1`, puerto `3306`, usuario `root`, sin contraseña) y pulsa **Instalar UniPark**.
5. Ingresa con `admin` / `Demo2026*`.

El instalador crea la base de datos, las tablas con sus reglas, una cuenta de MySQL con privilegios mínimos para la aplicación y, si lo eliges, datos de demostración. Al terminar se bloquea solo.

`http://localhost/UniPark/` abre el inicio de sesión; la presentación pública está en `/UniPark/inicio`.

**Actualizar una copia ya instalada** sin perder la conexión ni los datos:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\actualizar-xampp.ps1
```

Guía completa, actualización, acceso por túnel (ngrok o Cloudflare) y problemas frecuentes: [`docs/manual-instalacion.md`](docs/manual-instalacion.md).

## Cuentas iniciales

Todas usan la contraseña **`Demo2026*`**. Cada persona puede cambiarla desde **Cambiar contraseña**, en la parte inferior del menú.

| Usuario | Rol | Se crea |
|---|---|---|
| `admin` | Administrador | Siempre |
| `porteria` | Portería | Con datos de demostración |
| `caja`, `caja2` | Caja | Con datos de demostración |
| `auditor` | Auditor | Con datos de demostración |

## Funcionalidades

| Módulo | Rol | Qué hace |
|---|---|---|
| Portería | Portería | Busca por documento, registra entradas (carro o moto) con la tarifa de la categoría y autoriza salidas solo con pago confirmado |
| Caja | Caja | Cola de pendientes, cobro por el valor exacto, comprobante con consecutivo y cierre de caja con arqueo |
| Estudiantes y profesores | Administrador (consulta: todos) | Registro con documento único, categoría e historial de visitas y pagos |
| Usuarios | Administrador | Cuentas individuales, roles, contraseñas temporales, activación y desactivación |
| Tarifas | Administrador | Valor fijo por vehículo y categoría, con historial de vigencias |
| Reportes | Administrador, Auditor (Portería: visitas abiertas) | Visitas abiertas, entradas y salidas, pagos y recaudo, con filtros y exportación CSV |
| Auditoría | Administrador, Auditor | Bitácora inmutable de operaciones sensibles e intentos denegados |

## Reglas críticas y dónde se garantizan

| Regla | Aplicación (PHP) | Base de datos |
|---|---|---|
| Una visita abierta por persona | Transacción + `SELECT … FOR UPDATE` | `UNIQUE` sobre columna generada `visita_abierta` |
| Salida solo con pago de esa visita | `ServicioSalida` + `ReglasVisita` | `CHECK` de estados |
| Un único pago válido por visita | `ServicioPago` con bloqueo | `UNIQUE` sobre columna generada `pago_valido` |
| Tarifa según vehículo y categoría | `ServicioEntrada` | FK compuesta `(id_tarifa, tipo_vehiculo, categoria)` |
| Tarifa histórica | Copia en `tarifa_aplicada` | Disparador que impide modificarla |
| Nada se borra | Anulaciones con motivo | Sin privilegio `DELETE` + disparadores |
| Permisos por rol | `RoleMiddleware` en cada ruta | Cuenta de la aplicación con privilegios mínimos |

Probado con solicitudes simultáneas reales: 6 procesos para entradas y 5 para pagos.

## Pruebas

```powershell
C:\xampp\php\php.exe tests\run.php
```

Resultado actual: **78 pruebas, 0 fallidas**. Cubren los criterios de aceptación CP-01 a CP-14 y las tarifas por categoría. Requieren preparar antes la base de pruebas; ver [`tests/README.md`](tests/README.md).

## Estructura

```
index.php     Punto de entrada (en la raíz, para XAMPP)
instalar.php  Instalador web; se bloquea después de usarse
assets/       CSS, JS e imágenes (lo único que Apache sirve directamente)
app/          Controllers, Services, Repositories, Middleware, Views, Helpers
config/       app.php, database.php, permissions.php y env.php (generado por el instalador)
database/     schema.sql, base.sql, seed.sql, migraciones, diagrama ER, diccionario de datos
storage/      logs, comprobantes, respaldos y bloqueo de instalación
tests/        Unit, Integration, Security y ejecutor run.php
docs/         Documentación de la entrega y evidencias
scripts/      Instalación por consola, respaldo, restauración y empaquetado
```

Las carpetas internas tienen su propio `.htaccess` que niega todo acceso web. La conexión se guarda en `config/env.php`, que es un archivo PHP: aunque Apache ignorara los `.htaccess`, pedirlo por la web no mostraría su contenido.

## Documentación

| Documento | Contenido |
|---|---|
| [Análisis de requerimientos](docs/analisis-requerimientos.md) | Alcance, reglas, RF y RNF con su estado, decisiones pendientes |
| [Arquitectura](docs/arquitectura.md) | Capas y recorrido de una solicitud |
| [Modelo de datos](docs/modelo-datos.md) | Relaciones, estados, integridad · [diagrama ER](database/diagrams/er-diagram.png) · [diccionario](database/dictionary/data-dictionary.md) |
| [Manual de instalación](docs/manual-instalacion.md) | XAMPP, instalador web, producción, respaldos y recuperación |
| [Manual de usuario](docs/manual-usuario.md) | Portería y Caja |
| [Manual del administrador](docs/manual-administrador.md) | Usuarios, personas, tarifas, anulaciones, reportes y auditoría |
| [Plan de pruebas](docs/plan-pruebas.md) | Casos, datos de prueba y resultados |
| [Seguridad](docs/seguridad.md) | Autenticación, permisos, sesiones, privacidad y respaldos |
| [Evidencias](docs/evidencias/README.md) | Capturas del recorrido y salida de las pruebas |

## Generar el paquete

```powershell
powershell -ExecutionPolicy Bypass -File scripts\empaquetar.ps1
```

Crea `dist/UniPark.zip` sin configuración local, credenciales, logs ni respaldos.

## Autoría

Desarrollado por David Martínez Giraldo · Docente: Gabriel Barrera · 2026.
