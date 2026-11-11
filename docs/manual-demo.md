# Manual de la demostración de UniPark

Cómo **instalar, abrir y usar** la copia de demostración para mostrar el sistema completo —entrada, tolerancia, cobro,
recarga de saldo en línea y salida— sin tocar el sistema real y sin dinero real.

---

## 1. Qué es la demo

| | Sistema real | Demostración |
|---|---|---|
| Dirección | `http://localhost/UniPark/` | `http://localhost/UniParkDemo/` |
| Base de datos | `unipark_db` | `unipark_demo` (datos ficticios) |
| Tolerancia | 15 minutos | **1 minuto** («tiempo acelerado», configurable) |
| Pagos en línea | apagados | **pasarela simulada** (no es Wompi; no mueve dinero) |
| Rótulo | — | franja amarilla «MODO DEMOSTRACIÓN» en todas las pantallas |

El código es el mismo de UniPark. La carpeta `htdocs\UniParkDemo` solo tiene tres archivos (`index.php`, `.htaccess`,
`config-demo.php`) que apuntan al código principal con otra configuración.

**Qué es real y qué es simulado:** todo lo que hace UniPark es real (registro de entrada, tolerancia, cobro
automático, saldo, firmas y verificación del pago, acreditación única, libro de movimientos, salida, reportes). Lo
único simulado es la **página de pago** y el **proveedor de pagos**; esa página tiene siempre una franja roja
«SIMULACIÓN — esta página NO es Wompi».

Protecciones: el simulador se niega a funcionar en una instalación de producción y sobre la base `unipark_db`; la
tolerancia acelerada solo se acepta en la demo (el sistema real siempre usa 15 minutos); la demo tiene su propia
cookie de sesión (`UNIPARK_DEMO`), así que nunca comparte sesión con el sistema real.

---

## 2. Requisitos

- Windows con **XAMPP** instalado en `C:\xampp` (Apache, PHP 8.2 y MySQL/MariaDB).
- UniPark instalado en `C:\xampp\htdocs\UniPark` y funcionando (`http://localhost/UniPark/` abre el inicio de sesión).
- Un navegador (Chrome, Edge o Firefox).

---

## 3. Instalar la demo (la primera vez)

1. Abre el **Panel de control de XAMPP** y pulsa **Start** en **Apache** y en **MySQL** (deben quedar en verde).
2. Abre la carpeta `C:\xampp\htdocs\UniPark\scripts` y haz **doble clic en `demo-preparar.bat`**.
   - Crea la base `unipark_demo` con los datos ficticios, la carpeta `C:\xampp\htdocs\UniParkDemo` y la
     configuración con tolerancia de 1 minuto.
   - Al terminar abre `http://localhost/UniParkDemo/` en el navegador.

Alternativa por consola (desde `C:\xampp\htdocs\UniPark`):

```
C:\xampp\php\php.exe scripts\demo.php --crear
```

Verás algo así:

```
UniPark · copia de demostración
  ✓ schema.sql (77 sentencias)
  ✓ base.sql (11 sentencias)
  ✓ seed.sql (28 sentencias)
  ✓ Privilegios mínimos (sin DELETE) en 20 tablas
  ✓ Escenario: Santiago (estudiante2) con un cobro pendiente (1 pendiente, saldo $0)
  ✓ Carpeta C:\xampp\htdocs\UniParkDemo (index.php, .htaccess, config-demo.php)
  ✓ Tolerancia de la demo: 1 min (tiempo acelerado; el sistema real sigue en 15)
```

---

## 4. Abrir la demo

- Dirección: **`http://localhost/UniParkDemo/`**
- Contraseña de todos los usuarios de demostración: **`demo2026*`**

| Usuario | Rol | Para qué sirve en la demo |
|---|---|---|
| `porteria` | Portería | Registrar entradas y autorizar salidas |
| `estudiante3` | Estudiante (Mariana, saldo $1.500) | Ruta completa en vivo |
| `estudiante4` · `estudiante8` · `estudiante10` | Juan José, Tomás y Emiliano (carnés impresos 1000000004, 1000000008, 1000000010) | Mi cuenta de quienes escaneas con las fotocopias |
| `estudiante2` | Estudiante (Santiago, saldo $0, ya con un cobro pendiente) | Ruta corta |
| `estudiante1` | Estudiante (Valentina, saldo $18.000, cobro de hoy ya pagado con su saldo) | Saldo suficiente · salida autorizada · recargas aprobada, rechazada y pendiente |
| `caja` | Caja | Recargar saldo en efectivo y ver cobros pendientes |
| `admin` / `auditor` | Administración / Auditoría | Reportes → Recargas en línea, movimientos y auditoría |

**Consejo:** para la ruta completa necesitas dos personas conectadas a la vez (el portero y la estudiante). Usa **dos
navegadores** (por ejemplo Chrome y Edge) o una ventana normal y una **ventana de incógnito**. En un mismo navegador
solo puede haber una sesión de la demo a la vez.

---

## 5. Recorrido completo en vivo (3 a 4 minutos)

Antes de empezar, reinicia la demo (sección 7) para partir de un estado limpio.

| # | Ventana | Qué haces | Qué ves |
|---|---|---|---|
| 1 | Portero | Entra como `porteria` → **Portería** → busca el documento **1000000003** | Mariana Gómez · saldo $1.500 · sin visita abierta |
| 2 | Portero | Elige **Automóvil** → **Registrar entrada** | «Entrada registrada… 1 min de tolerancia, luego $2.000 del día» |
| 3 | Portero | **Deja la pantalla de Portería abierta** y espera un poco más de 1 minuto | (Portería revisa los cobros cada 20 segundos) |
| 4 | Portero | Vuelve a buscar **1000000003** | Cobro del día **$2.000 · Pendiente** (el saldo de $1.500 no alcanza) |
| 5 | Portero | **Verificar pago y autorizar salida** | **«Pago pendiente. Diríjase a Caja.»** → la salida se deniega |
| 6 | Estudiante | Entra como `estudiante3` → **Mi saldo** | Saldo $1.500 · **Saldo pendiente $2.000** · «Recargar ahora» |
| 7 | Estudiante | **Recargar saldo** → elige **$20.000** → **Continuar** | Recarga «Sin pagar» con su referencia (aún no se mueve dinero) |
| 8 | Estudiante | **Pagar $20.000 en la pasarela** | Página de pago **SIMULADA** (franja roja) |
| 9 | Estudiante | **Aprobar el pago (tarjeta de prueba)** | Vuelve a UniPark: recarga **Aprobada** · saldo **$19.500** (el cobro se pagó solo) |
| 10 | Portero | Busca otra vez **1000000003** → **Autorizar salida** | **«Salida autorizada: Mariana Gómez Duque. La visita quedó CERRADA.»** |

Qué decir en el paso 9: «El simulador envió una notificación firmada; el servidor verificó la firma, consultó la
transacción y solo entonces acreditó el dinero, una sola vez. Con ese saldo pagó el cobro pendiente. La persona sale
sin hacer fila en Caja.»

**Para mostrar la seguridad** (opcional, 1 minuto): entra como **`admin`** a `http://localhost/UniParkDemo/simulador-pago/panel`
(el panel del simulador es solo para Administración) → **Reenviar notificación**. Resultado: `DUPLICADA`; en Mi saldo el saldo no cambia. Otra recarga con **Rechazar el
pago** no suma saldo; una con **Dejar pendiente** se acredita solo cuando en el panel pulsas **Aprobar ahora**.

**Para mostrar la administración:** entra como `auditor` → **Reportes → Recargas en línea** (referencias,
transacciones, estados y la sección de conciliación) y **Movimientos de saldo**.

---

## 6. Recorrido corto (sin esperar)

Si no hay tiempo para esperar el minuto: entra como **`estudiante2`** (Santiago). La demo ya trae su cobro pendiente
de $2.000 con saldo $0. Haz los pasos 6 a 9 (recarga de $20.000 → saldo $18.000) y, si quieres, el paso 10 con su
documento **1000000002**.

---

## 6.1 Escenarios de demostración

Los genera `scripts/demo-escenarios.php` cada vez que se crea o reinicia la demo, **solo en `unipark_demo`**, con los
servicios reales (entrada, cobro automático, recarga, notificación firmada, salida) y con auditoría. Los logs de la
demo van a `storage/logs/demo` y los de las pruebas a `storage/logs/pruebas`; el sistema real escribe en `storage/logs`.

| Escenario | Cómo se ve | Preparado o en vivo |
|---|---|---|
| Estudiante registrado | Portería → documento **1000000003** (Mariana) | En vivo (sección 5) |
| Visitante | Portería o escáner → documento **99887766** → el portero confirma nombre y vehículo; sin cobro | En vivo |
| Saldo suficiente | Portería → **1000000001** (Valentina): cobro del día **pagado con su saldo** | Preparado |
| Saldo insuficiente | Portería → **1000000002** (Santiago): cobro **pendiente**, saldo $0 | Preparado |
| Salida autorizada | Valentina → **Verificar pago y autorizar salida** | Preparado |
| Salida bloqueada | Santiago → la salida se deniega; en el escáner aparece cómo pagar la deuda | Preparado |
| Recarga aprobada | `estudiante1` → **Mi saldo**: $10.000 aprobada y acreditada | Preparado |
| Recarga rechazada | `estudiante1` → **Mi saldo**: $15.000 rechazada, no suma saldo | Preparado |
| Recarga pendiente | `estudiante1` → $20.000 pendiente; `admin` → panel del simulador → **Aprobar ahora** o **Rechazar** | Preparado, se resuelve en vivo |
| Clasificación correcta | Escáner → carné → la cámara reconoce el vehículo con confianza suficiente | En vivo (requiere cámara) |
| Clasificación de baja confianza | Escáner → carné → apuntar la cámara a algo que no sea un vehículo: el portero recibe la alerta **Requiere intervención** y elige Carro, Moto o Bicicleta | En vivo (requiere cámara) |

El portero ve en **Portería → En tiempo real** a cada persona que se identifica en el escáner (solo lectura) y
**confirma cada detección de la IA** (regla del 10/11/2026):

- **La IA identifica el vehículo** (80 % o más): al portero le llega **Confirmar vehículo** con el botón
  **Confirmar: Automóvil** y, debajo, Carro / Moto / Bicicleta por si hay que cambiarlo. El celular muestra
  «Espera un momento · Vehículo detectado: Automóvil. Un portero lo está confirmando.» (fondo blanco).
- **La IA no lo identifica**: **Requiere intervención** con los tres botones. El celular muestra «No se pudo
  identificar el vehículo. Un portero lo está verificando.»
- Nada se registra hasta que el portero decide. Si confirma, queda «IA · confirmado por Portería»; si cambia el tipo,
  queda como clasificación manual. La persona que se identifica nunca elige la categoría.
- Para volver al registro automático sin portero: `IA_CONFIRMAR_PORTERO=0` en la configuración.

**Carnés impresos de prueba:** Juan José Arango (1000000004, usuario `estudiante4`), Tomás Cardona (1000000008,
`estudiante8`) y Emiliano Castaño (1000000010, `estudiante10`). Sus personas vienen de la semilla y sus cuentas las
crea cada reinicio de la demo, así que las fotocopias siguen sirviendo.

**Formatos de identificación** que acepta el escáner: Code 128, Code 39, Code 93, Codabar, ITF, EAN, UPC, QR,
Data Matrix y PDF417 por cámara; lector USB o Bluetooth que escribe como teclado; digitación; y **OCR** (botón «Leer
número impreso»: lee el número de una foto y lo deja en el campo para que el portero lo confirme). Los carnés
ficticios imprimibles (Code 128, QR y PDF417) están en **Portería → Carnets de prueba**.

### Limitaciones conocidas (no probadas en hardware real)

- La cámara del celular, el OCR con la foto de un documento real y la IA con vehículos reales **no se han probado**
  en este equipo. El OCR se verificó con texto generado en pantalla; los carnés se verificaron decodificándolos.
- La IA es un modelo **genérico** (COCO-SSD) que corre en el navegador: distingue carro, moto y bicicleta, no
  identifica un vehículo en particular ni su placa. El servidor no confía a ciegas: aplica un umbral y, si la
  confianza es baja, pasa la decisión al portero. La confianza la declara el navegador y queda en la auditoría.
- El PDF417 de los carnés usa un formato propio marcado `UNIPARK-FICTICIO`; el de la cédula real no está confirmado.

---

## 7. Reiniciar la demo (antes de cada presentación)

Doble clic en `scripts\demo-preparar.bat` (si la demo ya existe, la reinicia), o por consola:

```
C:\xampp\php\php.exe scripts\demo.php --reiniciar
```

Borra y recrea **solo** la base `unipark_demo` y los pagos simulados; el sistema real no se toca.

Otras opciones:

```
C:\xampp\php\php.exe scripts\demo.php --estado                    (cómo está la demo)
C:\xampp\php\php.exe scripts\demo.php --reiniciar --tolerancia=3  (tolerancia de 3 minutos; de 1 a 15)
```

---

## 8. Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `demo-preparar.bat` dice que no pudo preparar la demo | Apache o MySQL apagados: enciéndelos en el Panel de XAMPP |
| `http://localhost/UniParkDemo/` no abre | La demo no está creada: ejecuta `demo-preparar.bat` |
| La página se ve sin estilos | Abre la dirección con la barra final: `http://localhost/UniParkDemo/` |
| A los 2 minutos el cobro no aparece | El cobro lo procesa la pantalla de **Portería** (o **Caja**) abierta. Déjala abierta o recárgala |
| Al iniciar como estudiante se cerró la sesión del portero | Es la misma ventana: usa dos navegadores o una ventana de incógnito |
| «Ya tienes recargas sin terminar» | Hay 3 recargas sin pagar: cancélalas desde Mi saldo o reinicia la demo |
| La recarga quedó «Pago en proceso» | Elegiste «Dejar pendiente»: apruébala en el Panel del simulador |

---

## 9. Quitar la demo

La demo no afecta al sistema real, pero si quieres eliminarla:

1. En phpMyAdmin (`http://localhost/phpmyadmin`) elimina la base **`unipark_demo`**.
2. Borra la carpeta **`C:\xampp\htdocs\UniParkDemo`**.
3. (Opcional) Borra la carpeta `C:\xampp\htdocs\UniPark\storage\simulador`.

---

## 10. De la demo a Wompi real (cuando tengas la cuenta)

El código de Wompi ya está en UniPark (`app/Services/Pasarela/PasarelaWompi.php`). Para probarlo en sandbox **en la
copia de demostración** (nunca en `unipark_db`):

1. Crea la cuenta de comercio en Wompi y copia las 4 llaves de **pruebas** (`pub_test_…`, `prv_test_…`,
   `test_events_…`, `test_integrity_…`).
2. En `C:\xampp\htdocs\UniParkDemo\config-demo.php` cambia `PASARELA_PROVEEDOR` a `WOMPI` y agrega
   `WOMPI_ENTORNO => sandbox` y las cuatro llaves (`WOMPI_LLAVE_PUBLICA`, `WOMPI_LLAVE_PRIVADA`,
   `WOMPI_SECRETO_EVENTOS`, `WOMPI_SECRETO_INTEGRIDAD`). No subas ese archivo a ningún repositorio.
   `demo.php --reiniciar` **conserva** esa configuración; para volver al simulador, cambia de nuevo
   `PASARELA_PROVEEDOR` a `SIMULADO`.
3. Para recibir las notificaciones de Wompi en tu computador necesitas una dirección HTTPS pública (un túnel como
   ngrok o Cloudflare Tunnel) y registrar en el panel de Wompi la URL `…/UniParkDemo/api/pasarela/wompi/eventos`.
   Sin túnel también se puede probar: la conciliación consulta los pagos a Wompi desde tu computador. Ejecútala
   **con la configuración de la demo** (en una consola `cmd`, desde `C:\xampp\htdocs\UniPark`):

   ```
   set UNIPARK_ENV_INSTANCIA=C:\xampp\htdocs\UniParkDemo\config-demo.php
   C:\xampp\php\php.exe scripts\conciliar-recargas.php
   ```
4. Paga con los datos de prueba de Wompi: tarjeta `4242 4242 4242 4242` (aprobada), `4111 1111 1111 1111`
   (rechazada); en PSE, «Banco que aprueba» / «Banco que rechaza».

Detalle técnico completo: `docs/recargas-en-linea.md`. Guion de presentación: `docs/demo-recargas.md`.
