@echo off
rem UniPark - Programa las TAREAS AUTOMATICAS en el Programador de tareas de Windows (doble clic).
rem   Sin parametros: crea (o actualiza) las tareas.      cobros-programar.bat quitar : las elimina.
rem Tareas (carpeta "UniPark" del Programador de tareas):
rem   UniPark\Cobros        cada minuto    sistema real  (scripts\cobros.php)
rem   UniPark\CobrosDemo    cada minuto    demostracion  (scripts\cobros-demo.php; nada si no hay demo)
rem   UniPark\Recargas      cada 5 minutos sistema real  (scripts\conciliar-recargas.php; nada si no hay pasarela)
rem   UniPark\RecargasDemo  cada 5 minutos demostracion  (scripts\conciliar-recargas-demo.php)
rem Usan php-win.exe (no abren ventana). Corren mientras tu sesion de Windows este iniciada.
rem Son idempotentes: no duplican cobros ni acreditaciones.
chcp 65001 >nul
setlocal
set PHPW=C:\xampp\php\php-win.exe
set DIR=%~dp0

if /I "%~1"=="quitar" (
    schtasks /Delete /TN "UniPark\Cobros" /F >nul 2>&1
    schtasks /Delete /TN "UniPark\CobrosDemo" /F >nul 2>&1
    schtasks /Delete /TN "UniPark\Recargas" /F >nul 2>&1
    schtasks /Delete /TN "UniPark\RecargasDemo" /F >nul 2>&1
    echo  Tareas automaticas de UniPark eliminadas.
    pause
    exit /b 0
)

if not exist "%PHPW%" (
    echo  No se encontro %PHPW%. Revisa la instalacion de XAMPP.
    pause
    exit /b 1
)

schtasks /Create /F /SC MINUTE /MO 1 /TN "UniPark\Cobros" /TR "\"%PHPW%\" \"%DIR%cobros.php\"" >nul
if errorlevel 1 ( echo  No se pudo crear la tarea UniPark\Cobros. & pause & exit /b 1 )
schtasks /Create /F /SC MINUTE /MO 1 /TN "UniPark\CobrosDemo" /TR "\"%PHPW%\" \"%DIR%cobros-demo.php\"" >nul
if errorlevel 1 ( echo  No se pudo crear la tarea UniPark\CobrosDemo. & pause & exit /b 1 )
schtasks /Create /F /SC MINUTE /MO 5 /TN "UniPark\Recargas" /TR "\"%PHPW%\" \"%DIR%conciliar-recargas.php\"" >nul
if errorlevel 1 ( echo  No se pudo crear la tarea UniPark\Recargas. & pause & exit /b 1 )
schtasks /Create /F /SC MINUTE /MO 5 /TN "UniPark\RecargasDemo" /TR "\"%PHPW%\" \"%DIR%conciliar-recargas-demo.php\"" >nul
if errorlevel 1 ( echo  No se pudo crear la tarea UniPark\RecargasDemo. & pause & exit /b 1 )

echo.
echo  Tareas automaticas programadas:
echo    UniPark\Cobros        cada minuto     (cobro del sistema real)
echo    UniPark\CobrosDemo    cada minuto     (cobro de la demostracion)
echo    UniPark\Recargas      cada 5 minutos  (conciliacion de recargas del sistema real)
echo    UniPark\RecargasDemo  cada 5 minutos  (conciliacion de recargas de la demostracion)
echo  Requiere MySQL encendido en XAMPP; si esta apagado, cada tarea espera a su siguiente turno.
echo  Para quitarlas:  cobros-programar.bat quitar
echo.
schtasks /Query /TN "UniPark\Cobros" /FO LIST | findstr /C:"Estado" /C:"Status" /C:"Next Run"
pause
