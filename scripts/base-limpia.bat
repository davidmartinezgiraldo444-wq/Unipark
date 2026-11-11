@echo off
rem UniPark - Deja la base del SISTEMA (DB_NAME de config\env.php) limpia, sin datos de demostracion.
rem   1. Respaldo completo en storage\backups (se puede restaurar con restore-db.bat).
rem   2. Reinstala la estructura y los datos base: roles, permisos, tarifas, puesto de porteria y la cuenta admin
rem      (contrasena temporal demo2026*; el sistema obliga a cambiarla en el primer ingreso).
rem   3. Crea las personas de los carnes impresos (mismo documento) y sus cuentas usuario1, usuario2, usuario3.
rem La copia de demostracion (unipark_demo) NO se toca.
setlocal
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
cd /d "%~dp0.."

echo.
echo  UniPark - Base del sistema limpia
echo  =================================
echo  Esto BORRA todo el contenido actual de la base del sistema (antes se guarda un respaldo).
echo  La demo no se toca.
echo.
set /p OK=  Escribe SI para continuar:
if /I not "%OK%"=="SI" (
    echo  Cancelado. No se cambio nada.
    pause
    exit /b 0
)

for /f %%f in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmm"') do set FECHA=%%f
echo.
echo  1/3 Respaldo...
"%PHP%" scripts\backup.php --out=storage\backups\antes-de-base-limpia-%FECHA%.sql
if errorlevel 1 (
    echo  No se pudo hacer el respaldo: no se continua. Revisa que MySQL este encendido.
    pause
    exit /b 1
)

echo.
echo  2/3 Instalacion limpia (sin datos de demostracion)...
"%PHP%" scripts\install.php --yes
if errorlevel 1 (
    echo  La instalacion fallo. Restaura el respaldo con: scripts\restore-db.bat storage\backups\antes-de-base-limpia-%FECHA%.sql
    pause
    exit /b 1
)

echo.
echo  3/3 Personas y cuentas de los carnes impresos (escribe cada contrasena y pulsa Enter)...
"%PHP%" scripts\personas-iniciales.php

echo.
echo  Listo. Entra a http://localhost/UniPark/ como admin / demo2026* : el sistema te pedira una contrasena nueva.
echo  Despues crea desde Usuarios las cuentas del personal (porteria, caja, punto de acceso).
pause
