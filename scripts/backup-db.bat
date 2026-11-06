@echo off
REM UniPark - Respaldo de la base de datos (Windows / XAMPP).
REM Uso: scripts\backup-db.bat [--db=base] [--out=archivo.sql]
setlocal
set "PHP_BIN=%PHP_BIN%"
if "%PHP_BIN%"=="" set "PHP_BIN=C:\xampp\php\php.exe"
if not exist "%PHP_BIN%" (
  echo No se encontro PHP en %PHP_BIN%. Define la variable PHP_BIN.
  exit /b 1
)
"%PHP_BIN%" "%~dp0backup.php" %*
exit /b %ERRORLEVEL%
