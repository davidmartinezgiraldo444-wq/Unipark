@echo off
rem UniPark - Abre la DEMO en tu DOMINIO FIJO de ngrok (doble clic). Sirve para el celular y para el webhook de
rem Wompi sandbox, porque la direccion no cambia entre pruebas (a diferencia de demo-celular.bat).
rem Requisitos: XAMPP con Apache y MySQL encendidos, la demo creada (demo-preparar.bat), ngrok instalado y con tu
rem token registrado (ngrok config add-authtoken ...), y tu dominio gratuito reclamado en dashboard.ngrok.com.
rem El tunel llega SOLO al sitio 127.0.0.1:8090 (scripts\apache\unipark-demo-celular.conf): la demo y nada mas.
chcp 65001 >nul
setlocal
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

set NG=ngrok
where ngrok >nul 2>&1
if errorlevel 1 (
    if exist "%LOCALAPPDATA%\Microsoft\WinGet\Links\ngrok.exe" set NG="%LOCALAPPDATA%\Microsoft\WinGet\Links\ngrok.exe"
    for /d %%p in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\Ngrok.Ngrok_*") do if exist "%%~p\ngrok.exe" set NG="%%~p\ngrok.exe"
)
%NG% version >nul 2>&1
if errorlevel 1 (
    echo.
    echo  No se encontro ngrok. Instalalo con:  winget install --id Ngrok.Ngrok
    pause
    exit /b 1
)

echo.
echo  UniPark - Demo en tu dominio fijo
echo  =================================
for /f "delims=" %%d in ('"%PHP%" "%~dp0demo-celular.php" --dominio 2^>nul') do set DOMINIO=%%d
if "%DOMINIO%"=="" (
    echo  Primera vez: escribe tu dominio de ngrok, el que aparece en dashboard.ngrok.com, seccion Domains.
    echo  Ejemplo: palabra-palabra-1234.ngrok-free.app
    set /p DOMINIO=  Dominio:
)
"%PHP%" "%~dp0demo-celular.php" --fijar-dominio=%DOMINIO%
if errorlevel 1 (
    pause
    exit /b 1
)
for /f "delims=" %%d in ('"%PHP%" "%~dp0demo-celular.php" --dominio') do set DOMINIO=%%d

echo  Verificando el sitio solo-demo (127.0.0.1:8090) y cambiando la contrasena de la demo...
"%PHP%" "%~dp0demo-celular.php"
if errorlevel 1 (
    pause
    exit /b 1
)

rem Un tunel anterior (ngrok o el rapido de Cloudflare) se cierra para no tener dos direcciones abiertas.
taskkill /IM ngrok.exe /F >nul 2>&1
taskkill /IM cloudflared.exe /F >nul 2>&1
set NGLOG=%TEMP%\unipark-ngrok.log
start "" /b %NG% http 127.0.0.1:8090 --domain=%DOMINIO% --log "%NGLOG%" --log-format logfmt >nul 2>&1
timeout /t 4 >nul
findstr /C:"started tunnel" "%NGLOG%" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  ngrok no pudo abrir el tunel. Revisa el registro: %NGLOG%
    echo  Causas comunes: token sin registrar ^(ngrok config add-authtoken ...^) o dominio mal escrito.
    findstr /C:"err=" "%NGLOG%"
    pause
    exit /b 1
)

echo.
echo  ==============================================================================
echo   DIRECCION FIJA (no cambia entre pruebas):
echo.
echo   Celular (acceso1):   https://%DOMINIO%/UniParkDemo/porteria/escaner
echo   Mi cuenta:           https://%DOMINIO%/UniParkDemo/mi-cuenta
echo   Webhook para Wompi:  https://%DOMINIO%/UniParkDemo/api/pasarela/wompi/eventos
echo  ==============================================================================
echo   La primera vez, ngrok muestra en el navegador un aviso "You are about to visit...": pulsa "Visit Site".
echo   Contrasena: la que aparece arriba (Upk-...), para todos los usuarios de la demo.
echo   Registro del tunel: %NGLOG%
echo.
echo  Deja esta ventana abierta mientras pruebas.
echo  Cuando termines, pulsa una tecla aqui para CERRAR el tunel.
pause >nul
taskkill /IM ngrok.exe /F >nul 2>&1
echo  Tunel cerrado.
timeout /t 3 >nul
