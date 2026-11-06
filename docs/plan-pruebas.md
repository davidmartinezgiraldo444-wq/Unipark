# Plan de pruebas · UniPark

## 1. Objetivo

Verificar que el sistema cumple los criterios de aceptación CP-01 a CP-14 del documento base. Además se prueban las reglas de negocio, los permisos, la seguridad web, la concurrencia y la restauración de respaldos.

## 2. Alcance y niveles

| Nivel | Qué cubre | Automatizada | Ubicación |
|---|---|---|---|
| Unitario | Reglas puras: tarifas, montos, salida, pago, documento, contraseñas | Sí | `tests/Unit` |
| Integración | Servicios contra MariaDB real, restricciones y disparadores | Sí | `tests/Integration` |
| Seguridad | Recorrido HTTP por el Kernel: permisos, CSRF, inyección SQL, XSS, errores | Sí | `tests/Security` |
| Concurrencia | Procesos PHP simultáneos contra la misma visita o el mismo estudiante | Sí | `EntradaTest`, `PagoTest` |
| Restauración | Respaldo con mysqldump y restauración con los scripts del proyecto | Sí | `BackupRestoreTest` |
| Funcional manual | Recorrido de pantallas por rol en el navegador | No | Sección 6 |

## 3. Entorno

| Elemento | Valor |
|---|---|
| Sistema | Windows 11 |
| PHP / Base de datos | PHP 8.2.12 / MariaDB 10.4.32 (XAMPP) |
| Base de pruebas | `unipark_test`, recreada desde `schema.sql` + `seed.sql` antes de **cada** caso |
| Cuenta de conexión | `unipark_app` (privilegios mínimos, igual que producción) |
| Ejecución | `php tests/run.php` |

## 4. Datos de prueba

Definidos en [`database/seed.sql`](../database/seed.sql). Son ficticios: documentos `1000000001` a `1000000012`, correos `@correo.test`.

| Dato | Uso en las pruebas |
|---|---|
| Estudiantes 9 y 10 | Sin visitas: entradas nuevas y concurrencia |
| Profesores 6 y 11 | Tarifa de profesor: visita 7 pagada ($2.800), entrada nueva de moto ($1.800) |
| Estudiante 5 · visita 6 (MOTO) | Abierta sin pago: entrada duplicada |
| Profesor 6 · visita 7 (CARRO) | Abierta y pagada: salida válida, pago duplicado |
| Estudiante 7 · visita 8 (CARRO) | Abierta sin pago: salida denegada, cobro |
| Estudiante 12 | Inactivo |
| Visitas 1 y 2 | Tarifa histórica 2025 de estudiantes ($1.900 / $950) |
| Visita 9 | Anulada |
| Pago 4 | Anulado y recobrado con el pago 5 |
| Usuario `inactivo` | Cuenta desactivada (CP-09) |
| Contraseña de todas las cuentas | `Demo2026*` |

## 5. Casos de aceptación

| ID | Escenario | Pasos | Resultado esperado | Resultado obtenido |
|---|---|---|---|---|
| CP-01 | Entrada válida | Portería registra al estudiante 9 como CARRO | Visita `EN_CURSO` con estudiante, categoría, tipo, tarifa $2.050, hora y funcionario; evento en auditoría | ✓ Cumple |
| CP-02 | Entrada duplicada | Registrar entrada al estudiante 5, que ya tiene la visita 6 abierta | Rechazo `VISITA_ABIERTA` que informa la visita 6; sigue habiendo una sola visita abierta | ✓ Cumple |
| CP-03 | Entradas simultáneas | 6 procesos registran a la vez la entrada del estudiante 10 | 1 confirmada, 5 con rechazo controlado; 1 visita en la base. Un `INSERT` directo también falla (error 1062). | ✓ Cumple |
| CP-04 | Pago válido | Caja cobra las visitas 6 y 8 | Montos $1.050 y $2.050 iguales a la tarifa aplicada; consecutivos `R00000008` y `R00000009` | ✓ Cumple |
| CP-05 | Pago duplicado | Cobrar la visita 7, ya pagada; 5 cobros simultáneos de la visita 8 | Rechazo `PAGO_DUPLICADO`; un solo pago válido; sin consumir consecutivos | ✓ Cumple |
| CP-06 | Salida sin pago | Portería autoriza la salida de la visita 8 | «Pago pendiente. Diríjase a Caja.»; `salida_en` sigue NULL; `SALIDA_DENEGADA` en auditoría | ✓ Cumple |
| CP-07 | Salida con pago | Portería autoriza la salida de la visita 7 | Visita `CERRADA` con hora de salida y funcionario | ✓ Cumple |
| CP-08 | Rol no autorizado | Caja hace `POST /porteria/salida`; Portería hace `POST /caja/pago`; 16 combinaciones rol/ruta | 403, sin cambios en la base, `ACCESO_DENEGADO` en auditoría | ✓ Cumple |
| CP-09 | Usuario inactivo | Login con la cuenta `inactivo` | 401 con mensaje genérico; una cuenta desactivada en sesión pierde el acceso | ✓ Cumple |
| CP-10 | Cambio de tarifa | Cambiar CARRO estudiante a $2.500 | Las visitas 1 y 8 conservan $1.900 y $2.050; la nueva entrada de estudiante usa $2.500 y la de profesor sigue en $2.800; el disparador impide editar `tarifa_aplicada` | ✓ Cumple |
| T-01 | Tarifa por categoría | Entradas de profesor (moto) y estudiante (moto y carro) | $1.800, $1.050 y $2.050; la base rechaza aplicar una tarifa de otra categoría | ✓ Cumple |
| T-02 | Instalador web | Instalar en una carpeta nueva de `htdocs` con XAMPP | Base, tablas, cuentas y `config/env.php` creados; instalador bloqueado (403); `config/env.php` y carpetas internas con 403 | ✓ Cumple |
| T-03 | Cambio de contraseña | `porteria` cambia `Demo2026*` por una nueva | La anterior deja de funcionar (401) y la nueva inicia sesión | ✓ Cumple |
| CP-11 | Inyección SQL | 6 cargas maliciosas en búsqueda, login, Portería y filtros de reportes | Se tratan como datos: sin resultados, sin errores, tablas intactas | ✓ Cumple |
| CP-12 | CSRF inválido | `POST` sin token o con token falso a 8 rutas sensibles, incluido el login | 419, sin cambios, `CSRF_INVALIDO` en auditoría | ✓ Cumple |
| CP-13 | Auditoría | Cambio de tarifa y anulación de pago | Usuario, hora del servidor, acción, valores anterior y nuevo y motivo; la bitácora no se puede alterar | ✓ Cumple |
| CP-14 | Restauración | Respaldar, hacer cambios, restaurar con `restore.php` | Conteos y consecutivo iguales al respaldo; restricciones y disparadores activos; sin confirmación no restaura | ✓ Cumple |

## 6. Pruebas funcionales manuales

Recorrido realizado sobre el paquete `UniPark.zip` desplegado en Apache de XAMPP:

| # | Rol | Pasos | Esperado |
|---|---|---|---|
| M-01 | — | Abrir `/` y `/panel` sin sesión | Landing pública; `/panel` redirige al login |
| M-02 | Portería | Buscar `1000000007` | Visita abierta, «Pago pendiente. Diríjase a Caja.» |
| M-03 | Portería | Autorizar la salida sin pago | Mensaje de denegación; la visita sigue abierta |
| M-04 | Caja | Cobrar la visita del estudiante 7 | Comprobante con consecutivo; desaparece de pendientes |
| M-05 | Caja | Cierre de caja con el efectivo contado | Diferencia calculada y registrada |
| M-06 | Portería | Autorizar la salida de nuevo | Visita CERRADA |
| M-07 | Administrador | Cambiar tarifa, anular pago, crear usuario | Mensajes de confirmación; eventos en auditoría |
| M-08 | Todos | Revisar en ancho de móvil (375 px) | Sin desplazamiento horizontal; menú horizontal |

Las capturas están en [`evidencias/`](evidencias/README.md).

## 7. Criterios de salida

- 100 % de las pruebas automatizadas pasan (`0 fallidas`).
- Ningún caso CP-01 a CP-14 queda sin cubrir.
- Sin errores en `storage/logs` durante el recorrido manual, salvo el provocado a propósito por `testErroresInternosNoSeMuestranEnPantalla`.

## 8. Resultado

`78 pruebas · 0 fallidas · 0 omitidas` (06/11/2026). Salida completa: [`evidencias/resultado-pruebas.txt`](evidencias/resultado-pruebas.txt).
