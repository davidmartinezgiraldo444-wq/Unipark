# Actualización: cobro diario, IA de vehículos, carnets por celular y cuentas por rol

Fecha: 07/11/2026 · Se trabajó sobre la estructura existente (PHP 8 sin framework, MySQL/MariaDB,
HTML/CSS/JS). No se cambió de tecnología ni se eliminaron funcionalidades.

## 1. Problema de inicio de sesión de estudiantes: causa real

La auditoría mostraba 4 intentos `LOGIN_FALLIDO` con el usuario `estudiante` y `id_usuario` vacío.
**No era un error de contraseña ni de hash:** el sistema no tenía ningún rol ni cuenta para estudiantes.
Los estudiantes solo existían en la tabla `estudiantes` (personas del parqueadero), sin credenciales; la
tabla `usuarios` solo tenía Portería, Caja, Administrador y Auditor. Además, tras 5 intentos fallidos el
sistema bloquea ese usuario 5 minutos (protección existente).

Corrección: roles **Estudiante, Profesor y Trabajador** (permiso `CUENTA_PROPIA`), cuentas vinculadas a su
persona (`usuarios.id_estudiante`), pantalla **Mi cuenta** y redirección por rol después del login.
Probado: los 19 usuarios de prueba inician sesión y llegan a su pantalla (`RolesPruebaTest`).

## 2. Usuarios de prueba — contraseña de todos: `demo2026*`

Guardada con `password_hash` (bcrypt), igual que el resto del sistema.

| Rol | Usuarios | Persona / saldo inicial |
|---|---|---|
| Estudiante | `estudiante1`, `estudiante2`, `estudiante3` | Valentina Ríos ($10.000) · Santiago Mejía ($0) · Mariana Gómez ($1.500) |
| Profesor | `profesor1`, `profesor2`, `profesor3` | Luciana Echeverri ($20.000) · Ricardo Alzate ($0) · Paola Restrepo ($5.000) |
| Trabajador | `trabajador1`, `trabajador2`, `trabajador3` | Hernán Gil ($10.000) · Gloria Zuluaga ($0) · Fabio Montoya ($3.000) |
| Portería | `porteria`, `porteria2`, `porteria3` | — |
| Caja | `caja`, `caja2`, `caja3` | — |
| Administrador | `admin`, `admin2` | — |
| Auditor | `auditor`, `auditor2` | — |
| (prueba CP-09) | `inactivo` | Cuenta desactivada a propósito |

Las cuentas `admin`, `porteria`, `caja`, `caja2` y `auditor` existían con `Demo2026*`: ahora usan `demo2026*`.

## 3. Reglas implementadas

| Regla | Dónde |
|---|---|
| Tolerancia de 15 min desde la entrada; si sale antes, no se cobra | `visitas.tolerancia_hasta`, `ReglasCobro`, `ServicioSalida` |
| Tarifa por día según rol y vehículo (Est. 2.000/1.000 · Prof. y Trab. 3.000/2.000) | `tarifas` (nuevas vigencias; las anteriores quedan en el historial) |
| Bicicleta y patineta: se registran, sin tarifa | `ReglasCobro::generaCobro` |
| Patineta = **scooter** (con manubrio, manual o eléctrica). Solo la reconoce el modelo propio; el skateboard y cualquier detección dudosa dan «Desconocido» y Portería clasifica | `assets/js/vehiculos-ia.js` |
| La IA nunca registra una entrada con una detección dudosa; la salida nunca depende de la IA | `vigilar()` (umbral, margen y estabilidad); entrada automática desactivada por defecto y con 3 s cancelables |
| Un solo cobro por persona y día, aunque entre y salga varias veces | `cobros_diarios.cobro_vigente` **UNIQUE** + transacción con bloqueo |
| El día vale la **tarifa más alta** de los vehículos usados: si cambia a uno de tarifa mayor, se cobra **solo la diferencia** | Cobro de concepto `DIFERENCIA` (uno por visita, `diferencia_vigente` UNIQUE); visita `DIFERENCIA` |
| Al vencer la tolerancia: cobro → débito del saldo o valor pendiente en Caja | `ServicioCobro` (cron `scripts/cobros.php` y latido de las pantallas abiertas) |
| Sin saldo: notificación push al celular (**implementada; falta probarla en un celular real**) | `ServicioNotificacion` + `WebPush` (VAPID, aes128gcm, sin dependencias) |
| Salida rápida: solo consulta si está pagado o no requiere pago; nunca calcula, cobra ni debita | `ServicioSalida` |
| Si el cobro automático no corrió: la salida muestra una **incidencia** y Caja o Portería la resuelven aparte | `SALIDA_INCIDENCIA`, «Incidencias de cobro» en Caja, botón en Portería y en el escáner |
| Documento no registrado = visitante, sin cobro | `ServicioEntrada::registrarVisitante` |
| Visitantes sin restricción por visitas abiertas (sin avisos) | `visitas.visita_abierta` excluye `VISITANTE` |
| IA solo guarda el tipo de vehículo (y si fue IA o manual) | `visitas.tipo_vehiculo`, `visitas.tipo_origen` |
| Pagos sin duplicados e historial inmutable | `pagos.pago_cobro_valido` UNIQUE, disparadores, `movimientos_saldo` sin UPDATE/DELETE |

## 4. Estado de las decisiones técnicas (actualizado 07/11/2026)

### 4.1 Aprobadas e implementadas

**a) Cambio de vehículo el mismo día: tarifa más alta, solo la diferencia.** Funciona así:
- El primer vehículo que supera la tolerancia genera el cobro del día, de concepto `DIARIO`.
- Si después la persona entra en un vehículo de **tarifa mayor** y supera los 15 minutos, se cobra **solo la diferencia**, como un cobro de concepto `DIFERENCIA`. Nunca se genera un segundo cobro completo.
- Si entra en un vehículo de tarifa igual o menor, la visita queda cubierta y no se cobra nada.

Al final, el total del día es igual a la tarifa más alta que usó. La diferencia se debita del saldo antes de la salida o queda pendiente en Caja, y en ese caso se envía un aviso.

| Estudiante, saldo $10.000 | Cobro | Saldo |
|---|---|---|
| 7:00 entra en motocicleta → 7:15 vence la tolerancia | Cobro del día **$1.000** | $9.000 |
| 12:20 entra en automóvil → 12:35 vence la tolerancia | **Diferencia $1.000** (no $2.000) | $8.000 |
| 15:00 vuelve a entrar en automóvil | Cubierta: ya pagó $2.000 hoy | $8.000 |
| Si el orden fuera automóvil y luego moto | $2.000 una vez; la moto queda cubierta | $8.000 |

Lo comprueban `testMotoYLuegoAutomovilCobraSoloLaDiferencia`, `testAutomovilYLuegoMotoNoCobraNadaMas` y
`testDiferenciaSinSaldoQuedaPendienteYLaSalidaSeDeniega`. La base de datos impide dos diferencias para la
misma visita. Si la visita del vehículo mayor dura menos de 15 minutos, no se cobra diferencia (misma tolerancia).

**b) Salida de solo consulta:** la salida nunca calcula, cobra ni debita. Si el cobro automático no corrió,
muestra una **incidencia**, y Caja o Portería la resuelven aparte con «Procesar cobro».

**c) Entrada y salida «automáticas»: qué significan exactamente.**
- **«Salida al leer el carnet»** (activada): cuando se lee el carnet de alguien que está dentro, el escáner
  consulta el estado del pago y muestra «Autorizada» o «Denegada» sin un toque extra. **No usa la IA.** Solo
  autoriza si el cobro del día está pagado o no corresponde cobro; si está pendiente, niega. Si se desactiva,
  el portero ve la visita y pulsa «Registrar salida» (la consulta es la misma).
- **«Entrada automática (IA segura)»** (**desactivada por defecto**): sin activarla, la IA solo preselecciona
  el tipo de vehículo y el portero confirma con un toque. Si se activa, la entrada se registra sola solo cuando la detección es **confiable**, es decir:
  1. no es «Desconocido»;
  2. su confianza es de al menos 0,80;
  3. le saca al menos 0,25 a la segunda opción (por ejemplo, no hay una moto y un carro a la vez);
  4. se mantiene igual durante 1,2 s.

  Aun así, muestra una cuenta regresiva de 3 s con «Cancelar» y se puede corregir tocando otro tipo.
  Cualquier detección **dudosa** (baja confianza, margen corto, inestable o desconocida) **nunca registra
  nada**: queda para que Portería elija el tipo con los botones. Cada visita guarda si el tipo lo puso la IA o el portero.

**d) Patineta = solo scooter** (aprobado el 07/11/2026). Una patineta es un scooter: de pie, con manubrio,
manual o eléctrico. En pantalla aparece como «Patineta (scooter)». El **skateboard** (tabla sin manubrio)
**no es patineta**: no hay categoría para él, da «Desconocido» y Portería lo clasifica a mano. En Teachable
Machine, la clase `Patineta` se entrena solo con scooters; no pongas tablas en esa clase.

### 4.2 Verificadas técnicamente

| Punto | Evidencia |
|---|---|
| COCO-SSD (80 clases) tiene `car`, `motorcycle` y `bicycle`, y **no tiene patineta ni scooter**. Su `skateboard` no se usa | Lista de clases de `coco-ssd@2.2.3` |
| Cron sin duplicados: 5 ejecuciones simultáneas de `scripts/cobros.php` dan un solo cobro, débito y aviso | `testElCronEjecutadoVariasVecesALaVezNoDuplicaCobrosNiDebitos` |
| Hora y fecha de Colombia (`America/Bogota`, MySQL `-05:00`, sin horario de verano) | `testElCobroUsaLaHoraYFechaDeColombia` |
| Cambio de modelo: con solo copiar `model.json`, `metadata.json` y `weights.bin` se usa el modelo propio | Modelo de prueba con el formato de Teachable Machine en el navegador (MOTO, CARRO y BICICLETA con 0,99; `Ninguno` dio «Desconocido») |
| Cifrado y firma de las notificaciones push (VAPID y aes128gcm) | `WebPushTest` |
| Lectura de Code 128 con ZXing | Código generado y leído en el navegador |

### 4.3 Pendiente de prueba física (no se marca como verificado)

| Pendiente | Qué hay que probar |
|---|---|
| **Notificaciones push** | Están implementadas, pero **no se han probado en un celular real**: el navegador del entorno de pruebas bloquea los service workers. Prueba: con HTTPS, `estudiante2` activa avisos en su celular, **cierra la página**, entra un vehículo, pasan 15 min sin saldo y debe llegar la notificación. Probar en Android (Chrome) y en iPhone (iOS 16.4+, desde «Agregar a inicio»). |
| **Carnet real** | Falta escanear un **carnet real con una imagen nítida** para confirmar la simbología y qué número codifica (documento o código propio). Las capturas enviadas estaban borrosas: la webcam del portátil tiene foco fijo y no enfoca de cerca. Ver la sección 4 de la guía de celulares. |
| **Cámara del celular** | La lectura en vivo y el botón «Tomar foto del carnet» en un celular con autoenfoque. |
| **IA con vehículos reales** | COCO-SSD y, después, el modelo propio, frente al carril real. Ajustar `IA_UMBRAL` si hace falta. |

### 4.4 Requieren tu aprobación

1. **Origen del saldo:** solo se recarga en Caja; no hay pago en línea.
2. **Umbrales de la IA:** confianza 0,80 (`IA_UMBRAL`), margen 0,25, estabilidad 1,2 s y cuenta regresiva de 3 s.
3. **Ventana del cron:** hasta ~1 minuto con el cron por minuto (unos 10 s con el escáner abierto). En ese lapso la salida muestra la incidencia.
4. Visitas `LEGADO`, nombre opcional del visitante, `'unsafe-eval'` solo en las pantallas de cámara y cobro con la fecha de entrada (sin cambios).

## 5. Archivos

**Nuevos**

| Archivo | Para qué |
|---|---|
| `database/migrations/20261107_1200_personas_cuentas_saldo.sql` | Categorías Trabajador/Visitante, código de carnet, saldo, cuenta ↔ persona |
| `database/migrations/20261107_1210_cobro_diario_tolerancia.sql` | Tolerancia, liquidación, `cobros_diarios`, pagos de cobros, `movimientos_saldo`, disparadores |
| `database/migrations/20261107_1220_notificaciones_camaras.sql` | `notificaciones`, `push_suscripciones`, `puestos_camara` |
| `database/migrations/20261107_1230_datos_roles_tarifas_diarias.sql` | Roles, permisos y tarifas diarias |
| `database/migrations/20261107_1240_demo_usuarios_prueba.sql` | Usuarios de prueba y saldos (solo con `--demo`) |
| `database/migrations/20261107_1300_cobro_diferencia_vehiculo.sql` | Concepto `DIFERENCIA`: tarifa más alta del día |
| `scripts/migrar.php` | Aplica migraciones con respaldo previo y privilegios mínimos |
| `scripts/cobros.php` | Cobro automático por cron (cada minuto) |
| `app/Services/ServicioCobro.php`, `ReglasCobro.php`, `ServicioSaldo.php`, `ServicioNotificacion.php`, `LectorDocumento.php` | Reglas y procesos nuevos |
| `app/Repositories/CobroRepository.php`, `SaldoRepository.php`, `NotificacionRepository.php`, `PuestoCamaraRepository.php` | Acceso a datos |
| `app/Helpers/WebPush.php` | Notificaciones push (VAPID + cifrado) sin librerías |
| `app/Controllers/EscanerController.php`, `CobroController.php`, `CuentaController.php` | Escáner, cámara, cobros, Mi cuenta |
| `app/Views/porteria/escaner.php`, `camara.php`, `carnets.php`, `app/Views/cuenta/index.php`, `app/Views/caja/recarga.php`, `app/Views/layouts/movil.php` | Pantallas |
| `assets/js/camara.js`, `vehiculos-ia.js`, `escaner.js`, `camara-vehiculos.js`, `latido.js`, `mi-cuenta.js`, `carnets.js` | Cámara, ZXing, TensorFlow.js, push |
| `assets/css/movil.css`, `assets/img/icono-192.png`, `icono-512.png`, `sw.js`, `manifest.webmanifest` | Interfaz del celular y app instalable |
| `assets/modelo-vehiculos/` (`.htaccess`, `LEEME.txt`) | Carpeta para el modelo de Teachable Machine |
| `tests/Integration/CobroDiarioTest.php`, `tests/Security/RolesPruebaTest.php`, `tests/Unit/ReglasCobroTest.php`, `tests/Unit/WebPushTest.php` | 34 pruebas nuevas |
| `docs/integracion-celulares-ia.md`, este documento | Guías |

**Modificados:** `database/schema.sql`, `base.sql`, `seed.sql` (instalación nueva = base migrada; verificado
columna por columna), `config/app.php`, `config/permissions.php`, `app/routes.php`, `app/Kernel.php`,
`app/Middleware/AuthMiddleware.php`, `app/Helpers/Response.php`, `functions.php`, servicios de entrada,
salida, pago, tarifa y usuarios, repositorios de visitas, pagos, personas, tarifas y usuarios, controladores
de Portería, Caja, Panel, Personas y Usuarios, vistas de Portería, Caja, recibo, cierre, personas, usuarios,
landing, layouts, `assets/css/components.css`, `assets/js/entrada.js`, `.htaccess`, `.env.example`,
`.gitignore`, `scripts/install.php`, `scripts/empaquetar.ps1`, `app/Services/Instalador.php` y 8 pruebas existentes
(valores de tarifa y contraseña nuevos).

## 6. Base de datos

Tablas nuevas: `cobros_diarios`, `movimientos_saldo`, `notificaciones`, `push_suscripciones`,
`puestos_camara`, `migraciones`. Columnas nuevas: `estudiantes.codigo_carnet`, `estudiantes.saldo`,
`usuarios.id_estudiante`, `visitas.tipo_origen`, `tolerancia_hasta`, `liquidacion`, `id_cobro`,
`pagos.id_cobro`. Valores nuevos: categorías `TRABAJADOR` y `VISITANTE`, vehículos `BICICLETA` y
`PATINETA`, medio de pago `SALDO`. Diagrama de relaciones:

```
estudiantes ─┬─< visitas >── tarifas
             ├─< cobros_diarios (1 vigente por persona y día) ─< pagos
             ├─< movimientos_saldo (recarga / débito / reintegro)
             ├─< notificaciones
             └── usuarios (cuenta propia) ─< push_suscripciones
```

La cuenta de la aplicación sigue sin `DELETE`; `auditoria`, `movimientos_saldo` y `migraciones` solo admiten `SELECT, INSERT`.

## 7. Migraciones

Ya apliqué todas las migraciones a tu base local `unipark_db`, incluida la de la diferencia (respaldos previos en
`storage/backups/unipark_db_20261107_165430.sql` y `unipark_db_20261107_183146.sql`). En otra copia:

```bash
C:\xampp\php\php.exe scripts\migrar.php --estado
```

```bash
C:\xampp\php\php.exe scripts\migrar.php --demo
```

Sin `--demo` no se crean los usuarios de prueba (para producción). El script respalda la base, aplica solo
las pendientes, las registra en `migraciones` y ajusta los privilegios. Una instalación nueva con el
instalador web ya queda con todo. Para volver atrás: `scripts/restore-db.bat` con el respaldo.

## 8. Pruebas

```bash
C:\xampp\php\php.exe tests\run.php
```

Resultado: **112 pruebas, 0 fallidas** (78 anteriores, 8 de ellas ajustadas a las reglas nuevas, y 34 nuevas). Cubren tolerancia, cobro único
por día, día siguiente, débito, pendiente + aviso, pago en Caja, recarga que paga pendientes, reintegro,
visitantes, bicicleta/patineta, salida que solo consulta, índices únicos, inmutabilidad del libro, login de
todos los usuarios de prueba, permisos por rol, todas las pantallas y el cifrado push.

Verificado además en el navegador: login de estudiante → Mi cuenta; escáner móvil → identificación de una
trabajadora → entrada → salida en tolerancia sin cobro; ZXing lee Code 128; COCO-SSD carga y detecta objetos
en imágenes reales (WebGL).

**No verificado aquí:** la cámara física y una notificación push real, porque el navegador integrado del entorno de pruebas
bloquea la cámara y los service workers. Se verifica en un celular con HTTPS (sección 5 de `integracion-celulares-ia.md`).

## 9. Requisitos del hosting

- PHP 8.1+ con `pdo_mysql`, `mbstring`, `openssl` y `curl` (push). MySQL 8.0.16+ o MariaDB 10.4+.
- **HTTPS obligatorio** (cámara y notificaciones en el celular).
- Cron cada minuto: `php /ruta/UniPark/scripts/cobros.php`, o cron por URL con `CRON_TOKEN`.
- Salida a internet desde el servidor hacia los servicios push (FCM, Mozilla, Apple) por el puerto 443.
- Los celulares descargan las bibliotecas desde `cdn.jsdelivr.net` y los pesos de COCO-SSD desde
  `storage.googleapis.com` (la primera vez ~10–15 MB; luego quedan en caché).
- `storage/` escribible (logs, respaldos y `storage/push/vapid.php`, que se genera solo; no lo compartas).
- Opcional: `PUSH_SUBJECT` con un correo real de la universidad.

## 10. Qué falta o depende de algo externo

| Pendiente | Qué se necesita |
|---|---|
| Probar el código de barras con un carnet real y una imagen nítida | Celular con autoenfoque o «Tomar foto del carnet» (guía, sección 4) |
| Probar notificaciones push con la página cerrada | Celular real con HTTPS (sección 4.3) |
| Modelo propio de Teachable Machine | Fotos del carril real y entrenar/exportar (guía, sección 3); mientras tanto funciona COCO-SSD |
| Patinetas (manuales y eléctricas) | Solo con el modelo propio (COCO-SSD no tiene esa clase): incluir fotos de ambas en la clase `Patineta` |
| Push en iPhone | iOS 16.4+ y abrir UniPark desde «Agregar a inicio» |
| Pago en línea del saldo | No existe pasarela; el saldo se recarga en Caja |
| Hardware dedicado (lectores fijos, cámaras IP) | No necesario para la demostración |

## 11. Uso diario en XAMPP

Todo el proyecto está en `C:\xampp\htdocs\UniPark` y la base `unipark_db` ya tiene todas las migraciones.

1. Abre el **XAMPP Control Panel** y pulsa **Start** en **Apache** y en **MySQL**.
2. Abre <http://localhost/UniPark/> e ingresa, por ejemplo, con `admin` / `demo2026*`.
3. El botón **Admin** de MySQL abre phpMyAdmin (<http://localhost/phpmyadmin/>), donde aparece la base `unipark_db` con todas las tablas.
4. Opcional, para el cobro automático sin depender de las pantallas abiertas:
   ```bash
   C:\xampp\php\php.exe C:\xampp\htdocs\UniPark\scripts\cobros.php --bucle
   ```

Si el panel dice que el puerto 80 o el 3306 está en uso, otra instancia de Apache o MySQL quedó abierta:
ciérrala con `C:\xampp\apache_stop.bat` y `C:\xampp\mysql_stop.bat` y vuelve a pulsar **Start**.