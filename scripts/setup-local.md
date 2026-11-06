# Configuración local (Windows + XAMPP)

> Para desplegar en XAMPP basta con descomprimir `UniPark.zip` en `htdocs` y abrir `http://localhost/UniPark/`: el instalador web hace todo. Estos pasos son para desarrollo con la consola.

Pasos cortos para dejar UniPark funcionando en un equipo de desarrollo. Para la versión detallada, con Apache y solución de problemas, ver [`docs/manual-instalacion.md`](../docs/manual-instalacion.md).

1. **Iniciar MySQL** desde el panel de XAMPP (botón *Start* de MySQL).
2. **Crear `.env`** a partir de la plantilla:
   ```powershell
   Copy-Item .env.example .env
   ```
   Edita `DB_PASS` con una contraseña propia para la cuenta `unipark_app`. Si tu usuario `root` tiene contraseña, escríbela en `DB_ADMIN_PASS`.
3. **Instalar la base** con datos de prueba y la base de pruebas:
   ```powershell
   C:\xampp\php\php.exe scripts\install.php --seed --test
   ```
4. **Iniciar la aplicación:**
   ```powershell
   C:\xampp\php\php.exe -S localhost:8000 index.php
   ```
   Abre <http://localhost:8000>. Cuentas: `admin`, `porteria`, `caja`, `auditor` con la contraseña `Demo2026*`.
5. **Ejecutar las pruebas:**
   ```powershell
   C:\xampp\php\php.exe tests\run.php
   ```

## Respaldos

| Acción | Windows | Bash (Linux, macOS, Git Bash) |
|---|---|---|
| Respaldar | `scripts\backup-db.bat` | `scripts/backup-db.sh` |
| Restaurar | `scripts\restore-db.bat storage\backups\archivo.sql` | `scripts/restore-db.sh storage/backups/archivo.sql` |

La restauración pide escribir el nombre de la base para confirmar y crea antes un respaldo del estado actual. Los respaldos quedan en `storage/backups/`, que Git ignora y Apache no sirve.

### Equivalente en PowerShell sin los scripts

```powershell
& C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --routines --triggers unipark_db --result-file=storage\backups\manual.sql
Get-Content storage\backups\manual.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root unipark_db
```
