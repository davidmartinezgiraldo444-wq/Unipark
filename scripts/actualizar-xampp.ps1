# UniPark - Actualiza una copia ya instalada en XAMPP con los archivos de este proyecto.
# Uso (PowerShell, desde la raiz del proyecto):
#   powershell -ExecutionPolicy Bypass -File scripts\actualizar-xampp.ps1
#   powershell -ExecutionPolicy Bypass -File scripts\actualizar-xampp.ps1 -Destino D:\xampp\htdocs\UniPark
#
# Conserva la instalacion: no toca config\env.php (conexion), storage\ (logs,
# comprobantes, respaldos y el bloqueo del instalador) ni la base de datos.
# Los archivos que ya no existen en el proyecto se eliminan de la copia.

param([string]$Destino = 'C:\xampp\htdocs\UniPark')

$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path (Join-Path $Destino 'index.php'))) {
    throw "No se encontro UniPark en $Destino. Para una instalacion nueva, descomprime UniPark.zip en htdocs."
}
if (-not (Test-Path (Join-Path $Destino 'config\env.php'))) {
    Write-Warning "La copia en $Destino aun no esta instalada: al abrirla se mostrara el instalador."
}
if ((Resolve-Path $Destino).Path -eq (Resolve-Path $raiz).Path) {
    throw 'El destino es la misma carpeta del proyecto.'
}

robocopy $raiz $Destino /MIR /NFL /NDL /NJH /NP `
    /XD .git .claude dist storage vendor node_modules `
    /XF .env env.php installed.lock
if ($LASTEXITCODE -ge 8) { throw "robocopy fallo ($LASTEXITCODE)" }

Write-Host ''
Write-Host "UniPark actualizado en $Destino"
Write-Host 'Se conservaron config\env.php, storage\ y la base de datos.'
Write-Host 'En el navegador pulsa Ctrl+F5 si ves estilos anteriores.'
