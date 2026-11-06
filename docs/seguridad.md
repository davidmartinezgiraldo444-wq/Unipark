# Seguridad y privacidad · UniPark

Corresponde a la sección 10 del documento base. Cada control indica dónde está implementado y qué prueba lo verifica.

| Control | Medida implementada | Código | Prueba |
|---|---|---|---|
| Autenticación | `password_hash()` / `password_verify()`; rehash automático; mensaje genérico; hash ficticio cuando el usuario no existe, para no revelarlo por el tiempo de respuesta | `AuthService::intentar` | `PermisosTest::testMensajeGenerico…` |
| Contraseñas | Mínimo 10 caracteres, letras y números, sin el usuario; temporales obligadas a cambiarse | `AuthService::validarPolitica` | `ValidacionVisitaTest::testPoliticaDeContrasenas` |
| Abuso de acceso | 5 fallos por usuario (o 15 por IP) en 5 minutos bloquean el ingreso; los intentos se auditan | `AuthService`, `AuditoriaRepository::fallidosRecientes` | `PermisosTest::testMensajeGenericoYLimiteDeIntentos` |
| Autorización | Cada ruta declara su permiso; `RoleMiddleware` deniega por defecto y audita | `routes.php`, `RoleMiddleware` | `PermisosTest::testCP08…` |
| Estado de la cuenta | Usuario y permisos leídos de la base en cada solicitud: desactivar o cambiar el rol surte efecto de inmediato | `AuthMiddleware` | `PermisosTest::testCuentaDesactivada…` |
| Sesiones | ID regenerado al autenticar; cookie `HttpOnly`, `SameSite=Strict`, `Secure` con HTTPS; `use_strict_mode`; expiración por inactividad; logout que destruye la sesión | `Session`, `AuthMiddleware` | `PermisosTest::testRN14…`, `testLoginCorrecto…` |
| CSRF | Token por sesión exigido en **todo** `POST`, incluido el login; se renueva al autenticar | `Csrf`, `Kernel` | `CsrfTest` (CP-12) |
| Inyección SQL | PDO con preparaciones nativas (`EMULATE_PREPARES=false`); comodines de `LIKE` escapados; filtros de reportes validados contra listas cerradas | `Repository` | `SqlInjectionTest` (CP-11) |
| XSS | Toda salida pasa por `e()` (`htmlspecialchars`); `Content-Security-Policy` sin scripts en línea | `functions.php`, `Response::send` | `SqlInjectionTest::testXss…` |
| Cabeceras | `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Cache-Control: no-store` | `Response::send` | — |
| Errores | Mensaje genérico al usuario; detalle técnico en `storage/logs` | `Kernel`, `Logger` | `SqlInjectionTest::testErroresInternos…` |
| Mínimo privilegio | La cuenta de la aplicación solo tiene `SELECT, INSERT, UPDATE` (auditoría: `SELECT, INSERT`); nunca `root` | `scripts/install.php` | `AuditoriaTest::testLaCuentaDeLaAplicacion…` |
| Integridad del historial | Disparadores impiden borrar visitas, pagos y auditoría, y editar tarifas aplicadas o pagos confirmados, incluso a un administrador de la base | `schema.sql` | `PagoTest`, `SalidaTest`, `AuditoriaTest` |
| Auditoría | Cambios de tarifa, pagos, anulaciones, salidas denegadas, accesos denegados, CSRF inválido, exportaciones y acciones administrativas | `AuditService` | `AuditoriaTest` (CP-13) |
| Exportación | CSV solo con permiso `REPORTE_EXPORTAR`, documento enmascarado, protección contra inyección de fórmulas y registro en auditoría | `ReporteController::exportar`, `Response::csv` | — |
| Secretos | La conexión se guarda en `config/env.php`, un archivo PHP: aunque el servidor ignorara los `.htaccess`, pedirlo por la web no muestra su contenido. Ignorado por Git y excluido del ZIP | `Instalador`, `.gitignore`, `empaquetar.ps1` | Verificado en Apache: 403 |
| Carpetas internas | `.htaccess` raíz con reglas de reescritura y un `.htaccess` «Require all denied» en `app`, `config`, `database`, `storage`, `tests`, `docs` y `scripts`; archivos `.sql`, `.md`, `.env`, `.log` y ocultos denegados | `.htaccess` | Verificado en Apache: 403 en 17 rutas internas |
| Instalador | Se bloquea con `storage/installed.lock` al terminar; después responde 403. Exige token CSRF y valida cada campo antes de ejecutar SQL | `instalar.php`, `Instalador` | Verificado en Apache |
| Respaldos | `mysqldump --single-transaction` con disparadores; contraseña pasada en un archivo temporal, no en la línea de comandos; restauración con confirmación y respaldo previo | `scripts/` | `BackupRestoreTest` (CP-14) |

## Privacidad de datos personales

- **Datos mínimos:** documento, nombres y apellidos. El correo y el teléfono son opcionales. No se registra ningún dato del vehículo ni datos de tarjetas.
- **Visibilidad limitada:** los listados, reportes, comprobantes y exportaciones muestran el documento enmascarado (`••••••0007`). Solo quien gestiona estudiantes lo ve completo, y Portería lo ve porque es quien lo digita.
- **Acceso por rol:** Caja solo ve sus propios comprobantes; el historial requiere `VISITA_VER`.
- **Sin borrado operativo:** la eliminación de datos personales al terminar el periodo de retención debe definirla la institución y hacerse por un procedimiento controlado fuera de la operación diaria.

El tratamiento de datos personales debe revisarse frente a la política institucional y la **Ley 1581 de 2012** y sus decretos reglamentarios. Este documento técnico no sustituye esa revisión jurídica.

## Riesgos conocidos y pendientes

| Tema | Situación |
|---|---|
| HTTPS | Lo aporta el servidor; la aplicación marca la cookie `Secure` cuando lo detecta. En el servidor embebido de desarrollo no hay HTTPS. |
| Contraseñas iniciales | Todas las cuentas inician con `Demo2026*`, conocida. Deben cambiarse antes de operar con datos reales; el instalador y el manual lo advierten. |
| Recuperación de contraseña | La hace el Administrador con contraseña temporal; no hay recuperación por correo (no aprobada). |
| Cifrado de respaldos | `storage/backups/` está protegido, pero los archivos no se cifran. Recomendado al copiarlos fuera del servidor. |
| Retención de datos | Pendiente de la política institucional. |
| Fuentes tipográficas | Se cargan de Google Fonts; para operar sin Internet, descargarlas a `assets/` y ajustar la CSP. |
