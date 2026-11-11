@echo off
rem UniPark - Prepara la copia de DEMOSTRACION y la abre en el navegador (doble clic).
rem Requisitos: XAMPP con Apache y MySQL encendidos desde el Panel de control de XAMPP.
rem No toca el sistema real: solo la base unipark_demo y la carpeta htdocs\UniParkDemo.
chcp 65001 >nul
setlocal
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php

echo.
echo  UniPark - Copia de demostracion
echo  ================================
"%PHP%" "%~dp0demo.php" --estado >nul 2>&1
"%PHP%" "%~dp0demo.php" --estado | findstr /C:"existe" | findstr /V /C:"no existe" >nul
if %errorlevel%==0 (
    "%PHP%" "%~dp0demo.php" --reiniciar
) else (
    "%PHP%" "%~dp0demo.php" --crear
)
if errorlevel 1 (
    echo.
    echo  No se pudo preparar la demo. Revisa que Apache y MySQL esten encendidos en XAMPP.
    pause
    exit /b 1
)
start "" "http://localhost/UniParkDemo/"
echo.
echo  Demo lista. Se abrio http://localhost/UniParkDemo/ en tu navegador.
pause
