# Informe final · Fase final y requisitos definitivos de acceso

Fecha: 08/11/2026 · Entorno de prueba: Windows 11, XAMPP, PHP 8.2.12, MariaDB 10.4.32.
Evidencia de pruebas: `docs/evidencias/resultado-pruebas-2026-11-08.txt` (**147 pruebas, 0 fallidas, 0 omitidas**).

Se distinguen siempre tres niveles de evidencia:
**[Automática]** = prueba automática en verde · **[Manual]** = verificado a mano en el navegador o la consola de este equipo ·
**[Pendiente de hardware]** = requiere celulares, cámaras o servidor reales y **no** se ha probado.

---

## 1. Arquitectura actualizada del acceso

```
Punto de acceso (celular/tablet, rol «Punto de acceso»)
  1. Lee carné o cédula (ZXing, PDF417 interpretado en el navegador) ──► POST api/acceso/identificar
        Servidor: busca a la persona, valida activa/registrada, decide ENTRADA o SALIDA,
        crea el evento (eventos_acceso, EN_CAPTURA). Índices únicos: 1 evento abierto por persona,
        por documento y por puesto.
  2. Cámara clasifica el vehículo (TF.js en el navegador; solo viaja tipo + confianza + segunda clase)
        ──► POST api/acceso/{id}/clasificacion
        Servidor: ReglasAcceso::confiable (umbral 0.80, margen 0.25, tipos habilitados).
          ├─ confiable → ENTRADA: crea la visita con tarifa del servidor │ SALIDA: el tipo debe coincidir con la
          │              visita abierta; la salida solo consulta el cobro del día (nunca cobra).
          └─ no confiable / no coincide / sin detección → PENDIENTE_PORTERO
  3. Panel de portería (rol Portería, CLASIFICACION_RESOLVER): avisos cada 3 s, sin duplicados,
        Carro / Moto / Bicicleta / Rechazar ──► POST api/porteria/eventos/{id}/resolver (auditado)
  4. Cron cada minuto: cobro diario al vencer 15 min (débito del saldo o pendiente + aviso) y expiración
        de eventos sin atender (10 min).
```

Documento de diseño: `docs/flujo-acceso-definitivo.md`. Despliegue: `docs/despliegue-produccion.md`.

## 2. Funcionalidades terminadas (con evidencia)

| Funcionalidad | Evidencia |
|---|---|
| Identidad primero (entrada y salida), carné o cédula, no registrado → portero, inactivo → rechazo | [Automática] `AccesoTest` 1-3 |
| Clasificación automática solo con confianza suficiente; validación en el servidor | [Automática] `AccesoTest` 4-6 |
| El estudiante y el dispositivo nunca eligen el tipo; solo el portero, sobre un evento pendiente | [Automática] `AccesoSeguridadTest` 1-3 |
| Panel de portería: evento, identidad validada, fecha/hora, mensaje, opciones; sin duplicados; persiste sin portero | [Automática] `AccesoTest` 2, 7 · [Manual] panel en el navegador |
| Salida: vehículo distinto o sin detección → portero, no cierra | [Automática] `AccesoTest` 12-13 |
| Concurrencia: dos personas en el mismo punto, dos puntos simultáneos, detección vieja del puesto | [Automática] `AccesoTest` 14-16 |
| Reintentos idempotentes, eventos que expiran sin registrar nada | [Automática] `AccesoTest` 8, 17 |
| Tolerancia 15 min, cobro diario único, diferencia por tarifa mayor, salida que no cobra | [Automática] `CobroDiarioTest` (18 pruebas) |
| `aplicarSaldoAPendientes` corregido (saltea el que no alcanza y paga los siguientes) | [Automática] `AdministracionTest::testElSaldoPagaLosPendientesQueAlcanza…` |
| Anulación de cobros desde el historial (solo Administrador, motivo obligatorio) | [Automática] `AdministracionTest` |
| Reverso controlado de recargas (una vez, sin borrar, no si el saldo ya se usó) | [Automática] `AdministracionTest` (3 pruebas) |
| Reportes nuevos: cobros, movimientos, saldos, eventos de acceso, calibración de la IA, CSV | [Automática] `AdministracionTest::testLosReportesNuevosRespetanElRol` |
| Panel del administrador: esperando al portero, deuda pendiente, recargas netas, cobros sin procesar | [Automática] idem |
| Importación de la base institucional (web y consola, validar/aplicar, sin borrar, registro de errores enmascarado) | [Automática] `AdministracionTest` (3 pruebas) · [Manual] consola sobre la base limpia |
| Rol «Punto de acceso» con permiso mínimo y sesión de turno | [Automática] `AccesoSeguridadTest` |
| Textos públicos actualizados al cobro diario (simulador de la página de inicio incluido) | [Manual] simulador probado en el navegador |
| Instalación limpia en base nueva sin datos de demostración | [Manual] `unipark_prueba_limpia` (sección 6) |

## 3. Funcionalidades corregidas

| Problema | Corrección | Evidencia |
|---|---|---|
| `putenv` no es seguro entre hilos (Apache ZTS en Windows): ~17 % de solicitudes concurrentes fallaban | `Env` sin `putenv` (arreglo interno de solo lectura) | [Manual] 300/300 solicitudes concurrentes correctas |
| Sesión del celular de cámara expiraba a los 15 min | Inactividad por rol (Portería 480 min, dispositivo 720 min) | [Automática] |
| API sin sesión respondía 419 CSRF y lo auditaba | 401 JSON `SESION_EXPIRADA` antes del CSRF, errores `{ok,codigo,error}` | [Automática] |
| `/api/cobros/procesar` sin permiso propio | Permiso `COBRO_PROCESAR` | [Automática] |
| SSRF en suscripción push | Lista de servicios permitidos, solo HTTPS, sin redirecciones | [Automática] |
| Librerías de CDN sin integridad | SRI sha384 en las 6 URLs | [Manual] carga en el navegador |
| `aplicarSaldoAPendientes` se detenía en el primer cobro impagable (`break`) | `continue` | [Automática] |
| Latido de la cámara generaba 403 auditados en el kiosco | El latido solo se carga con `COBRO_PROCESAR` y se detiene con 401/403 | [Automática] |
| Cron por URL con fichas cortas | Exige 32+ caracteres | [Automática] |

## 4. Problemas encontrados durante la fase

1. Un comentario terminado en `;` dentro de un `CREATE TABLE` partía la migración → corregido y probado en copia limpia.
2. Una prueba fallaba porque PHP convierte claves numéricas a entero → conversión explícita.
3. La prueba de patineta quedó obsoleta (tipo deshabilitado) → ahora verifica `TIPO_INVALIDO`.
4. La sección de calibración desaparecía sin datos → ahora se muestra con mensaje vacío.
5. La importación no reconocía «Tipo de vinculación» → alias agregados en la configuración.

## 5. Pruebas ejecutadas y resultados

Suite completa: **147 pruebas · 0 fallidas · 0 omitidas · 155 s** (archivo de evidencia en `docs/evidencias/`).

| Caso pedido | Prueba | Tipo |
|---|---|---|
| Carné / cédula | `testIdentificacionConCarneYConCedula` | Simulada (código leído) |
| No registrado / desautorizado | `testPersonaNoRegistrada…`, `testPersonaDesautorizada…` | Automática |
| Automóvil, motocicleta, bicicleta | `testClasificacionConfiable…`, `testClasificacionDeMotoYBicicleta` | Simulada (resultado de IA) |
| Relación persona-vehículo, vehículo diferente, sin detección | `testSalidaConVehiculoDiferente…`, `testSalidaSinDeteccion…` | Automática |
| < 15 min / > 15 min / cobro automático / saldo insuficiente / pendiente | `AccesoTest` 9-11, `CobroDiarioTest` | Automática (reloj simulado con `SET timestamp`) |
| Dos usuarios / dos vehículos simultáneos | `testDosPersonasEnElMismoPunto…`, `testDosPuntosDeAccesoSimultaneos` | Automática |
| Reintentos / cron repetido | `testLosReintentosNoDuplicanEntradas`, `testElCronEjecutadoVariasVecesALaVez…` | Automática |
| Sesión de cámara prolongada | `testSesionDelDispositivoDuraElTurno…` | Automática |
| Recargas, pagos, reembolsos (reintegros), reversos, anulaciones | `CobroDiarioTest`, `PagoTest`, `AdministracionTest` | Automática |
| Permisos de todos los roles | `PermisosTest`, `RolesPruebaTest`, `AccesoSeguridadTest`, `AdministracionTest` | Automática |
| Seguridad: CSRF, inyección SQL, XSS, errores internos, sesión, contraseñas, SSRF, cron, archivos privados | `Security/*` · [Manual] 7 rutas privadas responden 403 | Automática + manual |

## 6. Instalación limpia de prueba

Base nueva `unipark_prueba_limpia`, **sin** datos de demostración, con el instalador del proyecto:
63 sentencias de esquema, 8 roles, 24 permisos, 10 tarifas, 1 usuario (`admin`, bcrypt), 0 personas,
7 migraciones registradas (las `_demo_` omitidas). La cuenta de la aplicación no puede borrar (error 1142).
Importación por consola: validar (3 nuevas, 1 rechazada, nada guardado) y aplicar (3 creadas con
origen INSTITUCIONAL). **`unipark_db` quedó idéntica antes y después** (65 auditoría, 6 movimientos, 17 personas).
La base `unipark_prueba_limpia` se dejó para revisión; se puede borrar cuando ya no se necesite.

## 7. Pruebas pendientes de hardware real

- Lectura de carnés y cédulas reales (PDF417 y código de barras) con buena y mala luz.
- Clasificación en el carril real con COCO-SSD y con un modelo propio entrenado; medir la tasa real de aciertos.
- Modo «dos equipos» (escáner + celular de cámara) con dos celulares físicos y red real.
- Avisos push en Android y iPhone reales.
- Servidor Linux con HTTPS, cron de Linux y respaldo/restauración en ese servidor.
- Uso continuo de un turno completo (batería, calentamiento, reconexión).

## 8. Limitaciones de la IA

- COCO-SSD es un modelo genérico: distingue `car`, `motorcycle` y `bicycle`, pero no fue entrenado con el
  carril real; ángulo, luz, lluvia o vehículos parcialmente visibles bajan la confianza. **No se ha medido
  su precisión con imágenes reales del parqueadero.**
- Solo clasifica la categoría; **no** identifica un vehículo individual (placa, color, marca). La relación
  persona-vehículo en la salida se verifica por categoría; si se requiere identidad del vehículo hay que
  integrar un lector de placas (interfaz `IdentificadorVehiculo` preparada).
- Con varios vehículos en el cuadro, el modelo puede tomar el equivocado: por eso se exige margen entre la
  primera y la segunda clase y, si no se cumple, decide el portero.
- La IA corre en el navegador: un celular lento tarda más en estabilizar la detección.

## 9. Requisitos para entrenar con imágenes reales

1. Fotos tomadas con el mismo celular, la misma posición y el mismo encuadre del carril.
2. Clases: `Automovil`, `Motocicleta`, `Bicicleta` y `Ninguno` (carril vacío, peatones).
3. Al menos 300 a 500 imágenes por clase, de día, de noche, con lluvia, distintos colores y modelos, de frente y de lado.
4. Separar un 20 % para validar y no usarlo en el entrenamiento.
5. Exportar desde Teachable Machine (TensorFlow.js) a `assets/modelo-vehiculos/` (ver `LEEME.txt`).
6. Operar dos a cuatro semanas con el umbral 0.80 y ajustar con el reporte «Calibración de la IA».

## 10. Cambios en la base de datos

Migraciones versionadas (respaldo automático antes de aplicarlas: `storage/backups/unipark_db_20261108_160451.sql`):

| Migración | Cambio |
|---|---|
| `20261108_0900_eventos_acceso_importacion.sql` | `eventos_acceso` (con únicos de idempotencia y disparadores de inmutabilidad), `importaciones`, `estudiantes.programa/origen/sincronizado_en`, `puestos_camara.segunda`, `visitas.id_evento_entrada/salida`, `movimientos_saldo` tipo `REVERSO_RECARGA` con `id_movimiento_origen`, `motivo` y único de reverso |
| `20261108_0910_datos_dispositivo_permisos.sql` | Rol «Punto de acceso» y 6 permisos nuevos |
| `20261108_0920_demo_dispositivos.sql` | Solo demostración: `acceso1`, `acceso2` |

`schema.sql`, `base.sql` y `seed.sql` incluyen estos cambios; una base nueva quedó idéntica a una migrada.
No se borró ningún dato ni registro histórico.

## 11. Archivos modificados y nuevos

**Nuevos:** `app/Services/ServicioAcceso.php`, `ReglasAcceso.php`, `IdentificadorVehiculo.php`,
`SinIdentificadorVehiculo.php`, `ServicioImportacion.php`; `app/Repositories/EventoRepository.php`;
`app/Controllers/AccesoController.php`, `ImportacionController.php`; vistas `estudiantes/_finanzas.php`,
`estudiantes/importar.php`, `reportes/_cobros.php`, `_movimientos.php`, `_saldos.php`, `_eventos.php`,
`_calibracion.php`; `assets/js/avisos.js`; `scripts/importar-personas.php`; 3 migraciones;
`tests/Integration/AccesoTest.php`, `AdministracionTest.php`, `tests/Security/AccesoSeguridadTest.php`;
`.env.production.example`; `docs/flujo-acceso-definitivo.md`, `docs/despliegue-produccion.md`,
`docs/ejemplos/personas-ejemplo.csv`, este informe y la evidencia de pruebas.

**Modificados:** `app/Kernel.php`, `app/routes.php`, `app/Helpers/Env.php`, `WebPush.php`,
`app/Middleware/AuthMiddleware.php`, `config/app.php`, `config/permissions.php`, servicios `ServicioEntrada`,
`ServicioSalida`, `ServicioCobro`, `ServicioSaldo`, repositorios `VisitaRepository`, `PuestoCamaraRepository`,
`CobroRepository`, `SaldoRepository`, `EstudianteRepository`, controladores `CobroController`,
`EscanerController`, `CuentaController`, `CajaController`, `ReporteController`, `EntradaController`,
`EstudianteController`, `DashboardController`; vistas `porteria/escaner.php`, `porteria/index.php`,
`reportes/index.php`, `estudiantes/historial.php`, `estudiantes/index.php`, `dashboard/index.php`, `landing.php`,
`cuenta/index.php`, `carnets.php`, `layouts/movil.php`; JS `escaner.js`, `vehiculos-ia.js`,
`camara-vehiculos.js`, `camara.js`, `latido.js`, `app.js`; CSS `movil.css`, `components.css`;
`scripts/migrar.php`, `scripts/cobros.php`, `app/Services/Instalador.php`; `database/schema.sql`, `base.sql`,
`seed.sql`; `tests/Integration/CobroDiarioTest.php`; `README.md`, `tests/README.md`, `.gitignore`.

## 12. Requisitos de hosting e instrucciones de despliegue

Ver `docs/despliegue-produccion.md`: Linux + Apache 2.4 (`mod_rewrite`) + PHP 8.2 (`pdo_mysql`, `mbstring`,
`openssl`, `curl`) + MariaDB 10.4/MySQL 8.0.16, HTTPS obligatorio, cron cada minuto, respaldo diario,
cuenta de base con privilegios mínimos, lista de verificación de 13 puntos antes de abrir el servicio.

## 13. Decisiones pendientes de la institución

Patineta deshabilitada · personas ausentes del archivo institucional no se desactivan · foto del carril
apagada · visitantes decididos por el portero · eventos expiran a los 10 minutos · el rol Caja no ve los
reportes de cobros y saldos. Todas son configurables sin tocar el código (`config/app.php`, `config/permissions.php`).

## 14. Riesgos

| Riesgo | Mitigación |
|---|---|
| La precisión de la IA en el carril real es desconocida | Umbral y margen en el servidor; portero como respaldo; reporte de calibración |
| Mala luz o cámara de baja calidad impiden leer carnés | Ingreso manual del código en el punto de acceso y entrada manual en Portería |
| Cron detenido | Indicador «Cobros sin procesar» en el panel; la salida registra incidencia y no cobra |
| Pérdida de `storage/push/vapid.php` | Incluirlo en los respaldos |
| Sin identificación de placa, un vehículo de la misma categoría podría intercambiarse | Documentado; requiere lector de placas si la institución lo exige |
| Datos personales en respaldos e importaciones | Acceso restringido, documentos enmascarados en logs, archivo de importación borrado al terminar |
