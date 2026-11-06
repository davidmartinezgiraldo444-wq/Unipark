@echo off
REM UniPark - Restauracion de un respaldo (Windows / XAMPP).
REM Uso: scripts\restore-db.bat storage\backups\archivo.sql [--db=base]
REM Pide escribir el nombre de la base para confirmar y crea un respaldo previo.
setlocal
if "%~1"=="" (
  echo Uso: scripts\restore-db.bat archivo.sql [--db=base]
  exit /b 1
)
if not exist "%~1" (
  echo No existe el archivo: %~1
  exit /b 1
)
set "PHP_BIN=%PHP_BIN%"
if "%PHP_BIN%"=="" set "PHP_BIN=C:\xampp\php\php.exe"
if not exist "%PHP_BIN%" (
  echo No se encontro PHP en %PHP_BIN%. Define la variable PHP_BIN.
  exit /b 1
)
"%PHP_BIN%" "%~dp0restore.php" %*
exit /b %ERRORLEVEL%
