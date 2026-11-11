# Despliegue en producción · UniPark

Guía para instalar UniPark en un servidor Linux con HTTPS. El entorno de desarrollo (XAMPP en Windows) se
describe en `manual-instalacion.md`. Nada de este documento contiene contraseñas reales: los valores entre
`<...>` los define la institución.

> **Validado hasta ahora:** instalación limpia en una base nueva (`unipark_prueba_limpia`) con MariaDB 10.4.32 y
> PHP 8.2.12, 147 pruebas automáticas aprobadas e importación de personas por consola. **No validado:** un
> servidor Linux real, el certificado HTTPS y el cron de Linux. Repite la lista de verificación de la sección 12
> en el servidor definitivo antes de abrir el servicio.

## 1. Requisitos del servidor

| Componente | Mínimo | Notas |
|---|---|---|
| Sistema | Linux (Ubuntu 22.04/24.04 LTS, Debian 12 o similar) | Cualquier hosting con Apache y PHP 8.2 sirve si permite `.htaccess` y cron |
| Servidor web | Apache 2.4 con `mod_rewrite` y `AllowOverride All` | Nginx sirve, pero hay que traducir a mano las reglas de `.htaccess` (sección 4.3) |
| PHP | 8.2 o superior | Extensiones: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`, `fileinfo` |
| Base de datos | MariaDB 10.4+ o MySQL 8.0.16+ | Necesita `CHECK`, columnas generadas y disparadores |
| Herramientas | `mysqldump` y `mysql` en el PATH | Para respaldos y restauración |
| HTTPS | Certificado válido (Let's Encrypt u otro) | **Obligatorio**: la cámara del celular, los avisos push y la cookie `Secure` exigen HTTPS |
| Hora | Zona `America/Bogota` en PHP y NTP activo | El cobro diario depende de la fecha local |

Configuración recomendada de PHP (`/etc/php/8.2/apache2/conf.d/99-unipark.ini`):

```ini
display_errors = Off
log_errors = On
expose_php = Off
date.timezone = America/Bogota
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
upload_max_filesize = 12M      ; importación CSV de la base institucional (máximo 10 MB)
post_max_size = 14M
memory_limit = 256M
max_execution_time = 120
```

Configuración recomendada de MariaDB/MySQL (`/etc/mysql/mariadb.conf.d/99-unipark.cnf`):

```ini
[mysqld]
bind-address = 127.0.0.1            ; la base no se expone a la red
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
default-time-zone = '-05:00'
sql_mode = STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
innodb_flush_log_at_trx_commit = 1  ; los cobros y movimientos de saldo son transaccionales
```

## 2. Instalación paso a paso (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install apache2 mariadb-server php8.2 libapache2-mod-php8.2 php8.2-mysql php8.2-mbstring php8.2-curl php8.2-xml unzip
sudo a2enmod rewrite headers ssl
sudo mysql_secure_installation
```

1. Copia el proyecto a `/var/www/unipark` (sin `tests/` si no se van a correr pruebas en el servidor, sin
   `.env`, sin `config/env.php` y sin `storage/backups` del equipo de desarrollo). `scripts/empaquetar.ps1`
   genera un paquete limpio desde Windows.
2. Propietario y permisos:
   ```bash
   sudo chown -R root:www-data /var/www/unipark
   sudo find /var/www/unipark -type d -exec chmod 750 {} \;
   sudo find /var/www/unipark -type f -exec chmod 640 {} \;
   sudo chown -R www-data:www-data /var/www/unipark/storage
   sudo chmod -R 770 /var/www/unipark/storage
   # Solo durante la instalación web (luego vuelve a 750):
   sudo chown www-data /var/www/unipark/config && sudo chmod 770 /var/www/unipark/config
   ```
3. Cuenta administrativa de la base para la instalación (no es la de la aplicación):
   ```sql
   CREATE USER '<admin_unipark>'@'localhost' IDENTIFIED BY '<contraseña>';
   GRANT ALL PRIVILEGES ON unipark_db.* TO '<admin_unipark>'@'localhost' WITH GRANT OPTION;
   GRANT CREATE USER, RELOAD, PROCESS ON *.* TO '<admin_unipark>'@'localhost';
   ```
4. Instala **sin datos de demostración** (elige una opción):
   - **Web:** abre `https://<dominio>/instalar.php`, deja desmarcado «datos de demostración» y completa el
     formulario. Escribe `config/env.php` y bloquea el instalador (`storage/installed.lock`).
   - **Consola:** copia `.env.production.example` como `.env`, completa los valores y ejecuta
     `php scripts/install.php` (sin `--seed`).
5. Devuelve los permisos de `config/` (`chmod 750`, propietario `root`) y verifica que `config/env.php` tenga `640`.
6. Entra como `admin`, cambia la contraseña de inmediato y crea las cuentas reales en **Usuarios**.

El instalador crea la cuenta de la aplicación (`DB_USER`) con **SELECT, INSERT y UPDATE** y sin `DELETE`. En
las tablas de solo inserción (`auditoria`, `movimientos_saldo`, `migraciones`, `importaciones`) recibe solo
`SELECT, INSERT`. Los disparadores impiden además borrar cobros, eventos de acceso o importaciones.

## 3. Variables de configuración

Plantilla: `.env.production.example`. Lo indispensable:

| Variable | Producción |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `produccion` / `0` |
| `DB_USER` / `DB_PASS` | Cuenta propia de la aplicación, contraseña aleatoria larga |
| `DB_ADMIN_USER` / `DB_ADMIN_PASS` | Solo si los respaldos o migraciones corren en este servidor; si no, vacías |
| `CRON_TOKEN` | Vacío (se usa cron de consola). Solo en hostings sin cron: 32 caracteres o más |
| `IA_UMBRAL` / `IA_MARGEN` | 0.80 / 0.25 para empezar; ajustar con el reporte de calibración |
| `IA_CAPTURA_PORTERO` | `0` salvo decisión de la institución (sección 9) |
| `PUSH_SUBJECT` | `mailto:` real del área responsable |
| `SESSION_IDLE_*` | Personal 15 min; Portería 480; dispositivos fijos 720 |

## 4. Apache y HTTPS

### 4.1 VirtualHost

```apache
<VirtualHost *:80>
    ServerName <dominio>
    Redirect permanent / https://<dominio>/
</VirtualHost>

<VirtualHost *:443>
    ServerName <dominio>
    DocumentRoot /var/www/unipark
    <Directory /var/www/unipark>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/<dominio>/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/<dominio>/privkey.pem
    Header always set Strict-Transport-Security "max-age=31536000"
    ErrorLog  ${APACHE_LOG_DIR}/unipark-error.log
    CustomLog ${APACHE_LOG_DIR}/unipark-access.log combined
</VirtualHost>
```

Certificado: `sudo apt install certbot python3-certbot-apache && sudo certbot --apache -d <dominio>`.

### 4.2 Lo que ya protege la aplicación

- `.htaccess` niega `app/`, `config/`, `database/`, `storage/`, `tests/`, `docs/`, `scripts/`, archivos ocultos y
  `.sql/.md/.env/.log/.json`. Cada carpeta interna tiene además su propio `Require all denied`.
- Cabeceras: CSP (`script-src 'self'`; `'unsafe-eval'` solo en las pantallas de cámara, por TensorFlow.js),
  `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`. Las librerías de CDN (TensorFlow.js, COCO-SSD,
  Teachable Machine, ZXing, JsBarcode) se cargan con versión fija y **SRI** (`integrity`).
- Sesión `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS, regeneración al iniciar sesión, CSRF en todo POST.

### 4.3 Nginx (si no hay Apache)

Traduce: raíz `/var/www/unipark`; `location ~ ^/(app|config|database|storage|tests|docs|scripts)/ { deny all; }`;
`location ~ /\. { deny all; }`; `location ~ \.(sql|md|env|log|ini|sh|bat|json|txt)$ { deny all; }` (excepto
`manifest.webmanifest`); `try_files $uri /index.php?$query_string;`.

## 5. Base de datos y migraciones

- Instalación nueva: `schema.sql` + `base.sql` ya contienen todas las migraciones (verificado: una base
  nueva queda idéntica a una migrada).
- Actualizar una instalación existente: `php scripts/migrar.php` (hace un respaldo con `mysqldump` antes,
  aplica solo lo pendiente y registra cada archivo en `migraciones`). `--estado` muestra qué falta.
  **Nunca** uses `--demo` en producción (crea usuarios de prueba con contraseña conocida).
- Cambios futuros: archivo nuevo `database/migrations/AAAAMMDD_HHMM_descripcion.sql`; nunca editar uno aplicado.

## 6. Cobro automático (cron de Linux)

El cobro diario se genera al vencer la tolerancia de 15 minutos. Debe correr **cada minuto**:

```bash
sudo crontab -u www-data -e
# UniPark: cobros vencidos y eventos de acceso sin atender
* * * * * /usr/bin/php /var/www/unipark/scripts/cobros.php >> /var/www/unipark/storage/logs/cron.log 2>&1
```

- Es idempotente: ejecutarlo dos veces a la vez no duplica cobros ni débitos (probado con ejecuciones
  concurrentes). También expira los eventos de acceso sin atender (10 minutos).
- Si el cron se detiene, el panel muestra «Cobros sin procesar» y la salida registra una incidencia en lugar
  de cobrar: la salida **nunca** cobra.
- Rotación del log: `/etc/logrotate.d/unipark` con `/var/www/unipark/storage/logs/*.log { weekly rotate 8 compress missingok }`.
- Solo si el hosting no permite cron de consola: define `CRON_TOKEN` (32+ caracteres) y programa una llamada
  HTTPS a `/cron/cobros?token=…` cada minuto desde el panel del hosting. No publiques esa URL.

## 7. Notificaciones push

- Las claves VAPID se generan solas la primera vez en `storage/push/vapid.php` (fuera del alcance web).
  **Respáldalas**: si se pierden, todos los celulares deben volver a activar los avisos.
- Solo se envía a servicios push conocidos (FCM, Mozilla, Apple, Windows) por HTTPS: protección contra SSRF.
- Requisito del celular: HTTPS, navegador compatible y, en iPhone, la app instalada en la pantalla de inicio
  (iOS 16.4+). **Pendiente de verificar con celulares reales.**

## 8. Roles y cuentas

| Rol | Uso | Pantalla inicial |
|---|---|---|
| Administrador | Tarifas, usuarios, anulaciones, reversos, importación, reportes | Panel |
| Portería | Panel de avisos (resuelve eventos que la IA no confirmó), entrada y salida manual | Portería |
| Punto de acceso | Celular o tablet fijo en el carril: solo el escáner (`ACCESO_KIOSCO`) | Escáner |
| Caja | Recargas, cobros pendientes, cierre de caja | Caja |
| Auditor | Reportes y auditoría, solo lectura | Reportes |
| Estudiante / Profesor / Trabajador | Su cuenta: saldo, cobros, avisos | Mi cuenta |

Crea **una cuenta «Punto de acceso» por dispositivo** (por ejemplo `acceso-norte`), nunca la de un portero.
Así cada evento queda atribuido al equipo que lo generó y el dispositivo no puede ver reportes ni datos completos.

## 9. Dispositivos y cámaras

| Dispositivo | Uso | Requisitos |
|---|---|---|
| Celular o tablet del punto de acceso | Lee el carné/cédula y, en modo «un equipo», también clasifica el vehículo | Android 10+ con Chrome actual o iPhone con iOS 16+ y Safari; cámara trasera con autofoco; HTTPS |
| Segundo celular («puesto de cámara») | Apunta al carril y publica solo el tipo de vehículo | Igual; fijo, con carga permanente y buena luz |
| Equipo de portería | Panel de avisos con los eventos pendientes | Cualquier navegador actual |

- La IA corre **en el navegador**: al servidor llega solo el tipo y la confianza, nunca video. El servidor
  vuelve a validar umbral, margen y tipos habilitados.
- Opcional `IA_CAPTURA_PORTERO=1`: se guarda una foto del carril para el portero mientras el evento está
  pendiente y se borra al resolverlo. Requiere aprobación de la institución (datos personales).
- La identificación individual del vehículo (placa) **no está implementada**: el sistema reconoce solo la
  categoría. La interfaz `IdentificadorVehiculo` deja preparado el punto de integración.

## 10. Importación de la base institucional

1. Exporta desde el sistema institucional un CSV (UTF-8, separado por `;` o `,`) con, como mínimo,
   documento, nombres, apellidos y categoría. Ejemplo: `docs/ejemplos/personas-ejemplo.csv` (datos ficticios).
2. Si los encabezados o los valores de categoría son distintos, agrégalos en `config/app.php` →
   `importacion.columnas` / `importacion.categorias`. No hace falta tocar el código.
3. Valida primero (no guarda nada): **Personas → Importar base institucional → Solo validar**, o
   `php scripts/importar-personas.php archivo.csv`.
4. Revisa las filas rechazadas (fila, documento enmascarado y motivo) y corrige el archivo.
5. Aplica: misma pantalla con «Aplicar», o `php scripts/importar-personas.php archivo.csv --aplicar`.
   Puedes programarlo en cron (por ejemplo cada noche) apuntando al archivo que deje la institución.

Reglas: la persona se reconoce por documento; se crean las nuevas y se actualizan las existentes; nunca se
borra a nadie, nunca se toca el saldo y no se desactiva a quien no venga en el archivo (decisión pendiente);
un campo opcional vacío no borra el dato existente; un carné asignado a otra persona se rechaza; todo se
aplica en una sola transacción y queda en `importaciones` y en la auditoría. El archivo subido se borra al
terminar. Código de salida del script: 0 sin rechazos, 3 con rechazos, 1/2 error.

## 11. Respaldos, restauración y actualización

- Respaldo diario (cron como root o la cuenta que tenga la clave administrativa):
  `30 2 * * * /usr/bin/php /var/www/unipark/scripts/backup.php >> /var/www/unipark/storage/logs/backup.log 2>&1`
  y copia `storage/backups/` y `storage/push/vapid.php` fuera del servidor. Los respaldos tienen datos
  personales: acceso restringido y cifrado en el destino.
- Restaurar: `php scripts/restore.php storage/backups/<archivo>.sql` (pide confirmación). Prueba la
  restauración en una base aparte al menos una vez al mes.
- Actualizar a una versión nueva:
  1. Avisa y elige una hora de poco tráfico.
  2. `php scripts/backup.php`.
  3. Copia los archivos nuevos sin pisar `config/env.php`, `.env` ni `storage/`.
  4. `php scripts/migrar.php --estado` y luego `php scripts/migrar.php`.
  5. Prueba ingreso, salida, recarga y reporte con una cuenta de cada rol.
  6. Si algo falla: restaura el respaldo y vuelve a los archivos anteriores.

## 12. Lista de verificación antes de abrir el servicio

- [ ] `https://<dominio>` con certificado válido; `http://` redirige a HTTPS.
- [ ] `https://<dominio>/config/env.php`, `/.env`, `/database/schema.sql`, `/storage/logs/` y `/scripts/cobros.php` responden **403/404**.
- [ ] `APP_DEBUG=0`; un error interno muestra la página genérica, no el detalle.
- [ ] Base instalada **sin** datos de demostración; no existen `porteria`, `caja`, `acceso1`, `estudiante1`… (`php scripts/migrar.php --estado` no muestra migraciones `_demo_` aplicadas).
- [ ] Contraseña de `admin` cambiada; cada persona del personal tiene su propia cuenta.
- [ ] La cuenta de la aplicación no puede borrar (`DELETE` responde error 1142).
- [ ] Cron de cobros activo: `storage/logs/cron.log` crece cada minuto y el panel muestra «Cobro automático al día».
- [ ] Respaldo diario programado y una restauración probada en otra base.
- [ ] Tarifas reales cargadas en **Tarifas**.
- [ ] Base institucional validada e importada.
- [ ] Un celular real: escanea un carné y una cédula reales, clasifica carro, moto y bicicleta en el carril
      real y recibe un aviso push.
- [ ] El panel de portería recibe un evento de baja confianza y lo resuelve.
- [ ] `IA_UMBRAL` revisado con las primeras semanas del reporte «Calibración de la IA».

## 13. Decisiones pendientes de la institución

| Tema | Comportamiento actual |
|---|---|
| Patineta (scooter) | Deshabilitada (`tipos_habilitados`); el dato sigue en la base |
| Personas ausentes del archivo institucional | No se desactivan |
| Foto del carril para el portero | Apagada (`IA_CAPTURA_PORTERO=0`) |
| Visitantes en el punto de acceso | Los admite o rechaza el portero |
| Tiempo para expirar un evento sin atender | 10 minutos (`eventos_expiran_min`) |
| Reporte de caja para el rol Caja | Caja no ve los reportes de cobros/saldos (solo Administrador y Auditor) |
