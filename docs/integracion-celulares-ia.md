# Celulares, lectura de carnets e IA de vehículos

Guía para la demostración sin hardware especializado: qué hace cada celular, cómo entrenar el modelo de
Teachable Machine, cómo verificar el código de barras del carnet y cómo probar desde un teléfono.

## 1. Arquitectura

```
 Celular del portero                     Servidor UniPark (PHP + MySQL)            Celular de la persona
 /porteria/escaner                                                                    /mi-cuenta
 ├─ cámara → código de barras ──POST──►  /api/porteria/identificar  (rol o visitante)
 │  (BarcodeDetector o ZXing)            /api/porteria/entrada      (visita + tolerancia)
 ├─ cámara → TensorFlow.js ──tipo──►     /api/porteria/salida       (solo consulta el pago)
 │  (o lo toma del celular de cámara)                                                    ▲
 └─ veredicto de salida                  cron / latido → ServicioCobro ── push ──────────┘
                                         (vence tolerancia → cobro → débito o pendiente)
 Celular de cámara (opcional)
 /porteria/camara ── tipo + confianza ─► /api/camara/deteccion  → tabla puestos_camara
```

- **Las imágenes nunca salen del celular.** El código de barras y el tipo de vehículo se calculan en el
  navegador; al servidor solo llegan el texto del código y la categoría (`CARRO`, `MOTO`, `BICICLETA`,
  `PATINETA`). No se guardan placas, marcas, colores ni fotografías.
- Ambos celulares usan la misma página web y la misma base de datos: solo hace falta que inicien
  sesión con una cuenta de Portería (por ejemplo `porteria` y `porteria2`).

## 2. ¿Uno o dos celulares?

| | Un celular | Dos celulares (recomendado para el evento) |
|---|---|---|
| Hardware | 1 teléfono | 2 teléfonos + un soporte o trípode barato para el de cámara |
| Flujo | El portero escanea el carnet y luego apunta la misma cámara al vehículo | El portero escanea el carnet; el celular fijo ya está viendo el carril |
| Tiempo por entrada | ~4–6 s (mover el teléfono y esperar 1,2 s de estabilidad) | ~2 s (la detección ya está lista al escanear) |
| Riesgos | Si el carnet está lejos del vehículo hay que caminar; más movimiento → más detecciones dudosas | El celular fijo debe tener batería y conexión; se usa el "bloqueo de pantalla" automático (Wake Lock) |
| Configuración | Escáner en modo «Un celular» | Escáner en modo «Dos celulares · Portería principal» y el otro en `/porteria/camara` |

**Recomendación:** dos celulares. El reconocimiento continuo en un teléfono fijo da resultados más estables
(siempre el mismo ángulo y distancia) y la portería se ve fluida en la demostración. Con un solo teléfono
el sistema funciona igual; sirve como respaldo si uno se descarga.

Cómo se conectan: el celular de cámara publica cada segundo la clase que ve (`/api/camara/deteccion`).
Cuando el portero escanea un carnet, su celular consulta la última detección del puesto
(`/api/camara/ultima`), la acepta si tiene menos de 8 s y supera el umbral de confianza, y registra la entrada.

## 3. Reconocimiento del vehículo (Teachable Machine + TensorFlow.js)

El sistema funciona desde ya con el modelo preentrenado **COCO-SSD** para automóvil (`car`), motocicleta
(`motorcycle`) y bicicleta (`bicycle`). **COCO-SSD no tiene una clase de patineta ni de scooter** (su
`skateboard` es una tabla deportiva y no se usa): con él una patineta da «Desconocido» y Portería la clasifica
a mano. Para reconocer patinetas, y para mejorar las demás clases, entrena un modelo propio con fotos del carril real:

1. Entra a <https://teachablemachine.withgoogle.com/> → **Get started → Image project → Standard image model**.
2. Crea estas clases (el nombre importa; las tildes y mayúsculas no):
   `Motocicleta`, `Automovil`, `Bicicleta`, `Patineta` y **`Ninguno`** (carril vacío, personas caminando).
   **`Patineta` = solo scooter** (de pie, con manubrio, manual o eléctrico; definición aprobada). No pongas
   skateboards en esa clase: un skateboard no es patineta y se clasifica a mano como «Desconocido».
3. Toma 150–300 imágenes por clase **desde el mismo lugar donde irá el celular de cámara**, de día y con
   distinto clima, varios colores y modelos, de frente y de lado. Para `Patineta`, incluye scooters manuales
   y eléctricos: COCO-SSD no tiene esa clase.
4. **Train Model** → prueba en vivo → **Export Model → TensorFlow.js → Download**.
5. Copia `model.json`, `metadata.json` y `weights.bin` en `assets/modelo-vehiculos/`.
   Las pantallas lo detectan solas y muestran «Modelo propio (Teachable Machine)». **No hay que modificar nada
   más.** Se probó con un modelo de ese mismo formato (07/11/2026). Para volver a COCO-SSD, borra los tres archivos.

Compatibilidad: el modelo de Teachable Machine se carga con `@tensorflow/tfjs@1.3.1` y
`@teachablemachine/image@0.8.5`; COCO-SSD con `@tensorflow/tfjs@4.22.0` y `@tensorflow-models/coco-ssd@2.2.3`
(versiones fijas desde jsDelivr). TensorFlow.js necesita `'unsafe-eval'` en la política de contenido: se
permite **solo** en `/porteria/escaner` y `/porteria/camara`.

**Detección confiable o dudosa:** una clase es confiable solo si no es «Desconocido», supera el umbral
(`IA_UMBRAL`, 0,80), le saca 0,25 a la segunda opción y se mantiene 1,2 s. Todo lo demás es dudoso y nunca
registra entradas: Portería elige el tipo. La entrada automática viene desactivada; si se activa, espera 3 s cancelables. Si en 8 s no hay una clase confiable, la pantalla pide la clasificación manual con
cuatro botones. Cada visita guarda si el tipo lo definió la IA o el portero (`visitas.tipo_origen`).

## 4. Código de barras del carnet

**Pendiente de verificar con una muestra real:** no tuve acceso a un carnet físico, así que no pude
confirmar su simbología ni qué número codifica. El lector ya acepta los formatos habituales:
Code 128, Code 39, Code 93, Codabar, ITF, EAN-13/8, UPC-A, PDF417 (cédula), QR y Data Matrix.

Para verificarlo:

1. Abre `/porteria/escaner` y enciende la cámara. Pon el código horizontal dentro del marco, a 15–25 cm y con
   buena luz. Si la imagen se ve borrosa (webcams de portátil con foco fijo), aléjalo un poco o usa
   **«Tomar foto del carnet»**, que usa la cámara nativa con autoenfoque. El campo de búsqueda manual y la
   tarjeta de la persona muestran el número leído; el formato queda en la respuesta de
   `/api/porteria/identificar` (`formato`).
2. Si el código **es el documento**, no hay que hacer nada.
3. Si el código **es otro número** (código estudiantil), regístralo en «Estudiantes y profesores →
   Código de barras del carnet» (`estudiantes.codigo_carnet`). La búsqueda usa primero ese código y luego el documento.

**Cédula de visitantes:** la cédula amarilla tiene un PDF417 al respaldo; se toma el número (posiciones
48–57) y el nombre, y **se descarta todo lo demás** (fecha de nacimiento, RH, sexo, huella) antes de
enviarlo. La cédula digital se lee por la zona MRZ (`ICCOL…`) o el número más largo; el portero puede
corregirlo escribiéndolo. Cualquier documento que no esté registrado se clasifica como visitante.

Para la demostración hay una hoja con códigos de barras de prueba en **Portería → Carnets de prueba**
(`/porteria/carnets`), y cada estudiante/profesor/trabajador ve su carnet digital en **Mi cuenta**.

**Las capturas enviadas se ven borrosas** porque la webcam del portátil tiene foco fijo y no enfoca a
menos de ~40 cm. Ningún lector puede descifrar barras desenfocadas. Para la demostración usa el celular
(enfoca solo), el botón «Tomar foto del carnet» o un lector USB/Bluetooth de códigos de barras (funciona
como teclado en el campo de búsqueda). El escáner avisa cuando detecta una cámara de foco fijo.

**Pendiente:** probar con un carnet real y una imagen nítida para confirmar la simbología y el número.

## 5. Probar desde un celular

La cámara y las notificaciones **exigen HTTPS** en el celular (en el computador basta `localhost`).

1. Inicia Apache y MySQL en XAMPP.
2. Publica el sitio con un túnel HTTPS (ver `manual-instalacion.md`, sección túnel):
   ```bash
   cloudflared tunnel --url http://localhost:80
   ```
   o `ngrok http 80`. Abre en el celular `https://<dirección-del-túnel>/UniPark/`.
3. Celular del portero: inicia sesión con `porteria` / `demo2026*` → menú **Escáner (celular)** → **Encender cámara**.
4. Celular de cámara (opcional): inicia sesión con `porteria2` / `demo2026*` → **Cámara de vehículos** →
   **Encender cámara**, y en el primero elige «Dos celulares · Portería principal».
5. Celular de la persona: `estudiante2` / `demo2026*` (saldo 0) → **Mi cuenta → Activar avisos**.
   En iPhone primero «Compartir → Agregar a inicio» y abrir desde ese ícono (iOS 16.4 o superior).
6. Escanea el carnet de prueba de Santiago Mejía (estudiante2), registra la entrada en carro y espera
   15 minutos: el cobro queda pendiente y llega la notificación. Recarga saldo en Caja (`caja`) y el cobro
   se paga solo; al escanear de nuevo, la salida se autoriza al instante.

Para no esperar 15 minutos en una prueba rápida puedes cambiar temporalmente `tolerancia_minutos` en
`config/app.php` (vuelve a 15 antes del evento).

## 6. El cobro automático necesita un disparador

- **Recomendado:** cron cada minuto: `php scripts/cobros.php` (o `--bucle` en una consola durante el evento).
- **Sin cron:** las pantallas abiertas de Portería, Caja y Mi cuenta llaman a `/api/cobros/procesar`
  cada 20 s; con el escáner abierto en portería el cobro siempre corre.
- **Cron por URL** (hostings sin consola): define `CRON_TOKEN` y llama `https://sitio/cron/cobros?token=…`.
- **La salida nunca cobra.** Si nada de lo anterior corrió, la salida se deniega con una **incidencia**. Caja la ve
  en «Incidencias de cobro» y Portería en el escáner o en Portería; ambos la resuelven con «Procesar cobro»,
  y la persona vuelve a escanear para salir.
- Cron en el hosting (cPanel → Cron Jobs, cada minuto):
  ```bash
  /usr/local/bin/php /home/USUARIO/public_html/UniPark/scripts/cobros.php >/dev/null 2>&1
  ```
  Verificado: 5 ejecuciones simultáneas no duplican cobros, débitos ni avisos, y la fecha y hora son las de
  Colombia (`America/Bogota`, MySQL `-05:00`).
