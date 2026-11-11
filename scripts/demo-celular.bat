@echo off
rem UniPark - Abre la DEMO al celular por un tunel HTTPS de Cloudflare (doble clic).
rem Requisitos: XAMPP con Apache y MySQL encendidos, la demo creada (demo-preparar.bat) y cloudflared instalado
rem   (winget install --id Cloudflare.cloudflared).
rem El tunel llega SOLO al sitio 127.0.0.1:8090 (scripts\apache\unipark-demo-celular.conf): la demo y nada mas.
rem Para cerrar el tunel: Ctrl+C en esta ventana (o cerrarla).
chcp 65001 >nul
setlocal
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

set CF=cloudflared
where cloudflared >nul 2>&1
if errorlevel 1 (
    if exist "%ProgramFiles(x86)%\cloudflared\cloudflared.exe" set CF="%ProgramFiles(x86)%\cloudflared\cloudflared.exe"
    if exist "%ProgramFiles%\cloudflared\cloudflared.exe" set CF="%ProgramFiles%\cloudflared\cloudflared.exe"
)
%CF% --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo  No se encontro cloudflared. Instalalo con:  winget install --id Cloudflare.cloudflared
    pause
    exit /b 1
)

echo.
echo  UniPark - Demo en el celular
echo  ============================
echo  Verificando el sitio solo-demo (127.0.0.1:8090)...
"%PHP%" "%~dp0demo-celular.php"
if errorlevel 1 (
    pause
    exit /b 1
)
rem Un tunel anterior que siga abierto tendria otra direccion: se cierra para no confundirlas.
taskkill /IM cloudflared.exe /F >nul 2>&1
echo.
echo  Abriendo el tunel (unos segundos)...
set CFLOG=%TEMP%\unipark-tunel.log
start "" /b %CF% tunnel --no-autoupdate --metrics 127.0.0.1:20241 --url http://127.0.0.1:8090 --logfile "%CFLOG%" >nul 2>&1
"%PHP%" "%~dp0demo-celular.php" --direccion
echo  Contrasena: la que aparece arriba (Upk-...), para acceso1 y para todos los usuarios de la demo.
echo  Panel del portero en este PC:  http://localhost/UniParkDemo/porteria   (usuario porteria)
echo  Registro del tunel: %CFLOG%
echo.
echo  Deja esta ventana abierta mientras pruebas.
echo  Cuando termines, pulsa una tecla aqui para CERRAR el tunel.
pause >nul
taskkill /IM cloudflared.exe /F >nul 2>&1
echo  Tunel cerrado: la direccion ya no funciona.
timeout /t 3 >nul
