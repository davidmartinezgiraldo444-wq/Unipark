@echo off
rem UniPark - Crea las cuentas usuario1, usuario2 y usuario3 de las personas de los carnes impresos.
rem Pide cada contrasena en la ventana (no queda escrita en ningun archivo). Se puede repetir sin duplicar.
setlocal
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
cd /d "%~dp0.."
echo.
echo  UniPark - Cuentas de los carnes impresos
echo  =======================================
echo  Escribe cada contrasena y pulsa Enter.
echo.
"%PHP%" scripts\personas-iniciales.php
echo.
pause
