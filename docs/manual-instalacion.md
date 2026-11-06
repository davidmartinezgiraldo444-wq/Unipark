# Manual de instalación · UniPark

## 1. Requisitos

| Componente | Versión mínima | Probado con |
|---|---|---|
| PHP | 8.1, con `pdo_mysql` y `mbstring` | 8.2.12 (XAMPP) |
| MariaDB o MySQL | MariaDB 10.4 / MySQL 8.0.16 (requieren `CHECK` y columnas generadas) | MariaDB 10.4.32 (XAMPP) |
| Servidor web | Apache 2.4 con `mod_rewrite` y `AllowOverride All` (así viene XAMPP) | Apache de XAMPP |
| Navegador | Chrome, Edge, Firefox o Safari actuales | Edge |

En Windows basta con **XAMPP 8.2**.

## 2. Instalación con el instalador web (recomendada)

1. En el panel de XAMPP, inicia **Apache** y **MySQL**.
2. Descomprime `UniPark.zip` dentro de `C:\xampp\htdocs\`. Debe quedar así:
   ```
   C:\xampp\htdocs\UniPark\index.php
   C:\xampp\htdocs\UniPark\instalar.php
   C:\xampp\htdocs\UniPark\app\ …
   ```
3. Abre <http://localhost/UniPark/>. Como aún no está instalado, UniPark te lleva al instalador.
4. Revisa la lista de requisitos: todos deben aparecer con ✓.
5. Completa el formulario:

   | Campo | Valor en XAMPP | Notas |
   |---|---|---|
   | Servidor | `127.0.0.1` | |
   | Puerto | `3306` | |
   | Nombre de la base de datos | `unipark_db` | Se crea si no existe |
   | Usuario de MySQL | `root` | Debe poder crear bases, tablas y disparadores |
   | Contraseña de MySQL | *(vacía)* | La de tu `root`, si le pusiste una |
   | Crear una cuenta de la aplicación | ✓ (recomendado) | Crea `unipark_app` sin permiso de borrar; la contraseña se genera sola |
   | Cargar datos de demostración | ✓ para pruebas, ✗ para uso real | Usuarios por rol, estudiantes, profesores, visitas y pagos de ejemplo |
   | Reemplazar las tablas existentes | Solo si reinstalas | **Borra** los datos de esa base |

6. Pulsa **Instalar UniPark** y confirma. La pantalla final lista cada paso y las cuentas creadas.
7. Pulsa **Ir a UniPark** e ingresa con `admin` / `Demo2026*`.

### Qué hace el instalador

1. Se conecta a MySQL y verifica la versión.
2. Crea la base con `utf8mb4`.
3. Aplica `database/schema.sql`: tablas, claves, índices, restricciones y disparadores.
4. Aplica `database/base.sql`: roles, permisos, tarifas y el administrador. Si elegiste la demostración, también `database/seed.sql`.
5. Crea la cuenta de la aplicación con `SELECT, INSERT, UPDATE` por tabla (`auditoria`: solo `SELECT, INSERT`).
6. Comprueba que la aplicación se conecta con esa cuenta.
7. Guarda la conexión en `config/env.php` y crea `storage/installed.lock`, que **bloquea el instalador**.

### Reinstalar

Borra `storage/installed.lock` (y, si quieres empezar de cero, `config/env.php`) y vuelve a abrir `http://localhost/UniPark/`. Marca **Reemplazar las tablas existentes** si vas a usar la misma base. **Se pierden los datos de esa base:** saca antes un respaldo.

## 3. Cuentas iniciales y contraseñas

Todas las cuentas iniciales usan **`Demo2026*`**:

| Usuario | Rol |
|---|---|
| `admin` | Administrador |
| `porteria` | Portería (demostración) |
| `caja`, `caja2` | Caja (demostración) |
| `auditor` | Auditor (demostración) |

Para cambiar la contraseña, pulsa **Cambiar contraseña** en la parte inferior del menú. La nueva debe tener al menos 10 caracteres, combinar letras y números y no contener el usuario. **Cámbialas antes de usar UniPark con datos reales.**

## 4. Tarifas iniciales

| Vehículo | Estudiante | Profesor |
|---|---|---|
| Carro | $ 2.050 | $ 2.800 |
| Moto | $ 1.050 | $ 1.800 |

Se modifican desde **Tarifas** (Administrador).

## 5. Instalación por consola (alternativa)

Útil en desarrollo, para preparar también la base de pruebas:

```powershell
cd C:\xampp\htdocs\UniPark
Copy-Item .env.example .env        # edita DB_PASS con una contraseña propia
C:\xampp\php\php.exe scripts\install.php --seed --test
```

| Opción | Efecto |
|---|---|
| *(ninguna)* | Crea `DB_NAME` con `schema.sql` + `base.sql` y la cuenta de la aplicación |
| `--seed` | Carga también los datos de demostración |
| `--test` | Prepara además `TEST_DB_NAME` para las pruebas |
| `--yes` | No pide confirmación si la base ya tiene tablas (**las borra**) |

La consola lee la configuración de `.env`, o de `config/env.php` si existe. También crea `storage/installed.lock`.

Para usar el servidor embebido de PHP en lugar de Apache:

```powershell
C:\xampp\php\php.exe -S localhost:8000 index.php
```

## 6. Actualizar una instalación existente

`http://localhost/UniPark/` abre directamente el **inicio de sesión** (o el panel si ya hay sesión). La página de presentación pública está en `http://localhost/UniPark/inicio`.

Para pasar a `htdocs` los cambios del proyecto **sin perder la instalación**:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\actualizar-xampp.ps1
```

El script copia el código a `C:\xampp\htdocs\UniPark` y conserva:
- `config\env.php`, la conexión a MySQL;
- `storage\`, con los logs, comprobantes, respaldos y el bloqueo del instalador;
- la base de datos.

Para otra ruta: `-Destino D:\xampp\htdocs\UniPark`.

**Alternativa manual:** descomprime el `UniPark.zip` nuevo encima de `htdocs\UniPark` y acepta reemplazar los archivos. El ZIP no trae `config\env.php` ni el bloqueo, así que la instalación se conserva. **No borres la carpeta antes de copiar.**

Si después de actualizar ves estilos anteriores, pulsa **Ctrl + F5**. Los CSS y JS llevan su fecha de modificación (`styles.css?v=…`), así que normalmente el navegador descarga la versión nueva solo.

## 7. Acceso desde Internet con un túnel (ngrok o Cloudflare)

Un túnel publica tu Apache local con una dirección `https://…`. UniPark funciona detrás del túnel: toma la IP real del visitante para la auditoría y el límite de intentos, y marca la cookie de sesión como segura.

```powershell
# ngrok
ngrok http 80
# Cloudflare
cloudflared tunnel --url http://localhost:80
```

Abre la dirección que entrega el túnel **agregando `/UniPark/`** al final, por ejemplo `https://abcd-1234.ngrok-free.app/UniPark/`. El túnel publica todo `htdocs`, no solo UniPark.

**Antes de abrir el túnel:**

1. **Cambia las contraseñas `Demo2026*`** de todas las cuentas: cualquiera con la dirección podría ingresar.
2. **Protege phpMyAdmin.** XAMPP lo permite solo desde el equipo local, pero el túnel entra como si fuera local: con `root` sin contraseña, cualquiera podría abrir `/phpmyadmin` y ver o borrar todas tus bases. Ponle contraseña a `root` (y escríbela en `config\env.php` como `DB_ADMIN_PASS`), o no publiques el puerto 80 mientras phpMyAdmin esté abierto.
3. Cierra el túnel cuando no lo uses (Ctrl + C).

La dirección de ngrok gratuito cambia cada vez que lo inicias, y la primera visita muestra un aviso de ngrok; pulsa **Visit Site**.

## 8. Producción

- Usa **HTTPS**: la cookie de sesión se marca `Secure` automáticamente.
- Instala **sin** datos de demostración y crea las cuentas reales desde **Usuarios**.
- Cambia la contraseña de `admin` en el primer ingreso.
- `config/env.php` guarda también la cuenta administrativa de MySQL, porque la necesitan los respaldos. Si no vas a respaldar desde este servidor, borra de ese archivo las líneas `DB_ADMIN_USER` y `DB_ADMIN_PASS`.
- No expongas XAMPP directamente a Internet (sección 9.2 del documento base).

## 9. Recuperación ante fallos

| Situación | Procedimiento |
|---|---|
| Respaldo periódico | `scripts\backup-db.bat` (o `.sh`); prográmalo con el Programador de tareas de Windows |
| Restaurar | `scripts\restore-db.bat storage\backups\archivo.sql`. Pide escribir el nombre de la base y guarda antes un respaldo del estado actual. |
| Probar la restauración | `php tests/run.php BackupRestore` (CP-14) |
| Copias fuera del equipo | Copia `storage/backups/` a un medio externo con acceso restringido: contiene datos personales |
| Caída del sistema | Protocolo pendiente de definir por la universidad |

## 10. Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| El instalador dice «No se pudo conectar a MySQL» | MySQL no está iniciado en XAMPP, o el usuario o la contraseña no coinciden |
| «La base ya tiene N tablas» | Usa otro nombre de base o marca **Reemplazar las tablas existentes** |
| «No se pudo crear la cuenta de la aplicación» | El usuario no puede crear usuarios (hosting compartido): desmarca esa opción |
| Un requisito aparece en rojo | Activa la extensión en `C:\xampp\php\php.ini` o da permisos de escritura a `config/` y `storage/` |
| 404 en todas las rutas | `mod_rewrite` desactivado o `AllowOverride None`; actívalos en `httpd.conf` |
| «Servicio no disponible» | Revisa `storage/logs/`. Si cambiaste la contraseña de MySQL, actualiza `config/env.php`. |
| Horas desfasadas | Revisa la zona horaria del equipo; UniPark fija `America/Bogota` y `-05:00` |
