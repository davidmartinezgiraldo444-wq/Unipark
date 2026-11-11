# Auditoría integral y plan de consolidación de UniPark

**Fecha:** 09/11/2026 · **Tipo:** auditoría de solo lectura (no se modificó código, rutas, datos ni configuración).
**Entornos:** XAMPP local (Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32).

| Entorno | Uso en esta auditoría |
|---|---|
| `unipark_db` (sistema real) | Solo consultas `SELECT` / `information_schema` y `migrar.php --estado` |
| `unipark_demo` (copia de demostración, `http://localhost/UniParkDemo/`) | Recorrido HTTP por roles (solo GET) y navegador; nunca se envió un POST |
| `unipark_db_test` (base de pruebas) | Suite automática (se borra y recrea en cada caso) |

**Leyenda de evidencia:** **[V]** verificado en esta auditoría (consulta, prueba o recorrido) · **[C]** leído en el código
actual · **[P]** cubierto por pruebas automáticas que pasaron · **[NV]** no verificado.

---

## 1. Resumen ejecutivo

UniPark es un prototipo **sólido en su núcleo**: los procesos internos (entrada, tolerancia de 15 minutos, cobro diario,
saldo prepago, pagos, anulaciones, salida, auditoría) son reales, están protegidos por el servidor y su integridad
financiera se verificó en la base real y en la demo (11 controles en cero). La suite automática pasa completa y el
recorrido de 9 perfiles por todas las rutas no encontró ni errores 500 ni enlaces rotos.

Lo que separa al proyecto de una demostración «impecable» no son fallas del núcleo sino **bordes**: el checkout
simulado es público (cualquiera que tenga el enlace puede aprobar una recarga ajena), la pantalla de portería en el
celular esconde el campo principal, la demo deja pantallas sin rótulo, la regla de **estadías de varios días** no
existe, el cobro automático depende de que haya una pantalla abierta, y la apariencia todavía usa tarjetas redondeadas
y bordes de bajo contraste. La identificación **individual** del vehículo (placa/RFID) no existe: el sistema solo
compara la **categoría**, y lo documenta.

Las integraciones externas (Wompi, notificaciones push, modelo de IA propio, cámaras, directorio institucional,
hosting) están **preparadas en la arquitectura pero no conectadas**, y el sistema no finge lo contrario: el simulador de
pagos está rotulado como simulación y separado por candados del sistema real.

**Clasificación global:** 14 funcionalidades A · 12 B · 9 C · 5 D (sección 4).

---

## 2. Estado real de la arquitectura

```
Navegador (HTML/CSS/JS propios; TensorFlow.js y ZXing desde CDN con SRI)
   │  formularios POST (CSRF) · fetch JSON /api/…
   ▼
index.php → Kernel (ruta → CSRF → sesión → permiso → controlador)      [C][V]
   ├─ Middleware: AuthMiddleware (sesión, inactividad por rol, usuario releído en cada petición)
   │              RoleMiddleware (permiso de la ruta; deniega y audita)
   ├─ Controllers (19) → Services (28) → Repositories (13) → PDO (consultas preparadas reales)
   └─ Rutas sin sesión ('sesion' => false): webhook, /pago/retorno y el simulador
MariaDB: 20 tablas · 18 disparadores de inmutabilidad · columnas generadas con índice único
Instancias: htdocs/UniPark (real) · htdocs/UniParkDemo (misma base de código, config-demo.php)
```

- **Sin framework**, autoload propio (PSR-4), 80 rutas (40 GET / 40 POST), 11 públicas. [V]
- La lógica de negocio vive en `app/Services`; las reglas puras (`ReglasCobro`, `ReglasAcceso`, `ReglasVisita`) no
  tocan la base y tienen pruebas unitarias. [C][P]
- La pasarela de pagos está detrás de la interfaz `App\Services\Pasarela\PasarelaPagos` (implementaciones
  `PasarelaWompi` y `PasarelaSimulada`); la billetera, los cobros y el libro no dependen del proveedor. [C][P]
- La configuración sale de `config/env.php` + `.env` + variables del servidor; la demo impone la suya con
  `Env::forzarDesde()` (`UNIPARK_ENV_INSTANCIA`). [C][V]

---

## 3. Inventario de módulos

| Módulo | Archivos principales | Pantallas / rutas |
|---|---|---|
| Autenticación y sesión | `AuthController`, `AuthService`, `AuthMiddleware`, `RoleMiddleware`, `Session`, `Csrf` | `/login`, `/logout`, `/cambiar-clave` |
| Usuarios y roles | `UsuarioController`, `ServicioUsuario`, `config/permissions.php`, tablas `roles`, `permisos`, `rol_permiso` | `/usuarios…` |
| Personas (estudiantes, profesores, trabajadores, visitantes) | `EstudianteController`, `ImportacionController`, `ServicioImportacion`, `EstudianteRepository` | `/estudiantes…`, `/estudiantes/importar` |
| Identificación y escaneo | `EscanerController`, `AccesoController`, `ServicioAcceso`, `LectorDocumento`, `assets/js/escaner.js`, `camara.js` | `/porteria/escaner`, `/api/acceso/*` |
| Clasificación vehicular (IA) | `assets/js/vehiculos-ia.js`, `camara-vehiculos.js`, `ReglasAcceso`, `PuestoCamaraRepository`, `config/app.php` (`ia`) | `/porteria/camara`, `/api/camara/*` |
| Entradas y salidas | `EntradaController`, `SalidaController`, `ServicioEntrada`, `ServicioSalida`, `VisitaRepository`, `EventoRepository` | `/porteria`, `/api/porteria/*` |
| Cobro diario y tolerancia | `ServicioCobro`, `ReglasCobro`, `CobroController`, `scripts/cobros.php`, `assets/js/latido.js` | `/api/cobros/procesar`, `/cron/cobros` |
| Billetera, Caja y pagos | `ServicioSaldo`, `ServicioPago`, `CajaController`, `SaldoRepository`, `PagoRepository`, `CobroRepository` | `/caja…`, `/saldo/{id}/reversar` |
| Recargas en línea | `SaldoController`, `ServicioRecargaLinea`, `RecargaLineaRepository`, `Pasarela/*`, `PasarelaController`, `SimuladorController` | `/mi-saldo…`, `/pago/retorno`, `/simulador-pago…`, `/api/pasarela/wompi/eventos` |
| Cuenta propia y avisos | `CuentaController`, `ServicioNotificacion`, `WebPush`, `sw.js`, `mi-cuenta.js` | `/mi-cuenta`, `/api/push/*` |
| Reportes y auditoría | `ReporteController`, `AuditoriaController`, `AuditService` | `/reportes`, `/reportes/exportar`, `/auditoria` |
| Demo | `scripts/demo.php`, `demo-preparar.bat`, `PasarelaSimulada`, `layouts/demo_banner.php`, `htdocs/UniParkDemo/*` | `http://localhost/UniParkDemo/` |
| Operación | `scripts/migrar.php`, `backup.php`, `restore.php`, `conciliar-recargas.php`, `install.php`, `instalar.php` | consola |

---

## 4. Estado de cada funcionalidad (clasificación A · B · C · D)

### A — Funcional y verificado

| Funcionalidad | Evidencia |
|---|---|
| Inicio y cierre de sesión, contraseñas bcrypt, bloqueo por intentos, cambio de clave obligatorio | [P] `AccesoSeguridadTest`, `RolesPruebaTest` · [V] 22/22 hashes `$2y$` |
| Permisos validados en el servidor (no solo menús ocultos) | [V] recorrido: 403 fuera del área de cada rol, 401 en la API sin sesión · [P] `PermisosTest` (11) |
| Expiración por inactividad por rol y regreso seguro tras el login (`?volver=`, solo rutas internas) | [P] `PermisosTest`, `RecargaLineaTest` |
| Sesión intacta al volver de la pasarela (rutas sin sesión, `SameSite=Strict`) | [V] navegador real (sesión anterior) · [P] |
| CSRF en todo POST (excepto webhook y simulador, documentados) | [P] `CsrfTest` (5) · [V] matriz de rutas |
| Gestión de usuarios y personas por el Administrador | [V] pantallas 200 · [P] `AdministracionTest` (9) |
| Registro de entrada en Portería (manual) con tarifa y tolerancia del servidor | [P] `EntradaTest` (11) · [V] ruta completa en la demo |
| Tolerancia de 15 min y salida sin cobro dentro de ella | [P] `CobroDiarioTest` (18), `ReglasCobroTest` |
| Cobro diario único por persona y día, diferencia por vehículo de tarifa mayor | [P] `CobroDiarioTest` |
| Débito del saldo, cobro pendiente sin saldo, pago automático al recargar | [P] `CobroDiarioTest`, `RecargaLineaTest` · [V] demo |
| Salida que solo consulta (no cobra) y deniega con deuda | [P] `SalidaTest` · [V] demo («Pago pendiente…», luego «Salida autorizada») |
| Recarga en Caja, anulación de pagos con reintegro, reverso de recargas (corrección E1 aplicada) | [P] `PagoTest`, `RecargaLineaTest` · [V] migración aplicada |
| Libro de movimientos y auditoría inmutables | [V] 18 disparadores en `unipark_db` · [P] `AuditoriaTest` |
| Reportes, exportación CSV e importación institucional (CSV local) | [V] pantallas 200 · [P] `VistasTest`, `AdministracionTest` |

### B — Implementado, necesita ajustes para la presentación

| # | Funcionalidad | Problema verificado | Dónde |
|---|---|---|---|
| B1 | Checkout DEMO (simulador) | Rutas públicas sin sesión ni CSRF: cualquiera con el enlace firmado puede aprobar una recarga ajena; el **panel** lista referencias y montos de todos y permite aprobar pendientes o reenviar con solo el id | `routes.php` (`/simulador-pago*`), `SimuladorController` · [V] panel 200 como anónimo |
| B2 | Identidad visual del checkout DEMO | Se presenta como «Pasarela de pagos (simulada)» con estilo propio, no integrado a la identidad de UniPark | `layouts/simulador.php` |
| B3 | Rótulo de demostración | Faltan en `/inicio` (sin layout) y en el panel del simulador | `DashboardController::landing`, `views/landing.php` · [V] recorrido |
| B4 | Portería en el celular | El campo del documento queda **debajo del pliegue** (rótulo + menú + texto + botón + panel); el menú horizontal se corta («Cámara de ve…») sin indicio | `views/porteria/index.php`, `layouts/app.php` · [V] captura 375 px |
| B5 | Cobro automático | Solo corre con una pantalla de Portería/Caja abierta (latido); **no hay tarea programada** | `latido.js`, `scripts/cobros.php` · [V] `schtasks` sin tareas |
| B6 | Datos de ejemplo | 3 visitas **abiertas del esquema anterior (LEGADO)** aparecen como «Pendiente» en Portería y confunden la demo | `seed.sql` · [V] consulta |
| B7 | Página de carnets de prueba | Visible en el sistema real para Administración, Auditoría, Caja y Portería (códigos de barras de todas las personas) | `/porteria/carnets` (`ESTUDIANTE_VER`) · [V] recorrido |
| B8 | Origen y confianza de la IA | Llegan del navegador: Portería puede registrar una entrada marcada «IA» eligiendo el tipo; el punto de acceso envía tipo y confianza que el servidor no puede recomprobar | `EscanerController.php:102`, `AccesoController.php:45` · [C] |
| B9 | Aviso del escenario de demo | Dice «paga en Caja» porque el script corre con la configuración principal | `scripts/demo.php` (`escenarioInicial`) |
| B10 | Apariencia | Tarjetas con radio 10–14 px y sombras difusas; borde de campos `#dedbd3` (1,38:1, bajo el 3:1 de WCAG 1.4.11); foco casi invisible; aviso `#9a6a00` sobre `#fbf1d4` (4,20:1) | `assets/css/*` · ver `reports/Diseño frontend parqueadero universitario.md` |
| B11 | Logs | Pruebas, demo y sistema real escriben en el mismo `storage/logs` | `Logger` · [V] errores de pruebas en el log del 08/11 |
| B12 | Escáner de carné | Cámara (BarcodeDetector/ZXing), lector USB/Bluetooth tipo teclado y digitación existen; la cámara **no se pudo probar** aquí y el **formato real del carné no está confirmado** | `escaner.js`, `camara.js`, `LectorDocumento` · [P] lector · [NV] cámara |

### C — Preparado en la arquitectura, depende de integraciones futuras

| Integración | Qué existe | Qué falta |
|---|---|---|
| Wompi (pagos reales) | Adaptador según documentación oficial, firma de integridad verificada con el ejemplo oficial, webhook, consulta, conciliación, candados sandbox/producción | Cuenta de comercio, llaves, HTTPS público, confirmar la firma de eventos con un webhook real, reembolsos |
| Notificaciones push | VAPID propio, suscripción, envío cifrado | HTTPS y prueba en un celular real con la página cerrada [NV] |
| Modelo de IA propio | Carga de Teachable Machine si existe `assets/modelo-vehiculos/model.json` | Entrenar el modelo (hoy usa COCO-SSD genérico) |
| Cámara del puesto (modo dos celulares) | Publicación y lectura de la detección por puesto | Cámaras físicas/IP; hoy, celulares |
| Directorio institucional | Importación CSV con columnas configurables | Fuente oficial y su formato |
| Carnets institucionales | Lectura por cámara/teclado | Muestra real de carné |
| Cobro programado | `scripts/cobros.php`, `/cron/cobros` con token | Tarea programada o cron del hosting |
| Hosting y dominio | Guía `docs/despliegue-produccion.md`, `.env.production.example` | Servidor, HTTPS, contraseñas |
| Identificación individual del vehículo | Interfaz `IdentificadorVehiculo` (implementación actual: ninguna) | Lector de placas/RFID y aprobación de la política de datos |

### D — Incompleto, roto o no implementado

| Funcionalidad | Estado |
|---|---|
| Verificar que el vehículo que sale es **el mismo** que entró | No existe: solo se compara la categoría (`ReglasAcceso::coincideCategoria`) |
| Estadías de varios días | No hay regla: un carro que pasa la noche paga un solo día (`ServicioCobro.php:97` usa la fecha de entrada) |
| Recuperación de acceso por la propia persona | No existe (solo restablecimiento por el Administrador) |
| Reembolsos y conciliación diaria con el proveedor | No implementados (documentado en la auditoría de preparación para Wompi) |
| Búsqueda de transacciones por referencia | No documentada por Wompi; sin ella un pago aprobado sin webhook ni retorno queda sin conciliar |

---

## 5. Flujos completos comprobados

| Flujo | Cómo se comprobó |
|---|---|
| Entrada manual → 1 min (demo) → cobro pendiente → salida denegada → recarga simulada → cobro pagado solo → salida autorizada | Navegador en la demo, 08/11 (Mariana, `estudiante3`) |
| Recarga simulada aprobada / rechazada / pendiente → aprobación posterior; reenvío duplicado sin doble acreditación | Navegador y `SimuladorDemoTest` |
| Regreso desde la pasarela sin perder la sesión | Navegador real con sitio externo + contraprueba |
| Recarga en Caja que paga cobros pendientes; anulación con reintegro y nueva recarga (E1) | `CobroDiarioTest`, `RecargaLineaTest` |
| Identificación → evento → clasificación confiable o pendiente para el portero → entrada/salida | `AccesoTest` (17) |
| Webhook firmado, consulta al proveedor, monto distinto, firma falsa, proveedor caído (503), 4 procesos simultáneos | `RecargaLineaTest` (20) con un Wompi simulado sin internet |

## 6. Flujos incompletos o defectuosos

1. **Checkout DEMO abierto** (B1): falta exigir la sesión de la dueña de la recarga y restringir el panel.
2. **Cobro sin pantallas abiertas** (B5): sin tarea programada, una salida tras la tolerancia queda como incidencia.
3. **Estadías de varios días** (D): regla de negocio pendiente de definir.
4. **Portería en celular** (B4): el flujo funciona, pero la acción principal no está a la vista.
5. **Escaneo por cámara** (B12): no verificado en esta auditoría.

---

## 7. Estado real de la base de datos

Consultado el 09/11/2026 con la cuenta administrativa, solo lectura. [V]

| | `unipark_db` | `unipark_demo` |
|---|---|---|
| Tablas / disparadores | 20 / 18 | 20 / 18 |
| Personas / usuarios | 17 / 22 | 17 / 22 |
| Visitas (abiertas LEGADO) | 10 (3) | 10 (3) + 1 del escenario |
| Cobros diarios | 0 | 1 pendiente (escenario) |
| Pagos / movimientos | 7 / 6 | 7 / 6 |
| Recargas en línea | 0 | 0 |
| Saldo total | $ 49.500 | $ 49.500 |
| Auditoría | 80 | 4 |

**Controles de integridad (los 11 en 0 en ambas bases):** saldo = último saldo del libro · saldo = suma del libro ·
cobro PAGADO con pago válido · cobro PENDIENTE sin pago válido · pago con saldo con su DEBITO · recarga APROBADA con su
movimiento · movimiento PASARELA con su recarga · ninguna tolerancia vencida sin liquidar · ninguna persona con dos
visitas abiertas · ningún saldo negativo · ninguna contraseña fuera de bcrypt.

**Bases sobrantes en el servidor:** `unipark_dev`, `unipark_prueba_limpia`, `unipark_test`, `unipark_fase1_verificacion`
(además de `unipark_db_test`, que usan las pruebas). La cuenta de la aplicación no tiene `DELETE` en `unipark_db`. La
cuenta `root` de MariaDB **no tiene contraseña**.

## 8. Estado de las migraciones

11 de 11 aplicadas en `unipark_db` y en `unipark_demo` (`migrar.php --estado`). Las dos últimas
(`20261108_1000_debito_unico_por_pago`, `20261108_1010_recargas_en_linea`) se aplicaron el 08/11/2026 a las 22:04
con respaldo previo (`storage/backups/unipark_db_20261108_220457.sql`) y validación documentada en
`docs/fase1-validacion-migraciones.md`. La base de pruebas se construye desde `schema.sql` (no desde las migraciones);
la ruta de migración se ensayó en una copia durante la Fase 1.

## 9. Estado del sistema financiero

- **Modelo:** saldo prepago interno; nunca se debita una tarjeta ni una cuenta. [C]
- **Libro único** `movimientos_saldo` (RECARGA, DEBITO, REINTEGRO, REVERSO_RECARGA): solo INSERT, disparadores contra
  UPDATE/DELETE, regla de cuadre `saldo_nuevo = saldo_anterior ± monto`. [V][P]
- **Idempotencia:** un cobro DIARIO por persona y día (`cobro_vigente`), una diferencia por visita, un pago válido por
  cobro (`pago_cobro_valido`), un DEBITO por pago (`debito_unico`), una acreditación por recarga
  (`uq_recargas_movimiento`), huella única por notificación. [V][P]
- **Transacciones** con bloqueo de filas en orden fijo (persona → visita/recarga → cobro). [C][P]
- **Concurrencia:** 4 procesos que acreditan la misma recarga a la vez → 1 acreditación. [P]
- **Pendiente:** estadías de varios días, reembolsos, conciliación diaria con el proveedor.

## 10. Estado de la pasarela y del modo DEMO

| Requisito | Estado |
|---|---|
| Billetera → valor → checkout → resultado → acreditación única → historial, todo dentro de UniPark | Cumplido [V] |
| Simulación honesta y rotulada | Cumplido (franja roja «SIMULACIÓN — no es Wompi») [V] |
| Aprobado / rechazado / pendiente | Cumplido [V][P] |
| El navegador no puede producir una acreditación arbitraria | **Parcial**: el monto va firmado y sale de la recarga, y la acreditación pasa por el procesador del webhook con firma y consulta; pero **cualquier persona** puede aprobar una recarga ajena (B1) |
| Checkout con la identidad de UniPark | **Parcial** (B2) |
| Producción futura: adaptador, variables de entorno, credenciales solo en servidor, firmas, webhook, consulta, monto/moneda/referencia, idempotencia, conciliación, estados | Preparado [C][P]; sin prueba contra Wompi real |
| Activación solo por configuración del servidor | Cumplido: `PASARELA_PROVEEDOR`, `WOMPI_ENTORNO`, `WOMPI_PRODUCCION_AUTORIZADA`; el simulador se niega en producción y sobre `unipark_db` [P] |

## 11. Estado de la IA y de los escáneres

| Elemento | Real | Simulado / pendiente |
|---|---|---|
| Lectura de carné por cámara | BarcodeDetector nativo o ZXing (CDN con SRI) en el navegador [C] | Cámara no probada aquí [NV]; formato del carné real sin confirmar |
| Lector USB/Bluetooth | Funciona como teclado en el campo del escáner [C] | — |
| Digitación manual | Sí [V] | — |
| Modelo de IA | COCO-SSD **genérico** preentrenado en el navegador [C] | No hay modelo propio entrenado (`assets/modelo-vehiculos/` sin `model.json`) [V] |
| Clases | Automóvil, motocicleta, bicicleta (patineta deshabilitada) [C] | — |
| Umbral | 0,80 de confianza y 0,25 de margen, reaplicados en el servidor [C][P] | Los valores los envía el navegador (B8) |
| Resultado dudoso | Pasa al **panel del portero** (`CLASIFICACION_RESOLVER`), el estudiante nunca elige [C][P] | — |
| Identificación individual | No existe (D) | Interfaz preparada (C) |

## 12. Auditoría visual

Recorrido en la demo (escritorio y 375 px) y en el navegador. [V]

- **Sin errores de JavaScript** en Portería, Escáner, Panel y Reportes.
- **Sin desbordamiento horizontal** a 375 px; las tablas se desplazan dentro de su contenedor.
- **Ningún botón ni enlace roto** (seguimiento de todos los enlaces internos por rol).
- Problemas: B3 (rótulo), B4 (portería móvil), B10 (radios, sombras, contraste de bordes y foco), B2 (identidad del
  checkout), B6 (datos LEGADO). La dirección visual recomendada está en `reports/Diseño frontend parqueadero universitario.md`.

## 13. Auditoría de seguridad

| Tema | Estado |
|---|---|
| Autorización en servidor | Correcta en las 80 rutas [V][P] |
| CSRF | Todo POST con token salvo webhook (firma) y simulador (B1) [V] |
| XSS | Salida escapada con `e()`; CSP `script-src 'self'` (más jsDelivr y `unsafe-eval` solo en cámaras) [C][P] |
| SQL injection | Consultas preparadas reales (`EMULATE_PREPARES=false`) [C][P] `SqlInjectionTest` |
| Sesiones | `HttpOnly`, `SameSite=Strict`, `Secure` en HTTPS, regeneración al ingresar, cookie distinta por instancia [C][V] |
| Datos personales | Documentos enmascarados en reportes y CSV; la cámara no envía imágenes por defecto [C]; página de carnets expuesta (B7) |
| Integridad financiera | Ver sección 9 [V][P] |
| Secretos | `config/env.php` con la contraseña de la base en texto plano (protegido por `.htaccess` y por ser PHP); `root` sin contraseña; ningún secreto en los logs [V] |
| Errores | `APP_DEBUG=0`; mensajes genéricos al usuario; detalle en `storage/logs` [C][P] |
| Webhook | Firma + entorno + consulta al proveedor; sin límite de tasa para firmas inválidas (cada intento inserta una fila) [C] |
| Dependencias externas | jsDelivr (ZXing, TensorFlow.js, COCO-SSD, JsBarcode) con hashes SRI; sin internet no hay IA ni ZXing [C] |
| Concurrencia MariaDB | `Env` sin `putenv()` (corrección del 08/11); 4 procesos simultáneos sin duplicados [C][P] |
| Varios | `composer.json` contiene el nombre de la institución; `APP_ENV=produccion` en la instalación local real (bloquea los sandbox, coherente) |

## 14. Resultados de pruebas

Ejecutadas el 09/11/2026 en `unipark_db_test` (`php tests/run.php`). Evidencia: `docs/evidencias/resultado-pruebas-auditoria-2026-11-09.txt`.

**Resultado: 183 pruebas · 0 fallidas · 0 omitidas · 153.7 s.** Ninguna prueba se omitió ni falló.

| Grupo | Archivos | Pruebas |
|---|---|---|
| Unitarias | 7 | 34 |
| Integración | 11 | 110 |
| Seguridad | 5 | 39 |

Además: recorrido HTTP de 9 perfiles en la demo (sin 500 ni enlaces rotos), controles de integridad en `unipark_db` y
`unipark_demo`, revisión de consola y de 375 px en el navegador.

**No se pudo comprobar:** cámara y lectura real de carnés; notificaciones push en un celular; Wompi real; rendimiento
con muchos usuarios (no hay pruebas de carga); accesibilidad con lector de pantalla.

## 15. Riesgos y limitaciones

1. Sin identificación individual del vehículo, un estudiante podría salir en otro carro de la misma categoría.
2. La confianza de la IA es la que reporta el dispositivo (B8): un dispositivo manipulado podría declarar «bicicleta».
3. Sin tarea programada, la demo depende de dejar abierta la pantalla de Portería.
4. El simulador abierto permite aprobaciones ajenas en la demo (B1).
5. Sin internet en la presentación no cargan la IA ni el lector ZXing (el campo manual y el lector USB sí funcionan).
6. `root` sin contraseña y bases sobrantes en el servidor local.

## 16. Evidencias y archivos involucrados

- Scripts de esta auditoría (fuera del proyecto, carpeta temporal de la sesión): `estado_bd.php` (consultas de solo
  lectura) y `recorrido.php` (GET por rol en la demo).
- `docs/evidencias/resultado-pruebas-auditoria-2026-11-09.txt` (suite completa).
- `docs/fase1-validacion-migraciones.md`, `docs/recargas-en-linea.md`, `docs/manual-demo.md`,
  `reports/Diseño frontend parqueadero universitario.md`.

## 17. Recomendaciones

Ver el plan por fases (sección siguiente). Prioridad inmediata: B1, B5, B7, la regla de estadías (D) y la portería en
el celular (B4).

---

## Plan de consolidación por fases

El orden propuesto adelanta la parte visual de la portería (B4) dentro de la Fase 2 porque bloquea la demostración en
el celular, y deja la identidad visual completa (Fase 5) para cuando los flujos estén cerrados.

| Fase | Objetivo | Resuelve | Complejidad |
|---|---|---|---|
| 1 | Estabilidad, seguridad e integridad | B1, B7, B8 (mitigación), B11, contraseña de `root`, bases sobrantes, `composer.json` | Baja–media |
| 2 | Flujos del parqueadero completos | Estadías de varios días (D), cobro programado (B5), portería en celular (B4), datos LEGADO (B6) | Media |
| 3 | Finanzas y checkout DEMO | B2, B3, B9, conciliación y reembolsos simulados, adaptador Wompi intacto | Media |
| 4 | Escáneres, IA e intervención manual | B12, prueba de cámara, guía para entrenar el modelo propio, registro de origen confiable | Media–alta |
| 5 | Identidad visual y paneles | B10, dirección «Señalética institucional», paneles por rol, reportes | Alta |
| 6 | Datos ficticios, pruebas integrales y demo | 200 usuarios ficticios con carnés de prueba, guion, pruebas de flujo completo | Media |
| 7 | Documentación y hoja de ruta | Arquitectura, limitaciones, hosting e integraciones futuras | Baja |

### Fase 1 · Correcciones críticas
- **Objetivo:** cerrar los huecos que un evaluador podría explotar en la demo y dejar el entorno limpio.
- **Archivos:** `routes.php`, `SimuladorController`, `views/simulador/*`, `EscanerController`, `Logger`, `composer.json`.
- **Criterios de aceptación:** el checkout simulado solo lo usa la dueña de la recarga con sesión; el panel solo
  Administración; carnets de prueba solo en la demo; Portería no puede marcar «IA» una elección manual; logs separados
  por instancia; ninguna regresión.
- **Pruebas:** nuevas pruebas de permisos del simulador y de carnets; suite completa.
- **Dependencias/decisiones:** borrar bases sobrantes y poner contraseña a `root` requieren tu autorización.

### Fase 2 · Flujos del parqueadero
- **Objetivo:** que la ruta entrada → salida funcione sin pantallas abiertas y con reglas completas.
- **Archivos:** `ServicioCobro`, `ReglasCobro`, `scripts/cobros.php`, `views/porteria/index.php`, `seed.sql`, `demo.php`.
- **Criterios:** regla de varios días aprobada e implementada con pruebas; tarea programada documentada (y creada si la
  autorizas); campo de búsqueda visible al abrir Portería en 375 px; la demo sin visitas LEGADO confusas.
- **Riesgo:** la regla de varios días cambia cobros: necesita tu definición y no debe alterar la tolerancia.

### Fase 3 · Finanzas y checkout DEMO
- **Objetivo:** checkout simulado con la identidad de UniPark, rótulos completos y conciliación/reembolso demostrables.
- **Archivos:** `layouts/simulador.php`, `SimuladorController`, `landing.php`, `ServicioRecargaLinea`, reportes.
- **Criterios:** recorrido completo sin salir de la identidad de UniPark; ninguna pantalla de la demo sin rótulo;
  reembolso simulado con movimiento compensatorio; Wompi intacto y sus pruebas en verde.

### Fase 4 · Escáneres e IA
- **Objetivo:** demostrar la lectura de carnés y la clasificación con la intervención del portero.
- **Criterios:** prueba en un celular real (HTTPS o localhost); carnés de prueba imprimibles; modelo propio opcional;
  el estudiante nunca elige categoría (ya cumplido, se conserva).
- **Dependencia:** un celular con cámara y, para otro equipo, HTTPS (túnel).

### Fase 5 · Identidad visual
- **Objetivo:** aplicar la dirección «Señalética institucional» sin cambiar tecnología.
- **Criterios:** contraste AA en bordes, foco y avisos; radios 0–2 px; estados con símbolo + color + texto; acceso con
  la imagen nublada conservada.

### Fase 6 · Datos ficticios y demostración
- **Objetivo:** 200 usuarios ficticios por rol con carnés de prueba y un guion integral.
- **Criterios:** datos claramente ficticios (dominio `.test`, documentos no reales); pruebas de flujo completo.

### Fase 7 · Documentación
- **Objetivo:** arquitectura, limitaciones, hoja de ruta de hosting e integraciones.

---

## Decisiones que requieren tu autorización

1. **Regla de estadías de varios días** (cobrar cada día calendario, cada 24 h o un tope).
2. **Acceso al simulador:** solo la dueña de la recarga y panel solo para Administración.
3. **Página de carnets de prueba:** solo en la demo, o también para Administración en el sistema real.
4. **Tarea programada de Windows** para el cobro automático (configuración persistente del equipo).
5. **Eliminar las bases sobrantes** (`unipark_dev`, `unipark_prueba_limpia`, `unipark_test`, `unipark_fase1_verificacion`).
6. **Contraseña para `root`** de MariaDB (afecta phpMyAdmin y los scripts de respaldo).
7. **Visitas LEGADO de la semilla:** cerrarlas/anularlas en la demo o regenerar los datos de ejemplo.
8. **Adoptar la dirección visual** «Señalética institucional» en la Fase 5.
9. **Renombrar** el paquete en `composer.json` para no nombrar a la institución.
