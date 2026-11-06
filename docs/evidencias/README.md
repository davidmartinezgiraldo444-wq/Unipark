# Evidencias de pruebas

Recorrido realizado el 05/11/2026 sobre el paquete `UniPark.zip` desplegado en Apache de XAMPP (PHP 8.2.12, MariaDB 10.4.32). La instalación se hizo con el instalador web, cargando los datos ficticios de demostración. Las capturas son de 1366 × 900, salvo las dos de móvil (390 × 844).

## Pruebas automatizadas

| Archivo | Contenido |
|---|---|
| [`resultado-pruebas.txt`](resultado-pruebas.txt) | Salida completa de `php tests/run.php`: **78 pruebas, 0 fallidas** |

## Capturas del recorrido

| # | Archivo | Rol | Qué demuestra | Caso |
|---|---|---|---|---|
| 01 | [`01-landing.png`](01-landing.png) | Público | Página de presentación con las tarifas vigentes leídas de la base | — |
| 02 | [`02-login-marca-de-agua.png`](02-login-marca-de-agua.png) | — | Inicio de sesión con la imagen del campus como marca de agua | RF-01 |
| 03 | [`03-instalador.png`](03-instalador.png) | — | Instalador web: requisitos, datos de MySQL y opciones | Despliegue |
| 04 | [`04-porteria-entrada-estudiante.png`](04-porteria-entrada-estudiante.png) | Portería | Estudiante sin visita: tarifas de estudiante ($2.050 / $1.050) | RF-07 |
| 05 | [`05-porteria-salida-denegada.png`](05-porteria-salida-denegada.png) | Portería | Profesor con moto ($1.800) sin pago: «Pago pendiente. Diríjase a Caja.» | CP-06 |
| 06 | [`06-caja-cobro-profesor.png`](06-caja-cobro-profesor.png) | Caja | Cobro de la visita del profesor por el valor exacto | RF-09, RF-10 |
| 07 | [`07-caja-comprobante.png`](07-caja-comprobante.png) | Caja | Comprobante con consecutivo, categoría y documento enmascarado | CP-04 |
| 08 | [`08-porteria-salida-autorizada.png`](08-porteria-salida-autorizada.png) | Portería | Salida autorizada tras el pago; visita CERRADA | CP-07 |
| 09 | [`09-admin-panel.png`](09-admin-panel.png) | Administrador | Ocupación, pendientes, recaudo del día y tarifas por categoría | Sección 12 |
| 10 | [`10-admin-tarifas.png`](10-admin-tarifas.png) | Administrador | Tarifas por vehículo y categoría, con historial | RF-06 |
| 11 | [`11-admin-estudiantes-profesores.png`](11-admin-estudiantes-profesores.png) | Administrador | Registro de personas con su categoría | RF-04 |
| 12 | [`12-admin-reporte-pagos.png`](12-admin-reporte-pagos.png) | Administrador | Reporte de pagos con filtros, incluida la categoría | RF-16 |
| 13 | [`13-admin-auditoria.png`](13-admin-auditoria.png) | Administrador | Bitácora: salida denegada, pago, salida autorizada, cambio de contraseña | CP-13, RF-17 |
| 14 | [`14-cambiar-contrasena.png`](14-cambiar-contrasena.png) | Administrador | Cambio voluntario de la contraseña inicial `Demo2026*` | RN-14 |
| 15 | [`15-acceso-denegado-403.png`](15-acceso-denegado-403.png) | Portería | Acceso directo por URL a Tarifas: 403 y registro del intento | CP-08 |
| 16 | [`16-movil-login.png`](16-movil-login.png) | — | Inicio de sesión en un teléfono | RNF-07 |
| 17 | [`17-movil-tarifas.png`](17-movil-tarifas.png) | Administrador | Tarifas en un teléfono | RNF-07 |

## Verificaciones adicionales en Apache

| Verificación | Resultado |
|---|---|
| Sin instalar, `/` redirige a `instalar.php` | 303 |
| Instalación web completa | Base, tablas, disparadores, cuentas y `config/env.php` creados |
| Instalador después de instalar | 403 (bloqueado por `storage/installed.lock`) |
| `config/env.php`, `.env.example`, `composer.json`, `README.md`, `*.sql`, `app/`, `config/`, `database/`, `storage/`, `docs/`, `tests/`, `scripts/` por la web | 403 en todas |
| `assets/css/*.css`, `assets/img/*.jpg` | 200 |
| Profesor con moto | Tarifa aplicada y cobrada: $1.800 |
| Cambio de contraseña de `porteria` | La contraseña anterior responde 401 y la nueva inicia sesión |

## Cómo agregar evidencias

Nombra los archivos `NN-modulo-descripcion.png`, agrégalos a la tabla con el caso que demuestran y **no incluyas datos personales reales**: usa solo los datos de `seed.sql`.
