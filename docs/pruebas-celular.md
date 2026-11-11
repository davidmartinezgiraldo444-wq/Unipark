# UniPark · Conectar el celular a la demo y probar el escaneo y la IA

Fecha: 09/11/2026. Aplica **solo a la copia de demostración** (`http://localhost/UniParkDemo/`). El sistema real no cambia.

## 1. Por qué hace falta HTTPS

El navegador del celular solo permite usar la cámara en un **contexto seguro** (`https://` o `localhost`). Si se entra
por `http://192.168.x.x/UniParkDemo`, la página carga pero `navigator.mediaDevices` no existe y el escáner dice
«Este navegador no permite usar la cámara» (`assets/js/camara.js`). El resto ya funcionaba detrás de un túnel:

- La cookie de sesión se marca `Secure` cuando hay HTTPS (`app/Helpers/Session.php`). Se reconoce el HTTPS que llega
  de un túnel que corre en el mismo PC, por la cabecera `X-Forwarded-Proto` (`app/Helpers/Request.php::esHttps`).
- Las rutas se arman a partir de la dirección pedida (`url()`, `url_absoluta()`), así que sirve cualquier dirección.
- La CSP de las pantallas de cámara ya permite ZXing, Tesseract, TensorFlow/COCO-SSD y la cámara (`Response::conBibliotecasDeCamara`).

## 2. Cómo queda la conexión

```
Celular (usuario acceso1)                    https://xxxx.trycloudflare.com/UniParkDemo/porteria/escaner
   │  HTTPS con certificado válido
   ▼
Cloudflare  ──túnel cifrado (sale desde el PC)──►  cloudflared (en el PC)
                                                     │
                                                     ▼
                                    Apache 127.0.0.1:8090  (scripts/apache/unipark-demo-celular.conf)
                                    solo /UniParkDemo + estilos y scripts públicos; el resto → 403
                                                     │
                                                     ▼
                                    UniParkDemo ──► MySQL unipark_demo
                                                     ▲
PC (usuario porteria)  http://localhost/UniParkDemo/porteria  ── panel «En tiempo real» y alertas
```

- **El celular** identifica a la persona (cámara, OCR o digitación) y clasifica el vehículo con la IA en el navegador.
- **El PC** muestra, en Portería, a cada persona que se identifica y **cada detección de la IA para que el portero la
  confirme** («Confirmar: Automóvil») o la cambie (Carro, Moto, Bicicleta). Si la IA no reconoce el vehículo, la
  alerta es «Requiere intervención» con los tres botones. Nada se registra hasta que el portero decide.

Por qué el puerto 8090 y no el 80: un túnel hacia el puerto 80 publicaría en internet todo XAMPP (el sistema real,
phpMyAdmin y el panel de XAMPP). El sitio 8090 escucha solo en el propio PC y deja pasar únicamente la demo.

## 3. Preparación (una sola vez)

1. **Apache:** `C:\xampp\apache\conf\httpd.conf` incluye al final
   `Include "C:/xampp/htdocs/UniPark/scripts/apache/unipark-demo-celular.conf"`.
   Después de agregarlo hay que **reiniciar Apache** en el Panel de XAMPP (Stop y Start).
2. **cloudflared:** en una consola, `winget install --id Cloudflare.cloudflared` (programa oficial de Cloudflare).
   No hace falta crear una cuenta para el túnel rápido.

Para quitarlo: borra la línea `Include` de `httpd.conf`, reinicia Apache y desinstala cloudflared
(`winget uninstall --id Cloudflare.cloudflared`).

## 4. Cada vez que vayas a probar

1. Enciende Apache y MySQL en XAMPP. Si quieres partir de cero, ejecuta `scripts\demo-preparar.bat`.
2. Doble clic en **`scripts\demo-celular.bat`**. El script:
   - comprueba que el sitio 8090 deje pasar la demo y bloquee `/UniPark`, phpMyAdmin y el panel de XAMPP;
   - cambia la contraseña de **todos** los usuarios de la demo por una **aleatoria** y la muestra en esa ventana
     (`demo2026*` deja de servir desde internet; `demo.php --reiniciar` la restablece);
   - cierra cualquier túnel anterior, abre uno nuevo y muestra en un recuadro la dirección completa del escáner
     («ABRE ESTO EN EL CELULAR»). **Usa siempre la de esta vez**: la de una prueba anterior responde «Not Found».
3. En el **celular**, abre `https://….trycloudflare.com/UniParkDemo/porteria/escaner` y entra como **`acceso1`**
   con la contraseña que mostró la ventana. Permite la cámara cuando el navegador lo pida.
4. En el **PC**, abre `http://localhost/UniParkDemo/porteria` y entra como **`porteria`** (misma contraseña).
5. Abre «**Diagnóstico del dispositivo (demo)**» al pie del escáner. Debe decir **Contexto seguro: Sí**.
6. Al terminar: **pulsa una tecla** en la ventana del túnel. La dirección deja de funcionar.

## 5. Guion de pruebas en hardware real

Marca cada prueba como ✓, ✗ o «no probada» y anota lo que diga el diagnóstico. **No** tomes capturas que muestren
documentos completos. Evidencia del servidor: **Auditoría** (como `admin` o `auditor`), el panel de Portería y
`storage/logs/demo/`.

| # | Qué haces | Qué debes ver | Evidencia |
|---|---|---|---|
| 1 | Imprime `/porteria/carnets` (como `admin` o `porteria` en el PC) o ábrela en otra pantalla. Escanea el **Code 128** de Mariana (1000000003) | «Código detectado · CODE 128», luego el nombre, y pasa sola a la cámara del vehículo | Diagnóstico: lector (BarcodeDetector o ZXing) y formato · Portería → En tiempo real |
| 2 | Escanea el **QR** del mismo carné | «Código detectado · QR CODE» y la misma persona | Ídem |
| 3 | Escanea el **PDF417** | «Código detectado · PDF417» y la misma persona | Ídem |
| 4 | Conecta un **lector USB/Bluetooth** tipo teclado y escanea el Code 128 | El número entra en el campo y se identifica | Diagnóstico: «Digitación, OCR o lector USB/Bluetooth» |
| 5 | **Digita** 1000000001 y pulsa Continuar | Valentina identificada | Ídem |
| 6 | **OCR:** «Leer número impreso» → foto del número impreso de un carné ficticio | El número queda en el campo; tú lo confirmas | Ningún envío hasta que pulses Continuar |
| 7 | **Cédula real** (con permiso de la persona): reverso de la cédula amarilla (PDF417) o QR de la cédula digital | Anota **solo** si se leyó y qué formato muestra el diagnóstico. Si dice «Documento no registrado» es normal: esa persona no está en la demo | Diagnóstico (formato y longitud). No guardes fotos ni el número |
| 8 | Tras identificar a alguien, apunta a un **carro** real de lado | Celular: «Vehículo detectado: Automóvil. Un portero lo está confirmando». PC: **Confirmar: Automóvil** → al pulsarlo se registra la entrada | Diagnóstico: IA, confianza y ms · Auditoría: `ACCESO_CLASIFICADO_IA` |
| 9 | Igual con una **moto** | «Motocicleta · NN %» | Ídem |
| 10 | Igual con una **bicicleta** | «Bicicleta · NN %» | Ídem |
| 11 | Apunta a algo que **no sea un vehículo** (una pared) | El celular dice «Espera un momento… un portero lo está verificando». En el PC aparece **Requiere intervención** con los botones | Panel de Portería · Auditoría (clasificación del portero) |
| 12 | **Salida con saldo:** escanea a Valentina (1000000001), que ya está dentro | «Salida autorizada» con el detalle del cobro pagado | Escáner (detalle) · Auditoría |
| 13 | **Salida con deuda:** escanea a Santiago (1000000002) | «Salida denegada», el valor pendiente y cómo pagarlo | Escáner (detalle) · Auditoría `SALIDA_DENEGADA` |

Consejos: el código horizontal dentro del marco, a 15–25 cm y con buena luz. La primera carga de la IA descarga
varios MB (usa Wi-Fi). En iPhone, Safari no trae lector nativo y usa ZXing, que puede ser más lento con PDF417.

## 6. Si la cédula real no se interpreta bien

`app/Services/LectorDocumento.php` reconoce hoy el número del carné, una cédula en texto y el PDF417 **ficticio** de
UniPark (`UNIPARK-FICTICIO`). El formato del PDF417 de la cédula amarilla y del QR de la cédula digital **no está
confirmado**. Lo que hay que anotar en la prueba 7 (sin datos personales):

- el formato que muestra el diagnóstico (PDF417 o QR) y la longitud en caracteres;
- si el escáner dijo «Documento no registrado» (se leyó un número) o «Error de formato» (no se encontró el número).

Con eso se adapta `LectorDocumento::interpretar()`, siempre con pruebas automatizadas que usen **solo datos ficticios**
con la misma estructura (`tests/Integration/IdentificacionTest.php`). Nunca se copian datos de un documento real a
las pruebas.

## 6.1 Dirección FIJA con ngrok (para Wompi sandbox)

`demo-celular.bat` usa un túnel rápido cuya dirección cambia cada vez. Para registrar el **webhook de Wompi** hace
falta una dirección que no cambie: **`scripts\demo-publico.bat`** abre la demo en el dominio gratuito de ngrok
`eject-whooping-letdown.ngrok-free.dev` (guardado en `config-demo.php` como `NGROK_DOMINIO`; `demo.php --reiniciar`
lo conserva). Hace lo mismo que `demo-celular.bat` (verifica el sitio 8090, cambia la contraseña de la demo, cierra
túneles anteriores) y muestra:

| Uso | Dirección |
|---|---|
| Escáner (celular, `acceso1`) | `https://eject-whooping-letdown.ngrok-free.dev/UniParkDemo/porteria/escaner` |
| Mi cuenta | `https://eject-whooping-letdown.ngrok-free.dev/UniParkDemo/mi-cuenta` |
| Webhook para registrar en Wompi | `https://eject-whooping-letdown.ngrok-free.dev/UniParkDemo/api/pasarela/wompi/eventos` |

- La primera vez, en el navegador ngrok muestra «You are about to visit…»: pulsa **Visit Site**. Los avisos de
  Wompi (servidor a servidor) no ven esa página.
- El token de ngrok está en `%LOCALAPPDATA%\ngrok\ngrok.yml` (no está en el proyecto). Si se expone, se regenera
  en dashboard.ngrok.com → Your Authtoken y se vuelve a registrar con `ngrok config add-authtoken`.
- Verificado el 10/11/2026 desde internet: demo 200, `/UniPark`, phpMyAdmin, panel de XAMPP y `config-demo.php`
  bloqueados (403), cookie `secure`, y el webhook rechaza un aviso sin firma (400).

## 7. Limitaciones

- Nada de este guion se ha probado todavía en un celular. Lo verificado en el PC: el sitio de Apache es válido, el
  modo diagnóstico aparece solo en la demo y registra formato y longitud sin el contenido, y las pruebas automáticas pasan.
- La dirección `trycloudflare.com` cambia cada vez que se abre el túnel. Para el webhook de Wompi sandbox conviene una
  dirección fija (un dominio propio en Cloudflare o el dominio gratuito de ngrok).
- Si algún día se agrega un modelo propio de Teachable Machine (`model.json`), el `.htaccess` de UniPark hoy bloquea
  los `.json`: habría que permitir esa carpeta.
- La IA es COCO-SSD genérica: distingue carro, moto y bicicleta, no identifica un vehículo en particular ni su placa.
