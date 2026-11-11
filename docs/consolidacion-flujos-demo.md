# UniPark · Consolidación de los flujos de demostración

Fecha: 09/11/2026 · Base de la auditoría: `docs/auditoria-integral-unipark.md`.
Sin cambios de tecnología, sin migraciones nuevas, sin tocar la base real `unipark_db`. Wompi y sus migraciones se
conservan intactos. Suite completa: **190 pruebas, 0 fallidas** (`docs/evidencias/suite-consolidacion-2026-11-09.txt`).

---

## Fase 1 · Protección del simulador de pagos (hallazgo B1)

**Archivos:** `app/routes.php`, `app/Controllers/SimuladorController.php`, `app/Views/simulador/pago.php`,
`app/Views/simulador/panel.php`, `assets/css/components.css`, `tests/Integration/SimuladorDemoTest.php`.

**Terminado:**
- El checkout simulado exige sesión (`CUENTA_PROPIA`) y CSRF; el panel y sus acciones, permiso de Administración (`SALDO_REVERSAR`).
- El servidor valida la firma de integridad, el regreso exacto a esta instalación y que la recarga sea **de quien paga**,
  del simulador, en estado `CREADA`, sin transacción previa y por el mismo monto. Sin doble acreditación (idempotencia
  existente del webhook, reprobada).
- El checkout usa el diseño de UniPark (layout de la aplicación) con el rótulo «Simulación académica».

**Pruebas:** `SimuladorDemoTest` 9/9 (anónimo → 303, sin CSRF → 419, recarga ajena → 422, Caja en el panel → 403, duplicado sin doble crédito).

**Prueba manual:** `estudiante3` → Mi saldo → Recargar → Pagar → Aprobar. Luego, en otro navegador como `caja`,
abrir `/simulador-pago/panel` → «No tienes permiso».

---

## Fase 2 · Identificación de personas

**Archivos:** `app/Services/LectorDocumento.php`, `app/Controllers/EscanerController.php`,
`app/Views/porteria/escaner.php`, `app/Views/porteria/carnets.php`, `assets/js/escaner.js`, `assets/js/camara.js`,
`assets/js/carnets.js`, `assets/css/movil.css`, `tests/Integration/IdentificacionTest.php`.

**Terminado:**
- Formatos: Code 128/39/93, Codabar, ITF, EAN, UPC, QR, Data Matrix y PDF417 por cámara; lector USB/Bluetooth tipo
  teclado; digitación; **OCR** (Tesseract.js 5.1.1, se carga solo al pedirlo; el número leído queda en el campo para
  que el portero lo confirme, nunca se envía solo).
- Estados visibles: código detectado (con formato), lectura fallida, validando, persona encontrada / no encontrada, error de formato.
- Carnés ficticios imprimibles con Code 128, QR y PDF417 (bwip-js 4.7.0), sello «Carné ficticio» y documento
  enmascarado. Fuera de la demo solo los ve Administración (hallazgo B7).

**Pruebas:** `IdentificacionTest` 4/4. En el navegador: 48/48 códigos dibujados y decodificados con ZXing; OCR leyó
«C.C. 1.000.000.003» de un texto generado en pantalla.

**Limitación:** cámara real, OCR con documento real y lector físico **no probados** en este equipo.

---

## Fase 3 · Clasificación vehicular con IA e intervención del portero

**Archivos:** `app/Services/ServicioAcceso.php`, `app/Repositories/EventoRepository.php`,
`app/Controllers/AccesoController.php`, `app/Views/porteria/index.php`, `assets/js/avisos.js`, `tests/Integration/AccesoTest.php`.

**Terminado:**
- Paso automático identificación → cámara → IA (COCO-SSD genérico en el navegador; no hay modelo propio).
- Integridad: el navegador solo **declara** tipo y confianza; el servidor aplica umbral 0,80 y margen 0,25 y registra
  `ACCESO_CLASIFICADO_IA` con lo reportado. La entrada manual nunca queda con origen IA (hallazgo B8).
- (Actualizado el 10/11/2026: ahora el portero confirma TODA detección de la IA; ver docs/manual-demo.md, sección 6.1.)
- Portería ve **en tiempo real** (solo lectura) a cada persona identificada; la alerta «Requiere intervención» con los
  botones Carro / Moto / Bicicleta aparece **solo** al portero y **solo** cuando la IA no reconoce el vehículo. El
  estudiante nunca elige la categoría.

**Pruebas:** `AccesoTest` 19/19 (actividad del portero, alerta solo con baja confianza, traza en auditoría).

**Limitación:** IA no probada con vehículos reales; no identifica un vehículo individual ni su placa.

---

## Fase 4 · Integración de entrada y salida

**Terminado:** el escáner muestra el detalle del resultado.
- Entrada: persona enmascarada, vehículo y método, fecha y hora, estado de la visita, «Sin cobro hasta» (tolerancia).
- Salida: persona, categoría al entrar y al salir, tiempo, cobro, estado del pago, autorización o bloqueo y, si hay
  deuda, cómo pagarla (recargar en Mi saldo o pagar en Caja y volver a escanear).

**Pruebas:** `AccesoTest::testElResultadoMuestraElDetalleDeEntradaYSalidaConDeuda` (entrada → tolerancia vencida →
deuda → salida denegada → recarga → salida autorizada).

---

## Fase 5 · Ajustes visuales (hallazgos B4 y B10)

**Archivos:** `app/Views/porteria/index.php`, `app/Views/landing.php`, `assets/css/components.css`,
`assets/css/styles.css`, `assets/css/movil.css`.

**Terminado:**
- El campo del documento es lo primero después del título en Portería (también en celular).
- El menú del celular baja de línea en vez de quedar recortado.
- Bordes de campos con contraste ≥ 3:1 (`#8a8578`), foco visible (contorno amarillo), tonos de aviso y error más
  oscuros, radios de 2 px y sin sombras en paneles, KPI, tablas, recibos y avisos.
- Rótulo «MODO DEMOSTRACIÓN» también en la página de inicio; franja más compacta en celular.

**Verificación:** navegador a 375 px: campo visible sin desplazar, menú completo en tres líneas.

---

## Fase 6 · Escenarios de demostración y separación de datos

**Archivos:** `scripts/demo-escenarios.php` (nuevo), `scripts/demo.php`, `app/Helpers/Logger.php`,
`app/bootstrap.php`, `tests/run.php`, `app/Controllers/CuentaController.php`, `tests/Security/RolesPruebaTest.php`, `docs/manual-demo.md`.

**Terminado:**
- Los escenarios corren con la configuración de la demo (corrige B9) y solo sobre `unipark_demo`: visitas LEGADO
  cerradas con auditoría; saldo suficiente (Valentina, salida autorizada); saldo insuficiente (Santiago, salida
  bloqueada); recargas aprobada, rechazada y pendiente.
- Logs separados (B11): sistema real `storage/logs`, demo `storage/logs/demo`, pruebas `storage/logs/pruebas`.
- «Mi cuenta» ofrece recargar en Mi saldo cuando las recargas en línea están disponibles.

**Verificación:** `php scripts/demo.php --reiniciar` → 3 visitas LEGADO cerradas, recarga aprobada `ACREDITADA`,
saldo final de Valentina $18.000; Portería muestra a Santiago con cobro pendiente y a Valentina con el cobro pagado.

**Escenarios en vivo** (visitante, clasificación correcta y de baja confianza): ver `docs/manual-demo.md`, sección 6.1.

---

## Pendiente fuera de este alcance

- Probar con el celular real: cámara, lector físico, OCR con un documento y la IA con vehículos.
- Confirmar el formato real del carné institucional y del PDF417 de la cédula.
- Activar Wompi sandbox con las llaves de prueba propias (el adaptador está listo; requiere la inscripción).
