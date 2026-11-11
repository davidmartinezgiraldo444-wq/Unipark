# UniPark · Estado del proyecto

Actualizado: 10/11/2026. Suite automática: ver `docs/evidencias/` (última ejecución completa en verde).

## 1. Qué está listo

| Área | Estado | Detalle |
|---|---|---|
| Entrada, cobro y salida | Funcional y probado | Tolerancia de 15 min (1 min en la demo), un cobro por persona y día, diferencia por vehículo de tarifa mayor, **cobro de cada día siguiente si el vehículo no sale** (desde las 00:00), salida que solo consulta el pago |
| Identificación de personas | Funcional; probado en celular real | Code 128, QR, PDF417 y otros por cámara; lector USB/Bluetooth; digitación; OCR del número impreso. Carnés ficticios imprimibles |
| Clasificación del vehículo | Funcional; IA genérica | COCO-SSD en el navegador. **El portero confirma o cambia toda detección** («Confirmar: Automóvil» o Carro/Moto/Bicicleta). Nada se registra sin su decisión |
| Saldo prepago y Caja | Funcional y probado | Recargas en Caja, débito automático, cobros pendientes que se pagan al recargar, reversos con movimiento compensatorio |
| Recargas en línea | Funcional con pasarela **simulada** | Checkout dentro de UniPark, aprobada / rechazada / pendiente, acreditación única por webhook firmado, conciliación cada 5 min, **reembolsos** (la recarga se ve «Reembolsada» y se avisa) |
| Wompi | Conector listo, sin probar contra Wompi | Firma de integridad, verificación de eventos, consulta de transacciones y candados sandbox/producción. Falta la inscripción (ver sección 4) |
| Avisos | Funcional; push sin probar en celular | Pago verificado, pago pendiente, recargas y reembolsos en Mi cuenta; notificación push si la persona la activa |
| Tareas automáticas | Programadas en Windows | `UniPark\Cobros` y `UniPark\CobrosDemo` (cada minuto), `UniPark\Recargas` y `UniPark\RecargasDemo` (cada 5 min): `scripts\cobros-programar.bat` |
| Demo y celular | Lista | `demo-preparar.bat` (datos de ejemplo), `demo-celular.bat` (túnel rápido), `demo-publico.bat` (dominio fijo de ngrok `eject-whooping-letdown.ngrok-free.dev`). Solo se publica la demo; el sistema real y phpMyAdmin quedan bloqueados |
| Diseño | Aplicado | Dirección «Señalética institucional» (`assets/css/senaletica.css`): Barlow Condensed + IBM Plex, paleta con contraste medido, estados con símbolo, sin sombras, acceso con la imagen nublada |
| Seguridad del entorno | Al día | `root` de MariaDB con contraseña (phpMyAdmin la pide), bases sobrantes eliminadas con respaldo, ninguna mención institucional, secretos fuera de Git |

## 1.1 Fase 1 de la auditoría del 11/11/2026 (cerrada)

| Punto | Resultado |
|---|---|
| Base real con datos y cuentas de demostración | Reinstalada limpia (`scripts\base-limpia.bat`); quedan `admin` (cambio de contraseña obligatorio) y `usuario1`–`usuario3` (Emiliano 1000000010, Juan José 1000000004, Tomás 1000000008) |
| `brag-output/` público | Bloqueado (403) |
| Instalación limpia | `base.sql`: `admin` con cambio obligatorio y registro de todas las migraciones no demo |
| Carné no falsificable | **Decisión: no se implementa.** El portero ve a la persona en cada ingreso |
| Contingencia sin portero | **Decisión: no se implementa.** Siempre hay portero; sin portero la universidad está cerrada |
| Contraseñas cortas de `usuario1`–`usuario3` | **Decisión: se mantienen** (usuarios de prueba) |
| Cuenta huérfana `unipark_dev_app` de MariaDB | Eliminada |

## 2. Limitaciones conocidas

- **IA genérica:** COCO-SSD distingue carro, moto y bicicleta, pero no conoce el parqueadero (en una prueba real una moto
  dio 36 %). Por eso el portero confirma. Mejora posible: entrenar un modelo propio en Teachable Machine con fotos del
  parqueadero y copiarlo en `assets/modelo-vehiculos/` (`model.json`, `metadata.json`, `weights.bin`); se usa solo.
- **Sin identificación individual del vehículo:** se compara la categoría (carro con carro), no la placa.
- **Cédula real:** el formato del PDF417 / QR de la cédula colombiana no está confirmado (la lectura de los carnés sí).
- **Notificaciones push:** no probadas en un celular con la página cerrada.
- **Tareas automáticas:** corren con la sesión de Windows iniciada; en un servidor real se usa cron
  (`docs/despliegue-produccion.md`).
- **Túnel de ngrok gratuito:** muestra un aviso «Visit Site» la primera vez en el navegador; sirve para pruebas y
  sandbox, no para producción.

## 3. Pruebas pendientes con el celular

1. Confirmación del portero en vivo (escanear en el celular, confirmar en el PC) con `demo-publico.bat`.
2. Notificaciones push: entrar como estudiante por la dirección fija, activar avisos en Mi cuenta y esperar el cobro.
3. (Opcional) Cédula real: anotar solo el formato y la longitud que muestra el diagnóstico.

Guion completo: `docs/pruebas-celular.md`.

## 4. Lo último: Wompi sandbox

1. Inscribirse en Wompi y obtener las 4 llaves de **pruebas**: llave pública, llave privada, secreto de eventos y
   secreto de integridad (nunca en el chat ni en Git).
2. Ponerlas en `C:\xampp\htdocs\UniParkDemo\config-demo.php` y cambiar `PASARELA_PROVEEDOR` a `WOMPI`
   (`WOMPI_ENTORNO` sandbox). `demo.php --reiniciar` las conserva.
3. Registrar en el panel de Wompi la URL de eventos:
   `https://eject-whooping-letdown.ngrok-free.dev/UniParkDemo/api/pasarela/wompi/eventos`
4. Abrir `demo-publico.bat`, hacer recargas de prueba (aprobada, rechazada, pendiente) y verificar en
   **Reportes → Recargas en línea** que cada una se acredita una sola vez y que la firma de los eventos coincide.
5. Producción (dinero real): solo con autorización expresa, en un servidor con HTTPS propio y `WOMPI_PRODUCCION_AUTORIZADA`.
